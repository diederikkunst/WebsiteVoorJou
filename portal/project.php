<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

$projectId = (int)($_GET['id'] ?? 0);
$isNew     = isset($_GET['new']);

if (!$client || !$projectId) {
    header('Location: /portal/dashboard.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
$stmt->execute([$projectId, $client['id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: /portal/dashboard.php');
    exit;
}

$error   = '';
$success = '';

// Handle project update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $newName = trim($_POST['name'] ?? '');
    $newDesc = trim($_POST['description'] ?? '');

    if (!$newName) {
        $error = 'Projectnaam mag niet leeg zijn.';
    } else {
        $db->prepare('UPDATE projects SET name = ?, description = ? WHERE id = ? AND client_id = ?')
           ->execute([$newName, $newDesc, $projectId, $client['id']]);
        $success = 'Project bijgewerkt.';
        // Reload
        $stmt->execute([$projectId, $client['id']]);
        $project = $stmt->fetch();
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_files'])) {
    $uploaded = 0;
    foreach (['documents','images'] as $field) {
        if (!empty($_FILES[$field]['name'][0])) {
            $files = $_FILES[$field];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $single = ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]];
                    $isImage = $field === 'images';
                    $type    = $isImage ? 'image' : 'document';
                    $subdir  = $isImage ? 'images' : 'documents';
                    $path    = saveUpload($single, $subdir);
                    if ($path) {
                        $stmt2 = $db->prepare('INSERT INTO project_files (project_id, filename, original_name, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?)');
                        $stmt2->execute([$projectId, $path, $files['name'][$i], $type, $user['id']]);
                        $uploaded++;
                    }
                }
            }
        }
    }
    if ($uploaded > 0) {
        $success = "$uploaded bestand(en) succesvol geüpload.";
    } else {
        $error = 'Geen bestanden geüpload. Controleer het bestandsformaat (max 10MB).';
    }
}

// Reload project files
$stmt3 = $db->prepare('SELECT * FROM project_files WHERE project_id = ? ORDER BY uploaded_at DESC');
$stmt3->execute([$projectId]);
$files = $stmt3->fetchAll();

// Preview token
$tokenRow = null;
if ($project['status'] === 'preview_beschikbaar') {
    $stmt4 = $db->prepare('SELECT token FROM preview_tokens WHERE project_id = ? AND expires_at > NOW() LIMIT 1');
    $stmt4->execute([$projectId]);
    $tokenRow = $stmt4->fetch();
}

$statusList = ['nieuw','in_behandeling','preview_beschikbaar','afgerond','factuur_gestuurd','factuur_betaald'];
$currentIdx = array_search($project['status'], $statusList);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($project['name']) ?> — WebSiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <aside class="sidebar">
    <div class="sidebar-brand">WebSiteVoorJou</div>
    <ul class="sidebar-nav">
      <li><a href="/portal/dashboard.php"><span class="nav-icon">&#127968;</span> Dashboard</a></li>
      <li><a href="/portal/new-project.php"><span class="nav-icon">&#43;</span> Nieuw project</a></li>
    </ul>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="sidebar-user-role">Klant</div>
        </div>
      </div>
      <a href="/logout.php" class="btn btn-outline btn-sm w-full" style="margin-top:8px;">Uitloggen</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($isNew): ?>
      <div class="alert alert-success" data-dismiss="5000">&#10003; Project aangemaakt! We nemen snel contact met je op.</div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="5000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/portal/dashboard.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Terug naar dashboard</a>
          <h1 style="margin-top:4px;"><?= htmlspecialchars($project['name']) ?></h1>
          <div style="display:flex;gap:10px;align-items:center;margin-top:6px;">
            <?= statusLabel($project['status']) ?>
            <?= packageLabel($project['package']) ?>
          </div>
        </div>
        <?php if ($tokenRow): ?>
          <a href="/preview.php?token=<?= htmlspecialchars($tokenRow['token']) ?>" target="_blank" class="btn btn-primary">&#128065; Bekijk preview</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status timeline -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">Voortgang</h3></div>
      <div class="status-timeline">
        <?php foreach ($statusList as $i => $s): ?>
          <?php
            $icons = ['&#127381;','&#9881;','&#128065;','&#10003;','&#128195;','&#9989;'];
            $labels = ['Nieuw','In behandeling','Preview klaar','Afgerond','Factuur gestuurd','Factuur betaald'];
            $isDone   = $i < $currentIdx;
            $isActive = $i === $currentIdx;
          ?>
          <div class="timeline-step <?= $isDone ? 'done' : ($isActive ? 'active' : '') ?>">
            <div class="timeline-dot"><?= $isDone ? '&#10003;' : $icons[$i] ?></div>
            <div class="timeline-label"><?= $labels[$i] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid-2">
      <!-- Project info / edit -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Projectdetails</h3></div>
        <form method="post">
          <input type="hidden" name="update_project" value="1">
          <div class="form-group">
            <label class="form-label">Projectnaam</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($project['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Beschrijving</label>
            <textarea name="description" class="form-control" rows="6" placeholder="Beschrijf je wensen, doelgroep, stijl, aanpassingen..."><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
            <p class="form-hint">Vertel ons alles wat je wil aanpassen of doorgeven. We lezen het direct.</p>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
        </form>
        <div class="divider"></div>
        <div style="font-size:0.85rem;color:var(--text-muted);">
          Aangemaakt: <?= formatDateTime($project['created_at']) ?><br>
          Laatste update: <?= formatDateTime($project['updated_at']) ?>
        </div>
        <?php if ($project['preview_url'] && $project['status'] !== 'nieuw'): ?>
        <div class="divider"></div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Preview</label>
          <?php if ($tokenRow): ?>
            <a href="/preview.php?token=<?= htmlspecialchars($tokenRow['token']) ?>" target="_blank" class="btn btn-sm btn-outline">&#128065; Preview bekijken</a>
          <?php else: ?>
            <p class="text-muted">Preview link verlopen of niet beschikbaar.</p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Upload files -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Bestanden uploaden</h3></div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="upload_files" value="1">
          <div class="form-group">
            <label class="form-label">Documenten (PDF, Word, etc.)</label>
            <div class="file-drop">
              <div class="file-drop-icon">&#128196;</div>
              <p>Sleep bestanden hier naartoe of klik om te selecteren</p>
              <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
              <div class="file-list"></div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Afbeeldingen (logo, foto's, etc.)</label>
            <div class="file-drop">
              <div class="file-drop-icon">&#128247;</div>
              <p>Sleep afbeeldingen hier naartoe of klik om te selecteren</p>
              <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.svg" style="display:none;">
              <div class="file-list"></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Uploaden</button>
        </form>
      </div>
    </div>

    <!-- Uploaded files list -->
    <?php if (!empty($files)): ?>
    <div class="card" style="margin-top:24px;">
      <div class="card-header"><h3 class="card-title">Geüploade bestanden</h3></div>
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

    <!-- Upgrade suggestion -->
    <?php if ($project['package'] === 'zilver'): ?>
    <div class="alert alert-info" style="margin-top:24px;">
      &#128161; Wil je zelf de inhoud van je website kunnen beheren? Upgrade naar <strong>Pakket Goud</strong> voor een volledig CMS. Neem contact op via <a href="mailto:info@websitevoorjou.nl">info@websitevoorjou.nl</a>.
    </div>
    <?php endif; ?>

    <?php if (in_array($project['package'], ['brons','zilver','goud'])): ?>
    <div class="alert alert-info" style="margin-top:24px;">
      &#128640; Heb je ook <strong>bedrijfssoftware</strong> nodig? Wij bouwen klantportalen, boekingssystemen, ERP-koppelingen en meer. Neem contact op en we bespreken de mogelijkheden.
    </div>
    <?php endif; ?>

  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
