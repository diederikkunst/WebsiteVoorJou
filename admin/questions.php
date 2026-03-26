<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$success = '';
$error   = '';

// Alleen status wijzigen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $status     = $_POST['status'] ?? '';
    $validStatuses = ['nieuw','in_behandeling','wacht_op_reactie','afgerond'];
    if ($questionId && in_array($status, $validStatuses)) {
        $db->prepare('UPDATE questions SET status = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$status, $questionId]);
        $success = 'Status bijgewerkt.';
    } else {
        $error = 'Ongeldige status.';
    }
}

// Reageer op vraag + update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_question'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    $message    = trim($_POST['message'] ?? '');
    $status     = $_POST['status'] ?? '';

    $validStatuses = ['nieuw','in_behandeling','wacht_op_reactie','afgerond'];
    if (!in_array($status, $validStatuses)) $status = 'in_behandeling';

    if ($questionId && $message) {
        try {
            // Sla reactie op
            $db->prepare('INSERT INTO question_replies (question_id, user_id, message) VALUES (?, ?, ?)')
               ->execute([$questionId, $user['id'], $message]);

            // Update status
            $db->prepare('UPDATE questions SET status = ?, updated_at = NOW() WHERE id = ?')
               ->execute([$status, $questionId]);

            // Mail de klant
            $qStmt = $db->prepare('
                SELECT q.*, p.name AS project_name, u.email AS client_email, u.name AS client_name
                FROM questions q
                JOIN projects p ON p.id = q.project_id
                JOIN clients c ON c.id = q.client_id
                JOIN users u ON u.id = c.user_id
                WHERE q.id = ?
            ');
            $qStmt->execute([$questionId]);
            $q = $qStmt->fetch();

            if ($q) {
                $html = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
                      . '<p>Beste ' . htmlspecialchars($q['client_name']) . ',</p>'
                      . '<p>Er is een reactie geplaatst op jouw vraag <strong>"' . htmlspecialchars($q['subject']) . '"</strong>:</p>'
                      . '<blockquote style="border-left:3px solid #6C63FF;margin:16px 0;padding:12px 16px;background:#f9f9ff;">'
                      . nl2br(htmlspecialchars($message))
                      . '</blockquote>'
                      . '<p><a href="' . APP_URL . '/portal/questions.php" style="color:#6C63FF;">Bekijk je vragen in het portaal</a></p>'
                      . '<p style="margin-top:32px;color:#888;font-size:0.85rem;">Met vriendelijke groet,<br>Het WebsiteVoorJou team</p>'
                      . '</div>';
                sendMail($q['client_email'], 'Reactie op je vraag — ' . $q['subject'], $html, $q['client_name']);
            }

            $success = 'Reactie verstuurd en status bijgewerkt.';
        } catch (\Throwable $e) {
            $error = 'Fout: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = 'Vul een reactie in.';
    }
}

$filterStatus = $_GET['status'] ?? '';
$validStatuses = ['nieuw','in_behandeling','wacht_op_reactie','afgerond'];
$where  = [];
$params = [];
if (in_array($filterStatus, $validStatuses)) {
    $where[]  = 'q.status = ?';
    $params[] = $filterStatus;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$questions = $db->prepare("
    SELECT q.*, p.name AS project_name, p.id AS project_id_real,
           c.name AS client_name, u.name AS user_name
    FROM questions q
    JOIN projects p ON p.id = q.project_id
    JOIN clients c ON c.id = q.client_id
    LEFT JOIN users u ON u.id = c.user_id
    $whereSQL
    ORDER BY FIELD(q.status,'nieuw','in_behandeling','wacht_op_reactie','afgerond'), q.updated_at DESC
");
$questions->execute($params);
$questions = $questions->fetchAll();

function qStatusLabel(string $status): string {
    $map = [
        'nieuw'            => ['Nieuw',               '#6C63FF'],
        'in_behandeling'   => ['In behandeling',      '#00D4FF'],
        'wacht_op_reactie' => ['Wacht op reactie',    '#FFB300'],
        'afgerond'         => ['Afgerond',            '#00C853'],
    ];
    [$label, $color] = $map[$status] ?? [$status, '#999'];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;background:' . $color . '22;color:' . $color . ';">' . $label . '</span>';
}

// Laad replies per vraag (voor de uitklapweergave)
$allReplies = [];
if ($questions) {
    $ids = implode(',', array_column($questions, 'id'));
    $rStmt = $db->query("
        SELECT r.*, u.name AS replier_name
        FROM question_replies r
        JOIN users u ON u.id = r.user_id
        WHERE r.question_id IN ($ids)
        ORDER BY r.created_at DESC
    ");
    foreach ($rStmt->fetchAll() as $r) {
        $allReplies[$r['question_id']][] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vragen — WebsiteVoorJou Admin</title>
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
          <h1>Klantvragen</h1>
          <p>Vragen gesteld door klanten via het portaal.</p>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">&#10003; <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Filter -->
    <form method="get" class="filter-bar">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" onchange="this.form.submit()">
          <option value="">Alle vragen</option>
          <option value="nieuw"            <?= $filterStatus === 'nieuw'            ? 'selected' : '' ?>>Nieuw</option>
          <option value="in_behandeling"   <?= $filterStatus === 'in_behandeling'   ? 'selected' : '' ?>>In behandeling</option>
          <option value="wacht_op_reactie" <?= $filterStatus === 'wacht_op_reactie' ? 'selected' : '' ?>>Wacht op reactie</option>
          <option value="afgerond"         <?= $filterStatus === 'afgerond'         ? 'selected' : '' ?>>Afgerond</option>
        </select>
      </div>
    </form>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><?= count($questions) ?> vraag/vragen</h3></div>
      <?php if (empty($questions)): ?>
        <p class="text-muted" style="padding:24px;">Geen vragen gevonden.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;">
          <?php foreach ($questions as $q): ?>
            <div style="padding:20px;border-bottom:1px solid var(--border);">

              <!-- Vraag header -->
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                <div style="flex:1;min-width:260px;">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                    <strong><?= htmlspecialchars($q['subject']) ?></strong>
                    <?= qStatusLabel($q['status']) ?>
                  </div>
                  <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">
                    <strong><?= htmlspecialchars($q['client_name']) ?></strong>
                    &bull; Project: <strong><?= htmlspecialchars($q['project_name']) ?></strong>
                    &bull; <?= formatDateTime($q['created_at']) ?>
                  </div>
                  <p style="font-size:0.9rem;white-space:pre-wrap;"><?= htmlspecialchars($q['message']) ?></p>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0;">
                  <a href="/admin/project-detail.php?id=<?= $q['project_id_real'] ?>" class="btn btn-sm btn-outline">&#128196; Naar project</a>
                  <button type="button" class="btn btn-sm btn-primary" onclick="toggleQReply(<?= $q['id'] ?>)">&#128172; Reageren</button>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                    <select name="status" class="form-control" style="font-size:0.8rem;padding:4px 8px;height:auto;">
                      <option value="nieuw"            <?= $q['status'] === 'nieuw'            ? 'selected' : '' ?>>Nieuw</option>
                      <option value="in_behandeling"   <?= $q['status'] === 'in_behandeling'   ? 'selected' : '' ?>>In behandeling</option>
                      <option value="wacht_op_reactie" <?= $q['status'] === 'wacht_op_reactie' ? 'selected' : '' ?>>Wacht op reactie</option>
                      <option value="afgerond"         <?= $q['status'] === 'afgerond'         ? 'selected' : '' ?>>Afgerond</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline">&#10003;</button>
                  </form>
                </div>
              </div>

              <!-- Eerdere reacties -->
              <?php if (!empty($allReplies[$q['id']])): ?>
                <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px;">
                  <?php foreach ($allReplies[$q['id']] as $r): ?>
                    <div style="background:var(--bg-2);border-radius:8px;padding:10px 14px;border-left:3px solid var(--primary);">
                      <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:4px;">
                        <strong><?= htmlspecialchars($r['replier_name']) ?></strong> &bull; <?= formatDateTime($r['created_at']) ?>
                      </div>
                      <p style="font-size:0.9rem;white-space:pre-wrap;margin:0;"><?= htmlspecialchars($r['message']) ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Reactieformulier -->
              <div id="qreply-<?= $q['id'] ?>" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <form method="post">
                  <input type="hidden" name="reply_question" value="1">
                  <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                  <div class="form-row">
                    <div class="form-group" style="flex:2;">
                      <label class="form-label">Reactie</label>
                      <textarea name="message" class="form-control" rows="3" placeholder="Typ hier je reactie..." required></textarea>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Status instellen</label>
                      <select name="status" class="form-control">
                        <option value="in_behandeling"   <?= $q['status'] === 'in_behandeling'   ? 'selected' : '' ?>>In behandeling</option>
                        <option value="wacht_op_reactie" <?= $q['status'] === 'wacht_op_reactie' ? 'selected' : '' ?>>Wacht op reactie</option>
                        <option value="afgerond"         <?= $q['status'] === 'afgerond'         ? 'selected' : '' ?>>Afgerond</option>
                        <option value="nieuw"            <?= $q['status'] === 'nieuw'            ? 'selected' : '' ?>>Nieuw</option>
                      </select>
                    </div>
                  </div>
                  <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">&#9993; Verstuur reactie</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleQReply(<?= $q['id'] ?>)">Annuleren</button>
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
function toggleQReply(id) {
  var el = document.getElementById('qreply-' + id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>
