<?php
$user    = currentUser();
$current = basename($_SERVER['PHP_SELF']);
$dir     = basename(dirname($_SERVER['PHP_SELF']));
$isAdmin = $user['role'] === 'admin';
?>
<aside class="sidebar">
  <div class="sidebar-brand">WebsiteVoorJou</div>

  <div class="sidebar-section">Overzicht</div>
  <ul class="sidebar-nav">
    <li><a href="/admin/index.php" <?= $current === 'index.php' && $dir === 'admin' ? 'class="active"' : '' ?>><span class="nav-icon">&#128200;</span> Dashboard</a></li>
    <li><a href="/admin/projects.php" <?= $current === 'projects.php' || $current === 'project-detail.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#128196;</span> Projecten</a></li>
    <li><a href="/admin/new-project.php" <?= $current === 'new-project.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#43;</span> Nieuw project</a></li>
  </ul>

  <div class="sidebar-section">Klanten</div>
  <ul class="sidebar-nav">
    <li><a href="/admin/clients.php" <?= $current === 'clients.php' || $current === 'client-detail.php' || $current === 'new-client.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#128101;</span> Klanten &amp; Leads</a></li>
    <li><a href="/admin/contacts.php" <?= $current === 'contacts.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#128172;</span> Aanvragen</a></li>
    <li><a href="/admin/questions.php" <?= $current === 'questions.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#10067;</span> Vragen</a></li>
  </ul>

  <div class="sidebar-section">Financieel</div>
  <ul class="sidebar-nav">
    <li><a href="/admin/invoices.php" <?= $current === 'invoices.php' || $current === 'invoice.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#128195;</span> Facturen</a></li>
  </ul>

  <?php if ($isAdmin): ?>
  <div class="sidebar-section">Beheer</div>
  <ul class="sidebar-nav">
    <li><a href="/admin/employees.php" <?= $current === 'employees.php' ? 'class="active"' : '' ?>><span class="nav-icon">&#128119;</span> Medewerkers</a></li>
  </ul>
  <?php endif; ?>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div>
        <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="sidebar-user-role"><?= ucfirst($user['role']) ?></div>
      </div>
    </div>
    <a href="/logout.php" class="btn btn-outline btn-sm w-full" style="margin-top:8px;">Uitloggen</a>
  </div>
</aside>
