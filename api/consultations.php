<?php
// ═══════════════════════════════════════
//  KLINIK — API Consultations
//  Actions: create, list
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/functions.php';

checkAuth(['medecin', 'admin']);
header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    $pdo  = getDB();
    $user = getUser();
    $mid  = $user['medecin_id'] ?? null;

    // ══════════════════════════════
    //  CRÉER une consultation
    // ══════════════════════════════
    if ($action === 'create') {
        if (!$mid && $user['role'] !== 'admin') {
            jsonResponse(false, 'Médecin non identifié.');
        }

        $patientId    = (int)($input['patient_id'] ?? 0);
        $rdvId        = !empty($input['rendez_vous_id']) ? (int)$input['rendez_vous_id'] : null;
        $diagnostic   = cleanInput($input['diagnostic'] ?? '');
        $prescription = cleanInput($input['prescription'] ?? '');
        $observations = cleanInput($input['observations'] ?? '');

        if (!$patientId) {
            jsonResponse(false, 'Patient requis.');
        }

        // Vérifier que le patient existe
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Patient introuvable.');
        }

        // Vérifier le RDV s'il est fourni
        if ($rdvId) {
            $stmt = $pdo->prepare("SELECT id, statut FROM rendez_vous WHERE id = ?");
            $stmt->execute([$rdvId]);
            $rdv = $stmt->fetch();
            if (!$rdv) {
                jsonResponse(false, 'Rendez-vous introuvable.');
            }
            // Vérifier qu'il n'y a pas déjà une consultation pour ce RDV
            $stmt = $pdo->prepare("SELECT id FROM consultations WHERE rendez_vous_id = ?");
            $stmt->execute([$rdvId]);
            if ($stmt->fetch()) {
                jsonResponse(false, 'Une consultation existe déjà pour ce rendez-vous.');
            }
        }

        // Pour admin, on peut spécifier le medecin_id
        $medecinId = $mid;
        if ($user['role'] === 'admin' && !empty($input['medecin_id'])) {
            $medecinId = (int)$input['medecin_id'];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO consultations (medecin_id, patient_id, rendez_vous_id, diagnostic, prescription, observations)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$medecinId, $patientId, $rdvId, $diagnostic, $prescription, $observations]);
            $consultId = (int)$pdo->lastInsertId();

            // Le trigger SQL met automatiquement le RDV en 'termine'

            // Notification au patient
            $patientStmt = $pdo->prepare("SELECT utilisateur_id FROM patients WHERE id = ?");
            $patientStmt->execute([$patientId]);
            $patientUserId = $patientStmt->fetchColumn();
            if ($patientUserId) {
                $medecinNom = $user['prenom'] . ' ' . $user['nom'];
                createNotification($pdo, (int)$patientUserId,
                    'Nouvelle consultation',
                    "Dr. {$medecinNom} a enregistré une consultation pour vous.",
                    'info', 'mon-dossier.php');
            }

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        jsonResponse(true, 'Consultation enregistrée.', ['consultation_id' => $consultId]);
    }

    // ══════════════════════════════
    //  LISTE des consultations
    // ══════════════════════════════
    if ($action === 'list') {
        $where = "1=1";
        $params = [];

        if ($user['role'] === 'medecin' && $mid) {
            $where .= " AND c.medecin_id = ?";
            $params[] = $mid;
        }

        $patientFilter = (int)($input['patient_id'] ?? ($_GET['patient_id'] ?? 0));
        if ($patientFilter) {
            $where .= " AND c.patient_id = ?";
            $params[] = $patientFilter;
        }

        $stmt = $pdo->prepare("
            SELECT c.*, 
                   up.nom AS patient_nom, up.prenom AS patient_prenom,
                   um.nom AS medecin_nom, um.prenom AS medecin_prenom
            FROM consultations c
            JOIN patients p ON c.patient_id = p.id
            JOIN utilisateurs up ON p.utilisateur_id = up.id
            JOIN medecins m ON c.medecin_id = m.id
            JOIN utilisateurs um ON m.utilisateur_id = um.id
            WHERE {$where}
            ORDER BY c.date_consult DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        
        jsonResponse(true, 'OK', ['consultations' => $stmt->fetchAll()]);
    }

    jsonResponse(false, 'Action non reconnue.');

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    logError('CONSULTATION', 'Erreur: ' . $e->getMessage());
    jsonResponse(false, APP_ENV === 'development' ? 'Erreur: ' . $e->getMessage() : 'Erreur serveur.');
}
