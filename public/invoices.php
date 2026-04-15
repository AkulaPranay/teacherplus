<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}

$page_title = "Transactions - TeacherPlus";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, plan_name, amount, payment_method, status, created_at 
    FROM subscription_orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();
?>

<div class="container my-5">
    <h2 class="mb-4">Transactions</h2>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Transaction ID</th>
                    <th>Invoice ID</th>
                    <th>Plan</th>
                    <th>Payment Gateway</th>
                    <th>Payment Type</th>
                    <th>Transaction Status</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions->num_rows > 0): ?>
                    <?php while($row = $transactions->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td>TP<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['payment_method'] ?? 'Manual'); ?></td>
                        <td>Onetime</td>
                        <td>
                            <span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>₹<?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo date('F j, Y g:i A', strtotime($row['created_at'])); ?></td>
                        <td>
                            <a href="#" class="btn btn-primary btn-sm">View Invoice</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center py-4">No transactions found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-center text-muted mt-3">Showing 1 - 1 of 1 transactions</p>
</div>

<?php include '../includes/footer.php'; ?>