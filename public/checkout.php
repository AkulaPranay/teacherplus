<?php
require '../includes/config.php';
require '../includes/mailer.php';   // ← fires registration email on Place Order

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$plan_id  = intval($_GET['plan_id']  ?? $_POST['plan_id']  ?? 0);
$upgrade  = isset($_GET['upgrade']) || isset($_POST['upgrade']);

// ── FIX 1: Prevent duplicate orders on refresh/back ──
// Only create a new order if NO existing pending order exists for this user+plan
if (!$order_id && $plan_id && $upgrade) {

    // Check for an existing pending order first
    $chk = $conn->prepare("
        SELECT id FROM subscription_orders
        WHERE user_id = ? AND plan_id = ? AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $chk->bind_param("ii", $user_id, $plan_id);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();

    if ($existing) {
        // Reuse the existing pending order instead of creating a new one
        $order_id = $existing['id'];
    } else {
        // Fetch plan
        $ps = $conn->prepare("SELECT id, name, price, duration_months FROM subscription_plans WHERE id = ?");
        $ps->bind_param("i", $plan_id);
        $ps->execute();
        $plan = $ps->get_result()->fetch_assoc();

        if (!$plan) {
            header("Location: renewal.php");
            exit;
        }

        // Create ONE new pending order
        $ins = $conn->prepare("
            INSERT INTO subscription_orders
                (user_id, username, first_name, last_name, email, phone, plan_id, plan_name, amount, payment_method, status, created_at)
            VALUES (?, ?, '', '', '', '', ?, ?, ?, 'gateway', 'pending', NOW())
        ");
        $ins->bind_param("issis",
            $user_id, $_SESSION['username'],
            $plan['id'], $plan['name'], $plan['price']
        );
        $ins->execute();
        $order_id = $conn->insert_id;
    }
}

if (!$order_id) {
    header("Location: subscribe-new.php");
    exit;
}

// ── FIX 2: Handle cancel order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $cancel_id = intval($_POST['order_id'] ?? 0);

    if ($cancel_id) {
        // Only allow cancelling own pending orders
        $cancel_stmt = $conn->prepare("
            UPDATE subscription_orders
            SET status = 'cancelled'
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $cancel_stmt->bind_param("ii", $cancel_id, $user_id);
        $cancel_stmt->execute();

        if ($cancel_stmt->affected_rows > 0) {
            header("Location: renewal.php?cancelled=1");
        } else {
            header("Location: renewal.php?cancel_failed=1");
        }
        exit;
    }
}

// Fetch the order + plan details
$order_stmt = $conn->prepare("
    SELECT so.*, sp.duration_months
    FROM subscription_orders so
    LEFT JOIN subscription_plans sp ON sp.id = so.plan_id
    WHERE so.id = ? AND so.user_id = ?
");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: subscribe-new.php");
    exit;
}

// Handle billing form submission
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $first_name     = trim($_POST['first_name']     ?? '');
    $last_name      = trim($_POST['last_name']      ?? '');
    $country        = trim($_POST['country']        ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $apartment      = trim($_POST['apartment']      ?? '');
    $town_city      = trim($_POST['town_city']      ?? '');
    $state          = trim($_POST['state']          ?? '');
    $pin_code       = trim($_POST['pin_code']       ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $email          = trim($_POST['email']          ?? '');
    $order_notes    = trim($_POST['order_notes']    ?? '');

    if (!$first_name || !$last_name || !$country || !$street_address || !$town_city || !$state || !$pin_code || !$phone || !$email) {
        $error = "Please fill in all required fields.";
    } else {
        $full_address = $street_address;
        if ($apartment) $full_address .= ", " . $apartment;
        $full_address .= "\n" . $town_city . ", " . $state . " - " . $pin_code . "\n" . $country;

        $upd = $conn->prepare("
            UPDATE subscription_orders
            SET first_name=?, last_name=?, email=?, phone=?,
                address=?, country=?, state=?, city=?, pin_code=?,
                order_notes=?, status='pending'
            WHERE id=? AND user_id=?
        ");
        $upd->bind_param("ssssssssssii",
            $first_name, $last_name, $email, $phone,
            $full_address, $country, $state, $town_city, $pin_code,
            $order_notes, $order_id, $user_id
        );

        if ($upd->execute()) {
            // ── Send registration confirmation email ──────────────────────
            $full_name = $first_name . ' ' . $last_name;
            $plan_name = $order['plan_name'] ?? 'Selected Plan';
            $username  = $_SESSION['username'] ?? '';
            tp_send_registration($email, $full_name, $username, $plan_name);
            // ─────────────────────────────────────────────────────────────

            $success = "Your order has been placed successfully! "
                . "A confirmation email has been sent to " . htmlspecialchars($email) . ". "
                . "Please complete the payment via NEFT/Cheque and email the details to circulation@teacherplus.org.";
        } else {
            $error = "Order placement failed. Please try again. Error: " . $conn->error;
        }
    }
}

// Output HTML
$page_title = "Checkout - TeacherPlus";
include '../includes/header.php';

$fname = $order['first_name'] ?: ($_SESSION['full_name'] ? explode(' ', trim($_SESSION['full_name']))[0] : '');
$lname = $order['last_name']  ?: (strpos(trim($_SESSION['full_name'] ?? ''), ' ') !== false ? explode(' ', trim($_SESSION['full_name']), 2)[1] : '');
?>

<style>
    body { background: #fff; }

    .checkout-wrap {
        max-width: 1100px;
        margin: 40px auto 80px;
        padding: 0 30px;
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 30px;
        align-items: start;
    }

    .billing-panel {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 30px 28px;
        background: #fff;
    }

    .billing-panel h4 {
        font-size: 1.05rem;
        font-weight: 700;
        color: #222;
        margin-bottom: 24px;
    }

    .field-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .field-group { margin-bottom: 16px; }

    .field-group label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
    }

    .field-group label .req { color: #e07000; }

    .field-group input,
    .field-group select,
    .field-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #dce0e8;
        border-radius: 4px;
        font-size: 0.88rem;
        color: #333;
        background: #f7f8fb;
        box-sizing: border-box;
        outline: none;
        transition: border-color 0.2s;
    }

    .field-group input:focus,
    .field-group select:focus,
    .field-group textarea:focus {
        border-color: #aab;
        background: #fff;
    }

    .field-group input::placeholder,
    .field-group textarea::placeholder { color: #bbb; }

    .order-panel {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 24px 20px;
        background: #fff;
        position: sticky;
        top: 20px;
    }

    .order-panel h4 {
        font-size: 1rem;
        font-weight: 700;
        color: #222;
        margin-bottom: 18px;
    }

    .order-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-bottom: 16px;
    }

    .order-table th {
        font-weight: 600;
        color: #333;
        padding: 6px 0;
        border-bottom: 1px solid #eee;
    }

    .order-table th:last-child,
    .order-table td:last-child { text-align: right; }

    .order-table td {
        padding: 10px 0;
        color: #444;
        border-bottom: 1px solid #eee;
    }

    .order-table tr.total-row td {
        font-weight: 700;
        color: #222;
        border-bottom: none;
        padding-top: 12px;
    }

    .cod-box {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 14px 16px;
        margin-top: 6px;
        margin-bottom: 18px;
    }

    .cod-box .cod-title {
        font-size: 0.88rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cod-box .cod-desc {
        font-size: 0.8rem;
        color: #888;
        margin: 0;
        padding-left: 22px;
    }

    .btn-place-order {
        width: 100%;
        background: #e07000;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 14px;
        font-size: 0.9rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: background 0.2s;
        text-transform: uppercase;
    }

    .btn-place-order:hover { background: #c55f00; }

    /* Cancel button */
    .btn-cancel-order {
        width: 100%;
        background: #fff;
        color: #cc0000;
        border: 1px solid #cc0000;
        border-radius: 4px;
        padding: 11px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s, color 0.2s;
    }

    .btn-cancel-order:hover {
        background: #cc0000;
        color: #fff;
    }

    .notes-panel {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 24px 28px;
        background: #fff;
        margin-top: 20px;
        grid-column: 1 / 2;
    }

    .notes-panel label {
        display: block;
        font-size: 0.88rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .notes-panel textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #dce0e8;
        border-radius: 4px;
        font-size: 0.88rem;
        color: #333;
        background: #f7f8fb;
        box-sizing: border-box;
        resize: vertical;
        min-height: 90px;
        outline: none;
    }

    .alert-success {
        background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
        padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem;
    }

    .alert-danger {
        background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
        padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem;
    }

    @media (max-width: 860px) {
        .checkout-wrap { grid-template-columns: 1fr; padding: 0 16px; }
        .order-panel { position: static; }
        .notes-panel { grid-column: 1; }
    }
</style>

<?php if ($error): ?>
    <div style="max-width:1100px;margin:20px auto;padding:0 30px;">
        <div class="alert-danger"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div style="max-width:1100px;margin:40px auto;padding:0 30px;">
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    </div>
<?php else: ?>

<form method="POST" action="checkout.php?order_id=<?php echo $order_id; ?>" id="billing-form">
    <input type="hidden" name="place_order" value="1">
    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

    <div class="checkout-wrap">

        <!-- LEFT: Billing Details -->
        <div>
            <div class="billing-panel">
                <h4>Billing Details</h4>

                <div class="field-row">
                    <div class="field-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" required
                               value="<?php echo htmlspecialchars($fname); ?>">
                    </div>
                    <div class="field-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" required
                               value="<?php echo htmlspecialchars($lname); ?>">
                    </div>
                </div>

                <div class="field-group">
                    <label>Country / Region <span class="req">*</span></label>
                    <select name="country" required>
                        <option value="">Select a country / region...</option>
                        <option value="India" selected>India</option>
                    </select>
                </div>

                <div class="field-group">
                    <label>Street address <span class="req">*</span></label>
                    <input type="text" name="street_address" required
                           placeholder="House number and street name"
                           value="<?php echo htmlspecialchars($order['address'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <input type="text" name="apartment"
                           placeholder="Apartment, suite, unit, etc. (optional)">
                </div>

                <div class="field-group">
                    <label>Town / City <span class="req">*</span></label>
                    <input type="text" name="town_city" required
                           value="<?php echo htmlspecialchars($order['city'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <label>State <span class="req">*</span></label>
                    <select name="state" required>
                        <option value="">Select state...</option>
                        <?php
                        $states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh',
                            'Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala',
                            'Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland',
                            'Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura',
                            'Uttar Pradesh','Uttarakhand','West Bengal',
                            'Andaman and Nicobar Islands','Chandigarh','Dadra and Nagar Haveli and Daman and Diu',
                            'Delhi','Jammu and Kashmir','Ladakh','Lakshadweep','Puducherry'];
                        $savedState = $order['state'] ?? '';
                        foreach ($states as $s) {
                            $sel = ($s === $savedState) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($s) . "\" $sel>" . htmlspecialchars($s) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="field-group">
                    <label>PIN Code <span class="req">*</span></label>
                    <input type="text" name="pin_code" required
                           value="<?php echo htmlspecialchars($order['pin_code'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <label>Phone <span class="req">*</span></label>
                    <input type="text" name="phone" required
                           value="<?php echo htmlspecialchars($order['phone'] ?? ''); ?>">
                </div>

                <div class="field-group">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($order['email'] ?? ''); ?>">
                </div>
            </div>

            <!-- Order notes below billing -->
            <div class="notes-panel">
                <label>Order notes (optional)</label>
                <textarea name="order_notes" placeholder="Notes about your order, e.g. special notes for delivery."><?php echo htmlspecialchars($order['order_notes'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- RIGHT: Order Summary -->
        <div class="order-panel">
            <h4>Your Order</h4>

            <table class="order-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo htmlspecialchars($order['plan_name']); ?> &times; 1</td>
                        <td>₹<?php echo number_format($order['amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Subtotal</td>
                        <td>₹<?php echo number_format($order['amount'], 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Total</td>
                        <td>₹<?php echo number_format($order['amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Payment method -->
            <div class="cod-box">
                <div class="cod-title">
                    <input type="radio" name="payment_method" value="cod" checked style="accent-color:#e07000;">
                    Cash on Delivery
                </div>
                <p class="cod-desc">Pay by Credit Card / Debit Card / Net Banking / UPI</p>
            </div>

            <button type="submit" class="btn-place-order">PLACE ORDER</button>
        </div>

    </div>
</form>

<!-- Cancel Order — separate form so it cannot be accidentally submitted with Place Order -->
<?php if ($order['status'] === 'pending'): ?>
<div style="max-width:1100px;margin:-10px auto 40px;padding:0 30px;display:grid;grid-template-columns:1fr 340px;gap:30px;">
    <div></div>
    <form method="POST" action="checkout.php?order_id=<?php echo $order_id; ?>"
          onsubmit="return confirm('Are you sure you want to cancel this order?');">
        <input type="hidden" name="cancel_order" value="1">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <button type="submit" class="btn-cancel-order">✕ Cancel This Order</button>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>