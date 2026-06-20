<?php
// Database configuration — written by admin/settings.php or env vars (Docker)
// DO NOT edit manually while the settings page is in use

$envHost = getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: null;

return [
    'host'    => $envHost ?: '127.0.0.1',
    'port'    => getenv('MYSQL_PORT') ?: '3306',
    'name'    => getenv('MYSQL_DATABASE') ?: 'iptv_system',
    'user'    => getenv('MYSQL_USER') ?: 'root',
    'pass'    => getenv('MYSQL_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
