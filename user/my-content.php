<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    header("Location: ../public/login.php");
    exit;
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Only allow staff to delete their own articles
    if ($_SESSION['role'] === 'staff') {
        $conn->query("DELETE FROM articles WHERE id=$del_id AND user_id={$_SESSION['user_id']}");
    } else {
        $conn->query("DELETE FROM articles WHERE id=$del_id");
    }
    header("Location: my-content.php?msg=deleted");
    exit;
}

// Fetch articles — staff sees only their own, admin sees all
if ($_SESSION['role'] === 'staff') {
    $stmt = $conn->prepare("
        SELECT id, title, category, status, created_at
        FROM articles
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("
        SELECT id, title, category, status, created_at
        FROM articles
        ORDER BY created_at DESC
    ");
}
$stmt->execute();
$result = $stmt->get_result();

// Also fetch worksheets
if ($_SESSION['role'] === 'staff') {
    $ws_stmt = $conn->prepare("
        SELECT id, title, subject, status, created_at
        FROM worksheets
        WHERE author_name = ?
        ORDER BY created_at DESC
    ");
    $ws_stmt->bind_param("s", $_SESSION['username']);
} else {
    $ws_stmt = $conn->prepare("
        SELECT id, title, subject, status, created_at
        FROM worksheets
        ORDER BY created_at DESC
    ");
}
$ws_stmt->execute();
$worksheets = $ws_stmt->get_result();

// Also fetch e-magazines
if ($_SESSION['role'] === 'staff') {
    $mag_stmt = $conn->prepare("
        SELECT id, title, issue_year, status, created_at
        FROM e_magazines
        WHERE author_name = ?
        ORDER BY created_at DESC
    ");
    $mag_stmt->bind_param("s", $_SESSION['username']);
} else {
    $mag_stmt = $conn->prepare("
        SELECT id, title, issue_year, status, created_at
        FROM e_magazines
        ORDER BY created_at DESC
    ");
}
$mag_stmt->execute();
$magazines = $mag_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Content — TeacherPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .main-wrap { max-width: 1100px; margin-left: 250px;padding: 30px; }
        .page-title { font-size: 1.3rem; font-weight: 700; color: #1f2a4a; margin-bottom: 6px; }
        .page-sub { font-size: 13px; color: #aaa; margin-bottom: 28px; }

        /* Tabs */
        .content-tabs { display: flex; gap: 4px; margin-bottom: 22px; }
        .ctab {
            padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: 1.5px solid #e0e3ec; background: #fff; color: #888;
            transition: all 0.15s;
        }
        .ctab.active { background: #1f2a4a; color: #fff; border-color: #1f2a4a; }
        .ctab:hover:not(.active) { border-color: #f87407; color: #f87407; }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Table card */
        .content-card {
            background: #fff; border-radius: 12px;
            border: 1px solid #eef0f6;
            box-shadow: 0 1px 6px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .content-card table { width: 100%; border-collapse: collapse; }
        .content-card thead th {
            padding: 11px 18px; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.06em; color: #9aa0b4;
            background: #f8f9fc; border-bottom: 2px solid #eef0f6; text-align: left;
        }
        .content-card tbody td {
            padding: 13px 18px; border-bottom: 1px solid #f5f6fa;
            font-size: 13.5px; color: #53585c; vertical-align: middle;
        }
        .content-card tbody tr:last-child td { border-bottom: none; }
        .content-card tbody tr:hover { background: #fafbff; }

        .pill {
            display: inline-block; padding: 3px 10px; border-radius: 20px;
            font-size: 11px; font-weight: 600; text-transform: uppercase;
        }
        .pill-published { background: #e8f5e9; color: #2e7d32; }
        .pill-draft     { background: #fff3cd; color: #856404; }
        .pill-scheduled { background: rgba(61,52,139,0.1); color: #3d348b; }
        .pill-archived  { background: #f0f2f5; color: #9aa0b4; }

        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 7px; font-size: 13px;
            text-decoration: none; border: none; background: transparent; cursor: pointer;
            transition: all 0.15s;
        }
        .btn-edit   { color: #f87407; background: rgba(248,116,7,0.08); }
        .btn-delete { color: #dc3545; background: rgba(220,53,69,0.08); }
        .action-btn:hover { transform: scale(1.12); }

        .empty-state { padding: 52px 24px; text-align: center; color: #c5c8d0; }
        .empty-state i { font-size: 2.2rem; display: block; margin-bottom: 10px; color: #e0e3ec; }
        .empty-state p { font-size: 13.5px; }

        .alert-tp { padding: 10px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
        .alert-ok  { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

        .add-btn {
            background: #f87407; color: #fff; border: none; padding: 9px 20px;
            border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: background 0.2s;
        }
        .add-btn:hover { background: #d96400; color: #fff; }
    </style>
    <!-- ✅ Admin CSS -->
<link rel="stylesheet" href="../admin/assets/css/admin.css">

<!-- ✅ Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ✅ ICON FIX -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'staff-sidebar.php'; ?>

<div class="main-wrap">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px">
        <div>
            <div class="page-title">My Content</div>
            <div class="page-sub">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="add-article.php"    class="add-btn"><i class="fas fa-plus"></i> Article</a>
            <a href="add-worksheet.php"  class="add-btn" style="background:#3d348b"><i class="fas fa-plus"></i> Worksheet</a>
            <a href="add-e-magazine.php" class="add-btn" style="background:#1f2a4a"><i class="fas fa-plus"></i> E-Magazine</a>
            <a href="dashboard.php"      class="add-btn" style="background:#6c757d"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert-tp alert-ok"><i class="fas fa-check-circle me-2"></i>Content deleted successfully.</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="content-tabs">
        <div class="ctab active" onclick="switchTab('articles',this)">
            <i class="fas fa-newspaper me-1"></i> Articles
            <span style="background:#f87407;color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;margin-left:4px">
                <?php echo $result->num_rows; ?>
            </span>
        </div>
        <div class="ctab" onclick="switchTab('worksheets',this)">
            <i class="fas fa-file-alt me-1"></i> Worksheets
            <span style="background:#3d348b;color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;margin-left:4px">
                <?php echo $worksheets->num_rows; ?>
            </span>
        </div>
        <div class="ctab" onclick="switchTab('magazines',this)">
            <i class="fas fa-book-open me-1"></i> E-Magazines
            <span style="background:#1f2a4a;color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;margin-left:4px">
                <?php echo $magazines->num_rows; ?>
            </span>
        </div>
    </div>

    <!-- Articles tab -->
    <div class="tab-pane active" id="tab-articles">
        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:#1f2a4a;max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </div>
                            </td>
                            <td style="color:#888;font-size:12.5px"><?php echo htmlspecialchars($row['category'] ?: '—'); ?></td>
                            <td>
                                <span class="pill pill-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="color:#aaa;font-size:12.5px"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display:flex;gap:5px">
                                    <a href="edit-content.php?id=<?php echo $row['id']; ?>" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>"
                                       class="action-btn btn-delete"
                                       title="Delete"
                                       onclick="return confirm('Delete this article?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-newspaper"></i>
                                <p>No articles yet. <a href="add-article.php" style="color:#f87407">Add your first article</a></p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Worksheets tab -->
    <div class="tab-pane" id="tab-worksheets">
        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($worksheets->num_rows > 0): ?>
                        <?php while ($row = $worksheets->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:#1f2a4a;max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </div>
                            </td>
                            <td style="color:#888;font-size:12.5px"><?php echo htmlspecialchars($row['subject'] ?: '—'); ?></td>
                            <td>
                                <span class="pill pill-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="color:#aaa;font-size:12.5px"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display:flex;gap:5px">
                                    <a href="#" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Delete this worksheet?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-file-alt"></i>
                                <p>No worksheets yet. <a href="add-worksheet.php" style="color:#3d348b">Add your first worksheet</a></p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- E-Magazines tab -->
    <div class="tab-pane" id="tab-magazines">
        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($magazines->num_rows > 0): ?>
                        <?php while ($row = $magazines->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:#1f2a4a;max-width:360px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </div>
                            </td>
                            <td style="color:#888;font-size:12.5px"><?php echo $row['issue_year']; ?></td>
                            <td>
                                <span class="pill pill-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td style="color:#aaa;font-size:12.5px"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display:flex;gap:5px">
                                    <a href="#" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="action-btn btn-delete" title="Delete"
                                       onclick="return confirm('Delete this e-magazine?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <p>No e-magazines yet. <a href="add-e-magazine.php" style="color:#1f2a4a">Add your first e-magazine</a></p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ctab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>