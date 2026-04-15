<?php
/**
 * public/forgot-password.php
 * Step 1 – user enters their email and receives a reset link.
 */
require '../includes/config.php';
require '../includes/mailer.php';

$page_title = "Forgot Password - Teacher Plus";
include '../includes/header.php';

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up user
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Always show success (security: don't reveal whether email exists)
        $msg = 'If that email is registered, you will receive a password reset link shortly.';

        if ($user) {
            // Generate a secure token
            $token    = bin2hex(random_bytes(32));
            $expires  = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token (create table if needed — see below)
            $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del->bind_param('s', $email);
            $del->execute();
            $del->close();

            $ins = $conn->prepare(
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
            );
            $ins->bind_param('sss', $email, $token, $expires);
            $ins->execute();
            $ins->close();

            // Send email
            tp_send_password_reset($email, $user['full_name'] ?? $email, $token);
        }
    }
}
?>

<style>
.reset-wrap {
    max-width: 460px;
    margin: 60px auto 100px;
    padding: 0 16px;
}
.reset-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.08);
    padding: 40px;
}
.reset-card h2 {
    color: #f87407;
    font-size: 1.4rem;
    margin-bottom: 6px;
}
.reset-card p.lead {
    font-size: 14px;
    color: #666;
    margin-bottom: 24px;
}
.btn-tp {
    background: #f87407;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 11px 24px;
    font-weight: 600;
    width: 100%;
    font-size: 15px;
    cursor: pointer;
}
.btn-tp:hover { background: #d96500; }
.back-link { text-align: center; margin-top: 16px; font-size: 13px; }
.back-link a { color: #3d348b; }
</style>

<div class="reset-wrap">
    <div class="reset-card">
        <h2>Forgot Password</h2>
        <p class="lead">Enter your registered email and we will send you a reset link.</p>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="font-size:14px;"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="font-size:14px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$msg): ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:14px;">Email Address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@example.com" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn-tp">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>