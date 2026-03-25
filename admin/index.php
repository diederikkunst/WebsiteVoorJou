<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$totalProjects  = $db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$totalClients   = $db->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalLeads     = $db->query("SELECT COUNT(*) FROM clients WHERE type='lead'")->fetchColumn();
$openInvoices   = $db->query("SELECT COUNT(*) FROM invoices WHERE status != 'betaald'")->fetchColumn();
$totalRevenue   = $db->query("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status='betaald'")->fetchColumn();
$newContacts    = $db->query("SELECT COUNT(*) FROM contact_requests WHERE is_read=0")->fetchColumn();

// Recent projects (oldest first, first 10 not yet done)
$recentProjects = $db->query("
    SELECT p.*, c.name AS client_name
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    WHERE p.status NOT IN ('factuur_betaald')
    ORDER BY p.created_at ASC
    LIMIT 10
")->fetchAll();

// New contact requests
$contacts = $db->query("SELECT * FROM contact_requests WHERE is_read=0 ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>Dashboard</h1>
      <p>Overzicht van alle activiteiten en openstaande projecten.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $totalProjects ?></div>
        <div class="stat-label">Totaal projecten</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalClients ?></div>
        <div class="stat-label">Klanten</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalLeads ?></div>
        <div class="stat-label">Leads</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $openInvoices ?></div>
        <div class="stat-label">Open facturen</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">&euro;<?= number_format($totalRevenue, 0, ',', '.') ?></div>
        <div class="stat-label">Omzet betaald</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $newContacts ?></div>
        <div class="stat-label">Nieuwe aanvragen</div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Active projects -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Openstaande projecten</h3>
          <a href="/admin/projects.php" class="btn btn-sm btn-outline">Alle projecten</a>
        </div>
        <?php if (empty($recentProjects)): ?>
          <p class="text-muted">Geen openstaande projecten.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Project</th><th>Klant</th><th>Status</th><th>Datum</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($recentProjects as $p): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                  <td><?= htmlspecialchars($p['client_name']) ?></td>
                  <td><?= statusLabel($p['status']) ?></td>
                  <td><?= formatDate($p['created_at']) ?></td>
                  <td><a href="/admin/project-detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Open</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- New contact requests -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Nieuwe aanvragen</h3>
          <a href="/admin/contacts.php" class="btn btn-sm btn-outline">Alle aanvragen</a>
        </div>
        <?php if (empty($contacts)): ?>
          <p class="text-muted">Geen nieuwe aanvragen.</p>
        <?php else: ?>
          <?php foreach ($contacts as $c): ?>
            <div style="padding:12px 0;border-bottom:1px solid var(--border);">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div>
                  <strong><?= htmlspecialchars($c['name']) ?></strong>
                  <?php if ($c['company']): ?>
                    <span class="text-muted"> — <?= htmlspecialchars($c['company']) ?></span>
                  <?php endif; ?>
                  <div style="font-size:0.85rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($c['email']) ?></div>
                  <p style="font-size:0.9rem;margin-top:4px;"><?= htmlspecialchars(substr($c['message'], 0, 100)) ?>...</p>
                </div>
                <span style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= formatDate($c['created_at']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
