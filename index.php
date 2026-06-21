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
<div class="container d-flex flex-column min-vh-100 py-3 py-md-4" style="max-width:1260px;gap:12px">

    <nav class="navbar navbar-expand-md navbar-dark mb-3 px-3 rounded-3" style="background:rgba(0,0,0,0.45);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.06)">
        <div class="container-fluid px-0">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php if ($brand['site_logo']): ?>
                    <img src="<?= htmlspecialchars($brand['site_logo']) ?>" alt="" width="32" height="32" class="rounded">
                <?php else: ?>
                    <i class="fas fa-tv" style="color:#ff5722;font-size:1.3rem"></i>
                <?php endif; ?>
                <span class="fw-bold"><?= htmlspecialchars($brand['site_name']) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" style="border-color:rgba(255,255,255,0.25)">
                <span class="navbar-toggler-icon" style="filter:brightness(0) invert(1)"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active px-2" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link px-2" href="/admin/login.php">Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="row g-2 flex-grow-1" style="min-height:0">
        <div class="col-lg-9 col-12 d-flex">
            <div class="bg-black rounded-3 shadow position-relative overflow-hidden w-100 d-flex align-items-center justify-content-center" style="background:radial-gradient(circle at center,#1e4d54,#0f2e33)">
                <div class="w-100 h-100 d-flex align-items-center justify-content-center" id="playerWrapper">
                    <div class="text-center" style="color:rgba(255,255,255,0.35)">
                        <div class="mb-2 opacity-50" style="font-size:4rem">▶</div>
                        <p class="small mb-0">Select a channel</p>
                    </div>
                </div>
                <div class="position-absolute top-0 start-0 end-0 d-flex align-items-center gap-2 px-3 py-2 rounded-top-3" style="background:linear-gradient(rgba(0,0,0,0.6),transparent);z-index:1;pointer-events:none">
                    <span class="badge fw-bold text-uppercase px-2 py-1" style="font-size:0.65rem;letter-spacing:1px;color:#ff4444;background:rgba(0,0,0,0.5);animation:pulse-live 1.8s ease-in-out infinite">LIVE</span>
                    <small class="text-white-50" id="viewerCount"><i class="fas fa-eye"></i> —</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-12 d-flex">
            <div class="bg-white rounded-3 shadow d-flex flex-column overflow-hidden w-100">
                <div class="d-flex justify-content-between align-items-center px-3 py-3 fw-semibold text-white" style="background:linear-gradient(135deg,#00bcd4,#0097a7)">
                    <span class="small">Channel List (<?= count($allChannels) ?>)</span>
                    <i class="fas fa-search" style="cursor:pointer;transition:transform 0.2s" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"></i>
                </div>
                <div class="d-flex flex-column overflow-auto flex-grow-1" id="channelList">
                    <?php foreach ($cats as $cat):
                        $catChs = $grouped[$cat['id']]['channels'] ?? [];
                        if (empty($catChs)) continue;
                    ?>
                    <div class="category-group">
                        <div class="category-header d-flex justify-content-between align-items-center px-3 py-2 small fw-semibold text-secondary border-bottom user-select-none" style="background:#f5f5f5;cursor:pointer">
                            <span><?= htmlspecialchars($cat['category_name']) ?> (<?= count($catChs) ?>)</span>
                            <span class="small" style="transition:transform 0.25s">▼</span>
                        </div>
                        <?php foreach ($catChs as $ch): ?>
                        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom channel-item"
                             style="cursor:pointer;font-size:0.85rem;color:#333;transition:all 0.2s;border-left:3px solid transparent"
                             data-id="<?= $ch['id'] ?>"
                             data-name="<?= htmlspecialchars($ch['name']) ?>"
                             data-type="<?= htmlspecialchars($ch['stream_type']) ?>"
                             data-logo="<?= htmlspecialchars($ch['logo'] ?? '') ?>">
                            <i class="fas fa-tv flex-shrink-0" style="color:#ff5722;font-size:1rem;width:18px"></i>
                            <span class="text-truncate"><?= htmlspecialchars($ch['name']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($noticeText !== ''): ?>
    <div class="text-center px-3 py-2 rounded-3 small fw-medium text-white" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,0.06)"><?= htmlspecialchars($noticeText) ?></div>
    <?php endif; ?>

    <div class="d-flex align-items-center gap-2 w-100">
        <button class="btn btn-outline-light rounded-circle flex-shrink-0 p-0 d-flex align-items-center justify-content-center left-arrow" style="width:30px;height:30px;border-width:1px;transition:all 0.25s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"><i class="fas fa-chevron-left small"></i></button>
        <div class="d-flex gap-1 overflow-auto w-100 py-1 carousel-row" id="bottomCarousel">
            <?php foreach ($allChannels as $ch): ?>
            <div class="flex-shrink-0 bg-white rounded-2 text-center px-2 py-1 shadow-sm channel-card"
                 style="min-width:100px;max-width:140px;font-size:0.78rem;font-weight:500;color:#333;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px;transition:all 0.2s;border:1px solid rgba(255,255,255,0.15);overflow:hidden"
                 data-id="<?= $ch['id'] ?>"
                 data-name="<?= htmlspecialchars($ch['name']) ?>"
                 data-type="<?= htmlspecialchars($ch['stream_type']) ?>"
                 data-logo="<?= htmlspecialchars($ch['logo'] ?? '') ?>">
                <i class="fas fa-tv" style="color:#ff5722;font-size:0.85rem"></i>
                <span class="text-truncate w-100"><?= htmlspecialchars($ch['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-outline-light rounded-circle flex-shrink-0 p-0 d-flex align-items-center justify-content-center right-arrow" style="width:30px;height:30px;border-width:1px;transition:all 0.25s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"><i class="fas fa-chevron-right small"></i></button>
    </div>

    <div class="text-center small py-2 text-white-50">
        &copy; 2026 <a href="https://hub.docker.com/u/alfurkan1" target="_blank" class="text-white-50 text-decoration-none">Al Furkan</a>. All rights reserved.
    </div>
</div>

<style>
body { background:radial-gradient(circle at center,#317873,#1e4d54) !important; min-height:100vh }
.channel-item:hover { background:#f5f5f5 !important; border-left-color:#00bcd4 !important; padding-left:18px !important }
.channel-item.active { background:linear-gradient(90deg,#e0f7fa,#fff) !important; border-left-color:#00bcd4 !important; font-weight:600 !important }
.channel-item.active i { color:#00bcd4 !important }
.channel-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.12) !important; transform:translateY(-1px) !important; background:rgba(255,255,255,0.98) !important }
.channel-card.active { background:#00bcd4 !important; color:#fff !important; border-color:#00bcd4 !important; box-shadow:0 3px 10px rgba(0,188,212,0.35) !important; transform:translateY(-1px) !important }
.channel-card.active i { color:#fff !important }
.carousel-row { scrollbar-width:none; scroll-behavior:smooth }
.carousel-row::-webkit-scrollbar { display:none }
.category-header.collapsed span:last-child { transform:rotate(-90deg) }
@keyframes pulse-live { 0%,100%{opacity:1} 50%{opacity:0.6} }
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
