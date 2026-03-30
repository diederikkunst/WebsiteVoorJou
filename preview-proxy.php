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
    $requested     = $_GET['url'];
    $allowedHost   = parse_url($baseUrl, PHP_URL_HOST);
    $requestedHost = parse_url($requested, PHP_URL_HOST);
    if ($requestedHost === $allowedHost) {
        $targetUrl = $requested;
    } else {
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

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('Fout: PHP curl-extensie is niet beschikbaar op deze server.');
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
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_ENCODING       => '',
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

// Verwijder HTML-commentaar
$body = preg_replace('/<!--(?!\[if).*?-->/s', '', $body);

// Verwijder security meta-tags
$body = preg_replace('/<meta[^>]+http-equiv=["\']X-Frame-Options["\'][^>]*>/i', '', $body);
$body = preg_replace('/<meta[^>]+http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $body);
$body = preg_replace('/<meta[^>]+http-equiv=["\']X-Content-Type-Options["\'][^>]*>/i', '', $body);

// ---- Herschrijf alle <a href="..."> direct in PHP -----------------------
// Zo werken links ook als de site eigen JS gebruikt voor navigatie.

$proxyBase  = APP_URL . '/preview-proxy.php?token=' . urlencode($token) . '&url=';
$siteHost   = parse_url($finalUrl, PHP_URL_HOST);
$siteScheme = parse_url($finalUrl, PHP_URL_SCHEME) ?: 'https';
$sitePath   = parse_url($finalUrl, PHP_URL_PATH) ?? '/';

$body = preg_replace_callback('/<a(\s[^>]*)>/i', function ($m) use ($proxyBase, $finalUrl, $siteHost, $siteScheme, $sitePath) {
    $inner = $m[1];

    return preg_replace_callback(
        '/(\bhref=)(["\'])([^"\']*)\2/i',
        function ($hm) use ($proxyBase, $finalUrl, $siteHost, $siteScheme, $sitePath) {
            $eq    = $hm[1];
            $q     = $hm[2];
            $href  = $hm[3];

            // Laat speciale hrefs ongemoeid
            if ($href === '' || $href === '#') return $hm[0];
            if (preg_match('/^(javascript:|mailto:|tel:|data:)/i', $href)) return $hm[0];

            // Pure anchor (#sectie) → ongewijzigd laten, JS scrollt dit af
            if ($href[0] === '#') return $hm[0];

            // Maak absoluut
            if (preg_match('#^https?://#i', $href)) {
                $abs = $href;
            } elseif (str_starts_with($href, '//')) {
                $abs = $siteScheme . ':' . $href;
            } elseif ($href[0] === '/') {
                $abs = $siteScheme . '://' . $siteHost . $href;
            } else {
                // Relatief pad → los op t.o.v. huidige pagina
                $base = preg_replace('#/[^/]*$#', '/', $finalUrl);
                // Verwijder query/fragment van base
                $base = preg_replace('/[?#].*$/', '', $base);
                $abs  = rtrim($base, '/') . '/' . $href;
            }

            $absHost = parse_url($abs, PHP_URL_HOST);

            // Extern domein → ongemoeid (proxy-JS toont melding als erop geklikt wordt)
            if ($absHost !== $siteHost) return $hm[0];

            // Zelfde pagina + fragment → alleen fragment bewaren zodat browser scrollt
            $absPath = parse_url($abs, PHP_URL_PATH) ?? '/';
            $absFrag = parse_url($abs, PHP_URL_FRAGMENT);
            if ($absPath === $sitePath && $absFrag) {
                return $eq . $q . '#' . $absFrag . $q;
            }

            // Alle andere interne links → via proxy
            return $eq . $q . htmlspecialchars($proxyBase . urlencode($abs), ENT_QUOTES) . $q;
        },
        '<a' . $inner . '>'
    );
}, $body);

// ---- <base> tag + injecteer JS ------------------------------------------

$baseTag = '<base href="' . htmlspecialchars($finalUrl, ENT_QUOTES) . '" target="_self">';

$protection = '<script>(function(){' .
    'window.parent.postMessage("preview_loaded","*");' .
    'var PROXY="' . addslashes($proxyBase) . '";' .

    // MutationObserver: herschrijf hrefs in dynamisch geladen elementen (bijv. mobiel menu)
    'function fixLinks(root){' .
        '(root.querySelectorAll?root.querySelectorAll("a[href]"):[]).forEach(function(a){' .
            'var h=a.getAttribute("href");' .
            'if(!h||h==="#"||h.startsWith("#")||h.startsWith(PROXY)||/^(javascript:|mailto:|tel:|data:)/i.test(h))return;' .
            'try{' .
                'var abs=new URL(h,document.baseURI).href;' .
                'a.setAttribute("href",PROXY+encodeURIComponent(abs));' .
            '}catch(e){}' .
        '});' .
    '}' .
    'document.addEventListener("DOMContentLoaded",function(){fixLinks(document);});' .
    'new MutationObserver(function(ms){' .
        'ms.forEach(function(m){' .
            'm.addedNodes.forEach(function(n){if(n.nodeType===1){fixLinks(n);}});' .
        '});' .
    '}).observe(document.documentElement,{childList:true,subtree:true});' .

    // Klik-handler als fallback voor anchor-links (#sectie scrollen)
    'document.addEventListener("click",function(e){' .
        'var a=e.target.closest("a");if(!a)return;' .
        'var h=a.getAttribute("href");' .
        'if(!h||!h.startsWith("#")||h==="#")return;' .
        'e.preventDefault();' .
        'var el=document.getElementById(h.slice(1))||document.querySelector("[name=\'"+h.slice(1)+"\']");' .
        'if(el)el.scrollIntoView({behavior:"smooth"});' .
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
