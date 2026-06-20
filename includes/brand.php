<?php
$brandFile = __DIR__ . '/../cache/branding.json';
$brand = ['site_name' => 'IPTV Live', 'site_logo' => ''];
if (is_file($brandFile)) {
    $data = json_decode(file_get_contents($brandFile), true);
    if (is_array($data)) {
        $brand['site_name'] = $data['site_name'] ?? 'IPTV Live';
        $brand['site_logo'] = $data['site_logo'] ?? '';
    }
}
