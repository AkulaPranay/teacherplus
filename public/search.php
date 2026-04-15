<?php
require '../includes/config.php';

$page_title = "Search Results - TeacherPlus";
include '../includes/header.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [];
if (!empty($query)) {
    $search = "%" . $query . "%";
    $stmt = $conn->prepare("
        SELECT id, title, excerpt, author_name, created_at 
        FROM articles 
        WHERE status = 'published' 
        AND (title LIKE ? OR excerpt LIKE ? OR body LIKE ?)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $results = $stmt->get_result();
}
?>

<div class="container my-5">
    <?php if (!empty($query)): ?>
        <h2>Search Results for: <span style="color:#f87407;">"<?php echo htmlspecialchars($query); ?>"</span></h2>
        
        <?php if ($results->num_rows > 0): ?>
            <div class="row g-4">
                <?php while($row = $results->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>
                                <a href="article.php?id=<?php echo $row['id']; ?>">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </a>
                            </h5>
                            <p class="text-muted small">
                                By <?php echo htmlspecialchars($row['author_name']); ?> • 
                                <?php echo date('F j, Y', strtotime($row['created_at'])); ?>
                            </p>
                            <?php if ($row['excerpt']): ?>
                                <p><?php echo htmlspecialchars(substr($row['excerpt'], 0, 150)) . '...'; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No results found for your search.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>