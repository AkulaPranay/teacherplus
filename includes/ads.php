<?php
function getAds($conn, $position) {
    $stmt = $conn->prepare("SELECT * FROM advertisements 
                            WHERE status='active' AND position=?");
    $stmt->bind_param("s", $position);
    $stmt->execute();
    return $stmt->get_result();
}