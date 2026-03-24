<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$preselectedClientId = (int)($_GET['client_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId   = (int)($_POST['client_id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $package    = $_POST['package'] ?? 'brons';
    $previewUrl = trim($_POST['preview_url'] ?? '');
    $status     = $_POST['status'] ?? 'nieuw';

    if (!in_array($package, ['brons','zilver','goud','platinum'])) $package = 'brons';
    if (!array_key_exists($status, statusOptions())) $status = 'nieuw';

    if (!$clientId) {
        $error = 'Selecteer een klant.';
    } elseif (!$name) {
        $error = 'Vul een projectnaam in.';
    } else {
        $stmt = $db->prepare('INSERT INTO projects (client_id, name, description, package, preview_url, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$clientId, $name, $desc, $package, $previewUrl ?: null, $status]);
        $projectId = $db->lastInsertId();

        // Automatisch preview token aanmaken als preview URL + status preview_beschikbaar
        if ($previewUrl && $status === 'preview_beschikbaar') {
            createPreviewToken($projectId);
        }

        header('Location: /admin/project-detail.php?id=' . $projectId . '&new=1');
        exit;
    }
}

$clients = $db->query("SELECT id, name, type FROM clients ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nieuw project — WebSiteVoorJou Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/admin/projects.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Projecten</a>
          <h1 style="margin-top:4px;">Nieuw project aanmaken</h1>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:680px;">
      <form method="post">
        <div class="form-group">
          <label class="form-label">Klant *</label>
          <select name="client_id" class="form-control" required>
            <option value="">— Selecteer klant of lead —</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (int)($preselectedClientId ?: ($_POST['client_id'] ?? 0)) === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?> (<?= $c['type'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Staat de klant er niet bij? <a href="/admin/new-client.php">Maak eerst een klant aan.</a></p>
        </div>

        <div class="form-group">
          <label class="form-label">Projectnaam *</label>
          <input type="text" name="name" class="form-control" placeholder="Bijv. Nieuwe website bakkerij De Krent" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Beschrijving</label>
          <textarea name="description" class="form-control" rows="4" placeholder="Wat wil de klant? Welke wensen zijn er doorgegeven?"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pakket</label>
            <select name="package" class="form-control">
              <option value="brons"    <?= ($_POST['package'] ?? 'brons') === 'brons'    ? 'selected' : '' ?>>&#127881; Brons — Gratis preview</option>
              <option value="zilver"   <?= ($_POST['package'] ?? '') === 'zilver'   ? 'selected' : '' ?>>&#127748; Zilver — €999</option>
              <option value="goud"     <?= ($_POST['package'] ?? '') === 'goud'     ? 'selected' : '' ?>>&#11088; Goud — €2.999</option>
              <option value="platinum" <?= ($_POST['package'] ?? '') === 'platinum' ? 'selected' : '' ?>>&#128142; Platinum — Op maat</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control status-select">
              <?php foreach (statusOptions() as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= ($_POST['status'] ?? 'nieuw') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Preview URL (optioneel)</label>
          <input type="url" name="preview_url" class="form-control" placeholder="https://klant.websitevoorjou.nl" value="<?= htmlspecialchars($_POST['preview_url'] ?? '') ?>">
          <p class="form-hint">Stel status in op "Preview beschikbaar" om automatisch een preview-token aan te maken.</p>
        </div>

        <div style="display:flex;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary">Project aanmaken &#8594;</button>
          <a href="/admin/projects.php" class="btn btn-outline">Annuleren</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
