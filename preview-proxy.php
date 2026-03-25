<?php
/**
 * Preview proxy — haalt de preview-URL op via curl en stuurt hem door
 * zonder X-Frame-Options zodat het iframe werkt.
 * Alleen toegankelijk met een geldig preview-token.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(403); exit('Geen toegang.'); }

$project = getProjectByToken($token);
if (!$project || empty($project['preview_url'])) {
    http_response_code(404); exit('Preview niet gevonden.');
}

$baseUrl   = $project['preview_url'];
$targetUrl = $baseUrl;

// Als er een specifieke sub-pagina gevraagd wordt, valideer dat het dezelfde host is
if (!empty($_GET['url'])) {
    $requested    = $_GET['url'];
    $allowedHost  = parse_url($baseUrl, PHP_URL_HOST);
    $requestedHost = parse_url($requested, PHP_URL_HOST);
    if ($requestedHost === $allowedHost) {
        $targetUrl = $requested;
    } else {
        // Extern domein — toon melding
        header('Content-Type: text/html; charset=UTF-8');
        exit(previewNotice());
    }
}

function previewNotice(): string {
    return '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8">
    <style>body{margin:0;display:flex;align-items:center;justify-content:center;
    min-height:100vh;font-family:sans-serif;background:#f5f5f7;text-align:center;}
    .box{background:#fff;border-radius:12px;padding:32px 40px;box-shadow:0 2px 16px rgba(0,0,0,0.08);max-width:400px;}
    p{color:#555;margin:8px 0 0;font-size:0.95rem;line-height:1.5;}
    button{margin-top:20px;padding:10px 24px;background:#6C63FF;color:#fff;border:none;
    border-radius:8px;font-size:0.9rem;font-weight:600;cursor:pointer;}
    </style></head><body>
    <div class="box">
      <strong style="font-size:1.1rem;color:#111;">Niet mogelijk in preview.</strong>
      <p>Om de hele website te bekijken kan je naar beneden scrollen.</p>
      <button onclick="history.back()">&#8592; Terug</button>
    </div>
    </body></html>';
}

// Debug checks
if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('Fout: PHP curl-extensie is niet beschikbaar op deze server.');
}
if (empty($targetUrl)) {
    http_response_code(500);
    exit('Fout: geen preview-URL ingesteld voor dit project.');
}
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(500);
    exit('Fout: ongeldige preview-URL: ' . htmlspecialchars($targetUrl));
}

// Haal de pagina op via curl
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_ENCODING       => '', // accepteer gzip/br automatisch
]);

$response    = curl_exec($ch);
$headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$finalUrl    = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curlError   = curl_error($ch);
curl_close($ch);

if (!$response || $httpCode === 0) {
    http_response_code(502);
    exit('Preview kon niet worden geladen. HTTP: ' . $httpCode . ' — Fout: ' . htmlspecialchars($curlError));
}

if ($httpCode >= 400) {
    header('Content-Type: text/html; charset=UTF-8');
    exit(previewNotice());
}

$body = substr($response, $headerSize);

// Verwijder HTML-commentaar (maakt source minder leesbaar)
$body = preg_replace('/<!--(?!\[if).*?-->/s', '', $body);

// Verwijder eventuele meta X-Frame-Options tags
$body = preg_replace('/<meta[^>]+http-equiv=["\']X-Frame-Options["\'][^>]*>/i', '', $body);

// Zorg dat relatieve URLs kloppen via een <base> tag
$baseTag = '<base href="' . htmlspecialchars($finalUrl, ENT_QUOTES) . '" target="_self">';

// Injecteer bescherming: blokkeer rechtermuisknop, sneltoetsen én route links via proxy
$proxyBase = '/preview-proxy.php?token=' . urlencode($token) . '&url=';
$protection = '<script>(function(){' .
    'var PROXY="' . addslashes($proxyBase) . '";' .
    // Onderschep alle link-kliks en route via proxy
    'document.addEventListener("click",function(e){' .
        'var a=e.target.closest("a");' .
        'if(!a)return;' .
        'e.preventDefault();' .
        'var h=a.getAttribute("href");' .
        'if(!h||h.startsWith("javascript:"))return;' .
        // Anchor-only links (#sectie) gewoon scrollen, niet door proxy
        'if(h.startsWith("#")){' .
            'var el=document.getElementById(h.slice(1))||document.querySelector("[name=\'"+h.slice(1)+"\']");' .
            'if(el)el.scrollIntoView({behavior:"smooth"});' .
            'return;' .
        '}' .
        'var abs=new URL(h,document.baseURI).href;' .
        // Als de URL een fragment heeft naar dezelfde pagina, alleen scrollen
        'var fragOnly=abs.split("#");' .
        'if(fragOnly.length>1&&fragOnly[0]===window.location.href.split("?")[0]){' .
            'var el2=document.getElementById(fragOnly[1]);' .
            'if(el2){el2.scrollIntoView({behavior:"smooth"});return;}' .
        '}' .
        'window.location.href=PROXY+encodeURIComponent(abs);' .
    '});' .
    // Blokkeer rechtermuisknop en sneltoetsen
    'document.addEventListener("contextmenu",function(e){e.preventDefault();});' .
    'document.addEventListener("selectstart",function(e){e.preventDefault();});' .
    'document.addEventListener("copy",function(e){e.preventDefault();});' .
    'document.addEventListener("keydown",function(e){' .
        'if(e.key==="F12"||' .
        '(e.ctrlKey&&["u","s","a","p","c","x","j","i"].indexOf(e.key.toLowerCase())>-1)||' .
        '(e.ctrlKey&&e.shiftKey&&["i","j","c","k"].indexOf(e.key.toLowerCase())>-1))' .
        '{e.preventDefault();e.stopPropagation();}' .
    '});' .
    '})();</script>';

// Voeg base + bescherming in na <head>
if (preg_match('/<head[^>]*>/i', $body)) {
    $body = preg_replace('/(<head[^>]*>)/i', '$1' . $baseTag . $protection, $body, 1);
} else {
    $body = $baseTag . $protection . $body;
}

// Stuur zonder X-Frame-Options
header_remove('X-Frame-Options');
header('Content-Security-Policy: ');
header('Cache-Control: no-store');
$ct = $contentType ?: 'text/html; charset=UTF-8';
header('Content-Type: ' . $ct);

echo $body;
