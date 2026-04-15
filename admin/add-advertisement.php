<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title']);
    $ad_type    = $_POST['ad_type'];
    $position   = $_POST['position'];
    $link_url   = trim($_POST['link_url'] ?? '');
    $status     = $_POST['status'];

    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $target_dir = "../uploads/ads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_name = uniqid('ad_') . '.' . $ext;
        $target_file = $target_dir . $new_name;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = 'uploads/ads/' . $new_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO advertisements 
        (title, ad_type, position, image_path, link_url, status) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $title, $ad_type, $position, $image_path, $link_url, $status);
    
    if ($stmt->execute()) {
        header("Location: advertisements.php?msg=added");
        exit;
    } else {
        $msg = "Error adding advertisement.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Advertisement - TeacherPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body { background: #f4f6f9; margin: 0; }
        .main-content { margin-left: 260px; padding: 40px 30px; min-height: 100vh; }
    </style>
</head>
<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <h2 class="mb-4 text-orange">Add New Advertisement</h2>

        <?php if ($msg): ?>
            <div class="alert alert-danger"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ad Type</label>
                            <select name="ad_type" class="form-select" required>
                                <option value="banner">Banner</option>
                                <option value="sidebar">Sidebar</option>
                                <option value="video">Video</option>
                                <option value="text">Text Only</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position <span class="text-danger">*</span></label>
                            <select name="position" class="form-select" required>
                                <option value="">-- Select Position --</option>
                                <option value="between_articles">Between Articles / In-Content</option>
                                <option value="sidebar_right">Sidebar Right</option>
                                <option value="homepage_bottom">Homepage Bottom</option>
                                <option value="article_top">Article Top</option>
                                <option value="article_bottom">Article Bottom</option>
                                <option value="article_inline">Article Inline (Between Paragraphs)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image (optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Link URL (optional)</label>
                        <input type="url" name="link_url" class="form-control">
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-orange px-4">Add Advertisement</button>
                        <a href="advertisements.php" class="btn btn-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>