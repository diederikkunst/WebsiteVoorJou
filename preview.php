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

// Screenshot via thum.io — geen API key nodig
$screenshotUrl = 'https://image.thum.io/get/width/1280/crop/900/' . $project['preview_url'];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview — <?= htmlspecialchars($project['name']) ?> — WebSiteVoorJou</title>
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

    /* Scrollable screenshot area */
    .preview-scroll {
      flex: 1; overflow-y: auto; overflow-x: hidden;
      position: relative; background: #e8eaf0;
    }

    /* Screenshot wrapper — centered, max-width, with watermarks */
    .preview-shot-wrap {
      position: relative;
      display: block;
      width: 100%;
      max-width: 1280px;
      margin: 0 auto;
      user-select: none;
    }
    .preview-shot-wrap img {
      display: block; width: 100%; height: auto;
      pointer-events: none;
    }

    /* Transparent click blocker */
    .preview-click-block {
      position: absolute; inset: 0; z-index: 5;
      cursor: not-allowed; background: transparent;
    }

    /* Repeating diagonal watermark pattern */
    .preview-wm-grid {
      position: absolute; inset: 0; z-index: 6;
      pointer-events: none; overflow: hidden;
      display: flex; flex-direction: column; gap: 120px;
      padding: 60px 0;
    }
    .preview-wm-row {
      display: flex; gap: 200px; white-space: nowrap;
      transform: rotate(-25deg);
      transform-origin: center center;
      opacity: 0.12;
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

    /* Loading state */
    .preview-loading {
      position: absolute; inset: 0; display: flex;
      flex-direction: column; align-items: center; justify-content: center;
      gap: 16px; background: var(--bg-2); z-index: 2;
    }
    .preview-loading .spinner {
      width: 40px; height: 40px; border-radius: 50%;
      border: 3px solid var(--border);
      border-top-color: var(--primary);
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body class="preview-mode">

  <!-- Top bar -->
  <div class="preview-bar">
    <span class="preview-bar-brand">WebSiteVoorJou</span>
    <span class="preview-bar-info" style="display:flex;align-items:center;gap:10px;">
      <?php if (!empty($project['client_logo'])): ?>
        <img src="/uploads/<?= htmlspecialchars($project['client_logo']) ?>" alt="Logo" style="height:28px;max-width:80px;object-fit:contain;border-radius:4px;background:#fff;padding:2px 4px;">
      <?php endif; ?>
      Preview: <strong><?= htmlspecialchars($project['name']) ?></strong>
      &nbsp;&bull;&nbsp; Concept — nog niet definitief
    </span>
    <a href="/#contact" class="btn btn-primary btn-sm">Interesse? &#8594;</a>
  </div>

  <!-- Corner badges (fixed so always visible while scrolling) -->
  <div class="preview-badge tl">PREVIEW</div>
  <div class="preview-badge tr">WebSiteVoorJou.nl</div>
  <div class="preview-badge bl">CONCEPT &copy; <?= date('Y') ?></div>
  <div class="preview-badge br">Niet definitief</div>

  <!-- Scrollable screenshot -->
  <div class="preview-scroll">
    <div class="preview-shot-wrap" id="shotWrap">

      <!-- Loading indicator -->
      <div class="preview-loading" id="loading">
        <div class="spinner"></div>
        <p id="loadMsg" style="color:var(--text-muted);font-size:0.9rem;">Screenshot wordt gemaakt...</p>
        <p id="loadSub" style="color:var(--text-muted);font-size:0.8rem;display:none;">Dit kan tot 30 seconden duren bij een nieuw domein.</p>
      </div>

      <!-- Screenshot image -->
      <img
        id="shotImg"
        src="<?= htmlspecialchars($screenshotUrl) ?>"
        alt="Website preview"
        style="display:none;"
        draggable="false"
      >

      <!-- Click blocker overlay -->
      <div class="preview-click-block"></div>

      <!-- Watermark grid -->
      <div class="preview-wm-grid" id="wmGrid"></div>
    </div>
  </div>

<script>
(function() {
  var img     = document.getElementById('shotImg');
  var loading = document.getElementById('loading');
  var wrap    = document.getElementById('shotWrap');
  var wmGrid  = document.getElementById('wmGrid');

  // Build repeating watermark rows
  for (var r = 0; r < 12; r++) {
    var row = document.createElement('div');
    row.className = 'preview-wm-row';
    for (var c = 0; c < 6; c++) {
      var s = document.createElement('span');
      s.textContent = 'PREVIEW — WebSiteVoorJou.nl';
      row.appendChild(s);
    }
    wmGrid.appendChild(row);
  }

  // Show sub-message after 5 seconds
  var subTimer = setTimeout(function() {
    document.getElementById('loadSub').style.display = 'block';
    document.getElementById('loadMsg').textContent = 'Nog even geduld...';
  }, 5000);

  // Show retry button after 35 seconds
  var retryTimer = setTimeout(function() {
    loading.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;text-align:center;padding:0 24px;">Screenshot kon niet worden geladen.</p>'
      + '<button onclick="location.reload()" style="margin-top:16px;padding:10px 24px;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;border:none;border-radius:8px;font-size:0.9rem;font-weight:600;cursor:pointer;">Opnieuw proberen</button>';
  }, 35000);

  img.onload = function() {
    clearTimeout(subTimer);
    clearTimeout(retryTimer);
    loading.style.display = 'none';
    img.style.display = 'block';
  };

  img.onerror = function() {
    clearTimeout(subTimer);
    clearTimeout(retryTimer);
    loading.innerHTML = '<p style="color:var(--text-muted);font-size:0.9rem;text-align:center;padding:0 24px;">Screenshot kon niet worden geladen.</p>'
      + '<button onclick="location.reload()" style="margin-top:16px;padding:10px 24px;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;border:none;border-radius:8px;font-size:0.9rem;font-weight:600;cursor:pointer;">Opnieuw proberen</button>';
  };

  // Disable right-click, selection, copy, keyboard shortcuts
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
