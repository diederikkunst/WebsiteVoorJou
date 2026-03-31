<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

$invoiceId = (int)($_GET['id'] ?? 0);

if (!$client || !$invoiceId) {
    header('Location: /portal/dashboard.php'); exit;
}

// Haal factuur op — alleen als die bij een project van deze klant hoort
$stmt = $db->prepare('
    SELECT i.*, p.name AS project_name, p.package,
           c.name AS client_name, c.address AS client_address,
           c.email AS client_email, c.kvk AS client_kvk,
           c.bank_account AS client_bank, c.client_category
    FROM invoices i
    JOIN projects p ON p.id = i.project_id
    JOIN clients c ON c.id = p.client_id
    WHERE i.id = ? AND c.id = ?
');
$stmt->execute([$invoiceId, $client['id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: /portal/dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Factuur <?= htmlspecialchars($invoice['invoice_number']) ?> — WebsiteVoorJou</title>
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
      <li><a href="/portal/profile.php"><span class="nav-icon">&#128100;</span> Mijn profiel</a></li>
    </ul>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="sidebar-user-role">Klant</div>
        </div>
      </div>
      <a href="/logout.php" class="btn btn-outline btn-sm w-full" style="margin-top:8px;">Uitloggen</a>
      <a href="/algemene-voorwaarden.php" style="display:block;text-align:center;margin-top:10px;font-size:0.75rem;color:var(--text-muted);">Algemene voorwaarden</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <div class="page-header-row">
        <div>
          <a href="/portal/dashboard.php" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Terug naar dashboard</a>
          <h1 style="margin-top:4px;">Factuur <?= htmlspecialchars($invoice['invoice_number']) ?></h1>
        </div>
        <button onclick="window.print()" class="btn btn-primary">&#128424; Afdrukken / Opslaan als PDF</button>
      </div>
    </div>

    <div class="invoice-preview" id="invoice-print">
      <div class="invoice-header">
        <div>
          <div class="invoice-logo">WebsiteVoorJou</div>
          <div style="font-size:0.85rem;color:#666;">info@websitevoorjou.nl</div>
          <div style="font-size:0.85rem;color:#666;">websitevoorjou.nl</div>
          <div style="font-size:0.85rem;color:#666;">KvK: 24444475</div>
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
          <strong>KunstIT</strong><br>
          Goudkruid 78<br>
          3068SZ Rotterdam<br>
          Nederland<br>
          BTW: NL 001570862B65<br>
          KvK: 24444475
        </div>
        <div class="invoice-party">
          <h4>Aan</h4>
          <strong><?= htmlspecialchars($invoice['client_name']) ?></strong><br>
          <?= nl2br(htmlspecialchars($invoice['client_address'] ?? '')) ?><br>
          <?= htmlspecialchars($invoice['client_email'] ?? '') ?>
          <?php if (!empty($invoice['client_kvk'])): ?>
            <br>KvK: <?= htmlspecialchars($invoice['client_kvk']) ?>
          <?php endif; ?>
          <?php if (!empty($invoice['client_bank'])): ?>
            <br>IBAN: <?= htmlspecialchars($invoice['client_bank']) ?>
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
  </main>
</div>
<style>
@media print {
  .sidebar, .page-header, .alert { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  .invoice-preview { box-shadow: none; }
}
</style>
<script src="/assets/js/main.js"></script>
</body>
</html>
