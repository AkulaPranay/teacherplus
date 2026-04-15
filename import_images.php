<?php
/**
 * TeacherPlus — Image Downloader
 * Downloads all images from the live WordPress site and saves them locally.
 * Updates article body img src paths + featured_image in the DB.
 *
 * Place in project root: C:/xampp/htdocs/teacherplus/import_images.php
 * Open: http://localhost/teacherplus/import_images.php
 */

require 'includes/config.php';

set_time_limit(0); // images can take a while
ini_set('memory_limit', '256M');

// ── CONFIG — set your old WordPress site URL ──────────────────────────────────
define('WP_BASE_URL', 'https://teacherplus.org'); // ← change if different

// ── PROGRESS TABLE ────────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS _image_import (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    original_url VARCHAR(500) NOT NULL UNIQUE,
    local_path   VARCHAR(500) DEFAULT NULL,
    status       ENUM('pending','done','failed') DEFAULT 'pending',
    error        VARCHAR(255) DEFAULT NULL,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ── HARD RESET ────────────────────────────────────────────────────────────────
if (isset($_GET['reset'])) {
    $conn->query("DROP TABLE IF EXISTS _image_import");
    header("Location: import_images.php"); exit;
}

// ── SCAN: collect all image URLs from XML files + article bodies ──────────────
if (isset($_GET['scan'])) {
    header('Content-Type: application/json');

    $xml_files = ['readers-blog.xml','editorial.xml','articles-part1.xml','articles-part2.xml'];
    $found = 0;

    foreach ($xml_files as $xf) {
        $path = __DIR__ . '/' . $xf;
        if (!file_exists($path)) continue;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) continue;

        $ns = $xml->getNamespaces(true);

        foreach ($xml->channel->item as $item) {
            $wp = isset($ns['wp']) ? $item->children($ns['wp']) : null;

            // Featured image from attachment items
            if ($wp && (string)$wp->post_type === 'attachment') {
                $url = trim((string)$wp->attachment_url);
                if (empty($url)) $url = trim((string)$item->guid);
                if (!empty($url) && preg_match('/\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i', $url)) {
                    $url_clean = strtok($url, '?'); // remove query string
                    $u = $conn->real_escape_string($url_clean);
                    $conn->query("INSERT IGNORE INTO _image_import (original_url, status) VALUES ('$u','pending')");
                    $found++;
                }
            }

            // Images inside article body content
            $body = isset($ns['content']) ? (string)$item->children($ns['content'])->encoded : '';
            if (!empty($body)) {
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $body, $matches);
                foreach ($matches[1] as $img_url) {
                    $img_url = strtok(trim($img_url), '?');
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $img_url)) {
                        $u = $conn->real_escape_string($img_url);
                        $conn->query("INSERT IGNORE INTO _image_import (original_url, status) VALUES ('$u','pending')");
                        $found++;
                    }
                }
            }
        }
    }

    // Also scan article bodies already in DB
    $articles = $conn->query("SELECT id, body FROM articles WHERE body LIKE '%<img%'");
    while ($row = $articles->fetch_assoc()) {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $row['body'], $matches);
        foreach ($matches[1] as $img_url) {
            $img_url = strtok(trim($img_url), '?');
            if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $img_url)) {
                $u = $conn->real_escape_string($img_url);
                $conn->query("INSERT IGNORE INTO _image_import (original_url, status) VALUES ('$u','pending')");
                $found++;
            }
        }
    }

    $total = $conn->query("SELECT COUNT(*) FROM _image_import")->fetch_row()[0];
    echo json_encode(['found'=>$found, 'total'=>$total]);
    exit;
}

// ── DOWNLOAD: process one batch ───────────────────────────────────────────────
if (isset($_GET['download'])) {
    header('Content-Type: application/json');

    $batch = 10; // download 10 images at a time
    $rows  = $conn->query("SELECT id, original_url FROM _image_import WHERE status='pending' LIMIT $batch");

    $done_count  = 0;
    $fail_count  = 0;
    $results     = [];

    while ($row = $rows->fetch_assoc()) {
        $url        = $row['original_url'];
        $id         = $row['id'];

        // Build local save path
        // Extract path from URL: https://teacherplus.org/wp-content/uploads/2024/03/image.jpg
        // → save to: uploads/articles/2024-03/image.jpg
        $parsed     = parse_url($url);
        $url_path   = $parsed['path'] ?? '';

        // Keep the original directory structure under uploads/
        // Strip /wp-content/uploads/ prefix if present
        $rel = preg_replace('#^/wp-content/uploads/#', '', $url_path);
        if ($rel === $url_path) {
            // Not a wp-content URL — just use the filename
            $rel = basename($url_path);
        }

        $local_rel  = 'uploads/imported/' . $rel;  // relative to project root
        $local_abs  = __DIR__ . '/' . $local_rel;
        $local_dir  = dirname($local_abs);

        // Create directory
        if (!is_dir($local_dir)) {
            mkdir($local_dir, 0755, true);
        }

        // Download the image
        $downloaded = false;
        $error_msg  = '';

        // Try with the original URL first, then with WP_BASE_URL prefix
        $try_urls = [$url];
        if (!preg_match('/^https?:\/\//i', $url)) {
            $try_urls = [WP_BASE_URL . $url]; // relative URL
        }

        foreach ($try_urls as $try_url) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout'          => 15,
                    'follow_location'  => true,
                    'user_agent'       => 'Mozilla/5.0 (compatible; TeacherPlus-Importer/1.0)',
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $data = @file_get_contents($try_url, false, $ctx);
            if ($data !== false && strlen($data) > 100) {
                if (file_put_contents($local_abs, $data)) {
                    $downloaded = true;
                    break;
                } else {
                    $error_msg = 'Could not write file';
                }
            } else {
                $error_msg = 'Download failed or empty response';
            }
        }

        if ($downloaded) {
            $lp  = $conn->real_escape_string($local_rel);
            $eid = (int)$id;
            $conn->query("UPDATE _image_import SET status='done', local_path='$lp' WHERE id=$eid");
            $done_count++;
            $results[] = ['url'=>$url,'local'=>$local_rel,'ok'=>true];
        } else {
            $em  = $conn->real_escape_string($error_msg);
            $eid = (int)$id;
            $conn->query("UPDATE _image_import SET status='failed', error='$em' WHERE id=$eid");
            $fail_count++;
            $results[] = ['url'=>$url,'error'=>$error_msg,'ok'=>false];
        }
    }

    $remaining = $conn->query("SELECT COUNT(*) FROM _image_import WHERE status='pending'")->fetch_row()[0];
    $total     = $conn->query("SELECT COUNT(*) FROM _image_import")->fetch_row()[0];
    $done_all  = $conn->query("SELECT COUNT(*) FROM _image_import WHERE status='done'")->fetch_row()[0];

    echo json_encode([
        'batch_done'  => $done_count,
        'batch_fail'  => $fail_count,
        'remaining'   => (int)$remaining,
        'total'       => (int)$total,
        'total_done'  => (int)$done_all,
        'finished'    => (int)$remaining === 0,
        'results'     => $results,
    ]);
    exit;
}

// ── UPDATE DB: replace old URLs with new local paths in article bodies ─────────
if (isset($_GET['update_db'])) {
    header('Content-Type: application/json');

    // Get all successfully downloaded images
    $images = $conn->query("SELECT original_url, local_path FROM _image_import WHERE status='done'");
    $updated_articles = 0;
    $url_map = [];
    while ($img = $images->fetch_assoc()) {
        $url_map[$img['original_url']] = '/' . $img['local_path'];
    }

    if (empty($url_map)) {
        echo json_encode(['error'=>'No downloaded images found. Run the download step first.']);
        exit;
    }

    // Update article bodies
    $articles = $conn->query("SELECT id, body, featured_image FROM articles");
    while ($article = $articles->fetch_assoc()) {
        $new_body     = $article['body'];
        $new_featured = $article['featured_image'];
        $changed      = false;

        foreach ($url_map as $old_url => $new_path) {
            if (strpos($new_body, $old_url) !== false) {
                $new_body = str_replace($old_url, $new_path, $new_body);
                $changed  = true;
            }
            if ($article['featured_image'] && strpos($article['featured_image'], $old_url) !== false) {
                $new_featured = str_replace($old_url, $new_path, $new_featured);
                $changed      = true;
            }
        }

        if ($changed) {
            $nb  = $conn->real_escape_string($new_body);
            $nf  = $conn->real_escape_string($new_featured);
            $aid = (int)$article['id'];
            $conn->query("UPDATE articles SET body='$nb', featured_image='$nf' WHERE id=$aid");
            $updated_articles++;
        }
    }

    echo json_encode(['updated_articles' => $updated_articles, 'url_map_size' => count($url_map)]);
    exit;
}

// ── STATS for HTML page ───────────────────────────────────────────────────────
$tbl_exists = $conn->query("SHOW TABLES LIKE '_image_import'")->num_rows > 0;
$stats = ['total'=>0,'pending'=>0,'done'=>0,'failed'=>0];
if ($tbl_exists) {
    $r = $conn->query("SELECT status, COUNT(*) as c FROM _image_import GROUP BY status");
    while ($row = $r->fetch_assoc()) $stats[$row['status']] = (int)$row['c'];
    $stats['total'] = $stats['pending'] + $stats['done'] + $stats['failed'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Image Importer — TeacherPlus</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f8;color:#333;padding:40px 20px}
.wrap{max-width:760px;margin:0 auto}
h1{font-size:1.4rem;font-weight:700;color:#1f2a4a;margin-bottom:4px}
.sub{font-size:13px;color:#888;margin-bottom:28px}
.card{background:#fff;border-radius:12px;border:1px solid #e8eaf0;padding:22px 26px;margin-bottom:18px}
.card h3{font-size:13.5px;font-weight:700;color:#1f2a4a;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.card h3 .step-num{width:24px;height:24px;border-radius:50%;background:#f87407;color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.btn{display:inline-block;padding:10px 24px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none}
.btn-orange{background:#f87407;color:#fff}
.btn-orange:hover{background:#d96400;color:#fff}
.btn-orange:disabled{background:#ccc;cursor:not-allowed}
.btn-navy{background:#1f2a4a;color:#fff}
.btn-navy:hover{background:#162038;color:#fff}
.btn-ghost{background:#fff;border:1px solid #dee2e6;color:#555}
.btn-ghost:hover{border-color:#f87407;color:#f87407}
.btn-red{background:#fff;border:1px solid #f5c2c7;color:#dc3545}
.btn-red:hover{background:#dc3545;color:#fff}
.stats-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.stat-box{background:#f8f9fc;border-radius:8px;padding:12px 18px;text-align:center;flex:1;min-width:80px}
.stat-box .num{font-size:1.6rem;font-weight:700;color:#1f2a4a;line-height:1}
.stat-box .lbl{font-size:11px;color:#aaa;margin-top:4px;text-transform:uppercase;letter-spacing:0.05em}
.stat-box.done .num{color:#28a745}
.stat-box.failed .num{color:#dc3545}
.stat-box.pending .num{color:#f87407}
.bar-wrap{height:10px;background:#f0f2f8;border-radius:5px;overflow:hidden;margin:12px 0}
.bar-fill{height:100%;background:#f87407;border-radius:5px;width:0;transition:width 0.4s ease}
.bar-fill.complete{background:#28a745}
.log-box{background:#1e1e1e;color:#aaa;border-radius:8px;padding:14px 16px;font-family:monospace;font-size:12px;height:180px;overflow-y:auto;margin-top:12px;line-height:1.6}
.log-ok{color:#69db7c}
.log-fail{color:#ff6b6b}
.log-info{color:#74c0fc}
code{background:#f0f0f0;border-radius:4px;padding:1px 6px;font-family:monospace;font-size:12px;color:#c0392b}
.note{font-size:12.5px;color:#888;margin-top:8px;line-height:1.6}
.done-box{background:#e8f5e9;border:1px solid #c8e6c9;border-radius:10px;padding:18px 22px;margin-top:16px;font-size:13.5px;color:#2e7d32;line-height:1.9;display:none}
.done-box strong{color:#1b5e20}
</style>
</head>
<body>
<div class="wrap">

<h1>🖼️ Image Importer</h1>
<p class="sub">Downloads all images from the live WordPress site and updates article content.</p>

<!-- Stats -->
<?php if ($stats['total'] > 0): ?>
<div class="card">
    <h3>📊 Progress</h3>
    <div class="stats-row">
        <div class="stat-box"><div class="num"><?php echo $stats['total']; ?></div><div class="lbl">Total</div></div>
        <div class="stat-box done"><div class="num"><?php echo $stats['done']; ?></div><div class="lbl">Downloaded</div></div>
        <div class="stat-box pending"><div class="num"><?php echo $stats['pending']; ?></div><div class="lbl">Pending</div></div>
        <div class="stat-box failed"><div class="num"><?php echo $stats['failed']; ?></div><div class="lbl">Failed</div></div>
    </div>
    <div class="bar-wrap">
        <div class="bar-fill <?php echo $stats['pending']===0?'complete':''; ?>"
             id="main-bar"
             style="width:<?php echo $stats['total']>0?round(($stats['done']/$stats['total'])*100):0; ?>%"></div>
    </div>
</div>
<?php endif; ?>

<!-- Step 1: Scan -->
<div class="card">
    <h3><span class="step-num">1</span> Scan XML files for image URLs</h3>
    <p class="note">Reads all 4 XML files + article bodies in your DB and collects every image URL that needs to be downloaded.</p>
    <div style="margin-top:14px">
        <button class="btn btn-orange" onclick="runScan()" id="btn-scan">🔍 Scan for Images</button>
        <span id="scan-result" style="margin-left:14px;font-size:13px;color:#888"></span>
    </div>
</div>

<!-- Step 2: Download -->
<div class="card">
    <h3><span class="step-num">2</span> Download images</h3>
    <p class="note">Downloads images in batches of 10 from <code><?php echo WP_BASE_URL; ?></code> and saves them to <code>uploads/imported/</code>.</p>
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-orange" onclick="startDownload()" id="btn-download">⬇️ Start Download</button>
        <span id="dl-label" style="font-size:13px;color:#888;align-self:center"></span>
    </div>
    <div class="bar-wrap" style="display:none" id="dl-bar-wrap">
        <div class="bar-fill" id="dl-bar"></div>
    </div>
    <div class="log-box" id="log" style="display:none"></div>
</div>

<!-- Step 3: Update DB -->
<div class="card">
    <h3><span class="step-num">3</span> Update article URLs in database</h3>
    <p class="note">Replaces all old WordPress image URLs in article bodies and featured_image fields with the new local paths.</p>
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-navy" onclick="updateDB()" id="btn-update">🔄 Update Article URLs</button>
        <span id="update-label" style="font-size:13px;color:#888;align-self:center"></span>
    </div>
</div>

<!-- Reset -->
<div class="card" style="border-color:#f5c2c7">
    <h3>⚠️ Reset</h3>
    <p class="note">Drops the image progress table so you can start fresh. Does NOT delete downloaded images.</p>
    <div style="margin-top:14px">
        <a class="btn btn-red" href="?reset=1" onclick="return confirm('Reset image import progress?')">↺ Reset Progress</a>
    </div>
</div>

<div class="done-box" id="done-box">
    ✅ <strong>All done!</strong> Images downloaded and article URLs updated.<br><br>
    ⚠️ <strong>Delete <code>import_images.php</code> from your server when finished.</strong>
</div>

</div>

<script>
let totalImages = <?php echo $stats['total']; ?>;
let doneImages  = <?php echo $stats['done'];  ?>;

function log(msg, type='info') {
    const box = document.getElementById('log');
    box.style.display = 'block';
    const cls = type==='ok'?'log-ok':type==='fail'?'log-fail':'log-info';
    box.innerHTML += `<span class="${cls}">${msg}</span>\n`;
    box.scrollTop = box.scrollHeight;
}

function updateMainBar() {
    if (totalImages > 0) {
        const pct = Math.round((doneImages / totalImages) * 100);
        const bar = document.getElementById('main-bar');
        if (bar) { bar.style.width = pct + '%'; if (pct>=100) bar.classList.add('complete'); }
    }
}

async function runScan() {
    document.getElementById('btn-scan').disabled = true;
    document.getElementById('scan-result').textContent = 'Scanning…';
    try {
        const res  = await fetch('?scan=1&t=' + Date.now());
        const data = await res.json();
        totalImages = data.total;
        document.getElementById('scan-result').textContent =
            `Found ${data.total} unique image URLs to download.`;
        document.getElementById('btn-scan').disabled = false;
    } catch(e) {
        document.getElementById('scan-result').textContent = 'Error: ' + e.message;
        document.getElementById('btn-scan').disabled = false;
    }
}

async function startDownload() {
    document.getElementById('btn-download').disabled = true;
    document.getElementById('dl-bar-wrap').style.display = 'block';

    while(true) {
        let data;
        try {
            const res = await fetch('?download=1&t=' + Date.now());
            data = await res.json();
        } catch(e) {
            log('Network error: ' + e.message, 'fail');
            break;
        }

        doneImages += data.batch_done;

        // Log results
        for (const r of data.results) {
            if (r.ok) log('✓ ' + r.url.split('/').pop(), 'ok');
            else      log('✗ ' + r.url.split('/').pop() + ' — ' + r.error, 'fail');
        }

        document.getElementById('dl-label').textContent =
            `Downloaded: ${data.total_done} / ${data.total} (${data.batch_fail} failed)`;

        const pct = data.total > 0 ? Math.round((data.total_done / data.total) * 100) : 0;
        document.getElementById('dl-bar').style.width = pct + '%';
        if (pct >= 100) document.getElementById('dl-bar').classList.add('complete');

        updateMainBar();

        if (data.finished) {
            document.getElementById('dl-label').textContent =
                `✅ Complete! ${data.total_done} downloaded, ${data.batch_fail + (data.total - data.total_done - data.batch_fail)} failed.`;
            log('--- Download complete ---', 'info');
            break;
        }

        await new Promise(r => setTimeout(r, 300));
    }

    document.getElementById('btn-download').disabled = false;
}

async function updateDB() {
    document.getElementById('btn-update').disabled = true;
    document.getElementById('update-label').textContent = 'Updating…';
    try {
        const res  = await fetch('?update_db=1&t=' + Date.now());
        const data = await res.json();
        if (data.error) {
            document.getElementById('update-label').textContent = '❌ ' + data.error;
        } else {
            document.getElementById('update-label').textContent =
                `✅ Updated ${data.updated_articles} articles (${data.url_map_size} URL mappings applied)`;
            document.getElementById('done-box').style.display = 'block';
        }
    } catch(e) {
        document.getElementById('update-label').textContent = 'Error: ' + e.message;
    }
    document.getElementById('btn-update').disabled = false;
}
</script>
</body>
</html> 