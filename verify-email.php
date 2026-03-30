<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
$error = '';

if (!$token) {
    $error = 'Ongeldige bevestigingslink.';
} else {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, name, email, email_verification_sent_at FROM users WHERE email_verification_token = ? AND is_active = 0 LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Deze link is ongeldig of al gebruikt.';
    } elseif (strtotime($user['email_verification_sent_at']) < time() - 86400) {
        $error = 'Deze bevestigingslink is verlopen (24 uur). <a href="/register.php">Probeer opnieuw</a>.';
    } else {
        // Activeer account
        $db->prepare('UPDATE users SET is_active = 1, email_verified = 1, email_verification_token = NULL WHERE id = ?')
           ->execute([$user['id']]);

        // Als deze gebruiker gekoppeld is aan een lead → nu omzetten naar klant
        $db->prepare("UPDATE clients SET type = 'client' WHERE user_id = ? AND type = 'lead'")
           ->execute([$user['id']]);

        // Auto-login
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = 'client';
        session_regenerate_id(true);

        header('Location: /portal/dashboard.php?welcome=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account bevestigen — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    body { display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:24px; }
  </style>
</head>
<body>
  <div style="max-width:440px;">
    <div style="font-size:1.6rem;font-weight:800;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:32px;">WebsiteVoorJou</div>
    <div style="font-size:3rem;margin-bottom:16px;">&#10007;</div>
    <h2 style="margin-bottom:8px;">Link niet geldig</h2>
    <p style="color:var(--text-muted);line-height:1.6;"><?= $error ?></p>
    <a href="/login.php" class="btn btn-primary" style="margin-top:24px;display:inline-block;">Naar inloggen</a>
  </div>
</body>
</html>
