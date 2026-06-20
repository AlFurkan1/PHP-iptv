<?php
// HLS proxy: relays YouTube HLS streams through the local server
// Accepts ?url=... (video segments) or ?channel_id=... (manifest request)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ytdl.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Cache-Control: public, max-age=15');

// Convert CDN URL to nginx direct-proxy path (no PHP overhead for segments)
function cdnProxyUrl(string $url): string {
    $parsed = parse_url($url);
    $scheme = $parsed['scheme'] ?? 'https';
    $host   = $parsed['host'] ?? '';
    $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $path   = $parsed['path'] ?? '/';
    // If URL has query params, fall back to PHP proxy
    if (isset($parsed['query']) && $parsed['query'] !== '') {
        return '/api/proxy.php?url=' . urlencode($url);
    }
    return '/cdn/' . $scheme . '/' . $host . $port . $path;
}

$urlParam       = $_GET['url'] ?? '';
$channelIdParam = $_GET['channel_id'] ?? '';

// ── Mode 1: Segment proxy ───────────────────────────────────────────
if ($urlParam !== '') {
    $ch = curl_init();
    $parsed = parse_url($urlParam);
    $domain = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    $cookieJar = __DIR__ . '/../cache/cookies_' . str_replace([':', '/', '.'], '_', $parsed['host'] ?? 'unknown') . '.txt';
    curl_setopt_array($ch, [
        CURLOPT_URL            => $urlParam,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TCP_KEEPALIVE  => 1,
        CURLOPT_TCP_KEEPIDLE   => 30,
        CURLOPT_TCP_KEEPINTVL  => 15,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => [
            'Origin: ' . $domain,
            'Referer: ' . $domain . '/',
        ],
    ]);

    $data  = curl_exec($ch);
    $info  = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($data === false || $info['http_code'] !== 200) {
        if ($info['http_code'] === 0 && $error !== '') {
            error_log('proxy.php curl error for ' . $urlParam . ': ' . $error);
        }
        http_response_code(502);
        header('Content-Type: text/plain');
        exit('Proxy error: ' . ($error ?: 'HTTP ' . $info['http_code']));
    }

    $ct = $info['content_type'] ?: '';
    $isPlaylist = str_contains($ct, 'mpegurl') || str_contains($ct, 'vnd.apple.mpegurl')
                  || str_contains($data, '#EXTM3U');

    if ($isPlaylist) {
        $baseUrl = $urlParam;
        if (str_ends_with($baseUrl, '/')) $baseUrl = rtrim($baseUrl, '/');
        $baseUrlDir = substr($baseUrl, 0, strrpos($baseUrl, '/') ?: strlen($baseUrl));
        $parsedSeg  = parse_url($baseUrl);
        $baseRoot   = ($parsedSeg['scheme'] ?? 'https') . '://' . ($parsedSeg['host'] ?? '');
        $lines = explode("\n", $data);
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#EXT-X-KEY')) {
                $trimmed = preg_replace_callback('/URI="([^"]+)"/', function ($m) use ($baseUrlDir, $baseRoot) {
                    $keyUrl = $m[1];
                    if (!preg_match('#^https?://#i', $keyUrl)) {
                        if ($keyUrl[0] === '/') {
                            $keyUrl = rtrim($baseRoot, '/') . '/' . ltrim($keyUrl, '/');
                        } else {
                            $keyUrl = rtrim($baseUrlDir, '/') . '/' . ltrim($keyUrl, '/');
                        }
                    }
                    return 'URI="' . cdnProxyUrl($keyUrl) . '"';
                }, $trimmed);
            } elseif ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
                if (preg_match('#^https?://#i', $trimmed)) {
                } elseif ($trimmed[0] === '/') {
                    $trimmed = rtrim($baseRoot, '/') . '/' . ltrim($trimmed, '/');
                } else {
                    $trimmed = rtrim($baseUrlDir, '/') . '/' . ltrim($trimmed, '/');
                }
                $trimmed = cdnProxyUrl($trimmed);
            }
            $out[] = $trimmed;
        }
        $data = implode("\n", $out);
    }

    header('Content-Type: ' . ($ct ?: ($isPlaylist ? 'application/vnd.apple.mpegurl' : 'application/octet-stream')));
    if (!$isPlaylist && $info['download_content_length'] > 0) {
        header('Content-Length: ' . $info['download_content_length']);
    }
    header('Cache-Control: public, max-age=30');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 30) . ' GMT');
    echo $data;
    exit;
}

// ── Mode 2: Manifest proxy (channel_id-based) ───────────────────────
if ($channelIdParam === '' || !ctype_digit($channelIdParam)) {
    http_response_code(400);
    exit('Missing url or channel_id parameter');
}

$cacheDir  = __DIR__ . '/../cache';
$cacheKey  = 'hls_' . $channelIdParam;
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

// Check cache (30-second TTL)
$hlsUrl      = '';
$cacheExpiry = 30;
$cacheType   = null;
if (file_exists($cacheFile)) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && isset($cached['url']) && (time() - $cached['time']) < $cacheExpiry) {
        $hlsUrl   = $cached['url'];
        $cacheType = $cached['type'] ?? null;
    }
}

// Verify cached type matches current channel type (invalidate if changed)
if ($hlsUrl !== '' && $cacheType !== null) {
    $typeCheck = $pdo->prepare('SELECT stream_type FROM channels WHERE id = :id LIMIT 1');
    $typeCheck->execute([':id' => (int)$channelIdParam]);
    $currentType = $typeCheck->fetchColumn();
    if ($currentType !== false && $currentType !== $cacheType) {
        $hlsUrl = ''; // type changed, force refresh
    }
}

// If not cached, expired, or stream type changed, resolve fresh
if ($hlsUrl === '') {
    $stmt = $pdo->prepare('SELECT url, stream_type, direct_play FROM channels WHERE id = :id AND status = :st LIMIT 1');
    $stmt->execute([':id' => (int)$channelIdParam, ':st' => 'active']);
    $chRow = $stmt->fetch();

    if (!$chRow) {
        http_response_code(404);
        exit('Channel not found');
    }

    if ($chRow['stream_type'] === 'YouTube') {
        // YouTube: run yt-dlp to get fresh HLS URL
        $safeUrl = escapeshellarg($chRow['url']);
        $ytFmt   = !empty($chRow['yt_format']) ? '-f ' . escapeshellarg($chRow['yt_format']) : '';
        $cmd     = "\"$ytdl\" $ytFmt -g --no-warnings $safeUrl 2>NUL";
        $output  = [];
        $code    = 0;
        exec($cmd, $output, $code);

        if ($code !== 0 || empty($output[0])) {
            http_response_code(502);
            exit('Could not resolve YouTube stream URL');
        }

        $hlsUrl = trim($output[0]);
    } else {
        // Restream or direct: use the URL from DB as-is
        $hlsUrl = $chRow['url'];
    }

    // Write to cache
    file_put_contents($cacheFile, json_encode(['url' => $hlsUrl, 'time' => time(), 'type' => $chRow['stream_type']]), LOCK_EX);
    $cacheType  = $chRow['stream_type'];
    $directPlay = !empty($chRow['direct_play']);
} else {
    // Fetch from cache - need to determine direct_play from DB
    $dpStmt = $pdo->prepare('SELECT direct_play FROM channels WHERE id = :id LIMIT 1');
    $dpStmt->execute([':id' => (int)$channelIdParam]);
    $directPlay = !empty($dpStmt->fetchColumn());
}

// Fetch the HLS manifest
$ch = curl_init();
$parsedHlsUrl = parse_url($hlsUrl);
$cookieJar    = __DIR__ . '/../cache/cookies_' . str_replace([':', '/', '.'], '_', $parsedHlsUrl['host'] ?? 'unknown') . '.txt';
$curlOpts = [
    CURLOPT_URL            => $hlsUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TCP_KEEPALIVE  => 1,
    CURLOPT_TCP_KEEPIDLE   => 30,
    CURLOPT_TCP_KEEPINTVL  => 15,
    CURLOPT_COOKIEJAR      => $cookieJar,
    CURLOPT_COOKIEFILE     => $cookieJar,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
];

// Add Referer/Origin headers — YouTube needs explicit youtube.com, others use the target domain
if ($cacheType === 'YouTube') {
    $curlOpts[CURLOPT_HTTPHEADER] = [
        'Origin: https://www.youtube.com',
        'Referer: https://www.youtube.com/',
    ];
} else {
    $hlsDomain = ($parsedHlsUrl['scheme'] ?? 'https') . '://' . ($parsedHlsUrl['host'] ?? '');
    $curlOpts[CURLOPT_HTTPHEADER] = [
        'Origin: ' . $hlsDomain,
        'Referer: ' . $hlsDomain . '/',
    ];
}

curl_setopt_array($ch, $curlOpts);

$data  = curl_exec($ch);
$info  = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

if ($data === false || $info['http_code'] !== 200) {
    if ($info['http_code'] === 0 && $error !== '') {
        error_log('proxy.php(channel) curl error for channel ' . $channelIdParam . ': ' . $error);
    }
    http_response_code(502);
    header('Content-Type: text/plain');
    exit('Proxy error: ' . ($error ?: 'HTTP ' . $info['http_code']));
}

$contentType = $info['content_type'] ?: 'application/vnd.apple.mpegurl';
header('Content-Type: ' . $contentType);
// Don't forward Content-Length — rewritten URLs are longer than origin

// Read proxy cache TTL from settings (default 3 seconds)
$proxyTtlFile = $cacheDir . '/proxy_ttl.txt';
$proxyTtl     = is_file($proxyTtlFile) ? (int)trim(file_get_contents($proxyTtlFile)) : 1;
if ($proxyTtl < 1) $proxyTtl = 1;

// Check output cache
$outCacheFile = $cacheDir . '/hls_out_' . $channelIdParam . '.txt';
if (file_exists($outCacheFile)) {
    $outCached = file_get_contents($outCacheFile);
    $outData   = @json_decode($outCached, true);
    if ($outData && isset($outData['output'], $outData['time']) && (time() - $outData['time']) < $proxyTtl) {
        header('Content-Type: ' . ($outData['content_type'] ?? 'application/vnd.apple.mpegurl'));
        echo $outData['output'];
        exit;
    }
}

// Rewrite segment URLs to go through proxy
$baseDir  = dirname($hlsUrl);
$parsedHls = parse_url($hlsUrl);
$baseRoot = ($parsedHls['scheme'] ?? 'https') . '://' . ($parsedHls['host'] ?? '');
$lines    = explode("\n", $data);
$out      = [];

foreach ($lines as $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '#EXT-X-KEY')) {
        $trimmed = preg_replace_callback('/URI="([^"]+)"/', function ($m) use ($baseDir, $baseRoot, $directPlay) {
            $keyUrl = $m[1];
            if (!preg_match('#^https?://#i', $keyUrl)) {
                if ($keyUrl[0] === '/') {
                    $keyUrl = rtrim($baseRoot, '/') . '/' . ltrim($keyUrl, '/');
                } else {
                    $keyUrl = rtrim($baseDir, '/') . '/' . ltrim($keyUrl, '/');
                }
            }
            return 'URI="' . ($directPlay ? $keyUrl : cdnProxyUrl($keyUrl)) . '"';
        }, $trimmed);
    } elseif ($trimmed !== '' && !str_starts_with($trimmed, '#')) {
        if (preg_match('#^https?://#i', $trimmed)) {
        } elseif ($trimmed[0] === '/') {
            $trimmed = rtrim($baseRoot, '/') . '/' . ltrim($trimmed, '/');
        } else {
            $trimmed = rtrim($baseDir, '/') . '/' . ltrim($trimmed, '/');
        }
        if (!$directPlay) {
            $trimmed = cdnProxyUrl($trimmed);
        }
    }
    $out[] = $trimmed;
}

$output = implode("\n", $out);

// Write output cache
file_put_contents($outCacheFile, json_encode([
    'output'       => $output,
    'content_type' => $contentType,
    'time'         => time(),
]), LOCK_EX);

echo $output;
