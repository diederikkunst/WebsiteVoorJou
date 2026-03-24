<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$projectId = (int)($_GET['project_id'] ?? 0);
if (!$projectId) { header('Location: /admin/projects.php'); exit; }

$stmt = $db->prepare('SELECT p.*, c.name AS client_name, c.email AS client_email, c.id AS client_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) { header('Location: /admin/projects.php'); exit; }

$success = $error = '';
$token = null;

// Ensure token exists
if ($project['preview_url']) {
    $tknStmt = $db->prepare('SELECT token, expires_at FROM preview_tokens WHERE project_id = ? AND expires_at > NOW() LIMIT 1');
    $tknStmt->execute([$projectId]);
    $tokenRow = $tknStmt->fetch();
    if (!$tokenRow) {
        $token = createPreviewToken($projectId);
        $tknStmt->execute([$projectId]);
        $tokenRow = $tknStmt->fetch();
    } else {
        $token = $tokenRow['token'];
    }
}

$previewLink = $token ? (APP_URL . '/preview.php?token=' . $token) : '';

// Send email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $toEmail   = trim($_POST['to_email'] ?? '');
    $toName    = trim($_POST['to_name'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $bodyExtra = trim($_POST['body_extra'] ?? '');

    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } elseif (!$previewLink) {
        $error = 'Geen preview URL ingesteld voor dit project.';
    } else {
        // Try to get screenshot
        $screenshotHtml = '';
        if ($project['preview_url']) {
            $screenshotPath = getScreenshot($project['preview_url']);
            if ($screenshotPath) {
                $screenshotUrl = APP_URL . '/uploads/' . $screenshotPath;
                $screenshotHtml = '<p style="text-align:center;"><img src="' . htmlspecialchars($screenshotUrl) . '" alt="Website preview" style="max-width:100%;border-radius:8px;border:1px solid #ddd;"></p>';
            }
        }

        $htmlBody = '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;">
<div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#6C63FF,#00D4FF);padding:32px 32px 24px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:1.8rem;">WebSiteVoorJou</h1>
    <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;">Jouw website, razendsnel live</p>
  </div>
  <div style="padding:32px;">
    <h2 style="color:#111;font-size:1.3rem;margin-bottom:8px;">Hoi ' . htmlspecialchars($toName ?: $project['client_name']) . ',</h2>
    <p style="color:#444;line-height:1.6;">Goed nieuws! Je website preview staat klaar. We hebben op basis van jouw wensen een persoonlijk concept samengesteld.</p>
    ' . ($bodyExtra ? '<p style="color:#444;line-height:1.6;">' . nl2br(htmlspecialchars($bodyExtra)) . '</p>' : '') . '
    <div style="text-align:center;margin:32px 0;">
      <a href="' . htmlspecialchars($previewLink) . '" style="display:inline-block;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;text-decoration:none;padding:16px 40px;border-radius:8px;font-weight:700;font-size:1.05rem;">
        &#128065; Bekijk je website preview &rarr;
      </a>
    </div>
    ' . $screenshotHtml . '
    <p style="color:#666;font-size:0.9rem;">Tevreden? Maak dan een account aan en we zorgen samen dat jouw website live gaat. Vragen of aanpassingen? Reageer gewoon op deze e-mail.</p>
    <div style="background:#f4f4f8;border-radius:8px;padding:16px;margin-top:24px;">
      <p style="margin:0;font-size:0.85rem;color:#666;">
        <strong>Project:</strong> ' . htmlspecialchars($project['name']) . '<br>
        <strong>Preview link:</strong> <a href="' . htmlspecialchars($previewLink) . '" style="color:#6C63FF;">' . htmlspecialchars($previewLink) . '</a><br>
        <strong>Geldig tot:</strong> ' . ($token ? date('d-m-Y', strtotime('+' . PREVIEW_TOKEN_EXPIRY . ' days')) : '—') . '
      </p>
    </div>
  </div>
  <div style="background:#f9f9f9;padding:16px 32px;text-align:center;border-top:1px solid #eee;">
    <p style="font-size:0.8rem;color:#999;margin:0;">WebSiteVoorJou &bull; info@websitevoorjou.nl &bull; websitevoorjou.nl</p>
  </div>
</div>
</body></html>';

        if (sendMail($toEmail, $subject, $htmlBody, $toName)) {
            $success = 'E-mail succesvol verstuurd naar ' . $toEmail . '!';
            // Update project status
            if ($project['status'] === 'in_behandeling') {
                $db->prepare("UPDATE projects SET status = 'preview_beschikbaar' WHERE id = ?")->execute([$projectId]);
            }
        } else {
            $error = 'Versturen mislukt. Controleer de mailconfiguratie in config.php.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview mailen — <?= htmlspecialchars($project['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="6000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/admin/project-detail.php?id=<?= $projectId ?>" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Project</a>
          <h1 style="margin-top:4px;">Preview mailen</h1>
          <p><?= htmlspecialchars($project['name']) ?> — <?= htmlspecialchars($project['client_name']) ?></p>
        </div>
        <?php if ($previewLink): ?>
          <a href="<?= htmlspecialchars($previewLink) ?>" target="_blank" class="btn btn-outline">&#128065; Preview bekijken</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$project['preview_url']): ?>
      <div class="alert alert-warning">
        &#9888; Dit project heeft nog geen preview URL. <a href="/admin/project-detail.php?id=<?= $projectId ?>">Stel eerst een preview URL in.</a>
      </div>
    <?php endif; ?>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3 class="card-title">E-mail versturen</h3></div>
        <form method="post">
          <input type="hidden" name="send_email" value="1">
          <div class="form-group">
            <label class="form-label">Naar (naam)</label>
            <input type="text" name="to_name" class="form-control" value="<?= htmlspecialchars($project['client_name']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Naar (e-mailadres) *</label>
            <input type="email" name="to_email" class="form-control" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Onderwerp</label>
            <input type="text" name="subject" class="form-control" value="Je website preview staat klaar! — WebSiteVoorJou">
          </div>
          <div class="form-group">
            <label class="form-label">Extra bericht (optioneel)</label>
            <textarea name="body_extra" class="form-control" rows="4" placeholder="Voeg een persoonlijk bericht toe aan de e-mail..."></textarea>
          </div>
          <?php if ($previewLink): ?>
            <div class="form-group">
              <label class="form-label">Preview link die wordt meegestuurd</label>
              <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" class="form-control" value="<?= htmlspecialchars($previewLink) ?>" readonly>
                <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars($previewLink) ?>">Kopieer</button>
              </div>
            </div>
          <?php endif; ?>
          <p class="form-hint" style="margin-bottom:16px;">&#128247; Er wordt automatisch een screenshot van de preview toegevoegd aan de e-mail.</p>
          <button type="submit" class="btn btn-primary" <?= !$project['preview_url'] ? 'disabled' : '' ?>>&#128231; E-mail versturen</button>
        </form>
      </div>

      <!-- Email preview -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">E-mail voorbeeld</h3></div>
        <div style="background:#f9f9f9;border-radius:8px;padding:20px;font-family:Arial,sans-serif;font-size:0.9rem;border:1px solid var(--border);">
          <div style="background:linear-gradient(135deg,#6C63FF,#00D4FF);padding:20px;border-radius:8px 8px 0 0;text-align:center;margin:-20px -20px 20px;">
            <h2 style="color:#fff;margin:0;font-size:1.3rem;">WebSiteVoorJou</h2>
            <p style="color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:0.85rem;">Jouw website, razendsnel live</p>
          </div>
          <p style="color:#444;">Hoi <?= htmlspecialchars($project['client_name']) ?>,</p>
          <p style="color:#444;">Goed nieuws! Je website preview staat klaar...</p>
          <?php if ($previewLink): ?>
            <div style="text-align:center;margin:20px 0;">
              <span style="display:inline-block;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;padding:12px 28px;border-radius:6px;font-weight:700;">
                &#128065; Bekijk je website preview &rarr;
              </span>
            </div>
          <?php endif; ?>
          <p style="color:#666;font-size:0.85rem;">Tevreden? Maak dan een account aan en we zorgen samen dat jouw website live gaat.</p>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
