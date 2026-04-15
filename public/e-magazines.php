<?php
require '../includes/config.php';
require '../includes/functions.php';

$page_title = "E-Magazines - TeacherPlus";
include '../includes/header.php';

// Fetch all published magazines
$result = $conn->query("
    SELECT id, title, issue_year, cover_image, pdf_file 
    FROM e_magazines 
    WHERE status = 'published' 
    ORDER BY issue_year DESC, id DESC
");

$magazines = [];
while ($row = $result->fetch_assoc()) {
    $magazines[$row['issue_year']][] = $row;
}

$years = array_keys($magazines);
$activeYear = $years[0] ?? date('Y');
?>

<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">

<!-- PDF.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- StPageFlip -->
<script src="https://cdn.jsdelivr.net/npm/page-flip@1.2.0/dist/js/page-flip.browser.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /* ── Wrapper ── */
  .emag-wrapper {
    font-family: 'Source Sans 3', sans-serif;
    background: #f5f4f0;
    min-height: 100vh;
    padding-bottom: 60px;
  }

  /* ── Notice Bar ── */
  .emag-notice {
    background: #fff;
    border-bottom: 1px solid #e0ddd6;
    text-align: center;
    padding: 14px 20px;
    font-size: 15px;
    color: #444;
    letter-spacing: 0.01em;
  }

  /* ── Year Tab Nav ── */
  .emag-year-nav {
    display: flex;
    justify-content: center;
    gap: 0;
    padding: 36px 20px 0;
  }

  .year-tab {
    background: none;
    border: none;
    font-family: 'Source Sans 3', sans-serif;
    font-size: 17px;
    font-weight: 500;
    color: #555;
    padding: 8px 28px;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
    letter-spacing: 0.02em;
  }

  .year-tab::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: #222;
    transition: width 0.25s ease;
  }

  .year-tab:hover { color: #111; }
  .year-tab.active { color: #111; font-weight: 600; }
  .year-tab.active::after { width: 60%; }

  /* ── Thin divider ── */
  .emag-divider {
    border: none;
    border-top: 1px solid #d8d5ce;
    margin: 0 40px;
  }

  /* ── Year Grid Sections ── */
  .emag-grid-section { display: none; }
  .emag-grid-section.active { display: block; }

  .emag-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 28px;
    max-width: 1100px;
    margin: 44px auto 0;
    padding: 0 30px;
  }

  @media (max-width: 1024px) { .emag-grid { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 720px)  { .emag-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 480px)  { .emag-grid { grid-template-columns: 1fr; } }

  /* ── Card ── */
  .mag-card {
    background: #fff;
    border-radius: 12px;
    padding: 12px 12px 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
    transition: transform 0.28s ease, box-shadow 0.28s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .mag-card:hover {
    transform: translateY(-7px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.13);
  }

  .mag-cover-wrap {
    width: 100%;
    aspect-ratio: 3 / 4;
    border-radius: 8px;
    overflow: hidden;
    background: #e9e7e2;
    flex-shrink: 0;
  }

  .mag-cover-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    cursor: pointer;
    transition: transform 0.35s ease;
  }

  .mag-card:hover .mag-cover-wrap img { transform: scale(1.04); }

  .mag-no-cover {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e2dfd7;
    color: #999;
    font-size: 13px;
  }

  .mag-title {
    margin-top: 12px;
    font-size: 15px;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.3;
  }

  .mag-btn {
    display: inline-block;
    margin-top: 10px;
    padding: 7px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
  }

  .mag-btn-primary {
    background: #1a1a1a;
    color: #fff;
    border: 1.5px solid #1a1a1a;
  }

  .mag-btn-primary:hover { background: #333; color: #fff; }

  .mag-btn-outline {
    background: transparent;
    color: #c0392b;
    border: 1.5px solid #c0392b;
  }

  .mag-btn-outline:hover { background: #c0392b; color: #fff; }

  .mag-btn-download {
    background: transparent;
    color: #666;
    border: 1.5px solid #ccc;
    font-size: 12px;
    padding: 5px 14px;
    margin-top: 5px;
  }

  .mag-btn-download:hover { background: #f0f0f0; color: #222; border-color: #999; }

  /* ── Flipbook Modal ── */
  #flipbookModal .modal-dialog {
    max-width: 98vw;
    width: 98vw;
    margin: 10px auto;
  }

  #flipbookModal .modal-content {
    background: #1a1a2e;
    border: none;
    border-radius: 12px;
  }

  #flipbookModal .modal-header {
    background: #1a1a2e;
    border-bottom: 1px solid #333;
    color: #fff;
  }

  #flipbookModal .btn-close {
    filter: invert(1);
  }

  /* ── Flipbook Container ── */
  #flipbook-area {
    position: relative;
    width: 100%;
    background: #1a1a2e;
    min-height: 82vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px 10px 10px;
  }

  /* Loading overlay */
  #flipbook-loading {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #1a1a2e;
    color: #fff;
    z-index: 10;
    gap: 16px;
    font-family: 'Source Sans 3', sans-serif;
  }

  .loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid rgba(255,255,255,0.2);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  #loading-text {
    font-size: 15px;
    color: rgba(255,255,255,0.7);
  }

  #loading-progress {
    font-size: 13px;
    color: rgba(255,255,255,0.5);
  }

  /* The flipbook canvas wrapper */
  #flipbook-container {
    display: none;
    position: relative;
    transform-origin: top center;
    transition: transform 0.2s ease;
    flex-shrink: 0;
  }

  /* ── Bottom Toolbar ── */
  .flipbook-toolbar {
    background: #111827;
    border-top: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    gap: 12px;
    flex-wrap: wrap;
    border-radius: 0 0 12px 12px;
  }

  .tb-group {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .tb-btn {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.75);
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: background 0.18s, color 0.18s, border-color 0.18s;
  }

  .tb-btn:hover {
    background: rgba(255,255,255,0.18);
    color: #fff;
    border-color: rgba(255,255,255,0.35);
  }

  .tb-btn.tb-active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
  }

  .tb-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
  }

  .tb-page-jump {
    display: flex;
    align-items: center;
    gap: 5px;
    color: rgba(255,255,255,0.7);
    font-size: 13px;
    font-family: 'Source Sans 3', sans-serif;
  }

  .tb-page-jump input {
    width: 48px;
    height: 34px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 6px;
    color: #fff;
    font-size: 13px;
    text-align: center;
    outline: none;
    padding: 0 4px;
  }

  .tb-page-jump input:focus {
    border-color: #3b82f6;
    background: rgba(59,130,246,0.15);
  }

  #page-total {
    white-space: nowrap;
  }

  /* Sound muted state */
  .tb-btn.muted { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); color: rgba(255,255,255,0.4); }

  /* Zoom label */
  .tb-zoom-label {
    color: rgba(255,255,255,0.6);
    font-size: 12px;
    font-family: 'Source Sans 3', sans-serif;
    min-width: 38px;
    text-align: center;
    user-select: none;
  }

  /* Zoom wrapper on flipbook container */
  #flipbook-zoom-wrap {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    overflow: auto;
    width: 100%;
    max-height: 78vh;
    padding: 10px 0;
  }

  #flipbook-container {
    transform-origin: top center;
    transition: transform 0.2s ease;
  }
</style>

<div class="emag-wrapper">

  <!-- Notice bar -->
  <div class="emag-notice">
    Please note: The monthly magazine will be uploaded on the last day of the month.
  </div>

  <!-- Year tabs -->
  <div class="emag-year-nav">
    <?php foreach ($years as $i => $year): ?>
      <button class="year-tab <?php echo $i === 0 ? 'active' : ''; ?>"
              data-year="<?php echo $year; ?>"
              onclick="switchYear(this, '<?php echo $year; ?>')">
        <?php echo $year; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <hr class="emag-divider">

  <!-- Magazine grids per year -->
  <?php foreach ($magazines as $year => $issues): ?>
  <div class="emag-grid-section <?php echo $year === $activeYear ? 'active' : ''; ?>"
       id="year-<?php echo $year; ?>">
    <div class="emag-grid">
      <?php foreach ($issues as $mag): ?>
      <div class="mag-card">

        <div class="mag-cover-wrap">
       <?php if (!empty($mag['cover_image'])): 
    $clean_cover = ltrim($mag['cover_image'], './');
    $clean_pdf   = ltrim($mag['pdf_file'] ?? '', './');
?>
    <img src="/<?= htmlspecialchars($clean_cover) ?>"
         alt="<?= htmlspecialchars($mag['title']) ?>"
         class="flipbook-trigger"
         data-pdf="/<?= htmlspecialchars($clean_pdf) ?>"
         data-title="<?= htmlspecialchars($mag['title']) ?>">
<?php else: ?>
            <div class="mag-no-cover">No Cover</div>
          <?php endif; ?>
        </div>

        <div class="mag-title"><?php echo htmlspecialchars($mag['title']); ?></div>

        <?php if (hasFullAccess()): ?>
          <button class="mag-btn mag-btn-primary flipbook-trigger"
                  data-pdf="../<?php echo htmlspecialchars($mag['pdf_file']); ?>"
                  data-title="<?php echo htmlspecialchars($mag['title']); ?>">
            📖 Read Flipbook
          </button>
<a href="/<?= htmlspecialchars(ltrim($mag['pdf_file'] ?? '', './')) ?>             download
             class="mag-btn mag-btn-download">⬇ Download PDF</a>
        <?php else: ?>
          <a href="restricted-emag.php" class="mag-btn mag-btn-outline">Login to View</a>
        <?php endif; ?>

      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

</div>

<!-- ── Flipbook Modal ── -->
<div class="modal fade" id="flipbookModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <!-- Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="flipbook-modal-title">E-Magazine</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="destroyFlipbook()"></button>
      </div>

      <div class="modal-body p-0">
        <div id="flipbook-area">

          <!-- Loading state -->
          <div id="flipbook-loading">
            <div class="loading-spinner"></div>
            <div id="loading-text">Loading magazine…</div>
            <div id="loading-progress"></div>
          </div>

          <!-- The flipbook pages render here -->
          <div id="flipbook-zoom-wrap">
            <div id="flipbook-container"></div>
          </div>

        </div>
      </div>

      <!-- ── Bottom Toolbar ── -->
      <div class="flipbook-toolbar" id="flipbook-toolbar" style="display:none;">

        <!-- Left: Prev + page jump -->
        <div class="tb-group">
          <button class="tb-btn" id="btn-first"   onclick="flipFirst()"  title="Go to First Page">⏮</button>
          <button class="tb-btn" id="btn-prev"    onclick="flipPrev()"   title="Previous Page">◀</button>
          <div class="tb-page-jump">
            <input type="number" id="page-input" min="1" value="1" onchange="jumpToPage(this.value)" title="Go to page">
            <span id="page-total">/ 1</span>
          </div>
          <button class="tb-btn" id="btn-next"    onclick="flipNext()"   title="Next Page">▶</button>
          <button class="tb-btn" id="btn-last"    onclick="flipLast()"   title="Go to Last Page">⏭</button>
        </div>

        <!-- Center: View mode -->
        <div class="tb-group">
          <button class="tb-btn tb-active" id="btn-double" onclick="setViewMode('double')" title="Double Page Mode">
            <svg width="18" height="14" viewBox="0 0 18 14" fill="currentColor"><rect x="0" y="0" width="8" height="14" rx="1"/><rect x="10" y="0" width="8" height="14" rx="1"/></svg>
          </button>
          <button class="tb-btn" id="btn-single" onclick="setViewMode('single')" title="Single Page Mode">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><rect x="2" y="0" width="10" height="14" rx="1"/></svg>
          </button>
        </div>

        <!-- Right: Zoom + Sound + Download -->
        <div class="tb-group">
          <button class="tb-btn" id="btn-zoom-out" onclick="adjustZoom(-0.15)" title="Zoom Out">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><line x1="10.5" y1="10.5" x2="14.5" y2="14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="4" y1="6.5" x2="9" y2="6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </button>
          <span class="tb-zoom-label" id="zoom-label">100%</span>
          <button class="tb-btn" id="btn-zoom-in" onclick="adjustZoom(0.15)" title="Zoom In">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><line x1="10.5" y1="10.5" x2="14.5" y2="14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="6.5" y1="4" x2="6.5" y2="9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="4" y1="6.5" x2="9" y2="6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </button>
        </div>

        <!-- Right: Sound + Download -->
        <div class="tb-group">
          <button class="tb-btn tb-active" id="btn-sound" onclick="toggleSound()" title="Turn on/off Sound">🔊</button>
          <a id="btn-download" href="#" download class="tb-btn" title="Download PDF">⬇</a>
        </div>

      </div><!-- /.flipbook-toolbar -->

    </div>
  </div>
</div>

<script>
// Configure PDF.js worker
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let pageFlip    = null;
let renderedPages = [];
let currentPdfUrl = '';
let soundEnabled  = true;
let isSinglePage  = false;
let totalPageCount = 0;
let currentZoom   = 1.0;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 2.0;

// ── Open flipbook ─────────────────────────────────────────────────────────────
async function openFlipbook(pdfUrl, title) {
  currentPdfUrl = pdfUrl;

  // Reset UI
  currentZoom = 1.0;
  applyZoom();
  document.getElementById('flipbook-modal-title').textContent = title;
  document.getElementById('flipbook-loading').style.display = 'flex';
  document.getElementById('flipbook-container').style.display = 'none';
  document.getElementById('flipbook-container').innerHTML = '';
  document.getElementById('flipbook-toolbar').style.display = 'none';
  document.getElementById('loading-progress').textContent = '';
  document.getElementById('loading-text').textContent = 'Loading magazine…';
  document.getElementById('btn-download').href = pdfUrl;

  new bootstrap.Modal(document.getElementById('flipbookModal')).show();

  try {
    const loadingTask = pdfjsLib.getDocument(pdfUrl);
    loadingTask.onProgress = (p) => {
      if (p.total) {
        const pct = Math.round((p.loaded / p.total) * 100);
        document.getElementById('loading-progress').textContent = `Downloading… ${pct}%`;
      }
    };

    const pdf = await loadingTask.promise;
    totalPageCount = pdf.numPages;
    document.getElementById('page-total').textContent = `/ ${totalPageCount}`;

    document.getElementById('loading-text').textContent = `Rendering pages…`;

    // Determine display dimensions
    const firstPage = await pdf.getPage(1);
    const vpBase    = firstPage.getViewport({ scale: 1 });
    const aspectRatio = vpBase.height / vpBase.width;

    const modalW = Math.min(window.innerWidth * 0.95, 1280);
    const modalH = window.innerHeight * 0.76;

    let pageW = Math.min(Math.floor(modalW / 2) - 16, 560);
    let pageH = Math.round(pageW * aspectRatio);
    if (pageH > modalH) { pageH = modalH; pageW = Math.round(pageH / aspectRatio); }

    // Render at 2× pixel density for crisp text
    const SCALE_FACTOR = 2;
    renderedPages = [];

    for (let i = 1; i <= totalPageCount; i++) {
      document.getElementById('loading-progress').textContent = `Page ${i} of ${totalPageCount}`;
      const page = await pdf.getPage(i);
      const baseScale = pageW / page.getViewport({ scale: 1 }).width;
      const hiScale   = baseScale * SCALE_FACTOR;
      const vp        = page.getViewport({ scale: hiScale });

      const canvas  = document.createElement('canvas');
      canvas.width  = vp.width;
      canvas.height = vp.height;
      const ctx = canvas.getContext('2d');
      await page.render({ canvasContext: ctx, viewport: vp }).promise;
      renderedPages.push(canvas.toDataURL('image/png')); // PNG = lossless, crisp
    }

    buildFlipbook(pageW, pageH);

  } catch (err) {
    console.error('PDF load error:', err);
    document.getElementById('loading-text').textContent = 'Failed to load. Please try again.';
    document.getElementById('loading-progress').textContent = err.message;
  }
}

// ── Build StPageFlip ──────────────────────────────────────────────────────────
function buildFlipbook(pageW, pageH) {
  const container = document.getElementById('flipbook-container');
  container.innerHTML = '';

  const bookEl = document.createElement('div');
  bookEl.id = 'the-book';
  container.appendChild(bookEl);

  renderedPages.forEach((dataUrl) => {
    const page = document.createElement('div');
    page.className = 'page';
    const img = document.createElement('img');
    // Display at logical pageW×pageH even though source is 2× — browser downscales = crisp
    img.style.cssText = `width:${pageW}px;height:${pageH}px;display:block;`;
    img.src = dataUrl;
    page.appendChild(img);
    bookEl.appendChild(page);
  });

  if (pageFlip) { try { pageFlip.destroy(); } catch(e){} pageFlip = null; }

  pageFlip = new St.PageFlip(bookEl, {
    width:    pageW,
    height:   pageH,
    size:     'fixed',
    drawShadow:   true,
    flippingTime: 650,
    usePortrait:  isSinglePage,
    showCover:    true,
    mobileScrollSupport: false,
    useMouseEvents: true,
  });

  pageFlip.loadFromHTML(bookEl.querySelectorAll('.page'));

  pageFlip.on('flip', (e) => {
    if (soundEnabled) playFlipSound();
    updateToolbar();
  });

  document.getElementById('flipbook-loading').style.display = 'none';
  container.style.display = 'block';
  document.getElementById('flipbook-toolbar').style.display = 'flex';
  updateToolbar();
}

// ── Toolbar actions ───────────────────────────────────────────────────────────
function updateToolbar() {
  if (!pageFlip) return;
  const cur = pageFlip.getCurrentPageIndex() + 1;
  const tot = pageFlip.getPageCount();
  document.getElementById('page-input').value = cur;
  document.getElementById('page-input').max   = tot;
  document.getElementById('btn-prev').disabled  = cur <= 1;
  document.getElementById('btn-first').disabled = cur <= 1;
  document.getElementById('btn-next').disabled  = cur >= tot;
  document.getElementById('btn-last').disabled  = cur >= tot;
}

function flipPrev()  { if (pageFlip) pageFlip.flipPrev('top'); }
function flipNext()  { if (pageFlip) pageFlip.flipNext('top'); }
function flipFirst() { if (pageFlip) pageFlip.flip(0); }
function flipLast()  { if (pageFlip) pageFlip.flip(pageFlip.getPageCount() - 1); }

function jumpToPage(val) {
  const n = parseInt(val, 10);
  if (!pageFlip || isNaN(n)) return;
  const clamped = Math.max(1, Math.min(n, pageFlip.getPageCount()));
  pageFlip.flip(clamped - 1);
}

function setViewMode(mode) {
  isSinglePage = (mode === 'single');
  document.getElementById('btn-single').classList.toggle('tb-active', isSinglePage);
  document.getElementById('btn-double').classList.toggle('tb-active', !isSinglePage);
  // Rebuild with same dimensions (read from current img)
  if (pageFlip) {
    const img = document.querySelector('#the-book .page img');
    if (img) buildFlipbook(parseInt(img.style.width), parseInt(img.style.height));
  }
}

function toggleSound() {
  soundEnabled = !soundEnabled;
  const btn = document.getElementById('btn-sound');
  btn.textContent = soundEnabled ? '🔊' : '🔇';
  btn.classList.toggle('tb-active', soundEnabled);
  btn.classList.toggle('muted',     !soundEnabled);
}

function adjustZoom(delta) {
  currentZoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, currentZoom + delta));
  applyZoom();
}

function applyZoom() {
  const container = document.getElementById('flipbook-container');
  if (container) container.style.transform = `scale(${currentZoom})`;
  const label = document.getElementById('zoom-label');
  if (label) label.textContent = Math.round(currentZoom * 100) + '%';
  const btnIn  = document.getElementById('btn-zoom-in');
  const btnOut = document.getElementById('btn-zoom-out');
  if (btnIn)  btnIn.disabled  = currentZoom >= MAX_ZOOM;
  if (btnOut) btnOut.disabled = currentZoom <= MIN_ZOOM;
}

function playFlipSound() {
  try {
    const ctx      = new (window.AudioContext || window.webkitAudioContext)();
    const sr       = ctx.sampleRate;
    const duration = 0.28;
    const buf      = ctx.createBuffer(1, Math.floor(sr * duration), sr);
    const data     = buf.getChannelData(0);

    for (let i = 0; i < data.length; i++) {
      const t    = i / sr;
      const prog = i / data.length;

      // Softer noise (less harsh than pure white noise)
      const noise = (Math.random() * 2 - 1) * 0.4;

      // Smooth envelope (gentle fade in + natural decay)
      const attack = Math.pow(prog, 0.5);
      const decay  = Math.exp(-prog * 6);
      const env    = attack * decay;

      // Very subtle low-frequency body (soft page movement)
      const body = Math.sin(2 * Math.PI * 90 * t) * Math.exp(-prog * 10) * 0.15;

      // Airy paper texture
      const rustle = noise * Math.pow(1 - prog, 2) * 0.5;

      data[i] = (rustle + body) * env * 0.6;
    }

    const src = ctx.createBufferSource();
    src.buffer = buf;

    // Softer band-pass for natural paper tone
    const bp = ctx.createBiquadFilter();
    bp.type = 'bandpass';
    bp.frequency.value = 1400;
    bp.Q.value = 0.5;

    // Light high-shelf (reduced sharpness)
    const hs = ctx.createBiquadFilter();
    hs.type = 'highshelf';
    hs.frequency.value = 3500;
    hs.gain.value = 2;

    // Smooth master gain
    const gain = ctx.createGain();
    gain.gain.setValueAtTime(0.5, ctx.currentTime);
    gain.gain.linearRampToValueAtTime(0, ctx.currentTime + duration);

    src.connect(bp);
    bp.connect(hs);
    hs.connect(gain);
    gain.connect(ctx.destination);

    src.start();
  } catch (e) {}
}

function destroyFlipbook() {
  if (pageFlip) { try { pageFlip.destroy(); } catch(e){} pageFlip = null; }
  renderedPages = [];
  document.getElementById('flipbook-container').innerHTML = '';
}

// ── Year switcher ─────────────────────────────────────────────────────────────
function switchYear(btn, year) {
  document.querySelectorAll('.year-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.emag-grid-section').forEach(s => s.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('year-' + year).classList.add('active');
}

// ── Attach cover-click listeners ──────────────────────────────────────────────
document.querySelectorAll('.flipbook-trigger').forEach(el => {
  el.addEventListener('click', function () {
    <?php if (!hasFullAccess()): ?>
      window.location.href = "restricted-emag.php";
      return;
    <?php endif; ?>
    openFlipbook(this.getAttribute('data-pdf'), this.getAttribute('data-title'));
  });
});

// Clean up when modal closes
document.getElementById('flipbookModal').addEventListener('hidden.bs.modal', destroyFlipbook);
</script>

<?php include '../includes/footer.php'; ?>
