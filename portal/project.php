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

// Accepteer preview: klantgegevens aanvullen + status naar afgerond
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_preview'])) {
    $address    = trim($_POST['address'] ?? '');
    $bankAcc    = trim($_POST['bank_account'] ?? '');
    $clientType = $_POST['client_type'] ?? 'particulier';
    $bedrijf    = trim($_POST['bedrijfsnaam'] ?? '');

    $kvk = trim($_POST['kvk'] ?? '');

    if (!$address || !$bankAcc) {
        $error = 'Vul je adres en rekeningnummer in.';
    } elseif ($clientType === 'zakelijk' && !$kvk) {
        $error = 'Vul je KVK-nummer in.';
    } else {
        // Update klantprofiel
        $updateFields = 'address = ?, bank_account = ?, kvk = ?, client_category = ?, type = ?';
        $updateParams = [$address, $bankAcc, $kvk, $clientType, 'client'];
        if ($clientType === 'zakelijk' && $bedrijf) {
            $updateFields .= ', name = ?';
            $updateParams[] = $bedrijf;
        }
        $updateParams[] = $client['id'];
        $db->prepare("UPDATE clients SET $updateFields WHERE id = ?")->execute($updateParams);

        // Zet project op afgerond
        $db->prepare("UPDATE projects SET status = 'afgerond' WHERE id = ? AND client_id = ?")
           ->execute([$projectId, $client['id']]);

        // Maak factuur aan als die er nog niet is
        $existingInv = $db->prepare('SELECT id FROM invoices WHERE project_id = ?');
        $existingInv->execute([$projectId]);
        if (!$existingInv->fetch()) {
            $packagePrices = ['brons' => 0, 'zilver' => 999, 'goud' => 2999, 'platinum' => 0];
            $amount = $packagePrices[$project['package']] ?? 0;
            $number = nextInvoiceNumber();
            $desc   = 'Websiteontwikkeling pakket ' . ucfirst($project['package']) . ' — ' . $project['name'];
            $due    = date('Y-m-d', strtotime('+30 days'));
            $db->prepare('INSERT INTO invoices (project_id, invoice_number, amount, description, due_date) VALUES (?, ?, ?, ?, ?)')
               ->execute([$projectId, $number, $amount, $desc, $due]);
        }

        $success = 'Bedankt! Je bent nu officieel klant. We sturen je binnenkort een factuur.';
        $stmt->execute([$projectId, $client['id']]);
        $project = $stmt->fetch();
        $client  = getClientForUser($user['id']);
    }
}

// Handle preview approval (legacy / herbevestiging)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_preview'])) {
    if (in_array($project['status'], ['preview_beschikbaar', 'afgerond'])) {
        $db->prepare("UPDATE projects SET status = 'afgerond' WHERE id = ? AND client_id = ?")
           ->execute([$projectId, $client['id']]);

        // Auto-create invoice if none exists yet
        $existingInv = $db->prepare('SELECT id FROM invoices WHERE project_id = ?');
        $existingInv->execute([$projectId]);
        if (!$existingInv->fetch()) {
            $packagePrices = ['brons' => 0, 'zilver' => 999, 'goud' => 2999, 'platinum' => 0];
            $amount = $packagePrices[$project['package']] ?? 0;
            $number = nextInvoiceNumber();
            $desc   = 'Websiteontwikkeling pakket ' . ucfirst($project['package']) . ' — ' . $project['name'];
            $due    = date('Y-m-d', strtotime('+30 days'));
            $db->prepare('INSERT INTO invoices (project_id, invoice_number, amount, description, due_date) VALUES (?, ?, ?, ?, ?)')
               ->execute([$projectId, $number, $amount, $desc, $due]);
        }

        $success = 'Preview goedgekeurd! We sturen je binnenkort een factuur.';
        $stmt->execute([$projectId, $client['id']]);
        $project = $stmt->fetch();
    }
}

// Handle go-live request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_go_live'])) {
    $preference = $_POST['go_live_preference'] ?? 'together';
    $label      = $preference === 'together' ? 'Samen online plaatsen' : 'Website downloaden';

    // Reload fresh description
    $descStmt = $db->prepare('SELECT description FROM projects WHERE id = ? AND client_id = ?');
    $descStmt->execute([$projectId, $client['id']]);
    $currentDesc = $descStmt->fetchColumn() ?? '';

    // Replace existing keuze or append new one
    if (preg_match('/\[GO-LIVE VERZOEK: [^\]]+\]/', $currentDesc)) {
        $newDesc = preg_replace('/\[GO-LIVE VERZOEK: [^\]]+\]/', '[GO-LIVE VERZOEK: ' . $label . ']', $currentDesc);
    } else {
        $newDesc = $currentDesc . "\n\n[GO-LIVE VERZOEK: " . $label . "]";
    }

    $db->prepare('UPDATE projects SET description = ? WHERE id = ? AND client_id = ?')
       ->execute([$newDesc, $projectId, $client['id']]);

    $success = $preference === 'together'
        ? 'We nemen zo snel mogelijk contact met je op om de website online te plaatsen!'
        : 'Je download wordt klaargezet. Je ontvangt een bericht zodra het beschikbaar is.';
    $stmt->execute([$projectId, $client['id']]);
    $project = $stmt->fetch();
}

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
  <title><?= htmlspecialchars($project['name']) ?> — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <aside class="sidebar">
    <div class="sidebar-brand">WebsiteVoorJou</div>
    <ul class="sidebar-nav">
      <li><a href="/portal/dashboard.php"><span class="nav-icon">&#127968;</span> Dashboard</a></li>
      <li><a href="/portal/new-project.php"><span class="nav-icon">&#43;</span> Nieuw project</a></li>
      <li><a href="/portal/questions.php"><span class="nav-icon">&#10067;</span> Mijn vragen</a></li>
      <li><a href="/portal/profile.php"><span class="nav-icon">&#128100;</span> Mijn profiel</a></li>
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

    <!-- Preview accepteren -->
    <?php if ($project['status'] === 'preview_beschikbaar' || $project['status'] === 'afgerond'):
        $hasInvoice = $db->prepare('SELECT id FROM invoices WHERE project_id = ?');
        $hasInvoice->execute([$projectId]);
        $hasInvoice = (bool)$hasInvoice->fetch();
    ?>
    <?php if ($project['status'] === 'preview_beschikbaar'): ?>
    <div class="card" style="margin-top:24px;border-color:var(--primary);">
      <h3 style="margin-bottom:6px;">&#127775; Je preview staat klaar!</h3>
      <p style="margin-bottom:20px;">Bekijk je website hieronder. Ben je tevreden? Vul dan je gegevens in en accepteer de preview. Je wordt daarna officieel klant en ontvangt een factuur.</p>
      <?php if ($tokenRow): ?>
        <a href="/preview.php?token=<?= htmlspecialchars($tokenRow['token']) ?>" target="_blank" class="btn btn-outline" style="margin-bottom:24px;display:inline-block;">&#128065; Preview bekijken</a>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="accept_preview" value="1">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Adres *</label>
            <input type="text" name="address" class="form-control" placeholder="Straat, huisnummer, postcode, plaats"
              value="<?= htmlspecialchars($client['address'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">IBAN rekeningnummer *</label>
            <input type="text" name="bank_account" class="form-control" placeholder="NL00 BANK 0000 0000 00"
              value="<?= htmlspecialchars($client['bank_account'] ?? '') ?>" required>
          </div>
        </div>
        <?php $savedCategory = $client['client_category'] ?? 'particulier'; ?>
        <div class="form-group">
          <label class="form-label">Type klant *</label>
          <div style="display:flex;gap:24px;margin-top:6px;">
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
              <input type="radio" name="client_type" value="particulier"
                <?= $savedCategory === 'particulier' ? 'checked' : '' ?>
                onchange="document.getElementById('zakelijk-rows').style.display='none'">
              Particulier
            </label>
            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
              <input type="radio" name="client_type" value="zakelijk"
                <?= $savedCategory === 'zakelijk' ? 'checked' : '' ?>
                onchange="document.getElementById('zakelijk-rows').style.display='block'">
              Zakelijk
            </label>
          </div>
        </div>
        <div id="zakelijk-rows" style="display:<?= $savedCategory === 'zakelijk' ? 'block' : 'none' ?>;">
          <div class="form-group">
            <label class="form-label">Bedrijfsnaam</label>
            <input type="text" name="bedrijfsnaam" class="form-control" placeholder="Naam van je bedrijf"
              value="<?= htmlspecialchars($_POST['bedrijfsnaam'] ?? $client['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">KVK-nummer *</label>
            <input type="text" name="kvk" class="form-control" placeholder="12345678"
              value="<?= htmlspecialchars($_POST['kvk'] ?? $client['kvk'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">&#10003; Accepteren &amp; officieel klant worden</button>
      </form>
    </div>
    <?php elseif ($project['status'] === 'afgerond' && !$hasInvoice): ?>
    <div class="card" style="margin-top:24px;border-color:var(--warning);">
      <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
          <h3 style="margin-bottom:6px;">&#9203; Wachten op factuur</h3>
          <p>Je hebt de preview goedgekeurd. We zijn je factuur aan het opmaken — dat duurt niet lang meer.</p>
          <p style="margin-top:8px;font-size:0.85rem;">Heb je de preview toch nog niet goed bekeken of wil je de goedkeuring opnieuw bevestigen?</p>
        </div>
        <form method="post" style="margin:0;flex-shrink:0;">
          <input type="hidden" name="approve_preview" value="1">
          <button type="submit" class="btn btn-outline btn-sm">&#8635; Goedkeuring opnieuw bevestigen</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Go-live opties na betaling -->
    <?php if (in_array($project['status'], ['afgerond', 'factuur_gestuurd', 'factuur_betaald'])):
        preg_match('/\[GO-LIVE VERZOEK: ([^\]]+)\]/', $project['description'] ?? '', $goLiveMatch);
        $previousChoice = $goLiveMatch[1] ?? null;
        $isTogether = $previousChoice === 'Samen online plaatsen';
        $isDownload = $previousChoice === 'Website downloaden';
    ?>
    <div class="card" style="margin-top:24px;border-color:var(--success);">
      <?php if ($previousChoice): ?>
        <!-- Eerder gemaakte keuze tonen -->
        <div style="display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
          <div>
            <h3 style="margin-bottom:4px;">&#10003; Keuze bevestigd</h3>
            <p>Je hebt gekozen voor: <strong><?= htmlspecialchars($previousChoice) ?></strong><?= $isTogether ? ' — we nemen binnenkort contact op.' : ' — de bestanden worden klaargezet.' ?></p>
          </div>
          <button onclick="document.getElementById('go-live-form').style.display='block';this.style.display='none';" class="btn btn-outline btn-sm">&#9998; Keuze wijzigen</button>
        </div>
        <div id="go-live-form" style="display:none;margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
      <?php else: ?>
        <h3 style="margin-bottom:6px;">
          <?php if ($project['status'] === 'factuur_betaald'): ?>&#127881; Betaling ontvangen — wat wil je nu doen?
          <?php elseif ($project['status'] === 'factuur_gestuurd'): ?>&#128195; Factuur ontvangen — geef je voorkeur door
          <?php else: ?>&#128640; Bijna klaar — hoe wil je de website ontvangen?
          <?php endif; ?>
        </h3>
        <p style="margin-bottom:20px;">
          <?php if ($project['status'] === 'factuur_betaald'): ?>Je website is klaar! Kies hoe je verder wilt gaan.
          <?php elseif ($project['status'] === 'factuur_gestuurd'): ?>Geef alvast aan hoe je de website wilt ontvangen zodra de betaling is verwerkt.
          <?php else: ?>Je preview is goedgekeurd! Geef alvast aan hoe je de website wilt ontvangen.
          <?php endif; ?>
        </p>
        <div id="go-live-form">
      <?php endif; ?>
          <form method="post">
            <div class="grid-2" style="margin-bottom:20px;">
              <label style="display:flex;gap:12px;align-items:flex-start;padding:16px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                <input type="radio" name="go_live_preference" value="together" <?= (!$previousChoice || $isTogether) ? 'checked' : '' ?> style="margin-top:3px;flex-shrink:0;">
                <div>
                  <strong>Samen online plaatsen</strong>
                  <p style="font-size:0.85rem;margin-top:4px;">Wij nemen contact op via je e-mail of telefoonnummer om samen de website live te zetten.</p>
                </div>
              </label>
              <label style="display:flex;gap:12px;align-items:flex-start;padding:16px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                <input type="radio" name="go_live_preference" value="download" <?= $isDownload ? 'checked' : '' ?> style="margin-top:3px;flex-shrink:0;">
                <div>
                  <strong>Website downloaden</strong>
                  <p style="font-size:0.85rem;margin-top:4px;">We zetten de bestanden klaar zodat je zelf de website kunt plaatsen.</p>
                </div>
              </label>
            </div>
            <input type="hidden" name="request_go_live" value="1">
            <button type="submit" class="btn btn-primary">Bevestigen &#8594;</button>
          </form>
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
