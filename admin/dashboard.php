<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Dashboard';

// ── Stats ──────────────────────────────────────────────────────────────────
$totalChannels = $pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn();
$activeStreams = $pdo->query("SELECT COUNT(*) FROM channels WHERE status = 'active'")->fetchColumn();
$onlineVisitors = $pdo->query(
    'SELECT COUNT(DISTINCT ip_address) FROM live_sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
)->fetchColumn();

// ── Active sessions (last 5 minutes) ────────────────────────────────────────
$sessions = $pdo->query(
    'SELECT ls.id, ls.ip_address, ls.user_agent, ls.last_activity,
            ch.name AS channel_name
     FROM live_sessions ls
     LEFT JOIN channels ch ON ch.id = ls.channel_id
     WHERE ls.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY ls.last_activity DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Dashboard</h4>
    <span class="text-secondary"><?= date('Y-m-d H:i:s') ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-label">Active Streams</div>
            <div class="stat-value text-success"><?= (int)$activeStreams ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-label">Total Channels</div>
            <div class="stat-value"><?= (int)$totalChannels ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-label">Online Visitors</div>
            <div class="stat-value text-warning"><?= (int)$onlineVisitors ?></div>
        </div>
    </div>
</div>

<div class="admin-header">
    <h5>Live Traffic Monitor</h5>
    <span class="text-secondary">Viewers active in the last 5 minutes</span>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>IP Address</th>
                <th>User Agent</th>
                <th>Channel</th>
                <th>Live Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sessions)): ?>
            <tr><td colspan="4" class="text-center text-secondary py-3">No active viewers.</td></tr>
            <?php else: foreach ($sessions as $s): ?>
            <tr>
                <td><code><?= htmlspecialchars($s['ip_address']) ?></code></td>
                <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                    title="<?= htmlspecialchars($s['user_agent']) ?>">
                    <?= htmlspecialchars(explode('(', $s['user_agent'])[0]) ?>
                </td>
                <td><?= htmlspecialchars($s['channel_name'] ?? '—') ?></td>
                <td class="live-time" data-time="<?= strtotime($s['last_activity']) ?>">
                    <?= (new DateTime($s['last_activity']))->diff(new DateTime())->format('%H:%I:%S') ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
// Live time ticker
(function() {
    const cells = document.querySelectorAll('.live-time');
    setInterval(() => {
        cells.forEach(cell => {
            const ts = parseInt(cell.dataset.time);
            if (!ts) return;
            const diff = Math.floor((Date.now() / 1000) - ts);
            const h = String(Math.floor(diff / 3600)).padStart(2, '0');
            const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            const s = String(diff % 60).padStart(2, '0');
            cell.textContent = `${h}:${m}:${s}`;
        });
    }, 1000);
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
