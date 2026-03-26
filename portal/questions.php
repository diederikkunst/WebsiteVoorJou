<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

if (!$client) {
    header('Location: /portal/dashboard.php'); exit;
}

$success = '';
$error   = '';

// Nieuwe vraag stellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ask_question'])) {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $subject   = trim($_POST['subject'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    // Valideer dat project van deze klant is
    $pCheck = $db->prepare('SELECT id FROM projects WHERE id = ? AND client_id = ?');
    $pCheck->execute([$projectId, $client['id']]);

    if ($projectId && $subject && $message && $pCheck->fetch()) {
        $db->prepare('INSERT INTO questions (project_id, client_id, subject, message) VALUES (?, ?, ?, ?)')
           ->execute([$projectId, $client['id'], $subject, $message]);
        $success = 'Je vraag is verstuurd. We reageren zo snel mogelijk.';
    } else {
        $error = 'Vul alle velden in en selecteer een geldig project.';
    }
}

// Laad projecten van deze klant
$projects = $db->prepare('SELECT * FROM projects WHERE client_id = ? ORDER BY name ASC');
$projects->execute([$client['id']]);
$projects = $projects->fetchAll();

// Laad vragen van deze klant (nieuwste bovenaan)
$filterStatus = $_GET['status'] ?? '';
$validStatuses = ['nieuw','in_behandeling','wacht_op_reactie','afgerond'];
$extraWhere = in_array($filterStatus, $validStatuses) ? "AND q.status = " . $db->quote($filterStatus) : '';

$questions = $db->query("
    SELECT q.*, p.name AS project_name
    FROM questions q
    JOIN projects p ON p.id = q.project_id
    WHERE q.client_id = {$client['id']} $extraWhere
    ORDER BY q.updated_at DESC
")->fetchAll();

// Laad replies per vraag
$allReplies = [];
if ($questions) {
    $ids = implode(',', array_column($questions, 'id'));
    $rStmt = $db->query("
        SELECT r.*, u.name AS replier_name, u.role AS replier_role
        FROM question_replies r
        JOIN users u ON u.id = r.user_id
        WHERE r.question_id IN ($ids)
        ORDER BY r.created_at ASC
    ");
    foreach ($rStmt->fetchAll() as $r) {
        $allReplies[$r['question_id']][] = $r;
    }
}

function questionStatusLabel(string $status): string {
    $map = [
        'nieuw'             => ['label' => 'Nieuw',              'color' => '#6C63FF'],
        'in_behandeling'    => ['label' => 'In behandeling',     'color' => '#00D4FF'],
        'wacht_op_reactie'  => ['label' => 'Wacht op jouw reactie', 'color' => '#FFB300'],
        'afgerond'          => ['label' => 'Afgerond',           'color' => '#00C853'],
    ];
    $s = $map[$status] ?? ['label' => $status, 'color' => '#999'];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;background:' . $s['color'] . '22;color:' . $s['color'] . ';">' . $s['label'] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mijn vragen — WebsiteVoorJou</title>
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
      <li><a href="/portal/questions.php" class="active"><span class="nav-icon">&#10067;</span> Mijn vragen</a></li>
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
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <h1>Mijn vragen</h1>
          <p>Stel een vraag over een van je projecten.</p>
        </div>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Nieuwe vraag -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">Nieuwe vraag stellen</h3></div>
      <?php if (empty($projects)): ?>
        <p class="text-muted" style="padding:20px;">Je hebt nog geen projecten. <a href="/portal/new-project.php">Maak een project aan</a> om een vraag te stellen.</p>
      <?php else: ?>
      <form method="post">
        <input type="hidden" name="ask_question" value="1">
        <div class="form-group">
          <label class="form-label">Project *</label>
          <select name="project_id" class="form-control" required>
            <option value="">— Selecteer een project —</option>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id'] ?>" <?= isset($_POST['project_id']) && (int)$_POST['project_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Onderwerp *</label>
          <input type="text" name="subject" class="form-control" placeholder="Waar gaat je vraag over?"
            value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Vraag *</label>
          <textarea name="message" class="form-control" rows="4" placeholder="Stel hier je vraag..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Verstuur vraag &#8594;</button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Filter -->
    <form method="get" style="margin-bottom:16px;">
      <div class="form-group" style="max-width:240px;margin-bottom:0;">
        <label class="form-label">Filter op status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle vragen</option>
          <option value="nieuw"            <?= $filterStatus === 'nieuw'            ? 'selected' : '' ?>>Nieuw</option>
          <option value="in_behandeling"   <?= $filterStatus === 'in_behandeling'   ? 'selected' : '' ?>>In behandeling</option>
          <option value="wacht_op_reactie" <?= $filterStatus === 'wacht_op_reactie' ? 'selected' : '' ?>>Wacht op reactie</option>
          <option value="afgerond"         <?= $filterStatus === 'afgerond'         ? 'selected' : '' ?>>Afgerond</option>
        </select>
      </div>
    </form>

    <!-- Overzicht vragen -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Mijn vragen (<?= count($questions) ?>)</h3></div>
      <?php if (empty($questions)): ?>
        <p class="text-muted" style="padding:20px;">Je hebt nog geen vragen gesteld.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;">
          <?php foreach ($questions as $q): ?>
            <div style="padding:20px;border-bottom:1px solid var(--border);">

              <!-- Vraag header -->
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                <strong><?= htmlspecialchars($q['subject']) ?></strong>
                <?= questionStatusLabel($q['status']) ?>
              </div>
              <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;">
                Project: <strong><?= htmlspecialchars($q['project_name']) ?></strong>
                &bull; <?= formatDateTime($q['created_at']) ?>
              </p>

              <!-- Vraag -->
              <p style="font-size:0.9rem;white-space:pre-wrap;"><?= htmlspecialchars($q['message']) ?></p>

              <!-- Reacties -->
              <?php if (!empty($allReplies[$q['id']])): ?>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                  <?php foreach ($allReplies[$q['id']] ?? [] as $r): ?>
                    <div style="background:var(--bg-2);border-radius:8px;padding:10px 14px;border-left:3px solid var(--primary);">
                      <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:4px;">
                        <strong><?= htmlspecialchars($r['replier_name']) ?></strong> &bull; <?= formatDateTime($r['created_at']) ?>
                      </div>
                      <p style="font-size:0.9rem;white-space:pre-wrap;margin:0;"><?= htmlspecialchars($r['message']) ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
