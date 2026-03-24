<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$filterStatus   = $_GET['status'] ?? '';
$filterEmployee = (int)($_GET['employee'] ?? 0);

$where  = [];
$params = [];

if ($filterStatus) {
    $where[]  = 'p.status = ?';
    $params[] = $filterStatus;
}

if ($filterEmployee) {
    $where[]  = 'EXISTS (SELECT 1 FROM project_employees pe WHERE pe.project_id = p.id AND pe.user_id = ?)';
    $params[] = $filterEmployee;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$projects = $db->prepare("
    SELECT p.*, c.name AS client_name, c.type AS client_type
    FROM projects p
    JOIN clients c ON c.id = p.client_id
    $whereSQL
    ORDER BY p.created_at ASC
");
$projects->execute($params);
$projects = $projects->fetchAll();

$employees = $db->query("SELECT id, name FROM users WHERE role IN ('admin','employee') ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projecten — WebSiteVoorJou Admin</title>
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
          <h1>Projecten</h1>
          <p>Alle projecten, oudste aanvragen bovenaan.</p>
        </div>
        <a href="/admin/new-project.php" class="btn btn-primary">+ Nieuw project</a>
      </div>
    </div>

    <!-- Filters -->
    <form method="get" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle statussen</option>
          <?php foreach (statusOptions() as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Medewerker</label>
        <select name="employee" class="form-control" onchange="this.form.submit()">
          <option value="">Alle medewerkers</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $filterEmployee === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($filterStatus || $filterEmployee): ?>
        <div class="form-group" style="display:flex;align-items:flex-end;">
          <a href="/admin/projects.php" class="btn btn-outline">Reset</a>
        </div>
      <?php endif; ?>
    </form>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= count($projects) ?> project(en)</h3>
      </div>
      <?php if (empty($projects)): ?>
        <p class="text-muted" style="padding:24px;">Geen projecten gevonden.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Project</th>
                <th>Klant</th>
                <th>Pakket</th>
                <th>Status</th>
                <th>Aangemaakt</th>
                <th>Medewerkers</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $i => $p): ?>
              <?php
                $empStmt = $db->prepare('SELECT u.name FROM project_employees pe JOIN users u ON u.id = pe.user_id WHERE pe.project_id = ?');
                $empStmt->execute([$p['id']]);
                $emps = $empStmt->fetchAll(PDO::FETCH_COLUMN);
              ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                <td>
                  <a href="/admin/client-detail.php?id=<?= $p['client_id'] ?>"><?= htmlspecialchars($p['client_name']) ?></a>
                  <span class="badge badge-<?= $p['client_type'] ?>"><?= $p['client_type'] ?></span>
                </td>
                <td><?= packageLabel($p['package']) ?></td>
                <td><?= statusLabel($p['status']) ?></td>
                <td><?= formatDate($p['created_at']) ?></td>
                <td>
                  <?php if ($emps): ?>
                    <?= htmlspecialchars(implode(', ', $emps)) ?>
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
