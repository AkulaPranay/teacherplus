<?php
// ============================================================
// fix_images_v2.php — TeacherPlus Image Recovery via WP API
// Place in: C:\xampp1\htdocs\teacherplus\public\
// Run at:   http://localhost/teacherplus/public/fix_images_v2.php
// ============================================================

set_time_limit(0); // This will take a while
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../includes/config.php';

$save_dir = __DIR__ . '/uploads/images/';
$web_path = '/teacherplus/public/uploads/images/';
$api_base = 'https://teacherplus.org/wp-json/wp/v2/media/';

if (!is_dir($save_dir)) {
    mkdir($save_dir, 0755, true);
}

$ctx = stream_context_create([
    'http' => [
        'timeout'         => 20,
        'follow_location' => true,
        'user_agent'      => 'Mozilla/5.0 (compatible; TeacherPlusFixer/2.0)',
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

// ── Cache: image_id → local filename (avoid repeat API calls) ─
$id_to_url      = []; // wp image id => original URL
$id_to_filename = []; // wp image id => local filename
$api_failures   = []; // ids that failed API lookup

function get_image_url_for_id(int $id, string $api_base, $ctx, array &$cache, array &$failures): string|false {
    if (isset($failures[$id])) return false;
    if (isset($cache[$id]))    return $cache[$id];

    $api_url = $api_base . $id;
    $json    = @file_get_contents($api_url, false, $ctx);

    if ($json === false) {
        $failures[$id] = true;
        return false;
    }

    $data = json_decode($json, true);
    $url  = $data['source_url'] ?? ($data['guid']['rendered'] ?? false);

    if (!$url) {
        $failures[$id] = true;
        return false;
    }

    $cache[$id] = $url;
    return $url;
}

function download_and_save(string $url, string $save_dir, $ctx): string|false {
    $filename  = basename(parse_url($url, PHP_URL_PATH));
    $filename  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $localpath = $save_dir . $filename;

    if (file_exists($localpath)) return $filename;

    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return false;

    file_put_contents($localpath, $data);
    return $filename;
}

// ── Step 1: Fix body images ───────────────────────────────────

$stats = ['body_fixed' => 0, 'feat_fixed' => 0, 'api_fail' => 0, 'dl_fail' => 0, 'articles' => 0];

// Get all articles with broken local image paths in body
$res = $conn->query("SELECT id, title, body, featured_image FROM articles 
                     WHERE body LIKE '%wp-image-%' 
                        OR featured_image LIKE '%/uploads/images/%'
                        OR featured_image LIKE '%teacherplus.org%'");

if (!$res) die("Query error: " . $conn->error);

$rows = $res->fetch_all(MYSQLI_ASSOC);
$total = count($rows);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<title>TeacherPlus Image Fixer v2</title>
<style>
  body { font-family: Arial, sans-serif; padding: 30px; background: #f5f5f5; }
  h1   { color: #983436; }
  .log { background:#fff; padding:15px; border-radius:4px; height:400px; overflow-y:auto;
         font-size:13px; font-family:monospace; border:1px solid #ddd; margin-bottom:20px; }
  .ok  { color:green; } .err { color:red; } .info { color:#666; }
  .summary { background:#fff; padding:15px 20px; border-left:5px solid #983436;
             border-radius:4px; font-size:15px; }
</style></head><body>
<h1>TeacherPlus – Image Fixer v2</h1>
<p>Processing <strong>$total articles</strong>... (this page will update as it runs)</p>
<div class='log' id='log'>";

flush(); ob_flush();

foreach ($rows as $i => $row) {
    $id           = $row['id'];
    $new_body     = $row['body'];
    $new_featured = $row['featured_image'];
    $changed      = false;

    // ── Fix body: replace broken <img src="/teacherplus/public/uploads/images/"> 
    //             using wp-image-XXXXX class to look up correct URL
    if (!empty($row['body']) && str_contains($row['body'], 'wp-image-')) {

        $new_body = preg_replace_callback(
            '#<img([^>]*)\ssrc=["\']([^"\']*)["\']([^>]*)>#i',
            function ($m) use ($save_dir, $web_path, $api_base, $ctx, &$id_to_url, &$api_failures, &$stats) {
                $before = $m[1];
                $src    = $m[2];
                $after  = $m[3];
                $full   = $m[0];

                // Only fix if src looks broken (no filename, just folder path)
                $is_broken_local = str_ends_with(rtrim($src, '/'), 'images') || 
                                   (str_contains($src, '/uploads/images/') && !preg_match('/\.\w{2,4}$/', $src));
                $is_external     = str_contains($src, 'teacherplus.org');

                if (!$is_broken_local && !$is_external) return $full; // already fine

                // Extract wp-image-XXXXX from class attribute
                if (!preg_match('/wp-image-(\d+)/i', $before . $after, $cm)) return $full;
                $img_id = (int)$cm[1];

                $url = get_image_url_for_id($img_id, $api_base, $ctx, $id_to_url, $api_failures);
                if (!$url) { $stats['api_fail']++; return $full; }

                $filename = download_and_save($url, $save_dir, $ctx);
                if (!$filename) { $stats['dl_fail']++; return $full; }

                $stats['body_fixed']++;
                return '<img' . $before . ' src="' . $web_path . $filename . '"' . $after . '>';
            },
            $row['body']
        );

        if ($new_body !== $row['body']) $changed = true;
    }

    // ── Fix featured_image ────────────────────────────────────
    $fi = $row['featured_image'];
    if (!empty($fi)) {
        $needs_fix = (str_contains($fi, 'teacherplus.org')) ||
                     (str_contains($fi, '/uploads/images/') && !preg_match('/\.\w{2,4}$/', $fi));

        if ($needs_fix) {
            // Try to get filename from URL if it's an external URL
            if (str_contains($fi, 'teacherplus.org')) {
                $filename = download_and_save($fi, $save_dir, $ctx);
                if ($filename) {
                    $new_featured = $web_path . $filename;
                    $stats['feat_fixed']++;
                    $changed = true;
                } else {
                    $stats['dl_fail']++;
                }
            } else {
                // Broken local path with no filename — we can't recover without an ID
                // Mark as needing manual check
                echo "<span class='err'>⚠ Article #$id featured_image has no filename and no source URL to recover from.</span><br>";
                flush(); ob_flush();
            }
        }
    }

    // ── Save to DB ────────────────────────────────────────────
    if ($changed) {
        $stmt = $conn->prepare("UPDATE articles SET body=?, featured_image=? WHERE id=?");
        $stmt->bind_param("ssi", $new_body, $new_featured, $id);
        $stmt->execute();
        $stmt->close();
        $stats['articles']++;
    }

    // Log progress every 50 articles
    if ($i % 50 === 0) {
        $pct = round(($i / $total) * 100);
        echo "<span class='info'>[$pct%] Processed " . ($i+1) . "/$total articles | "
           . "Body fixed: {$stats['body_fixed']} | Feat fixed: {$stats['feat_fixed']} | "
           . "API fails: {$stats['api_fail']} | DL fails: {$stats['dl_fail']}</span><br>";
        flush(); ob_flush();
    }
}

echo "</div>"; // close log

// Final summary
$img_count = count(glob($save_dir . '*'));
echo "<div class='summary'>
  <h2>✅ Done!</h2>
  <p><strong>Articles processed:</strong> {$stats['articles']}</p>
  <p><strong>Body images fixed:</strong> {$stats['body_fixed']}</p>
  <p><strong>Featured images fixed:</strong> {$stats['feat_fixed']}</p>
  <p><strong>API lookup failures:</strong> {$stats['api_fail']}</p>
  <p><strong>Download failures:</strong> {$stats['dl_fail']}</p>
  <p><strong>Images now in uploads/images/:</strong> $img_count</p>
</div>
<p style='color:red;margin-top:20px'><strong>⚠ Delete this file from your server now!</strong></p>
</body></html>";
?>