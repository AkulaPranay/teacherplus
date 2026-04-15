<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'subscriber') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    header("Location: upgrade.php");
    exit;
}

// Only allow cancelling own orders that are pending or active
$stmt = $conn->prepare("
    UPDATE subscription_orders
    SET status = 'cancelled'
    WHERE id = ? AND user_id = ? AND status IN ('pending', 'active', 'approved')
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    header("Location: upgrade.php?msg=cancelled");
} else {
    header("Location: upgrade.php?msg=error");
}
exit;