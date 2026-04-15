<?php
/**
 * TeacherPlus — WordPress XML Importer
 *
 * Usage:  Place in C:\xampp\htdocs\teacherplus\public\
 * Run:    http://localhost/teacherplus/public/import_wordpress_xml.php
 *
 * Field mapping:
 *   title          → <title>
 *   excerpt        → <excerpt:encoded>  (stripped of WP block comments)
 *   description    → month-year category label  e.g. "March 2026"  (used for archives)
 *   body           → <content:encoded>  (stripped of WP block comments)
 *   featured_image → attachment URL resolved via _thumbnail_id postmeta
 *   author_name    → dc:creator login (lowercase)
 *   user_id        → looked up from users table by username
 *   category       → non-date category label  e.g. "HumaneMath"
 *   tags           → comma-joined post_tag labels
 *   status         → 'published' for wp:status=publish, 'scheduled' for future
 *   scheduled_date → wp:post_date when status=future, otherwise NULL
 *   created_at     → wp:post_date
 *   updated_at     → wp:post_modified
 *
 * Deduplication: UPDATE if title + DATE(created_at) already exists, else INSERT.
 */

require_once __DIR__ . '/../includes/config.php';   // provides $conn (MySQLi)

// No time limit — image downloads can take a while across many articles
set_time_limit(0);

// ─── CONFIG ───────────────────────────────────────────────────────────────────

// Auto-scan the imports/ folder for all .xml files.
$xml_files = glob(__DIR__ . '/../imports/*.xml');

// Absolute filesystem path where downloaded images will be saved (must be writable).
define('IMG_SAVE_DIR', 'C:/xampp/htdocs/teacherplus/public/uploads/');

// Web-accessible URL prefix used in body/featured_image after downloading.
define('IMG_BASE_URL', '/teacherplus/public/uploads/');

// Source domain images are downloaded from.
define('IMG_SOURCE_HOST', 'https://teacherplus.org');

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function xmlText(SimpleXMLElement $node): string {
    return trim((string) $node);
}

/** Strip Gutenberg block comments and trim. */
function stripWpBlocks(string $html): string {
    return trim(preg_replace('/<!--\s*\/?wp:[^>]*-->/i', '', $html));
}

/** Build [ wp_post_id => attachment_url ] map from all attachment items. */
function buildAttachmentMap(SimpleXMLElement $channel): array {
    $map = [];
    foreach ($channel->item as $item) {
        $wp = $item->children('wp', true);
        if (xmlText($wp->post_type) !== 'attachment') continue;
        $id  = (int) xmlText($wp->post_id);
        $url = xmlText($wp->attachment_url);
        if ($id && $url) $map[$id] = $url;
    }
    return $map;
}

/** Resolve user_id from username (case-insensitive), with static cache. */
function resolveUserId(mysqli $conn, string $login): ?int {
    static $cache = [];
    $key = strtolower($login);
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($id);
    $uid = $stmt->fetch() ? (int) $id : null;
    $stmt->close();
    return $cache[$key] = $uid;
}

/** True if nicename is a month-year pattern e.g. "march-2026". */
function isMonthYear(string $nicename): bool {
    return (bool) preg_match(
        '/^(january|february|march|april|may|june|july|august|september|october|november|december)-\d{4}$/i',
        $nicename
    );
}

/**
 * Downloads a remote image into IMG_SAVE_DIR preserving year/month subfolders.
 * Skips if file already exists locally. Returns local URL on success,
 * original remote URL as fallback on failure.
 */
function downloadImage(string $remoteUrl): string {
    if (strpos($remoteUrl, IMG_SOURCE_HOST) !== 0) return $remoteUrl;

    $relativePath = preg_replace('#^.*?/wp-content/uploads/#', '', $remoteUrl);
    if (!$relativePath || $relativePath === $remoteUrl) return $remoteUrl;

    $localPath = rtrim(IMG_SAVE_DIR, '/\\') . '/' . ltrim($relativePath, '/');
    $localUrl  = rtrim(IMG_BASE_URL, '/')   . '/' . ltrim($relativePath, '/');

    if (file_exists($localPath)) return $localUrl;

    $dir = dirname($localPath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return $remoteUrl;

    $context = stream_context_create([
        'http' => [
            'timeout'          => 8,
            'follow_location'  => true,
            'user_agent'       => 'Mozilla/5.0 (compatible; TeacherPlusImporter/1.0)',
        ],
        'ssl'  => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($remoteUrl, false, $context);
    if ($data === false) return $remoteUrl;

    file_put_contents($localPath, $data);
    return $localUrl;
}

/** Echo a line immediately to the browser without buffering. */
function out(string $html): void {
    echo $html . "\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// ─── PAGE HEADER (output immediately so browser shows a live page) ────────────

if (ob_get_level()) ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>TeacherPlus XML Import</title>
<style>
  body  { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; line-height: 1.6; }
  .box  { background: #eef6ee; border: 1px solid #4caf50; border-radius: 6px; padding: 14px 20px; margin-bottom: 20px; }
  .err  { color: red; }
  .ok   { color: green; }
  .upd  { color: #e67e00; }
  hr    { border: none; border-top: 1px solid #ddd; margin: 12px 0; }
  h3    { margin: 20px 0 4px; }
  #status { position: sticky; top: 0; background: #fff8e1; border: 1px solid #ffc107;
            border-radius:6px; padding: 8px 16px; margin-bottom:16px; font-weight:bold; }
</style>
</head>
<body>
<h1>TeacherPlus — WordPress XML Import</h1>
<div id="status">⏳ Running… please wait, images are being downloaded.</div>
<?php
flush();

// ─── MAIN ─────────────────────────────────────────────────────────────────────

if (empty($xml_files)) {
    out("<p class='err'>No XML files found. Drop your .xml exports into <code>teacherplus/imports/</code> and refresh.</p>");
}

$grand_inserted = $grand_updated = $grand_errors = 0;

foreach ($xml_files as $xml_path) {
    $filename = basename($xml_path);
    out("<h3>📄 $filename</h3>");

    libxml_use_internal_errors(true);
    $raw = ltrim(file_get_contents($xml_path), "\xEF\xBB\xBF");
    $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);

    if (!$xml) {
        $err = libxml_get_errors()[0]->message ?? 'unknown error';
        libxml_clear_errors();
        out("<p class='err'>✗ Could not parse XML: " . htmlspecialchars($err) . "</p>");
        $grand_errors++;
        continue;
    }

    $channel       = $xml->channel;
    $attachmentMap = buildAttachmentMap($channel);
    $ns_dc      = 'http://purl.org/dc/elements/1.1/';
    $ns_content = 'http://purl.org/rss/1.0/modules/content/';
    $ns_excerpt = 'http://wordpress.org/export/1.2/excerpt/';

    $f_inserted = $f_updated = 0;

    foreach ($channel->item as $item) {
        $wp = $item->children('wp', true);   // prefix → true
        $dc = $item->children($ns_dc);       // URI    → false (default)
        $co = $item->children($ns_content);  // URI    → false (default)
        $ex = $item->children($ns_excerpt);  // URI    → false (default)

        $post_type   = xmlText($wp->post_type);
        $post_status = xmlText($wp->status);

        if ($post_type !== 'post') continue;
        if (!in_array($post_status, ['publish', 'future'], true)) continue;

        // ── Core fields ───────────────────────────────────────────────────────
        $title      = xmlText($item->title);
        $body       = stripWpBlocks(xmlText($co->encoded));
        $excerpt    = stripWpBlocks(xmlText($ex->encoded));
        $created_at = xmlText($wp->post_date);
        $updated_at = xmlText($wp->post_modified);

        // ── Status & scheduled_date ───────────────────────────────────────────
        if ($post_status === 'future') {
            $status         = 'scheduled';
            $scheduled_date = $created_at;
        } else {
            $status         = 'published';
            $scheduled_date = null;
        }

        // ── Author ────────────────────────────────────────────────────────────
        $author_login = xmlText($dc->creator);
        $user_id      = resolveUserId($conn, $author_login);
        $author_name  = strtolower($author_login);

        // ── Categories & Tags ─────────────────────────────────────────────────
        $category    = '';
        $description = '';
        $tags_arr    = [];

        foreach ($item->category as $cat) {
            $domain   = (string) $cat['domain'];
            $nicename = (string) $cat['nicename'];
            $label    = xmlText($cat);

            if ($domain === 'category') {
                if (isMonthYear($nicename)) {
                    $description = $label;
                } else {
                    $category = $label;
                }
            } elseif ($domain === 'post_tag') {
                $tags_arr[] = $label;
            }
        }

        $tags = implode(', ', $tags_arr);

        // ── Featured image ────────────────────────────────────────────────────
        $featured_image = '';
        foreach ($wp->postmeta as $meta) {
            if (xmlText($meta->meta_key) === '_thumbnail_id') {
                $thumb_id       = (int) xmlText($meta->meta_value);
                $featured_image = $attachmentMap[$thumb_id] ?? '';
                break;
            }
        }

        // ── Download images ───────────────────────────────────────────────────
        out("<div style='color:#888;font-size:.9em'>⬇ Downloading images for: <em>" . htmlspecialchars($title) . "</em></div>");

        if ($featured_image) {
            $featured_image = downloadImage($featured_image);
        }

        // Rewrite every src="https://teacherplus.org/..." in body
        $body = preg_replace_callback(
            '/(src=["\'])(' . preg_quote(IMG_SOURCE_HOST, '/') . '[^"\']+)(["\'])/i',
            fn(array $m) => $m[1] . downloadImage($m[2]) . $m[3],
            $body
        );

        // ── Upsert ────────────────────────────────────────────────────────────
        $chk = $conn->prepare(
            "SELECT id FROM articles WHERE title = ? AND DATE(created_at) = DATE(?) LIMIT 1"
        );
        $chk->bind_param('ss', $title, $created_at);
        $chk->execute();
        $chk->bind_result($existing_id);
        $exists = $chk->fetch();
        $chk->close();

        if ($exists) {
            $stmt = $conn->prepare(
                "UPDATE articles SET
                    user_id = ?, author_name = ?, excerpt = ?, description = ?,
                    body = ?, featured_image = ?, category = ?, tags = ?,
                    status = ?, scheduled_date = ?, updated_at = ?
                WHERE id = ?"
            );
            $stmt->bind_param(
                'issssssssssi',
                $user_id, $author_name, $excerpt, $description, $body,
                $featured_image, $category, $tags,
                $status, $scheduled_date, $updated_at, $existing_id
            );
            if ($stmt->execute()) {
                out("<div class='upd'>↻ Updated: " . htmlspecialchars($title) . "</div>");
                $f_updated++; $grand_updated++;
            } else {
                out("<div class='err'>✗ Update error: " . htmlspecialchars($title) . " — " . htmlspecialchars($stmt->error) . "</div>");
                $grand_errors++;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO articles
                    (user_id, author_name, title, excerpt, description, body,
                     featured_image, category, tags, status, scheduled_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issssssssssss',
                $user_id, $author_name, $title, $excerpt, $description, $body,
                $featured_image, $category, $tags,
                $status, $scheduled_date, $created_at, $updated_at
            );
            if ($stmt->execute()) {
                out("<div class='ok'>✓ Inserted: " . htmlspecialchars($title) . "</div>");
                $f_inserted++; $grand_inserted++;
            } else {
                out("<div class='err'>✗ Insert error: " . htmlspecialchars($title) . " — " . htmlspecialchars($stmt->error) . "</div>");
                $grand_errors++;
            }
            $stmt->close();
        }
    }

    out("<p>File total → <b class='ok'>$f_inserted inserted</b>, <b class='upd'>$f_updated updated</b></p><hr>");
}

// Update the sticky status bar via JS now that we're done
?>
<script>
  document.getElementById('status').style.background = '#eef6ee';
  document.getElementById('status').style.borderColor = '#4caf50';
  document.getElementById('status').innerHTML =
    '✅ Done — <b><?= $grand_inserted ?> inserted</b>, <b><?= $grand_updated ?> updated</b>, <b><?= $grand_errors ?> errors</b>';
</script>
</body>
</html>