<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Settings';
$message   = '';
$error     = '';
$config    = is_file(__DIR__ . '/../includes/config.php') ? require __DIR__ . '/../includes/config.php' : [];
$noticeFile = __DIR__ . '/../cache/notice.txt';
$noticeText = is_file($noticeFile) ? file_get_contents($noticeFile) : '';
$brandFile  = __DIR__ . '/../cache/branding.json';
$brandData  = is_file($brandFile) ? json_decode(file_get_contents($brandFile), true) : [];
$siteName   = $brandData['site_name'] ?? 'IPTV Live';
$siteLogo   = $brandData['site_logo'] ?? '';
$proxyTtlFile = __DIR__ . '/../cache/proxy_ttl.txt';
$proxyTtl     = is_file($proxyTtlFile) ? (int)trim(file_get_contents($proxyTtlFile)) : 3;
$sessionTtlFile = __DIR__ . '/../cache/session_timeout.txt';
$sessionTtl     = is_file($sessionTtlFile) ? (int)trim(file_get_contents($sessionTtlFile)) : 60;
$logRetFile     = __DIR__ . '/../cache/log_retention.txt';
$logRetention   = is_file($logRetFile) ? (int)trim(file_get_contents($logRetFile)) : 30;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfVerify($_POST['_csrf'] ?? '')) {
    if (isset($_POST['site_name'])) {
        $siteName = trim($_POST['site_name'] ?? 'IPTV Live');
        $siteLogo = trim($_POST['site_logo'] ?? '');
        file_put_contents($brandFile, json_encode(['site_name' => $siteName, 'site_logo' => $siteLogo], JSON_UNESCAPED_SLASHES), LOCK_EX);
        $message = 'Branding updated.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Branding Updated', 'Site name/logo changed');
    } elseif (isset($_POST['notice'])) {
        file_put_contents($noticeFile, $_POST['notice'], LOCK_EX);
        $noticeText = $_POST['notice'];
        $message = 'Notice updated.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Notice Updated', 'Visitor notice changed');
    } elseif (isset($_POST['proxy_ttl'])) {
        $val = max(1, (int)$_POST['proxy_ttl']);
        file_put_contents($proxyTtlFile, (string)$val, LOCK_EX);
        $proxyTtl = $val;
        $message = 'Proxy cache TTL updated to ' . $val . ' seconds.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Settings Updated', 'Proxy cache TTL changed to ' . $val . 's');
    } elseif (isset($_POST['session_ttl'])) {
        $val = max(10, (int)$_POST['session_ttl']);
        file_put_contents($sessionTtlFile, (string)$val, LOCK_EX);
        $sessionTtl = $val;
        $message = 'Session timeout updated to ' . $val . ' seconds.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Settings Updated', 'Session timeout changed to ' . $val . 's');
    } elseif (isset($_POST['log_retention'])) {
        $val = max(1, (int)$_POST['log_retention']);
        file_put_contents($logRetFile, (string)$val, LOCK_EX);
        $logRetention = $val;
        $message = 'Log retention updated to ' . $val . ' days.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Settings Updated', 'Log retention changed to ' . $val . ' days');
    } else {
    $host    = trim($_POST['host'] ?? '127.0.0.1');
    $port    = trim($_POST['port'] ?? '3306');
    $name    = trim($_POST['dbname'] ?? 'iptv_system');
    $user    = trim($_POST['user'] ?? 'root');
    $pass    = $_POST['pass'] ?? '';
    $charset = trim($_POST['charset'] ?? 'utf8mb4');

    if ($host === '' || $port === '' || $name === '' || $user === '') {
        $error = 'Host, Port, Database Name, and Username are required.';
    } else {
        // Test the new connection before saving
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
            $test = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $test->query('SELECT 1');

            // Write new config
            $content = "<?php\n// Database configuration — written by admin/settings.php\n\nreturn [\n" .
                "    'host'    => " . var_export($host, true) . ",\n" .
                "    'port'    => " . var_export($port, true) . ",\n" .
                "    'name'    => " . var_export($name, true) . ",\n" .
                "    'user'    => " . var_export($user, true) . ",\n" .
                "    'pass'    => " . var_export($pass, true) . ",\n" .
                "    'charset' => " . var_export($charset, true) . ",\n" .
                "];\n";

            if (file_put_contents(__DIR__ . '/../includes/config.php', $content, LOCK_EX)) {
                $message = 'Database settings saved and verified.';
                $config  = compact('host', 'port', 'name', 'user', 'pass', 'charset');
                logActivity($pdo, (int)$_SESSION['admin_id'], 'Settings Updated', 'Database configuration changed');
            } else {
                $error = 'Could not write config file. Check file permissions.';
            }
        } catch (PDOException $e) {
            $error = 'Connection failed with these credentials: ' . $e->getMessage();
        }
    }
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Settings</h4>
</div>

<?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

            <div class="mb-3">
                <label class="form-label">Host</label>
                <input type="text" name="host" class="form-control" value="<?= htmlspecialchars($config['host'] ?? '127.0.0.1') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Port</label>
                <input type="text" name="port" class="form-control" value="<?= htmlspecialchars($config['port'] ?? '3306') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Database Name</label>
                <input type="text" name="dbname" class="form-control" value="<?= htmlspecialchars($config['name'] ?? 'iptv_system') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="user" class="form-control" value="<?= htmlspecialchars($config['user'] ?? 'root') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="pass" class="form-control" value="<?= htmlspecialchars($config['pass'] ?? '') ?>">
                <small class="form-text text-secondary">Leave unchanged to keep current password.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Charset</label>
                <input type="text" name="charset" class="form-control" value="<?= htmlspecialchars($config['charset'] ?? 'utf8mb4') ?>">
            </div>

            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Test & Save</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Site Branding</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Site Name</label>
                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($siteName) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Site Logo URL</label>
                <input type="text" name="site_logo" class="form-control" value="<?= htmlspecialchars($siteLogo) ?>" placeholder="assets/uploads/logos/site.png or https://...">
                <small class="form-text">Relative path or full URL to logo image. Leave empty for default icon.</small>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save Branding</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Visitor Notice (Marquee)</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <textarea name="notice" class="form-control" rows="3" placeholder="Enter notice text to show at top of viewer page..."><?= htmlspecialchars($noticeText) ?></textarea>
                <small class="form-text">Leave empty to hide the marquee.</small>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save Notice</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Proxy Cache TTL</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Cache Duration (seconds)</label>
                <input type="number" name="proxy_ttl" class="form-control" value="<?= $proxyTtl ?>" min="1" max="30" required>
                <small class="form-text">How long to cache HLS manifest responses (1-30s). Lower = fresher segments, higher = less upstream load.</small>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save TTL</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Session Timeout</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Session Timeout (seconds)</label>
                <input type="number" name="session_ttl" class="form-control" value="<?= $sessionTtl ?>" min="10" max="600" required>
                <small class="form-text">How long after last heartbeat a viewer is still counted as online (10-600s).</small>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save Session Timeout</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Activity Log Retention</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Keep Logs for (days)</label>
                <input type="number" name="log_retention" class="form-control" value="<?= $logRetention ?>" min="1" max="365" required>
                <small class="form-text">Auto-delete activity logs older than this many days.</small>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save Retention</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
