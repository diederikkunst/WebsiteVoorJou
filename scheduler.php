<?php
/**
 * Scheduler voor ingeplande social media posts.
 *
 * Verwerkt alle posts waarvan het tijdstip bereikt is (status 'ingepland',
 * scheduled_at <= nu). Zonder platform-koppeling draait dit in veilige dry-run
 * (markeert als verwerkt, publiceert niets echt).
 *
 * Bedoeld om periodiek te draaien (bijv. elke 5 minuten):
 *   Windows Taakplanner : programma "php", argument "C:\Projects\WebSiteVoorJou\scheduler.php"
 *   cron                : *\/5 * * * * php /pad/scheduler.php >> /pad/storage/scheduler.log 2>&1
 *
 * Een lockbestand voorkomt dubbele verwerking bij overlappende runs.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dit script draait alleen via de command line.\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/social.php';

$stamp = date('Y-m-d H:i:s');

// ---- Lock ----------------------------------------------------------------
$lockDir = __DIR__ . '/storage';
if (!is_dir($lockDir)) @mkdir($lockDir, 0755, true);
$lockFile = $lockDir . '/scheduler.lock';
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[$stamp] Andere scheduler-run is bezig — overgeslagen.\n";
    exit(0);
}

// ---- Verwerken -----------------------------------------------------------
try {
    $db = getDB();
    $summary = socialProcessDuePosts($db);

    if ($summary['processed'] === 0) {
        echo "[$stamp] Geen posts due.\n";
    } else {
        echo "[$stamp] Verwerkt: {$summary['processed']} · gepubliceerd: {$summary['published']} · mislukt: {$summary['failed']}\n";
        foreach ($summary['lines'] as $line) {
            echo "  - $line\n";
        }
    }
} catch (Throwable $e) {
    echo "[$stamp] Fout: " . $e->getMessage() . "\n";
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
exit(0);
