<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Import M3U';
$message   = '';
$error     = '';

function parseM3U(string $content, PDO $pdo, array &$imported, array &$skipped): void
{
    $lines = explode("\n", $content);
    $i     = 0;

    while ($i < count($lines)) {
        $line = trim($lines[$i]);
        $i++;

        if (!preg_match('/^#EXTINF:/i', $line)) continue;

        $name    = '';
        $catName = '';

        if (preg_match('/group-title="([^"]*)"/i', $line, $m)) {
            $catName = trim($m[1]);
        }
        if (preg_match('/tvg-name="([^"]*)"/i', $line, $m)) {
            $name = trim($m[1]);
        }
        if ($name === '') {
            $parts = explode(',', $line, 2);
            $name  = trim(end($parts));
        }

        while ($i < count($lines) && trim($lines[$i]) === '') {
            $i++;
        }
        $url = trim($lines[$i] ?? '');
        $i++;

        if ($name === '' || $url === '') continue;

        $type = 'M3U8';
        if (preg_match('/\.mp4/i', $url))         $type = 'MP4';
        elseif (preg_match('/rtmp:/i', $url))     $type = 'RTMP';
        elseif (preg_match('/\.mpd/i', $url))     $type = 'Dash';
        elseif (preg_match('/youtube\.com|youtu\.be/i', $url)) $type = 'YouTube';

        // Find or create category
        $catId = 0;
        if ($catName !== '') {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $catName), '-'));
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :s LIMIT 1');
            $stmt->execute([':s' => $slug]);
            $row = $stmt->fetch();
            if ($row) {
                $catId = (int)$row['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (category_name, slug) VALUES (:n, :s)');
                $stmt->execute([':n' => $catName, ':s' => $slug]);
                $catId = (int)$pdo->lastInsertId();
            }
        } else {
            $stmt = $pdo->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1');
            $catId = (int)$stmt->fetchColumn();
            if (!$catId) {
                $pdo->exec("INSERT INTO categories (category_name, slug) VALUES ('General', 'general')");
                $catId = (int)$pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM channels WHERE url = :u');
        $stmt->execute([':u' => $url]);
        if ($stmt->fetchColumn() > 0) { $skipped[] = $name; continue; }

        $stmt = $pdo->prepare(
            'INSERT INTO channels (category_id, name, stream_type, url) VALUES (:cat, :name, :type, :url)'
        );
        $stmt->execute([':cat' => $catId, ':name' => $name, ':type' => $type, ':url' => $url]);
        $imported[] = $name;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['_csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $content = null;

        // File upload
        if (isset($_FILES['m3u_file']) && $_FILES['m3u_file']['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($_FILES['m3u_file']['tmp_name']);
        }
        // Paste input
        if ($content === null && !empty($_POST['m3u_text'])) {
            $content = $_POST['m3u_text'];
        }

        if ($content === null || trim($content) === '') {
            $error = 'No M3U data provided.';
        } elseif (!str_starts_with(trim($content), '#EXTM3U')) {
            $error = 'Invalid M3U — content must start with #EXTM3U.';
        } else {
            $imported = [];
            $skipped  = [];
            parseM3U($content, $pdo, $imported, $skipped);

            $logAction = isset($_FILES['m3u_file']) ? 'M3U Import' : 'M3U Import (paste)';
            logActivity($pdo, (int)$_SESSION['admin_id'], $logAction,
                'Imported: ' . count($imported) . ', Skipped: ' . count($skipped));

            $msg = count($imported) . ' channel(s) imported.';
            if (count($skipped) > 0) {
                $msg .= ' Skipped (duplicates): ' . implode(', ', array_slice($skipped, 0, 5));
                if (count($skipped) > 5) $msg .= '...';
            }
            $message = $msg;
        }
    }
}

$chCount = $pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn();
$catCount = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Import M3U Playlist</h4>
    <span class="text-secondary"><?= (int)$chCount ?> channels / <?= (int)$catCount ?> categories</span>
</div>

<?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card p-4">
            <h5 class="mb-3">Upload .m3u File</h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Select M3U File</label>
                    <input type="file" name="m3u_file" class="form-control" accept=".m3u,.txt,.m3u8" required>
                </div>
                <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Upload & Import</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-4">
            <h5 class="mb-3">Paste M3U Content</h5>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">M3U Data</label>
                    <textarea name="m3u_text" class="form-control" rows="8"
                        placeholder="#EXTM3U&#10;#EXTINF:-1 ,Channel Name&#10;http://stream.url/playlist.m3u8" required></textarea>
                </div>
                <button type="submit" class="btn-accent" style="width:auto;padding:.5rem 1.5rem">Parse & Import</button>
            </form>
        </div>
    </div>
</div>

<div class="mt-4">
    <div class="card p-4">
        <h5 class="mb-3">Supported M3U Format</h5>
        <pre style="background:var(--bg-dark);padding:1rem;border-radius:8px;color:var(--text-primary);font-size:.85rem;">#EXTM3U
#EXTINF:-1 ,Channel Name
http://stream.url/playlist.m3u8

# Or with category:
#EXTINF:-1 tvg-name="News HD" group-title="News",News HD
http://stream.url/playlist.m3u8</pre>
        <p class="text-secondary mt-2">YouTube URLs are auto-detected and resolved to playable streams.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
