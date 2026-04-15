<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$admins      = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
$staff       = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TeacherPlus</title>
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

        /* Welcome banner — orange gradient */
        .welcome-card {
            background: linear-gradient(135deg, #f87407, #e06200);
            color: #fff;
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 40px;
        }
        .welcome-card h2 {
            color: #fff;
            font-size: 1.6rem;
            margin-bottom: 6px;
        }
        .welcome-card p {
            color: rgba(255,255,255,0.88);
            margin: 0;
        }

        /* Stat cards */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-8px); }

        .stat-card h5 {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        /* Stat numbers — orange */
        .stat-card h3 {
            font-size: 2.8rem;
            font-weight: 700;
            color: #f87407;
            margin: 0;
        }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">

        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</h2>
            <p>Here's a quick overview of your platform.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <h5>Total Users</h5>
                    <h3><?php echo number_format($total_users); ?></h3>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <h5>Admins</h5>
                    <h3><?php echo number_format($admins); ?></h3>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="stat-card">
                    <h5>Staff Members</h5>
                    <h3><?php echo number_format($staff); ?></h3>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>