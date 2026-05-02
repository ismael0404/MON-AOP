<?php
// ═══════════════════════════════════════
//  KLINIK — Fonctions utilitaires
// ═══════════════════════════════════════

/**
 * Redirection HTTP.
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Sanitize une chaîne pour l'affichage HTML.
 * Ne PAS utiliser pour les requêtes SQL (utiliser les prepared statements).
 */
function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize un input brut (trim + strip tags, sans encoder HTML).
 * À utiliser pour les données qui vont en BDD via prepared statements.
 */
function cleanInput(string $data): string {
    return strip_tags(trim($data));
}

/**
 * Retourne une réponse JSON et termine le script.
 */
function jsonResponse(bool $success, string $message, array $data = []): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Retourne le chemin relatif vers le dashboard selon le rôle.
 * Utiliser uniquement pour les redirections depuis /api/ ou /auth/.
 */
function getRoleDashboard(string $role): string {
    $map = [
        'admin'      => '../admin/dashboard.php',
        'medecin'    => '../medecin/dashboard.php',
        'patient'    => '../patient/dashboard.php',
        'laborantin' => '../laborantin/dashboard.php',
        'caissier'   => '../caissier/dashboard.php',
    ];
    return $map[$role] ?? '../auth/login.php';
}

// ── CSRF Protection ──

/**
 * Génère un token CSRF et le stocke en session.
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF.
 */
function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ── Notifications Helper ──

/**
 * Crée une notification pour un utilisateur.
 */
function createNotification(PDO $pdo, int $userId, string $titre, string $message, string $type = 'info', ?string $lien = null): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (utilisateur_id, titre, message, type, lien)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$userId, $titre, $message, $type, $lien]);
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('NOTIFICATION', 'Erreur création notification: ' . $e->getMessage(), [
                'user_id' => $userId, 'titre' => $titre
            ]);
        }
        return false;
    }
}

/**
 * Crée une notification pour tous les utilisateurs d'un rôle donné.
 */
function notifyRole(PDO $pdo, string $role, string $titre, string $message, string $type = 'info', ?string $lien = null): void {
    try {
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE role = ? AND actif = 1");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll();
        foreach ($users as $u) {
            createNotification($pdo, $u['id'], $titre, $message, $type, $lien);
        }
    } catch (PDOException $e) {
        if (function_exists('logError')) {
            logError('NOTIFICATION', 'Erreur notifyRole: ' . $e->getMessage());
        }
    }
}

// ── Validation Helpers ──

/**
 * Valide un email.
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone.
 */
function isValidPhone(string $phone): bool {
    return preg_match('/^[\d\s+\-().]{8,20}$/', $phone) === 1;
}

// ── Date Helpers ──

/**
 * Formate une date en français.
 */
function formatDateFr(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

/**
 * Formate un montant en FCFA.
 */
function formatMontant(float $montant): string {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}
