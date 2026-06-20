<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Activity Logs';

// Auto-cleanup old logs based on retention setting
$logRetFile   = __DIR__ . '/../cache/log_retention.txt';
$logRetention = is_file($logRetFile) ? (int)trim(file_get_contents($logRetFile)) : 30;
if ($logRetention > 0) {
    $pdo->exec('DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . (int)$logRetention . ' DAY)');
}

// Pagination
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$total = $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();

$logs = $pdo->prepare(
    'SELECT a.*, ad.username
     FROM activity_logs a
     LEFT JOIN admins ad ON ad.id = a.admin_id
     ORDER BY a.created_at DESC
     LIMIT :limit OFFSET :offset'
);
$logs->execute([':limit' => $perPage, ':offset' => $offset]);
$logs = $logs->fetchAll();

$totalPages = max(1, (int)ceil($total / $perPage));

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Activity Logs</h4>
    <span class="text-secondary"><?= (int)$total ?> total entries</span>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Details</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" class="text-center text-secondary py-3">No activity logs.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr>
                <td><?= $log['id'] ?></td>
                <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                <td style="white-space:nowrap"><?= $log['created_at'] ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link bg-transparent text-secondary border-secondary" href="?p=<?= $i ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
