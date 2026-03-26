<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$db     = getDB();

$userRow = $db->prepare('SELECT * FROM users WHERE id = ?');
$userRow->execute([$user['id']]);
$userRow = $userRow->fetch();

$client = getClientForUser($user['id']);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $passNew = $_POST['password_new'] ?? '';
    $passOld = $_POST['password_old'] ?? '';

    if (!$name || !$email) {
        $error = 'Naam en e-mailadres zijn verplicht.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } else {
        // Check email not taken by someone else
        $check = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$email, $user['id']]);
        if ($check->fetch()) {
            $error = 'Dit e-mailadres is al in gebruik.';
        } else {
            // Update user
            if ($passNew) {
                if (!password_verify($passOld, $userRow['password'])) {
                    $error = 'Huidig wachtwoord is onjuist.';
                } elseif (strlen($passNew) < 8) {
                    $error = 'Nieuw wachtwoord moet minimaal 8 tekens bevatten.';
                } else {
                    $hash = password_hash($passNew, PASSWORD_DEFAULT);
                    $db->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?')
                       ->execute([$name, $email, $hash, $user['id']]);
                }
            } else {
                $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                   ->execute([$name, $email, $user['id']]);
            }

            if (!$error) {
                // Update client record
                if ($client) {
                    $db->prepare('UPDATE clients SET name = ?, email = ?, phone = ?, address = ?, website = ? WHERE id = ?')
                       ->execute([$company ?: $name, $email, $phone, $address, $website, $client['id']]);
                }

                $_SESSION['user_name'] = $name;
                $success = 'Profiel bijgewerkt.';

                // Reload
                $userRow = $db->prepare('SELECT * FROM users WHERE id = ?');
                $userRow->execute([$user['id']]);
                $userRow = $userRow->fetch();
                $client = getClientForUser($user['id']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mijn profiel — WebsiteVoorJou</title>
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
      <li><a href="/portal/questions.php"><span class="nav-icon">&#10067;</span> Mijn vragen</a></li>
      <li><a href="/portal/profile.php" class="active"><span class="nav-icon">&#128100;</span> Mijn profiel</a></li>
    </ul>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($userRow['name'], 0, 1)) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($userRow['name']) ?></div>
          <div class="sidebar-user-role">Klant</div>
        </div>
      </div>
      <a href="/logout.php" class="btn btn-outline btn-sm w-full" style="margin-top:8px;">Uitloggen</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <h1>Mijn profiel</h1>
      <p>Houd je gegevens up-to-date zodat we altijd contact met je kunnen opnemen.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="4000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid-2">
      <!-- Persoonsgegevens -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Persoonlijke gegevens</h3></div>
        <form method="post">
          <input type="hidden" name="update_profile" value="1">
          <div class="form-group">
            <label class="form-label">Naam *</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($userRow['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">E-mailadres *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userRow['email']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Bedrijfsnaam</label>
            <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($client['name'] ?? '') ?>" placeholder="Jouw Bedrijf B.V.">
          </div>
          <div class="form-group">
            <label class="form-label">Telefoonnummer</label>
            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="+31 6 12345678">
            <p class="form-hint">We gebruiken dit om contact op te nemen bij het online plaatsen van je website.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Adres</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Straat 1, 1234 AB Stad"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Website</label>
            <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($client['website'] ?? '') ?>" placeholder="https://www.jouwbedrijf.nl">
          </div>
          <div class="divider"></div>
          <h4 style="margin-bottom:16px;font-size:1rem;">Wachtwoord wijzigen</h4>
          <div class="form-group">
            <label class="form-label">Huidig wachtwoord</label>
            <input type="password" name="password_old" class="form-control" placeholder="Laat leeg om niet te wijzigen">
          </div>
          <div class="form-group">
            <label class="form-label">Nieuw wachtwoord</label>
            <input type="password" name="password_new" class="form-control" placeholder="Minimaal 8 tekens">
          </div>
          <button type="submit" class="btn btn-primary">Opslaan</button>
        </form>
      </div>

      <!-- Info -->
      <div>
        <div class="card">
          <div class="card-header"><h3 class="card-title">Waarom zijn je gegevens belangrijk?</h3></div>
          <div style="display:flex;flex-direction:column;gap:16px;">
            <div class="about-feature">
              <div class="about-feature-icon">&#128231;</div>
              <div>
                <h4>E-mailadres</h4>
                <p>Voor facturen, preview-links en updates over je project.</p>
              </div>
            </div>
            <div class="about-feature">
              <div class="about-feature-icon">&#128222;</div>
              <div>
                <h4>Telefoonnummer</h4>
                <p>Wij bellen je op als je kiest voor "samen online plaatsen" na betaling.</p>
              </div>
            </div>
            <div class="about-feature">
              <div class="about-feature-icon">&#127968;</div>
              <div>
                <h4>Adres</h4>
                <p>Gebruikt op je facturen en in de bedrijfsgegevens.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
