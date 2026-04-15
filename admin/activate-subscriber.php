<?php
/**
 * admin/activate-subscriber.php
 *
 * Admin visits this page to confirm payment and activate a subscriber.
 * Fires the payment confirmation email automatically.
 *
 * Usage: admin/activate-subscriber.php?order_id=42
 */
session_start();
require '../includes/config.php';
require '../includes/functions.php';
require '../includes/mailer.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

$msg   = '';
$error = '';

// ── Load pending orders ────────────────────────────────────────────────────
$orders = $conn->query("
    SELECT so.id, so.first_name, so.last_name, so.email, so.phone,
           so.status, so.created_at, so.plan_id, so.subscription_expiry,
           sp.name AS plan_name, sp.price, sp.duration_months
    FROM subscription_orders so
    LEFT JOIN subscription_plans sp ON so.plan_id = sp.id
    ORDER BY so.created_at DESC
    LIMIT 100
");

// ── Handle activation ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_order_id'])) {
    $order_id = (int)$_POST['activate_order_id'];

    // Fetch order details
    $os = $conn->prepare("
        SELECT so.*, sp.name AS plan_name, sp.price, sp.duration_months
        FROM subscription_orders so
        LEFT JOIN subscription_plans sp ON so.plan_id = sp.id
        WHERE so.id = ?
    ");
    $os->bind_param('i', $order_id);
    $os->execute();
    $order = $os->get_result()->fetch_assoc();
    $os->close();

    if (!$order) {
        $error = 'Order not found.';
    } else {
        // Calculate expiry
        $expiry = date('Y-m-d', strtotime('+' . $order['duration_months'] . ' months'));

        // 1. Mark order as active
        $upd = $conn->prepare("
            UPDATE subscription_orders
            SET status = 'active', activated_at = NOW(), subscription_expiry = ?
            WHERE id = ?
        ");
        $upd->bind_param('si', $expiry, $order_id);
        $upd->execute();
        $upd->close();

        // 2. Check if user account already exists, create or update
        $email     = $order['email'];
        $full_name = trim($order['first_name'] . ' ' . $order['last_name']);

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            // Update existing subscriber's expiry
            $uupd = $conn->prepare("
                UPDATE users SET subscription_expiry = ?, role = 'subscriber' WHERE id = ?
            ");
            $uupd->bind_param('si', $expiry, $existing['id']);
            $uupd->execute();
            $uupd->close();
        } else {
            // Create new subscriber account
            $username = $order['username'] ?? strtolower(str_replace(' ', '.', $full_name));
            $pw_hash  = $order['password_hash'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

            $ins = $conn->prepare("
                INSERT INTO users (username, email, full_name, phone, password, role, subscription_expiry)
                VALUES (?, ?, ?, ?, ?, 'subscriber', ?)
            ");
            $ins->bind_param('ssssss',
                $username, $email, $full_name, $order['phone'], $pw_hash, $expiry
            );
            $ins->execute();
            $ins->close();
        }

        // 3. Send payment confirmation email
        $sent = tp_send_payment_confirmation(
            $email,
            $full_name,
            $order['plan_name'],
            (float)$order['price'],
            $order_id,
            $expiry
        );

        $msg = "Order #{$order_id} activated. Subscription valid until {$expiry}. "
             . ($sent ? '✅ Confirmation email sent.' : '⚠️ Email sending failed — check logs.');

        // Reload orders
        $orders = $conn->query("
            SELECT so.id, so.first_name, so.last_name, so.email, so.phone,
                   so.status, so.created_at, so.plan_id, so.subscription_expiry,
                   sp.name AS plan_name, sp.price, sp.duration_months
            FROM subscription_orders so
            LEFT JOIN subscription_plans sp ON so.plan_id = sp.id
            ORDER BY so.created_at DESC
            LIMIT 100
        ");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate Subscribers – Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        body { background:#f5f7fa; font-family:'Segoe UI',sans-serif; margin:0; }
        .main-content { margin-left:260px; padding:40px 30px; min-height:100vh; }
        .badge-pending  { background:#fd7e14; }
        .badge-active   { background:#198754; }
        .badge-inactive { background:#6c757d; }
        td, th { vertical-align: middle !important; }
    </style>
</head>
<body>

<?php include '../includes/sidebar.php'; ?>

<div class="main-content">
    <h2 class="mb-4" style="color:#fd7e14;">Subscription Orders – Activate &amp; Confirm Payment</h2>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">All Orders</h5>
            <small class="text-muted">Click <em>Activate</em> to confirm payment and send the subscriber their confirmation email.</small>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0" style="font-size:14px;">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Submitted</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($o = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['first_name'] . ' ' . $o['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($o['email']); ?></td>
                            <td><?php echo htmlspecialchars($o['plan_name'] ?? '—'); ?></td>
                            <td>₹<?php echo number_format($o['price'] ?? 0, 0); ?></td>
                            <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                            <td><?php echo $o['subscription_expiry'] ? date('d M Y', strtotime($o['subscription_expiry'])) : '—'; ?></td>
                            <td>
                                <?php
                                $s = $o['status'];
                                $cls = $s === 'active' ? 'badge-active' : ($s === 'pending' ? 'badge-pending' : 'badge-inactive');
                                ?>
                                <span class="badge <?php echo $cls; ?>"><?php echo ucfirst($s); ?></span>
                            </td>
                            <td>
                                <?php if ($o['status'] !== 'active'): ?>
                                    <form method="post" style="display:inline;"
                                          onsubmit="return confirm('Activate order #<?php echo $o['id']; ?> and send payment confirmation email?');">
                                        <input type="hidden" name="activate_order_id" value="<?php echo $o['id']; ?>">
                                        <button class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Active</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>