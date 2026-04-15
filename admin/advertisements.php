<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Handle delete advertisement
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM advertisements WHERE id = $del_id");
    header("Location: advertisements.php?msg=deleted");
    exit;
}

// Fetch all advertisements
$ads = $conn->query("
    SELECT id, title, ad_type, position, image_path, link_url, status, impressions, clicks, created_at 
    FROM advertisements 
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advertisements - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    
    <style>
        body { background: #f4f6f9; margin: 0; }

        .main-content {
            margin-left: 260px;
            padding: 40px 30px;
            min-height: 100vh;
        }

        .table th, .table td { vertical-align: middle; }
        .ad-image { max-width: 80px; border-radius: 6px; }

        .action-btn {
            font-size: 1.05rem;
            cursor: pointer;
            margin: 0 6px;
            text-decoration: none;
        }
        .action-btn:hover { opacity: 0.75; }

        .pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <h2 class="mb-4">Advertisements</h2>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                Advertisement deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm" style="border-radius:12px; overflow:hidden;">
            <div class="card-header" style="background:#f87407; color:#fff;">
                <h5 class="mb-0" style="color:#fff;">All Advertisements</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ads->num_rows > 0): ?>
                            <?php while ($ad = $ads->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($ad['image_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($ad['image_path']); ?>" 
                                             class="ad-image" alt="Ad">
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($ad['title']); ?></strong></td>
                                <td><?php echo strtoupper($ad['ad_type']); ?></td>
                                <td><?php echo htmlspecialchars($ad['position']); ?></td>
                                <td>
                                    <span class="pill <?php echo $ad['status'] === 'active' ? 'bg-success text-white' : 'bg-secondary text-white'; ?>">
                                        <?php echo ucfirst($ad['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($ad['impressions'] ?? 0); ?></td>
                                <td><?php echo number_format($ad['clicks'] ?? 0); ?></td>
                                <td>
                                    <a href="edit-advertisement.php?id=<?php echo $ad['id']; ?>" 
                                       class="action-btn" style="color:#f87407;" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $ad['id']; ?>" 
                                       class="action-btn text-danger" title="Delete"
                                       onclick="return confirm('Delete this advertisement?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No advertisements yet. <a href="add-advertisement.php" style="color:#f87407;">Add new advertisement</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 text-end">
            <a href="add-advertisement.php" class="btn btn-orange">
                <i class="fas fa-plus me-2"></i> Add New Advertisement
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>