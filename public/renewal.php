<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}

$page_title = "Current Membership - TeacherPlus";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// === IMPROVED QUERY ===
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.plan_name, 
        s.amount, 
        s.created_at,
        s.duration_months,
        s.status,
        sp.name as plan_display_name
    FROM subscription_orders s
    LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
    WHERE s.user_id = ?
      AND s.status = 'active'          -- Only active subscriptions
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

// Auto-sync role if needed
if ($current && $_SESSION['role'] !== 'subscriber') {
    $conn->query("UPDATE users SET role = 'subscriber' WHERE id = $user_id");
    $_SESSION['role'] = 'subscriber';
}
?>

<div class="container my-5">
    <h2 class="mb-4">Current Membership</h2>

    <div class="table-responsive mb-5">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>No.</th>
                    <th>Membership Plan</th>
                    <th>Plan Type</th>
                    <th>Starts On</th>
                    <th>Expires On</th>
                    <th>Cycle Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($current): 
                    // Calculate expiry date safely
                    $expires_on = 'Not Available';
                    if (!empty($current['created_at']) && !empty($current['duration_months'])) {
                        $start = new DateTime($current['created_at']);
                        $start->modify("+{$current['duration_months']} months");
                        $expires_on = $start->format('F j, Y');
                    }
                ?>
                <tr>
                    <td>1</td>
                    <td><?= htmlspecialchars($current['plan_display_name'] ?? $current['plan_name'] ?? 'N/A') ?></td>
                    <td>₹<?= number_format($current['amount'] ?? 0, 2) ?> - Onetime</td>
                    <td><?= date('F j, Y', strtotime($current['created_at'])) ?></td>
                    <td><?= $expires_on ?></td>
                    <td>-</td>
                    <td>
                        <a href="renew-now.php" class="btn btn-primary btn-sm">Renew</a>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-danger">
                        No active subscription found.<br>
                        <a href="subscribe-new.php" class="btn btn-warning mt-3">Subscribe Now</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-center text-muted">Showing 1 - 1 of 1 Membership</p>
</div>

<?php include '../includes/footer.php'; ?>