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

    $title       = trim($_POST['title'] ?? '');
    $issue_year  = (int)($_POST['issue_year'] ?? date('Y'));
    $author_name = trim($_POST['author_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username']);
    $status      = $_POST['status'] ?? 'draft';
    $scheduled_date = ($status === 'scheduled' && !empty($_POST['scheduled_date'])) ? $_POST['scheduled_date'] : null;

    if (empty($title) || empty($issue_year)) {
        $errors[] = "Title and Issue Year are required.";
    }

    // ✅ PDF Upload
    $pdf_file = null;
    if (!empty($_FILES['pdf_file']['name'])) {

        $upload_dir = '../uploads/e-magazines/' . $issue_year . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));

        if ($ext === 'pdf' && $_FILES['pdf_file']['size'] < 30000000) {

            $filename = time() . '_' . preg_replace('/\s+/', '_', basename($_FILES['pdf_file']['name']));
            $target = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target)) {
                $pdf_file = 'uploads/e-magazines/' . $issue_year . '/' . $filename;
            } else {
                $errors[] = "Failed to upload PDF.";
            }

        } else {
            $errors[] = "Only PDF allowed (max 30MB).";
        }

    } else {
        $errors[] = "PDF file is required.";
    }

    // ✅ Cover Image Upload
    $cover_image = null;

    if (!empty($_FILES['cover_image']['name'])) {

        $upload_dir_img = '../uploads/covers/' . $issue_year . '/';
        if (!is_dir($upload_dir_img)) mkdir($upload_dir_img, 0755, true);

        $ext_img = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (in_array($ext_img, $allowed) && $_FILES['cover_image']['size'] < 5000000) {

            $filename_img = time() . '_cover.' . $ext_img;
            $target_img = $upload_dir_img . $filename_img;

            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_img)) {
                $cover_image = 'uploads/covers/' . $issue_year . '/' . $filename_img;
            } else {
                $errors[] = "Failed to upload cover image.";
            }

        } else {
            $errors[] = "Invalid image (jpg/png/webp, max 5MB).";
        }

    } else {
        $errors[] = "Cover image is required.";
    }

    // ✅ Insert into DB
    if (empty($errors)) {

        $stmt = $conn->prepare("
            INSERT INTO e_magazines 
            (title, issue_year, pdf_file, cover_image, author_name, status, scheduled_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("sisssss", $title, $issue_year, $pdf_file, $cover_image, $author_name, $status, $scheduled_date);

        if ($stmt->execute()) {

            $success = "E-Magazine uploaded successfully!";

            // ✅ Activity Log FIXED
            $action = "Uploaded E-Magazine";
            $details = "Title: $title | Status: " . ucfirst($status);

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
<title>Add New E-Magazine</title>

<!-- ✅ Admin CSS -->
<link rel="stylesheet" href="../admin/assets/css/admin.css">

<!-- ✅ Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- ✅ ICON FIX (VERY IMPORTANT) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
.main-content {
    margin-left: 250px;
    padding: 30px;
}

.card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>

</head>

<body>

<!-- ✅ Sidebar -->
<?php include 'staff-sidebar.php'; ?>

<div class="main-content">

    <div class="card p-4">
        <h3 class="mb-4">Add New E-Magazine</h3>

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
                <label class="form-label fw-bold">Issue Year *</label>
                <input type="number" name="issue_year" class="form-control" value="<?php echo date('Y'); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">PDF File *</label>
                <input type="file" name="pdf_file" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Cover Image *</label>
                <input type="file" name="cover_image" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Author</label>
                <input type="text" name="author_name" class="form-control">
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

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload
            </button>

            <a href="dashboard.php" class="btn btn-secondary ms-2">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>

        </form>
    </div>

</div>

<script>
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('scheduleField').style.display =
            (this.value === 'scheduled') ? 'block' : 'none';
    });
});
</script>

</body>
</html>
