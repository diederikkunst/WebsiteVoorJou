<?php
require_once __DIR__ . '/db.php';

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string {
    $labels = [
        'nieuw'                => '<span class="badge badge-new">Nieuw</span>',
        'in_behandeling'       => '<span class="badge badge-progress">In behandeling</span>',
        'preview_beschikbaar'  => '<span class="badge badge-preview">Preview beschikbaar</span>',
        'afgerond'             => '<span class="badge badge-done">Afgerond</span>',
        'factuur_gestuurd'     => '<span class="badge badge-invoice">Factuur gestuurd</span>',
        'factuur_betaald'      => '<span class="badge badge-paid">Factuur betaald</span>',
    ];
    return $labels[$status] ?? '<span class="badge">' . h($status) . '</span>';
}

function statusOptions(): array {
    return [
        'nieuw'               => 'Nieuw',
        'in_behandeling'      => 'In behandeling',
        'preview_beschikbaar' => 'Preview beschikbaar',
        'afgerond'            => 'Afgerond',
        'factuur_gestuurd'    => 'Factuur gestuurd',
        'factuur_betaald'     => 'Factuur betaald',
    ];
}

function packageLabel(string $package): string {
    $labels = [
        'brons'    => '<span class="badge badge-bronze">Brons</span>',
        'zilver'   => '<span class="badge badge-silver">Zilver</span>',
        'goud'     => '<span class="badge badge-gold">Goud</span>',
        'platinum' => '<span class="badge badge-platinum">Platinum</span>',
    ];
    return $labels[$package] ?? h($package);
}

function formatDate(string $date): string {
    return date('d-m-Y', strtotime($date));
}

function formatDateTime(string $date): string {
    return date('d-m-Y H:i', strtotime($date));
}

function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function createPreviewToken(int $projectId): string {
    $db = getDB();
    $token = generateToken(32);
    $expires = date('Y-m-d H:i:s', strtotime('+' . PREVIEW_TOKEN_EXPIRY . ' days'));

    // Remove old tokens for this project
    $db->prepare('DELETE FROM preview_tokens WHERE project_id = ?')->execute([$projectId]);

    $stmt = $db->prepare('INSERT INTO preview_tokens (project_id, token, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$projectId, $token, $expires]);
    return $token;
}

function getProjectByToken(string $token): ?array {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT p.*, pt.expires_at
        FROM preview_tokens pt
        JOIN projects p ON p.id = pt.project_id
        WHERE pt.token = ? AND pt.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function nextInvoiceNumber(): string {
    $db = getDB();
    $stmt = $db->query('SELECT COUNT(*) FROM invoices');
    $count = (int)$stmt->fetchColumn();
    return 'WVJ-' . date('Y') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function saveUpload(array $file, string $subdir): ?string {
    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','zip'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE) return null;

    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = generateToken(16) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return $subdir . '/' . $filename;
    }
    return null;
}

function sendMail(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    return mail($to, $subject, $htmlBody, $headers);
}

function getScreenshot(string $url): ?string {
    if (empty(SCREENSHOT_API_KEY)) return null;
    $apiUrl = SCREENSHOT_API_URL . '?access_key=' . SCREENSHOT_API_KEY . '&url=' . urlencode($url) . '&format=jpg&viewport_width=1280&viewport_height=800';
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $data = curl_exec($ch);
    curl_close($ch);
    if ($data && strlen($data) > 1000) {
        $filename = 'screenshots/' . generateToken(16) . '.jpg';
        $dir = UPLOAD_DIR . 'screenshots/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(UPLOAD_DIR . $filename, $data);
        return $filename;
    }
    return null;
}
