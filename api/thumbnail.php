<?php
// Returns a cached thumbnail image for a given channel
// Proxies YouTube thumbnails through the server and caches the image locally

require_once __DIR__ . '/../includes/db.php';

$channelId = $_GET['channel_id'] ?? '';
if ($channelId === '' || !ctype_digit($channelId)) {
    http_response_code(400);
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="90" viewBox="0 0 160 90"><rect fill="#333" width="160" height="90"/><text x="80" y="48" fill="#666" font-size="12" text-anchor="middle" font-family="sans-serif">No ID</text></svg>';
    exit;
}

$cacheDir   = __DIR__ . '/../cache';
require_once __DIR__ . '/../includes/ytdl.php';
$metaFile   = $cacheDir . '/thumb_' . $channelId . '.json';
$imageFile  = $cacheDir . '/thumb_' . $channelId . '.jpg';
$metaExpiry = 300; // re-check video ID every 5 min
$imgExpiry  = 3600; // cache image for 1 hour

// Check cached image first
if (file_exists($imageFile) && (time() - filemtime($imageFile)) < $imgExpiry) {
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=3600');
    readfile($imageFile);
    exit;
}

// Resolve video ID (from meta cache or yt-dlp)
$videoId = '';
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    if ($meta && isset($meta['video_id']) && (time() - $meta['time']) < $metaExpiry) {
        $videoId = $meta['video_id'];
    }
}

if ($videoId === '') {
    $stmt = $pdo->prepare('SELECT url, stream_type FROM channels WHERE id = :id AND status = :st LIMIT 1');
    $stmt->execute([':id' => (int)$channelId, ':st' => 'active']);
    $chRow = $stmt->fetch();

    if (!$chRow) {
        http_response_code(404);
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="90" viewBox="0 0 160 90"><rect fill="#333" width="160" height="90"/><text x="80" y="48" fill="#666" font-size="12" text-anchor="middle" font-family="sans-serif">No Channel</text></svg>';
        exit;
    }

    if ($chRow['stream_type'] === 'YouTube') {
        $safeUrl = escapeshellarg($chRow['url']);
        $cmd     = "\"$ytdl\" --get-id --no-warnings $safeUrl 2>NUL";
        $output  = [];
        $code    = 0;
        exec($cmd, $output, $code);
        if ($code === 0 && !empty($output[0])) {
            $videoId = trim($output[0]);
        }
    }

    file_put_contents($metaFile, json_encode(['video_id' => $videoId, 'time' => time()]), LOCK_EX);
}

// Fetch and cache the image
if ($videoId !== '') {
    $thumbUrl = 'https://img.youtube.com/vi/' . rawurlencode($videoId) . '/mqdefault.jpg';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $thumbUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($imgData !== false && $httpCode === 200) {
        file_put_contents($imageFile, $imgData, LOCK_EX);
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=3600');
        echo $imgData;
        exit;
    }
}

// Fallback placeholder
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="90" viewBox="0 0 160 90"><rect fill="#222" width="160" height="90"/><text x="80" y="48" fill="#555" font-size="14" text-anchor="middle" font-family="sans-serif">📺</text><text x="80" y="68" fill="#555" font-size="10" text-anchor="middle" font-family="sans-serif">No Preview</text></svg>';
