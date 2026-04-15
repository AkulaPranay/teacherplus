<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$subscribers = $conn->query("
    SELECT id, username, email, full_name, phone, last_activity
    FROM users
    WHERE role = 'subscriber'
    ORDER BY last_activity DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribers - Admin</title>
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

        /* action icon buttons */
        .action-btn {
            font-size: 1.05rem;
            cursor: pointer;
            margin: 0 6px;
            text-decoration: none;
        }
        .action-btn:hover { opacity: 0.75; }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- Page heading — orange (via admin.css .main-content h2) -->
        <h2 class="mb-4">Subscribers</h2>

        <div class="card shadow-sm" style="border-radius:12px; overflow:hidden;">
            <!-- Card header — orange -->
            <div class="card-header" style="background:#f87407; color:#fff;">
                <h5 class="mb-0" style="color:#fff;">All Subscribers</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Last Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sub = $subscribers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub['full_name'] ?? $sub['username']); ?></td>
                                <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                <td><?php echo htmlspecialchars($sub['phone'] ?? '-'); ?></td>
                                <td><?php echo $sub['last_activity'] ? date('d M Y H:i', strtotime($sub['last_activity'])) : 'Never'; ?></td>
                                <td>
                                    <a href="subscriber-profile.php?id=<?php echo $sub['id']; ?>&mode=view"
                                       class="action-btn" style="color:#3d348b;" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="subscriber-profile.php?id=<?php echo $sub['id']; ?>&mode=edit"
                                       class="action-btn" style="color:#f87407;" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $sub['id']; ?>"
                                       class="action-btn text-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this subscriber?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>