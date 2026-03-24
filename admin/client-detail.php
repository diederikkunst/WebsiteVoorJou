<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$clientId = (int)($_GET['id'] ?? 0);
if (!$clientId) { header('Location: /admin/clients.php'); exit; }

$stmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$clientId]);
$client = $stmt->fetch();
if (!$client) { header('Location: /admin/clients.php'); exit; }

$success = $error = '';
$isNew   = isset($_GET['new']);

// Update client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client'])) {
    $name    = trim($_POST['name'] ?? '');
    $type    = $_POST['type'] ?? 'lead';
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $bank    = trim($_POST['bank_account'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');
    if (!in_array($type, ['lead','client'])) $type = 'lead';

    $logo = $client['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $newLogo = saveUpload($_FILES['logo'], 'logos');
        if ($newLogo) $logo = $newLogo;
    }

    if ($name) {
        $db->prepare('UPDATE clients SET name=?, type=?, address=?, email=?, phone=?, website=?, bank_account=?, logo=?, notes=? WHERE id=?')
           ->execute([$name, $type, $address, $email, $phone, $website, $bank, $logo, $notes, $clientId]);
        $success = 'Klant bijgewerkt.';
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
    } else {
        $error = 'Naam is verplicht.';
    }
}

// Projects for this client
$projects = $db->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at ASC');
$projects->execute([$clientId]);
$projects = $projects->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($client['name']) ?> — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <?php if ($isNew): ?>
      <div class="alert alert-success" data-dismiss="5000">&#10003; Klant aangemaakt!</div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="4000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-row">
        <div style="display:flex;align-items:center;gap:16px;">
          <?php if ($client['logo']): ?>
            <img src="/uploads/<?= htmlspecialchars($client['logo']) ?>" style="width:60px;height:60px;object-fit:contain;border-radius:10px;background:var(--bg-card);border:1px solid var(--border);padding:4px;">
          <?php else: ?>
            <div style="width:60px;height:60px;background:var(--bg-card);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;"><?= strtoupper(substr($client['name'],0,1)) ?></div>
          <?php endif; ?>
          <div>
            <a href="/admin/clients.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Klanten</a>
            <h1 style="margin-top:2px;"><?= htmlspecialchars($client['name']) ?></h1>
            <span class="badge badge-<?= $client['type'] ?>"><?= $client['type'] === 'lead' ? 'Lead' : 'Klant' ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Edit form -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Klantgegevens</h3></div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="update_client" value="1">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Type</label>
              <select name="type" class="form-control">
                <option value="lead"   <?= $client['type'] === 'lead'   ? 'selected' : '' ?>>Lead</option>
                <option value="client" <?= $client['type'] === 'client' ? 'selected' : '' ?>>Klant</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Naam *</label>
              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">E-mailadres</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Telefoon</label>
              <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Adres</label>
            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Website</label>
              <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($client['website'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">IBAN</label>
              <input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($client['bank_account'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Logo</label>
            <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
          </div>
          <div class="form-group">
            <label class="form-label">Notities</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Opslaan</button>
        </form>
      </div>

      <!-- Quick info -->
      <div>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><h3 class="card-title">Snel contact</h3></div>
          <?php if ($client['email']): ?>
            <div style="margin-bottom:10px;">
              <div class="form-label">E-mail</div>
              <a href="mailto:<?= htmlspecialchars($client['email']) ?>"><?= htmlspecialchars($client['email']) ?></a>
            </div>
          <?php endif; ?>
          <?php if ($client['phone']): ?>
            <div style="margin-bottom:10px;">
              <div class="form-label">Telefoon</div>
              <a href="tel:<?= htmlspecialchars($client['phone']) ?>"><?= htmlspecialchars($client['phone']) ?></a>
            </div>
          <?php endif; ?>
          <?php if ($client['website']): ?>
            <div>
              <div class="form-label">Website</div>
              <a href="<?= htmlspecialchars($client['website']) ?>" target="_blank"><?= htmlspecialchars($client['website']) ?></a>
            </div>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="card-title">Statistieken</h3></div>
          <div style="display:flex;gap:16px;">
            <div class="stat-card flex-1" style="padding:12px;">
              <div class="stat-value" style="font-size:1.5rem;"><?= count($projects) ?></div>
              <div class="stat-label">Projecten</div>
            </div>
            <div class="stat-card flex-1" style="padding:12px;">
              <div class="stat-value" style="font-size:1.5rem;"><?= count(array_filter($projects, fn($p) => $p['status'] === 'factuur_betaald')) ?></div>
              <div class="stat-label">Afgerond</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Projects -->
    <div class="card" style="margin-top:24px;">
      <div class="card-header">
        <h3 class="card-title">Projecten</h3>
        <a href="/admin/new-project.php?client_id=<?= $clientId ?>" class="btn btn-primary btn-sm">+ Nieuw project</a>
      </div>
      <?php if (empty($projects)): ?>
        <p class="text-muted" style="padding:16px 0;">Nog geen projecten voor deze klant.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Project</th>
                <th>Pakket</th>
                <th>Status</th>
                <th>Aangemaakt</th>
                <th>Preview</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $p): ?>
              <?php
                $tknStmt = $db->prepare('SELECT token FROM preview_tokens WHERE project_id = ? AND expires_at > NOW() LIMIT 1');
                $tknStmt->execute([$p['id']]);
                $tkn = $tknStmt->fetch();
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                <td><?= packageLabel($p['package']) ?></td>
                <td><?= statusLabel($p['status']) ?></td>
                <td><?= formatDate($p['created_at']) ?></td>
                <td>
                  <?php if ($p['preview_url'] && $tkn): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                      <a href="/preview.php?token=<?= htmlspecialchars($tkn['token']) ?>" target="_blank" class="btn btn-sm btn-outline">&#128065; Bekijk</a>
                      <a href="/admin/send-preview.php?project_id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">&#128231; Mail preview</a>
                    </div>
                  <?php elseif ($p['status'] === 'preview_beschikbaar'): ?>
                    <a href="/admin/project-detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Preview instellen</a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="/admin/project-detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Open</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
