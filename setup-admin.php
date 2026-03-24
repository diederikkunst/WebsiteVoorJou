<?php
/**
 * Eenmalig setup-script om het admin-wachtwoord in te stellen.
 * VERWIJDER DIT BESTAND NA GEBRUIK!
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$newPassword = 'Admin@2024!';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$db = getDB();

// Check if admin exists
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute(['admin@websitevoorjou.nl']);
$existing = $stmt->fetch();

if ($existing) {
    $db->prepare('UPDATE users SET password = ? WHERE email = ?')
       ->execute([$hash, 'admin@websitevoorjou.nl']);
    echo '<p style="color:green;font-family:sans-serif;">&#10003; Wachtwoord bijgewerkt.</p>';
} else {
    $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')
       ->execute(['Administrator', 'admin@websitevoorjou.nl', $hash, 'admin']);
    echo '<p style="color:green;font-family:sans-serif;">&#10003; Admin-account aangemaakt.</p>';
}

echo '<p style="font-family:sans-serif;"><strong>E-mail:</strong> admin@websitevoorjou.nl<br>';
echo '<strong>Wachtwoord:</strong> ' . htmlspecialchars($newPassword) . '</p>';
echo '<p style="color:red;font-family:sans-serif;"><strong>Verwijder dit bestand direct na gebruik!</strong></p>';
echo '<p style="font-family:sans-serif;"><a href="/login.php">Naar inlogpagina &rarr;</a></p>';
