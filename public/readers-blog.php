<?php
require '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$page_title = "Readers' Blog — TeacherPlus";

/* 🔥 FETCH READERS BLOG — articles uploaded by shalini or kumar */
$stmt = $conn->prepare("
    SELECT id, title, excerpt, body, author_name, category, created_at
    FROM articles
    WHERE status = 'published'
      AND user_id IN (
          SELECT id FROM users WHERE LOWER(username) IN ('shalini','kumar')
      )
    ORDER BY created_at DESC
");
$stmt->execute();
$articles = $stmt->get_result();

include '../includes/header.php';
?>

<style>
.blog-page {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
}

/* ARTICLE */
.article-item {
    padding: 30px 0;
    border-bottom: 1px solid #eee;
}

.article-item:last-child {
    border-bottom: none;
}

/* DATE */
.article-date {
    font-size: 13px;
    color: #f87407;
    margin-bottom: 6px;
}

/* TITLE */
.article-title {
    font-size: 20px;
    font-weight: 700;
    color: #3d348b ;
    text-decoration: none;
    display: block;
    margin-bottom: 8px;
}

.article-title:hover {
    color: #141414;
}

/* AUTHOR */
.article-author {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 10px;
}

/* CONTENT */
.article-excerpt {
    font-size: 14px;
    color: #555;
    line-height: 1.7;
}

/* READ MORE */
.read-more {
    display: inline-block;
    margin-top: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #3d348b;
    text-decoration: none;
    float: right;
}

.read-more:hover {
   color: #141414;
}

/* NO DATA */
.no-results {
    text-align: center;
    padding: 60px;
    color: #aaa;
}
</style>

<div class="blog-page">

    <h2 style="margin-bottom:30px;">Readers' Blog</h2>

    <?php if ($articles->num_rows > 0): ?>

        <?php while ($article = $articles->fetch_assoc()):

            $excerpt = trim($article['excerpt']);
            if (empty($excerpt) && !empty($article['body'])) {
                $excerpt = strip_tags($article['body']);
            }

            $excerpt = mb_substr($excerpt, 0, 250);
            if (strlen($excerpt) >= 250) $excerpt .= '...';

            $date = date('F d, Y', strtotime($article['created_at']));
        ?>

        <div class="article-item">

            <!-- DATE -->
            <div class="article-date"><?php echo $date; ?></div>

            <!-- TITLE -->
            <a href="article.php?id=<?php echo $article['id']; ?>" class="article-title">
                <?php echo htmlspecialchars($article['title']); ?>
            </a>

            <!-- AUTHOR -->
            <?php if (!empty($article['author_name'])): ?>
                <div class="article-author">
                    By <?php echo htmlspecialchars(strip_tags($article['author_name'])); ?>
                </div>
            <?php endif; ?>

            <!-- EXCERPT -->
            <div class="article-excerpt">
                <?php echo htmlspecialchars(strip_tags($excerpt)); ?>
            </div>

            <!-- READ MORE -->
            <a href="article.php?id=<?php echo $article['id']; ?>" class="read-more">
                Read More 
            </a>

        </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="no-results">
            No Readers' Blog articles found.
        </div>

    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>