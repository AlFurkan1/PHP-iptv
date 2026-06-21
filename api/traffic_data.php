<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$stmt = $pdo->prepare(
    'SELECT DATE(recorded_at) AS day,
            MAX(bytes_in) - MIN(bytes_in) AS bytes_in,
            MAX(bytes_out) - MIN(bytes_out) AS bytes_out
     FROM traffic_stats
     WHERE recorded_at >= :from AND recorded_at < DATE_ADD(:to, INTERVAL 1 DAY)
     GROUP BY DATE(recorded_at)
     ORDER BY day ASC'
);
$stmt->execute([':from' => $from, ':to' => $to]);
$rows = $stmt->fetchAll();

$labels = [];
$in     = [];
$out    = [];

foreach ($rows as $r) {
    $labels[] = $r['day'];
    $in[]     = (float)$r['bytes_in'];
    $out[]    = (float)$r['bytes_out'];
}

echo json_encode([
    'labels' => $labels,
    'in'     => $in,
    'out'    => $out,
]);
