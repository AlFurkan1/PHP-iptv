<?php
// yt-dlp path resolver — prefers Linux binary, then .exe (Windows), then system PATH (Docker)
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows) {
    $p = __DIR__ . '/../bin/yt-dlp.exe';
    $ytdl = is_file($p) ? $p : 'yt-dlp';
} else {
    $p = __DIR__ . '/../bin/yt-dlp';
    $ytdl = is_file($p) ? $p : 'yt-dlp';
}
