<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    header("Location: ../public/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - TeacherPlus</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../admin/assets/css/admin.css">   <!-- Using admin.css like admin pages -->

    <style>
        body { 
            background: #f4f6f9; 
            margin: 0; 
        }

        .main-content {
            margin-left: 260px;
            padding: 40px 30px;
            min-height: 100vh;
        }

        /* Welcome banner */
        .welcome-card {
            background: linear-gradient(135deg, #f87407, #e06200);
            color: #fff;
            border-radius: 16px;
            padding: 42px;
            margin-bottom: 40px;
        }
        .welcome-card h2 {
            font-size: 1.85rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        /* Action cards */
        .action-card {
            background: #fff;
            border-radius: 16px;
            padding: 34px 26px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
            height: 100%;
            transition: all 0.3s;
        }
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .action-card i {
            font-size: 3.4rem;
            color: #f87407;
            margin-bottom: 20px;
        }

        .btn-orange {
            background: #f87407;
            color: white;
            border: none;
            padding: 12px 28px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
        }
        .btn-orange:hover { 
            background: #e06200; 
        }
    </style>
</head>
<body>

    <?php include 'staff-sidebar.php'; ?>

    <div class="main-content">

        <div class="welcome-card">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Staff Member'); ?>!</h2>
            <p>What would you like to contribute today?</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <i class="fas fa-newspaper"></i>
                    <h5>Add New Article</h5>
                    <p>Regular articles for the magazine or website</p>
                    <a href="add-article.php" class="btn btn-orange mt-3">Add Article</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <i class="fas fa-file-alt"></i>
                    <h5>Add New Worksheet</h5>
                    <p>Practice sheets and classroom activities</p>
                    <a href="add-worksheet.php" class="btn btn-orange mt-3">Add Worksheet</a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <i class="fas fa-book-open"></i>
                    <h5>Add New E-Magazine</h5>
                    <p>Digital magazine issues or PDFs</p>
                    <a href="add-e-magazine.php" class="btn btn-orange mt-3">Add E-Magazine</a>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <a href="my-content.php" class="btn btn-outline-primary btn-lg px-5 py-3">
                <i class="fas fa-list me-2"></i> View My Activity
            </a>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>