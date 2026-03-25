<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$error = '';

// Pre-fill from contact request
$prefill = ['name' => '', 'email' => '', 'phone' => '', 'type' => 'lead', 'notes' => '', 'website' => '', 'logo' => ''];
if (!empty($_GET['from_contact'])) {
    $cr = $db->prepare('SELECT * FROM contact_requests WHERE id = ?');
    $cr->execute([(int)$_GET['from_contact']]);
    $contact = $cr->fetch();
    if ($contact) {
        $prefill['name']    = $contact['company'] ?: $contact['name'];
        $prefill['email']   = $contact['email'];
        $prefill['phone']   = $contact['phone'] ?? '';
        $prefill['website'] = $contact['current_website'] ?? '';
        $prefill['logo']    = $contact['logo'] ?? '';
        $prefill['notes']   = 'Aanvraag van ' . $contact['name'] . ":\n" . $contact['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $type    = $_POST['type'] ?? 'lead';
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $bank    = trim($_POST['bank_account'] ?? '');
    $notes   = trim($_POST['notes'] ?? '');

    if (!in_array($type, ['lead','client'])) $type = 'lead';

    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $logo = saveUpload($_FILES['logo'], 'logos');
    } elseif (!empty($_POST['existing_logo'])) {
        // Kopieer logo van contact_logos naar logos
        $src = UPLOAD_DIR . basename(dirname($_POST['existing_logo'])) . '/' . basename($_POST['existing_logo']);
        $dest = UPLOAD_DIR . 'logos/' . basename($_POST['existing_logo']);
        if (!is_dir(UPLOAD_DIR . 'logos/')) mkdir(UPLOAD_DIR . 'logos/', 0755, true);
        if (file_exists($src) && copy($src, $dest)) {
            $logo = 'logos/' . basename($_POST['existing_logo']);
        }
    }

    if ($name) {
        // Create portal user if email provided
        $userId = null;
        if ($email && $type === 'client') {
            $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $checkStmt->execute([$email]);
            $existing = $checkStmt->fetch();
            if (!$existing) {
                $tempPass = password_hash(generateToken(8), PASSWORD_DEFAULT);
                $ins = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, \'client\')');
                $ins->execute([$name, $email, $tempPass]);
                $userId = $db->lastInsertId();
            } else {
                $userId = $existing['id'];
            }
        }

        $stmt2 = $db->prepare('INSERT INTO clients (user_id, type, name, address, email, phone, website, bank_account, logo, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt2->execute([$userId, $type, $name, $address, $email, $phone, $website, $bank, $logo, $notes]);
        $clientId = $db->lastInsertId();
        header('Location: /admin/client-detail.php?id=' . $clientId . '&new=1');
        exit;
    } else {
        $error = 'Naam is verplicht.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nieuwe klant — WebSiteVoorJou Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/admin/clients.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Klanten</a>
          <h1 style="margin-top:4px;">Nieuwe klant of lead aanmaken</h1>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:720px;">
      <form method="post" enctype="multipart/form-data">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Type *</label>
            <select name="type" class="form-control">
              <option value="lead"   <?= ($_POST['type'] ?? $prefill['type']) === 'lead' ? 'selected' : '' ?>>Lead</option>
              <option value="client" <?= ($_POST['type'] ?? $prefill['type']) === 'client' ? 'selected' : '' ?>>Klant</option>
            </select>
            <p class="form-hint">Lead = nog geen klant. Klant = betalende klant met portaal-toegang.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Naam *</label>
            <input type="text" name="name" class="form-control" placeholder="Bedrijfsnaam of naam" value="<?= htmlspecialchars($_POST['name'] ?? $prefill['name']) ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">E-mailadres</label>
            <input type="email" name="email" class="form-control" placeholder="info@bedrijf.nl" value="<?= htmlspecialchars($_POST['email'] ?? $prefill['email']) ?>">
            <p class="form-hint">Bij type "Klant" wordt automatisch een portaalaccount aangemaakt.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Telefoonnummer</label>
            <input type="tel" name="phone" class="form-control" placeholder="+31 6 12345678" value="<?= htmlspecialchars($_POST['phone'] ?? $prefill['phone']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Adres</label>
          <textarea name="address" class="form-control" rows="2" placeholder="Straat 1, 1234 AB Plaats"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Website</label>
            <input type="url" name="website" class="form-control" placeholder="https://www.bedrijf.nl" value="<?= htmlspecialchars($_POST['website'] ?? $prefill['website']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Rekeningnummer (IBAN)</label>
            <input type="text" name="bank_account" class="form-control" placeholder="NL91 ABNA 0417 1643 00" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Logo</label>
          <?php $existingLogo = $_POST['existing_logo'] ?? $prefill['logo']; ?>
          <?php if ($existingLogo): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <img src="/uploads/<?= htmlspecialchars($existingLogo) ?>" alt="Logo" style="height:48px;max-width:120px;object-fit:contain;border-radius:6px;border:1px solid var(--border);padding:4px;background:#fff;">
              <span style="font-size:0.85rem;color:var(--text-muted);">Logo overgenomen uit aanvraag</span>
            </div>
            <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($existingLogo) ?>">
          <?php endif; ?>
          <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"<?= $existingLogo ? '' : '' ?>>
          <?php if ($existingLogo): ?>
            <p class="form-hint">Upload een nieuw bestand om het bovenstaande logo te vervangen.</p>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Notities</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Interne notities..."><?= htmlspecialchars($_POST['notes'] ?? $prefill['notes']) ?></textarea>
        </div>
        <div style="display:flex;gap:12px;margin-top:8px;">
          <button type="submit" class="btn btn-primary">Aanmaken &#8594;</button>
          <a href="/admin/clients.php" class="btn btn-outline">Annuleren</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="/assets/js/main.js"></script>
</body>
</html>
