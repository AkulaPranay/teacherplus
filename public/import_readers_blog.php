<?php
/**
 * import_readers_blog.php
 * ─────────────────────────────────────────────────────────────
 * Imports readers-blog.xml into the articles table.
 * dc:creator (shalini/kumar) is used to look up user_id.
 *
 * HOW TO USE:
 * 1. Put readers-blog.xml in:  teacherplus/xml_imports/
 * 2. Drop this file in:        teacherplus/public/
 * 3. Open:  http://localhost/teacherplus/public/import_readers_blog.php
 * 4. DELETE after done!
 * ─────────────────────────────────────────────────────────────
 */

ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');
set_time_limit(0);

require '../includes/config.php';

$xml_path = '../xml_imports/readers-blog.xml';

if (!file_exists($xml_path)) {
    die("<b style='color:red'>File not found: $xml_path</b><br>
         Put readers-blog.xml inside teacherplus/xml_imports/ and reload.");
}

$xml = simplexml_load_file($xml_path, 'SimpleXMLElement', LIBXML_NOCDATA);
if (!$xml) {
    die("<b style='color:red'>Failed to parse XML.</b>");
}

// ── Build author map: login → {id, full_name} ────────────────
// First from <wp:author> entries in the XML itself
$author_map = [];
foreach ($xml->channel->children('http://wordpress.org/export/1.2/') as $node) {
    if ($node->getName() !== 'author') continue;
    $login = strtolower(trim((string)$node->author_login));
    $name  = trim((string)$node->author_display_name) ?: $login;
    $author_map[$login] = ['id' => null, 'name' => $name];
}

// Match against your users table by username
foreach ($author_map as $login => &$data) {
    $uq = $conn->prepare("SELECT id, full_name, username FROM users WHERE LOWER(username) = ? LIMIT 1");
    $uq->bind_param("s", $login);
    $uq->execute();
    $urow = $uq->get_result()->fetch_assoc();
    if ($urow) {
        $data['id']   = $urow['id'];
        $data['name'] = $urow['full_name'] ?: $urow['username'];
    }
}
unset($data);

$items = $xml->channel->item;

// ── Pass 1: attachment maps ───────────────────────────────────
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
}

// ── Pass 2: import posts ──────────────────────────────────────
$imported = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];

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
    $pub_date = (string)$wp->post_date_gmt;
    $creator  = strtolower(trim((string)$dc->creator));

    if (!$title) continue;

    // ── Resolve uploader from dc:creator ─────────────────────
    $uploader_id   = $author_map[$creator]['id']   ?? null;
    $uploader_name = $author_map[$creator]['name'] ?? $creator;

    // ── Parse categories ──────────────────────────────────────
    $topic_cats = [];
    $kw_tags    = [];

    foreach ($item->category as $cat) {
        $nicename = (string)$cat['nicename'];
        $domain   = (string)$cat['domain'];
        $display  = trim((string)$cat);

        if ($domain === 'post_tag') {
            $kw_tags[] = $display;
            continue;
        }

        // Skip month-year categories (october-2024-2024 etc.)
        if (preg_match('/\d{4}/', $nicename)) continue;

        // Clean topic
        if ($display && strpos($display, ',') === false) {
            $topic_cats[] = $display;
        }
    }

    $category = $topic_cats ? $topic_cats[0] : 'Readers Blog';
    $tags     = implode(', ', $kw_tags);

    // ── Resolve featured image ────────────────────────────────
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

    // ── Check if already exists ──────────────────────────────
    $chk = $conn->prepare("SELECT id FROM articles WHERE title = ? AND created_at = ? LIMIT 1");
    $chk->bind_param("ss", $title, $pub_date);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();

    if ($existing) {
        // ── UPDATE existing article ───────────────────────────
        $upd = $conn->prepare("
            UPDATE articles
            SET user_id       = ?,
                author_name   = ?,
                category      = ?,
                tags          = ?,
                excerpt       = ?,
                body          = ?,
                featured_image = ?,
                status        = 'published',
                updated_at    = NOW()
            WHERE id = ?
        ");
        $upd->bind_param("issssssi",
            $uploader_id, $uploader_name, $category, $tags,
            $excerpt, $body, $featured_image, $existing['id']
        );
        if ($upd->execute()) {
            $updated++;
        } else {
            $errors[] = "Update failed: \"$title\" — " . $conn->error;
        }
    } else {
        // ── INSERT new article ────────────────────────────────
        $ins = $conn->prepare("
            INSERT INTO articles
                (user_id, title, excerpt, body, featured_image,
                 author_name, category, tags, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, NOW())
        ");
        $ins->bind_param("issssssss",
            $uploader_id,
            $title, $excerpt, $body, $featured_image,
            $uploader_name, $category, $tags, $pub_date
        );
        if ($ins->execute()) {
            $imported++;
        } else {
            $errors[] = "Insert failed: \"$title\" — " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html><html><head><title>Readers Blog Import</title>
<style>
body{font-family:sans-serif;max-width:700px;margin:40px auto;padding:0 20px;background:#f5f5f5}
h2{color:#1a1a2e}
.done{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:16px 20px;border-radius:8px;margin-bottom:20px;font-weight:600;font-size:1rem}
.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;margin-bottom:16px}
.stat{display:block;font-size:.9rem;color:#555;margin-bottom:8px}
.stat b{color:#1a1a2e;font-size:1rem}
.errs{color:#991b1b;font-size:.82rem;margin-top:12px;background:#fee2e2;padding:8px 12px;border-radius:4px}
.authors{margin-top:14px;font-size:.82rem;color:#666}
.authors b{color:#1a1a2e}
.warn{background:#fef3c7;border:1px solid #fbbf24;color:#92400e;padding:12px 16px;border-radius:6px;margin-top:20px;font-size:.85rem}
</style></head><body>
<h2>📥 Readers Blog Import</h2>
<div class="done">✅ Import complete!</div>

<div class="card">
    <span class="stat">Articles imported (new): <b><?= $imported ?></b></span>
    <span class="stat">Articles updated: <b><?= $updated ?></b></span>
    <span class="stat">Skipped: <b><?= $skipped ?></b></span>

    <div class="authors">
        <b>Authors resolved:</b><br>
        <?php foreach ($author_map as $login => $data): ?>
            <?= htmlspecialchars($login) ?> →
            <?= htmlspecialchars($data['name']) ?>
            (user_id: <?= $data['id'] ?? '<span style="color:red">NOT FOUND in users table</span>' ?>)<br>
        <?php endforeach; ?>
    </div>

    <?php if ($errors): ?>
        <div class="errs"><b>Errors:</b><br><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>
</div>

<div class="warn">⚠️ <b>Delete</b> <code>import_readers_blog.php</code> after done.</div>
</body></html>