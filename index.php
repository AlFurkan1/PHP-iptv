<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/brand.php';

$cats = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM channels WHERE category_id = c.id AND status = 'active') AS ch_count
     FROM categories c ORDER BY c.sort_order ASC, c.category_name ASC"
)->fetchAll();

$allChannels = $pdo->query(
    "SELECT ch.*, cat.category_name FROM channels ch
     JOIN categories cat ON cat.id = ch.category_id
     WHERE ch.status = 'active'
     ORDER BY cat.sort_order ASC, ch.sort_order ASC, ch.name ASC"
)->fetchAll();

$grouped = [];
foreach ($allChannels as $ch) {
    $grouped[$ch['category_id']]['name'] = $ch['category_name'];
    $grouped[$ch['category_id']]['channels'][] = $ch;
}

$noticeText = '';
$noticeFile = __DIR__ . '/cache/notice.txt';
if (is_file($noticeFile)) {
    $noticeText = trim(file_get_contents($noticeFile));
}

require __DIR__ . '/includes/header.php';
?>
<div class="app-container">

    <nav class="navbar navbar-expand-md navbar-dark top-navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($brand['site_logo']): ?>
                    <img src="<?= htmlspecialchars($brand['site_logo']) ?>" alt="" width="32" height="32" class="rounded">
                <?php else: ?>
                    <i class="fas fa-tv" style="color:#ff5722;font-size:1.3rem"></i>
                <?php endif; ?>
                <span class="fw-bold"><?= htmlspecialchars($brand['site_name']) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/login.php">Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="video-container">
            <div class="player-wrapper" id="playerWrapper">
                <div class="placeholder">
                    <div class="icon">▶</div>
                    <p>Select a channel</p>
                </div>
            </div>

            <div class="video-controls">
                <div class="left-controls">
                    <button class="control-btn" id="playBtn"><i class="fas fa-pause"></i></button>
                    <button class="control-btn" id="muteBtn"><i class="fas fa-volume-up"></i></button>
                    <span class="live-badge">LIVE</span>
                    <span id="viewerCount">— viewers</span>
                </div>
                <div class="right-controls">
                    <button class="control-btn" id="fullscreenBtn"><i class="fas fa-expand"></i></button>
                </div>
            </div>
        </div>

        <div class="sidebar">
            <div class="sidebar-header">
                <span>Channel List (<?= count($allChannels) ?>)</span>
                <i class="fas fa-search search-icon"></i>
            </div>
            <div class="channel-list" id="channelList">
                <?php foreach ($cats as $cat):
                    $catChs = $grouped[$cat['id']]['channels'] ?? [];
                    if (empty($catChs)) continue;
                ?>
                <div class="category-group">
                    <div class="category-header">
                        <span><?= htmlspecialchars($cat['category_name']) ?> (<?= count($catChs) ?>)</span>
                        <span class="arrow">▼</span>
                    </div>
                    <?php foreach ($catChs as $ch): ?>
                    <div class="channel-item"
                         data-id="<?= $ch['id'] ?>"
                         data-name="<?= htmlspecialchars($ch['name']) ?>"
                         data-type="<?= htmlspecialchars($ch['stream_type']) ?>"
                         data-logo="<?= htmlspecialchars($ch['logo'] ?? '') ?>">
                        <i class="fas fa-tv tv-icon"></i>
                        <span class="ch-name"><?= htmlspecialchars($ch['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($noticeText !== ''): ?>
    <div class="notice-bar"><?= htmlspecialchars($noticeText) ?></div>
    <?php endif; ?>

    <div class="bottom-carousel-wrapper">
        <button class="nav-arrow left-arrow"><i class="fas fa-chevron-left"></i></button>
        <div class="bottom-carousel" id="bottomCarousel">
            <?php foreach ($allChannels as $ch): ?>
            <div class="carousel-item"
                 data-id="<?= $ch['id'] ?>"
                 data-name="<?= htmlspecialchars($ch['name']) ?>"
                 data-type="<?= htmlspecialchars($ch['stream_type']) ?>"
                 data-logo="<?= htmlspecialchars($ch['logo'] ?? '') ?>">
                <i class="fas fa-tv"></i>
                <?= htmlspecialchars($ch['name']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="nav-arrow right-arrow"><i class="fas fa-chevron-right"></i></button>
    </div>

    <div class="copyright">
        &copy; 2026 <a href="https://hub.docker.com/u/alfurkan1" target="_blank">Al Furkan</a>. All rights reserved.
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
