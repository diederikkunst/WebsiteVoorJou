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

/**
 * Controleert de geldigheid van een e-mailadres.
 * Naast het formaat wordt gecontroleerd of het domein daadwerkelijk
 * e-mail kan ontvangen (MX-record, met fallback naar een A/AAAA-record).
 * Zo worden typefouten (bijv. "gmail.con") en niet-bestaande domeinen afgevangen.
 *
 * @param bool $checkDomain Zet op false om alleen het formaat te controleren (bijv. offline).
 */
function isValidEmail(string $email, bool $checkDomain = true): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (!$checkDomain) {
        return true;
    }
    $domain = substr(strrchr($email, '@'), 1);
    if ($domain === '' || $domain === false) {
        return false;
    }
    // DNS-functies vereisen een punt-getermineerde hostnaam voor betrouwbare lookups.
    $host = rtrim($domain, '.') . '.';
    // Geldig zodra er een MX-record is, of anders een A/AAAA-record (mail mag dan naar de host).
    if (function_exists('checkdnsrr')) {
        return checkdnsrr($host, 'MX') || checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA');
    }
    return true; // DNS-check niet beschikbaar — val terug op formaatcontrole.
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
    $year = date('Y');
    $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute(['WVJ-' . $year . '-%']);
    $max = (int)$stmt->fetchColumn();
    return 'WVJ-' . $year . '-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
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

/**
 * SEO-checklist: gedeelde definitie (key => label) voor portal en admin.
 */
function seoChecklistItems(): array {
    return [
        'keyword'    => 'Belangrijkste zoekwoord gekozen waarop je gevonden wilt worden',
        'title_desc' => 'Elke pagina heeft een unieke titel en meta-omschrijving',
        'headings'   => 'Duidelijke koppen (H1/H2) met je zoekwoord',
        'alt'        => 'Alt-teksten bij alle afbeeldingen',
        'mobile'     => 'Website werkt goed op mobiel',
        'speed'      => 'Snelle laadtijd (afbeeldingen geoptimaliseerd)',
        'gbp'        => 'Google Bedrijfsprofiel aangemaakt (Google Business Profile)',
        'gsc'        => 'Sitemap ingediend bij Google Search Console',
        'internal'   => 'Interne links tussen je pagina\'s',
        'nap'        => 'Naam, adres en telefoonnummer duidelijk vermeld',
        'reviews'    => 'Reviews verzamelen (Google / social media)',
        'content'    => 'Regelmatig nieuwe, relevante inhoud toevoegen',
    ];
}

/**
 * Haalt een URL op voor de SEO-scan.
 * Geeft ['ok'=>bool, 'html'=>string, 'http'=>int, 'final_url'=>string, 'error'=>string] terug.
 */
function seoFetchUrl(string $url): array {
    $out = ['ok' => false, 'html' => '', 'http' => 0, 'final_url' => $url, 'error' => ''];

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        $out['error'] = 'Ongeldige URL. Gebruik een volledig adres, bijv. https://jouwsite.nl';
        return $out;
    }
    if (!function_exists('curl_init')) {
        $out['error'] = 'De server kan geen websites ophalen (curl ontbreekt).';
        return $out;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WebsiteVoorJou-SEO/1.0)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING       => '',
    ]);
    $body  = curl_exec($ch);
    $out['http']      = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $out['final_url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    $err   = curl_error($ch);
    curl_close($ch);

    if ($body === false || $out['http'] === 0) {
        $out['error'] = 'De website kon niet worden geladen.' . ($err ? ' (' . $err . ')' : '');
        return $out;
    }
    if ($out['http'] >= 400) {
        $out['error'] = 'De website gaf een foutcode terug (HTTP ' . $out['http'] . ').';
        return $out;
    }
    $out['ok']   = true;
    $out['html'] = $body;
    return $out;
}

/**
 * Analyseert opgehaalde HTML op SEO-basis en geeft een score (0-100) + checks terug.
 * Elke check: ['label','status'(good|warn|bad),'detail','advice','weight'].
 */
function seoAnalyze(string $html, string $finalUrl, string $focusKeyword = ''): array {
    $checks = [];
    $add = function (string $label, string $status, string $detail, string $advice, int $weight) use (&$checks) {
        $checks[] = compact('label', 'status', 'detail', 'advice', 'weight');
    };

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $textOf = function (?DOMNode $n): string {
        return $n ? trim(preg_replace('/\s+/', ' ', $n->textContent)) : '';
    };

    // Title
    $titleNode = $dom->getElementsByTagName('title')->item(0);
    $title = $textOf($titleNode);
    $tlen  = mb_strlen($title);
    if ($title === '') {
        $add('Paginatitel (<title>)', 'bad', 'Geen titel gevonden.', 'Geef elke pagina een unieke, beschrijvende titel van 30–60 tekens.', 15);
    } elseif ($tlen < 15 || $tlen > 65) {
        $add('Paginatitel (<title>)', 'warn', '"' . $title . '" (' . $tlen . ' tekens).', 'Streef naar 30–60 tekens met je belangrijkste zoekwoord vooraan.', 15);
    } else {
        $add('Paginatitel (<title>)', 'good', '"' . $title . '" (' . $tlen . ' tekens).', '', 15);
    }

    // Meta description
    $descNode = $xp->query('//meta[translate(@name,"DESCRIPTION","description")="description"]')->item(0);
    $desc = $descNode ? trim($descNode->getAttribute('content')) : '';
    $dlen = mb_strlen($desc);
    if ($desc === '') {
        $add('Meta-omschrijving', 'bad', 'Geen meta-omschrijving gevonden.', 'Voeg een wervende samenvatting van 120–160 tekens toe; dit is de tekst onder je link in Google.', 15);
    } elseif ($dlen < 70 || $dlen > 165) {
        $add('Meta-omschrijving', 'warn', $dlen . ' tekens.', 'Streef naar 120–160 tekens, inclusief je zoekwoord en een uitnodiging om te klikken.', 15);
    } else {
        $add('Meta-omschrijving', 'good', $dlen . ' tekens.', '', 15);
    }

    // H1
    $h1 = $xp->query('//h1');
    if ($h1->length === 0) {
        $add('Hoofdkop (H1)', 'bad', 'Geen H1 gevonden.', 'Elke pagina hoort één duidelijke H1 te hebben die het onderwerp benoemt.', 12);
    } elseif ($h1->length > 1) {
        $add('Hoofdkop (H1)', 'warn', $h1->length . ' H1-koppen gevonden.', 'Gebruik bij voorkeur één H1 per pagina; de rest als H2/H3.', 12);
    } else {
        $add('Hoofdkop (H1)', 'good', '"' . $textOf($h1->item(0)) . '"', '', 12);
    }

    // Subkoppen
    $h2 = $xp->query('//h2');
    if ($h2->length === 0) {
        $add('Subkoppen (H2)', 'warn', 'Geen H2-koppen gevonden.', 'Verdeel je tekst met H2-koppen — prettig voor lezers én Google.', 8);
    } else {
        $add('Subkoppen (H2)', 'good', $h2->length . ' subkop(pen).', '', 8);
    }

    // Afbeeldingen + alt
    $imgs = $xp->query('//img');
    $missingAlt = 0;
    foreach ($imgs as $img) {
        if (trim($img->getAttribute('alt')) === '') $missingAlt++;
    }
    if ($imgs->length === 0) {
        $add('Alt-teksten bij afbeeldingen', 'warn', 'Geen afbeeldingen gevonden.', 'Afbeeldingen met goede alt-teksten helpen vindbaarheid en toegankelijkheid.', 10);
    } elseif ($missingAlt > 0) {
        $add('Alt-teksten bij afbeeldingen', 'bad', $missingAlt . ' van ' . $imgs->length . ' afbeelding(en) mist een alt-tekst.', 'Geef elke afbeelding een korte beschrijving (alt) van wat erop staat.', 10);
    } else {
        $add('Alt-teksten bij afbeeldingen', 'good', 'Alle ' . $imgs->length . ' afbeelding(en) hebben een alt-tekst.', '', 10);
    }

    // Viewport / mobielvriendelijk
    $viewport = $xp->query('//meta[@name="viewport"]')->item(0);
    if ($viewport) {
        $add('Mobielvriendelijk', 'good', 'Viewport-instelling aanwezig.', '', 8);
    } else {
        $add('Mobielvriendelijk', 'bad', 'Geen viewport-meta gevonden.', 'Voeg een viewport-meta toe zodat de site goed schaalt op mobiel.', 8);
    }

    // HTTPS
    if (stripos($finalUrl, 'https://') === 0) {
        $add('Beveiligde verbinding (HTTPS)', 'good', 'De site gebruikt HTTPS.', '', 7);
    } else {
        $add('Beveiligde verbinding (HTTPS)', 'bad', 'De site gebruikt geen HTTPS.', 'Stel een SSL-certificaat in; Google geeft beveiligde sites voorrang.', 7);
    }

    // Taal
    $lang = trim($dom->documentElement ? $dom->documentElement->getAttribute('lang') : '');
    if ($lang !== '') {
        $add('Taalinstelling', 'good', 'lang="' . $lang . '"', '', 5);
    } else {
        $add('Taalinstelling', 'warn', 'Geen taal ingesteld op <html>.', 'Stel de taal in (bijv. <html lang="nl">) voor betere indexering.', 5);
    }

    // Canonical
    $canonical = $xp->query('//link[@rel="canonical"]')->item(0);
    if ($canonical) {
        $add('Canonieke URL', 'good', 'Canonical-link aanwezig.', '', 5);
    } else {
        $add('Canonieke URL', 'warn', 'Geen canonical-link gevonden.', 'Een canonical-link voorkomt dubbele-inhoud problemen.', 5);
    }

    // Open Graph (social delen)
    $ogTitle = $xp->query('//meta[@property="og:title"]')->item(0);
    $ogDesc  = $xp->query('//meta[@property="og:description"]')->item(0);
    if ($ogTitle && $ogDesc) {
        $add('Social media (Open Graph)', 'good', 'Open Graph-tags aanwezig.', '', 5);
    } else {
        $add('Social media (Open Graph)', 'warn', 'Open Graph-tags ontbreken (deels).', 'Voeg og:title, og:description en og:image toe voor mooie previews bij delen.', 5);
    }

    // Focus-zoekwoord
    if (trim($focusKeyword) !== '') {
        $kw = mb_strtolower(trim($focusKeyword));
        $bodyText = mb_strtolower($textOf($dom->getElementsByTagName('body')->item(0)));
        $inTitle = mb_strpos(mb_strtolower($title), $kw) !== false;
        $inDesc  = mb_strpos(mb_strtolower($desc), $kw) !== false;
        $inH1    = $h1->length && mb_strpos(mb_strtolower($textOf($h1->item(0))), $kw) !== false;
        $inBody  = mb_strpos($bodyText, $kw) !== false;
        $hits = ($inTitle ? 1 : 0) + ($inDesc ? 1 : 0) + ($inH1 ? 1 : 0);
        if ($hits >= 2) {
            $add('Zoekwoord "' . $focusKeyword . '"', 'good', 'Komt voor in titel/omschrijving/kop.', '', 10);
        } elseif ($inBody || $hits >= 1) {
            $add('Zoekwoord "' . $focusKeyword . '"', 'warn', 'Komt beperkt voor.', 'Gebruik je zoekwoord in de titel, de H1 én de meta-omschrijving.', 10);
        } else {
            $add('Zoekwoord "' . $focusKeyword . '"', 'bad', 'Niet gevonden op de pagina.', 'Verwerk je zoekwoord op natuurlijke wijze in titel, koppen en tekst.', 10);
        }
    }

    // Score berekenen (good = vol, warn = half, bad = 0)
    $total = 0; $earned = 0;
    foreach ($checks as $c) {
        $total += $c['weight'];
        $earned += $c['status'] === 'good' ? $c['weight'] : ($c['status'] === 'warn' ? $c['weight'] / 2 : 0);
    }
    $score = $total > 0 ? (int)round($earned / $total * 100) : 0;

    return [
        'score'  => $score,
        'checks' => $checks,
        'meta'   => ['title' => $title, 'description' => $desc],
    ];
}

function sendMail(string $to, string $subject, string $htmlBody, string $toName = '', string $type = 'overig', string $replyTo = ''): bool {
    $host = MAIL_SMTP_HOST;
    $port = MAIL_SMTP_PORT;
    $user = MAIL_SMTP_USER;
    $pass = MAIL_SMTP_PASS;
    $from = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;

    $toHeader = $toName ? '"' . $toName . '" <' . $to . '>' : $to;

    $message  = "Date: " . date('r') . "\r\n";
    $message .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $message .= "To: {$toHeader}\r\n";
    if ($replyTo) $message .= "Reply-To: {$replyTo}\r\n";
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

        $success = strpos($resp, '250') !== false;
        logEmail($to, $toName, $subject, $type, $success ? 'verstuurd' : 'mislukt');
        return $success;

    } catch (\Throwable $e) {
        logEmail($to, $toName, $subject, $type, 'mislukt');
        return false;
    }
}

function sendVerificationEmail(string $email, string $name, string $token): void {
    $verifyUrl  = APP_URL . '/verify-email.php?token=' . $token;
    $firstName  = explode(' ', $name)[0];

    $html = '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:\'Inter\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:40px 0;">
<tr><td align="center">
<table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <tr>
    <td style="background:linear-gradient(135deg,#6C63FF 0%,#00D4FF 100%);padding:40px 48px;text-align:center;">
      <div style="font-size:2rem;font-weight:900;color:#ffffff;letter-spacing:-0.5px;">WebsiteVoorJou</div>
      <div style="color:rgba(255,255,255,0.8);margin-top:8px;font-size:0.95rem;">Jouw website, razendsnel live</div>
    </td>
  </tr>
  <tr>
    <td style="padding:48px;">
      <h2 style="margin:0 0 16px;color:#111827;font-size:1.5rem;font-weight:700;">Bevestig je e-mailadres</h2>
      <p style="margin:0 0 16px;color:#4b5563;line-height:1.7;font-size:0.95rem;">Hoi ' . htmlspecialchars($firstName) . ',</p>
      <p style="margin:0 0 28px;color:#4b5563;line-height:1.7;font-size:0.95rem;">Bedankt voor het aanmaken van je account bij WebsiteVoorJou! Klik op de knop hieronder om je e-mailadres te bevestigen en je account te activeren.</p>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center" style="padding:8px 0 36px;">
            <a href="' . $verifyUrl . '" style="display:inline-block;background:linear-gradient(135deg,#6C63FF,#00D4FF);color:#ffffff;text-decoration:none;padding:16px 48px;border-radius:10px;font-weight:700;font-size:1rem;letter-spacing:0.3px;">
              Bevestig mijn account &#8594;
            </a>
          </td>
        </tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:#f8f7ff;border:1px solid #e5e3ff;border-radius:10px;padding:20px 24px;">
            <p style="margin:0 0 8px;font-size:0.85rem;color:#6b7280;">Werkt de knop niet? Kopieer dan deze link:</p>
            <p style="margin:0;font-size:0.8rem;color:#6C63FF;word-break:break-all;">' . $verifyUrl . '</p>
          </td>
        </tr>
      </table>
      <p style="margin:28px 0 0;color:#9ca3af;font-size:0.82rem;line-height:1.6;">
        Deze link is 24 uur geldig. Heb jij geen account aangemaakt bij WebsiteVoorJou? Dan kun je deze e-mail veilig negeren.
      </p>
    </td>
  </tr>
  <tr>
    <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 48px;text-align:center;">
      <p style="margin:0;font-size:0.8rem;color:#9ca3af;">WebsiteVoorJou &bull; info@websitevoorjou.nl &bull; websitevoorjou.nl</p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body></html>';

    sendMail($email, 'Bevestig je account — WebsiteVoorJou', $html, $name, 'accountbevestiging');
}

function logEmail(string $to, string $toName, string $subject, string $type, string $status): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO email_logs (to_email, to_name, subject, type, status) VALUES (?, ?, ?, ?, ?)')
           ->execute([$to, $toName, $subject, $type, $status]);
    } catch (\Throwable $e) {
        // Logging mag nooit de hoofdflow breken
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
        CURLOPT_USERAGENT      => 'WebsiteVoorJou/1.0',
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
