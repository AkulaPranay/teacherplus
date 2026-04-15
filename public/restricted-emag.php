<?php
require '../includes/config.php';
$page_title = "E-Magazine Access Restricted - TeacherPlus";
include '../includes/header.php';
?>

<div style="background-color: #f0f0f0; padding: 60px 20px; text-align: center;">
    <div style="max-width: 650px; margin: 0 auto;">
        <h2 style="color: #cc0000; font-size: 2.2rem; font-weight: 700; margin-bottom: 20px; line-height: 1.3;">
            If you are already a subscriber, please login to download
        </h2>

        <p style="font-size: 1rem; color: #333; margin-bottom: 30px;">
            Subscribe now to receive access to the Teacher Plus e-magazine.
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