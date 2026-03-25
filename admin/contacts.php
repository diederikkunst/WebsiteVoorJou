<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$success = '';
$error   = '';

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

// Stuur reactie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $id      = (int)$_POST['contact_id'];
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['reply_body'] ?? '');

    $stmt = $db->prepare('SELECT * FROM contact_requests WHERE id = ?');
    $stmt->execute([$id]);
    $contact = $stmt->fetch();

    if ($contact && $subject && $body) {
        $html = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
              . '<p>Beste ' . htmlspecialchars($contact['name']) . ',</p>'
              . '<p style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($body)) . '</p>'
              . '<p style="margin-top:32px;color:#888;font-size:0.85rem;">Met vriendelijke groet,<br>Het team van WebsiteVoorJou</p>'
              . '</div>';

        if (sendMail($contact['email'], $subject, $html, $contact['name'])) {
            $db->prepare('UPDATE contact_requests SET status = \'reactie_gestuurd\', is_read = 1, replied_at = NOW() WHERE id = ?')
               ->execute([$id]);
            $success = 'Reactie verstuurd naar ' . $contact['email'] . '.';
        } else {
            $error = 'Versturen mislukt. Controleer de mailconfiguratie.';
        }
    }
}

$filterRead   = $_GET['read'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$where        = [];
$params       = [];

if ($filterRead === '0') { $where[] = 'is_read = 0'; }
if ($filterRead === '1') { $where[] = 'is_read = 1'; }
if ($filterStatus === 'reactie_gestuurd') { $where[] = "status = 'reactie_gestuurd'"; }
if ($filterStatus === 'nieuw')            { $where[] = "status = 'nieuw'"; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$contacts = $db->query("SELECT * FROM contact_requests $whereSQL ORDER BY created_at DESC")->fetchAll();

// Bouw een map van email → client zodat we dubbelen kunnen herkennen
$existingEmails = [];
if ($contacts) {
    $emails = array_unique(array_filter(array_column($contacts, 'email')));
    if ($emails) {
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $stmt = $db->prepare("SELECT id, name, email FROM clients WHERE email IN ($placeholders)");
        $stmt->execute(array_values($emails));
        foreach ($stmt->fetchAll() as $row) {
            $existingEmails[strtolower($row['email'])] = $row;
        }
    }
}
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

    <?php if ($success): ?><div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filter -->
    <form method="get" class="filter-bar" style="display:flex;gap:16px;flex-wrap:wrap;">
      <div class="form-group">
        <label class="form-label">Gelezen</label>
        <select name="read" class="form-control" onchange="this.form.submit()">
          <option value="">Alle</option>
          <option value="0" <?= $filterRead === '0' ? 'selected' : '' ?>>Ongelezen</option>
          <option value="1" <?= $filterRead === '1' ? 'selected' : '' ?>>Gelezen</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle statussen</option>
          <option value="nieuw"            <?= $filterStatus === 'nieuw'            ? 'selected' : '' ?>>Nieuw</option>
          <option value="reactie_gestuurd" <?= $filterStatus === 'reactie_gestuurd' ? 'selected' : '' ?>>Reactie gestuurd</option>
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
                    <?php if (($c['status'] ?? '') === 'reactie_gestuurd'): ?>
                      <span class="badge badge-done">Reactie gestuurd</span>
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
                  <?php $existing = $existingEmails[strtolower($c['email'])] ?? null; ?>
                  <?php if ($existing): ?>
                    <a href="javascript:void(0)"
                       onclick="if(confirm('<?= htmlspecialchars(addslashes($existing['name'])) ?> bestaat al als lead/klant.\nWil je een nieuw project aanmaken voor deze lead?'))window.location.href='/admin/new-project.php?client_id=<?= $existing['id'] ?>&from_contact=<?= $c['id'] ?>'"
                       class="btn btn-sm btn-outline" title="Bestaat al: <?= htmlspecialchars($existing['name']) ?>">
                      Bestaande lead &#8594; project
                    </a>
                  <?php else: ?>
                    <a href="/admin/new-client.php?from_contact=<?= $c['id'] ?>" class="btn btn-sm btn-primary">Lead aanmaken</a>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline" onclick="toggleReply(<?= $c['id'] ?>)">&#128231; Reageren</button>
                </div>
              </div>

              <!-- Reactieformulier -->
              <div id="reply-<?= $c['id'] ?>" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <form method="post">
                  <input type="hidden" name="send_reply" value="1">
                  <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
                  <div class="form-group">
                    <label class="form-label">Onderwerp</label>
                    <input type="text" name="subject" class="form-control"
                      value="Re: Uw aanvraag via WebsiteVoorJou.nl">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Bericht</label>
                    <textarea name="reply_body" class="form-control" rows="7">Beste <?= htmlspecialchars($c['name']) ?>,

Bedankt voor uw aanvraag! We hebben uw bericht ontvangen en nemen zo snel mogelijk contact met u op.

Heeft u vragen? Dan kunt u altijd contact opnemen via dit e-mailadres.

Met vriendelijke groet,
Het team van WebsiteVoorJou</textarea>
                  </div>
                  <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">&#9993; Verstuur reactie</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleReply(<?= $c['id'] ?>)">Annuleren</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
<script>
function toggleReply(id) {
  var el = document.getElementById('reply-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
