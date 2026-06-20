<?php require_once __DIR__ . '/../../includes/brand.php'; ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= htmlspecialchars($brand['site_name']) ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="brand"><?= htmlspecialchars($brand['site_name']) ?> Admin</div>
        <nav>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'dashboard.php' ? 'active' : '' ?>"
               href="dashboard.php">📊 Dashboard</a>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'channels.php' ? 'active' : '' ?>"
               href="channels.php">📺 Channels</a>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'categories.php' ? 'active' : '' ?>"
                href="categories.php">🏷 Categories</a>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'import.php' ? 'active' : '' ?>"
                href="import.php">📥 Import M3U</a>
            <a class="nav-link" href="../api/playlist.php" target="_blank">📋 M3U Playlist</a>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'logs.php' ? 'active' : '' ?>"
                href="logs.php">📋 Activity Logs</a>
            <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'active' : '' ?>"
                href="settings.php">⚙️ Settings</a>
            <a class="nav-link" href="logout.php">🚪 Logout</a>
        </nav>
    </aside>
    <main class="admin-content">
