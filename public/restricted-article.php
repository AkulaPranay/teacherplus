<?php
require '../includes/config.php';
$page_title = "Article Limit Reached - TeacherPlus";
include '../includes/header.php';
?>

<div style="background-color: #f0f0f0; padding: 60px 20px; text-align: center;">
    <div style="max-width: 650px; margin: 0 auto;">
        <h2 style="color: #cc0000; font-size: 2.2rem; font-weight: 700; margin-bottom: 20px; line-height: 1.3;">
            You reached the limit of free articles for the month
        </h2>

        <p style="font-size: 1rem; color: #333; margin-bottom: 30px;">
            Subscribe now for unlimited access to every Teacher Plus article.
        </p>

        <a href="subscribe-new.php" style="
            display: inline-block;
            background-color: #cc0000;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 600;
            padding: 12px 36px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        ">Subscribe</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>