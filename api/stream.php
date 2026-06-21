<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid channel ID']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name, stream_type, url, yt_format, direct_play, status FROM channels WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => (int)$_GET['id']]);
$ch = $stmt->fetch();

if (!$ch || $ch['status'] === 'inactive') {
    http_response_code(404);
    echo json_encode(['error' => 'Channel not found or inactive']);
    exit;
}

// Increment view count
$pdo->prepare('UPDATE channels SET total_views = total_views + 1 WHERE id = :id')
   ->execute([':id' => $ch['id']]);

$mimeMap = [
    'M3U8'     => 'application/x-mpegURL',
    'MP4'      => 'video/mp4',
    'RTMP'     => 'video/rtmp',
    'Dash'     => 'application/dash+xml',
    'Restream' => 'application/x-mpegURL',
    'TS'       => 'video/mp2t',
    'Live'     => 'application/x-mpegURL',
];

$url     = $ch['url'];
$mime    = $mimeMap[$ch['stream_type']] ?? 'video/mp4';
require_once __DIR__ . '/../includes/ytdl.php';

// For YouTube streams, extract the direct stream URL via yt-dlp
if ($ch['stream_type'] === 'YouTube') {
    $safeUrl = escapeshellarg($url);
    $ytFmt   = !empty($ch['yt_format']) ? '-f ' . escapeshellarg($ch['yt_format']) : '';
    $cmd     = "\"$ytdl\" $ytFmt -g --no-warnings $safeUrl 2>NUL";
    $output  = [];
    $code    = 0;
    exec($cmd, $output, $code);

    if ($code === 0 && !empty($output[0])) {
        $hlsUrl = trim($output[0]);

        // Store the YouTube URL in cache so proxy.php can look it up by channel_id
        $cacheKey  = 'hls_' . $ch['id'];
        $cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.json';
        file_put_contents(
            $cacheFile,
            json_encode(['url' => $hlsUrl, 'time' => time(), 'type' => 'YouTube']),
            LOCK_EX
        );

        $url  = 'api/proxy.php?channel_id=' . $ch['id'];
        $mime = 'application/x-mpegURL';
    } else {
        http_response_code(502);
        echo json_encode(['error' => 'Could not resolve YouTube stream URL. Make sure the video exists and is accessible.']);
        exit;
    }
}

// For M3U8/Restream channels
if ($ch['stream_type'] === 'M3U8' || $ch['stream_type'] === 'Restream') {
    if (!empty($ch['direct_play'])) {
        // Direct CDN — no proxy overhead, browser loads manifest & segments straight from CDN
        $mime = 'application/x-mpegURL';
    } else {
        // Proxy through server to rewrite segment URLs (avoids CORS/IP issues if CDN doesn't allow it)
        $cacheKey  = 'hls_' . $ch['id'];
        $cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.json';
        file_put_contents(
            $cacheFile,
            json_encode(['url' => $url, 'time' => time(), 'type' => $ch['stream_type']]),
            LOCK_EX
        );
        $url  = 'api/proxy.php?channel_id=' . $ch['id'];
        $mime = 'application/x-mpegURL';
    }
}

// For direct_play non-HLS types (MP4, TS), serve original URL directly
if (!empty($ch['direct_play']) && in_array($ch['stream_type'], ['MP4', 'TS'])) {
    // URL already set to original, mime already set
}

echo json_encode([
    'id'          => $ch['id'],
    'name'        => htmlspecialchars($ch['name']),
    'url'         => $url,
    'mime'        => $mime,
    'stream_type' => $ch['stream_type'],
]);
