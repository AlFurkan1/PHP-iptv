<?php
require_once __DIR__ . '/../includes/db.php';

$netFile = __DIR__ . '/../cache/netstats.json';

if (!is_file($netFile)) {
    http_response_code(503);
    exit('netstats file not ready yet');
}

$data = json_decode(file_get_contents($netFile), true);

if ($data && isset($data['rx'], $data['tx'])) {
    $pdo->prepare(
        'INSERT INTO traffic_stats (recorded_at, bytes_in, bytes_out) VALUES (NOW(), :in, :out)'
    )->execute([':in' => (int)$data['rx'], ':out' => (int)$data['tx']]);

    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(500);
    echo 'Invalid netstats data';
}
