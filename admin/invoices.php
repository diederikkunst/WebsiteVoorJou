<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$filterStatus = $_GET['status'] ?? '';
$where  = [];
$params = [];
if ($filterStatus) {
    $where[]  = 'i.status = ?';
    $params[] = $filterStatus;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$invoices = $db->prepare("
    SELECT i.*, p.name AS project_name, c.name AS client_name, p.id AS project_id
    FROM invoices i
    JOIN projects p ON p.id = i.project_id
    JOIN clients c ON c.id = p.client_id
    $whereSQL
    ORDER BY i.created_at DESC
");
$invoices->execute($params);
$invoices = $invoices->fetchAll();

$totalOpen  = $db->query("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status != 'betaald'")->fetchColumn();
$totalPaid  = $db->query("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status = 'betaald'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facturen — WebsiteVoorJou Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>Facturen</h1>
      <p>Overzicht van alle facturen.</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= count($invoices) ?></div>
        <div class="stat-label">Totaal facturen</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">&euro;<?= number_format($totalOpen, 0, ',', '.') ?></div>
        <div class="stat-label">Openstaand (excl. BTW)</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">&euro;<?= number_format($totalPaid, 0, ',', '.') ?></div>
        <div class="stat-label">Betaald (excl. BTW)</div>
      </div>
    </div>

    <form method="get" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle statussen</option>
          <option value="concept"   <?= $filterStatus === 'concept'   ? 'selected' : '' ?>>Concept</option>
          <option value="verstuurd" <?= $filterStatus === 'verstuurd' ? 'selected' : '' ?>>Verstuurd</option>
          <option value="betaald"   <?= $filterStatus === 'betaald'   ? 'selected' : '' ?>>Betaald</option>
        </select>
      </div>
    </form>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($invoices) ?> facturen</h3></div>
      <?php if (empty($invoices)): ?>
        <p class="text-muted" style="padding:24px;">Geen facturen gevonden.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Nummer</th>
                <th>Project</th>
                <th>Klant</th>
                <th>Bedrag</th>
                <th>Incl. BTW</th>
                <th>Status</th>
                <th>Datum</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoices as $inv): ?>
              <tr>
                <td><code><?= htmlspecialchars($inv['invoice_number']) ?></code></td>
                <td><a href="/admin/project-detail.php?id=<?= $inv['project_id'] ?>"><?= htmlspecialchars($inv['project_name']) ?></a></td>
                <td><?= htmlspecialchars($inv['client_name']) ?></td>
                <td>&euro;<?= number_format($inv['amount'], 2, ',', '.') ?></td>
                <td>&euro;<?= number_format($inv['amount'] * 1.21, 2, ',', '.') ?></td>
                <td>
                  <?php
                    $statusColors = ['concept' => 'badge-new', 'verstuurd' => 'badge-preview', 'betaald' => 'badge-paid'];
                    $statusLabels = ['concept' => 'Concept', 'verstuurd' => 'Verstuurd', 'betaald' => 'Betaald'];
                  ?>
                  <span class="badge <?= $statusColors[$inv['status']] ?? '' ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span>
                </td>
                <td><?= formatDate($inv['created_at']) ?></td>
                <td><a href="/admin/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline">Open</a></td>
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
