<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$user = currentUser();
$db   = getDB();

$invoiceId = (int)($_GET['id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);

$invoice = null;
$project = null;
$client  = null;

if ($invoiceId) {
    $invoice = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $invoice->execute([$invoiceId]);
    $invoice = $invoice->fetch();
    if ($invoice) $projectId = $invoice['project_id'];
}

if ($projectId) {
    try {
        $stmt = $db->prepare('SELECT p.*, c.name AS client_name, c.address AS client_address, c.email AS client_email, c.bank_account, c.kvk AS client_kvk, c.client_category, c.id AS client_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
    } catch (\PDOException $e) {
        // Fallback als kvk/client_category kolommen nog niet bestaan in de database
        $stmt = $db->prepare('SELECT p.*, c.name AS client_name, c.address AS client_address, c.email AS client_email, c.bank_account, NULL AS client_kvk, \'particulier\' AS client_category, c.id AS client_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
    }
}

if (!$project) { header('Location: /admin/projects.php'); exit; }

$success = $error = '';

// Send invoice email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invoice_email']) && $invoice) {
    $toEmail = trim($_POST['to_email'] ?? $project['client_email'] ?? '');
    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } else {
        $amountInclBtw = number_format($invoice['amount'] * 1.21, 2, ',', '.');
        $amountExcl    = number_format($invoice['amount'], 2, ',', '.');
        $dueDate       = $invoice['due_date'] ? date('d-m-Y', strtotime($invoice['due_date'])) : date('d-m-Y', strtotime('+30 days'));

        $htmlBody = '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;background:#f9f9f9;padding:20px;">
<div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#6C63FF,#00D4FF);padding:32px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:1.8rem;">WebsiteVoorJou</h1>
    <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;">Jouw website, razendsnel live</p>
  </div>
  <div style="padding:32px;">
    <h2 style="color:#111;font-size:1.3rem;margin-bottom:16px;">Factuur voor ' . htmlspecialchars($project['name']) . '</h2>
    <p style="color:#444;line-height:1.6;">Hartelijk dank voor je vertrouwen in WebsiteVoorJou! Bijgaand ontvang je de factuur voor je website.</p>

    <div style="background:#f4f4f8;border-radius:8px;padding:20px;margin:24px 0;">
      <table style="width:100%;border-collapse:collapse;">
        <tr><td style="padding:6px 0;color:#666;font-size:0.9rem;">Factuurnummer</td><td style="text-align:right;font-weight:700;">' . htmlspecialchars($invoice['invoice_number']) . '</td></tr>
        <tr><td style="padding:6px 0;color:#666;font-size:0.9rem;">Omschrijving</td><td style="text-align:right;">' . htmlspecialchars($invoice['description']) . '</td></tr>
        <tr><td style="padding:6px 0;color:#666;font-size:0.9rem;">Bedrag excl. BTW</td><td style="text-align:right;">&euro;' . $amountExcl . '</td></tr>
        <tr><td style="padding:6px 0;color:#666;font-size:0.9rem;">BTW (21%)</td><td style="text-align:right;">&euro;' . number_format($invoice['amount'] * 0.21, 2, ',', '.') . '</td></tr>
        <tr style="border-top:2px solid #ddd;"><td style="padding:10px 0 6px;font-weight:700;">Totaal incl. BTW</td><td style="text-align:right;font-weight:700;font-size:1.1rem;color:#6C63FF;">&euro;' . $amountInclBtw . '</td></tr>
        <tr><td style="padding:6px 0;color:#666;font-size:0.9rem;">Betalen voor</td><td style="text-align:right;">' . $dueDate . '</td></tr>
      </table>
    </div>

    <p style="color:#444;font-size:0.95rem;line-height:1.7;">Gelieve het bedrag van <strong>&euro;' . $amountInclBtw . '</strong> vóór <strong>' . $dueDate . '</strong> over te maken op:<br>
    IBAN: <strong>NL 001570862B65</strong> t.n.v. WebsiteVoorJou<br>
    O.v.v. factuurnummer: <strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong></p>

    <div style="background:linear-gradient(135deg,rgba(108,99,255,0.08),rgba(0,212,255,0.08));border:1px solid rgba(108,99,255,0.2);border-radius:8px;padding:20px;margin:24px 0;">
      <h3 style="color:#111;font-size:1rem;margin:0 0 12px;">&#127881; Wat gebeurt er na betaling?</h3>
      <p style="color:#444;font-size:0.9rem;line-height:1.7;margin:0;">
        Na ontvangst van je betaling kun je inloggen op je account via <a href="' . APP_URL . '/login.php" style="color:#6C63FF;">' . APP_URL . '/login.php</a> en je project bekijken. Heb je nog vragen, dan kun je die ook via je account stellen.<br><br>
        Daarna heb je twee opties:<br>
        &bull; <strong>Samen online plaatsen</strong> — wij nemen contact op via je e-mail of telefoonnummer om samen de website live te zetten.<br>
        &bull; <strong>Zelf plaatsen</strong> — we zetten de bestanden klaar zodat je de website zelf kunt uploaden.<br><br>
        Kies je voorkeur na inloggen via je projectpagina.
      </p>
    </div>

    <div style="text-align:center;margin:28px 0;">
      <a href="' . APP_URL . '/login.php" style="display:inline-block;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#fff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:700;font-size:1rem;">Inloggen op mijn account &rarr;</a>
    </div>

    <p style="color:#888;font-size:0.85rem;">Vragen over de factuur? Stuur een e-mail naar <a href="mailto:' . MAIL_FROM . '" style="color:#6C63FF;">' . MAIL_FROM . '</a>.</p>
  </div>
  <div style="background:#f9f9f9;padding:16px 32px;text-align:center;border-top:1px solid #eee;">
    <p style="font-size:0.8rem;color:#999;margin:0;">WebsiteVoorJou &bull; ' . MAIL_FROM . ' &bull; websitevoorjou.nl</p>
  </div>
</div>
</body></html>';

        if (sendMail($toEmail, 'Factuur ' . $invoice['invoice_number'] . ' — WebsiteVoorJou', $htmlBody, $project['client_name'])) {
            $db->prepare("UPDATE invoices SET status = 'verstuurd' WHERE id = ?")->execute([$invoice['id']]);
            $db->prepare("UPDATE projects SET status = 'factuur_gestuurd' WHERE id = ?")->execute([$projectId]);
            // Redirect terug naar project met melding
            header('Location: /admin/project-detail.php?id=' . $projectId . '&invoice_sent=1');
            exit;
        } else {
            $error = 'Versturen mislukt. Controleer de mailconfiguratie.';
        }
    }
}

// Factuur bewerken (bedrag, omschrijving, vervaldatum + klant KVK & rekeningnummer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice']) && $invoice) {
    $amount     = (float)str_replace(',', '.', $_POST['amount'] ?? '0');
    $desc       = trim($_POST['description'] ?? '');
    $dueDate    = $_POST['due_date'] ?? '';
    $bankAcc    = trim($_POST['bank_account'] ?? '');
    $kvk        = trim($_POST['kvk'] ?? '');

    if ($amount > 0) {
        $db->prepare('UPDATE invoices SET amount = ?, description = ?, due_date = ? WHERE id = ?')
           ->execute([$amount, $desc, $dueDate ?: null, $invoice['id']]);

        // Update klantgegevens (bank + kvk)
        $db->prepare('UPDATE clients SET bank_account = ?, kvk = ? WHERE id = ?')
           ->execute([$bankAcc, $kvk, $project['client_id']]);

        $success = 'Factuur en klantgegevens bijgewerkt.';
        $invoice = $db->prepare('SELECT * FROM invoices WHERE id = ?');
        $invoice->execute([$invoiceId]);
        $invoice = $invoice->fetch();
        // Herlaad project + klant
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();
    } else {
        $error = 'Bedrag moet groter zijn dan 0.';
    }
}

// Update invoice status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && $invoice) {
    $newStatus = $_POST['invoice_status'] ?? $invoice['status'];
    if (!in_array($newStatus, ['concept','verstuurd','betaald'])) $newStatus = $invoice['status'];
    $db->prepare('UPDATE invoices SET status = ? WHERE id = ?')->execute([$newStatus, $invoice['id']]);

    // Update project status if paid
    if ($newStatus === 'betaald') {
        $db->prepare("UPDATE projects SET status = 'factuur_betaald' WHERE id = ?")->execute([$projectId]);
    } elseif ($newStatus === 'verstuurd') {
        $db->prepare("UPDATE projects SET status = 'factuur_gestuurd' WHERE id = ?")->execute([$projectId]);
    }

    $success = 'Factuurstatus bijgewerkt.';
    $invoice = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $invoice->execute([$invoiceId]);
    $invoice = $invoice->fetch();
}

// Create invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice']) && !$invoice) {
    $amount  = (float)str_replace(',', '.', $_POST['amount'] ?? '0');
    $desc    = trim($_POST['description'] ?? '');
    $dueDate = $_POST['due_date'] ?? '';
    $number  = nextInvoiceNumber();

    if ($amount > 0) {
        $db->prepare('INSERT INTO invoices (project_id, invoice_number, amount, description, due_date) VALUES (?, ?, ?, ?, ?)')
           ->execute([$projectId, $number, $amount, $desc, $dueDate ?: null]);
        $invoiceId = $db->lastInsertId();
        $db->prepare("UPDATE projects SET status = 'afgerond' WHERE id = ?")->execute([$projectId]);
        header('Location: /admin/invoice.php?id=' . $invoiceId);
        exit;
    } else {
        $error = 'Bedrag moet groter zijn dan 0.';
    }
}

$packagePrices = ['brons' => 0, 'zilver' => 999, 'goud' => 2999, 'platinum' => 0];
$suggestedPrice = $packagePrices[$project['package']] ?? 0;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Factuur — <?= htmlspecialchars($project['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="4000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/admin/project-detail.php?id=<?= $projectId ?>" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Project</a>
          <h1 style="margin-top:4px;">Factuur — <?= htmlspecialchars($project['name']) ?></h1>
        </div>
        <?php if ($invoice): ?>
          <div style="display:flex;gap:8px;">
            <button onclick="window.print()" class="btn btn-outline">&#128424; Afdrukken</button>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$invoice): ?>
    <!-- Create invoice form -->
    <div class="card" style="max-width:560px;margin-bottom:32px;">
      <div class="card-header"><h3 class="card-title">Factuur aanmaken</h3></div>
      <form method="post">
        <input type="hidden" name="create_invoice" value="1">
        <div class="form-group">
          <label class="form-label">Bedrag (excl. BTW) *</label>
          <input type="text" name="amount" class="form-control" value="<?= $suggestedPrice ?>" placeholder="999.00" required>
          <p class="form-hint">Suggestie op basis van pakket <?= ucfirst($project['package']) ?>: €<?= $suggestedPrice ?></p>
        </div>
        <div class="form-group">
          <label class="form-label">Omschrijving</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Websiteontwikkeling pakket <?= ucfirst($project['package']) ?> voor <?= htmlspecialchars($project['client_name']) ?>"><?= htmlspecialchars($project['name']) ?> — Pakket <?= ucfirst($project['package']) ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Vervaldatum</label>
          <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Factuur aanmaken</button>
      </form>
    </div>
    <?php else: ?>

    <!-- Stuur factuur per e-mail -->
    <div class="card" style="max-width:560px;margin-bottom:24px;border-color:var(--primary);">
      <div class="card-header"><h3 class="card-title">&#128231; Factuur per e-mail versturen</h3></div>
      <form method="post">
        <input type="hidden" name="send_invoice_email" value="1">
        <div style="display:flex;gap:8px;align-items:flex-end;">
          <div class="form-group flex-1" style="margin-bottom:0;">
            <label class="form-label">E-mailadres klant</label>
            <input type="email" name="to_email" class="form-control" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>" required placeholder="klant@bedrijf.nl">
          </div>
          <button type="submit" class="btn btn-primary">Versturen</button>
        </div>
        <p class="form-hint" style="margin-top:8px;">De e-mail bevat de factuurdetails en legt uit hoe de klant kan inloggen, goedkeuren en de website online kan plaatsen.</p>
      </form>
    </div>

    <!-- Factuur bewerken -->
    <div class="card" style="max-width:560px;margin-bottom:24px;">
      <div class="card-header" style="cursor:pointer;" onclick="document.getElementById('edit-invoice-form').style.display=document.getElementById('edit-invoice-form').style.display==='none'?'block':'none'">
        <h3 class="card-title">&#9998; Factuur bewerken</h3>
      </div>
      <div id="edit-invoice-form" style="display:none;">
        <form method="post">
          <input type="hidden" name="update_invoice" value="1">
          <div class="form-group">
            <label class="form-label">Bedrag (excl. BTW) *</label>
            <input type="text" name="amount" class="form-control" value="<?= $invoice['amount'] ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Omschrijving</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($invoice['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Vervaldatum</label>
            <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($invoice['due_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">IBAN rekeningnummer klant</label>
            <input type="text" name="bank_account" class="form-control" placeholder="NL 001570862B65"
              value="<?= htmlspecialchars($project['bank_account'] ?? '') ?>">
          </div>
          <?php if ($project['client_category'] === 'zakelijk'): ?>
          <div class="form-group">
            <label class="form-label">KVK-nummer klant</label>
            <input type="text" name="kvk" class="form-control" placeholder="12345678"
              value="<?= htmlspecialchars($project['client_kvk'] ?? '') ?>">
          </div>
          <?php else: ?>
            <input type="hidden" name="kvk" value="<?= htmlspecialchars($project['client_kvk'] ?? '') ?>">
          <?php endif; ?>
          <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
        </form>
      </div>
    </div>

    <!-- Status update -->
    <div class="card" style="max-width:400px;margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">Factuurstatus</h3></div>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;">
        <input type="hidden" name="update_status" value="1">
        <div class="form-group flex-1" style="margin-bottom:0;">
          <label class="form-label">Status</label>
          <select name="invoice_status" class="form-control">
            <option value="concept"   <?= $invoice['status'] === 'concept'   ? 'selected' : '' ?>>Concept</option>
            <option value="verstuurd" <?= $invoice['status'] === 'verstuurd' ? 'selected' : '' ?>>Verstuurd</option>
            <option value="betaald"   <?= $invoice['status'] === 'betaald'   ? 'selected' : '' ?>>Betaald</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
      </form>
    </div>

    <!-- Invoice preview -->
    <div class="invoice-preview" id="invoice-print">
      <div class="invoice-header">
        <div>
          <div class="invoice-logo">WebsiteVoorJou</div>
          <div style="font-size:0.85rem;color:#666;">info@websitevoorjou.nl</div>
          <div style="font-size:0.85rem;color:#666;">websitevoorjou.nl</div>
        </div>
        <div class="invoice-meta">
          <h2>FACTUUR</h2>
          <div><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></div>
          <div>Datum: <?= formatDate($invoice['created_at']) ?></div>
          <?php if ($invoice['due_date']): ?>
            <div>Vervaldatum: <?= formatDate($invoice['due_date']) ?></div>
          <?php endif; ?>
          <div style="margin-top:8px;">
            <span style="padding:3px 10px;background:<?= $invoice['status'] === 'betaald' ? '#00E676' : ($invoice['status'] === 'verstuurd' ? '#FFB300' : '#6C63FF') ?>;color:<?= $invoice['status'] === 'betaald' ? '#000' : '#fff' ?>;border-radius:4px;font-size:0.8rem;font-weight:700;">
              <?= strtoupper($invoice['status']) ?>
            </span>
          </div>
        </div>
      </div>

      <div class="invoice-parties">
        <div class="invoice-party">
          <h4>Van</h4>
          <strong>WebsiteVoorJou</strong><br>
          Nederland<br>
          info@websitevoorjou.nl<br>
          KvK: 24444475<br>
        </div>
        <div class="invoice-party">
          <h4>Aan</h4>
          <strong><?= htmlspecialchars($project['client_name']) ?></strong><br>
          <?= nl2br(htmlspecialchars($project['client_address'] ?? '')) ?><br>
          <?= htmlspecialchars($project['client_email'] ?? '') ?>
          <?php if (!empty($project['client_kvk'])): ?>
            <br>KvK: <?= htmlspecialchars($project['client_kvk']) ?>
          <?php endif; ?>
          <?php if (!empty($project['bank_account'])): ?>
            <br>IBAN: <?= htmlspecialchars($project['bank_account']) ?>
          <?php endif; ?>
        </div>
      </div>

      <table class="invoice-table">
        <thead>
          <tr>
            <th>Omschrijving</th>
            <th style="text-align:right;">Bedrag</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= nl2br(htmlspecialchars($invoice['description'])) ?></td>
            <td style="text-align:right;">&euro;<?= number_format($invoice['amount'], 2, ',', '.') ?></td>
          </tr>
        </tbody>
      </table>

      <div class="invoice-total">
        <div class="invoice-total-row">
          <span>Subtotaal</span>
          <span>&euro;<?= number_format($invoice['amount'], 2, ',', '.') ?></span>
        </div>
        <div class="invoice-total-row">
          <span>BTW (21%)</span>
          <span>&euro;<?= number_format($invoice['amount'] * 0.21, 2, ',', '.') ?></span>
        </div>
        <div class="invoice-total-row invoice-grand-total">
          <span>Totaal incl. BTW</span>
          <span>&euro;<?= number_format($invoice['amount'] * 1.21, 2, ',', '.') ?></span>
        </div>
      </div>

      <div class="invoice-footer">
        <p>Gelieve het bedrag van <strong>&euro;<?= number_format($invoice['amount'] * 1.21, 2, ',', '.') ?></strong> voor <?= $invoice['due_date'] ? formatDate($invoice['due_date']) : '30 dagen na factuurdatum' ?> over te maken op IBAN: <strong>NL 001570862B65</strong> t.n.v. WebsiteVoorJou o.v.v. factuurnummer <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>.</p>
        <p style="margin-top:8px;">Bedankt voor uw vertrouwen in WebsiteVoorJou!</p>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
<style>
@media print {
  .sidebar, .page-header, .card:not(#invoice-print), .alert { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  .invoice-preview { box-shadow: none; }
}
</style>
<script src="/assets/js/main.js"></script>
</body>
</html>
