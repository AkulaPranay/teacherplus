<?php
/**
 * TeacherPlus — WordPress Multi-File Importer v5
 * Fixed: iterator_to_array bug replaced with direct foreach + counter
 */

require 'includes/config.php';

// ── HARD RESET ────────────────────────────────────────────────────────────────
if (isset($_GET['reset'])) {
    $conn->query("DROP TABLE IF EXISTS _import_progress");
    header("Location: import_wp.php"); exit;
}

// ── XML INSPECTOR ─────────────────────────────────────────────────────────────
if (isset($_GET['inspect'])) {
    header('Content-Type: text/plain');
    $fname = basename($_GET['inspect']);
    $path  = __DIR__ . '/' . $fname;
    if (!file_exists($path)) { echo "File not found: $path"; exit; }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) { echo "XML parse error"; exit; }
    $ns = $xml->getNamespaces(true);
    $sc = []; $tc = [];
    foreach ($xml->channel->item as $item) {
        $wp = isset($ns['wp']) ? $item->children($ns['wp']) : null;
        $s  = $wp ? (string)$wp->status    : 'unknown';
        $t  = $wp ? (string)$wp->post_type : 'unknown';
        $sc[$s] = ($sc[$s] ?? 0) + 1;
        $tc[$t] = ($tc[$t] ?? 0) + 1;
    }
    echo "Total: " . count($xml->channel->item) . "\nBy status:\n";
    foreach ($sc as $s=>$c) echo "  '$s': $c\n";
    echo "\nBy post_type:\n";
    foreach ($tc as $t=>$c) echo "  '$t': $c\n";
    exit;
}

// ── CONFIG ────────────────────────────────────────────────────────────────────
$staff_users = [
    ['username'=>'shalini',   'full_name'=>'Shalini',    'email'=>'shalini@teacherplus.org',   'password'=>'staff@123'],
    ['username'=>'usharaman', 'full_name'=>'Usha Raman', 'email'=>'usharaman@teacherplus.org', 'password'=>'staff@123'],
    ['username'=>'kumar',     'full_name'=>'Kumar',      'email'=>'kumar@teacherplus.org',     'password'=>'staff@123'],
];

$files = [
    ['file'=>'readers-blog.xml',   'label'=>'Readers Blog',      'category'=>'Readers Blog', 'author'=>'Shalini',    'username'=>'shalini'],
    ['file'=>'editorial.xml',      'label'=>'Editorial',          'category'=>'Editorial',    'author'=>'Usha Raman', 'username'=>'usharaman'],
    ['file'=>'articles-part1.xml', 'label'=>'Articles (Part 1)', 'category'=>'',             'author'=>'Kumar',      'username'=>'kumar'],
    ['file'=>'articles-part2.xml', 'label'=>'Articles (Part 2)', 'category'=>'',             'author'=>'Kumar',      'username'=>'kumar'],
];

// ── SETUP ─────────────────────────────────────────────────────────────────────
if ($conn->query("SHOW COLUMNS FROM articles LIKE 'user_id'")->num_rows === 0) {
    $conn->query("ALTER TABLE articles ADD COLUMN user_id INT DEFAULT NULL AFTER id");
    $conn->query("ALTER TABLE articles ADD INDEX idx_user_id (user_id)");
}

$user_id_map = [];
foreach ($staff_users as $su) {
    $uname = $conn->real_escape_string($su['username']);
    $row   = $conn->query("SELECT id FROM users WHERE username='$uname' LIMIT 1")->fetch_assoc();
    if ($row) {
        $user_id_map[$su['username']] = (int)$row['id'];
    } else {
        $hash = password_hash($su['password'], PASSWORD_DEFAULT);
        $fn   = $conn->real_escape_string($su['full_name']);
        $em   = $conn->real_escape_string($su['email']);
        $conn->query("INSERT INTO users (username,full_name,email,password,role) VALUES ('$uname','$fn','$em','$hash','staff')");
        $user_id_map[$su['username']] = (int)$conn->insert_id;
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS _import_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_key VARCHAR(100) NOT NULL UNIQUE,
    total INT DEFAULT 0, imported INT DEFAULT 0, skipped INT DEFAULT 0,
    status ENUM('pending','running','done','error') DEFAULT 'pending',
    message TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
foreach ($files as $f) {
    $k = $conn->real_escape_string($f['file']);
    $conn->query("INSERT IGNORE INTO _import_progress (file_key,status) VALUES ('$k','pending')");
}

// ── AJAX: IMPORT BATCH ────────────────────────────────────────────────────────
if (isset($_GET['run'])) {
    header('Content-Type: application/json');

    $file_key = $_GET['run'];
    $def = null;
    foreach ($files as $f) { if ($f['file'] === $file_key) { $def = $f; break; } }
    if (!$def) { echo json_encode(['error'=>'Unknown file']); exit; }

    $xml_path = __DIR__ . '/' . $def['file'];
    if (!file_exists($xml_path)) {
        $k = $conn->real_escape_string($file_key);
        $conn->query("UPDATE _import_progress SET status='error',message='File not found' WHERE file_key='$k'");
        echo json_encode(['error'=>'File not found: '.$def['file']]); exit;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xml_path, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        $msg = implode('; ', array_map(fn($e)=>$e->message, array_slice(libxml_get_errors(),0,3)));
        $k   = $conn->real_escape_string($file_key);
        $conn->query("UPDATE _import_progress SET status='error',message='XML error' WHERE file_key='$k'");
        echo json_encode(['error'=>$msg]); exit;
    }

    $ns     = $xml->getNamespaces(true);
    $items  = $xml->channel->item;
    $total  = count($items);
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $batch  = 50;
    $imported = $skipped = 0;
    $author_user_id = $user_id_map[$def['username']] ?? null;

    // ── FIX: use index-based loop instead of iterator_to_array ──
    $idx = 0;
    foreach ($items as $item) {
        // Skip items before our offset
        if ($idx < $offset) { $idx++; continue; }
        // Stop after batch size
        if ($idx >= $offset + $batch) break;
        $idx++;

        $wp = isset($ns['wp']) ? $item->children($ns['wp']) : null;
        $dc = isset($ns['dc']) ? $item->children($ns['dc']) : null;

        $post_type = $wp ? trim((string)$wp->post_type) : 'post';
        if (!in_array($post_type, ['post',''])) { $skipped++; continue; }

        $status = $wp ? trim((string)$wp->status) : 'publish';
        if ($status !== 'publish') { $skipped++; continue; }

        $title = trim((string)$item->title);
        if (empty($title)) { $skipped++; continue; }

        $body    = isset($ns['content']) ? (string)$item->children($ns['content'])->encoded : (string)$item->description;
        $pub     = (string)$item->pubDate;
        $created_at = $pub ? date('Y-m-d H:i:s', strtotime($pub)) : date('Y-m-d H:i:s');

        $author_name = $def['author'];
        if ($dc && !empty(trim((string)$dc->creator))) $author_name = trim((string)$dc->creator);

        $category = $def['category'];
        if (empty($category)) {
            foreach ($item->category as $cat) {
                if ((string)$cat->attributes()['domain'] === 'category') { $category = (string)$cat; break; }
            }
        }

        $tags_arr = [];
        foreach ($item->category as $cat) {
            if ((string)$cat->attributes()['domain'] === 'post_tag') $tags_arr[] = (string)$cat;
        }
        $tags    = implode(', ', $tags_arr);
        $excerpt = isset($ns['excerpt']) ? trim((string)$item->children($ns['excerpt'])->encoded) : '';

        // Skip duplicates
        $t = $conn->real_escape_string($title);
        if ($conn->query("SELECT id FROM articles WHERE title='$t' LIMIT 1")->num_rows > 0) { $skipped++; continue; }

        $stmt = $conn->prepare("INSERT INTO articles (user_id,title,excerpt,body,author_name,category,tags,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'published',?,?)");
        $stmt->bind_param("issssssss", $author_user_id, $title, $excerpt, $body, $author_name, $category, $tags, $created_at, $created_at);
        $stmt->execute();
        $imported++;
    }

    $done  = ($offset + $batch) >= $total;
    $k_esc = $conn->real_escape_string($file_key);
    $conn->query("UPDATE _import_progress SET total=$total, imported=imported+$imported, skipped=skipped+$skipped, status='".($done?'done':'running')."' WHERE file_key='$k_esc'");

    echo json_encode(['total'=>$total,'offset'=>$offset+$batch,'imported'=>$imported,'skipped'=>$skipped,'done'=>$done]);
    exit;
}

// ── HTML ──────────────────────────────────────────────────────────────────────
$files_exist = [];
foreach ($files as $f) $files_exist[$f['file']] = file_exists(__DIR__.'/'.$f['file']);
$all_present = !in_array(false, $files_exist, true);

$progress = [];
foreach ($files as $f) {
    $k = $conn->real_escape_string($f['file']);
    $progress[$f['file']] = $conn->query("SELECT * FROM _import_progress WHERE file_key='$k' LIMIT 1")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>WordPress Importer — TeacherPlus</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f8;color:#333;padding:40px 20px}
.wrap{max-width:820px;margin:0 auto}
h1{font-size:1.4rem;font-weight:700;color:#1f2a4a;margin-bottom:4px}
.sub{font-size:13px;color:#888;margin-bottom:28px}
.accounts-box{background:#fff;border:1px solid #e8eaf0;border-radius:12px;padding:20px 24px;margin-bottom:22px}
.accounts-box h3{font-size:13px;font-weight:700;color:#1f2a4a;margin-bottom:12px}
.accounts-box h3 span{color:#f87407}
.acc-table{width:100%;border-collapse:collapse;font-size:13px}
.acc-table th{text-align:left;padding:8px 12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#9aa0b4;border-bottom:2px solid #f0f2f8}
.acc-table td{padding:9px 12px;border-bottom:1px solid #f5f6fa}
.acc-table tr:last-child td{border-bottom:none}
.badge-staff{background:rgba(248,116,7,0.1);color:#f87407;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600}
.badge-new{background:#e8f5e9;color:#2e7d32;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600}
.badge-exists{background:#f0f2f8;color:#9aa0b4;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600}
code{background:#f0f0f0;border-radius:4px;padding:1px 7px;font-size:12px;font-family:monospace;color:#c0392b}
.inspect-links{background:#f8f9fc;border:1px solid #e8eaf0;border-radius:10px;padding:12px 18px;margin-bottom:22px;font-size:12.5px}
.inspect-links a{color:#3d348b;margin-right:14px;text-decoration:none;font-weight:600}
.inspect-links a:hover{color:#f87407}
.file-card{background:#fff;border-radius:12px;border:1.5px solid #eef0f6;padding:20px 22px;margin-bottom:12px;transition:all 0.2s}
.file-card.running{border-color:#f87407;box-shadow:0 0 0 3px rgba(248,116,7,0.08)}
.file-card.done{border-color:#28a745}
.file-card.error,.file-card.missing{border-color:#dc3545}
.fc-header{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.fc-icon{width:38px;height:38px;border-radius:9px;background:#1f2a4a;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.fc-label{font-weight:700;color:#1f2a4a;font-size:13.5px}
.fc-file{font-size:11px;color:#aaa;font-family:monospace;margin-top:2px}
.fc-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.fc-author{font-size:12px;color:#3d348b;font-weight:600;background:rgba(61,52,139,0.08);padding:3px 10px;border-radius:20px}
.fc-status{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;padding:3px 10px;border-radius:20px}
.s-pending{background:#f0f2f8;color:#9aa0b4}
.s-running{background:rgba(248,116,7,0.12);color:#f87407}
.s-done{background:#e8f5e9;color:#28a745}
.s-error,.s-missing{background:#fce4e4;color:#dc3545}
.bar-wrap{height:7px;background:#f0f2f8;border-radius:4px;overflow:hidden;margin-bottom:10px}
.bar-fill{height:100%;background:#f87407;border-radius:4px;width:0;transition:width 0.35s ease}
.bar-fill.done{background:#28a745}
.fc-stats{display:flex;gap:18px;font-size:12px;color:#aaa;flex-wrap:wrap}
.fc-stats strong{color:#1f2a4a}
.missing-note{font-size:12px;color:#dc3545;margin-top:10px;padding:8px 12px;background:#fff0f0;border-radius:6px}
.bottom-bar{background:#fff;border-radius:12px;border:1px solid #e8eaf0;padding:18px 22px;display:flex;align-items:center;gap:12px;margin-top:6px;flex-wrap:wrap}
#btn-start{background:#f87407;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:background 0.2s}
#btn-start:hover{background:#d96400}
#btn-start:disabled{background:#ccc;cursor:not-allowed}
.btn-reset{background:#fff;color:#dc3545;border:1px solid #f5c2c7;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;display:inline-block}
.btn-reset:hover{background:#dc3545;color:#fff}
.overall-label{font-size:13px;color:#888}
.overall-label strong{color:#1f2a4a}
.done-box{background:#e8f5e9;border:1px solid #c8e6c9;border-radius:10px;padding:18px 22px;margin-top:16px;font-size:13.5px;color:#2e7d32;line-height:1.9}
.done-box strong{color:#1b5e20}
</style>
</head>
<body>
<div class="wrap">

<h1>📥 WordPress Importer v5</h1>
<p class="sub">Creates staff accounts · Imports published posts · Links articles to users</p>

<div class="accounts-box">
    <h3>👤 Staff accounts <span>(password: staff@123)</span></h3>
    <table class="acc-table">
        <tr><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th></tr>
        <?php foreach ($staff_users as $su):
            $uname  = $conn->real_escape_string($su['username']);
            $exists = $conn->query("SELECT id FROM users WHERE username='$uname' LIMIT 1")->num_rows > 0;
        ?>
        <tr>
            <td><code><?php echo htmlspecialchars($su['username']); ?></code></td>
            <td><?php echo htmlspecialchars($su['full_name']); ?></td>
            <td><?php echo htmlspecialchars($su['email']); ?></td>
            <td><span class="badge-staff">Staff</span></td>
            <td><span class="<?php echo $exists?'badge-exists':'badge-new'; ?>"><?php echo $exists?'Already exists':'Will be created'; ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="inspect-links">
    🔍 <strong>Inspect:</strong>
    <?php foreach ($files as $f): ?>
        <a href="?inspect=<?php echo urlencode($f['file']); ?>" target="_blank"><?php echo $f['label']; ?></a>
    <?php endforeach; ?>
</div>

<?php foreach ($files as $f):
    $exists     = $files_exist[$f['file']];
    $uid        = $user_id_map[$f['username']] ?? '?';
    $prog       = $progress[$f['file']];
    $cur_status = $exists ? ($prog['status'] ?? 'pending') : 'missing';
    $labels_map = ['pending'=>'Pending','running'=>'Importing…','done'=>'Done ✓','error'=>'Error ✗','missing'=>'Missing'];
?>
<div class="file-card <?php echo $cur_status; ?>" id="card-<?php echo $f['file']; ?>">
    <div class="fc-header">
        <div class="fc-icon">📄</div>
        <div>
            <div class="fc-label"><?php echo htmlspecialchars($f['label']); ?></div>
            <div class="fc-file"><?php echo htmlspecialchars($f['file']); ?></div>
        </div>
        <div class="fc-right">
            <span class="fc-author"><?php echo htmlspecialchars($f['author']); ?></span>
            <span class="fc-status s-<?php echo $cur_status; ?>" id="status-<?php echo $f['file']; ?>">
                <?php echo $labels_map[$cur_status] ?? $cur_status; ?>
            </span>
        </div>
    </div>
    <div class="bar-wrap">
        <div class="bar-fill <?php echo $cur_status==='done'?'done':''; ?>"
             id="bar-<?php echo $f['file']; ?>"
             style="width:<?php echo $cur_status==='done'?'100':'0'; ?>%"></div>
    </div>
    <div class="fc-stats">
        <span>Total: <strong id="total-<?php echo $f['file']; ?>"><?php echo $prog['total']??'—'; ?></strong></span>
        <span>Imported: <strong id="imported-<?php echo $f['file']; ?>"><?php echo $prog['imported']??0; ?></strong></span>
        <span>Skipped: <strong id="skipped-<?php echo $f['file']; ?>"><?php echo $prog['skipped']??0; ?></strong></span>
        <span style="margin-left:auto">user_id: <strong style="color:#3d348b"><?php echo $uid; ?></strong></span>
    </div>
    <?php if (!$exists): ?>
        <div class="missing-note">⚠️ Upload <code><?php echo $f['file']; ?></code> to the project root first</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="bottom-bar">
    <button id="btn-start" <?php echo $all_present?'':'disabled'; ?> onclick="startImport()">▶ Start Import</button>
    <a class="btn-reset" href="?reset=1" onclick="return confirm('Drop progress table and start completely fresh?')">↺ Hard Reset</a>
    <div class="overall-label" id="overall-label">
        <?php echo $all_present ? 'Ready — click Start Import.' : '⚠️ Upload the missing XML files first.'; ?>
    </div>
</div>

<div id="done-box" style="display:none" class="done-box">
    ✅ <strong>Import complete!</strong><br>
    All articles imported and linked to staff accounts.<br><br>
    <strong>Staff logins:</strong> shalini / staff@123 &nbsp;|&nbsp; usharaman / staff@123 &nbsp;|&nbsp; kumar / staff@123<br><br>
    ⚠️ <strong>Delete <code>import_wp.php</code> and all 4 XML files from your server now.</strong>
</div>

</div>
<script>
const files = <?php echo json_encode(array_column($files,'file')); ?>;
let totalImported=0, totalSkipped=0;

async function startImport(){
    document.getElementById('btn-start').disabled = true;
    document.getElementById('overall-label').textContent = 'Importing — please wait…';
    for (const file of files) {
        if (document.getElementById('card-'+file).classList.contains('missing')) continue;
        await importFile(file);
    }
    document.getElementById('overall-label').innerHTML =
        '✅ Done! <strong>'+totalImported+'</strong> imported, <strong>'+totalSkipped+'</strong> skipped.';
    document.getElementById('btn-start').textContent = '✓ Complete';
    document.getElementById('done-box').style.display = 'block';
}

async function importFile(fileKey){
    setStatus(fileKey,'running');
    let offset=0, fileImported=0, fileSkipped=0;
    while(true){
        let data;
        try{
            const res = await fetch('?run='+encodeURIComponent(fileKey)+'&offset='+offset+'&t='+Date.now());
            data = await res.json();
        } catch(e){
            setStatus(fileKey,'error');
            document.getElementById('total-'+fileKey).textContent='Network error';
            return;
        }
        if(data.error){
            setStatus(fileKey,'error');
            document.getElementById('total-'+fileKey).textContent=data.error;
            return;
        }
        fileImported += data.imported;
        fileSkipped  += data.skipped;
        offset        = data.offset;
        document.getElementById('total-'+fileKey).textContent    = data.total;
        document.getElementById('imported-'+fileKey).textContent = fileImported;
        document.getElementById('skipped-'+fileKey).textContent  = fileSkipped;
        const pct = data.total>0 ? Math.min(100,Math.round((offset/data.total)*100)) : 100;
        document.getElementById('bar-'+fileKey).style.width = pct+'%';
        if(data.done){
            document.getElementById('bar-'+fileKey).style.width='100%';
            document.getElementById('bar-'+fileKey).classList.add('done');
            setStatus(fileKey,'done');
            totalImported += fileImported;
            totalSkipped  += fileSkipped;
            return;
        }
        await new Promise(r=>setTimeout(r,150));
    }
}

function setStatus(k,s){
    const labels={pending:'Pending',running:'Importing…',done:'Done ✓',error:'Error ✗',missing:'Missing'};
    document.getElementById('card-'+k).className='file-card '+s;
    const b=document.getElementById('status-'+k);
    b.className='fc-status s-'+s;
    b.textContent=labels[s]||s;
}
</script>
</body>
</html>