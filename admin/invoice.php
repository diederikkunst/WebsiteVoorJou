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
    $stmt = $db->prepare('SELECT p.*, c.name AS client_name, c.address AS client_address, c.email AS client_email, c.bank_account, c.id AS client_id FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
}

if (!$project) { header('Location: /admin/projects.php'); exit; }

$success = $error = '';

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
          <button onclick="window.print()" class="btn btn-outline">&#128424; Afdrukken</button>
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
          <div class="invoice-logo">WebSiteVoorJou</div>
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
          <strong>WebSiteVoorJou</strong><br>
          Nederland<br>
          info@websitevoorjou.nl<br>
          KvK: XXXXXXXX<br>
          BTW: NL000000000B00
        </div>
        <div class="invoice-party">
          <h4>Aan</h4>
          <strong><?= htmlspecialchars($project['client_name']) ?></strong><br>
          <?= nl2br(htmlspecialchars($project['client_address'] ?? '')) ?><br>
          <?= htmlspecialchars($project['client_email'] ?? '') ?>
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
        <p>Gelieve het bedrag van <strong>&euro;<?= number_format($invoice['amount'] * 1.21, 2, ',', '.') ?></strong> voor <?= $invoice['due_date'] ? formatDate($invoice['due_date']) : '30 dagen na factuurdatum' ?> over te maken op IBAN: <strong>NL00 BANK 0000 0000 00</strong> t.n.v. WebSiteVoorJou o.v.v. factuurnummer <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>.</p>
        <p style="margin-top:8px;">Bedankt voor uw vertrouwen in WebSiteVoorJou!</p>
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
