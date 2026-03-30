<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

if (!$client) {
    header('Location: /portal/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $package = $_POST['package'] ?? 'brons';

    if (!in_array($package, ['brons','zilver','goud','platinum'])) $package = 'brons';

    if ($name) {
        $stmt = $db->prepare('INSERT INTO projects (client_id, name, description, package, status) VALUES (?, ?, ?, ?, \'nieuw\')');
        $stmt->execute([$client['id'], $name, $desc, $package]);
        $projectId = $db->lastInsertId();

        // Sla op als contactaanvraag zodat het zichtbaar is onder Aanvragen
        $contactMsg = "Nieuw project aangemaakt via het klantportaal.\n\nProject: $name\nPakket: $package\n\nBeschrijving:\n$desc";
        $db->prepare('INSERT INTO contact_requests (name, email, phone, company, message) VALUES (?, ?, ?, ?, ?)')
           ->execute([$client['name'], $client['email'], $client['phone'] ?? '', '', $contactMsg]);

        // Stuur notificatie naar info@websitevoorjou.nl
        $packageLabels = ['brons' => 'Brons (gratis preview)', 'zilver' => 'Zilver (€999)', 'goud' => 'Goud (€2.999)', 'platinum' => 'Platinum (op maat)'];
        $packageLabel  = $packageLabels[$package] ?? ucfirst($package);
        $adminUrl      = APP_URL . '/admin/project-detail.php?id=' . $projectId;

        $htmlBody = '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9;padding:20px;">
<div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#6C63FF,#00D4FF);padding:28px 32px;">
    <h1 style="color:#fff;margin:0;font-size:1.4rem;">&#128196; Nieuw project via portaal</h1>
  </div>
  <div style="padding:28px 32px;">
    <table style="width:100%;border-collapse:collapse;font-size:0.95rem;">
      <tr><td style="padding:8px 0;color:#666;width:140px;">Klant</td><td style="font-weight:600;">' . htmlspecialchars($client['name']) . '</td></tr>
      <tr><td style="padding:8px 0;color:#666;">E-mail</td><td><a href="mailto:' . htmlspecialchars($client['email']) . '" style="color:#6C63FF;">' . htmlspecialchars($client['email']) . '</a></td></tr>
      <tr><td style="padding:8px 0;color:#666;">Telefoon</td><td>' . htmlspecialchars($client['phone'] ?? '—') . '</td></tr>
      <tr><td style="padding:8px 0;color:#666;">Project</td><td style="font-weight:600;">' . htmlspecialchars($name) . '</td></tr>
      <tr><td style="padding:8px 0;color:#666;">Pakket</td><td>' . htmlspecialchars($packageLabel) . '</td></tr>
      <tr><td style="padding:8px 0;color:#666;vertical-align:top;">Beschrijving</td><td>' . nl2br(htmlspecialchars($desc ?: '—')) . '</td></tr>
    </table>
    <div style="margin-top:24px;text-align:center;">
      <a href="' . $adminUrl . '" style="display:inline-block;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:700;">Project bekijken in admin &rarr;</a>
    </div>
  </div>
</div>
</body></html>';

        sendMail(MAIL_FROM, 'Nieuw project: ' . $name . ' — ' . $client['name'], $htmlBody, 'WebsiteVoorJou Admin', 'nieuw_project');

        header('Location: /portal/project.php?id=' . $projectId . '&new=1');
        exit;
    } else {
        $error = 'Vul een projectnaam in.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nieuw project — WebsiteVoorJou</title>
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
      <li><a href="/portal/new-project.php" class="active"><span class="nav-icon">&#43;</span> Nieuw project</a></li>
      <li><a href="/portal/questions.php"><span class="nav-icon">&#10067;</span> Mijn vragen</a></li>
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
      <a href="/algemene-voorwaarden.php" style="display:block;text-align:center;margin-top:10px;font-size:0.75rem;color:var(--text-muted);">Algemene voorwaarden</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <h1>Nieuw project aanmaken</h1>
      <p>Vertel ons wat je nodig hebt en we gaan aan de slag.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:640px;">
      <form method="post">
        <div class="form-group">
          <label class="form-label">Projectnaam *</label>
          <input type="text" name="name" class="form-control" placeholder="Bijv. Nieuwe website voor mijn bakkerij" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Beschrijving</label>
          <textarea name="description" class="form-control" rows="5" placeholder="Beschrijf wat je wil. Wat doet je bedrijf? Wat voor website wil je? Heb je een huisstijl? Doelgroep?"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          <p class="form-hint">Hoe meer informatie, hoe beter we op je wensen kunnen inspelen.</p>
        </div>
        <div class="form-group">
          <label class="form-label">Gewenst pakket</label>
          <select name="package" class="form-control">
            <option value="brons">&#127881; Brons — Gratis website concept (preview)</option>
            <option value="zilver">&#127748; Zilver — Website live zetten (€999)</option>
            <option value="goud">&#11088; Goud — Website met eigen beheer (€2.999)</option>
            <option value="platinum">&#128142; Platinum — Bedrijfssoftware (op maat)</option>
          </select>
        </div>
        <div style="display:flex;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary">Project aanmaken &#8594;</button>
          <a href="/portal/dashboard.php" class="btn btn-outline">Annuleren</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
