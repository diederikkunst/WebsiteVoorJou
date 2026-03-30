<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$filterType     = $_GET['type'] ?? '';
$filterVerified = $_GET['verified'] ?? '';

$where  = [];
$params = [];

if ($filterType) {
    $where[]  = 'c.type = ?';
    $params[] = $filterType;
}
if ($filterVerified === 'no') {
    $where[] = 'u.id IS NOT NULL AND u.email_verified = 0';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT c.*, u.email AS user_email, u.is_active, u.email_verified,
    (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS project_count
    FROM clients c
    LEFT JOIN users u ON u.id = c.user_id
    $whereSQL
    ORDER BY c.created_at DESC");
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Klanten — WebsiteVoorJou Admin</title>
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
          <h1>Klanten &amp; Leads</h1>
          <p>Beheer al je klanten en leads op één plek.</p>
        </div>
        <a href="/admin/new-client.php" class="btn btn-primary">+ Nieuwe klant / lead</a>
      </div>
    </div>

    <!-- Filter -->
    <form method="get" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type" class="form-control" onchange="this.form.submit()">
          <option value="">Alle types</option>
          <option value="lead"   <?= $filterType === 'lead'   ? 'selected' : '' ?>>Lead</option>
          <option value="client" <?= $filterType === 'client' ? 'selected' : '' ?>>Klant</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Account status</label>
        <select name="verified" class="form-control" onchange="this.form.submit()">
          <option value="">Alle accounts</option>
          <option value="no" <?= $filterVerified === 'no' ? 'selected' : '' ?>>Nog niet bevestigd</option>
        </select>
      </div>
      <?php if ($filterType || $filterVerified): ?>
        <div class="form-group" style="display:flex;align-items:flex-end;">
          <a href="/admin/clients.php" class="btn btn-outline">Reset</a>
        </div>
      <?php endif; ?>
    </form>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= count($clients) ?> klant(en)</h3>
      </div>
      <?php if (empty($clients)): ?>
        <p class="text-muted" style="padding:24px;">Geen klanten gevonden.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Logo</th>
                <th>Naam</th>
                <th>Type</th>
                <th>Account</th>
                <th>E-mail</th>
                <th>Telefoon</th>
                <th>Website</th>
                <th>Projecten</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($clients as $c): ?>
              <tr>
                <td>
                  <?php if ($c['logo']): ?>
                    <img src="/uploads/<?= htmlspecialchars($c['logo']) ?>" style="width:36px;height:36px;object-fit:contain;border-radius:6px;background:var(--bg);" alt="">
                  <?php else: ?>
                    <div style="width:36px;height:36px;background:var(--bg-2);border-radius:6px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:700;"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                  <?php endif; ?>
                </td>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><span class="badge badge-<?= $c['type'] ?>"><?= $c['type'] === 'lead' ? 'Lead' : 'Klant' ?></span></td>
                <td>
                  <?php if (!$c['user_id']): ?>
                    <span style="font-size:0.78rem;color:var(--text-muted);">Geen account</span>
                  <?php elseif ($c['email_verified']): ?>
                    <span style="font-size:0.78rem;color:#00c853;font-weight:600;">&#10003; Bevestigd</span>
                  <?php else: ?>
                    <span style="font-size:0.78rem;color:#f59e0b;font-weight:600;">&#9679; Wacht op bevestiging</span>
                  <?php endif; ?>
                </td>
                <td><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email'] ?: '—') ?></a></td>
                <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                <td><?= $c['website'] ? '<a href="' . htmlspecialchars($c['website']) . '" target="_blank">&#128279;</a>' : '—' ?></td>
                <td><?= $c['project_count'] ?></td>
                <td>
                  <a href="/admin/client-detail.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Open</a>
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
