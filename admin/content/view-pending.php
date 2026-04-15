<?php
require '../../includes/config.php';
require '../../includes/functions.php';

require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pending-contributions.php");
    exit;
}

$id = (int)$_GET['id'];

// Fetch the contribution
$stmt = $conn->prepare("SELECT * FROM pending_contributions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<div class='alert alert-danger'>Contribution not found.</div>";
    exit;
}

$row = $res->fetch_assoc();
$stmt->close();

// Handle Approve / Reject
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $insert = $conn->prepare("
            INSERT INTO articles 
            (title, subtitle, category, tags, author_name, body, summary, is_free, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'published')
        ");

        if ($insert) {
            $insert->bind_param(
                "sssssss",
                $row['title'],
                $row['subtitle'],
                $row['category'],
                $row['tags'],
                $row['author_name'],
                $row['body'],
                $row['summary']
            );

            if ($insert->execute()) {
                // Delete from pending
                $delete = $conn->prepare("DELETE FROM pending_contributions WHERE id = ?");
                $delete->bind_param("i", $id);
                $delete->execute();
                $delete->close();

                $success_msg = "Article approved and published successfully.";
                echo "<script>setTimeout(function(){ window.location='pending-contributions.php'; }, 2000);</script>";
            } else {
                $error_msg = "Error publishing article: " . $insert->error;
            }
            $insert->close();
        } else {
            $error_msg = "Prepare failed: " . $conn->error;
        }
    }

    elseif ($action === 'reject') {
        $delete = $conn->prepare("DELETE FROM pending_contributions WHERE id = ?");
        $delete->bind_param("i", $id);

        if ($delete->execute()) {
            $success_msg = "Contribution rejected and removed.";
            echo "<script>setTimeout(function(){ window.location='pending-contributions.php'; }, 2000);</script>";
        } else {
            $error_msg = "Error rejecting: " . $delete->error;
        }
        $delete->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Pending Contribution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../dashboard.php">TeacherPlus Admin</a>
        <a href="../logout.php" class="btn btn-outline-light">Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <h2>View Contribution: <?php echo htmlspecialchars($row['title']); ?></h2>
    <a href="pending-contributions.php" class="btn btn-secondary mb-3">← Back to Pending List</a>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Article Details</div>
        <div class="card-body">
            <p><strong>Title:</strong> <?php echo htmlspecialchars($row['title']); ?></p>
            <p><strong>Subtitle:</strong> <?php echo htmlspecialchars($row['subtitle'] ?? 'N/A'); ?></p>
            <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></p>
            <p><strong>Tags:</strong> <?php echo htmlspecialchars($row['tags'] ?? 'N/A'); ?></p>
            <p><strong>Body:</strong><br><?php echo nl2br(htmlspecialchars($row['body'])); ?></p>
            <p><strong>Summary:</strong><br><?php echo nl2br(htmlspecialchars($row['summary'] ?? 'N/A')); ?></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Author Details</div>
        <div class="card-body">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($row['author_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($row['author_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['author_phone'] ?? 'N/A'); ?></p>
            <p><strong>Bio:</strong><br><?php echo nl2br(htmlspecialchars($row['author_bio'] ?? 'N/A')); ?></p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">Admin Actions</div>
        <div class="card-body">
            <form method="post">
                <button type="submit" name="action" value="approve" class="btn btn-success btn-lg me-3">
                    Approve & Publish
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg">
                    Reject
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>