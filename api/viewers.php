<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$channelId = $_GET['channel_id'] ?? '';
if (!ctype_digit($channelId)) {
    http_response_code(400);
    echo json_encode(['count' => 0]);
    exit;
}

$sessionTtlFile = __DIR__ . '/../cache/session_timeout.txt';
$sessionTtl     = is_file($sessionTtlFile) ? (int)trim(file_get_contents($sessionTtlFile)) : 60;
if ($sessionTtl < 10) $sessionTtl = 60;

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM live_sessions
     WHERE channel_id = :id AND last_activity >= DATE_SUB(NOW(), INTERVAL ' . (int)$sessionTtl . ' SECOND)'
);
$stmt->execute([':id' => (int)$channelId]);
$count = (int)$stmt->fetchColumn();

echo json_encode(['count' => $count]);
