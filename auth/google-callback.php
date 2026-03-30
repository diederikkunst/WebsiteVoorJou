<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Valideer state (CSRF bescherming)
if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: /login.php?error=invalid_state'); exit;
}
unset($_SESSION['oauth_state']);

if (empty($_GET['code'])) {
    header('Location: /login.php?error=no_code'); exit;
}

// Wissel code in voor access token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
]);
$tokenData = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($tokenData['access_token'])) {
    header('Location: /login.php?error=token_failed'); exit;
}

// Haal gebruikersinfo op
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']],
]);
$googleUser = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($googleUser['email'])) {
    header('Location: /login.php?error=no_email'); exit;
}

$db    = getDB();
$email = $googleUser['email'];
$name  = $googleUser['name'] ?? $email;

// Zoek bestaande gebruiker
$stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Nieuw account aanmaken — Google verifieert e-mail zelf
    $db->prepare('INSERT INTO users (name, email, password, role, is_active, email_verified) VALUES (?, ?, ?, \'client\', 1, 1)')
       ->execute([$name, $email, password_hash(generateToken(16), PASSWORD_DEFAULT)]);
    $userId = $db->lastInsertId();
} else {
    $userId = $user['id'];
    // Als account nog niet actief was (bijv. wachtte op e-mailbevestiging): activeer alsnog via Google
    if (!$user['is_active']) {
        $db->prepare('UPDATE users SET is_active = 1, email_verified = 1, email_verification_token = NULL WHERE id = ?')
           ->execute([$userId]);
    }
}

// Zorg altijd dat er een client-record bestaat (ook bij bestaande gebruikers)
$clientCheck = $db->prepare('SELECT id FROM clients WHERE user_id = ?');
$clientCheck->execute([$userId]);
if (!$clientCheck->fetch()) {
    $db->prepare('INSERT INTO clients (user_id, type, name, email) VALUES (?, \'lead\', ?, ?)')
       ->execute([$userId, $name, $email]);
}

// Inloggen
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];
session_regenerate_id(true);

header('Location: /portal/dashboard.php');
exit;
