<?php
// ═══════════════════════════════════════
//  KLINIK — Vérification d'authentification
// ═══════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie que l'utilisateur est authentifié et a le bon rôle.
 * Redirige vers login si non authentifié ou rôle incorrect.
 * 
 * @param array $allowedRoles Rôles autorisés (vide = tous les rôles connectés)
 */
function checkAuth(array $allowedRoles = []): void {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
        // Pour les API (JSON), retourner une erreur JSON
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
            exit;
        }
        // Pour les pages, rediriger
        header('Location: ' . getBaseUrl() . 'auth/login.php');
        exit;
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['user']['role'], $allowedRoles, true)) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            exit;
        }
        // Rediriger vers le bon dashboard selon le rôle
        $dashboard = getRoleDashboardUrl($_SESSION['user']['role']);
        header('Location: ' . $dashboard);
        exit;
    }
}

/**
 * Retourne les données de l'utilisateur connecté.
 */
function getUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Vérifie si c'est une requête API (AJAX/JSON).
 */
function isApiRequest(): bool {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    
    return (
        stripos($contentType, 'application/json') !== false ||
        stripos($accept, 'application/json') !== false ||
        strtolower($xhr) === 'xmlhttprequest' ||
        // Detect if we're in the api/ directory
        strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false
    );
}

/**
 * Retourne l'URL de base du projet.
 */
function getBaseUrl(): string {
    if (defined('ROOT_URL')) {
        return ROOT_URL;
    }
    // Fallback: calcul automatique
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Remonter d'un niveau si on est dans un sous-dossier
    $parts = explode('/', trim($scriptDir, '/'));
    if (count($parts) > 1) {
        array_pop($parts);
    }
    return '/' . implode('/', $parts) . '/';
}

/**
 * Retourne l'URL du dashboard pour un rôle donné.
 */
function getRoleDashboardUrl(string $role): string {
    $base = getBaseUrl();
    $map = [
        'admin'      => $base . 'admin/dashboard.php',
        'medecin'    => $base . 'medecin/dashboard.php',
        'patient'    => $base . 'patient/dashboard.php',
        'laborantin' => $base . 'laborantin/dashboard.php',
        'caissier'   => $base . 'caissier/dashboard.php',
    ];
    return $map[$role] ?? $base . 'auth/login.php';
}
