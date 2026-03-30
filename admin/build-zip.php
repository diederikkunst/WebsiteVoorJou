<?php
/**
 * Bouwt een ZIP van de preview-URL en slaat die op als beveiligde download.
 * Werkt voor statische HTML-sites. Dynamische PHP-sites worden als HTML opgeslagen.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrEmployee();
$db = getDB();

$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) { header('Location: /admin/projects.php'); exit; }

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project || empty($project['preview_url'])) {
    header('Location: /admin/project-detail.php?id=' . $projectId . '&zip_error=geen_url');
    exit;
}

if (!class_exists('ZipArchive')) {
    header('Location: /admin/project-detail.php?id=' . $projectId . '&zip_error=geen_ziparchive');
    exit;
}

set_time_limit(120);

$zipFilename = buildZipFromUrl($project['preview_url']);

if ($zipFilename) {
    // Verwijder oud bestand
    if (!empty($project['download_file'])) {
        $old = __DIR__ . '/../uploads/downloads/' . basename($project['download_file']);
        if (file_exists($old)) unlink($old);
    }
    $db->prepare('UPDATE projects SET download_file = ? WHERE id = ?')->execute([$zipFilename, $projectId]);
    header('Location: /admin/project-detail.php?id=' . $projectId . '&zip_ok=1');
} else {
    header('Location: /admin/project-detail.php?id=' . $projectId . '&zip_error=mislukt');
}
exit;

// -----------------------------------------------------------------------

function buildZipFromUrl(string $baseUrl): ?string
{
    $outDir = __DIR__ . '/../uploads/downloads/';
    if (!is_dir($outDir)) mkdir($outDir, 0755, true);

    $zipFilename = bin2hex(random_bytes(16)) . '.zip';
    $zipPath     = $outDir . $zipFilename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;

    $baseHost   = parse_url($baseUrl, PHP_URL_HOST);
    $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
    $fetched    = []; // absUrl => zipPath (of null = extern/mislukt)

    // ---- helpers -------------------------------------------------------

    $fetch = function (string $url): array|false {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; WebsiteVoorJou-Zipper/1.0)',
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body && $code < 400) ? ['body' => $body, 'type' => $type] : false;
    };

    $resolve = function (string $href, string $base) use ($baseScheme): string {
        $href = trim($href);
        if (preg_match('#^https?://#', $href))  return $href;
        if (str_starts_with($href, '//'))        return $baseScheme . ':' . $href;
        $p = parse_url($base);
        if (str_starts_with($href, '/'))         return $p['scheme'] . '://' . $p['host'] . $href;
        $basePath = preg_replace('#/[^/]*$#', '/', $p['scheme'] . '://' . $p['host'] . ($p['path'] ?? '/'));
        return $basePath . $href;
    };

    $isSameHost = fn(string $url): bool => parse_url($url, PHP_URL_HOST) === $baseHost;

    $urlToZipPath = function (string $url): string {
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '/', '/');
        if ($path === '' || str_ends_with($path, '/')) $path .= 'index.html';
        return $path;
    };

    $relPath = function (string $from, string $to): string {
        $a = explode('/', $from); array_pop($a);
        $b = explode('/', $to);
        while ($a && $b && $a[0] === $b[0]) { array_shift($a); array_shift($b); }
        return str_repeat('../', count($a)) . implode('/', $b);
    };

    // ---- download one asset and add to ZIP -----------------------------

    $downloadAsset = function (string $absUrl) use ($zip, $fetch, $isSameHost, $urlToZipPath, &$fetched): void {
        if (array_key_exists($absUrl, $fetched)) return;
        if (!$isSameHost($absUrl)) { $fetched[$absUrl] = null; return; }

        $result = $fetch($absUrl);
        if (!$result) { $fetched[$absUrl] = null; return; }

        $zipPath = $urlToZipPath($absUrl);
        $fetched[$absUrl] = $zipPath;
        $zip->addFromString($zipPath, $result['body']);
    };

    // ---- fetch main page -----------------------------------------------

    $mainResult = $fetch($baseUrl);
    if (!$mainResult) { $zip->close(); @unlink($zipPath); return null; }
    $html = $mainResult['body'];

    // ---- collect all asset URLs from HTML ------------------------------

    $assetUrls = [];

    // <link rel="stylesheet" href="..."> (beide attribuutvolgorden)
    preg_match_all('/<link\b[^>]*>/i', $html, $linkTags);
    foreach ($linkTags[0] as $tag) {
        if (preg_match('/rel=["\']stylesheet["\']/i', $tag) &&
            preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hm)) {
            $assetUrls[] = $resolve($hm[1], $baseUrl);
        }
    }

    // <script src="...">
    preg_match_all('/<script\b[^>]+src=["\']([^"\']+)["\']/i', $html, $jsM);
    foreach ($jsM[1] as $u) $assetUrls[] = $resolve($u, $baseUrl);

    // <img src="...">
    preg_match_all('/<img\b[^>]+src=["\']([^"\'#?][^"\']*)["\']]/i', $html, $imgM);
    foreach ($imgM[1] as $u) {
        if (!str_starts_with($u, 'data:')) $assetUrls[] = $resolve($u, $baseUrl);
    }

    // <link rel="icon/shortcut icon/...">
    preg_match_all('/<link\b[^>]*>/i', $html, $linkAll);
    foreach ($linkAll[0] as $tag) {
        if (preg_match('/rel=["\'][^"\']*icon[^"\']*["\']/i', $tag) &&
            preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hm)) {
            $assetUrls[] = $resolve($hm[1], $baseUrl);
        }
    }

    // ---- download assets -----------------------------------------------

    foreach ($assetUrls as $assetUrl) {
        if (array_key_exists($assetUrl, $fetched)) continue;
        if (!$isSameHost($assetUrl)) { $fetched[$assetUrl] = null; continue; }

        $result = $fetch($assetUrl);
        if (!$result) { $fetched[$assetUrl] = null; continue; }

        $localZipPath = $urlToZipPath($assetUrl);
        $fetched[$assetUrl] = $localZipPath;
        $body = $result['body'];

        // CSS: zoek url() verwijzingen en download sub-assets (fonts, images)
        $isCss = str_contains($result['type'], 'css')
               || str_ends_with(strtolower(parse_url($assetUrl, PHP_URL_PATH) ?? ''), '.css');

        if ($isCss) {
            preg_match_all('/url\(["\']?([^"\')\s]+)["\']?\)/i', $body, $cssUrls);
            foreach ($cssUrls[1] as $cu) {
                if (str_starts_with($cu, 'data:') || str_starts_with($cu, '#')) continue;
                $abs = $resolve($cu, $assetUrl);
                if (!$isSameHost($abs) || array_key_exists($abs, $fetched)) continue;
                $subResult = $fetch($abs);
                if ($subResult) {
                    $subZipPath = $urlToZipPath($abs);
                    $fetched[$abs] = $subZipPath;
                    $zip->addFromString($subZipPath, $subResult['body']);
                } else {
                    $fetched[$abs] = null;
                }
            }

            // Herschrijf url() paden in CSS naar relatief
            $body = preg_replace_callback('/url\(["\']?([^"\')\s]+)["\']?\)/i',
                function ($m) use ($fetched, $resolve, $assetUrl, $relPath, $localZipPath) {
                    $cu = $m[1];
                    if (str_starts_with($cu, 'data:') || str_starts_with($cu, '#')) return $m[0];
                    $abs = $resolve($cu, $assetUrl);
                    if (!isset($fetched[$abs])) return $m[0];
                    $rel = $relPath($localZipPath, $fetched[$abs]);
                    return 'url("' . $rel . '")';
                }, $body);
        }

        $zip->addFromString($localZipPath, $body);
    }

    // ---- herschrijf HTML paden -----------------------------------------

    $html = preg_replace_callback(
        '/(href|src)=["\']([^"\'#?][^"\']*)["\']/',
        function ($m) use ($fetched, $resolve, $baseUrl, $isSameHost) {
            $attr = $m[1];
            $url  = $m[2];
            if (preg_match('#^(javascript:|mailto:|tel:|data:)#i', $url)) return $m[0];
            $abs = $resolve($url, $baseUrl);
            if (!$isSameHost($abs))          return $m[0];
            if (empty($fetched[$abs]))       return $m[0];
            return $attr . '="' . $fetched[$abs] . '"';
        },
        $html
    );

    $zip->addFromString('index.html', $html);
    $zip->addFromString('LEESMIJ.txt',
        "Website: " . $baseHost . "\n" .
        "Gegenereerd: " . date('d-m-Y H:i') . "\n\n" .
        "Open index.html in je browser om de website lokaal te bekijken.\n" .
        "Let op: externe lettertypen en CDN-bestanden zijn niet inbegrepen.\n"
    );

    $zip->close();
    return file_exists($zipPath) && filesize($zipPath) > 1000 ? $zipFilename : null;
}
