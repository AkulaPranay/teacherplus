<?php
/**
 * import_xml.php — TeacherPlus WordPress XML Importer
 * ─────────────────────────────────────────────────────────────
 * Field mapping:
 *   category    → clean topic/section  e.g. "Activity"
 *   tags        → keyword tags         e.g. "creativity, STEM"
 *   description → month-year only      e.g. "December 2013"
 * ─────────────────────────────────────────────────────────────
 */

ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');
set_time_limit(0);

require '../includes/config.php';

$xml_dir = '../xml_imports/';

$xml_files = glob(rtrim($xml_dir, '/') . '/*.xml');
if (empty($xml_files)) {
    die("<b style='color:red'>No XML files found in: $xml_dir</b>");
}

// ── Known month names for detection ──────────────────────────
$month_names = [
    'january','february','march','april','may','june',
    'july','august','september','october','november','december',
    'may-june'
];

function is_month_year_cat(string $nicename): bool {
    global $month_names;
    // matches: "december-2013", "may-june-2015", "march-2026"
    foreach ($month_names as $m) {
        if (preg_match('/^' . preg_quote($m, '/') . '-\d{4}$/', $nicename)) return true;
    }
    return false;
}

function is_clean_topic(string $nicename, string $display): bool {
    // Reject if nicename looks like year-only, or year-topic combo
    if (preg_match('/^\d{4}$/', $nicename)) return false;
    if (preg_match('/^\d{4}-/', $nicename)) return false;
    // Reject if display name contains commas (tags mixed in)
    if (strpos($display, ',') !== false) return false;
    // Reject if display is just a year
    if (preg_match('/^\d{4}$/', trim($display))) return false;
    return true;
}

$results = [];

foreach ($xml_files as $xml_file) {
    $r = ['file'=>basename($xml_file),'imported'=>0,'skipped'=>0,'attachments'=>0,'errors'=>[]];

    $xml = simplexml_load_file($xml_file, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) { $r['errors'][] = "Failed to parse XML."; $results[]=$r; continue; }

    $items = $xml->channel->item;

    // ── Pass 1: attachment maps ───────────────────────────────
    $att_by_id     = [];
    $att_by_parent = [];

    foreach ($items as $item) {
        $wp = $item->children('http://wordpress.org/export/1.2/');
        if ((string)$wp->post_type !== 'attachment') continue;
        $id  = (int)$wp->post_id;
        $pid = (int)$wp->post_parent;
        $url = (string)$wp->attachment_url;
        if (!$url) continue;
        $att_by_id[$id] = $url;
        if ($pid && !isset($att_by_parent[$pid])) $att_by_parent[$pid] = $url;
        $r['attachments']++;
    }

    // ── Pass 2: import posts ──────────────────────────────────
    foreach ($items as $item) {
        $wp = $item->children('http://wordpress.org/export/1.2/');
        $co = $item->children('http://purl.org/rss/1.0/modules/content/');
        $ex = $item->children('http://wordpress.org/export/1.2/excerpt/');
        $dc = $item->children('http://purl.org/dc/elements/1.1/');

        if ((string)$wp->post_type !== 'post')    continue;
        if ((string)$wp->status    !== 'publish') continue;

        $wp_id    = (int)$wp->post_id;
        $title    = trim((string)$item->title);
        $body     = (string)$co->encoded;
        $excerpt  = strip_tags((string)$ex->encoded);
        $author   = trim((string)$dc->creator);
        $pub_date = (string)$wp->post_date_gmt;
        if (!$title) continue;

        // ── Parse categories ──────────────────────────────────
        $month_year  = '';   // → description  "December 2013"
        $topic_cats  = [];   // → category     "Activity"
        $kw_tags     = [];   // → tags         from post_tag domain

        foreach ($item->category as $cat) {
            $nicename = (string)$cat['nicename'];
            $domain   = (string)$cat['domain'];
            $display  = trim((string)$cat);

            if ($domain === 'post_tag') {
                // Actual keyword tags
                $kw_tags[] = $display;
                continue;
            }

            if (is_month_year_cat($nicename)) {
                // Month-year category → goes to description
                if (!$month_year) $month_year = $display;
            } elseif (is_clean_topic($nicename, $display)) {
                // Clean topic section → goes to category
                $topic_cats[] = $display;
            }
            // else: skip garbage (year-only, year-topic combos, comma-lists)
        }

        // Use first clean topic as category (matches real site sections)
        $category    = $topic_cats ? $topic_cats[0] : '';
        $tags        = implode(', ', $kw_tags);
        $description = $month_year;

        // ── Resolve featured image ────────────────────────────
        $featured_image = null;
        foreach ($wp->postmeta as $meta) {
            if ((string)$meta->meta_key === '_thumbnail_id') {
                $tid = (int)(string)$meta->meta_value;
                if (isset($att_by_id[$tid])) { $featured_image = $att_by_id[$tid]; break; }
            }
        }
        if (!$featured_image && isset($att_by_parent[$wp_id]))
            $featured_image = $att_by_parent[$wp_id];
        if (!$featured_image && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $body, $m))
            $featured_image = $m[1];

        // ── Skip duplicates ───────────────────────────────────
        $chk = $conn->prepare("SELECT id FROM articles WHERE title=? AND description=? LIMIT 1");
        $chk->bind_param("ss", $title, $description);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) { $r['skipped']++; continue; }

        // ── Insert ────────────────────────────────────────────
        $ins = $conn->prepare("
            INSERT INTO articles
                (user_id, title, excerpt, description, body, featured_image,
                 author_name, category, tags, status, created_at, updated_at)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, NOW())
        ");
        $ins->bind_param("sssssssss",
            $title, $excerpt, $description, $body,
            $featured_image, $author, $category, $tags, $pub_date
        );
        $ins->execute() ? $r['imported']++ : ($r['errors'][] = "Failed: \"$title\" — ".$conn->error);
    }

    $results[] = $r;
}
?>
<!DOCTYPE html><html><head><title>Import Results</title>
<style>
body{font-family:sans-serif;max-width:860px;margin:40px auto;padding:0 20px;background:#f5f5f5}
h2{color:#1a1a2e}
.done{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-weight:600}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px}
.card h3{margin:0 0 12px;color:#e07000;font-size:.95rem;word-break:break-all}
.stat{display:inline-block;margin-right:24px;font-size:.88rem;color:#555}
.stat b{color:#1a1a2e}
.errs{color:#991b1b;font-size:.82rem;margin-top:10px;background:#fee2e2;padding:8px 12px;border-radius:4px}
.legend{background:#fff;border:1px solid #ddd;border-radius:8px;padding:14px 20px;margin-bottom:20px;font-size:.84rem;line-height:1.9}
.warn{background:#fef3c7;border:1px solid #fbbf24;color:#92400e;padding:12px 16px;border-radius:6px;margin-top:24px;font-size:.85rem}
</style></head><body>
<h2>📥 XML Import Results</h2>
<div class="done">✅ Import complete!</div>
<div class="legend">
    <b>Field mapping:</b><br>
    <code>category</code>    → clean section name (e.g. "Activity", "Action Research")<br>
    <code>tags</code>        → keyword tags (e.g. "creativity, STEM, learning")<br>
    <code>description</code> → month-year (e.g. "December 2013") — used by archives page
</div>
<?php foreach ($results as $r): ?>
<div class="card">
    <h3>📄 <?= htmlspecialchars($r['file']) ?></h3>
    <span class="stat">Imported: <b><?= $r['imported'] ?></b></span>
    <span class="stat">Attachments: <b><?= $r['attachments'] ?></b></span>
    <span class="stat">Skipped: <b><?= $r['skipped'] ?></b></span>
    <?php if ($r['errors']): ?>
        <div class="errs"><b>Errors:</b><br><?= implode('<br>', array_map('htmlspecialchars', $r['errors'])) ?></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<div class="warn">⚠️ <b>Delete</b> <code>import_xml.php</code> after done.</div>
</body></html>