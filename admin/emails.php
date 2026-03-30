<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$db = getDB();

$search      = trim($_GET['q'] ?? '');
$filterType  = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$where  = [];
$params = [];

if ($search) {
    $where[]  = '(to_email LIKE ? OR to_name LIKE ? OR subject LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterType) {
    $where[]  = 'type = ?';
    $params[] = $filterType;
}
if ($filterStatus) {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$emails = $db->prepare("SELECT * FROM email_logs $whereSQL ORDER BY sent_at DESC LIMIT 500");
$emails->execute($params);
$emails = $emails->fetchAll();

$totalSent   = $db->query("SELECT COUNT(*) FROM email_logs WHERE status = 'verstuurd'")->fetchColumn();
$totalFailed = $db->query("SELECT COUNT(*) FROM email_logs WHERE status = 'mislukt'")->fetchColumn();
$totalToday  = $db->query("SELECT COUNT(*) FROM email_logs WHERE DATE(sent_at) = CURDATE()")->fetchColumn();

$typeLabels = [
    'preview'           => 'Preview',
    'factuur'           => 'Factuur',
    'contactreactie'    => 'Contactreactie',
    'accountbevestiging'=> 'Accountbevestiging',
    'vraag_reactie'     => 'Vraag reactie',
    'download'          => 'Download',
    'overig'            => 'Overig',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-maillog — WebsiteVoorJou Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <h1>E-maillog</h1>
      <p>Overzicht van alle verstuurde e-mails.</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $totalSent ?></div>
        <div class="stat-label">Totaal verstuurd</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $totalToday ?></div>
        <div class="stat-label">Vandaag</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" style="color:<?= $totalFailed > 0 ? '#ef4444' : 'inherit' ?>"><?= $totalFailed ?></div>
        <div class="stat-label">Mislukt</div>
      </div>
    </div>

    <!-- Zoek & filter -->
    <form method="get" class="filter-bar" style="flex-wrap:wrap;gap:12px;">
      <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0;">
        <label class="form-label">Zoeken</label>
        <input type="text" name="q" class="form-control" placeholder="E-mailadres, naam of onderwerp..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Type</label>
        <select name="type" class="form-control" onchange="this.form.submit()">
          <option value="">Alle types</option>
          <?php foreach ($typeLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= $filterType === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle statussen</option>
          <option value="verstuurd" <?= $filterStatus === 'verstuurd' ? 'selected' : '' ?>>Verstuurd</option>
          <option value="mislukt"   <?= $filterStatus === 'mislukt'   ? 'selected' : '' ?>>Mislukt</option>
        </select>
      </div>
      <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;margin-bottom:0;">
        <button type="submit" class="btn btn-primary btn-sm">Zoeken</button>
        <?php if ($search || $filterType || $filterStatus): ?>
          <a href="/admin/emails.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= count($emails) ?> e-mail<?= count($emails) !== 1 ? 's' : '' ?></h3>
      </div>
      <?php if (empty($emails)): ?>
        <p class="text-muted" style="padding:24px;">Geen e-mails gevonden.</p>
      <?php else: ?>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Datum &amp; tijd</th>
                <th>Ontvanger</th>
                <th>Onderwerp</th>
                <th>Type</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($emails as $e): ?>
              <tr>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:0.85rem;">
                  <?= date('d-m-Y', strtotime($e['sent_at'])) ?><br>
                  <span style="font-size:0.8rem;"><?= date('H:i:s', strtotime($e['sent_at'])) ?></span>
                </td>
                <td>
                  <?php if ($e['to_name']): ?>
                    <strong><?= htmlspecialchars($e['to_name']) ?></strong><br>
                  <?php endif; ?>
                  <a href="mailto:<?= htmlspecialchars($e['to_email']) ?>" style="font-size:0.85rem;"><?= htmlspecialchars($e['to_email']) ?></a>
                </td>
                <td style="max-width:320px;">
                  <span style="font-size:0.9rem;"><?= htmlspecialchars($e['subject']) ?></span>
                </td>
                <td>
                  <?php
                    $typeColors = [
                        'preview'            => '#6C63FF',
                        'factuur'            => '#f59e0b',
                        'contactreactie'     => '#3b82f6',
                        'accountbevestiging' => '#8b5cf6',
                        'vraag_reactie'      => '#06b6d4',
                        'download'           => '#10b981',
                        'overig'             => '#9ca3af',
                    ];
                    $kleur = $typeColors[$e['type']] ?? '#9ca3af';
                    $label = $typeLabels[$e['type']] ?? ucfirst($e['type']);
                  ?>
                  <span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;background:<?= $kleur ?>22;color:<?= $kleur ?>;">
                    <?= $label ?>
                  </span>
                </td>
                <td>
                  <?php if ($e['status'] === 'verstuurd'): ?>
                    <span style="color:#00c853;font-weight:600;font-size:0.85rem;">&#10003; Verstuurd</span>
                  <?php else: ?>
                    <span style="color:#ef4444;font-weight:600;font-size:0.85rem;">&#10007; Mislukt</span>
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
