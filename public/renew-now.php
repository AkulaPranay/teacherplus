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

    // Fetch plan details
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
        $erow = $existing->get_result()->fetch_assoc();

        if ($erow) {
            $order_id = $erow['id'];
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

// ── Fetch plans and current membership for display ──
$plans_result = $conn->query("SELECT id, name, price, duration_months FROM subscription_plans ORDER BY price ASC");
$plans_arr = [];
while ($row = $plans_result->fetch_assoc()) {
    $plans_arr[] = $row;
}

// Fetch current plan name
$cur_stmt = $conn->prepare("
    SELECT so.plan_name FROM subscription_orders so
    WHERE so.user_id = ? AND so.status IN ('pending','active','approved')
    ORDER BY so.created_at DESC LIMIT 1
");
$cur_stmt->bind_param("i", $user_id);
$cur_stmt->execute();
$cur = $cur_stmt->get_result()->fetch_assoc();
$current_plan = $cur['plan_name'] ?? '—';

$page_title = "Renew Subscription - TeacherPlus";
include '../includes/header.php';

// Default selected = first plan
$default = $plans_arr[0] ?? ['id' => 0, 'name' => '—', 'price' => 0];
?>

<style>
    body { background: #fff; }

    .renew-wrapper {
        max-width: 600px;
        margin: 40px auto 80px;
        padding: 0 20px;
    }

    .current-plan-label {
        text-align: center;
        font-size: 0.88rem;
        color: #444;
        margin-bottom: 16px;
    }
    .current-plan-label span { font-weight: 700; }

    /* Plans grid */
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
    }

    .plan-card.selected { border-color: #e07000; }

    .plan-card-top {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }

    .plan-radio-icon {
        width: 18px;
        height: 18px;
        border: 2px solid #e07000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .plan-name { font-size: 0.9rem; font-weight: 600; color: #222; }
    .plan-name.sel { color: #e07000; }
    .plan-price { font-size: 1rem; font-weight: 600; color: #222; padding-left: 26px; }
    .plan-price.sel { color: #e07000; }

    .plan-card input[type="radio"] { display: none; }

    .divider { border: none; border-top: 1px solid #eee; margin: 4px 0 20px; }

    /* Payment mode */
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
    }

    .pay-radio-icon {
        width: 18px;
        height: 18px;
        border: 2px solid #e07000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .pay-radio-icon::after {
        content: '';
        width: 10px;
        height: 10px;
        background: #e07000;
        border-radius: 50%;
    }

    .payment-option label { font-size: 0.88rem; color: #333; font-weight: 500; }

    /* Payment summary */
    .payment-summary {
        border-top: 1px solid #eee;
        padding-top: 16px;
        text-align: center;
        margin-bottom: 24px;
    }

    .summary-title { font-size: 0.88rem; color: #555; margin-bottom: 6px; }
    .summary-line { font-size: 0.85rem; color: #444; margin-bottom: 3px; }
    .summary-line strong { color: #111; }

    /* Submit */
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

<div class="renew-wrapper">
    <div class="current-plan-label">
        Your Current Membership: <span id="selected-plan-name"><?php echo htmlspecialchars($current_plan); ?></span>
    </div>

    <form method="POST" action="" id="renew-form">

        <div class="plans-grid">
            <?php foreach ($plans_arr as $idx => $plan):
                $isSel = ($idx === 0);
            ?>
            <label class="plan-card <?php echo $isSel ? 'selected' : ''; ?>"
                   onclick="selectPlan(this, <?php echo $plan['id']; ?>, '<?php echo addslashes(htmlspecialchars($plan['name'])); ?>', <?php echo $plan['price']; ?>)">
                <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" <?php echo $isSel ? 'checked' : ''; ?>>
                <div class="plan-card-top">
                    <div class="plan-radio-icon" id="radio-<?php echo $plan['id']; ?>">
                        <?php if ($isSel): ?>
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M2 6l3 3 5-5" stroke="#e07000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <span class="plan-name <?php echo $isSel ? 'sel' : ''; ?>" id="name-<?php echo $plan['id']; ?>">
                        <?php echo htmlspecialchars($plan['name']); ?>
                    </span>
                </div>
                <div class="plan-price <?php echo $isSel ? 'sel' : ''; ?>" id="price-<?php echo $plan['id']; ?>">
                    ₹<?php echo number_format($plan['price'], 2); ?>
                </div>
            </label>
            <?php endforeach; ?>
        </div>

        <hr class="divider">

        <div class="payment-section-title">Select your payment mode</div>
        <div class="payment-option">
            <div class="pay-radio-icon"></div>
            <label>Payment Gateway (Net Banking, Credit/ Debit Card, UPI)</label>
        </div>

        <hr class="divider">

        <div class="payment-summary">
            <div class="summary-title">Payment Summary</div>
            <div class="summary-line">
                Your currently selected plan : <strong id="summary-plan"><?php echo htmlspecialchars($default['name']); ?></strong>,
                Plan Amount : <strong id="summary-amount">₹<?php echo number_format($default['price'], 2); ?></strong>
            </div>
            <div class="summary-line">
                Payable Amount: <strong id="summary-payable">₹<?php echo number_format($default['price'], 2); ?></strong>
            </div>
        </div>

        <button type="submit" class="btn-submit">Submit</button>
    </form>
</div>

<script>
function selectPlan(el, id, name, price) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.plan-radio-icon').forEach(r => { r.classList.remove('checked'); r.innerHTML = ''; });
    document.querySelectorAll('.plan-name').forEach(n => n.classList.remove('sel'));
    document.querySelectorAll('.plan-price').forEach(p => p.classList.remove('sel'));

    el.classList.add('selected');
    const radio = document.getElementById('radio-' + id);
    radio.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#e07000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.getElementById('name-' + id).classList.add('sel');
    document.getElementById('price-' + id).classList.add('sel');

    const fmt = '₹' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('selected-plan-name').textContent = name;
    document.getElementById('summary-plan').textContent    = name;
    document.getElementById('summary-amount').textContent  = fmt;
    document.getElementById('summary-payable').textContent = fmt;

    el.querySelector('input[type="radio"]').checked = true;
}
</script>

<?php include '../includes/footer.php'; ?>