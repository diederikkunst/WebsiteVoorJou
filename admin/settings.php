<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$db = getDB();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $keys = ['portfolio_url_1', 'portfolio_url_2', 'portfolio_url_3'];
    foreach ($keys as $key) {
        $value = trim($_POST[$key] ?? '');
        // Valideer: leeg of geldige URL
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $error = 'Ongeldige URL bij ' . $key . '. Zorg dat de URL begint met https://.';
            break;
        }
        $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?')
           ->execute([$key, $value, $value]);
    }
    if (!$error) $success = 'Instellingen opgeslagen.';
}

// Laad huidige waarden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instellingen — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="4000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <h1>Instellingen</h1>
    </div>

    <!-- Portfolio URLs -->
    <div class="card" style="max-width:640px;">
      <div class="card-header"><h3 class="card-title">&#127760; Portfolio — voorbeeldwebsites</h3></div>
      <p style="color:var(--text-muted);margin-bottom:20px;font-size:0.9rem;">
        Deze websites worden getoond in de portfolio-sectie op de homepage. Laat een veld leeg om het te verbergen.
      </p>
      <form method="post">
        <input type="hidden" name="save_settings" value="1">
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="form-group">
          <label class="form-label">Voorbeeld <?= $i ?></label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="url" name="portfolio_url_<?= $i ?>" class="form-control"
              placeholder="https://klant.websitevoorjou.nl"
              value="<?= htmlspecialchars($settings['portfolio_url_' . $i] ?? '') ?>">
            <?php if (!empty($settings['portfolio_url_' . $i])): ?>
              <a href="<?= htmlspecialchars($settings['portfolio_url_' . $i]) ?>" target="_blank" class="btn btn-sm btn-outline" style="flex-shrink:0;">&#128065;</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endfor; ?>

        <?php
          $previews = array_filter([
              $settings['portfolio_url_1'] ?? '',
              $settings['portfolio_url_2'] ?? '',
              $settings['portfolio_url_3'] ?? '',
          ]);
        ?>
        <?php if (!empty($previews)): ?>
        <div style="margin-bottom:20px;">
          <div class="form-label" style="margin-bottom:10px;">Voorbeeld preview</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <?php foreach ($previews as $url): ?>
              <div style="border-radius:8px;overflow:hidden;border:1px solid var(--border);width:180px;">
                <img src="https://image.thum.io/get/width/400/crop/250/noanimate/<?= htmlspecialchars($url) ?>"
                  alt="preview" style="width:100%;display:block;" loading="lazy">
                <div style="padding:6px 10px;font-size:0.75rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= htmlspecialchars(parse_url($url, PHP_URL_HOST) ?: $url) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Opslaan</button>
      </form>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
