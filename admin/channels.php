<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Channels';
$message   = '';
$error     = '';

// ── Handle POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $csrfOk   = csrfVerify($_POST['_csrf'] ?? '');

    if (!$csrfOk) {
        $error = 'Invalid CSRF token.';
    } elseif ($action === 'create' || $action === 'update') {
        $name       = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $streamType = $_POST['stream_type'] ?? 'M3U8';
        $url        = trim($_POST['url'] ?? '');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);
        $ytFormat   = $_POST['yt_format'] ?? '';
        $directPlay = !empty($_POST['direct_play']) ? 1 : 0;
        $streamKey  = trim($_POST['stream_key'] ?? '');
        $ingestUrl  = trim($_POST['ingest_url'] ?? '');

        $allowedTypes = ['M3U8','MP4','RTMP','Dash','YouTube','Restream','TS','Live'];
        if (!in_array($streamType, $allowedTypes)) $streamType = 'M3U8';

        if ($streamType === 'Live') {
            if ($streamKey === '') {
                $streamKey = bin2hex(random_bytes(12));
            }
            if ($url === '') {
                $url = '/live/' . $streamKey . '/index.m3u8';
            }
        }

        if ($name === '' || ($url === '' && $streamType !== 'Live')) {
            $error = 'Name and URL are required.';
        } else {
            $logo = '';
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg','jpeg','png','gif','svg','webp'];
                if (in_array($ext, $allowedExt)) {
                    $logoName = uniqid('logo_') . '.' . $ext;
                    $dest = __DIR__ . '/../assets/uploads/logos/' . $logoName;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        $logo = 'assets/uploads/logos/' . $logoName;
                    }
                }
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO channels (category_id, name, stream_type, url, stream_key, ingest_url, yt_format, direct_play, logo, sort_order)
                     VALUES (:cat, :name, :type, :url, :sk, :iu, :ytfmt, :direct, :logo, :sort)'
                );
                $stmt->execute([':cat' => $categoryId, ':name' => $name, ':type' => $streamType, ':url' => $url, ':sk' => $streamKey ?: null, ':iu' => $ingestUrl ?: null, ':ytfmt' => $ytFormat ?: null, ':direct' => $directPlay, ':logo' => $logo, ':sort' => $sortOrder]);
                $message = 'Channel created.';
                logActivity($pdo, (int)$_SESSION['admin_id'], 'Channel Created', "Created channel: $name");
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $logoSet = $logo ? 'logo = :logo, ' : '';
                    $stmt = $pdo->prepare(
                        "UPDATE channels SET category_id = :cat, name = :name, stream_type = :type, url = :url, stream_key = :sk, ingest_url = :iu, yt_format = :ytfmt, direct_play = :direct, {$logoSet}sort_order = :sort WHERE id = :id"
                    );
                    $params = [':cat' => $categoryId, ':name' => $name, ':type' => $streamType, ':url' => $url, ':sk' => $streamKey ?: null, ':iu' => $ingestUrl ?: null, ':ytfmt' => $ytFormat ?: null, ':direct' => $directPlay, ':sort' => $sortOrder, ':id' => $id];
                    if ($logo) $params[':logo'] = $logo;
                    $stmt->execute($params);
                    $message = 'Channel updated.';
                    logActivity($pdo, (int)$_SESSION['admin_id'], 'Channel Updated', "Updated channel: $name");
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'inactive';
        $pdo->prepare('UPDATE channels SET status = :s WHERE id = :id')
            ->execute([':s' => $status === 'active' ? 'active' : 'inactive', ':id' => $id]);
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Channel Toggle', "Toggled channel #$id to $status");
        redirect('channels.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM channels WHERE id = :id')->execute([':id' => $id]);
        $message = 'Channel deleted.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Channel Deleted', "Deleted channel #$id");
    }
}

// ── Fetch data ──────────────────────────────────────────────────────────────
$channels   = $pdo->query(
    'SELECT ch.*, cat.category_name FROM channels ch
     JOIN categories cat ON cat.id = ch.category_id
     ORDER BY ch.sort_order ASC, ch.name ASC'
)->fetchAll();
$categories = $pdo->query('SELECT * FROM categories ORDER BY category_name ASC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Channels</h4>
    <button class="btn-accent" style="width:auto;padding:.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#channelModal"
            onclick="resetForm()">+ Add Channel</button>
</div>

<?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Logo</th>
                <th>Name</th>
                <th>Category</th>
                <th>Type</th>
                <th>YT Format</th>
                <th>URL / Stream Key</th>
                <th>Direct</th>
                <th>Status</th>
                <th>Views</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
        </thead>
            <tbody>
                <?php foreach ($channels as $ch): ?>
                <tr>
                    <td><?= $ch['id'] ?></td>
                    <td>
                        <?php if ($ch['logo']): ?>
                            <img src="../<?= htmlspecialchars($ch['logo']) ?>" alt="logo" class="ch-logo-preview">
                        <?php else: ?>
                            <img src="../api/thumbnail.php?channel_id=<?= $ch['id'] ?>"
                                 alt="thumb"
                                 class="thumb-preview"
                                 loading="lazy"
                                 width="80" height="45"
                                 onerror="this.style.display='none'">
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ch['name']) ?></td>
                <td><?= htmlspecialchars($ch['category_name']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($ch['stream_type']) ?></span></td>
                <td><?= $ch['stream_type'] === 'YouTube' ? htmlspecialchars($ch['yt_format'] ?: 'best') : '—' ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= $ch['stream_type'] === 'Live' ? 'Watch: ' . htmlspecialchars($ch['url']) . ' | Ingest: ' . htmlspecialchars($ch['ingest_url'] ?? 'rtmp://host:1935/live') . ' | Key: ' . htmlspecialchars($ch['stream_key'] ?? '') : htmlspecialchars($ch['url']) ?>">
                    <?php if ($ch['stream_type'] === 'Live' && $ch['stream_key']): ?>
                        <span style="color:var(--accent)">🔴 Key: <?= htmlspecialchars(substr($ch['stream_key'], 0, 16)) ?>…</span>
                    <?php else: ?>
                        <?= htmlspecialchars($ch['url']) ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $ch['direct_play'] ? 'bg-success' : 'bg-secondary' ?>"><?= $ch['direct_play'] ? 'ON' : 'OFF' ?></span></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $ch['id'] ?>">
                        <input type="hidden" name="status" value="<?= $ch['status'] === 'active' ? 'inactive' : 'active' ?>">
                        <label class="toggle-switch">
                            <input type="checkbox" onchange="this.form.submit()"
                                <?= $ch['status'] === 'active' ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </form>
                    <span class="badge-status <?= $ch['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?> ms-1">
                        <?= $ch['status'] ?>
                    </span>
                </td>
                <td><?= number_format($ch['total_views']) ?></td>
                <td><?= (int)$ch['sort_order'] ?></td>
                <td>
                    <button class="btn-sm-icon" title="Edit"
                            onclick='editChannel(<?= json_encode($ch) ?>)'
                            data-bs-toggle="modal" data-bs-target="#channelModal">✏️</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this channel?')">
                        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $ch['id'] ?>">
                        <button type="submit" class="btn-sm-icon danger" title="Delete">🗑</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="channelModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="ch-action" value="create">
            <input type="hidden" name="id" id="ch-id" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="ch-modal-title">Add Channel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Channel Name</label>
                    <input type="text" name="name" id="ch-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" id="ch-cat" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Channel Logo</label>
                    <input type="file" name="logo" id="ch-logo" class="form-control" accept="image/*">
                    <small class="form-text">Upload a channel logo (JPG, PNG, GIF, SVG, WEBP). Leave empty to keep current.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stream Type</label>
                    <select name="stream_type" id="ch-type" class="form-control" onchange="toggleUrlHint();toggleYtFormat();toggleLiveFields()">
                        <option value="M3U8">M3U8</option>
                        <option value="MP4">MP4</option>
                        <option value="RTMP">RTMP</option>
                        <option value="Dash">Dash</option>
                        <option value="YouTube">YouTube</option>
                        <option value="Restream">Restream</option>
                        <option value="TS">TS</option>
                        <option value="Live">Live (OBS/vMix)</option>
                    </select>
                </div>
                <div id="live-fields" style="display:none">
                    <div class="mb-3">
                        <label class="form-label">Ingest Server URL</label>
                        <input type="text" name="ingest_url" id="ch-ingest" class="form-control"
                               placeholder="rtmp://your-server-ip:1935/live">
                        <small class="form-text">RTMP server URL for OBS/vMix to push to. Default: rtmp://your-ip:1935/live</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stream Key</label>
                        <div class="input-group">
                            <input type="text" name="stream_key" id="ch-streamkey" class="form-control"
                                   placeholder="Auto-generated if empty" readonly>
                            <button type="button" class="btn btn-outline-secondary" onclick="generateStreamKey()"
                                    style="border-color:var(--border-color);color:var(--text-color)">Regenerate</button>
                        </div>
                        <small class="form-text">Unique key for OBS/vMix. OBS pushes to: <code>rtmp://server/live/<strong id="sk-display">...</strong></code></small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stream URL</label>
                    <input type="text" name="url" id="ch-url" class="form-control" required placeholder="https://...">
                    <small id="url-hint" class="form-text">Paste the full stream URL (M3U8, MP4, RTMP, Dash, or YouTube link)</small>
                </div>
                <div class="mb-3" id="yt-format-group" style="display:none">
                    <label class="form-label">YouTube Quality</label>
                    <select name="yt_format" id="ch-ytfmt" class="form-control">
                        <option value="">best (highest)</option>
                        <option value="best[height<=1080]">1080p</option>
                        <option value="best[height<=720]">720p</option>
                        <option value="best[height<=480]">480p</option>
                        <option value="best[height<=360]">360p</option>
                        <option value="worst">worst (lowest)</option>
                    </select>
                    <small class="form-text">Select output resolution for YouTube streams only.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="ch-sort" class="form-control" value="0" min="0">
                    <small class="form-text">Lower numbers appear first in the viewer panel.</small>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="direct_play" id="ch-direct" class="form-check-input" value="1">
                    <label class="form-check-label" for="ch-direct">Direct Segment Play</label>
                    <small class="form-text d-block">When ON, segments load directly from CDN (faster but need CORS). OFF = proxied through server.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-accent" style="width:auto">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function generateStreamKey() {
    const arr = new Uint8Array(18);
    crypto.getRandomValues(arr);
    const key = Array.from(arr, b => b.toString(36).padStart(2, '0')).join('').slice(0, 24);
    document.getElementById('ch-streamkey').value = key;
    document.getElementById('sk-display').textContent = key;
}
function toggleLiveFields() {
    const type = document.getElementById('ch-type').value;
    const live = document.getElementById('live-fields');
    const urlEl = document.getElementById('ch-url');
    const urlGroup = urlEl.closest('.mb-3');
    if (type === 'Live') {
        live.style.display = '';
        urlGroup.style.display = 'none';
        urlEl.required = false;
        urlEl.disabled = true;
        if (!document.getElementById('ch-streamkey').value) {
            generateStreamKey();
        }
        if (!document.getElementById('ch-ingest').value) {
            document.getElementById('ch-ingest').value = 'rtmp://' + window.location.hostname + ':1935/live';
        }
    } else {
        live.style.display = 'none';
        urlGroup.style.display = '';
        urlEl.required = true;
        urlEl.disabled = false;
    }
}
function resetForm() {
    document.getElementById('ch-action').value = 'create';
    document.getElementById('ch-id').value = '0';
    document.getElementById('ch-modal-title').textContent = 'Add Channel';
    document.getElementById('ch-name').value = '';
    document.getElementById('ch-cat').value = '';
    document.getElementById('ch-logo').value = '';
    document.getElementById('ch-type').value = 'M3U8';
    document.getElementById('ch-url').value = '';
    document.getElementById('ch-sort').value = '0';
    document.getElementById('ch-ytfmt').value = '';
    document.getElementById('ch-direct').checked = false;
    document.getElementById('ch-ingest').value = '';
    document.getElementById('ch-streamkey').value = '';
    toggleYtFormat();
    toggleLiveFields();
}
function editChannel(ch) {
    document.getElementById('ch-action').value = 'update';
    document.getElementById('ch-id').value = ch.id;
    document.getElementById('ch-modal-title').textContent = 'Edit Channel';
    document.getElementById('ch-name').value = ch.name;
    document.getElementById('ch-cat').value = ch.category_id;
    document.getElementById('ch-logo').value = '';
    document.getElementById('ch-type').value = ch.stream_type;
    document.getElementById('ch-url').value = ch.url || '';
    document.getElementById('ch-sort').value = ch.sort_order || 0;
    document.getElementById('ch-ytfmt').value = ch.yt_format || '';
    document.getElementById('ch-direct').checked = ch.direct_play == 1;
    document.getElementById('ch-ingest').value = ch.ingest_url || '';
    document.getElementById('ch-streamkey').value = ch.stream_key || '';
    if (ch.stream_key) {
        document.getElementById('sk-display').textContent = ch.stream_key;
    }
    toggleUrlHint();
    toggleYtFormat();
    toggleLiveFields();
    document.getElementById('ch-url').required = ch.stream_type !== 'Live';
}
function toggleUrlHint() {
    const type = document.getElementById('ch-type').value;
    const hint = document.getElementById('url-hint');
    if (type === 'YouTube') {
        hint.textContent = 'YouTube links open in a new tab. Best: youtube.com/watch?v=VIDEO_ID or @channel/live for live streams.';
    } else if (type === 'Restream') {
        hint.textContent = 'Paste any HLS stream URL (m3u8). The server will proxy/restream it to avoid CORS/IP blocks.';
    } else if (type === 'TS') {
        hint.textContent = 'Direct .ts file URL (e.g. https://example.com/stream.ts).';
    } else if (type === 'Live') {
        hint.textContent = 'Watch URL auto-generated from stream key. Set a custom URL override below if needed.';
    } else {
        hint.textContent = 'Paste the full stream URL (M3U8, MP4, RTMP, or Dash)';
    }
}
function toggleYtFormat() {
    const type = document.getElementById('ch-type').value;
    document.getElementById('yt-format-group').style.display = type === 'YouTube' ? '' : 'none';
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
