<?php
// ═══════════════════════════════════════
//  KLINIK — Configuration Base de Données
// ═══════════════════════════════════════

// ── Mode de l'application ──
define('APP_ENV', 'development'); // 'development' ou 'production'
define('APP_NAME', 'KLINIK');

// ── Base de données ──
define('DB_HOST',     'localhost');
define('DB_NAME',     'klinik_db');
define('DB_USER',     'root');
define('DB_PASSWORD', '');
define('DB_CHARSET',  'utf8mb4');

// ── Chemins absolus ──
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ROOT_URL',  '/MON AOP/');

// ── Logging ──
define('LOG_DIR', ROOT_PATH . 'logs' . DIRECTORY_SEPARATOR);
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// ── Configuration des erreurs PHP ──
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
ini_set('log_errors', 1);
ini_set('error_log', LOG_DIR . 'php_errors.log');

// ── Connexion PDO (singleton) ──
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
            ]);
        } catch (PDOException $e) {
            logError('DATABASE', $e->getMessage());
            if (APP_ENV === 'development') {
                die(json_encode(['success' => false, 'message' => 'Erreur BDD: ' . $e->getMessage()]));
            }
            die(json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données.']));
        }
    }
    return $pdo;
}

// ── Fonction de logging ──
function logError(string $context, string $message, array $data = []): void {
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = !empty($data) ? ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    $logLine = "[{$timestamp}] [{$context}] {$message}{$dataStr}" . PHP_EOL;
    @file_put_contents(LOG_DIR . 'app.log', $logLine, FILE_APPEND | LOCK_EX);
}
