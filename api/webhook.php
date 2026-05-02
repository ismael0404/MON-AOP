<?php
// ═══════════════════════════════════════
//  KLINIK — Webhook Paiements Mobile Money
//  Réception des callbacks des providers
// ═══════════════════════════════════════
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Ne pas exiger d'auth session (c'est un callback serveur-à-serveur)
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

logError('WEBHOOK', 'Callback reçu', ['body' => $rawBody, 'headers' => getallheaders()]);

try {
    $pdo = getDB();
    
    // Déterminer le provider depuis les headers ou le body
    $provider = detectProvider($data);
    if (!$provider) {
        http_response_code(400);
        jsonResponse(false, 'Provider non reconnu.');
    }

    // Valider la signature
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!validateSignature($provider, $rawBody, $signature)) {
        logError('WEBHOOK', 'Signature invalide', ['provider' => $provider]);
        http_response_code(401);
        jsonResponse(false, 'Signature invalide.');
    }

    // Extraire la transaction ID et le statut
    $txId = extractTransactionId($provider, $data);
    $status = extractStatus($provider, $data);
    
    if (!$txId) {
        logError('WEBHOOK', 'Transaction ID manquant', ['provider' => $provider, 'data' => $data]);
        http_response_code(400);
        jsonResponse(false, 'Transaction ID manquant.');
    }

    // Trouver le paiement mobile
    $stmt = $pdo->prepare("SELECT * FROM paiements_mobile WHERE transaction_id = ?");
    $stmt->execute([$txId]);
    $pm = $stmt->fetch();

    if (!$pm) {
        logError('WEBHOOK', 'Transaction introuvable', ['tx_id' => $txId]);
        http_response_code(404);
        jsonResponse(false, 'Transaction introuvable.');
    }

    // Mapper le statut provider vers notre statut interne
    $internalStatus = mapStatus($status);

    $pdo->beginTransaction();
    try {
        // Mettre à jour le paiement mobile
        $pdo->prepare("UPDATE paiements_mobile SET statut=?, webhook_data=?, updated_at=NOW() WHERE id=?")
            ->execute([$internalStatus, json_encode($data), $pm['id']]);

        // Si succès, créer le paiement réel et mettre à jour la facture
        if ($internalStatus === 'succes' && !$pm['paiement_id']) {
            // Récupérer la facture
            $fStmt = $pdo->prepare("SELECT * FROM factures WHERE id=?");
            $fStmt->execute([$pm['facture_id']]);
            $facture = $fStmt->fetch();

            if ($facture && $facture['statut'] !== 'payee') {
                // Trouver un caissier pour enregistrer (ou utiliser le system)
                $caissierStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE role='caissier' AND actif=1 LIMIT 1");
                $caissierStmt->execute();
                $caissierId = $caissierStmt->fetchColumn() ?: 1; // Fallback à admin

                // Créer le paiement
                $pdo->prepare("INSERT INTO paiements(facture_id,caissier_id,montant_paye,mode_paiement,reference)VALUES(?,?,?,'mobile_money',?)")
                    ->execute([$pm['facture_id'], $caissierId, $pm['montant'], $txId]);
                $paiementId = (int)$pdo->lastInsertId();

                // Lier au paiement mobile
                $pdo->prepare("UPDATE paiements_mobile SET paiement_id=? WHERE id=?")->execute([$paiementId, $pm['id']]);

                // Mettre à jour le statut facture
                $tpStmt = $pdo->prepare("SELECT COALESCE(SUM(montant_paye),0) FROM paiements WHERE facture_id=?");
                $tpStmt->execute([$pm['facture_id']]);
                $totalPaye = (float)$tpStmt->fetchColumn();
                $nouveauStatut = $totalPaye >= $facture['montant_total'] ? 'payee' : 'partielle';
                $pdo->prepare("UPDATE factures SET statut=? WHERE id=?")->execute([$nouveauStatut, $pm['facture_id']]);

                // Notification patient
                $pStmt = $pdo->prepare("SELECT utilisateur_id FROM patients WHERE id=?");
                $pStmt->execute([$facture['patient_id']]);
                $patientUserId = $pStmt->fetchColumn();
                if ($patientUserId) {
                    createNotification($pdo, (int)$patientUserId,
                        'Paiement mobile confirmé',
                        'Votre paiement de ' . formatMontant($pm['montant']) . ' a été confirmé.',
                        'success', 'mes-factures.php');
                }
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }

    logError('WEBHOOK', 'Traitement réussi', ['tx_id' => $txId, 'statut' => $internalStatus]);
    jsonResponse(true, 'OK');

} catch (Exception $e) {
    logError('WEBHOOK', 'Erreur: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, 'Erreur interne.');
}

// ── Fonctions helper ──

function detectProvider(?array $data): ?string {
    // Détection basée sur les headers ou la structure du payload
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (stripos($ua, 'wave') !== false) return 'wave';
    if (stripos($ua, 'orange') !== false) return 'orange_money';
    if (stripos($ua, 'mtn') !== false) return 'mtn_momo';
    if (stripos($ua, 'moov') !== false) return 'moov_money';
    // Fallback: essayer de détecter depuis le body
    if ($data) {
        if (isset($data['type']) && stripos($data['type'], 'wave') !== false) return 'wave';
        if (isset($data['notif_token'])) return 'orange_money';
        if (isset($data['externalId'])) return 'mtn_momo';
    }
    // Si provider est dans le query string
    return cleanInput($_GET['provider'] ?? '') ?: null;
}

function validateSignature(string $provider, string $body, string $signature): bool {
    // En développement, toujours valide
    if (defined('APP_ENV') && APP_ENV === 'development') return true;
    // En production, vérifier la signature HMAC selon le provider
    // TODO: Implémenter la vérification pour chaque provider
    return !empty($signature);
}

function extractTransactionId(string $provider, ?array $data): ?string {
    if (!$data) return null;
    switch ($provider) {
        case 'wave': return $data['checkout_session_id'] ?? $data['id'] ?? null;
        case 'orange_money': return $data['txnid'] ?? $data['pay_token'] ?? null;
        case 'mtn_momo': return $data['externalId'] ?? $data['referenceId'] ?? null;
        case 'moov_money': return $data['transaction_id'] ?? null;
        default: return $data['transaction_id'] ?? null;
    }
}

function extractStatus(string $provider, ?array $data): string {
    if (!$data) return 'unknown';
    switch ($provider) {
        case 'wave': return $data['payment_status'] ?? $data['status'] ?? 'unknown';
        case 'orange_money': return $data['status'] ?? 'unknown';
        case 'mtn_momo': return $data['status'] ?? 'unknown';
        case 'moov_money': return $data['status'] ?? 'unknown';
        default: return $data['status'] ?? 'unknown';
    }
}

function mapStatus(string $providerStatus): string {
    $successStatuses = ['succeeded','successful','success','completed','SUCCESSFUL','paid'];
    $failedStatuses = ['failed','rejected','declined','cancelled','FAILED'];
    $pendingStatuses = ['pending','processing','PENDING'];
    
    $lower = strtolower($providerStatus);
    if (in_array($lower, array_map('strtolower', $successStatuses))) return 'succes';
    if (in_array($lower, array_map('strtolower', $failedStatuses))) return 'echec';
    if (in_array($lower, array_map('strtolower', $pendingStatuses))) return 'en_cours';
    return 'en_cours';
}
