<?php
require_once __DIR__ . '/../includes/db.php';

// Update or insert live_session record for this IP + User-Agent
$ip      = getClientIP();
$ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
$channel = !empty($_POST['channel_id']) && ctype_digit($_POST['channel_id'])
           ? (int)$_POST['channel_id'] : null;

// Check if session already exists for this IP
$stmt = $pdo->prepare(
    'SELECT id FROM live_sessions WHERE ip_address = :ip AND user_agent = :ua LIMIT 1'
);
$stmt->execute([':ip' => $ip, ':ua' => $ua]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare(
        'UPDATE live_sessions SET channel_id = :channel_id, last_activity = NOW()
         WHERE id = :id'
    );
    $stmt->execute([':channel_id' => $channel, ':id' => $existing['id']]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO live_sessions (ip_address, user_agent, channel_id, last_activity)
         VALUES (:ip, :ua, :channel_id, NOW())'
    );
    $stmt->execute([':ip' => $ip, ':ua' => $ua, ':channel_id' => $channel]);
}

// Clean stale sessions
$sessionTtlFile = __DIR__ . '/../cache/session_timeout.txt';
$sessionTtl     = is_file($sessionTtlFile) ? (int)trim(file_get_contents($sessionTtlFile)) : 60;
if ($sessionTtl < 10) $sessionTtl = 60;
$pdo->exec('DELETE FROM live_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ' . $sessionTtl . ' SECOND)');

http_response_code(204);
