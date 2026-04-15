<?php
require '../includes/config.php';

$category = trim($_GET['category'] ?? '');

// If no category selected, redirect to home — dropdown handles navigation
if (!$category) {
    header("Location: index.php");
    exit;
}

$page_title = htmlspecialchars($category) . " - TeacherPlus";

$articles = [];
$q = $conn->prepare("
    SELECT id, title, excerpt, featured_image, author_name, category, description, created_at
    FROM articles
    WHERE status = 'published' AND category = ?
    ORDER BY created_at DESC
");
$q->bind_param("s", $category);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $articles[] = $row;
}

include '../includes/header.php';
?>

<style>
    body { background: #f7f7f7; }
    .sections-page { max-width: 900px; margin: 40px auto 80px; padding: 0 24px; }
    .sections-heading { font-size: 1.5rem; font-weight: 700; color: #f87407; margin-bottom: 28px; }
    .article-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 22px 26px; margin-bottom: 16px; }
    .article-card-title { font-size: 1.05rem; font-weight: 600; margin-bottom: 6px; }
    .article-card-title::before { content: "• "; color: #1a237e; }
    .article-card-title a { color: #1a237e; text-decoration: none; transition: color 0.15s; }
    .article-card-title a:hover { color: #f87407; }
    .article-card-author { font-size: 0.85rem; font-weight: 700; color: #333; margin-bottom: 8px; }
    .article-card-excerpt { font-size: 0.88rem; color: #555; line-height: 1.6; margin-bottom: 12px; }
    .article-card-meta { font-size: 0.78rem; color: #aaa; margin-bottom: 10px; }
    .read-more { font-size: 0.82rem; color: #1a237e; text-decoration: none; font-weight: 600; }
    .read-more:hover { color: #f87407; }
    .no-articles { text-align: center; color: #999; padding: 60px 0; font-size: 1rem; }
</style>

<div class="sections-page">

    <div class="sections-heading">Category: <?php echo htmlspecialchars($category); ?></div>

    <?php if (!empty($articles)): ?>
        <?php foreach ($articles as $art): ?>
            <div class="article-card">
                <div class="article-card-title">
                    <a href="article.php?id=<?php echo $art['id']; ?>">
                        <?php echo htmlspecialchars($art['title']); ?>
                    </a>
                </div>
                <?php if ($art['author_name']): ?>
                    <div class="article-card-author"><?php echo htmlspecialchars($art['author_name']); ?></div>
                <?php endif; ?>
                <?php if ($art['excerpt']): ?>
                    <div class="article-card-excerpt">
                        <?php echo htmlspecialchars(mb_strimwidth(strip_tags($art['excerpt']), 0, 220, '...')); ?>
                    </div>
                <?php endif; ?>
                <?php if ($art['description']): ?>
                    <div class="article-card-meta"><?php echo htmlspecialchars($art['description']); ?></div>
                <?php endif; ?>
                <a href="article.php?id=<?php echo $art['id']; ?>" class="read-more">• READ MORE »</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-articles">No articles found in this category.</div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>