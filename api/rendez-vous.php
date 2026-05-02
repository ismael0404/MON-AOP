<?php
// ═══════════════════════════════════════
//  KLINIK — API Rendez-vous
//  Actions: create, updateStatut, list, delete
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';

checkAuth(['patient', 'medecin', 'admin']);
header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    $pdo  = getDB();
    $user = getUser();

    // ══════════════════════════════
    //  CRÉER un RDV (patient)
    // ══════════════════════════════
    if ($action === 'create') {
        $patientId = $user['patient_id'] ?? null;
        
        // Admin/medecin peuvent créer pour un patient
        if (in_array($user['role'], ['admin', 'medecin'])) {
            $patientId = (int)($input['patient_id'] ?? $patientId ?? 0);
        }
        
        if (!$patientId) {
            jsonResponse(false, 'Patient non identifié.');
        }

        $medecinId = (int)($input['medecin_id'] ?? 0);
        $dateRdv   = cleanInput($input['date_rdv'] ?? '');
        $motif     = cleanInput($input['motif'] ?? '');

        if (!$medecinId || !$dateRdv) {
            jsonResponse(false, 'Médecin et date requis.');
        }

        // Vérifier que le médecin existe
        $stmt = $pdo->prepare("SELECT id FROM medecins WHERE id = ?");
        $stmt->execute([$medecinId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Médecin introuvable.');
        }

        // Vérifier que la date est dans le futur
        if (strtotime($dateRdv) < time()) {
            jsonResponse(false, 'La date du rendez-vous doit être dans le futur.');
        }

        // Vérifier conflit de créneau (±30 min)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM rendez_vous 
            WHERE medecin_id = ? 
              AND statut NOT IN ('annule')
              AND ABS(TIMESTAMPDIFF(MINUTE, date_rdv, ?)) < 30
        ");
        $stmt->execute([$medecinId, $dateRdv]);
        if ((int)$stmt->fetchColumn() > 0) {
            jsonResponse(false, 'Ce créneau est déjà pris. Veuillez choisir un autre horaire.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO rendez_vous (patient_id, medecin_id, date_rdv, motif) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$patientId, $medecinId, $dateRdv, $motif]);
        $rdvId = (int)$pdo->lastInsertId();

        // Notification au médecin
        $medecinStmt = $pdo->prepare("SELECT utilisateur_id FROM medecins WHERE id = ?");
        $medecinStmt->execute([$medecinId]);
        $medecinUserId = $medecinStmt->fetchColumn();
        if ($medecinUserId) {
            $patientNom = $user['prenom'] . ' ' . $user['nom'];
            $dateFormatted = date('d/m/Y à H:i', strtotime($dateRdv));
            createNotification($pdo, (int)$medecinUserId, 
                'Nouveau rendez-vous',
                "Rendez-vous avec {$patientNom} le {$dateFormatted}.",
                'info', 'mes-rendez-vous.php');
        }

        jsonResponse(true, 'Rendez-vous enregistré.', ['rdv_id' => $rdvId]);
    }

    // ══════════════════════════════
    //  METTRE À JOUR le statut
    // ══════════════════════════════
    if ($action === 'updateStatut') {
        $id     = (int)($input['id'] ?? 0);
        $statut = cleanInput($input['statut'] ?? '');
        $validStatuts = ['en_attente', 'confirme', 'termine', 'annule'];

        if (!$id || !in_array($statut, $validStatuts, true)) {
            jsonResponse(false, 'Données invalides.');
        }

        // Vérifier que le RDV existe
        $stmt = $pdo->prepare("SELECT * FROM rendez_vous WHERE id = ?");
        $stmt->execute([$id]);
        $rdv = $stmt->fetch();
        if (!$rdv) {
            jsonResponse(false, 'Rendez-vous introuvable.');
        }

        $pdo->prepare("UPDATE rendez_vous SET statut = ? WHERE id = ?")->execute([$statut, $id]);

        // Notification au patient
        $patientStmt = $pdo->prepare("SELECT utilisateur_id FROM patients WHERE id = ?");
        $patientStmt->execute([$rdv['patient_id']]);
        $patientUserId = $patientStmt->fetchColumn();
        if ($patientUserId) {
            $statutLabels = ['confirme' => 'confirmé', 'annule' => 'annulé', 'termine' => 'terminé'];
            if (isset($statutLabels[$statut])) {
                createNotification($pdo, (int)$patientUserId,
                    'Rendez-vous ' . $statutLabels[$statut],
                    'Votre rendez-vous du ' . date('d/m/Y', strtotime($rdv['date_rdv'])) . ' a été ' . $statutLabels[$statut] . '.',
                    $statut === 'confirme' ? 'success' : ($statut === 'annule' ? 'danger' : 'info'),
                    'mes-rendez-vous.php');
            }
        }

        jsonResponse(true, 'Statut mis à jour.');
    }

    // ══════════════════════════════
    //  LISTE des RDV
    // ══════════════════════════════
    if ($action === 'list') {
        $where = "1=1";
        $params = [];

        if ($user['role'] === 'patient' && $user['patient_id']) {
            $where .= " AND r.patient_id = ?";
            $params[] = $user['patient_id'];
        } elseif ($user['role'] === 'medecin' && $user['medecin_id']) {
            $where .= " AND r.medecin_id = ?";
            $params[] = $user['medecin_id'];
        }

        $stmt = $pdo->prepare("
            SELECT r.*, 
                   up.nom AS patient_nom, up.prenom AS patient_prenom,
                   um.nom AS medecin_nom, um.prenom AS medecin_prenom,
                   m.specialite
            FROM rendez_vous r
            JOIN patients p ON r.patient_id = p.id
            JOIN utilisateurs up ON p.utilisateur_id = up.id
            JOIN medecins med ON r.medecin_id = med.id
            JOIN utilisateurs um ON med.utilisateur_id = um.id
            LEFT JOIN medecins m ON r.medecin_id = m.id
            WHERE {$where}
            ORDER BY r.date_rdv DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        
        jsonResponse(true, 'OK', ['rdvs' => $stmt->fetchAll()]);
    }

    jsonResponse(false, 'Action non reconnue.');

} catch (PDOException $e) {
    logError('RDV', 'Erreur: ' . $e->getMessage());
    jsonResponse(false, APP_ENV === 'development' ? 'Erreur: ' . $e->getMessage() : 'Erreur serveur.');
} catch (Exception $e) {
    logError('RDV', 'Erreur: ' . $e->getMessage());
    jsonResponse(false, 'Erreur serveur.');
}
