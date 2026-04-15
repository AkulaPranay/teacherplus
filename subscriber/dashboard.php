<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!is_subscriber()) {
    header("Location: ../public/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscriber Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">TeacherPlus Subscriber</span>
            <a href="../logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </nav>

    <div class="container mt-5">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
        <p class="lead">Your subscription is active.</p>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Profile</h5>
                        <p>Email: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <a href="#" class="btn btn-primary">Edit Profile</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Premium Content</h5>
                        <a href="#" class="btn btn-primary mb-2">E-Magazines</a><br>
                        <a href="#" class="btn btn-primary">Worksheets</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
