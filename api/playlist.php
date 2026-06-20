<?php
// M3U playlist generator for VLC, IPTV players, etc.
// Access: http://localhost/iptv/api/playlist.php

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: audio/x-mpegurl; charset=utf-8');
header('Content-Disposition: attachment; filename="iptv_playlist.m3u"');

$channels = $pdo->query(
    'SELECT ch.id, ch.name, ch.stream_type, ch.url, ch.status,
            cat.category_name
     FROM channels ch
     JOIN categories cat ON cat.id = ch.category_id
     WHERE ch.status = "active"
     ORDER BY cat.category_name ASC, ch.name ASC'
)->fetchAll();

echo "#EXTM3U\n";
echo "#PLAYLIST: IPTV Live\n";

foreach ($channels as $ch) {
    $name = htmlspecialchars($ch['name']);
    $cat  = htmlspecialchars($ch['category_name']);
    $url  = $ch['url'];

    echo "#EXTINF:-1 tvg-id=\"{$ch['id']}\" tvg-name=\"{$name}\" group-title=\"{$cat}\",{$name}\n";
    echo "{$url}\n";
}
