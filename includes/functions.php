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
        SELECT p.*, pt.expires_at, c.logo AS client_logo, c.name AS client_name
        FROM preview_tokens pt
        JOIN projects p ON p.id = pt.project_id
        JOIN clients c ON c.id = p.client_id
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
    $allowed = ['jpg','jpeg','png','gif','webp','svg','pdf','doc','docx','xls','xlsx','zip'];
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
    $host = MAIL_SMTP_HOST;
    $port = MAIL_SMTP_PORT;
    $user = MAIL_SMTP_USER;
    $pass = MAIL_SMTP_PASS;
    $from = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;

    $boundary = md5(uniqid());
    $toHeader = $toName ? '"' . $toName . '" <' . $to . '>' : $to;

    $message  = "Date: " . date('r') . "\r\n";
    $message .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $message .= "To: {$toHeader}\r\n";
    $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "\r\n";
    $message .= chunk_split(base64_encode($htmlBody));

    try {
        // Connect
        $sock = fsockopen($host, $port, $errno, $errstr, 15);
        if (!$sock) return false;

        $read = function() use ($sock) {
            $res = '';
            while ($line = fgets($sock, 512)) {
                $res .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $res;
        };

        $cmd = function(string $c) use ($sock, $read) {
            fwrite($sock, $c . "\r\n");
            return $read();
        };

        $read(); // 220 greeting

        // EHLO
        $resp = $cmd('EHLO ' . gethostname());

        // STARTTLS on port 587
        if ($port === 587) {
            $cmd('STARTTLS');
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO ' . gethostname());
        }

        // AUTH LOGIN
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($user));
        $resp = $cmd(base64_encode($pass));
        if (strpos($resp, '235') === false) {
            fclose($sock);
            return false;
        }

        // Envelope
        $cmd("MAIL FROM:<{$from}>");
        $cmd("RCPT TO:<{$to}>");
        $cmd('DATA');
        fwrite($sock, $message . "\r\n.\r\n");
        $resp = $read();
        $cmd('QUIT');
        fclose($sock);

        return strpos($resp, '250') !== false;

    } catch (\Throwable $e) {
        return false;
    }
}

function getScreenshot(string $url): ?string {
    $dir = UPLOAD_DIR . 'screenshots/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = 'screenshots/' . generateToken(16) . '.jpg';

    if (!empty(SCREENSHOT_API_KEY)) {
        // Betaalde provider: screenshotone.com
        $apiUrl = SCREENSHOT_API_URL . '?access_key=' . SCREENSHOT_API_KEY . '&url=' . urlencode($url) . '&format=jpg&viewport_width=1280&viewport_height=800';
    } else {
        // Gratis provider: thum.io (geen API key nodig)
        $apiUrl = 'https://image.thum.io/get/width/1280/crop/800/' . $url;
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'WebSiteVoorJou/1.0',
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data && $httpCode === 200 && strlen($data) > 1000) {
        file_put_contents(UPLOAD_DIR . $filename, $data);
        return $filename;
    }
    return null;
}
