<?php
session_start();
require '../includes/config.php';

if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'])) {
    header("Location: ../public/login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

// Fetch article (add ownership check later for staff)
$stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("Not found");

$article = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Content</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Content</h2>
        <form method="post" enctype="multipart/form-data">
            <!-- Pre-fill fields from $article -->
            <div class="mb-3">
                <label>Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($article['title']); ?>">
            </div>
            <!-- Add other fields similarly -->
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</body>
</html>