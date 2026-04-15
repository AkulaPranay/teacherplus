<?php
/**
 * public/reset-password.php
 * Step 2 – user clicks the emailed link and sets a new password.
 */
require '../includes/config.php';

$page_title = "Reset Password - Teacher Plus";
include '../includes/header.php';

$token = trim($_GET['token'] ?? '');
$msg   = '';
$error = '';
$valid = false;
$reset_email = '';

// Validate token
if ($token) {
    $stmt = $conn->prepare("
        SELECT email FROM password_resets
        WHERE token = ? AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $valid       = true;
        $reset_email = $row['email'];
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle new password submission
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass    = $_POST['password']         ?? '';
    $confirm     = $_POST['password_confirm'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $upd->bind_param('ss', $hash, $reset_email);
        $upd->execute();
        $upd->close();

        // Delete used token
        $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->bind_param('s', $reset_email);
        $del->execute();
        $del->close();

        $valid = false; // hide form
        $msg   = 'Your password has been reset successfully. You can now <a href="login.php">login</a>.';
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
.reset-card h2 { color: #f87407; font-size: 1.4rem; margin-bottom: 6px; }
.reset-card p.lead { font-size: 14px; color: #666; margin-bottom: 24px; }
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
.strength-bar { height: 5px; border-radius: 3px; margin-top: 6px; background: #eee; }
.strength-fill { height: 100%; border-radius: 3px; transition: width .3s, background .3s; }
.strength-text { font-size: 12px; color: #888; text-align: right; margin-top: 3px; }
.back-link { text-align: center; margin-top: 16px; font-size: 13px; }
.back-link a { color: #3d348b; }
</style>

<div class="reset-wrap">
    <div class="reset-card">
        <h2>Reset Password</h2>
        <p class="lead">Choose a strong new password for your account.</p>

        <?php if ($msg): ?>
            <div class="alert alert-success" style="font-size:14px;"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="font-size:14px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($valid): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <div class="mb-3">
                <label class="form-label fw-bold" style="font-size:14px;">New Password</label>
                <input type="password" name="password" id="pw-new" class="form-control"
                       placeholder="Min. 8 characters" required minlength="8">
                <div class="strength-bar"><div class="strength-fill" id="strength-fill" style="width:0"></div></div>
                <div class="strength-text" id="strength-text"></div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold" style="font-size:14px;">Confirm New Password</label>
                <input type="password" name="password_confirm" class="form-control"
                       placeholder="Repeat password" required>
            </div>

            <button type="submit" class="btn-tp">Set New Password</button>
        </form>

        <?php elseif (!$msg): ?>
            <div class="back-link">
                <a href="forgot-password.php">Request a new reset link</a>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</div>

<script>
document.getElementById('pw-new')?.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;

    const colours = ['#dc3545','#fd7e14','#ffc107','#20c997','#198754'];
    const labels  = ['Very Weak','Weak','Fair','Good','Strong'];
    const pct     = [20, 40, 60, 80, 100];
    const idx     = Math.min(score, 4);

    document.getElementById('strength-fill').style.width      = pct[idx] + '%';
    document.getElementById('strength-fill').style.background = colours[idx];
    document.getElementById('strength-text').textContent      = labels[idx];
});
</script>

<?php include '../includes/footer.php'; ?>