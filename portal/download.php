<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

$projectId = (int)($_GET['id'] ?? 0);

if (!$client || !$projectId) {
    http_response_code(403); exit('Geen toegang.');
}

$stmt = $db->prepare('SELECT id, name, status, download_file FROM projects WHERE id = ? AND client_id = ?');
$stmt->execute([$projectId, $client['id']]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(403); exit('Geen toegang.');
}

if (empty($project['download_file'])) {
    http_response_code(404); exit('Download is nog niet beschikbaar.');
}

if ($project['status'] !== 'factuur_betaald') {
    http_response_code(403); exit('Download is pas beschikbaar na betaling.');
}

$filePath = __DIR__ . '/../uploads/downloads/' . basename($project['download_file']);

if (!file_exists($filePath)) {
    http_response_code(404); exit('Bestand niet gevonden. Neem contact op via info@websitevoorjou.nl.');
}

$downloadName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $project['name']) . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store');
readfile($filePath);
exit;
