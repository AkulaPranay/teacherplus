<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Database settings - CHANGE THESE!
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // usually 'root' for local
define('DB_PASS', '');           // empty for local XAMPP, or your password
define('DB_NAME', 'teacherplus_db');

// Connect
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
// In config.php or a function
function get_staff_emails($conn) {
    $emails = [];
    $result = $conn->query("SELECT email FROM users WHERE role = 'staff'");
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }
    return $emails;
}
define('BASE_URL', '/teacherplus/public/');
date_default_timezone_set('UTC');
?>

