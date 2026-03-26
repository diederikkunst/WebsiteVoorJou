<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

$projects = [];
if ($client) {
    $stmt = $db->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC');
    $stmt->execute([$client['id']]);
    $projects = $stmt->fetchAll();
}

$statuses = statusOptions();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mijn Dashboard — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand">WebsiteVoorJou</div>
    <ul class="sidebar-nav">
      <li><a href="/portal/dashboard.php" class="active"><span class="nav-icon">&#127968;</span> Dashboard</a></li>
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

  <!-- Main -->
  <main class="main-content">
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <h1>Welkom, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
          <p>Hier vind je al jouw projecten en kun je nieuwe aanvragen indienen.</p>
        </div>
        <a href="/portal/new-project.php" class="btn btn-primary">+ Nieuw project</a>
      </div>
    </div>

    <?php if (!$client): ?>
      <div class="alert alert-info">
        &#8505; Je account is aangemaakt maar nog niet gekoppeld aan een klantprofiel. Neem contact op via <a href="mailto:info@websitevoorjou.nl">info@websitevoorjou.nl</a>.
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= count($projects) ?></div>
        <div class="stat-label">Totaal projecten</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['status'] === 'nieuw')) ?></div>
        <div class="stat-label">Nieuw</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['status'] === 'preview_beschikbaar')) ?></div>
        <div class="stat-label">Previews beschikbaar</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($projects, fn($p) => $p['status'] === 'afgerond')) ?></div>
        <div class="stat-label">Afgerond</div>
      </div>
    </div>

    <!-- Projects -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Mijn projecten</h3>
      </div>
      <?php if (empty($projects)): ?>
        <div style="text-align:center;padding:48px 24px;">
          <div style="font-size:3rem;margin-bottom:16px;">&#128640;</div>
          <h3>Nog geen projecten</h3>
          <p style="margin-bottom:24px;">Maak je eerste project aan en we gaan aan de slag!</p>
          <a href="/portal/new-project.php" class="btn btn-primary">Nieuw project aanmaken</a>
        </div>
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
              <tr>
                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                <td><?= packageLabel($p['package']) ?></td>
                <td><?= statusLabel($p['status']) ?></td>
                <td><?= formatDate($p['created_at']) ?></td>
                <td>
                  <?php
                    $stmt2 = $db->prepare('SELECT token FROM preview_tokens WHERE project_id = ? AND expires_at > NOW() LIMIT 1');
                    $stmt2->execute([$p['id']]);
                    $tokenRow = $stmt2->fetch();
                  ?>
                  <?php if ($tokenRow): ?>
                    <a href="/preview.php?token=<?= htmlspecialchars($tokenRow['token']) ?>" target="_blank" class="btn btn-sm btn-outline">&#128065; Bekijk preview</a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                  <a href="/portal/project.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Details</a>
                  <?php if (in_array($p['status'], ['factuur_gestuurd','factuur_betaald'])): ?>
                    <?php
                      $invStmt = $db->prepare('SELECT id FROM invoices WHERE project_id = ? LIMIT 1');
                      $invStmt->execute([$p['id']]);
                      $inv = $invStmt->fetch();
                    ?>
                    <?php if ($inv): ?>
                      <a href="/portal/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary">&#128424; Factuur</a>
                    <?php endif; ?>
                  <?php endif; ?>
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
