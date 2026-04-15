<?php
/**
 * import_xml.php
 * ─────────────────────────────────────────────────────────────
 * Imports WordPress XML exports into your existing `articles` table.
 *
 * HOW TO USE:
 * 1. Place all your XML files in:  teacherplus/xml_imports/
 * 2. Drop this file in:            teacherplus/public/
 * 3. Open in browser:              http://localhost/teacherplus/public/import_xml.php
 * 4. DELETE this file after importing!
 * ─────────────────────────────────────────────────────────────
 */

require '../includes/config.php';

$xml_dir = '../xml_imports/';
set_time_limit(300);

$xml_files = glob(rtrim($xml_dir, '/') . '/*.xml');

if (empty($xml_files)) {
    die("
        <b style='color:red'>No XML files found in: $xml_dir</b><br><br>
        Steps:<br>
        1. Create the folder <code>teacherplus/xml_imports/</code><br>
        2. Put all your .xml files inside it<br>
        3. Reload this page
    ");
}

$results = [];

foreach ($xml_files as $xml_file) {
    $file_result = [
        'file'        => basename($xml_file),
        'imported'    => 0,
        'skipped'     => 0,
        'attachments' => 0,
        'errors'      => []
    ];

    $xml = simplexml_load_file($xml_file, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        $file_result['errors'][] = "Failed to parse XML.";
        $results[] = $file_result;
        continue;
    }

    $items = $xml->channel->item;

    // ── Pass 1: Build attachment map ─────────────────────────
    $att_by_id     = [];  // attachment wp_post_id => url
    $att_by_parent = [];  // parent wp_post_id     => first url

    foreach ($items as $item) {
        $ns_wp = $item->children('http://wordpress.org/export/1.2/');
        if ((string)$ns_wp->post_type !== 'attachment') continue;

        $wp_id     = (int)$ns_wp->post_id;
        $parent_id = (int)$ns_wp->post_parent;
        $url       = (string)$ns_wp->attachment_url;
        if (!$url) continue;

        $att_by_id[$wp_id] = $url;
        if ($parent_id && !isset($att_by_parent[$parent_id])) {
            $att_by_parent[$parent_id] = $url;
        }
        $file_result['attachments']++;
    }

    // ── Pass 2: Import published posts ───────────────────────
    foreach ($items as $item) {
        $ns_wp      = $item->children('http://wordpress.org/export/1.2/');
        $ns_content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $ns_excerpt = $item->children('http://wordpress.org/export/1.2/excerpt/');
        $ns_dc      = $item->children('http://purl.org/dc/elements/1.1/');

        if ((string)$ns_wp->post_type !== 'post')    continue;
        if ((string)$ns_wp->status    !== 'publish') continue;

        $wp_post_id = (int)$ns_wp->post_id;
        $title      = trim((string)$item->title);
        $body       = (string)$ns_content->encoded;
        $excerpt    = strip_tags((string)$ns_excerpt->encoded);
        $author     = trim((string)$ns_dc->creator);
        $pub_date   = (string)$ns_wp->post_date_gmt;

        if (!$title) continue;

        // ── Resolve category (month-year format like "December 2013") ──
        $month_year_cat = '';
        foreach ($item->category as $cat) {
            $nicename = (string)$cat['nicename'];
            if (preg_match('/^(january|february|march|april|may|june|july|august|september|october|november|december)(-june)?-(\d{4})$/', $nicename)) {
                $month_year_cat = trim((string)$cat);
                break;
            }
        }
        // Fallback: first category
        if (!$month_year_cat && $item->category) {
            $month_year_cat = trim((string)$item->category[0]);
        }

        // ── Resolve featured image ──
        $featured_image = null;

        // 1. _thumbnail_id postmeta
        foreach ($ns_wp->postmeta as $meta) {
            if ((string)$meta->meta_key === '_thumbnail_id') {
                $thumb_id = (int)(string)$meta->meta_value;
                if (isset($att_by_id[$thumb_id])) {
                    $featured_image = $att_by_id[$thumb_id];
                    break;
                }
            }
        }
        // 2. First child attachment
        if (!$featured_image && isset($att_by_parent[$wp_post_id])) {
            $featured_image = $att_by_parent[$wp_post_id];
        }
        // 3. First <img> in body
        if (!$featured_image && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $body, $m)) {
            $featured_image = $m[1];
        }

        // ── Skip duplicates (safe to re-run) ──
        $chk = $conn->prepare("SELECT id FROM articles WHERE title = ? AND category = ? LIMIT 1");
        $chk->bind_param("ss", $title, $month_year_cat);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $file_result['skipped']++;
            continue;
        }

        // ── Insert into existing articles table ──
        $ins = $conn->prepare("
            INSERT INTO articles
                (user_id, title, excerpt, body, featured_image, author_name,
                 category, tags, status, created_at, updated_at)
            VALUES
                (NULL, ?, ?, ?, ?, ?, ?, '', 'published', ?, NOW())
        ");
        $ins->bind_param("sssssss",
            $title, $excerpt, $body,
            $featured_image, $author,
            $month_year_cat, $pub_date
        );

        if ($ins->execute()) {
            $file_result['imported']++;
        } else {
            $file_result['errors'][] = "Insert failed for \"$title\": " . $conn->error;
        }
    }

    $results[] = $file_result;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>XML Import – TeacherPlus</title>
<style>
    body { font-family: sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
    h2   { color: #1a1a2e; }
    .done { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; }
    .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
    .card h3 { margin: 0 0 12px; color: #e07000; font-size: 0.95rem; word-break: break-all; }
    .stat { display: inline-block; margin-right: 24px; font-size: 0.88rem; color: #555; }
    .stat b { color: #1a1a2e; }
    .errs { color: #991b1b; font-size: 0.82rem; margin-top: 10px; background: #fee2e2; padding: 8px 12px; border-radius: 4px; }
    .warn { background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; padding: 12px 16px; border-radius: 6px; margin-top: 24px; font-size: 0.85rem; }
</style>
</head>
<body>
<h2>📥 XML Import Results</h2>
<div class="done">✅ Import complete! Check results below, then delete this file.</div>
<?php foreach ($results as $r): ?>
<div class="card">
    <h3>📄 <?php echo htmlspecialchars($r['file']); ?></h3>
    <span class="stat">Imported: <b><?php echo $r['imported']; ?></b></span>
    <span class="stat">Attachments mapped: <b><?php echo $r['attachments']; ?></b></span>
    <span class="stat">Skipped (duplicate): <b><?php echo $r['skipped']; ?></b></span>
    <?php if ($r['errors']): ?>
        <div class="errs"><b>Errors:</b><br><?php echo implode('<br>', array_map('htmlspecialchars', $r['errors'])); ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<div class="warn">⚠️ <b>Delete</b> <code>import_xml.php</code> after importing. Safe to re-run — duplicates are skipped automatically.</div>
</body>
</html>