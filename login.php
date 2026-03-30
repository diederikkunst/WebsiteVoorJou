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
  <title>Inloggen — WebsiteVoorJou</title>
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
    <div class="login-logo">WebsiteVoorJou</div>
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

      <?php if (GOOGLE_CLIENT_ID): ?>
      <div style="display:flex;align-items:center;gap:12px;margin:20px 0;">
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <span style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap;">of log in met</span>
        <div style="flex:1;height:1px;background:var(--border);"></div>
      </div>
      <a href="/auth/google.php" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:11px 16px;background:#fff;color:#3c4043;border:1px solid #dadce0;border-radius:8px;font-size:0.95rem;font-weight:500;text-decoration:none;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.boxShadow='none'">
        <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
        Inloggen met Google
      </a>
      <?php endif; ?>

      <div class="divider"></div>
      <p style="text-align:center;font-size:0.9rem;color:var(--text-muted);">
        Nog geen account? <a href="/register.php">Account aanmaken</a>
      </p>
    </div>
  </div>
  <script src="/assets/js/main.js"></script>
</body>
</html>
