<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| DATABASE CONFIG (Railway MySQL)
|--------------------------------------------------------------------------
*/

$DB_HOST = getenv('MYSQLHOST') ?: 'monorail.proxy.rlwy.net';
$DB_USER = getenv('MYSQLUSER') ?: 'root';
$DB_PASS = getenv('MYSQLPASSWORD') ?: 'JCGCmYBJsQbfPKLnRQVTkMmvFWoZWlsW'; // 🔴 replace this
$DB_NAME = getenv('MYSQLDATABASE') ?: 'railway';
$DB_PORT = getenv('MYSQLPORT') ?: 44527;

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

// Get all staff emails
function get_staff_emails($conn) {
    $emails = [];
    $result = $conn->query("SELECT email FROM users WHERE role = 'staff'");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['email'];
        }
    }
    
    return $emails;
}

/*
|--------------------------------------------------------------------------
| BASE SETTINGS
|--------------------------------------------------------------------------
*/

// Adjust if needed
define('BASE_URL', '/');

// Timezone
date_default_timezone_set('UTC');

?>
