<?php
// ═══════════════════════════════════════
//  KLINIK — Déconnexion
// ═══════════════════════════════════════
session_start();

// Vider toutes les données de session
$_SESSION = [];

// Supprimer le cookie de session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Détruire la session
session_destroy();

// Rediriger vers l'accueil
header('Location: ../index.php');
exit;
