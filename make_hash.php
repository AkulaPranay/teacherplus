<?php
$plain_password = 'admin123';           // ← change this to whatever you want
echo password_hash($plain_password, PASSWORD_DEFAULT);
?>