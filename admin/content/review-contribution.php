<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!is_admin() && !is_staff()) {
    header("Location: ../public/login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$stmt = $conn->prepare("SELECT * FROM pending_contributions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("Contribution not found");

$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review Contribution #<?php echo $id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Review Contribution #<?php echo $id; ?></h2>

        <div class="card mb-4">
            <div class="card-body">
                <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($row['author_name']); ?> (<?php echo $row['author_email']; ?>)</p>
                <p><strong>Submitted:</strong> <?php echo $row['submitted_at']; ?></p>

                <?php if ($row['featured_image']): ?>
                    <p><strong>Featured Image:</strong><br>
                    <img src="/<?php echo $row['featured_image']; ?>" alt="Featured" style="max-width:400px;"></p>
                <?php endif; ?>

                <p><strong>Excerpt:</strong><br><?php echo nl2br(htmlspecialchars($row['excerpt'] ?? 'None')); ?></p>
                <p><strong>Full Body:</strong><br><pre><?php echo htmlspecialchars($row['body']); ?></pre></p>

                <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category'] ?? 'None'); ?></p>
                <p><strong>Tags:</strong> <?php echo htmlspecialchars($row['tags'] ?? 'None'); ?></p>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Next step:</strong> Copy the above details and create a new article in the staff dashboard.
            <br><a href="../user/add-content.php" class="btn btn-primary mt-2">Go to Add Content Form</a>
        </div>

        <!-- Optional: Add Approve/Reject buttons later -->
    </div>
</body>
</html>