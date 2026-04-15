<?php
require_once 'config.php';

// Check if user is logged in (admin or subscriber)
function is_user_logged_in() {
    return isset($_SESSION['user_id']);
}




function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function is_staff() {
    return is_logged_in() && $_SESSION['role'] === 'staff';
}

function is_subscriber() {
    return is_logged_in() && $_SESSION['role'] === 'subscriber';
}

function require_role($roles) {
    if (!is_logged_in() || !in_array($_SESSION['role'], (array)$roles)) {
        header("Location: ../public/login.php");
        exit;
    }
}


// Redirect if not logged in (for user areas)
function require_login() {
    if (!is_user_logged_in()) {
        header("Location: ../public/login.php");  // We'll create this later
        exit;
    }
}

// Redirect if not admin (for admin panel)
function require_admin() {
    if (!is_admin()) {
        header("Location: ../public/login.php?error=admin_only");
        exit;
    }
}

// Get user role (or 'guest' if not logged in)
function get_user_role() {
    return is_user_logged_in() ? $_SESSION['role'] : 'guest';
}

function hasFullAccess() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'subscriber';
}

// Guest Article Limit: Max 5 free articles with alerts
function canViewArticle($article_id = null) {
    if (hasFullAccess()) {
        return true;
    }

    if (!isset($_SESSION['viewed_articles'])) {
        $_SESSION['viewed_articles'] = [];
    }

    $viewed_count = count($_SESSION['viewed_articles']);

    if ($article_id !== null && !in_array($article_id, $_SESSION['viewed_articles'])) {

        if ($viewed_count >= 5) {
            return false; // Block further articles
        }

        // 4th article → "one more left"
        if ($viewed_count === 3) {
            echo "<script>alert('You have one more free article left.');</script>";
        }

        // 5th article → "last free article"
        if ($viewed_count === 4) {
            echo "<script>alert('This is your last free article for the month.');</script>";
        }

        $_SESSION['viewed_articles'][] = $article_id;
    }

    return $viewed_count < 5;
}

?>
