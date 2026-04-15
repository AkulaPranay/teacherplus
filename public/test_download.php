<?php
$save_dir = __DIR__ . '/uploads/images/';
echo "Save path: " . $save_dir . "<br>";
echo "Folder exists: " . (is_dir($save_dir) ? 'YES' : 'NO') . "<br>";
echo "Folder writable: " . (is_writable($save_dir) ? 'YES' : 'NO') . "<br>";

// Test file_get_contents
$test_url = 'https://teacherplus.org/wp-content/uploads/2020/06/first-step-1.jpg';
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$data = @file_get_contents($test_url, false, $ctx);
echo "Download test: " . ($data !== false ? 'SUCCESS (' . strlen($data) . ' bytes)' : 'FAILED') . "<br>";

// Test write
if ($data !== false) {
    file_put_contents($save_dir . 'test.jpg', $data);
    echo "Write test: " . (file_exists($save_dir . 'test.jpg') ? 'SUCCESS' : 'FAILED') . "<br>";
}
?>