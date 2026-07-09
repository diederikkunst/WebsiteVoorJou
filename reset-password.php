<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

$db    = getDB();
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$done  = false;

// Token valideren (bestaat + niet verlopen)
$user = null;
if ($token !== '') {
    $stmt = $db->prepare('SELECT id, name, email, password_reset_expires FROM users WHERE password_reset_token = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user && strtotime($user['password_reset_expires']) < time()) {
        $user = null; // verlopen
    }
}
$tokenValid = (bool)$user;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 tekens bevatten.';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Wachtwoord bijwerken en token eenmalig ongeldig maken
        $db->prepare('UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?')
           ->execute([$hash, $user['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nieuw wachtwoord — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--bg-2); }
    .login-wrap { width: 100%; max-width: 440px; padding: 24px; }
    .login-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; }
    .login-logo { text-align: center; margin-bottom: 32px; font-size: 1.6rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .login-card h2 { font-size: 1.4rem; margin-bottom: 4px; }
    .login-card > p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-logo">WebsiteVoorJou</div>
    <div class="login-card">

      <?php if ($done): ?>
        <div style="text-align:center;padding:8px 0;">
          <div style="font-size:3rem;margin-bottom:16px;">&#9989;</div>
          <h2 style="margin-bottom:8px;">Wachtwoord gewijzigd</h2>
          <p style="color:var(--text-muted);font-size:0.95rem;line-height:1.6;">Je kunt nu inloggen met je nieuwe wachtwoord.</p>
          <a href="<?= BASE_PATH ?>/login.php" class="btn btn-primary w-full" style="margin-top:24px;">Naar inloggen &#8594;</a>
        </div>

      <?php elseif (!$tokenValid): ?>
        <div style="text-align:center;padding:8px 0;">
          <div style="font-size:3rem;margin-bottom:16px;">&#10007;</div>
          <h2 style="margin-bottom:8px;">Link niet geldig</h2>
          <p style="color:var(--text-muted);font-size:0.95rem;line-height:1.6;">Deze resetlink is ongeldig of verlopen (geldig gedurende 1 uur). Vraag een nieuwe aan.</p>
          <a href="<?= BASE_PATH ?>/forgot-password.php" class="btn btn-primary w-full" style="margin-top:24px;">Nieuwe link aanvragen</a>
        </div>

      <?php else: ?>

        <h2>Nieuw wachtwoord</h2>
        <p>Kies een nieuw wachtwoord voor <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-group">
            <label class="form-label">Nieuw wachtwoord</label>
            <input type="password" name="password" class="form-control" placeholder="Minimaal 8 tekens" required autofocus>
          </div>
          <div class="form-group">
            <label class="form-label">Herhaal wachtwoord</label>
            <input type="password" name="confirm" class="form-control" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;">Wachtwoord opslaan &#8594;</button>
        </form>

      <?php endif; ?>
    </div>
  </div>
  <script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
