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
    body { margin: 0; padding: 0; overflow: hidden; }
    .preview-wrapper { position: fixed; inset: 0; display: flex; flex-direction: column; }
    .preview-bar { background: var(--bg); border-bottom: 1px solid var(--border); padding: 8px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; z-index: 20; flex-shrink: 0; }
    .preview-bar-brand { font-size: 1rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .preview-bar-info { font-size: 0.85rem; color: var(--text-muted); }
    .preview-bar-info strong { color: var(--text); }
    .preview-frame-container { flex: 1; position: relative; }
    .preview-frame-container iframe { width: 100%; height: 100%; border: none; display: block; }
    .preview-overlay { position: absolute; inset: 0; z-index: 5; cursor: not-allowed; background: transparent; }
    .preview-watermarks { position: absolute; inset: 0; pointer-events: none; z-index: 6; overflow: hidden; display: grid; place-items: center; }
    .preview-wm { font-size: 2.5rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.15em; color: rgba(108,99,255,0.18); transform: rotate(-30deg); user-select: none; pointer-events: none; white-space: nowrap; }
    .preview-wm-corner { position: absolute; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; background: rgba(108,99,255,0.7); color: #fff; padding: 4px 10px; border-radius: 4px; pointer-events: none; user-select: none; }
    .preview-wm-corner.top-left  { top: 12px; left: 12px; }
    .preview-wm-corner.top-right { top: 12px; right: 12px; }
    .preview-wm-corner.bot-left  { bottom: 12px; left: 12px; }
    .preview-wm-corner.bot-right { bottom: 12px; right: 12px; }
    .preview-bar-actions { display: flex; gap: 8px; }
  </style>
</head>
<body class="preview-mode">
<div class="preview-wrapper">
  <div class="preview-bar">
    <span class="preview-bar-brand">WebSiteVoorJou</span>
    <span class="preview-bar-info">
      Preview: <strong><?= htmlspecialchars($project['name']) ?></strong>
      &nbsp;&bull;&nbsp; Dit is een concept — nog niet definitief
    </span>
    <div class="preview-bar-actions">
      <a href="/?#contact" target="_blank" class="btn btn-primary btn-sm">Interesse? &#8594;</a>
    </div>
  </div>
  <div class="preview-frame-container">
    <div class="preview-overlay"></div>
    <div class="preview-watermarks">
      <div class="preview-wm">PREVIEW — WebSiteVoorJou.nl</div>
      <div class="preview-wm-corner top-left">PREVIEW</div>
      <div class="preview-wm-corner top-right">WebSiteVoorJou.nl</div>
      <div class="preview-wm-corner bot-left">CONCEPT</div>
      <div class="preview-wm-corner bot-right">&#169; <?= date('Y') ?></div>
    </div>
    <iframe
      id="preview-iframe"
      src=""
      sandbox="allow-same-origin allow-scripts allow-forms"
      title="Website preview"
    ></iframe>
  </div>
</div>
<script>
// Load preview URL via JS so it's not trivially visible in source
(function() {
  var encoded = <?= json_encode(base64_encode($project['preview_url'])) ?>;
  var url = atob(encoded);
  document.getElementById('preview-iframe').src = url;
})();
</script>
<script src="/assets/js/main.js"></script>
</body>
</html>
