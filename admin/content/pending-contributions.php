<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if (!is_admin() && !is_staff()) {
    header("Location: ../public/login.php");
    exit;
}

$result = $conn->query("SELECT * FROM pending_contributions ORDER BY submitted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Contributions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Pending Article Contributions</h2>

        <?php if ($result->num_rows > 0): ?>
            <table class="table table-bordered mt-4">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Email</th>
                        <th>Submitted</th>
                        <th>Image</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['author_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['author_email']); ?></td>
                            <td><?php echo $row['submitted_at']; ?></td>
                            <td>
                                <?php if ($row['featured_image']): ?>
                                    <a href="/<?php echo $row['featured_image']; ?>" target="_blank">View</a>
                                <?php else: ?>
                                    No image
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="review-contribution.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No pending contributions.</p>
        <?php endif; ?>
    </div>
</body>
</html>