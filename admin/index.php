<?php
require '../includes/functions.php';

if (is_user_logged_in()) {
    if (is_admin()) {
        header("Location: dashboard.php");
    } else {
        // Subscriber trying to access admin? Redirect
        header("Location: ../public/dashboard.php");  // Placeholder for subscriber dashboard
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Check role: only admin/subscriber allowed
                if ($user['role'] === 'admin' || $user['role'] === 'subscriber') {
                    $_SESSION['user_id']   = $user['id'];  // General user ID
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role'];
                    if ($user['role'] === 'admin') {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: ../public/dashboard.php");  // Subscriber redirect
                    }
                    exit;
                } else {
                    $error = "This account type cannot login here. Guests don't need to login.";
                }
            } else {
                $error = "Wrong username or password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - TeacherPlus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body { background: #f8f9fa; } .login-box { max-width: 400px; margin: 100px auto; } </style>
</head>
<body>
<div class="container">
    <div class="login-box card p-4 shadow">
        <h3 class="text-center mb-4">Admin Login</h3>
        <p class="text-center text-muted small">Only admins and subscribers can login. Guests browse freely!</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</div>
</body>
</html>