<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$action_msg  = '';
$action_type = 'success';

// ── ADD ──────────────────────────────────────────────────────────────────────
if (isset($_POST['add_category'])) {
    $name   = trim($_POST['cat_name']);
    $url    = trim($_POST['cat_url']);
    $parent = (int)$_POST['cat_parent'];

    if ($name !== '' && $url !== '') {
        $parent_val = $parent > 0 ? $parent : null;
        $stmt = $conn->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $url, $parent_val);
        $stmt->execute();
        $stmt->close();
        header("Location: categories.php?msg=" . urlencode("Category \"$name\" added successfully.") . "&type=success");
        exit;
    } else {
        $action_msg  = "Name and URL are required.";
        $action_type = 'danger';
    }
}

// ── EDIT ─────────────────────────────────────────────────────────────────────
if (isset($_POST['edit_category'])) {
    $id     = (int)$_POST['cat_id'];
    $name   = trim($_POST['cat_name']);
    $url    = trim($_POST['cat_url']);
    $parent = (int)$_POST['cat_parent'];

    if ($id && $name !== '' && $url !== '') {
        $parent_val = $parent > 0 ? $parent : null;
        $stmt = $conn->prepare("UPDATE categories SET name=?, slug=?, parent_id=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $url, $parent_val, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: categories.php?msg=" . urlencode("Category updated successfully.") . "&type=success");
        exit;
    } else {
        $action_msg  = "Name and URL are required.";
        $action_type = 'danger';
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if (isset($_POST['delete_category'])) {
    $id = (int)$_POST['cat_id'];
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: categories.php?msg=" . urlencode("Category deleted.") . "&type=success");
        exit;
    }
}

// ── REDIRECT MSG ─────────────────────────────────────────────────────────────
if (!$action_msg && isset($_GET['msg'])) {
    $action_msg  = htmlspecialchars($_GET['msg']);
    $action_type = $_GET['type'] ?? 'success';
}

// ── EDIT MODE ─────────────────────────────────────────────────────────────────
$edit_cat = null;
if (isset($_GET['edit'])) {
    $edit_id  = (int)$_GET['edit'];
    $edit_cat = $conn->query("SELECT * FROM categories WHERE id=$edit_id")->fetch_assoc();
}

// ── FETCH CATEGORIES ──────────────────────────────────────────────────────────
$search = trim($_GET['cat_search'] ?? '');
$page   = max(1, (int)($_GET['cat_page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

if ($search !== '') {
    $like  = '%' . $conn->real_escape_string($search) . '%';
    $total = $conn->query("SELECT COUNT(*) FROM categories WHERE name LIKE '$like'")->fetch_row()[0];
    $res   = $conn->query("SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id WHERE c.name LIKE '$like' ORDER BY c.name ASC LIMIT $limit OFFSET $offset");
} else {
    $total = $conn->query("SELECT COUNT(*) FROM categories")->fetch_row()[0];
    $res   = $conn->query("SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY c.name ASC LIMIT $limit OFFSET $offset");
}

$categories = [];
while ($row = $res->fetch_assoc()) $categories[] = $row;
$total_pages = ceil($total / $limit);

// All categories for parent dropdown
$all_cats_res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$all_categories = [];
while ($r = $all_cats_res->fetch_assoc()) $all_categories[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - TeacherPlus Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body { background: #f5f7fa; margin: 0; }

        .main-content {
            margin-left: 260px;
            padding: 40px 30px;
            min-height: 100vh;
        }

        /* Page banner — matches dashboard orange gradient */
        .page-banner {
            background: linear-gradient(135deg, #f87407, #e06200);
            color: #fff;
            border-radius: 16px;
            padding: 32px 40px;
            margin-bottom: 32px;
        }
        .page-banner h2 { color: #fff; font-size: 1.6rem; margin-bottom: 4px; }
        .page-banner p  { color: rgba(255,255,255,.88); margin: 0; }

        /* Cards */
        .tp-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0,0,0,.06);
            overflow: hidden;
            height: 100%;
        }
        .tp-card-header {
            background: #1d2327;
            color: #fff;
            padding: 14px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tp-card-header h5 { margin: 0; font-size: 1rem; font-weight: 600; }
        .tp-badge {
            background: #f87407;
            color: #fff;
            border-radius: 20px;
            padding: 2px 12px;
            font-size: .78rem;
            font-weight: 600;
        }
        .tp-card-body { padding: 22px; }

        /* Table */
        .tp-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .tp-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 11px 14px;
            border-bottom: 2px solid #e9ecef;
            text-align: left;
        }
        .tp-table td {
            padding: 11px 14px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            vertical-align: middle;
        }
        .tp-table tr:last-child td { border-bottom: none; }
        .tp-table tr:hover td { background: #fff8f2; }

        .slug-badge {
            background: #f0f0f0;
            color: #555;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: .78rem;
            font-family: monospace;
        }
        .parent-badge {
            background: #e8f4fd;
            color: #2271b1;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: .78rem;
        }

        /* Buttons */
        .btn-edit {
            background: #2271b1; color: #fff;
            border: none; border-radius: 6px;
            padding: 5px 12px; font-size: .8rem;
            text-decoration: none; display: inline-block;
            transition: background .2s;
        }
        .btn-edit:hover { background: #135e96; color: #fff; }

        .btn-del {
            background: #dc3545; color: #fff;
            border: none; border-radius: 6px;
            padding: 5px 12px; font-size: .8rem;
            cursor: pointer; transition: background .2s;
        }
        .btn-del:hover { background: #b02a37; }

        /* Form */
        .tp-form-label { font-size: .85rem; font-weight: 600; color: #495057; margin-bottom: 4px; display: block; }
        .tp-input {
            width: 100%; border: 1px solid #ced4da; border-radius: 8px;
            padding: 8px 12px; font-size: .9rem; outline: none;
            transition: border-color .2s; box-sizing: border-box;
        }
        .tp-input:focus { border-color: #f87407; box-shadow: 0 0 0 3px rgba(248,116,7,.12); }

        .btn-orange {
            background: #f87407; color: #fff;
            border: none; border-radius: 8px;
            padding: 9px 22px; font-size: .9rem;
            font-weight: 600; cursor: pointer; transition: background .2s;
        }
        .btn-orange:hover { background: #e06200; }

        .btn-cancel {
            background: #6c757d; color: #fff;
            border: none; border-radius: 8px;
            padding: 9px 18px; font-size: .9rem;
            cursor: pointer; text-decoration: none;
            display: inline-block; transition: background .2s;
        }
        .btn-cancel:hover { background: #5a6268; color: #fff; }

        /* Pagination */
        .tp-pager { display: flex; gap: 4px; flex-wrap: wrap; }
        .tp-pager a, .tp-pager span {
            border: 1px solid #dee2e6; border-radius: 6px;
            padding: 4px 10px; font-size: .82rem;
            text-decoration: none; color: #2271b1;
        }
        .tp-pager span.active { background: #f87407; border-color: #f87407; color: #fff; }
        .tp-pager a:hover { background: #fff3e8; border-color: #f87407; }

        .tp-empty { text-align: center; padding: 50px 20px; color: #aaa; }
        .tp-empty i { font-size: 2.5rem; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="main-content">

    <!-- Banner -->
    <div class="page-banner">
        <h2><i class="fas fa-tags me-2"></i> Categories</h2>
        <p>Manage all content categories for TeacherPlus.</p>
    </div>

    <!-- Flash message -->
    <?php if ($action_msg): ?>
        <div class="alert alert-<?= $action_type === 'danger' ? 'danger' : 'success' ?> alert-dismissible fade show rounded-3 mb-4" role="alert">
            <?= $action_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">

        <!-- ── LEFT: Add / Edit Form ──────────────────────────────────────── -->
        <div class="col-lg-4">
            <div class="tp-card">
                <div class="tp-card-header">
                    <h5>
                        <?php if ($edit_cat): ?>
                            <i class="fas fa-pen me-2"></i>Edit Category
                        <?php else: ?>
                            <i class="fas fa-plus me-2"></i>Add Category
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="tp-card-body">
                    <form method="post">
                        <?php if ($edit_cat): ?>
                            <input type="hidden" name="cat_id" value="<?= $edit_cat['id'] ?>">
                        <?php endif; ?>

                        <!-- Name -->
                        <div class="mb-3">
                            <label class="tp-form-label">
                                Category Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="cat_name" class="tp-input"
                                   placeholder="e.g. Science"
                                   value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>" required>
                        </div>

                        <!-- URL Slug -->
                        <div class="mb-3">
                            <label class="tp-form-label">
                                URL <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="cat_url" id="cat_url" class="tp-input"
                                   placeholder="e.g. science"
                                   value="<?= htmlspecialchars($edit_cat['slug'] ?? '') ?>" required>
                            <small class="text-muted" style="font-size:.78rem;">Lowercase, hyphens only — used in page URLs.</small>
                        </div>

                        <!-- Parent Category -->
                        <div class="mb-4">
                            <label class="tp-form-label">Parent Category</label>
                            <select name="cat_parent" class="tp-input">
                                <option value="0">— None (Top Level) —</option>
                                <?php foreach ($all_categories as $ac):
                                    if ($edit_cat && $ac['id'] == $edit_cat['id']) continue;
                                    $sel = ($edit_cat && $edit_cat['parent_id'] == $ac['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $ac['id'] ?>" <?= $sel ?>><?= htmlspecialchars($ac['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="<?= $edit_cat ? 'edit_category' : 'add_category' ?>" class="btn-orange">
                                <i class="fas <?= $edit_cat ? 'fa-save' : 'fa-plus' ?> me-1"></i>
                                <?= $edit_cat ? 'Save Changes' : 'Add Category' ?>
                            </button>
                            <?php if ($edit_cat): ?>
                                <a href="categories.php" class="btn-cancel">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- ── RIGHT: Category Table ──────────────────────────────────────── -->
        <div class="col-lg-8">
            <div class="tp-card">
                <div class="tp-card-header">
                    <h5><i class="fas fa-list me-2"></i>All Categories</h5>
                    <span class="tp-badge"><?= number_format($total) ?> total</span>
                </div>
                <div class="tp-card-body" style="padding:0;">

                    <!-- Search bar -->
                    <div style="padding:14px 20px;border-bottom:1px solid #f0f0f0;background:#fafafa;">
                        <form method="get" style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="cat_search" class="tp-input" style="max-width:280px;"
                                   placeholder="Search categories…" value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn-orange" style="padding:8px 16px;white-space:nowrap;">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                            <?php if ($search): ?>
                                <a href="categories.php" class="btn-cancel" style="padding:8px 14px;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Table -->
                    <?php if (empty($categories)): ?>
                        <div class="tp-empty">
                            <i class="fas fa-tags"></i>
                            No categories found.
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                        <table class="tp-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>URL</th>
                                    <th>Parent</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categories as $i => $cat): ?>
                                <tr>
                                    <td style="color:#bbb;font-size:.8rem;"><?= $offset + $i + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                    <td><span class="slug-badge"><?= htmlspecialchars($cat['slug'] ?? '') ?></span></td>
                                    <td>
                                        <?php if (!empty($cat['parent_name'])): ?>
                                            <span class="parent-badge"><?= htmlspecialchars($cat['parent_name']) ?></span>
                                        <?php else: ?>
                                            <span style="color:#ccc;font-size:.8rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="categories.php?edit=<?= $cat['id'] ?><?= $search ? '&cat_search='.urlencode($search) : '' ?>"
                                           class="btn-edit">
                                            <i class="fas fa-pen"></i> Edit
                                        </a>
                                        <form method="post" style="display:inline;"
                                              onsubmit="return confirm('Delete &quot;<?= addslashes(htmlspecialchars($cat['name'])) ?>&quot;?\n\nThis cannot be undone.')">
                                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" name="delete_category" class="btn-del ms-1">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div style="padding:14px 20px;border-top:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;font-size:.85rem;color:#666;">
                            <span>Page <?= $page ?> of <?= $total_pages ?></span>
                            <div class="tp-pager">
                                <?php for ($p = 1; $p <= $total_pages; $p++):
                                    $purl = '?cat_page=' . $p . ($search ? '&cat_search='.urlencode($search) : '');
                                ?>
                                    <?php if ($p === $page): ?>
                                        <span class="active"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="<?= $purl ?>"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div><!-- /.row -->

</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-generate URL slug from name (add mode only)
<?php if (!$edit_cat): ?>
const nameInput = document.querySelector('input[name="cat_name"]');
const urlInput  = document.getElementById('cat_url');
let urlTouched  = false;

urlInput.addEventListener('input', () => { urlTouched = true; });
nameInput.addEventListener('input', () => {
    if (!urlTouched) {
        urlInput.value = nameInput.value
            .toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-');
    }
});
<?php endif; ?>
</script>
</body>
</html>