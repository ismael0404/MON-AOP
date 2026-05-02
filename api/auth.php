<?php
// ═══════════════════════════════════════
//  KLINIK — API Authentification
//  Actions: login, register, logout
// ═══════════════════════════════════════
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $pdo = getDB();

    // ══════════════════════════════
    //  LOGIN
    // ══════════════════════════════
    if ($action === 'login') {
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(false, 'Email et mot de passe requis.');
        }

        if (!isValidEmail($email)) {
            jsonResponse(false, 'Format d\'email invalide.');
        }

        $stmt = $pdo->prepare("
            SELECT u.*,
                   p.id AS patient_id,
                   m.id AS medecin_id
            FROM utilisateurs u
            LEFT JOIN patients p ON u.id = p.utilisateur_id
            LEFT JOIN medecins m ON u.id = m.utilisateur_id
            WHERE u.email = ? AND u.actif = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            logError('AUTH', 'Tentative de connexion échouée', ['email' => $email]);
            jsonResponse(false, 'Email ou mot de passe incorrect.');
        }

        // Mettre à jour la dernière connexion
        $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        // Régénérer l'ID de session pour prévenir la fixation de session
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'         => (int)$user['id'],
            'nom'        => $user['nom'],
            'prenom'     => $user['prenom'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'telephone'  => $user['telephone'],
            'patient_id' => $user['patient_id'] ? (int)$user['patient_id'] : null,
            'medecin_id' => $user['medecin_id'] ? (int)$user['medecin_id'] : null,
        ];

        $redirectMap = [
            'admin'      => '../admin/dashboard.php',
            'medecin'    => '../medecin/dashboard.php',
            'patient'    => '../patient/dashboard.php',
            'laborantin' => '../laborantin/dashboard.php',
            'caissier'   => '../caissier/dashboard.php',
        ];

        logError('AUTH', 'Connexion réussie', ['user_id' => $user['id'], 'role' => $user['role']]);

        jsonResponse(true, 'Connexion réussie.', [
            'redirect' => $redirectMap[$user['role']] ?? '../auth/login.php',
            'user' => [
                'nom'    => $user['nom'],
                'prenom' => $user['prenom'],
                'role'   => $user['role'],
            ]
        ]);
    }

    // ══════════════════════════════
    //  REGISTER
    // ══════════════════════════════
    if ($action === 'register') {
        $nom      = cleanInput($input['nom']      ?? '');
        $prenom   = cleanInput($input['prenom']   ?? '');
        $email    = trim($input['email']           ?? '');
        $password = $input['password']             ?? '';
        $telephone= cleanInput($input['telephone'] ?? '');

        // Si le formulaire envoie "nom" comme nom complet, on le parse
        if (!empty($nom) && empty($prenom)) {
            $parts  = explode(' ', $nom, 2);
            $prenom = $parts[0] ?? '';
            $nom    = $parts[1] ?? $parts[0];
            // Si une seule partie, mettre comme nom de famille
            if (count($parts) === 1) {
                $nom = $parts[0];
                $prenom = '';
            }
        }

        if (!$nom || !$email || !$password) {
            jsonResponse(false, 'Nom, email et mot de passe sont requis.');
        }
        if (!isValidEmail($email)) {
            jsonResponse(false, 'Email invalide.');
        }
        if (strlen($password) < 6) {
            jsonResponse(false, 'Mot de passe trop court (6 caractères minimum).');
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Cet email est déjà utilisé.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, telephone, actif)
                VALUES (?, ?, ?, ?, 'patient', ?, 1)
            ");
            $stmt->execute([$nom, $prenom, $email, $hash, $telephone ?: null]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO patients (utilisateur_id) VALUES (?)");
            $stmt->execute([$userId]);
            $patientId = (int)$pdo->lastInsertId();

            // Notification de bienvenue
            createNotification($pdo, $userId, 'Bienvenue sur KLINIK', 
                'Votre compte patient a été créé avec succès. Vous pouvez maintenant prendre des rendez-vous.', 
                'success', 'dashboard.php');

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Régénérer la session
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'         => $userId,
            'nom'        => $nom,
            'prenom'     => $prenom,
            'email'      => $email,
            'role'       => 'patient',
            'telephone'  => $telephone ?: null,
            'patient_id' => $patientId,
            'medecin_id' => null,
        ];

        logError('AUTH', 'Inscription réussie', ['user_id' => $userId]);

        jsonResponse(true, 'Compte créé avec succès.', [
            'redirect' => '../patient/dashboard.php'
        ]);
    }

    // ══════════════════════════════
    //  LOGOUT
    // ══════════════════════════════
    if ($action === 'logout') {
        $_SESSION = [];
        
        // Supprimer le cookie de session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
        
        jsonResponse(true, 'Déconnexion réussie.', [
            'redirect' => '../index.php'
        ]);
    }

    jsonResponse(false, 'Action non reconnue.');

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('AUTH', 'Erreur PDO: ' . $e->getMessage());
    jsonResponse(false, APP_ENV === 'development' ? 'Erreur: ' . $e->getMessage() : 'Erreur serveur.');
} catch (Exception $e) {
    logError('AUTH', 'Erreur: ' . $e->getMessage());
    jsonResponse(false, 'Erreur serveur inattendue.');
}
