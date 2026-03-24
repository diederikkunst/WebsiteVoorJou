<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header('Location: ' . ($role === 'client' ? '/portal/dashboard.php' : '/admin/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        if (login($email, $password)) {
            $role = $_SESSION['user_role'];
            header('Location: ' . ($role === 'client' ? '/portal/dashboard.php' : '/admin/index.php'));
            exit;
        } else {
            $error = 'Ongeldig e-mailadres of wachtwoord.';
        }
    } else {
        $error = 'Vul je e-mailadres en wachtwoord in.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inloggen — WebSiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-wrap { width: 100%; max-width: 440px; padding: 24px; }
    .login-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 40px; }
    .login-logo { text-align: center; margin-bottom: 32px; font-size: 1.6rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .login-card h2 { font-size: 1.4rem; margin-bottom: 4px; }
    .login-card > p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-logo">WebSiteVoorJou</div>
    <div class="login-card">
      <h2>Welkom terug</h2>
      <p>Log in op je account om verder te gaan.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label class="form-label">E-mailadres</label>
          <input type="email" name="email" class="form-control" placeholder="jij@bedrijf.nl" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label">Wachtwoord</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-full" style="margin-top:8px;">
          Inloggen &#8594;
        </button>
      </form>

      <div class="divider"></div>
      <p style="text-align:center;font-size:0.9rem;color:var(--text-muted);">
        Nog geen account? <a href="/register.php">Account aanmaken</a>
      </p>
    </div>
  </div>
  <script src="/assets/js/main.js"></script>
</body>
</html>
