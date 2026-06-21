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

<div class="admin-header mt-4">
    <h5>Traffic (nginx eth0)</h5>
    <span class="text-secondary">Bandwidth usage over time</span>
</div>

<div class="card mb-4" style="background:var(--bg-card);border:1px solid var(--border-color)">
    <div class="card-body">
        <form class="row g-3 mb-3 align-items-end" id="traffic-filter">
            <div class="col-auto">
                <label class="form-label small">From</label>
                <input type="date" name="from" id="tf-from" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small">To</label>
                <input type="date" name="to" id="tf-to" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button type="button" class="btn-accent btn-sm" onclick="loadTraffic()"
                        style="padding:.3rem 1rem">Apply</button>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="quickRange(7)"  style="border-color:var(--border-color);color:var(--text-color);padding:.3rem .8rem">7d</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="quickRange(30)" style="border-color:var(--border-color);color:var(--text-color);padding:.3rem .8rem">30d</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="quickRange(90)" style="border-color:var(--border-color);color:var(--text-color);padding:.3rem .8rem">90d</button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        onclick="quickRange(365)" style="border-color:var(--border-color);color:var(--text-color);padding:.3rem .8rem">1y</button>
            </div>
        </form>
        <canvas id="traffic-chart" height="220"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
let trafficChart = null;

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B','KB','MB','GB','TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function loadTraffic() {
    const from = document.getElementById('tf-from').value;
    const to   = document.getElementById('tf-to').value;
    const url  = '../api/traffic_data.php?' + new URLSearchParams({from, to});

    fetch(url)
        .then(r => r.json())
        .then(d => {
            if (!d.labels || d.labels.length === 0) {
                document.getElementById('traffic-chart').style.display = 'none';
                return;
            }
            document.getElementById('traffic-chart').style.display = '';

            if (trafficChart) trafficChart.destroy();

            const ctx = document.getElementById('traffic-chart').getContext('2d');
            trafficChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            label: 'Download',
                            data: d.in,
                            backgroundColor: 'rgba(0,188,212,0.7)',
                            borderColor: '#00bcd4',
                            borderWidth: 1,
                            borderRadius: 3,
                        },
                        {
                            label: 'Upload',
                            data: d.out,
                            backgroundColor: 'rgba(255,87,34,0.7)',
                            borderColor: '#ff5722',
                            borderWidth: 1,
                            borderRadius: 3,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#ccc' } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ' + formatBytes(ctx.parsed.y)
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: '#999', maxRotation: 45 },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        },
                        y: {
                            ticks: {
                                color: '#999',
                                callback: v => formatBytes(v)
                            },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        }
                    }
                }
            });
        })
        .catch(() => {});
}

function quickRange(days) {
    const to   = new Date();
    const from = new Date();
    from.setDate(from.getDate() - days);
    document.getElementById('tf-from').value = from.toISOString().slice(0,10);
    document.getElementById('tf-to').value   = to.toISOString().slice(0,10);
    loadTraffic();
}

(function() {
    quickRange(30);
})();

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
