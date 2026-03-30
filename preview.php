<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$token   = trim($_GET['token'] ?? '');
$project = null;

if ($token) {
    $project = getProjectByToken($token);
}

if (!$project || empty($project['preview_url'])) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Preview niet gevonden</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css"></head>
    <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;">
    <div><h1 style="font-size:4rem;">404</h1><h2>Preview niet gevonden</h2>
    <p>Deze preview link is verlopen of ongeldig.</p>
    <a href="/" class="btn btn-primary" style="margin-top:24px;">Terug naar home</a></div>
    </body></html>
    <?php
    exit;
}

$screenshotFallback = 'https://image.thum.io/get/width/1280/fullpage/' . $project['preview_url'];
$previewUrl = htmlspecialchars($project['preview_url']);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview — <?= htmlspecialchars($project['name']) ?> — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); overflow: hidden; height: 100vh; display: flex; flex-direction: column; }

    .preview-bar {
      background: var(--bg-2); border-bottom: 1px solid var(--border);
      padding: 10px 20px; display: flex; align-items: center;
      justify-content: space-between; gap: 16px; flex-shrink: 0; z-index: 20;
    }
    .preview-bar-brand {
      font-size: 1rem; font-weight: 800;
      background: var(--gradient); -webkit-background-clip: text;
      -webkit-text-fill-color: transparent; background-clip: text;
    }
    .preview-bar-info { font-size: 0.85rem; color: var(--text-muted); }
    .preview-bar-info strong { color: var(--text); }

    /* Main preview area */
    .preview-wrap {
      flex: 1; position: relative; overflow: hidden; background: #e8eaf0;
    }

    /* Iframe */
    #previewFrame {
      width: 100%; height: 100%; border: none; display: block;
    }

    /* Watermark grid over iframe */
    .preview-wm-grid {
      position: absolute; inset: 0; z-index: 6;
      pointer-events: none; overflow: hidden;
      display: flex; flex-direction: column; gap: 140px;
      padding: 60px 0;
    }
    .preview-wm-row {
      display: flex; gap: 200px; white-space: nowrap;
      transform: rotate(-25deg);
      transform-origin: center center;
      opacity: 0.10;
    }
    .preview-wm-row span {
      font-size: 1.6rem; font-weight: 900;
      text-transform: uppercase; letter-spacing: 0.2em;
      color: #6C63FF; user-select: none;
    }

    /* Corner badges */
    .preview-badge {
      position: fixed; z-index: 10;
      font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
      background: rgba(108,99,255,0.85); color: #fff;
      padding: 5px 12px; border-radius: 4px;
      pointer-events: none; user-select: none;
    }
    .preview-badge.tl { top: 56px;  left: 12px; }
    .preview-badge.tr { top: 56px;  right: 12px; }
    .preview-badge.bl { bottom: 12px; left: 12px; }
    .preview-badge.br { bottom: 12px; right: 12px; }

    /* Screenshot fallback (scrollable) */
    #screenshotFallback {
      display: none; position: absolute; inset: 0;
      overflow-y: auto; overflow-x: hidden; z-index: 1;
    }
    #screenshotFallback img {
      display: block; width: 100%; max-width: 1280px;
      height: auto; margin: 0 auto;
      pointer-events: none; user-select: none;
    }

    /* Loading state */
    #loadingOverlay {
      position: absolute; inset: 0; display: flex;
      flex-direction: column; align-items: center; justify-content: center;
      gap: 16px; background: var(--bg-2); z-index: 8;
    }
    #loadingOverlay .spinner {
      width: 40px; height: 40px; border-radius: 50%;
      border: 3px solid var(--border);
      border-top-color: var(--primary);
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Blocked notice */
    #blockedNotice {
      display: none; position: absolute; inset: 0; z-index: 9;
      flex-direction: column; align-items: center; justify-content: center;
      gap: 12px; background: var(--bg-2); text-align: center; padding: 32px;
    }
  </style>
</head>
<body class="preview-mode">

  <!-- Top bar -->
  <div class="preview-bar">
    <span class="preview-bar-brand">WebsiteVoorJou</span>
    <span class="preview-bar-info" style="display:flex;align-items:center;gap:10px;">
      <?php $barLogo = $project['logo'] ?? $project['client_logo'] ?? ''; ?>
      <?php if ($barLogo): ?>
        <img src="/uploads/<?= htmlspecialchars($barLogo) ?>" alt="Logo" style="height:28px;max-width:80px;object-fit:contain;border-radius:4px;background:#fff;padding:2px 4px;">
      <?php endif; ?>
      Preview: <strong><?= htmlspecialchars($project['name']) ?></strong>
      &nbsp;&bull;&nbsp; Concept — nog niet definitief
    </span>
    <a href="/#contact" class="btn btn-primary btn-sm">Interesse? &#8594;</a>
  </div>

  <!-- Corner badges (fixed so always visible while scrolling) -->
  <div class="preview-badge tl">PREVIEW</div>
  <div class="preview-badge tr">WebsiteVoorJou.nl</div>
  <div class="preview-badge bl">CONCEPT &copy; <?= date('Y') ?></div>
  <div class="preview-badge br">Niet definitief</div>

  <!-- Preview area -->
  <div class="preview-wrap" id="previewWrap">

    <!-- Iframe via proxy (omzeilt X-Frame-Options) -->
    <iframe id="previewFrame" src="/preview-proxy.php?token=<?= urlencode($token) ?>"></iframe>

    <!-- Screenshot fallback -->
    <div id="screenshotFallback">
      <img id="shotImg" src="" alt="Website preview" draggable="false">
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay">
      <div class="spinner"></div>
      <p style="color:var(--text-muted);font-size:0.9rem;" id="loadMsg">Website wordt geladen...</p>
    </div>

    <!-- Blocked notice (shown when iframe is blocked) -->
    <div id="blockedNotice">
      <p style="color:var(--text-muted);font-size:0.95rem;">Deze website blokkeert inladen in een frame.<br>We tonen een screenshot als alternatief.</p>
    </div>

    <!-- Watermark grid -->
    <div class="preview-wm-grid" id="wmGrid"></div>
  </div>

<script>
(function() {
  var frame       = document.getElementById('previewFrame');
  var loading     = document.getElementById('loadingOverlay');
  var blocked     = document.getElementById('blockedNotice');
  var fallback    = document.getElementById('screenshotFallback');
  var shotImg     = document.getElementById('shotImg');
  var wmGrid      = document.getElementById('wmGrid');
  var fallbackUrl = <?= json_encode($screenshotFallback) ?>;

  // Build watermark rows
  for (var r = 0; r < 14; r++) {
    var row = document.createElement('div');
    row.className = 'preview-wm-row';
    for (var c = 0; c < 6; c++) {
      var s = document.createElement('span');
      s.textContent = 'PREVIEW — WebsiteVoorJou.nl';
      row.appendChild(s);
    }
    wmGrid.appendChild(row);
  }

  // Detecteer of iframe geladen is via postMessage (betrouwbaarder dan onload)
  var iframeLoaded = false;
  var loadTimeout = setTimeout(function() {
    if (!iframeLoaded) showFallback();
  }, 20000);

  window.addEventListener('message', function(e) {
    if (e.data === 'preview_loaded') {
      iframeLoaded = true;
      clearTimeout(loadTimeout);
      loading.style.display = 'none';
    }
  });

  // onload als fallback (bijv. als proxy previewNotice toont)
  frame.onload = function() {
    if (!iframeLoaded) {
      iframeLoaded = true;
      clearTimeout(loadTimeout);
      loading.style.display = 'none';
    }
  };

  frame.onerror = function() {
    clearTimeout(loadTimeout);
    showFallback();
  };

  function showFallback() {
    frame.style.display = 'none';
    blocked.style.display = 'flex';
    loading.style.display = 'none';

    // Load screenshot
    setTimeout(function() {
      blocked.style.display = 'none';
      fallback.style.display = 'block';
      loading.style.display = 'flex';
      document.getElementById('loadMsg').textContent = 'Screenshot wordt gemaakt...';

      shotImg.onload = function() { loading.style.display = 'none'; };
      shotImg.onerror = function() {
        loading.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;padding:0 24px;">Kon niet laden.</p>'
          + '<button onclick="location.reload()" style="margin-top:16px;padding:10px 24px;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Opnieuw proberen</button>';
      };
      shotImg.src = fallbackUrl;
    }, 1500);
  }

  // Disable right-click, selection, copy
  document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
  document.addEventListener('selectstart', function(e) { e.preventDefault(); });
  document.addEventListener('copy',        function(e) { e.preventDefault(); });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'F12' || (e.ctrlKey && ['u','s','a','p'].includes(e.key.toLowerCase()))) {
      e.preventDefault();
    }
  });
})();
</script>
</body>
</html>
