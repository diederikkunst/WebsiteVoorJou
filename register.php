<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /portal/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $company  = trim($_POST['company'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'Vul alle verplichte velden in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } elseif (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        $db = getDB();

        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Dit e-mailadres is al in gebruik. <a href="/login.php">Inloggen?</a>';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Create user
            $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, \'client\')')
               ->execute([$name, $email, $hash]);
            $userId = $db->lastInsertId();

            // Create client record (type = lead totdat admin omzet naar client)
            $db->prepare('INSERT INTO clients (user_id, type, name, email, phone) VALUES (?, \'lead\', ?, ?, ?)')
               ->execute([$userId, $company ?: $name, $email, $phone]);

            // Auto login
            login($email, $password);
            header('Location: /portal/dashboard.php?welcome=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account aanmaken — WebSiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px 0; }
    .register-wrap { width: 100%; max-width: 480px; padding: 24px; }
    .register-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; }
    .register-logo { text-align: center; margin-bottom: 32px; font-size: 1.6rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  </style>
</head>
<body>
  <div class="register-wrap">
    <div class="register-logo">WebSiteVoorJou</div>
    <div class="register-card">
      <h2 style="margin-bottom:4px;">Account aanmaken</h2>
      <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:24px;">Maak een account aan om je projecten te beheren.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">&#10007; <?= $error ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label class="form-label">Jouw naam *</label>
          <input type="text" name="name" class="form-control" placeholder="Jan de Vries" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Bedrijfsnaam</label>
          <input type="text" name="company" class="form-control" placeholder="Jouw Bedrijf B.V." value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
          <p class="form-hint">Laat leeg als je geen bedrijf hebt.</p>
        </div>
        <div class="form-group">
          <label class="form-label">E-mailadres *</label>
          <input type="email" name="email" class="form-control" placeholder="jan@bedrijf.nl" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Telefoonnummer</label>
          <input type="tel" name="phone" class="form-control" placeholder="+31 6 12345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Wachtwoord *</label>
            <input type="password" name="password" class="form-control" placeholder="Minimaal 8 tekens" required>
          </div>
          <div class="form-group">
            <label class="form-label">Herhaal wachtwoord *</label>
            <input type="password" name="confirm" class="form-control" placeholder="••••••••" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;">
          Account aanmaken &#8594;
        </button>
      </form>

      <div class="divider"></div>
      <p style="text-align:center;font-size:0.9rem;color:var(--text-muted);">
        Al een account? <a href="/login.php">Inloggen</a>
      </p>
    </div>
  </div>
  <script src="/assets/js/main.js"></script>
</body>
</html>
