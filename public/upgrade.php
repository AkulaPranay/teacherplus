<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Handle POST before any output so header() works ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['plan_id'])) {
    $plan_id = intval($_POST['plan_id']);

    $ps = $conn->prepare("SELECT id, name, price, duration_months FROM subscription_plans WHERE id = ?");
    $ps->bind_param("i", $plan_id);
    $ps->execute();
    $plan = $ps->get_result()->fetch_assoc();

    if ($plan) {
        // Reuse existing pending order for same plan, or create new one
        $existing = $conn->prepare("
            SELECT id FROM subscription_orders
            WHERE user_id = ? AND plan_id = ? AND status = 'pending'
            ORDER BY created_at DESC LIMIT 1
        ");
        $existing->bind_param("ii", $user_id, $plan['id']);
        $existing->execute();
        $row = $existing->get_result()->fetch_assoc();

        if ($row) {
            $order_id = $row['id'];
        } else {
            $ins = $conn->prepare("
                INSERT INTO subscription_orders
                    (user_id, username, first_name, last_name, email, phone, plan_id, plan_name, amount, payment_method, status, created_at)
                VALUES (?, ?, '', '', '', '', ?, ?, ?, 'cod', 'pending', NOW())
            ");
            $ins->bind_param("issis",
                $user_id,
                $_SESSION['username'],
                $plan['id'],
                $plan['name'],
                $plan['price']
            );
            $ins->execute();
            $order_id = $conn->insert_id;
        }
        header("Location: checkout.php?order_id=" . $order_id);
        exit;
    }
}
// Fetch all plans
$plans_result = $conn->query("SELECT id, name, price, duration_months FROM subscription_plans ORDER BY price ASC");
$plans_arr = [];
while ($row = $plans_result->fetch_assoc()) {
    $plans_arr[] = $row;
}

// Fetch current subscriptions — use COALESCE to get proper plan name from subscription_plans table
$sub_stmt = $conn->prepare("
    SELECT so.id, so.amount, so.created_at, so.status,
           COALESCE(sp.name, so.plan_name) AS display_plan_name,
           COALESCE(sp.duration_months, 1) AS duration_months,
           DATE_ADD(so.created_at, INTERVAL COALESCE(sp.duration_months, 1) MONTH) AS end_date
    FROM subscription_orders so
    LEFT JOIN subscription_plans sp ON sp.id = so.plan_id
    WHERE so.user_id = ? AND so.status IN ('pending','active','approved')
    ORDER BY so.created_at DESC
");
$sub_stmt->bind_param("i", $user_id);
$sub_stmt->execute();
$subscriptions = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Current plan name for display
$current_plan = $subscriptions[0]['display_plan_name'] ?? 'None';

$page_title = "Upgrade / Downgrade - TeacherPlus";
include '../includes/header.php';
?>

<style>
    body { background: #fff; }

    .renewal-wrapper {
        max-width: 900px;
        margin: 40px auto 80px;
        padding: 0 20px;
    }

    /* ── Current Membership Table ── */
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #222;
        margin-bottom: 12px;
    }

    .membership-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #ddd;
        font-size: 0.85rem;
        margin-bottom: 8px;
    }

    .membership-table th {
        background: #f9f9f9;
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 1px solid #ddd;
        border-right: 1px solid #ddd;
        white-space: nowrap;
    }

    .membership-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #eee;
        border-right: 1px solid #ddd;
        color: #444;
        vertical-align: middle;
    }

    .membership-table tr:last-child td { border-bottom: none; }

    .btn-renew {
        display: block;
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        margin-bottom: 4px;
        text-align: center;
    }

    .btn-cancel {
        display: block;
        background: #e74c3c;
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
    }

    .pagination-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        font-size: 0.8rem;
        color: #666;
        margin-top: 8px;
        margin-bottom: 32px;
    }

    .page-btn {
        width: 26px;
        height: 26px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: #333;
    }

    .page-btn.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }

    /* ── Current plan label ── */
    .current-plan-label {
        text-align: center;
        font-size: 0.88rem;
        color: #444;
        margin-bottom: 16px;
    }
    .current-plan-label span { font-weight: 700; }

    /* ── Plans grid ── */
    .plans-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 28px;
    }

    .plan-card {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 20px 16px;
        cursor: pointer;
        transition: border-color 0.2s;
        position: relative;
    }

    .plan-card.selected { border-color: #e07000; }

    .plan-card-top {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }

    .plan-radio {
        width: 18px;
        height: 18px;
        border: 2px solid #e07000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .plan-radio.checked { border-color: #e07000; position: relative; }

    .plan-name { font-size: 0.9rem; font-weight: 600; color: #222; }
    .plan-name.selected-name { color: #e07000; }
    .plan-price { font-size: 1rem; font-weight: 600; color: #222; padding-left: 26px; }
    .plan-price.selected-price { color: #e07000; }
    .plan-card input[type="radio"] { display: none; }

    .divider { border: none; border-top: 1px solid #eee; margin: 4px 0 20px; }

    .payment-section-title {
        text-align: center;
        font-size: 0.9rem;
        color: #444;
        margin-bottom: 12px;
    }

    .payment-option {
        border: 1px solid #e07000;
        border-radius: 6px;
        padding: 13px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        cursor: pointer;
    }

    .payment-option .pay-radio {
        width: 18px;
        height: 18px;
        border: 2px solid #e07000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .payment-option .pay-radio::after {
        content: '';
        width: 10px;
        height: 10px;
        background: #e07000;
        border-radius: 50%;
    }

    .payment-option label { font-size: 0.88rem; color: #333; font-weight: 500; cursor: pointer; }

    .payment-summary {
        border-top: 1px solid #eee;
        padding-top: 16px;
        text-align: center;
        margin-bottom: 24px;
    }

    .payment-summary .summary-title { font-size: 0.88rem; color: #555; margin-bottom: 6px; }
    .payment-summary .summary-line { font-size: 0.85rem; color: #444; margin-bottom: 3px; }
    .payment-summary .summary-line strong { color: #111; }

    .btn-submit {
        width: 100%;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 14px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-submit:hover { background: #1d4ed8; }
</style>

<div class="renewal-wrapper">

    <!-- Flash messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'cancelled'): ?>
            <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:0.875rem;">
                Subscription cancelled successfully.
            </div>
        <?php elseif ($_GET['msg'] === 'error'): ?>
            <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-size:0.875rem;">
                Could not cancel subscription. It may already be cancelled or not found.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Current Membership -->
    <div class="section-title">Current Membership</div>
    <table class="membership-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Membership Plan</th>
                <th>Recurring Profile</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Renewal On</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($subscriptions)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; color:#888;">No active membership found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($subscriptions as $i => $sub): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($sub['display_plan_name']); ?></td>
                    <td>₹<?php echo number_format($sub['amount'], 0); ?>.00 - Onetime</td>
                    <td><?php echo date('F j, Y', strtotime($sub['created_at'])); ?></td>
                    <td><?php echo !empty($sub['end_date']) ? date('F j, Y', strtotime($sub['end_date'])) : '-'; ?></td>
                    <td>-</td>
                    <td style="white-space:nowrap;">
                        <a href="renew-now.php" class="btn-renew">Renew</a>
                        <a href="cancel-subscription.php?id=<?php echo $sub['id']; ?>"
                           class="btn-cancel"
                           onclick="return confirm('Cancel this subscription?')">Cancel</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination-row">
        Showing 1 - <?php echo count($subscriptions); ?> of <?php echo count($subscriptions); ?> Membership
        <button class="page-btn">&#8249;</button>
        <button class="page-btn active">1</button>
        <button class="page-btn">&#8250;</button>
    </div>

    <!-- Plan selector form -->
    <form method="POST" action="" id="renewal-form">

        <div class="current-plan-label">
            Your Current Membership: <span id="selected-plan-name"><?php echo htmlspecialchars($current_plan); ?></span>
        </div>

        <div class="plans-grid">
            <?php foreach ($plans_arr as $idx => $plan): ?>
                <?php $isSelected = ($plan['name'] === $current_plan); ?>
                <label class="plan-card <?php echo $isSelected ? 'selected' : ''; ?>"
                       onclick="selectPlan(this, <?php echo $plan['id']; ?>, '<?php echo addslashes(htmlspecialchars($plan['name'])); ?>', <?php echo $plan['price']; ?>)">
                    <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                    <div class="plan-card-top">
                        <div class="plan-radio <?php echo $isSelected ? 'checked' : ''; ?>" id="radio-<?php echo $plan['id']; ?>">
                            <?php if ($isSelected): ?>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M2 6l3 3 5-5" stroke="#e07000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <span class="plan-name <?php echo $isSelected ? 'selected-name' : ''; ?>" id="name-<?php echo $plan['id']; ?>">
                            <?php echo htmlspecialchars($plan['name']); ?>
                        </span>
                    </div>
                    <div class="plan-price <?php echo $isSelected ? 'selected-price' : ''; ?>" id="price-<?php echo $plan['id']; ?>">
                        ₹<?php echo number_format($plan['price'], 2); ?>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <hr class="divider">

        <div class="payment-section-title">Select your payment mode</div>
        <div class="payment-option">
            <div class="pay-radio"></div>
            <label>Payment Gateway (Net Banking, Credit/ Debit Card, UPI)</label>
        </div>

        <hr class="divider">

        <div class="payment-summary">
            <div class="summary-title">Payment Summary</div>
            <div class="summary-line">
                Your currently selected plan : <strong id="summary-plan"><?php echo htmlspecialchars($current_plan); ?></strong>,
                Plan Amount : <strong id="summary-amount">₹<?php
                    foreach ($plans_arr as $p) {
                        if ($p['name'] === $current_plan) { echo number_format($p['price'], 2); break; }
                    }
                    if (!in_array($current_plan, array_column($plans_arr, 'name'))) echo number_format($plans_arr[0]['price'] ?? 0, 2);
                ?></strong>
            </div>
            <div class="summary-line">
                Payable Amount: <strong id="summary-payable">₹<?php
                    foreach ($plans_arr as $p) {
                        if ($p['name'] === $current_plan) { echo number_format($p['price'], 2); break; }
                    }
                    if (!in_array($current_plan, array_column($plans_arr, 'name'))) echo number_format($plans_arr[0]['price'] ?? 0, 2);
                ?></strong>
            </div>
        </div>

        <button type="submit" class="btn-submit">Submit</button>
    </form>

</div>

<script>
function selectPlan(el, id, name, price) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.plan-radio').forEach(r => { r.classList.remove('checked'); r.innerHTML = ''; });
    document.querySelectorAll('.plan-name').forEach(n => n.classList.remove('selected-name'));
    document.querySelectorAll('.plan-price').forEach(p => p.classList.remove('selected-price'));

    el.classList.add('selected');
    const radio = document.getElementById('radio-' + id);
    radio.classList.add('checked');
    radio.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#e07000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.getElementById('name-' + id).classList.add('selected-name');
    document.getElementById('price-' + id).classList.add('selected-price');

    const fmt = '₹' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('selected-plan-name').textContent = name;
    document.getElementById('summary-plan').textContent    = name;
    document.getElementById('summary-amount').textContent  = fmt;
    document.getElementById('summary-payable').textContent = fmt;
    el.querySelector('input[type="radio"]').checked = true;
}
</script>

<?php include '../includes/footer.php'; ?>