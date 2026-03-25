<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

// Mark as read
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $db->prepare('UPDATE contact_requests SET is_read = 1 WHERE id = ?')->execute([$id]);
    header('Location: /admin/contacts.php');
    exit;
}

if (isset($_GET['mark_all_read'])) {
    $db->query('UPDATE contact_requests SET is_read = 1');
    header('Location: /admin/contacts.php');
    exit;
}

$filterRead = $_GET['read'] ?? '';
$where      = [];
$params     = [];

if ($filterRead === '0') { $where[] = 'is_read = 0'; }
if ($filterRead === '1') { $where[] = 'is_read = 1'; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$contacts = $db->query("SELECT * FROM contact_requests $whereSQL ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aanvragen — WebsiteVoorJou Admin</title>
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
          <h1>Contactaanvragen</h1>
          <p>Aanvragen via het contactformulier op de website.</p>
        </div>
        <a href="?mark_all_read=1" class="btn btn-outline btn-sm" data-confirm="Alle aanvragen als gelezen markeren?">Alles als gelezen</a>
      </div>
    </div>

    <!-- Filter -->
    <form method="get" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Filter</label>
        <select name="read" class="form-control" onchange="this.form.submit()">
          <option value="">Alle aanvragen</option>
          <option value="0" <?= $filterRead === '0' ? 'selected' : '' ?>>Ongelezen</option>
          <option value="1" <?= $filterRead === '1' ? 'selected' : '' ?>>Gelezen</option>
        </select>
      </div>
    </form>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($contacts) ?> aanvraag/aanvragen</h3></div>
      <?php if (empty($contacts)): ?>
        <p class="text-muted" style="padding:24px;">Geen aanvragen gevonden.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0;">
          <?php foreach ($contacts as $c): ?>
            <div style="padding:20px;border-bottom:1px solid var(--border);<?= !$c['is_read'] ? 'background:rgba(108,99,255,0.04);' : '' ?>">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                    <strong style="font-size:1rem;"><?= htmlspecialchars($c['name']) ?></strong>
                    <?php if ($c['company']): ?>
                      <span class="text-muted">— <?= htmlspecialchars($c['company']) ?></span>
                    <?php endif; ?>
                    <?php if (!$c['is_read']): ?>
                      <span class="badge badge-new">Nieuw</span>
                    <?php endif; ?>
                  </div>
                  <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;">
                    <span><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></span>
                    <?php if ($c['phone']): ?>
                      <span><a href="tel:<?= htmlspecialchars($c['phone']) ?>"><?= htmlspecialchars($c['phone']) ?></a></span>
                    <?php endif; ?>
                    <span><?= formatDateTime($c['created_at']) ?></span>
                  </div>
                  <p style="font-size:0.9rem;white-space:pre-wrap;"><?= htmlspecialchars($c['message']) ?></p>
                  <?php if ($c['current_website']): ?>
                    <p style="font-size:0.85rem;margin-top:6px;">&#127760; Huidige website: <a href="<?= htmlspecialchars($c['current_website']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($c['current_website']) ?></a></p>
                  <?php endif; ?>
                  <?php if ($c['logo']): ?>
                    <div style="margin-top:8px;"><img src="/uploads/<?= htmlspecialchars($c['logo']) ?>" alt="Logo" style="max-height:60px;max-width:180px;border-radius:4px;border:1px solid var(--border);padding:4px;background:#fff;"></div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;">
                  <?php if (!$c['is_read']): ?>
                    <a href="?mark_read=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Als gelezen markeren</a>
                  <?php endif; ?>
                  <a href="/admin/new-client.php?from_contact=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Lead aanmaken</a>
                  <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="btn btn-sm btn-outline">&#128231; Reageren</a>
                </div>
              </div>
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
