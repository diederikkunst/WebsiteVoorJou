<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { header('Location: /admin/projects.php'); exit; }

$stmt = $db->prepare('SELECT p.*, c.name AS client_name, c.email AS client_email, c.id AS client_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: /admin/projects.php'); exit; }

$success = $error = '';

// Update status + preview_url
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $newStatus = $_POST['status'] ?? $project['status'];
    $previewUrl = trim($_POST['preview_url'] ?? '');
    $package   = $_POST['package'] ?? $project['package'];

    $validStatuses = array_keys(statusOptions());
    if (!in_array($newStatus, $validStatuses)) $newStatus = $project['status'];

    $stmt2 = $db->prepare('UPDATE projects SET status = ?, preview_url = ?, package = ? WHERE id = ?');
    $stmt2->execute([$newStatus, $previewUrl, $package, $projectId]);
    $success = 'Project bijgewerkt.';

    // If status changed to preview_beschikbaar, create token
    if ($newStatus === 'preview_beschikbaar' && $previewUrl) {
        createPreviewToken($projectId);
    }

    // Reload
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
}

// Assign / remove employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_employee'])) {
    $empId = (int)$_POST['employee_id'];
    if ($empId) {
        $stmt3 = $db->prepare('INSERT IGNORE INTO project_employees (project_id, user_id) VALUES (?, ?)');
        $stmt3->execute([$projectId, $empId]);
        $success = 'Medewerker toegevoegd.';
    }
}

if (isset($_GET['remove_employee'])) {
    $empId = (int)$_GET['remove_employee'];
    $db->prepare('DELETE FROM project_employees WHERE project_id = ? AND user_id = ?')->execute([$projectId, $empId]);
    header('Location: /admin/project-detail.php?id=' . $projectId);
    exit;
}

// Get assigned employees
$assignedStmt = $db->prepare('SELECT u.id, u.name FROM project_employees pe JOIN users u ON u.id = pe.user_id WHERE pe.project_id = ?');
$assignedStmt->execute([$projectId]);
$assignedEmployees = $assignedStmt->fetchAll();
$assignedIds = array_column($assignedEmployees, 'id');

$allEmployees = $db->query("SELECT id, name FROM users WHERE role IN ('admin','employee') ORDER BY name")->fetchAll();

// Files
$files = $db->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC');
$files->execute([$projectId]);
$files = $files->fetchAll();

// Invoice
$invoice = $db->prepare('SELECT * FROM invoices WHERE project_id = ? ORDER BY created_at DESC LIMIT 1');
$invoice->execute([$projectId]);
$invoice = $invoice->fetch();

// Preview token
$tokenRow = $db->prepare('SELECT token, expires_at FROM preview_tokens WHERE project_id = ? AND expires_at > NOW() LIMIT 1');
$tokenRow->execute([$projectId]);
$tokenRow = $tokenRow->fetch();

$statusList = ['nieuw','in_behandeling','preview_beschikbaar','afgerond','factuur_gestuurd','factuur_betaald'];
$currentIdx = array_search($project['status'], $statusList);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($project['name']) ?> — Admin</title>
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
      <div class="page-header-row">
        <div>
          <a href="/admin/projects.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Projecten</a>
          <h1 style="margin-top:4px;"><?= htmlspecialchars($project['name']) ?></h1>
          <div style="display:flex;gap:10px;align-items:center;margin-top:6px;">
            <?= statusLabel($project['status']) ?>
            <?= packageLabel($project['package']) ?>
            <a href="/admin/client-detail.php?id=<?= $project['client_id'] ?>" style="font-size:0.9rem;"><?= htmlspecialchars($project['client_name']) ?></a>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($tokenRow): ?>
            <a href="/preview.php?token=<?= htmlspecialchars($tokenRow['token']) ?>" target="_blank" class="btn btn-outline btn-sm">&#128065; Preview bekijken</a>
          <?php endif; ?>
          <?php if ($project['status'] === 'afgerond' && !$invoice): ?>
            <a href="/admin/invoice.php?project_id=<?= $projectId ?>" class="btn btn-primary btn-sm">&#128195; Factuur aanmaken</a>
          <?php endif; ?>
          <a href="/admin/send-preview.php?project_id=<?= $projectId ?>" class="btn btn-outline btn-sm">&#128231; Preview mailen</a>
        </div>
      </div>
    </div>

    <!-- Status timeline -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">Voortgang</h3></div>
      <div class="status-timeline">
        <?php foreach ($statusList as $i => $s): ?>
          <?php
            $icons = ['&#127381;','&#9881;','&#128065;','&#10003;','&#128195;','&#9989;'];
            $lbls  = ['Nieuw','In behandeling','Preview klaar','Afgerond','Factuur gestuurd','Betaald'];
            $isDone   = $i < $currentIdx;
            $isActive = $i === $currentIdx;
          ?>
          <div class="timeline-step <?= $isDone ? 'done' : ($isActive ? 'active' : '') ?>">
            <div class="timeline-dot"><?= $isDone ? '&#10003;' : $icons[$i] ?></div>
            <div class="timeline-label"><?= $lbls[$i] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid-2">
      <!-- Edit project -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Project bewerken</h3></div>
        <form method="post">
          <input type="hidden" name="update_project" value="1">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control status-select">
              <?php foreach (statusOptions() as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= $project['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Pakket</label>
            <select name="package" class="form-control">
              <?php foreach (['brons','zilver','goud','platinum'] as $pkg): ?>
                <option value="<?= $pkg ?>" <?= $project['package'] === $pkg ? 'selected' : '' ?>><?= ucfirst($pkg) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Preview URL</label>
            <input type="url" name="preview_url" class="form-control" placeholder="https://klant.websitevoorjou.nl" value="<?= htmlspecialchars($project['preview_url'] ?? '') ?>">
            <p class="form-hint">Zet status op "Preview beschikbaar" om automatisch een preview-token aan te maken.</p>
          </div>
          <?php if ($tokenRow): ?>
            <div class="form-group">
              <label class="form-label">Preview link (voor klant)</label>
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" value="<?= htmlspecialchars(APP_URL . '/preview.php?token=' . $tokenRow['token']) ?>" readonly>
                <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars(APP_URL . '/preview.php?token=' . $tokenRow['token']) ?>">Kopieer</button>
              </div>
              <p class="form-hint">Verloopt op: <?= formatDateTime($tokenRow['expires_at']) ?></p>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">Opslaan</button>
        </form>
      </div>

      <!-- Employees -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Medewerkers</h3></div>
        <?php if (!empty($assignedEmployees)): ?>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
            <?php foreach ($assignedEmployees as $e): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--bg-2);border-radius:8px;border:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="sidebar-avatar" style="width:30px;height:30px;font-size:0.8rem;"><?= strtoupper(substr($e['name'],0,1)) ?></div>
                  <span><?= htmlspecialchars($e['name']) ?></span>
                </div>
                <a href="?id=<?= $projectId ?>&remove_employee=<?= $e['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Medewerker verwijderen van dit project?">&#10005;</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted" style="margin-bottom:16px;">Nog geen medewerkers toegewezen.</p>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="assign_employee" value="1">
          <div style="display:flex;gap:8px;align-items:flex-end;">
            <div class="form-group flex-1" style="margin-bottom:0;">
              <label class="form-label">Medewerker toevoegen</label>
              <select name="employee_id" class="form-control">
                <option value="">Selecteer medewerker</option>
                <?php foreach ($allEmployees as $e): ?>
                  <?php if (!in_array($e['id'], $assignedIds)): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom:0;">Toevoegen</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Description -->
    <div class="card" style="margin-top:24px;">
      <div class="card-header"><h3 class="card-title">Projectbeschrijving</h3></div>
      <p><?= nl2br(htmlspecialchars($project['description'] ?: 'Geen beschrijving.')) ?></p>
      <div class="divider"></div>
      <div style="font-size:0.85rem;color:var(--text-muted);">
        Aangemaakt: <?= formatDateTime($project['created_at']) ?> &bull;
        Laatste update: <?= formatDateTime($project['updated_at']) ?>
      </div>
    </div>

    <!-- Files -->
    <?php if (!empty($files)): ?>
    <div class="card" style="margin-top:24px;">
      <div class="card-header"><h3 class="card-title">Bestanden (<?= count($files) ?>)</h3></div>
      <div class="file-list">
        <?php foreach ($files as $f): ?>
          <div class="file-item">
            <span class="file-item-icon"><?= $f['file_type'] === 'image' ? '&#128247;' : '&#128196;' ?></span>
            <span class="file-item-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-item-size text-muted"><?= formatDateTime($f['uploaded_at']) ?></span>
            <a href="/uploads/<?= htmlspecialchars($f['filename']) ?>" target="_blank" class="btn btn-sm btn-outline" style="flex-shrink:0;">Download</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Invoice -->
    <?php if ($invoice): ?>
    <div class="card" style="margin-top:24px;">
      <div class="card-header">
        <h3 class="card-title">Factuur</h3>
        <a href="/admin/invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline">Bekijken</a>
      </div>
      <div style="display:flex;gap:24px;flex-wrap:wrap;">
        <div><div class="form-label">Factuurnummer</div><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></div>
        <div><div class="form-label">Bedrag</div><strong>&euro;<?= number_format($invoice['amount'], 2, ',', '.') ?></strong></div>
        <div><div class="form-label">Status</div><?= statusLabel('factuur_' . $invoice['status']) ?></div>
        <div><div class="form-label">Aangemaakt</div><?= formatDate($invoice['created_at']) ?></div>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
