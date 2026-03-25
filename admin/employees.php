<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$user = currentUser();
$db   = getDB();

$success = $error = '';

// Create employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = $_POST['role'] ?? 'employee';
    if (!in_array($role, ['admin','employee'])) $role = 'employee';

    if ($name && $email && $pass) {
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'E-mailadres is al in gebruik.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')->execute([$name, $email, $hash, $role]);
            $success = 'Medewerker aangemaakt.';
        }
    } else {
        $error = 'Vul alle velden in.';
    }
}

// Toggle active
if (isset($_GET['toggle'])) {
    $empId = (int)$_GET['toggle'];
    if ($empId !== $user['id']) {
        $cur = $db->prepare('SELECT is_active FROM users WHERE id = ?');
        $cur->execute([$empId]);
        $curActive = (int)$cur->fetchColumn();
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$curActive ? 0 : 1, $empId]);
    }
    header('Location: /admin/employees.php');
    exit;
}

$employees = $db->query("SELECT u.*, (SELECT COUNT(DISTINCT pe.project_id) FROM project_employees pe WHERE pe.user_id = u.id) AS project_count FROM users u WHERE u.role IN ('admin','employee') ORDER BY u.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medewerkers — WebsiteVoorJou Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>Medewerkers</h1>
      <p>Beheer de teamleden die toegang hebben tot het admin-paneel.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="4000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid-2">
      <!-- Employee list -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Team (<?= count($employees) ?>)</h3></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Naam</th><th>E-mail</th><th>Rol</th><th>Projecten</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($employees as $e): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="sidebar-avatar" style="width:32px;height:32px;font-size:0.85rem;"><?= strtoupper(substr($e['name'],0,1)) ?></div>
                    <strong><?= htmlspecialchars($e['name']) ?></strong>
                  </div>
                </td>
                <td><?= htmlspecialchars($e['email']) ?></td>
                <td><span class="badge badge-<?= $e['role'] === 'admin' ? 'gold' : 'new' ?>"><?= ucfirst($e['role']) ?></span></td>
                <td><?= $e['project_count'] ?></td>
                <td>
                  <?php if ($e['is_active']): ?>
                    <span class="badge badge-done">Actief</span>
                  <?php else: ?>
                    <span class="badge badge-invoice">Inactief</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($e['id'] !== $user['id']): ?>
                    <a href="?toggle=<?= $e['id'] ?>" class="btn btn-sm btn-outline" data-confirm="Status wijzigen voor <?= htmlspecialchars($e['name']) ?>?"><?= $e['is_active'] ? 'Deactiveren' : 'Activeren' ?></a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add employee -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Medewerker toevoegen</h3></div>
        <form method="post">
          <input type="hidden" name="create_employee" value="1">
          <div class="form-group">
            <label class="form-label">Naam *</label>
            <input type="text" name="name" class="form-control" placeholder="Jan de Vries" required>
          </div>
          <div class="form-group">
            <label class="form-label">E-mailadres *</label>
            <input type="email" name="email" class="form-control" placeholder="jan@websitevoorjou.nl" required>
          </div>
          <div class="form-group">
            <label class="form-label">Wachtwoord *</label>
            <input type="password" name="password" class="form-control" placeholder="Minimaal 8 tekens" required>
          </div>
          <div class="form-group">
            <label class="form-label">Rol</label>
            <select name="role" class="form-control">
              <option value="employee">Medewerker</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Toevoegen</button>
        </form>
      </div>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
