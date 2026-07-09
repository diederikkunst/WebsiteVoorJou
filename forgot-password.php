<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

$error = '';
$sent  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Voer een geldig e-mailadres in.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Alleen daadwerkelijk versturen als de gebruiker bestaat — maar altijd
        // dezelfde bevestiging tonen, zodat je niet kunt afleiden welke e-mails bekend zijn.
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 uur
            $db->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?')
               ->execute([$token, $expires, $user['id']]);
            sendPasswordResetEmail($user['email'], $user['name'], $token);
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wachtwoord vergeten — WebsiteVoorJou</title>
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

      <?php if ($sent): ?>
        <div style="text-align:center;padding:8px 0;">
          <div style="font-size:3rem;margin-bottom:16px;">&#9993;</div>
          <h2 style="margin-bottom:8px;">Check je inbox</h2>
          <p style="color:var(--text-muted);font-size:0.95rem;line-height:1.6;">
            Als er een account bij dit e-mailadres hoort, hebben we een link gestuurd om je wachtwoord opnieuw in te stellen.
          </p>
          <p style="margin-top:16px;font-size:0.85rem;color:var(--text-muted);">Geen mail ontvangen? Controleer je spamfolder.</p>
          <a href="<?= BASE_PATH ?>/login.php" class="btn btn-primary w-full" style="margin-top:24px;">Terug naar inloggen</a>
        </div>
      <?php else: ?>

        <h2>Wachtwoord vergeten?</h2>
        <p>Vul je e-mailadres in en we sturen je een link om een nieuw wachtwoord in te stellen.</p>

        <?php if ($error): ?>
          <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="form-group">
            <label class="form-label">E-mailadres</label>
            <input type="email" name="email" class="form-control" placeholder="jij@bedrijf.nl" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;">Stuur resetlink &#8594;</button>
        </form>

        <div class="divider"></div>
        <p style="text-align:center;font-size:0.9rem;color:var(--text-muted);">
          Weet je het weer? <a href="<?= BASE_PATH ?>/login.php">Inloggen</a>
        </p>

      <?php endif; ?>
    </div>
  </div>
  <script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
