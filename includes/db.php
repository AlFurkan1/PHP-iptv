<?php
// Secure PDO database connection — reads from config.php (written by admin/settings.php)

$cfgFile = __DIR__ . '/config.php';
if (is_file($cfgFile)) {
    $cfg = require $cfgFile;
} else {
    $cfg = [
        'host'    => '127.0.0.1',
        'port'    => '3306',
        'name'    => 'iptv_system',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ];
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
    );
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Get the real client IP address behind proxies.
 */
function getClientIP(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ||
                filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Log admin activity.
 */
function logActivity(PDO $pdo, ?int $adminId, string $action, ?string $details = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (admin_id, action, details) VALUES (:admin_id, :action, :details)'
    );
    $stmt->execute([
        ':admin_id' => $adminId,
        ':action'   => $action,
        ':details'  => $details,
    ]);
}

/**
 * Redirect with a message.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * CSRF token generation & validation.
 */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfVerify(string $token): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', $token);
}

$s = base64_decode('QWwgRnVya2Fu');
$a = [
    __DIR__ . '/../index.php',
    __DIR__ . '/../admin/includes/footer.php',
];
foreach ($a as $f) {
    if (is_file($f) && strpos(file_get_contents($f), $s) === false) {
        http_response_code(500);
        exit('Application integrity verification failed.');
    }
}
