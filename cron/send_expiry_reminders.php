<?php
/**
 * cron/send_expiry_reminders.php
 *
 * Run this script daily via XAMPP Task Scheduler or a cron job:
 *   Windows Task Scheduler: php C:\xampp1\htdocs\teacherplus\cron\send_expiry_reminders.php
 *   Linux cron (daily at 8 AM): 0 8 * * * php /var/www/teacherplus/cron/send_expiry_reminders.php
 *
 * Sends reminders at:  7 days before expiry  AND  1 day before expiry.
 */

// Run from any working directory
chdir(dirname(__DIR__));

require 'includes/config.php';
require 'includes/mailer.php';

$thresholds = [7, 1]; // days before expiry to send reminder

$sent = 0;
$failed = 0;

foreach ($thresholds as $days) {
    $target_date = date('Y-m-d', strtotime("+{$days} days"));

    // Find users whose subscription expires exactly on target_date
    $stmt = $conn->prepare("
        SELECT id, email, full_name, subscription_expiry
        FROM users
        WHERE role = 'subscriber'
          AND subscription_expiry = ?
    ");
    $stmt->bind_param('s', $target_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($user = $result->fetch_assoc()) {
        $ok = tp_send_expiry_reminder(
            $user['email'],
            $user['full_name'] ?: $user['email'],
            $user['subscription_expiry'],
            $days
        );

        if ($ok) {
            $sent++;
            echo "[OK]   {$user['email']} — expires {$user['subscription_expiry']} ({$days}d reminder)\n";
        } else {
            $failed++;
            echo "[FAIL] {$user['email']} — email could not be sent\n";
        }
    }
    $stmt->close();
}

echo "\nDone. Sent: {$sent}  Failed: {$failed}\n";