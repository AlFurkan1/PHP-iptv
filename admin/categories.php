<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Categories';
$message   = '';
$error     = '';

// ── Handle POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfOk = csrfVerify($_POST['_csrf'] ?? '');

    if (!$csrfOk) {
        $error = 'Invalid CSRF token.';
    } elseif ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $slug === '') {
            $error = 'Name and slug are required.';
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    'INSERT INTO categories (category_name, slug, sort_order) VALUES (:name, :slug, :sort)'
                );
                $stmt->execute([':name' => $name, ':slug' => $slug, ':sort' => $sortOrder]);
                $message = 'Category created.';
                logActivity($pdo, (int)$_SESSION['admin_id'], 'Category Created', "Category: $name");
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($id) {
                    $stmt = $pdo->prepare(
                        'UPDATE categories SET category_name = :name, slug = :slug, sort_order = :sort WHERE id = :id'
                    );
                    $stmt->execute([':name' => $name, ':slug' => $slug, ':sort' => $sortOrder, ':id' => $id]);
                    $message = 'Category updated.';
                    logActivity($pdo, (int)$_SESSION['admin_id'], 'Category Updated', "Category: $name");
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
        $message = 'Category deleted.';
        logActivity($pdo, (int)$_SESSION['admin_id'], 'Category Deleted', "Deleted category #$id");
    }
}

// ── Fetch ───────────────────────────────────────────────────────────────────
$categories = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM channels WHERE category_id = c.id) AS ch_count
     FROM categories c ORDER BY c.sort_order ASC, c.category_name ASC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="admin-header">
    <h4>Categories</h4>
    <button class="btn-accent" style="width:auto;padding:.5rem 1.2rem" data-bs-toggle="modal" data-bs-target="#catModal"
            onclick="resetCatForm()">+ Add Category</button>
</div>

<?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Channels</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td><?= htmlspecialchars($cat['category_name']) ?></td>
                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                <td><?= (int)$cat['ch_count'] ?></td>
                <td><?= (int)$cat['sort_order'] ?></td>
                <td>
                    <button class="btn-sm-icon" title="Edit"
                            onclick='editCat(<?= json_encode($cat) ?>)'
                            data-bs-toggle="modal" data-bs-target="#catModal">✏️</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this category? All channels in it will also be deleted.')">
                        <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="btn-sm-icon danger" title="Delete">🗑</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" id="cat-action" value="create">
            <input type="hidden" name="id" id="cat-id" value="0">
            <div class="modal-header">
                <h5 class="modal-title" id="cat-modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="name" id="cat-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" id="cat-slug" class="form-control" required placeholder="e.g. live-sports">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="cat-sort" class="form-control" value="0" min="0">
                    <small class="form-text">Lower numbers appear first.</small>
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
function resetCatForm() {
    document.getElementById('cat-action').value = 'create';
    document.getElementById('cat-id').value = '0';
    document.getElementById('cat-modal-title').textContent = 'Add Category';
    document.getElementById('cat-name').value = '';
    document.getElementById('cat-slug').value = '';
    document.getElementById('cat-sort').value = '0';
}
function editCat(cat) {
    document.getElementById('cat-action').value = 'update';
    document.getElementById('cat-id').value = cat.id;
    document.getElementById('cat-modal-title').textContent = 'Edit Category';
    document.getElementById('cat-name').value = cat.category_name;
    document.getElementById('cat-slug').value = cat.slug;
    document.getElementById('cat-sort').value = cat.sort_order || 0;
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
