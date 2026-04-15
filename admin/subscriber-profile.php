<?php
session_start();
require '../includes/config.php';
require '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: subscribers.php");
    exit;
}

$msg = '';
$error = '';

// ── Handle form submission ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name           = trim($_POST['full_name'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $phone               = trim($_POST['phone'] ?? '');
        $subscription_expiry = !empty($_POST['subscription_expiry']) ? $_POST['subscription_expiry'] : null;

        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, email = ?, phone = ?, subscription_expiry = ?
            WHERE id = ? AND role = 'subscriber'
        ");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $subscription_expiry, $id);
        $stmt->execute() ? $msg = "Profile updated." : $error = "Update failed: " . $conn->error;
    }

    if ($action === 'change_password') {
        $new_pw  = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new_pw) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($new_pw !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->execute() ? $msg = "Password changed." : $error = "Failed: " . $conn->error;
        }
    }
}

// ── Fetch subscriber ────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, username, email, phone, full_name, role,
           created_at, subscription_expiry, last_activity
    FROM users
    WHERE id = ? AND role = 'subscriber'
");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Subscriber not found.");
}

// ── Fetch subscription orders ───────────────────────────────────────────────
$orders_stmt = $conn->prepare("
    SELECT id, plan_name, amount, payment_method, status, created_at
    FROM subscription_orders
    WHERE email = ?
    ORDER BY created_at DESC
");
$orders_stmt->bind_param("s", $user['email']);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// ── Helpers ─────────────────────────────────────────────────────────────────
$display_name = htmlspecialchars($user['full_name'] ?: $user['username']);
$initials     = strtoupper(substr($user['full_name'] ?: $user['username'], 0, 1));
$is_expired   = $user['subscription_expiry'] && strtotime($user['subscription_expiry']) < time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Subscriber Profile — <?php echo $display_name; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
/* ── Page Layout ── */
body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; margin: 0; }
.main-content { margin-left: 260px; padding: 36px 32px; min-height: 100vh; }

/* ── Header strip ── */
.page-header {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 28px;
}
.page-header a { color: #6c757d; text-decoration: none; font-size: 13px; }
.page-header a:hover { color: #f87407; }
.page-header h2 { margin: 0; font-size: 1.35rem; font-weight: 700; color: #1f2a4a; }

/* ── Profile card top ── */
.profile-hero {
    background: linear-gradient(135deg, #1f2a4a 0%, #3d348b 100%);
    border-radius: 14px;
    padding: 32px 36px;
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
    color: #fff;
}
.avatar-circle {
    width: 76px; height: 76px; border-radius: 50%;
    background: #f87407;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 700; color: #fff;
    flex-shrink: 0;
    border: 3px solid rgba(255,255,255,0.3);
}
.profile-hero .name { font-size: 1.3rem; font-weight: 700; margin-bottom: 3px; }
.profile-hero .meta { font-size: 13px; opacity: 0.75; }
.profile-hero .badges { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
.badge-role {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.badge-expiry { background: rgba(248,116,7,0.8); border-color: #f87407; }
.badge-expired { background: rgba(220,53,69,0.8); border-color: #dc3545; }

/* ── Section cards ── */
.section-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 22px;
    overflow: hidden;
}
.section-card .card-head {
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    padding: 14px 20px;
    display: flex; align-items: center; gap: 10px;
    font-weight: 700; font-size: 14px; color: #1f2a4a;
}
.section-card .card-head i { color: #f87407; width: 18px; text-align: center; }
.section-card .card-body-inner { padding: 22px 24px; }

/* ── Form inputs ── */
.form-label { font-size: 12px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 5px; }
.form-control {
    border: 1px solid #dee2e6;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus {
    border-color: #f87407;
    box-shadow: 0 0 0 3px rgba(248,116,7,0.12);
    outline: none;
}
.form-control[readonly] { background: #f9f9f9; color: #888; cursor: not-allowed; }

/* ── Buttons ── */
.btn-save {
    background: #f87407; border: none; color: #fff;
    padding: 9px 26px; border-radius: 7px;
    font-weight: 600; font-size: 14px;
    transition: background 0.2s;
}
.btn-save:hover { background: #d9610a; color: #fff; }
.btn-back {
    background: #fff; border: 1px solid #dee2e6; color: #555;
    padding: 9px 20px; border-radius: 7px;
    font-weight: 500; font-size: 14px;
    transition: all 0.2s;
}
.btn-back:hover { border-color: #f87407; color: #f87407; }

/* ── Password toggle ── */
.pw-wrap { position: relative; }
.pw-wrap .eye-btn {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #aaa; font-size: 14px; padding: 0;
}
.pw-wrap .eye-btn:hover { color: #f87407; }

/* ── Orders table ── */
.orders-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #888; background: #f8f9fa; border-bottom: 2px solid #e9ecef; }
.orders-table td { font-size: 13px; vertical-align: middle; }
.status-badge {
    padding: 3px 10px; border-radius: 4px;
    font-size: 11px; font-weight: 700; text-transform: uppercase;
}
.s-pending    { background: #fff3cd; color: #856404; }
.s-completed  { background: #d4edda; color: #155724; }
.s-processing { background: #cce5ff; color: #004085; }
.s-cancelled  { background: #f8d7da; color: #721c24; }

/* ── Alert ── */
.alert-tp { padding: 10px 16px; border-radius: 7px; font-size: 13px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
.alert-ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* ── Readonly field hint ── */
.field-hint { font-size: 11px; color: #aaa; margin-top: 3px; }
</style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="main-content">

    <!-- Page header -->
    <div class="page-header">
        <a href="subscribers.php"><i class="fas fa-arrow-left me-1"></i> All Subscribers</a>
        <span style="color:#dee2e6">/</span>
        <h2><?php echo $display_name; ?></h2>
    </div>

    <?php if ($msg): ?>
        <div class="alert-tp alert-ok"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-tp alert-err"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Profile hero -->
    <div class="profile-hero">
        <div class="avatar-circle"><?php echo $initials; ?></div>
        <div>
            <div class="name"><?php echo $display_name; ?></div>
            <div class="meta">@<?php echo htmlspecialchars($user['username']); ?> &bull; <?php echo htmlspecialchars($user['email']); ?></div>
            <div class="meta">Joined <?php echo date('d M Y', strtotime($user['created_at'])); ?> &bull; Last active <?php echo $user['last_activity'] ? date('d M Y', strtotime($user['last_activity'])) : 'Never'; ?></div>
            <div class="badges">
                <span class="badge-role">Subscriber</span>
                <?php if ($user['subscription_expiry']): ?>
                    <span class="badge-role <?php echo $is_expired ? 'badge-expired' : 'badge-expiry'; ?>">
                        <?php echo $is_expired ? 'Expired' : 'Active'; ?> — <?php echo date('d M Y', strtotime($user['subscription_expiry'])); ?>
                    </span>
                <?php else: ?>
                    <span class="badge-role" style="background:rgba(108,117,125,0.5)">No expiry set</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- LEFT: Profile + Password -->
        <div class="col-lg-7">

            <!-- Personal Info -->
            <div class="section-card">
                <div class="card-head">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                <div class="card-body-inner">
                    <form method="post">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <div class="field-hint">Username cannot be changed</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control"
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                       placeholder="Enter full name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="Phone number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subscription Expiry</label>
                                <input type="date" name="subscription_expiry" class="form-control"
                                       value="<?php echo $user['subscription_expiry'] ?? ''; ?>">
                                <div class="field-hint">Leave blank if no expiry</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="Subscriber" readonly>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <a href="subscribers.php" class="btn-back">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="section-card">
                <div class="card-head">
                    <i class="fas fa-lock"></i> Change Password
                </div>
                <div class="card-body-inner">
                    <form method="post">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="new_password" id="pw1" class="form-control"
                                           placeholder="Min 6 characters" autocomplete="new-password">
                                    <button type="button" class="eye-btn" onclick="togglePw('pw1',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="confirm_password" id="pw2" class="form-control"
                                           placeholder="Re-enter password" autocomplete="new-password">
                                    <button type="button" class="eye-btn" onclick="togglePw('pw2',this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-key me-1"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- /col-lg-7 -->

        <!-- RIGHT: Order History -->
        <div class="col-lg-5">

            <div class="section-card">
                <div class="card-head">
                    <i class="fas fa-receipt"></i> Subscription Orders
                    <span class="ms-auto" style="font-size:12px;color:#aaa;font-weight:400">
                        <?php echo $orders->num_rows; ?> order(s)
                    </span>
                </div>
                <div class="card-body-inner" style="padding: 0;">
                    <?php if ($orders->num_rows > 0): ?>
                        <table class="table orders-table mb-0">
                            <thead>
                                <tr>
                                    <th style="padding:12px 16px">Plan</th>
                                    <th style="padding:12px 16px">Amount</th>
                                    <th style="padding:12px 16px">Date</th>
                                    <th style="padding:12px 16px">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding:10px 16px">
                                            <div style="font-weight:600;font-size:13px"><?php echo htmlspecialchars($order['plan_name']); ?></div>
                                            <div style="font-size:11px;color:#aaa;text-transform:uppercase"><?php echo $order['payment_method']; ?></div>
                                        </td>
                                        <td style="padding:10px 16px;font-weight:600">
                                            ₹<?php echo number_format($order['amount'], 0); ?>
                                        </td>
                                        <td style="padding:10px 16px;color:#888;font-size:12px">
                                            <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td style="padding:10px 16px">
                                            <span class="status-badge s-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding:32px;text-align:center;color:#aaa">
                            <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:10px;display:block"></i>
                            No orders found for this subscriber.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Info Summary -->
            <div class="section-card">
                <div class="card-head">
                    <i class="fas fa-info-circle"></i> Account Summary
                </div>
                <div class="card-body-inner">
                    <table class="table table-borderless mb-0" style="font-size:13px">
                        <tr>
                            <td style="color:#888;width:45%;padding:6px 0">User ID</td>
                            <td style="padding:6px 0;font-weight:600">#<?php echo $user['id']; ?></td>
                        </tr>
                        <tr>
                            <td style="color:#888;padding:6px 0">Member since</td>
                            <td style="padding:6px 0"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td style="color:#888;padding:6px 0">Last activity</td>
                            <td style="padding:6px 0"><?php echo $user['last_activity'] ? date('d M Y, H:i', strtotime($user['last_activity'])) : '—'; ?></td>
                        </tr>
                        <tr>
                            <td style="color:#888;padding:6px 0">Subscription expires</td>
                            <td style="padding:6px 0">
                                <?php if ($user['subscription_expiry']): ?>
                                    <span style="color:<?php echo $is_expired ? '#dc3545' : '#28a745'; ?>;font-weight:600">
                                        <?php echo date('d M Y', strtotime($user['subscription_expiry'])); ?>
                                        <?php echo $is_expired ? ' (Expired)' : ' (Active)'; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#aaa">Not set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        </div><!-- /col-lg-5 -->

    </div><!-- /row -->

</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const isText = f.type === 'text';
    f.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>