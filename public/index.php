<?php
require '../includes/config.php';
require '../includes/ads.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$today        = new DateTime();
$currentMonth = (int)$today->format('n');
$currentYear  = (int)$today->format('Y');
$currentMonthLabel = $today->format('F Y');

// Current month's e-magazine
$stmt = $conn->prepare("SELECT id, title, cover_image FROM e_magazines WHERE status='published' AND issue_year=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$currentMag = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Editorial by Usha Raman — current month first, fallback to latest
$stmt = $conn->prepare("SELECT id, title, excerpt, body, author_name, featured_image FROM articles WHERE status='published' AND LOWER(author_name) LIKE '%usha raman%' AND MONTH(created_at)=? AND YEAR(created_at)=? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$editorial = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$editorial) {
    $r = $conn->query("SELECT id, title, excerpt, body, author_name, featured_image FROM articles WHERE status='published' AND LOWER(author_name) LIKE '%usha raman%' ORDER BY created_at DESC LIMIT 1");
    $editorial = $r->fetch_assoc();
}

// This week — latest 4
$thisWeek = $conn->query("SELECT id, title, featured_image, category FROM articles WHERE status='published' ORDER BY created_at DESC LIMIT 4");

// Week before — next 4
$lastWeek = $conn->query("SELECT id, title, excerpt, body, featured_image, category, author_name, created_at FROM articles WHERE status='published' ORDER BY created_at DESC LIMIT 4 OFFSET 4");

// Past 3 months
$pastMonths = [];
for ($i = 1; $i <= 3; $i++) {
    $dt = clone $today;
    $dt->modify("-$i month");
    $m = (int)$dt->format('n');
    $y = (int)$dt->format('Y');

    $monthName = strtolower($dt->format('F'));
    $titlePattern = '%' . $monthName . '%' . $y . '%';
    $ms = $conn->prepare("SELECT id, cover_image, title FROM e_magazines WHERE status='published' AND LOWER(title) LIKE ? ORDER BY id DESC LIMIT 1");
    $ms->bind_param("s", $titlePattern);
    $ms->execute();
    $mag = $ms->get_result()->fetch_assoc();
    $ms->close();

    $as = $conn->prepare("SELECT id, title, category, tags, author_name, excerpt, body FROM articles WHERE status='published' AND MONTH(created_at)=? AND YEAR(created_at)=? ORDER BY created_at DESC LIMIT 4");
    $as->bind_param("ii", $m, $y);
    $as->execute();
    $arts = $as->get_result();
    $as->close();

    $pastMonths[] = ['label' => $dt->format('F Y'), 'year' => $y, 'mag' => $mag, 'articles' => $arts];
}

function asset_url($path) {
    if (empty($path)) return '';
    return (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
        ? $path
        : '../' . ltrim($path, '/');
}

function tp_ex($t, $len = 170) {
    $p = strip_tags($t);
    return mb_strlen($p) > $len ? mb_substr($p, 0, $len).'…' : $p;
}

include '../includes/header.php';
?>

<style>
/* ── Homepage-specific styles only ── */
*, *::before, *::after { box-sizing: border-box; }

.tp-page { max-width: 1080px; margin: 0 auto; padding: 22px 20px 60px; }

/* TOP 3-COL ROW */
.tp-top-row {
    display: grid;
    grid-template-columns: 270px 270px 1fr;
    gap: 14px;
    margin-bottom: 30px;
    align-items: stretch;
}

/* Col A — Cover */
.tp-cover-card { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; position: relative; background: #fff; }
.tp-badge-orange { position: absolute; top: 0; left: 0; background: #f87407; color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-bottom-right-radius: 4px; z-index: 2; text-transform: uppercase; letter-spacing: .4px; }
.tp-cover-card img { width: 100%; height: 320px; object-fit: cover; display: block; }
.tp-cover-placeholder { height: 320px; background: #1f2a4a; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: rgba(255,255,255,.5); font-size: 12px; text-align: center; padding: 12px; }
.tp-cover-caption { padding: 9px 11px; font-size: 11.5px; color: #555; line-height: 1.5; border-top: 1px solid #eee; }
.tp-cover-caption strong { display: block; font-size: 12.5px; color: #222; margin-bottom: 1px; }

/* Col B — Editorial */
.tp-editorial-card { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; position: relative; background: #fff; display: flex; flex-direction: column; }
.tp-badge-grey { position: absolute; top: 0; left: 0; background: #777; color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-bottom-right-radius: 4px; z-index: 2; text-transform: uppercase; letter-spacing: .4px; }
.tp-ed-img { width: 100%; height: 260px; object-fit: cover; flex-shrink: 0; }
.tp-ed-icon { height: 260px; background: #f7f7f7; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tp-ed-body { padding: 10px 12px; flex: 1; display: flex; flex-direction: column; }
.tp-ed-title { font-size: 13px; font-weight: 700; color: #222; line-height: 1.3; margin-bottom: 6px; }
.tp-ed-title a:hover { color: #f87407; }
.tp-ed-author { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.tp-ed-avatar { width: 24px; height: 24px; border-radius: 50%; background: #f87407; color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tp-ed-name { font-size: 12px; color: #f87407; font-weight: 600; }
.tp-ed-text { font-size: 12px; color: #555; line-height: 1.65; flex: 1; }
.tp-ed-more { display: inline-block; margin-top: 7px; font-size: 12px; color: #f87407; font-weight: 700; }
.tp-ed-more:hover { color: #c95f00; }

/* Col C — This Week 2×2 */
.tp-thisweek-card { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; position: relative; background: #fff; }
.tp-badge-green { position: absolute; top: 0; left: 0; background: #27ae60; color: #fff; font-size: 10px; font-weight: 700; padding: 3px 10px; border-bottom-right-radius: 4px; z-index: 4; text-transform: uppercase; letter-spacing: .4px; }
.tp-week-grid { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; height: 100%; min-height: 268px; }
.tp-week-cell { position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,.06); }
.tp-week-cell img { width: 100%; height: 100%; object-fit: cover; min-height: 132px; display: block; transition: transform .3s; }
.tp-week-cell:hover img { transform: scale(1.05); }
.tp-week-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,.72)); padding: 22px 9px 8px; pointer-events: none; }
.tp-week-overlay a { pointer-events: auto; }
.tp-cell-cat { display: inline-block; background: #f87407; color: #fff; font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 2px; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.tp-cell-title { font-size: 11.5px; font-weight: 700; line-height: 1.3; color: #fff; }
.tp-cell-title a { color: #fff; }
.tp-cell-title a:hover { color: #ffd58a; }
.tp-week-cell-blank { background: #f0f2f5; min-height: 132px; }

/* SECTION LABEL */
.tp-section-label { font-size: 15px; font-weight: 700; color: #f87407; border-bottom: 2px solid #f87407; display: inline-block; padding-bottom: 3px; margin-bottom: 16px; }

/* WEEK BEFORE LIST */
.tp-week-before-divider { border: none; border-top: 2px solid #1f2a4a; margin: 0 0 20px; }
.tp-art-list { display: flex; flex-direction: column; gap: 28px; margin-bottom: 20px; }
.tp-art-row { display: flex; gap: 20px; align-items: flex-start; }
.tp-art-thumb { width: 130px; min-width: 130px; height: 110px; object-fit: cover; border-radius: 3px; }
.tp-art-thumb-blank { width: 130px; min-width: 130px; height: 110px; background: #eee; border-radius: 3px; }
.tp-art-tags { margin-bottom: 6px; }
.tp-tag { display: inline-block; font-size: 9.5px; font-weight: 700; padding: 2px 8px; border-radius: 2px; text-transform: uppercase; letter-spacing: .3px; margin-right: 4px; }
.tp-tag-cat { background: #f87407; color: #fff; }
.tp-tag-month { background: #555; color: #fff; }
.tp-art-title { font-size: 15px; font-weight: 700; color: #f87407; line-height: 1.3; margin: 4px 0 5px; }
.tp-art-title a { color: #f87407; }
.tp-art-title a:hover { color: #c95f00; }
.tp-art-author { font-size: 13px; font-weight: 700; color: #222; margin-bottom: 5px; }
.tp-art-excerpt { font-size: 13px; color: #444; line-height: 1.7; }
.tp-art-more { display: inline-block; margin-top: 7px; font-size: 13px; font-weight: 600; color: #222; }
.tp-art-more:hover { color: #f87407; }
.tp-read-more-btn-wrap { text-align: right; margin-top: 10px; margin-bottom: 20px; }
.tp-read-more-btn { display: inline-block; background: #1f2a4a; color: #fff; font-size: 13px; font-weight: 600; padding: 10px 22px; border-radius: 4px; text-decoration: none; }
.tp-read-more-btn:hover { background: #2e3e6a; color: #fff; }

/* PAST 3 MONTHS */
.tp-divider { border: none; border-top: 2px solid #1f2a4a; margin: 6px 0 24px; }
.tp-past-months { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 36px; margin-bottom: 32px; }
.tp-pm-col { border-top: 2px solid #1f2a4a; padding-top: 16px; }
.tp-pm-month { font-size: 18px; font-weight: 700; color: #f87407; margin-bottom: 16px; display: block; }
.tp-pm-cover-wrap { margin-bottom: 20px; }
.tp-pm-cover { width: 55%; height: auto; aspect-ratio: 3/4; object-fit: cover; border-radius: 3px; border: 1px solid #ddd; display: block; }
.tp-pm-cover-blank { width: 55%; aspect-ratio: 3/4; background: #e5e8ef; border-radius: 3px; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 13px; }
.tp-pm-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 20px; }
.tp-pm-list li { padding: 0; border-bottom: none; }
.tp-pm-item-tags { margin-bottom: 5px; display: flex; flex-wrap: wrap; gap: 4px; }
.tp-pm-cat { display: inline-block; font-size: 9.5px; font-weight: 700; color: #fff; background: #f87407; text-transform: uppercase; letter-spacing: .3px; padding: 3px 9px; border-radius: 2px; }
.tp-pm-tag-month { display: inline-block; font-size: 9.5px; font-weight: 700; color: #fff; background: #f87407; text-transform: uppercase; letter-spacing: .3px; padding: 3px 9px; border-radius: 2px; }
.tp-pm-title { font-size: 14px; font-weight: 700; color: #1f2a4a; line-height: 1.35; display: block; margin: 5px 0 4px; }
.tp-pm-title:hover { color: #f87407; }
.tp-pm-author { font-size: 13px; font-weight: 700; color: #222; margin-bottom: 4px; }
.tp-pm-excerpt { font-size: 13px; color: #444; line-height: 1.65; }
.tp-pm-readmore { display: inline-block; margin-top: 14px; font-size: 12px; font-weight: 700; color: #f87407; border: 1px solid #f87407; padding: 5px 14px; border-radius: 3px; }
.tp-pm-readmore:hover { background: #f87407; color: #fff; }

/* BOTTOM FEATURES */
.tp-features { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; margin-top: 30px; }
.tp-feat { border: 1px solid #e0e0e0; border-radius: 6px; padding: 28px 20px; text-align: center; background: #fafafa; }
.tp-feat img { width: 200px; height: 200px; object-fit: contain; margin: 0 auto 14px; }
.tp-feat h5 { font-size: 14px; font-weight: 700; color: #1f2a4a; margin-bottom: 7px; }
.tp-feat p { font-size: 12px; color: #666; line-height: 1.7; }

/* RESPONSIVE */
@media (max-width: 900px) {
    .tp-top-row { grid-template-columns: 1fr 1fr; }
    .tp-thisweek-card { grid-column: span 2; }
    .tp-past-months { grid-template-columns: 1fr 1fr; }
    .tp-features { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .tp-top-row { grid-template-columns: 1fr; }
    .tp-thisweek-card { grid-column: span 1; }
    .tp-past-months { grid-template-columns: 1fr; }
    .tp-features { grid-template-columns: 1fr; }
}
</style>

<div class="tp-page">

    <!-- 🔥 TOP AD START -->
    <?php $ads = getAds($conn, 'homepage_top'); ?>
    <?php if ($ads->num_rows > 0): ?>
    <div class="text-center mb-3">
        <?php while ($ad = $ads->fetch_assoc()): ?>
            <a href="<?= $ad['link_url'] ?>" target="_blank">
                <img src="<?= htmlspecialchars(asset_url($ad['image_path'])) ?>" class="img-fluid" style="max-height:90px;">
            </a>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
    <!-- 🔥 TOP AD END -->

    <!-- ══ TOP ROW: Cover | Editorial | This Week ══ -->
    <div class="tp-top-row">

        <!-- A: Current Issue Cover -->
        <div class="tp-cover-card">
            <span class="tp-badge-orange"><?php echo strtoupper($currentMonthLabel); ?></span>
            <?php if ($currentMag && !empty($currentMag['cover_image'])): ?>
                <a href="e-magazines.php">
                    <img src="<?php echo htmlspecialchars(asset_url($currentMag['cover_image'])); ?>"
                         alt="<?php echo htmlspecialchars($currentMag['title']); ?>">
                </a>
                <div class="tp-cover-caption">
                    <strong><?php echo htmlspecialchars($currentMag['title']); ?></strong>
                    <?php echo $currentMonthLabel; ?> Issue
                </div>
            <?php else: ?>
                <div class="tp-cover-placeholder">
                    <i class="fas fa-book-open fa-2x"></i>
                    <span><?php echo $currentMonthLabel; ?><br>issue coming soon</span>
                </div>
                <div class="tp-cover-caption">Monthly E-Magazine</div>
            <?php endif; ?>
        </div>

        <!-- B: Editorial -->
        <div class="tp-editorial-card">
            <span class="tp-badge-grey">Editorial</span>
            <?php if ($editorial && !empty($editorial['featured_image'])): ?>
                <img class="tp-ed-img"
                     src="<?php echo htmlspecialchars(asset_url($editorial['featured_image'])); ?>"
                     alt="editorial">
            <?php else: ?>
                <div class="tp-ed-icon">
                    <svg width="110" height="150" viewBox="0 0 60 85" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity:.22;">
                        <ellipse cx="30" cy="68" rx="19" ry="14" fill="#1f2a4a"/>
                        <path d="M30 54 C8 26 54 6 30 54" stroke="#555" stroke-width="1.5" fill="none"/>
                        <path d="M30 54 Q34 34 52 12" stroke="#777" stroke-width="1" fill="none"/>
                        <path d="M30 54 Q26 34 8 12" stroke="#777" stroke-width="1" fill="none"/>
                        <line x1="30" y1="54" x2="30" y2="70" stroke="#999" stroke-width="1.5"/>
                    </svg>
                </div>
            <?php endif; ?>

            <?php $ads = getAds($conn, 'sidebar_right'); ?>
            <?php if ($ads->num_rows > 0): ?>
            <div>
                <?php while ($ad = $ads->fetch_assoc()): ?>
                    <a href="<?= $ad['link_url'] ?>" target="_blank">
                        <img src="<?= htmlspecialchars(asset_url($ad['image_path'])) ?>" class="img-fluid mb-2">
                    </a>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <div class="tp-ed-body">
                <?php if ($editorial): ?>
                    <div class="tp-ed-title">
                        <a href="article.php?id=<?php echo $editorial['id']; ?>">
                            <?php echo htmlspecialchars($editorial['title']); ?>
                        </a>
                    </div>
                    <div class="tp-ed-author">
                        <div class="tp-ed-avatar"><?php echo strtoupper(substr($editorial['author_name'] ?? 'U', 0, 1)); ?></div>
                        <span class="tp-ed-name"><?php echo htmlspecialchars($editorial['author_name']); ?></span>
                    </div>
                    <div class="tp-ed-text"><?php echo tp_ex($editorial['excerpt'] ?: $editorial['body'], 150); ?></div>
                    <a href="article.php?id=<?php echo $editorial['id']; ?>" class="tp-ed-more">Read More</a>
                <?php else: ?>
                    <div class="tp-ed-title">Editorial</div>
                    <div class="tp-ed-author">
                        <div class="tp-ed-avatar">U</div>
                        <span class="tp-ed-name">Usha Raman</span>
                    </div>
                    <div class="tp-ed-text" style="color:#bbb;">The <?php echo $currentMonthLabel; ?> editorial will be published soon.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- C: New This Week 2×2 -->
        <div class="tp-thisweek-card">
            <span class="tp-badge-green">New This Week</span>
            <div class="tp-week-grid">
                <?php
                $wc = 0;
                $fallbackBg = ['#e8f0fa','#fef5e7','#eafaf1','#fdf2f8'];
                while ($wr = $thisWeek->fetch_assoc()):
                    $wc++;
                ?>
                <div class="tp-week-cell">
                    <?php if (!empty($wr['featured_image'])): ?>
                        <a href="article.php?id=<?php echo $wr['id']; ?>">
                            <img src="<?php echo htmlspecialchars(asset_url($wr['featured_image'])); ?>"
                                 alt="<?php echo htmlspecialchars($wr['title']); ?>">
                        </a>
                    <?php else: ?>
                        <div class="tp-week-cell-blank" style="background:<?php echo $fallbackBg[$wc % 4]; ?>;"></div>
                    <?php endif; ?>
                    <div class="tp-week-overlay">
                        <?php if ($wr['category']): ?>
                            <span class="tp-cell-cat"><?php echo htmlspecialchars($wr['category']); ?></span>
                        <?php endif; ?>
                        <div class="tp-cell-title">
                            <a href="article.php?id=<?php echo $wr['id']; ?>"><?php echo htmlspecialchars($wr['title']); ?></a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php for ($f = $wc; $f < 4; $f++): ?>
                <div class="tp-week-cell">
                    <div class="tp-week-cell-blank"></div>
                    <div class="tp-week-overlay">
                        <div class="tp-cell-title" style="color:rgba(255,255,255,.35);font-size:11px;">Coming soon</div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

    </div><!-- /tp-top-row -->

    <?php $ads = getAds($conn, 'between_sections'); ?>
    <?php if ($ads->num_rows > 0): ?>
    <div class="text-center my-4">
        <?php while ($ad = $ads->fetch_assoc()): ?>
            <a href="<?= $ad['link_url'] ?>" target="_blank">
                <img src="<?= htmlspecialchars(asset_url($ad['image_path'])) ?>" class="img-fluid">
            </a>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>


    <!-- ══ THE WEEK BEFORE ══ -->
    <hr class="tp-week-before-divider">
    <div class="tp-section-label">The week before</div>
    <div class="tp-art-list">
        <?php while ($lr = $lastWeek->fetch_assoc()): ?>
        <div class="tp-art-row">
            <?php if (!empty($lr['featured_image'])): ?>
                <a href="article.php?id=<?php echo $lr['id']; ?>">
                    <img class="tp-art-thumb"
                         src="<?php echo htmlspecialchars(asset_url($lr['featured_image'])); ?>"
                         alt="<?php echo htmlspecialchars($lr['title']); ?>">
                </a>
            <?php else: ?>
                <div class="tp-art-thumb-blank"></div>
            <?php endif; ?>
            <div style="flex:1;">
                <div class="tp-art-tags">
                    <span class="tp-tag tp-tag-month"><?php echo strtoupper(date('F Y', strtotime($lr['created_at']))); ?></span>
                    <?php if ($lr['category']): ?>
                        <span class="tp-tag tp-tag-cat"><?php echo htmlspecialchars($lr['category']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="tp-art-title">
                    <a href="article.php?id=<?php echo $lr['id']; ?>"><?php echo htmlspecialchars($lr['title']); ?></a>
                </div>
                <div class="tp-art-author"><?php echo htmlspecialchars($lr['author_name'] ?: 'Team'); ?></div>
                <div class="tp-art-excerpt"><?php echo tp_ex($lr['excerpt'] ?: $lr['body'], 350); ?></div>
                <a href="article.php?id=<?php echo $lr['id']; ?>" class="tp-art-more">Read More</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="tp-read-more-btn-wrap">
        <a href="archives.php" class="tp-read-more-btn">Read more articles from this issue</a>
    </div>


    <!-- ══ PAST 3 MONTHS ══ -->
    <hr class="tp-divider">
    <div class="tp-past-months">
        <?php foreach ($pastMonths as $pm): ?>
        <div class="tp-pm-col">
            <span class="tp-pm-month"><?php echo htmlspecialchars($pm['label']); ?></span>

            <!-- Cover image -->
            <div class="tp-pm-cover-wrap">
                <?php if ($pm['mag'] && !empty($pm['mag']['cover_image'])): ?>
                    <a href="e-magazines.php?year=<?php echo $pm['year']; ?>">
                        <img class="tp-pm-cover"
                             src="<?php echo htmlspecialchars(asset_url($pm['mag']['cover_image'])); ?>"
                             alt="<?php echo htmlspecialchars($pm['mag']['title'] ?? ''); ?>">
                    </a>
                <?php else: ?>
                    <div class="tp-pm-cover-blank"><i class="fas fa-book fa-2x"></i></div>
                <?php endif; ?>
            </div>

            <!-- Articles list -->
            <ul class="tp-pm-list">
                <?php
                $pc = 0;
                while ($pa = $pm['articles']->fetch_assoc()):
                    $pc++;
                    $monthLabel = strtoupper($pm['label']);
                    $mlParts = explode(' ', $monthLabel);
                ?>
                <li>
                    <div class="tp-pm-item-tags">
                        <span class="tp-pm-tag-month"><?php echo $mlParts[0]; ?></span>
                        <?php if ($pa['category']): ?>
                            <span class="tp-pm-cat"><?php echo htmlspecialchars(strtoupper($pa['category'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <a class="tp-pm-title" href="article.php?id=<?php echo $pa['id']; ?>">
                        <?php echo htmlspecialchars($pa['title']); ?>
                    </a>
                    <div class="tp-pm-author"><?php echo htmlspecialchars($pa['author_name'] ?: 'Team'); ?></div>
                    <?php $exc = tp_ex($pa['excerpt'] ?: $pa['body'], 170); ?>
                    <?php if ($exc): ?>
                        <div class="tp-pm-excerpt"><?php echo $exc; ?></div>
                    <?php endif; ?>
                </li>
                <?php endwhile; ?>
                <?php if ($pc === 0): ?>
                    <li style="color:#bbb;font-size:12px;">No articles this month.</li>
                <?php endif; ?>
            </ul>
            <a href="e-magazines.php?year=<?php echo $pm['year']; ?>" class="tp-pm-readmore">
                Read more articles from this issue
            </a>
        </div>
        <?php endforeach; ?>
    </div>


    <!-- ══ BOTTOM FEATURE BOXES ══ -->
    <div class="tp-features">
        <div class="tp-feat">
            <img src="uploads/Ideas-you-can-use-1.jpg" alt="Ideas you can use">
            <h5>Ideas you can use</h5>
            <p>Explore our extensive collection of interactive worksheets designed to enhance classroom learning and engage students in diverse subjects.</p>
        </div>
        <div class="tp-feat">
            <img src="uploads/have-a-question-1.jpg" alt="Have a Question">
            <h5>Have a Question? Ask Us.</h5>
            <p>Participate in our educator focused questionnaires to share your insights, ideas, and feedback, helping us tailor content to your needs.</p>
        </div>
        <div class="tp-feat">
            <img src="uploads/write-to-us-1.jpg" alt="Contribute">
            <h5>Contribute</h5>
            <p>Join our community of educators by contributing articles, ideas, and resources to help inspire and support fellow teachers nationwide.</p>
        </div>
    </div>

</div><!-- /tp-page -->

<?php $ads = getAds($conn, 'homepage_bottom'); ?>
<?php if ($ads->num_rows > 0): ?>
<div class="text-center mt-4">
    <?php while ($ad = $ads->fetch_assoc()): ?>
        <a href="<?= $ad['link_url'] ?>" target="_blank">
            <img src="<?= htmlspecialchars(asset_url($ad['image_path'])) ?>" class="img-fluid">
        </a>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
