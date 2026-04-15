<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    header("Location: ../public/login.php");
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $content        = $_POST['content'] ?? '';
    $category       = trim($_POST['category'] ?? '');
    $tags           = trim($_POST['tags'] ?? '');
    $author_name    = trim($_POST['author_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username']);
    $status         = $_POST['status'] ?? 'draft';
    $scheduled_date = ($status === 'scheduled' && !empty($_POST['scheduled_date'])) ? $_POST['scheduled_date'] : null;

    if (empty($title) || empty($content)) {
        $errors[] = "Title and Content are required.";
    }

    $featured_image = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload_dir = '../uploads/articles/' . date('Y-m') . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed) && $_FILES['featured_image']['size'] < 5000000) {
            $filename = time() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target)) {
                $featured_image = 'uploads/articles/' . date('Y-m') . '/' . $filename;
            } else {
                $errors[] = "Image upload failed.";
            }
        } else {
            $errors[] = "Invalid image (jpg/png/gif/webp, max 5MB)";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO articles (
                title, body, featured_image, author_name, category, tags, type, status, scheduled_date
            ) VALUES (?, ?, ?, ?, ?, ?, 'article', ?, ?)
        ");
        $stmt->bind_param("ssssssss", $title, $content, $featured_image, $author_name, $category, $tags, $status, $scheduled_date);

        if ($stmt->execute()) {
            $success = "Article saved successfully!";

            // Activity Log
            $action = "Uploaded Article";
            $details = "Title: " . $title . " | Status: " . ucfirst($status);

            $log_stmt = $conn->prepare("
                INSERT INTO activity_log (user_id, username, action, details)
                VALUES (?, ?, ?, ?)
            ");
            $log_stmt->bind_param("isss", $_SESSION['user_id'], $_SESSION['username'], $action, $details);
            $log_stmt->execute();
        } else {
            $errors[] = "Database error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New Article</title>

<link rel="stylesheet" href="../admin/assets/css/admin.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://cdn.tiny.cloud/1/3z8e1162aio8qxwm824a0kcntg894n1titsnxfbomocu21ww/tinymce/6/tinymce.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
.main-content {
    padding: 30px;
    margin-left: 250px;
}

.card {
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
</style>

</head>

<body>

<?php include 'staff-sidebar.php'; ?>

<div class="main-content">

    <div class="card p-4">
        <h3 class="mb-4">Add New Article</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <p><?php echo $err; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

            <div class="mb-3">
                <label class="form-label fw-bold">Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Content *</label>
                <textarea name="content" id="editor"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Tags</label>
                <input type="text" name="tags" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Featured Image</label>
                <input type="file" name="featured_image" class="form-control">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Status</label>

                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input type="radio" name="status" value="draft" class="form-check-input" checked>
                        <label class="form-check-label">Draft</label>
                    </div>

                    <div class="form-check">
                        <input type="radio" name="status" value="scheduled" class="form-check-input">
                        <label class="form-check-label">Schedule</label>
                    </div>

                    <div class="form-check">
                        <input type="radio" name="status" value="published" class="form-check-input">
                        <label class="form-check-label">Publish</label>
                    </div>
                </div>

                <div class="mt-3" id="scheduleField" style="display:none;">
                    <input type="datetime-local" name="scheduled_date" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Article</button>

        </form>
    </div>

</div>

<script>
tinymce.init({
    selector: '#editor',
    height: 400,
    menubar: false,
    plugins: 'lists link image code fullscreen',
    toolbar: 'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | code'
});

document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('scheduleField').style.display =
            (this.value === 'scheduled') ? 'block' : 'none';
    });
});
</script>

</body>
</html>
