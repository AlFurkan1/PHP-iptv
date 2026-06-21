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
$netSettingsFile = __DIR__ . '/../cache/settings.json';
$netSettings     = is_file($netSettingsFile) ? json_decode(file_get_contents($netSettingsFile), true) : [];
$netInterface    = $netSettings['net_interface'] ?? 'eth0';
$graphType       = $netSettings['graph_type'] ?? 'area';

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
    } elseif (isset($_POST['net_interface'])) {
        $iface = trim($_POST['net_interface']);
        $gtype = $_POST['graph_type'] === 'bar' ? 'bar' : 'area';
        file_put_contents($netSettingsFile, json_encode(['net_interface' => $iface, 'graph_type' => $gtype], JSON_UNESCAPED_SLASHES), LOCK_EX);
        $netInterface = $iface;
        $graphType    = $gtype;
        $message = 'Network monitoring settings saved.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Settings Updated', 'Network interface: ' . $iface . ', graph: ' . $gtype);
    } elseif (isset($_POST['curr_user']) && isset($_POST['curr_pass'])) {
        $currUser = trim($_POST['curr_user'] ?? '');
        $currPass = $_POST['curr_pass'] ?? '';
        $newUser  = trim($_POST['new_user'] ?? '');
        $newPass  = $_POST['new_pass'] ?? '';
        $confirm  = $_POST['confirm_pass'] ?? '';

        $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $error = 'Admin account not found.';
        } elseif (!password_verify($currPass, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newUser === '' && $newPass === '') {
            $error = 'Enter a new username or new password.';
        } elseif ($newPass !== '' && $newPass !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif ($newPass !== '' && strlen($newPass) < 4) {
            $error = 'New password must be at least 4 characters.';
        } else {
            $updates = [];
            $params  = [':id' => (int)$_SESSION['admin_id']];

            if ($newUser !== '' && $newUser !== $admin['username']) {
                $updates[] = 'username = :u';
                $params[':u'] = $newUser;
                $_SESSION['admin_user'] = $newUser;
            }

            if ($newPass !== '') {
                $updates[] = 'password = :p';
                $params[':p'] = password_hash($newPass, PASSWORD_BCRYPT);
            }

            if (!empty($updates)) {
                $sql = 'UPDATE admins SET ' . implode(', ', $updates) . ' WHERE id = :id';
                $pdo->prepare($sql)->execute($params);
                $message = 'Credentials updated successfully.';
                logActivity($pdo, (int)$_SESSION['admin_id'], 'Credentials Updated', 'Username or password changed');
            } else {
                $error = 'No changes detected.';
            }
        }
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

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Network Monitoring</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Network Interface</label>
                <input type="text" name="net_interface" class="form-control" value="<?= htmlspecialchars($netInterface) ?>" required>
                <small class="form-text">Name of the network interface to monitor (e.g., eth0, ens3, eth1). Found in <code>/proc/net/dev</code> inside the nginx container.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Graph Style</label>
                <select name="graph_type" class="form-select">
                    <option value="area" <?= $graphType === 'area' ? 'selected' : '' ?>>Area Chart</option>
                    <option value="bar"  <?= $graphType === 'bar' ? 'selected' : '' ?>>Bar Chart</option>
                </select>
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Save Network Settings</button>
        </form>
    </div>
</div>

<div class="card mt-4" style="background:var(--bg-card);border:1px solid var(--border-color);max-width:600px">
    <div class="card-body">
        <h5 class="mb-3">Change Username / Password</h5>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label">Current Username</label>
                <input type="text" name="curr_user" class="form-control"
                       value="<?= htmlspecialchars($_SESSION['admin_user']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="curr_pass" class="form-control" required>
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label">New Username</label>
                <input type="text" name="new_user" class="form-control"
                       placeholder="Leave blank to keep current">
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_pass" class="form-control"
                       placeholder="Leave blank to keep current">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_pass" class="form-control"
                       placeholder="Repeat new password">
            </div>
            <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Update Credentials</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
