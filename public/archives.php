<?php
require '../includes/config.php';

$page_title = "Archives - TeacherPlus";

$month_names = [
    1=>'January', 2=>'February', 3=>'March',    4=>'April',
    5=>'May',     6=>'June',     7=>'July',      8=>'August',
    9=>'September',10=>'October',11=>'November', 12=>'December'
];

// Get all distinct years directly from created_at
$all_years = [];
$yr_q = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS yr
    FROM articles
    WHERE status = 'published'
    ORDER BY yr DESC
");
while ($row = $yr_q->fetch_assoc()) {
    $all_years[] = (int)$row['yr'];
}

$selected_year  = isset($_GET['year'])  ? intval($_GET['year'])  : ($all_years[0] ?? date('Y'));
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : null;

// Get available months for selected year
$avail_months = [];
$mo_q = $conn->prepare("
    SELECT DISTINCT MONTH(created_at) AS mn
    FROM articles
    WHERE status = 'published'
      AND YEAR(created_at) = ?
    ORDER BY mn ASC
");
$mo_q->bind_param("i", $selected_year);
$mo_q->execute();
$mo_res = $mo_q->get_result();
while ($row = $mo_res->fetch_assoc()) {
    $avail_months[(int)$row['mn']] = $month_names[(int)$row['mn']];
}

if (!$selected_month && !empty($avail_months)) {
    $selected_month = array_key_first($avail_months);
}

// Fetch articles for selected year + month
$articles = [];
if ($selected_year && $selected_month) {
    $art_q = $conn->prepare("
        SELECT id, title, excerpt, featured_image, author_name, category, created_at
        FROM articles
        WHERE status = 'published'
          AND YEAR(created_at)  = ?
          AND MONTH(created_at) = ?
        ORDER BY created_at ASC
    ");
    $art_q->bind_param("ii", $selected_year, $selected_month);
    $art_q->execute();
    $res = $art_q->get_result();
    while ($row = $res->fetch_assoc()) {
        $articles[] = $row;
    }
}

include '../includes/header.php';
?>

<style>
    body { background: #f7f7f7; }
    .archives-wrap { max-width: 960px; margin: 40px auto 80px; padding: 0 24px; }

    .year-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 28px; justify-content: center; }
    .year-tab {
        padding: 7px 16px; border-radius: 4px; font-size: 0.9rem; font-weight: 600;
        color: #444; background: #fff; border: 1px solid #ddd; text-decoration: none; transition: all 0.15s;
    }
    .year-tab:hover  { background: #f0f0f0; color: #222; }
    .year-tab.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

    .month-tabs {
        display: flex; flex-wrap: wrap; gap: 10px 24px; margin-bottom: 32px;
        border-bottom: 1px solid #e0e0e0; padding-bottom: 16px; justify-content: center;
    }
    .month-tab { font-size: 0.9rem; color: #666; text-decoration: none; padding-bottom: 4px; transition: color 0.15s; white-space: nowrap; }
    .month-tab:hover    { color: #1a1a2e; }
    .month-tab.active   { color: #e07000; font-weight: 700; border-bottom: 2px solid #e07000; }
    .month-tab.disabled { color: #ccc; cursor: default; pointer-events: none; }

    .article-list { list-style: disc; padding-left: 22px; margin: 0; }
    .article-list li { margin-bottom: 12px; font-size: 0.95rem; line-height: 1.5; }
    .article-list li a { color: #1a237e; text-decoration: none; transition: color 0.15s; }
    .article-list li a:hover { color: #e07000; text-decoration: underline; }
    .no-articles { text-align: center; color: #999; padding: 40px 0; font-size: 0.95rem; }

    @media (max-width: 600px) { .year-tab { padding: 5px 10px; font-size: 0.8rem; } }
</style>

<div class="archives-wrap">

    <div class="year-tabs">
        <?php foreach ($all_years as $yr): ?>
            <a href="archives.php?year=<?php echo $yr; ?>"
               class="year-tab <?php echo $yr === $selected_year ? 'active' : ''; ?>">
                <?php echo $yr; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="month-tabs">
        <?php foreach ($month_names as $mn => $label):
            $avail     = isset($avail_months[$mn]);
            $is_active = ($mn === $selected_month);
        ?>
            <?php if ($avail): ?>
                <a href="archives.php?year=<?php echo $selected_year; ?>&month=<?php echo $mn; ?>"
                   class="month-tab <?php echo $is_active ? 'active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            <?php else: ?>
                <span class="month-tab disabled"><?php echo $label; ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($articles)): ?>
        <ul class="article-list">
            <?php foreach ($articles as $art): ?>
                <li>
                    <a href="article.php?id=<?php echo $art['id']; ?>">
                        <?php echo htmlspecialchars($art['title']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="no-articles">No articles found for this period.</div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>