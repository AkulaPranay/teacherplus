<?php


require '../includes/config.php';
require '../includes/functions.php';

$page_title = "Article - TeacherPlus";

// IMPORTANT: Check access BEFORE including header
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id <= 0) {
    die("Invalid article ID");
}

if (!canViewArticle($article_id)) {
    header("Location: restricted-article.php");
    exit;
}

// Now include header (after access check)
include '../includes/header.php';

// Fetch the article
$stmt = $conn->prepare("
    SELECT id, title, excerpt, description, body, featured_image, 
           author_name, category, tags, status, scheduled_date, created_at
    FROM articles
    WHERE id = ? AND status = 'published'
");
$stmt->bind_param("i", $article_id);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();

if (!$article) {
    die("Article not found or not published.");
}

// Rest of your variables
$date = date('F d, Y', strtotime($article['created_at']));
$author = htmlspecialchars($article['author_name']);
$tags = array_filter(array_map('trim', explode(',', $article['tags'] ?? '')));

// Rewrite old WordPress image URLs in body to local paths
function rewriteBodyImages($html) {
    return preg_replace_callback(
        '/(<img\s[^>]*src\s*=\s*["\'])https?:\/\/[^\/]+\/([^"\']+)(["\'])/i',
        function ($m) {
            $filename  = basename($m[2]);
            $localPath = '../assets/uploads/body-images/' . $filename;
            return $m[1] . $localPath . $m[3];
        },
        $html
    );
}

// Build featured image URL
$featured_image_url = '';
if (!empty($article['featured_image'])) {
    $img = $article['featured_image'];
    // If already absolute URL, use as-is
    if (strpos($img, 'http') === 0) {
        $featured_image_url = $img;
    } else {
        // Strip leading slashes and any leading '../' or './'
        $img = ltrim($img, '/.');
        $img = preg_replace('#^(assets/)#', '../assets/', $img);
        if (strpos($img, '../') === false) {
            $img = '../' . ltrim($img, '/');
        }
        $featured_image_url = $img;
    }
}

// Archives
$archive_result = $conn->query("
    SELECT YEAR(created_at) AS yr, MONTH(created_at) AS mo,
           MONTHNAME(created_at) AS month_name, COUNT(*) AS cnt
    FROM articles
    WHERE status = 'published'
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY yr DESC, mo DESC
");
$archives = [];
while ($row = $archive_result->fetch_assoc()) {
    $archives[$row['yr']]['total'] = ($archives[$row['yr']]['total'] ?? 0) + $row['cnt'];
    $archives[$row['yr']]['months'][] = $row;
}
$current_year = (int)date('Y');

// Fetch active ads - Simplified (no start_date/end_date)
function getActiveAds($conn, $position) {
    $stmt = $conn->prepare("
        SELECT * FROM advertisements 
        WHERE status = 'active' 
        AND position = ?
    ");
    $stmt->bind_param("s", $position);
    $stmt->execute();
    return $stmt->get_result();
}

$ad_top      = getActiveAds($conn, 'article_top');
$ad_bottom   = getActiveAds($conn, 'article_bottom');
$ad_sidebar  = getActiveAds($conn, 'sidebar_right');
$ad_inline   = getActiveAds($conn, 'article_inline');   // Half-page ad
?>

<style>
/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; background: #fff; }
a { color: #333; text-decoration: none; }
a:hover { color: #f87407; }

/* Layout */
.tp-article-page {
    max-width: 1080px;
    margin: 24px auto 60px;
    padding: 0 16px;
    display: grid;
    grid-template-columns: 1fr 240px;
    gap: 40px;
    align-items: start;
}

.tp-article-main { min-width: 0; }

.tp-article-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f87407;
    line-height: 1.25;
    margin-bottom: 3px;
}

.tp-article-author {
    font-size: 13px;
    color: #555;
    margin-bottom: 18px;
}

.tp-featured-image {
    width: 100%;
    height: auto;
    display: block;
    margin-bottom: 20px;
    border-radius: 4px;
}

.tp-article-body {
    font-size: 13.5px;
    line-height: 1.82;
    color: #333;
}
.tp-article-body p { margin-bottom: 1.15em; }
.tp-article-body img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 14px auto;
}

/* Half-page inline ad */
.tp-inline-ad {
    text-align: center;
    margin: 45px 0;
    padding: 25px 15px;
    background: #f9f9f9;
    border: 1px dashed #ddd;
    border-radius: 8px;
}

/* Tags */
.tp-tags {
    margin-top: 22px;
    padding-top: 12px;
    border-top: 1px solid #e8e8e8;
    font-size: 13px;
    line-height: 2.4;
}
.tp-tags strong { margin-right: 4px; }
.tp-tags a {
    display: inline-block;
    background: #f2f2f2;
    color: #444;
    border-radius: 3px;
    padding: 1px 9px;
    margin: 2px 3px 2px 0;
    font-size: 12px;
    border: 1px solid #e0e0e0;
}
.tp-tags a:hover { background: #f87407; color: #fff; border-color: #f87407; }

.tp-issue { font-size: 12px; color: #888; margin-top: 6px; font-style: italic; }

.tp-discuss-line {
    margin-top: 18px;
    font-size: 13px;
    color: #444;
    padding-top: 10px;
    border-top: 1px solid #eee;
}
.tp-discuss-line a { color: #f87407; font-weight: 600; }

/* Sidebar */
.tp-sidebar { display: flex; flex-direction: column; gap: 24px; }

.tp-archives h4 {
    font-size: 14px;
    font-weight: 700;
    color: #222;
    padding-bottom: 5px;
    border-bottom: 2px solid #f87407;
    margin-bottom: 10px;
}
.tp-archive-tree { list-style: none; line-height: 1; }
.tp-archive-tree > li { margin-bottom: 0; }

.tp-yr-btn {
    background: none;
    border: none;
    padding: 4px 0;
    cursor: pointer;
    font-size: 13px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 4px;
    width: 100%;
    text-align: left;
}
.tp-yr-btn:hover { color: #f87407; }
.tp-yr-btn .arr { font-size: 8px; color: #666; display: inline-block; transition: transform .15s; }
.tp-yr-btn.open .arr { transform: rotate(90deg); }

.tp-month-list {
    list-style: none;
    padding-left: 14px;
    display: none;
    margin-bottom: 4px;
}
.tp-month-list.open { display: block; }
.tp-month-list li { line-height: 1.9; }
.tp-month-list li a { font-size: 12.5px; color: #444; }
.tp-month-list li a:hover { color: #f87407; }

.tp-login-box {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 14px 16px;
    font-size: 13px;
    color: #444;
    line-height: 1.7;
}
.tp-login-box a { color: #f87407; font-weight: 600; }

/* Responsive */
@media (max-width: 768px) {
    .tp-article-page { grid-template-columns: 1fr; }
    .tp-sidebar { order: -1; }
}
</style>

<div class="tp-article-page">

    <!-- LEFT: Article -->
    <main class="tp-article-main">

        <!-- Top Ad -->
        <?php if ($ad_top->num_rows > 0): ?>
            <div class="text-center mb-4">
                <?php while ($ad = $ad_top->fetch_assoc()): ?>
                    <a href="<?= htmlspecialchars($ad['link_url'] ?? '#') ?>" target="_blank">
                        <img src="../<?= htmlspecialchars($ad['image_path']) ?>" class="img-fluid" style="max-height:90px;">
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <?php if ($featured_image_url): ?>
            <img src="<?= htmlspecialchars($featured_image_url) ?>" 
                 alt="<?= htmlspecialchars($article['title']) ?>" 
                 class="tp-featured-image">
        <?php endif; ?>

        <h1 class="tp-article-title"><?= htmlspecialchars($article['title']) ?></h1>
        <div class="tp-article-author"><?= $author ?> &mdash; <?= $date ?></div>

        <div class="tp-article-body">
            <?php
            // Show excerpt/description if present
            if (!empty($article['description'])) {
                echo '<p>' . nl2br(htmlspecialchars($article['description'])) . '</p>';
            }

            // Body is rich HTML from editor — output directly
            $body_html = rewriteBodyImages($article['body'] ?? '');
            
            if ($ad_inline->num_rows > 0) {
                // Split on closing paragraph tags to inject ad in the middle
                $parts = preg_split('/(<\/p>)/i', $body_html, -1, PREG_SPLIT_DELIM_CAPTURE);
                // Rebuild: each pair is [text, </p>]
                $chunks = [];
                for ($i = 0; $i < count($parts) - 1; $i += 2) {
                    $chunks[] = $parts[$i] . ($parts[$i+1] ?? '');
                }
                // Remainder
                $remainder = (count($parts) % 2 === 1) ? $parts[count($parts)-1] : '';

                $total = count($chunks);
                $mid   = max(2, (int)($total / 2));
                $ad_injected = false;

                foreach ($chunks as $idx => $chunk) {
                    echo $chunk;
                    if ($idx + 1 === $mid && !$ad_injected) {
                        $ad_inline->data_seek(0);
                        echo '<div class="tp-inline-ad">';
                        while ($ad = $ad_inline->fetch_assoc()) {
                            if (!empty($ad['image_path'])) {
                                echo '<a href="' . htmlspecialchars($ad['link_url'] ?? '#') . '" target="_blank">';
                                echo '<img src="../' . htmlspecialchars($ad['image_path']) . '" style="max-height:140px;max-width:100%;">';
                                echo '</a>';
                            }
                        }
                        echo '</div>';
                        $ad_injected = true;
                    }
                }
                echo $remainder;
            } else {
                echo $body_html;
            }
            ?>
        </div>

        <?php if (!empty($tags)): ?>
            <div class="tp-tags">
                <strong>Tags:</strong>
                <?php foreach ($tags as $tag): ?>
                    <a href="/tag/<?= urlencode($tag) ?>"><?= htmlspecialchars($tag) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
            $issue_parts = [];
            if (!empty($article['category'])) $issue_parts[] = htmlspecialchars($article['category']);
            $issue_parts[] = date('F Y', strtotime($article['created_at']));
        ?>
        <div class="tp-issue"><?= implode(', ', $issue_parts) ?></div>

        <?php if (!$is_logged_in): ?>
            <div class="tp-discuss-line">
                Please <a href="login.php">login</a> to join discussion
            </div>
        <?php endif; ?>

    </main>

    <!-- RIGHT: Sidebar -->
    <aside class="tp-sidebar">

        <?php if ($ad_sidebar->num_rows > 0): ?>
            <div class="mb-4 text-center">
                <?php while ($ad = $ad_sidebar->fetch_assoc()): ?>
                    <a href="<?= htmlspecialchars($ad['link_url'] ?? '#') ?>" target="_blank">
                        <img src="../<?= htmlspecialchars($ad['image_path']) ?>" class="img-fluid" style="max-height:150px;">
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <div class="tp-archives">
            <h4>Archives</h4>
            <ul class="tp-archive-tree">
                <?php foreach ($archives as $yr => $data): ?>
                <li>
                    <button class="tp-yr-btn <?= ($yr == $current_year) ? 'open' : '' ?>" onclick="toggleYr(this)">
                        <span class="arr">▶</span>
                        <?= $yr ?> (<?= $data['total'] ?>)
                    </button>
                    <ul class="tp-month-list <?= ($yr == $current_year) ? 'open' : '' ?>">
                        <?php foreach ($data['months'] as $m): ?>
                            <li>
                                <a href="/archives/<?= $yr ?>/<?= sprintf('%02d', $m['mo']) ?>">
                                    <?= $m['month_name'] ?> (<?= $m['cnt'] ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!$is_logged_in): ?>
            <div class="tp-login-box">
                Please <a href="login.php">login</a> to join discussion
            </div>
        <?php endif; ?>

    </aside>

    <!-- Bottom Ad -->
    <?php if ($ad_bottom->num_rows > 0): ?>
        <div class="text-center mt-5">
            <?php while ($ad = $ad_bottom->fetch_assoc()): ?>
                <a href="<?= htmlspecialchars($ad['link_url'] ?? '#') ?>" target="_blank">
                    <img src="../<?= htmlspecialchars($ad['image_path']) ?>" class="img-fluid" style="max-height:90px;">
                </a>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleYr(btn) {
    btn.classList.toggle('open');
    btn.nextElementSibling.classList.toggle('open');
}
</script>

<?php include '../includes/footer.php'; ?>