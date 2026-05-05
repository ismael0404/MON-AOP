<?php
// ═══════════════════════════════════════
//  KLINIK — API Paiements Mobile Money
//  Actions: initier, verifier, webhook
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Configuration des providers (à remplacer par vos clés réelles)
define('MOBILE_MONEY_CONFIG', [
    'wave' => [
        'api_url'    => 'https://api.wave.com/v1/checkout/sessions',
        'api_key'    => 'WAVE_API_KEY_HERE',
        'secret'     => 'WAVE_SECRET_HERE',
        'webhook_secret' => 'WAVE_WEBHOOK_SECRET',
    ],
    'orange_money' => [
        'api_url'    => 'https://api.orange.com/orange-money-webpay/dev/v1/webpayment',
        'api_key'    => 'ORANGE_API_KEY_HERE',
        'secret'     => 'ORANGE_SECRET_HERE',
        'merchant_key' => 'ORANGE_MERCHANT_KEY',
    ],
    'mtn_momo' => [
        'api_url'    => 'https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay',
        'api_key'    => 'MTN_API_KEY_HERE',
        'subscription_key' => 'MTN_SUB_KEY',
        'api_user'   => 'MTN_USER',
    ],
    'moov_money' => [
        'api_url'    => 'https://api.moov-africa.com/payment',
        'api_key'    => 'MOOV_API_KEY_HERE',
        'secret'     => 'MOOV_SECRET_HERE',
    ],
]);

header('Content-Type: application/json; charset=utf-8');
$input=json_decode(file_get_contents('php://input'),true)??[];
$action=$input['action']??($_GET['action']??'');

try {
    $pdo=getDB();

    // ═══════════════════════════════
    //  INITIER un paiement Mobile Money
    // ═══════════════════════════════
    // ═══════════════════════════════
    //  INITIER un paiement Mobile Money
    // ═══════════════════════════════
    if($action==='initier'){
        checkAuth(['caissier','admin','patient']);
        $user=getUser();
        $factureId=(int)($input['facture_id']??0);
        $provider=cleanInput($input['provider']??'');
        $telephone=cleanInput($input['telephone']??'');
        $montant=(float)($input['montant']??0);

        if(!$factureId||!$provider||!$telephone||$montant<=0){
            jsonResponse(false,'Facture, provider, téléphone et montant requis.');
        }
        if(!in_array($provider,['wave','orange_money','mtn_momo','moov_money','cash'])){
            jsonResponse(false,'Provider invalide.');
        }
        // Vérifier facture
        $s=$pdo->prepare("SELECT * FROM factures WHERE id=?");$s->execute([$factureId]);
        $facture=$s->fetch();
        if(!$facture)jsonResponse(false,'Facture introuvable.');
        if($facture['statut']==='payee')jsonResponse(false,'Facture déjà payée.');

        // Créer l'entrée de paiement mobile
        $txId='KLK-'.strtoupper($provider).'-'.time().'-'.rand(1000,9999);
        $stmt=$pdo->prepare("INSERT INTO paiements_mobile(facture_id,provider,telephone,montant,transaction_id,statut)VALUES(?,?,?,?,?,'initie')");
        $stmt->execute([$factureId,$provider,$telephone,$montant,$txId]);
        $pmId=(int)$pdo->lastInsertId();

        // Mode Simulation : On demande l'OTP immédiatement
        jsonResponse(true,'Paiement initié. Veuillez saisir le code OTP envoyé au ' . $telephone, [
            'transaction_id' => $txId, 
            'pm_id' => $pmId,
            'status' => 'pending_otp'
        ]);
    }

    // ═══════════════════════════════
    //  VÉRIFIER l'OTP (Simulation)
    // ═══════════════════════════════
    if($action==='verify_otp'){
        checkAuth(['patient','caissier','admin']);
        $txId = cleanInput($input['transaction_id'] ?? '');
        $otp  = cleanInput($input['otp'] ?? '');

        if(!$txId || !$otp) jsonResponse(false, 'Transaction ID et OTP requis.');

        if($otp !== '0404') {
            jsonResponse(false, 'Code OTP incorrect. Veuillez réessayer.');
        }

        $s=$pdo->prepare("SELECT * FROM paiements_mobile WHERE transaction_id=?");$s->execute([$txId]);
        $pm=$s->fetch();
        if(!$pm) jsonResponse(false, 'Transaction introuvable.');
        if($pm['statut'] !== 'initie') jsonResponse(false, 'Cette transaction ne peut plus être validée.');

        $factureId = $pm['facture_id'];
        
        $pdo->beginTransaction();
        try {
            // 1. Succès Paiement Mobile
            $pdo->prepare("UPDATE paiements_mobile SET statut='succes' WHERE id=?")->execute([$pm['id']]);
            
            // 2. Insérer dans paiements officiels
            $pdo->prepare("INSERT INTO paiements(facture_id, caissier_id, montant_paye, mode_paiement, reference) VALUES(?, ?, ?, 'mobile_money', ?)")
                ->execute([$factureId, 1, $pm['montant'], $txId]);
            $paiementId = $pdo->lastInsertId();
            
            // 3. Mettre à jour facture
            $pdo->prepare("UPDATE factures SET statut='payee' WHERE id=?")->execute([$factureId]);
            
            // 4. SI c'est lié à un RDV, on CONFIRME le RDV
            $sf = $pdo->prepare("SELECT rendez_vous_id, patient_id FROM factures WHERE id=?");
            $sf->execute([$factureId]);
            $fData = $sf->fetch();
            
            if($fData && $fData['rendez_vous_id']) {
                $pdo->prepare("UPDATE rendez_vous SET statut='confirme' WHERE id=?")->execute([$fData['rendez_vous_id']]);
                
                // Notification RDV Confirmé
                $up=$pdo->prepare("SELECT utilisateur_id FROM patients WHERE id=?");$up->execute([$fData['patient_id']]);
                $uid=$up->fetchColumn();
                if($uid) {
                    createNotification($pdo, $uid, 'Rendez-vous Confirmé', "Votre paiement a été reçu. Votre rendez-vous est maintenant officiellement confirmé.", 'success', 'mes-rendez-vous.php');
                }
            } else if($fData) {
                // Notification Paiement Simple
                $up=$pdo->prepare("SELECT utilisateur_id FROM patients WHERE id=?");$up->execute([$fData['patient_id']]);
                $uid=$up->fetchColumn();
                if($uid) {
                    createNotification($pdo, $uid, 'Paiement Réussi', "Votre paiement de {$pm['montant']}F a été validé.", 'success', 'mes-factures.php');
                }
            }

            $pdo->commit();
            jsonResponse(true, 'Paiement réussi et validé avec succès !', ['statut'=>'succes']);
        } catch(Exception $e) {
            $pdo->rollBack();
            logError('PAIEMENT', 'Erreur transaction: '.$e->getMessage());
            jsonResponse(false, 'Erreur lors de la validation du paiement.');
        }
    }

    // ═══════════════════════════════
    //  VÉRIFIER le statut
    // ═══════════════════════════════
    if($action==='verifier'){
        checkAuth();
        $txId=cleanInput($input['transaction_id']??$_GET['transaction_id']??'');
        if(!$txId)jsonResponse(false,'Transaction ID requis.');
        $s=$pdo->prepare("SELECT * FROM paiements_mobile WHERE transaction_id=?");$s->execute([$txId]);
        $pm=$s->fetch();
        if(!$pm)jsonResponse(false,'Transaction introuvable.');
        jsonResponse(true,'OK',['statut'=>$pm['statut'],'provider'=>$pm['provider'],'montant'=>$pm['montant']]);
    }

    // ═══════════════════════════════
    //  SIMULATE CALLBACK (Obsolète avec le nouveau système OTP)
    // ═══════════════════════════════
    if($action==='simulate_callback'){
        jsonResponse(false, 'Action obsolète. Utilisez verify_otp.');
    }

    // ═══════════════════════════════
    //  VALIDER PAIEMENT (Caissier/Admin)
    // ═══════════════════════════════
    if($action==='valider'){
        checkAuth(['caissier','admin']);
        $pmId=(int)($input['pm_id']??0);
        if(!$pmId)jsonResponse(false,'ID requis.');
        
        $user=getUser();
        
        $s=$pdo->prepare("SELECT * FROM paiements_mobile WHERE id=?");$s->execute([$pmId]);
        $pm=$s->fetch();
        if(!$pm)jsonResponse(false,'Paiement introuvable.');
        
        // Trouver le paiement officiel s'il existe
        $sp=$pdo->prepare("SELECT id FROM paiements WHERE reference=?");$sp->execute([$pm['transaction_id']]);
        $pOff=$sp->fetchColumn();
        
        if($pOff) {
            $pdo->prepare("UPDATE paiements SET validated_by=?, validated_at=NOW() WHERE id=?")->execute([$user['id'], $pOff]);
        }
        
        // Mettre status succès si ce n'est pas déjà
        if($pm['statut'] !== 'succes') {
            $pdo->prepare("UPDATE paiements_mobile SET statut='succes' WHERE id=?")->execute([$pmId]);
            $pdo->prepare("UPDATE factures SET statut='payee' WHERE id=?")->execute([$pm['facture_id']]);
        }
        
        jsonResponse(true,'Paiement validé avec succès.');
    }
    
    // ═══════════════════════════════
    //  ANNULER PAIEMENT (Caissier/Admin)
    // ═══════════════════════════════
    if($action==='annuler'){
        checkAuth(['caissier','admin']);
        $pmId=(int)($input['pm_id']??0);
        if(!$pmId)jsonResponse(false,'ID requis.');
        
        $pdo->prepare("UPDATE paiements_mobile SET statut='echec', erreur='Annulé par la caisse' WHERE id=?")->execute([$pmId]);
        
        jsonResponse(true,'Paiement annulé.');
    }

    jsonResponse(false,'Action non reconnue.');
}catch(PDOException $e){
    logError('PAIEMENT','Erreur: '.$e->getMessage());
    jsonResponse(false,'Erreur serveur.');
}

/**
 * Simulation d'appel API provider (à remplacer par les vrais appels)
 */
function simulateProviderCall(string $provider, string $phone, float $amount, string $txId): array {
    // En production, remplacez par les vrais appels cURL vers les APIs
    // Pour l'instant, simulation réussie dans 90% des cas
    $success = rand(1,10) <= 9;
    return [
        'success' => $success,
        'provider' => $provider,
        'transaction_id' => $txId,
        'phone' => $phone,
        'amount' => $amount,
        'status' => $success ? 'pending' : 'failed',
        'error' => $success ? null : 'Simulation: échec aléatoire',
        'timestamp' => date('Y-m-d H:i:s'),
    ];
}
