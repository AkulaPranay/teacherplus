<?php
require '../includes/config.php';
require '../includes/functions.php';

$page_title = "Worksheets - TeacherPlus";
include '../includes/header.php';

// Fetch distinct subjects
$subjects_result = $conn->query("
    SELECT DISTINCT subject
    FROM worksheets
    WHERE status = 'published'
    ORDER BY subject
");

// Store subjects in array
$subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row['subject'];
}

// Move English to the front if it exists
$englishIndex = array_search('English', $subjects);
if ($englishIndex !== false) {
    unset($subjects[$englishIndex]);
    array_unshift($subjects, 'English');
}

// Get selected subject
$selected_subject = $_GET['subject'] ?? '';

// Default to first subject
if (!$selected_subject && !empty($subjects)) {
    $selected_subject = $subjects[0];
}

// Fetch worksheets
if ($selected_subject) {
    $stmt = $conn->prepare("
        SELECT id, title, grade_level, pdf_file
        FROM worksheets
        WHERE status = 'published' AND subject = ?
        ORDER BY title
    ");
    $stmt->bind_param("s", $selected_subject);
} else {
    $stmt = $conn->prepare("
        SELECT id, title, grade_level, pdf_file
        FROM worksheets
        WHERE status = 'published'
        ORDER BY title
    ");
}

$stmt->execute();
$worksheets_result = $stmt->get_result();
?>

<style>
.worksheets-page {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
}

.worksheets-page h1 {
    color: #f87407;
    font-size: 2.2rem;
    text-align: center;
    margin-bottom: 30px;
}

.subject-tabs {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 40px;
}

.subject-tabs a {
    padding: 8px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    color: #333;
    background: #f1f1f1;
    transition: all 0.3s;
}

.subject-tabs a.active,
.subject-tabs a:hover {
    background: #181f3f;
    color: white;
}

.worksheet-list {
    line-height: 2.1;
    font-size: 1.05rem;
}

.worksheet-list li {
    margin-bottom: 12px;
}

.worksheet-list a {
    color: #0d6efd;
    text-decoration: none;
}

.worksheet-list a:hover {
    text-decoration: underline;
    color: #f87407;
}

.download-link {
    color: #198754;
    font-weight: 500;
    margin-left: 12px;
}

.download-link:hover {
    color: #f87407;
}
</style>

<div class="worksheets-page">

    <h1>Worksheets</h1>

    <!-- Subject Tabs -->
    <div class="subject-tabs">
        <?php foreach ($subjects as $subject): ?>
            <a href="?subject=<?= urlencode($subject) ?>" 
               class="<?= $subject === $selected_subject ? 'active' : '' ?>">
                <?= htmlspecialchars($subject) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Worksheet List -->
    <div class="worksheet-list">
        <?php if ($worksheets_result->num_rows > 0): ?>
            <ul>
            <?php while ($ws = $worksheets_result->fetch_assoc()): ?>
                <?php 
                $pdf_url = '';
                if (!empty($ws['pdf_file'])) {
                    $pdf_path = ltrim($ws['pdf_file'], '/');
                    $pdf_url = '../' . $pdf_path;
                }
                ?>
                <li>
                    <?= htmlspecialchars($ws['title']) ?>

                    <?php if (!empty($ws['grade_level'])): ?>
                        <span class="text-muted">
                            – <?= htmlspecialchars($ws['grade_level']) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($pdf_url): ?>
                        <?php if (hasFullAccess()): ?>
                            <a href="<?= htmlspecialchars($pdf_url) ?>" 
                               target="_blank" 
                               class="download-link">
                                [Download]
                            </a>
                        <?php else: ?>
                            <a href="restricted-worksheet.php" class="download-link">
                                [Login to Download]
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                </li>
            <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p class="text-center lead py-5 text-muted">
                No worksheets available for <?= htmlspecialchars($selected_subject) ?> at the moment.
            </p>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
