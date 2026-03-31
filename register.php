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

        // Stap 1: blokkeer alleen als e-mail al bestaat als bevestigde klant (type=client)
        $clientCheck = $db->prepare("SELECT id FROM clients WHERE email = ? AND type = 'client' LIMIT 1");
        $clientCheck->execute([$email]);
        if ($clientCheck->fetch()) {
            $error = 'Dit e-mailadres is al gekoppeld aan een klantaccount. <a href="/login.php">Inloggen?</a>';
        } else {
            // Stap 2: zoek bestaande lead op basis van e-mail
            $leadStmt = $db->prepare("SELECT * FROM clients WHERE email = ? AND type = 'lead' LIMIT 1");
            $leadStmt->execute([$email]);
            $existingLead = $leadStmt->fetch();

            // Stap 3: zoek bestaande user op basis van e-mail
            $userStmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $userStmt->execute([$email]);
            $existingUser = $userStmt->fetch();

            $hash  = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));

            if ($existingUser) {
                // User bestaat al (bijv. via contactformulier aangemaakt) — update gegevens en stuur nieuwe verificatiemail
                $db->prepare('UPDATE users SET name = ?, password = ?, is_active = 0, email_verified = 0, email_verification_token = ?, email_verification_sent_at = NOW() WHERE id = ?')
                   ->execute([$name, $hash, $token, $existingUser['id']]);
                $userId = $existingUser['id'];
            } else {
                // Nieuwe user aanmaken — inactief tot bevestiging
                $db->prepare('INSERT INTO users (name, email, password, role, is_active, email_verified, email_verification_token, email_verification_sent_at) VALUES (?, ?, ?, \'client\', 0, 0, ?, NOW())')
                   ->execute([$name, $email, $hash, $token]);
                $userId = $db->lastInsertId();
            }

            if ($existingLead) {
                // Koppel aan bestaande lead + update gegevens — lead blijft 'lead' tot na e-mailbevestiging
                $updateName  = ($company ?: $name) ?: $existingLead['name'];
                $updatePhone = $phone ?: $existingLead['phone'];
                $db->prepare('UPDATE clients SET user_id = ?, name = ?, phone = ? WHERE id = ?')
                   ->execute([$userId, $updateName, $updatePhone, $existingLead['id']]);
            } else {
                // Geen lead gevonden — maak nieuw klantprofiel aan
                $db->prepare('INSERT INTO clients (user_id, type, name, email, phone) VALUES (?, \'lead\', ?, ?, ?)')
                   ->execute([$userId, $company ?: $name, $email, $phone]);
            }

            sendVerificationEmail($email, $name, $token);

            // Notificatie naar admin
            $adminHtml = '<p>Nieuwe aanmelding op WebsiteVoorJou:</p>'
                . '<ul><li><strong>Naam:</strong> ' . htmlspecialchars($name) . '</li>'
                . '<li><strong>E-mail:</strong> ' . htmlspecialchars($email) . '</li>'
                . ($phone ? '<li><strong>Telefoon:</strong> ' . htmlspecialchars($phone) . '</li>' : '')
                . ($company ? '<li><strong>Bedrijf:</strong> ' . htmlspecialchars($company) . '</li>' : '')
                . ($existingLead ? '<li><strong>Gekoppeld aan bestaande lead</strong></li>' : '')
                . '</ul>';
            sendMail(MAIL_FROM, 'Nieuwe aanmelding: ' . $name, $adminHtml, 'WebsiteVoorJou', 'admin_notificatie');

            $success = $existingLead ? 'sent_lead' : 'sent';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account aanmaken — WebsiteVoorJou</title>
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
    <div class="register-logo">WebsiteVoorJou</div>
    <div class="register-card">

      <?php if (in_array($success, ['sent', 'resent', 'sent_lead'])): ?>
        <div style="text-align:center;padding:16px 0;">
          <div style="font-size:3rem;margin-bottom:16px;">&#9993;</div>
          <h2 style="margin-bottom:8px;">Check je inbox!</h2>
          <p style="color:var(--text-muted);font-size:0.95rem;line-height:1.6;">
            We hebben een bevestigingslink verstuurd naar
            <strong><?= htmlspecialchars($_POST['email'] ?? '') ?></strong>.<br>
            Klik op de link in de e-mail om je account te activeren.
            <?php if ($success === 'sent_lead'): ?>
              <br><span style="font-size:0.85rem;margin-top:8px;display:inline-block;">Je bestaande aanvraag en projecten worden automatisch aan je account gekoppeld.</span>
            <?php endif; ?>
          </p>
          <p style="margin-top:20px;font-size:0.85rem;color:var(--text-muted);">Geen mail ontvangen? Controleer je spamfolder.</p>
        </div>
      <?php else: ?>

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

        <?php if (GOOGLE_CLIENT_ID): ?>
        <div style="display:flex;align-items:center;gap:12px;margin:20px 0;">
          <div style="flex:1;height:1px;background:var(--border);"></div>
          <span style="font-size:0.8rem;color:var(--text-muted);white-space:nowrap;">of registreer met</span>
          <div style="flex:1;height:1px;background:var(--border);"></div>
        </div>
        <a href="/auth/google.php" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:11px 16px;background:#fff;color:#3c4043;border:1px solid #dadce0;border-radius:8px;font-size:0.95rem;font-weight:500;text-decoration:none;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.boxShadow='none'">
          <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.08 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-3.59-13.46-8.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
          Registreren met Google
        </a>
        <?php endif; ?>

        <div class="divider"></div>
        <p style="text-align:center;font-size:0.9rem;color:var(--text-muted);">
          Al een account? <a href="/login.php">Inloggen</a>
        </p>

      <?php endif; ?>
    </div>
  </div>
  <script src="/assets/js/main.js"></script>
</body>
</html>
