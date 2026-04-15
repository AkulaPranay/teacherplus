<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Selected user (if any)
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch all users for left panel
$users = $conn->query("
    SELECT id, username, full_name, email, role
    FROM users
    ORDER BY role, username
");

// Fetch activities
if ($selected_user_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.action, a.details, a.created_at, u.username
        FROM activity_log a
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $activities = $stmt->get_result();
    $stmt->close();
} else {
    $activities = $conn->query("
        SELECT a.id, a.action, a.details, a.created_at, u.username
        FROM activity_log a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 100
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Log - TeacherPlus Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .sidebar { width: 260px; }
        .main-content { margin-left: 260px; padding: 40px 30px; min-height: 100vh; }
        .user-list { max-height: 70vh; overflow-y: auto; }
        .activity-date { font-weight: bold; color: #0d6efd; margin: 20px 0 10px; }
        .activity-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .activity-time { color: #6c757d; font-size: 0.9rem; }
    </style>
            <link rel="stylesheet" href="assets/css/admin.css">

</head>
<body>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Activity Log</h2>
            <div>
                <a href="users-activity.php" class="btn btn-outline-primary me-2">View All</a>
                <a href="users-activity.php" class="btn btn-outline-secondary me-2"><i class="fas fa-sync-alt"></i> Refresh</a>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <div class="row">
            <!-- Left: Users List -->
            <div class="col-lg-3">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Users</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="user-list">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item <?php echo $selected_user_id === 0 ? 'active bg-primary text-white' : ''; ?>">
                                    <a href="users-activity.php" class="text-decoration-none d-block">
                                        <strong>All Users</strong>
                                    </a>
                                </li>
                                <?php while ($u = $users->fetch_assoc()): ?>
                                    <li class="list-group-item <?php echo $selected_user_id === $u['id'] ? 'active bg-primary text-white' : ''; ?>">
                                        <a href="?user_id=<?php echo $u['id']; ?>" class="text-decoration-none d-block">
                                            <?php echo htmlspecialchars($u['full_name'] ?? $u['username']); ?>
                                            <small class="d-block <?php echo $selected_user_id === $u['id'] ? 'text-white-50' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($u['email']); ?>
                                            </small>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Activities -->
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php 
                            if ($selected_user_id > 0) {
                                $sel_user = $conn->query("SELECT username, full_name FROM users WHERE id = $selected_user_id")->fetch_assoc();
                                echo htmlspecialchars($sel_user['full_name'] ?? $sel_user['username']) . "'s Activity";
                            } else {
                                echo "All Recent Activities";
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($activities->num_rows > 0): ?>
                            <?php
                            $current_date = '';
                            while ($act = $activities->fetch_assoc()):
                                $date = date('d M Y', strtotime($act['created_at']));
                                if ($date !== $current_date):
                                    if ($current_date !== '') echo '</div>';
                                    $current_date = $date;
                                    echo '<div class="activity-date">' . $date . '</div>';
                                    echo '<div class="activity-list">';
                                endif;
                            ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($act['username']); ?></strong>
                                            <span class="ms-2 badge bg-info"><?php echo htmlspecialchars($act['action']); ?></span>
                                            <p class="mb-1 mt-1 text-muted"><?php echo htmlspecialchars($act['details'] ?? 'No details'); ?></p>
                                        </div>
                                        <div class="activity-time text-end">
                                            <?php echo date('H:i', strtotime($act['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted py-5">No activity recorded yet for this user.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>