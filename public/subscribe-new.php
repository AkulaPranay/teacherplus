<?php
require '../includes/config.php';

// ── Handle POST before ANY output (so header() redirect works) ──
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']   ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $plan_id    = intval($_POST['plan_id']  ?? 0);

    if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || $plan_id <= 0) {
        $error = "Please fill all required fields.";
    } else {
        // Check if username or email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Username or email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $full_name = $first_name . ' ' . $last_name;

            // 1. Create user as subscriber
            $stmt = $conn->prepare("
                INSERT INTO users (username, full_name, email, phone, password, role, created_at)
                VALUES (?, ?, ?, ?, ?, 'subscriber', NOW())
            ");
            $stmt->bind_param("sssss", $username, $full_name, $email, $phone, $password_hash);

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;

                // 2. Fetch selected plan details
                $plan_stmt = $conn->prepare("SELECT name, price, duration_months FROM subscription_plans WHERE id = ?");
                $plan_stmt->bind_param("i", $plan_id);
                $plan_stmt->execute();
                $plan = $plan_stmt->get_result()->fetch_assoc();

                // 3. Create subscription order
                $insert_order = $conn->prepare("
                    INSERT INTO subscription_orders
                        (user_id, username, first_name, last_name, email, phone, plan_id, plan_name, amount, payment_method, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'cod', 'pending', NOW())
                ");
                $insert_order->bind_param("isssssiss",
                    $new_user_id, $username, $first_name, $last_name, $email, $phone,
                    $plan_id, $plan['name'], $plan['price']
                );

                if ($insert_order->execute()) {
                    // Auto-login
                    $_SESSION['user_id']   = $new_user_id;
                    $_SESSION['username']  = $username;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role']      = 'subscriber';
                    // Redirect to checkout — no HTML sent yet so this is safe
                    $order_id = $conn->insert_id;
                    header("Location: checkout.php?order_id=" . $order_id);
                    exit;
                } else {
                    $error = "Failed to create subscription order. Error: " . $conn->error;
                }
            } else {
                $error = "Failed to create account. Error: " . $conn->error;
            }
        }
    }
}

// ── Only include header (which outputs HTML) after POST is handled ──
$page_title = "Subscribe - TeacherPlus";
include '../includes/header.php';

// Fetch plans
$plans_result = $conn->query("SELECT id, name, price, duration_months FROM subscription_plans ORDER BY price ASC");
$plans_arr = [];
while ($row = $plans_result->fetch_assoc()) {
    $plans_arr[] = $row;
}
?>

<style>
/* ── Page layout ── */
.subscribe-page {
    width: 100%;
    margin: 40px 0 80px;
    padding: 0 40px;
    display: grid;
    grid-template-columns: 1fr 420px;
    gap: 60px;
    align-items: start;
    box-sizing: border-box;
}

/* ── Left column ── */
.left-col h2 {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 14px;
    color: #111;
}
.left-col p, .left-col li {
    font-size: 0.91rem;
    color: #333;
    line-height: 1.78;
}
.left-col ol {
    padding-left: 18px;
    margin-bottom: 16px;
}
.neft-section, .cheque-section, .note-section {
    margin-top: 28px;
}
.neft-section h3, .cheque-section h3 {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: #111;
}

/* ── Right column ── */
.right-col { position: sticky; top: 20px; }
.login-note {
    font-size: 0.87rem;
    color: #333;
    margin-bottom: 4px;
}
.login-note a { color: #0d6efd; text-decoration: none; }

/* ── Alert boxes ── */
.alert-box {
    border-radius: 4px;
    padding: 10px 14px;
    font-size: 0.87rem;
    margin-bottom: 12px;
}
.alert-box.success {
    background: #d1fae5;
    border: 1px solid #6ee7b7;
    color: #065f46;
}
.alert-box.error {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

/* ── Signup box ── */
.signup-box {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 22px 18px;
    margin-top: 12px;
    background: #fff;
}
.signup-box h4 {
    text-align: center;
    font-size: 0.98rem;
    font-weight: 600;
    margin-bottom: 14px;
    color: #111;
}
.signup-box .form-control {
    font-size: 0.87rem;
    border-radius: 3px;
    margin-bottom: 9px;
    padding: 8px 11px;
    border: 1px solid #ccc;
    width: 100%;
    box-sizing: border-box;
}
.signup-box .form-control::placeholder { color: #aaa; }
.password-wrap { position: relative; }
.password-wrap .toggle-pw {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #888;
    font-size: 1rem;
}
.strength-label {
    font-size: 0.76rem;
    text-align: right;
    color: #888;
    margin-top: -5px;
    margin-bottom: 12px;
}

/* ── Plans grid ── */
.plans-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px;
    margin-top: 14px;
}
.plan-option {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px 12px;
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    transition: border-color 0.2s;
}
.plan-option input[type="radio"] { margin-top: 2px; accent-color: #e07000; }
.plan-option.selected { border-color: #e07000; background: #fffaf5; }
.plan-label { font-size: 0.85rem; font-weight: 600; color: #333; }
.plan-price { font-size: 0.85rem; color: #555; }
.plan-price.highlight { color: #e07000; font-weight: 700; }

/* ── Payment mode ── */
.payment-mode-section {
    margin-top: 18px;
    border-top: 1px solid #eee;
    padding-top: 14px;
    text-align: center;
}
.payment-mode-section > p {
    font-size: 0.86rem;
    color: #555;
    margin-bottom: 9px;
}
.payment-mode-option {
    border: 1px solid #e07000;
    border-radius: 4px;
    padding: 11px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff;
}
.payment-mode-option input[type="radio"] { accent-color: #e07000; }
.payment-mode-option label {
    font-size: 0.87rem;
    font-weight: 500;
    color: #333;
    cursor: pointer;
    text-align: left;
}

/* ── Payment summary ── */
.payment-summary {
    margin-top: 16px;
    border-top: 1px solid #eee;
    padding-top: 12px;
    text-align: center;
}
.payment-summary > p { font-size: 0.81rem; color: #555; margin-bottom: 3px; }
.payment-summary > p span { font-weight: 700; color: #111; }

/* ── Submit ── */
.btn-submit-sub {
    width: 100%;
    background: #1a56e8;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    margin-top: 16px;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-submit-sub:hover { background: #1440c0; }

@media (max-width: 900px) {
    .subscribe-page {
        grid-template-columns: 1fr;
        padding: 0 20px;
    }
    .right-col { position: static; }
}
</style>

<div class="subscribe-page">

    <!-- LEFT COLUMN -->
    <div class="left-col">
        <h2>How your digital subscription will work</h2>
        <p>Dear Subscriber,</p>
        <p>
            We welcome you to the exciting new digital world of <strong>Teacher Plus</strong>.
            We have so far been used to holding a 60-page hard copy of the magazine in our hands to read.
            But in this digital version, things are going to be a little different. As we enter this new
            phase in our journey with you, here are some things for you to note.
        </p>
        <ol>
            <li>When you subscribe to the magazine, you will receive an activation email within a day.</li>
            <li>Once you receive the activation email, you may use the login credentials you created while
                registering on the website to start reading the articles.</li>
            <li>In its new digital avatar, <strong>Teacher Plus</strong> will release 4/5 new articles every
                week starting from the first week of every month.</li>
            <li>You will receive weekly newsletters informing you of the articles released that week with
                links that will take you to the articles on our website.</li>
            <li>At the end of the month, you will have the entire magazine ready for download in our
                E-magazine section.</li>
        </ol>
        <p>
            And this is not all. With your new digital subscription you can expect a lot more from
            <strong>Teacher Plus</strong>. The entire <strong>Teacher Plus</strong> archive, with a rich
            collection of articles from the past several years, is now available to subscribers at the click
            of a button. We also have plans to launch a podcast, and offer more multimedia content.
            Additionally, our newsletters will point you to a range of relevant and interesting materials
            available online that can help you in your classroom.
        </p>
        <p>
            So take some time to explore the site and re-acquaint yourself with <strong>Teacher Plus</strong>
            in this new format. Do let us know what you think!
        </p>
        <p>Thank you,<br><strong>Teacher Plus</strong></p>

        <!-- NEFT -->
        <div class="neft-section">
            <h3>Subscribe by NEFT</h3>
            <p>
                <strong>A/c Name:</strong> Teacher Plus<br>
                <strong>A/c Number:</strong> 62167386094<br>
                <strong>Bank:</strong> State Bank of India<br>
                <strong>IFSC Code:</strong> SBIN0020766
            </p>
            <p>
                We request you to send us the transaction details and your complete postal address including
                pin code, phone no. and email id to
                <a href="mailto:circulation@teacherplus.org">circulation@teacherplus.org</a>.
                Please note that if we do not receive this information within two days of your transaction,
                we will not be able to process your subscription. And amount once paid will not be refunded.
            </p>
        </div>

        <!-- Cheque / DD -->
        <div class="cheque-section">
            <h3>Cheque/Demand Draft option</h3>
            <p>
                Please write your cheque/DD in the name of Teacher Plus and DD should be payable at
                Hyderabad. Send your cheques/DDs to:<br>
                Teacher Plus,<br>
                A15, Vikrampuri<br>
                Secunderabad<br>
                Telangana – 500009, India
            </p>
        </div>

        <!-- Note -->
        <div class="note-section">
            <p>
                <strong>Note:</strong> When you subscribe by NEFT or Demand Draft, you will receive a reset
                password email for activating your subscription after your subscription has been processed
                at our end.
            </p>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="right-col">
        <p class="login-note">If you are already a subscriber, please login to <a href="/renew">renew</a> your subscription.</p>
        <p class="login-note">If you are new to Teacher Plus,</p>

        <?php if ($success): ?>
            <div class="alert-box success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-box error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="signup-box">
            <h4>Please Signup</h4>
            <form method="POST" action="">

                <input type="text" class="form-control" name="username" placeholder="* Username" required
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">

                <input type="text" class="form-control" name="first_name" placeholder="* First Name" required
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">

                <input type="text" class="form-control" name="last_name" placeholder="* Last Name" required
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">

                <input type="tel" class="form-control" name="phone" placeholder="* Phone Number" required
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">

                <input type="email" class="form-control" name="email" placeholder="* Email Address" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

                <div class="password-wrap">
                    <input type="password" class="form-control" name="password" id="pw-field"
                           placeholder="* Password" required>
                    <span class="toggle-pw" onclick="togglePw()">👁</span>
                </div>
                <div class="strength-label" id="strength-label">Strength: Very Weak</div>

                <!-- Plans selection -->
                <div class="plans-grid">
                    <?php
                    $first = true;
                    foreach ($plans_arr as $plan):
                        $checked   = $first ? 'checked' : '';
                        $selected  = $first ? 'selected' : '';
                        $highlight = $first ? 'highlight' : '';
                    ?>
                        <label class="plan-option <?php echo $selected; ?>"
                               onclick="selectPlan(this, <?php echo $plan['id']; ?>, '<?php echo addslashes(htmlspecialchars($plan['name'])); ?>', <?php echo $plan['price']; ?>)">
                            <input type="radio" name="plan_id" value="<?php echo $plan['id']; ?>" <?php echo $checked; ?>>
                            <div>
                                <div class="plan-label"><?php echo htmlspecialchars($plan['name']); ?></div>
                                <div class="plan-price <?php echo $highlight; ?>">
                                    ₹<?php echo number_format($plan['price'], 0); ?>.00
                                </div>
                            </div>
                        </label>
                    <?php $first = false; endforeach; ?>
                </div>

                <!-- Payment mode -->
                <div class="payment-mode-section">
                    <p>Select your payment mode</p>
                    <div class="payment-mode-option">
                        <input type="radio" name="payment_method" id="cod" value="cod" checked>
                        <label for="cod">Cash on Delivery / Bank Transfer (NEFT / Cheque / DD)</label>
                    </div>
                </div>

                <!-- Payment summary -->
                <div class="payment-summary">
                    <p>Payment Summary</p>
                    <p>
                        Your currently selected plan:
                        <span id="summary-plan"><?php echo htmlspecialchars($plans_arr[0]['name'] ?? '—'); ?></span>,
                        Plan Amount:
                        <span id="summary-amount">₹<?php echo number_format($plans_arr[0]['price'] ?? 0, 2); ?></span>
                    </p>
                    <p>
                        Payable Amount:
                        <span id="summary-payable">₹<?php echo number_format($plans_arr[0]['price'] ?? 0, 2); ?></span>
                    </p>
                </div>

                <button type="submit" class="btn-submit-sub">Submit</button>
            </form>
        </div>
    </div>

</div>

<script>
function togglePw() {
    const field = document.getElementById('pw-field');
    const icon  = field.nextElementSibling;
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = '🙈';
    } else {
        field.type = 'password';
        icon.textContent = '👁';
    }
}

document.getElementById('pw-field').addEventListener('input', function () {
    const v = this.value;
    let s = 'Very Weak';
    if (v.length >= 12 && /[A-Z]/.test(v) && /[0-9]/.test(v) && /[^a-zA-Z0-9]/.test(v)) s = 'Strong';
    else if (v.length >= 8 && /[A-Z]/.test(v) && /[0-9]/.test(v)) s = 'Medium';
    else if (v.length >= 6) s = 'Weak';
    document.getElementById('strength-label').textContent = 'Strength: ' + s;
});

function selectPlan(el, id, name, price) {
    document.querySelectorAll('.plan-option').forEach(o => {
        o.classList.remove('selected');
        const pp = o.querySelector('.plan-price');
        if (pp) pp.classList.remove('highlight');
    });
    el.classList.add('selected');
    const thisPP = el.querySelector('.plan-price');
    if (thisPP) thisPP.classList.add('highlight');

    const fmt = '₹' + parseFloat(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('summary-plan').textContent    = name;
    document.getElementById('summary-amount').textContent  = fmt;
    document.getElementById('summary-payable').textContent = fmt;
}
</script>

<?php include '../includes/footer.php'; ?>