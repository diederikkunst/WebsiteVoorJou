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
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

// Project ophalen + eigenaarschap controleren
$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
$stmt->execute([$projectId, $client['id']]);
$project = $stmt->fetch();
if (!$project) {
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

// SEO-record ophalen (of lege standaard)
function loadSeo(PDO $db, int $projectId): array {
    $s = $db->prepare('SELECT * FROM project_seo WHERE project_id = ?');
    $s->execute([$projectId]);
    $row = $s->fetch();
    if (!$row) {
        $row = [
            'focus_keyword' => '', 'extra_keywords' => '', 'meta_title' => '',
            'meta_description' => '', 'target_audience' => '', 'notes' => '',
            'checklist' => '', 'scan_score' => null, 'scan_results' => '',
            'scanned_url' => '', 'scanned_at' => null,
        ];
    }
    return $row;
}

function upsertSeo(PDO $db, int $projectId, array $fields): void {
    $exists = $db->prepare('SELECT id FROM project_seo WHERE project_id = ?');
    $exists->execute([$projectId]);
    if ($exists->fetch()) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $projectId;
        $db->prepare("UPDATE project_seo SET $set WHERE project_id = ?")->execute($params);
    } else {
        $cols = array_merge(['project_id'], array_keys($fields));
        $ph   = implode(', ', array_fill(0, count($cols), '?'));
        $params = array_merge([$projectId], array_values($fields));
        $db->prepare('INSERT INTO project_seo (' . implode(', ', $cols) . ") VALUES ($ph)")->execute($params);
    }
}

$seo     = loadSeo($db, $projectId);
$error   = '';
$success = '';

// Gedeelde klantcontext — vult bekende input voor (o.a. doelgroep uit Social)
$ctx = clientContext($db, $project, $client);
// Doelgroep voorvullen vanuit Social als die in SEO nog leeg is
$audiencePrefilled = false;
if (trim($seo['target_audience'] ?? '') === '' && $ctx['audience'] !== '') {
    $seo['target_audience'] = $ctx['audience'];
    $audiencePrefilled = true;
}

// Checklist-definitie (key => label) — gedeeld met de admin
$CHECKLIST = seoChecklistItems();

// --- POST: SEO-velden opslaan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo'])) {
    upsertSeo($db, $projectId, [
        'focus_keyword'    => trim($_POST['focus_keyword'] ?? ''),
        'extra_keywords'   => trim($_POST['extra_keywords'] ?? ''),
        'meta_title'       => trim($_POST['meta_title'] ?? ''),
        'meta_description' => trim($_POST['meta_description'] ?? ''),
        'target_audience'  => trim($_POST['target_audience'] ?? ''),
        'notes'            => trim($_POST['notes'] ?? ''),
    ]);
    $success = 'SEO-gegevens opgeslagen. Ons team ziet jouw input bij het project.';
    $seo = loadSeo($db, $projectId);
}

// --- POST: checklist opslaan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_checklist'])) {
    $checked = array_values(array_intersect(array_keys($CHECKLIST), $_POST['check'] ?? []));
    upsertSeo($db, $projectId, ['checklist' => json_encode($checked)]);
    $success = 'Checklist bijgewerkt.';
    $seo = loadSeo($db, $projectId);
}

// --- POST: website scannen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_scan'])) {
    $scanUrl = trim($_POST['scan_url'] ?? '');
    if ($scanUrl === '') {
        $error = 'Vul een website-adres in om te scannen.';
    } else {
        if (!preg_match('#^https?://#i', $scanUrl)) {
            $scanUrl = 'https://' . $scanUrl;
        }
        $fetch = seoFetchUrl($scanUrl);
        if (!$fetch['ok']) {
            $error = $fetch['error'];
        } else {
            $result = seoAnalyze($fetch['html'], $fetch['final_url'], $seo['focus_keyword'] ?? '');
            upsertSeo($db, $projectId, [
                'scan_score'   => $result['score'],
                'scan_results' => json_encode($result['checks']),
                'scanned_url'  => $fetch['final_url'],
                'scanned_at'   => date('Y-m-d H:i:s'),
            ]);
            $success = 'Scan voltooid. SEO-score: ' . $result['score'] . '/100.';
            $seo = loadSeo($db, $projectId);
        }
    }
}

// Projectbeschrijving (zonder interne go-live-markering) als analysebron
$projectDesc = trim(preg_replace('/\[GO-LIVE VERZOEK:[^\]]*\]/', '', $project['description'] ?? ''));

// --- POST: zoekwoorden-analyse op basis van de projectbeschrijving ---
$kwAnalysis = null; // ['source'=>'ai'|'lokaal', 'focus','extra'(csv),'meta_title','meta_description','audience','keywords'(arr),'phrases'(arr)]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze_keywords'])) {
    if ($projectDesc === '') {
        $error = 'Er is nog geen projectbeschrijving. Vul die eerst in bij je project.';
    } elseif (aiEnabled()) {
        $sys = 'Je bent een Nederlandse SEO-specialist. Antwoord UITSLUITEND met geldige JSON, zonder toelichting of codeblokken.';
        $usr = "Analyseer de volgende bedrijfs-/projectbeschrijving en stel SEO-zoekwoorden voor waarop dit bedrijf gevonden wil worden.\n"
             . 'Bedrijf: ' . ($ctx['company'] ?: '-') . "\n"
             . ($ctx['website'] ? 'Website: ' . $ctx['website'] . "\n" : '')
             . ($ctx['audience'] ? 'Bekende doelgroep: ' . $ctx['audience'] . "\n" : '')
             . "Beschrijving:\n" . $projectDesc . "\n\n"
             . 'Geef JSON met exact deze velden: {"focus_keyword": string, "extra_keywords": [5-8 strings], '
             . '"meta_title": string (max 60 tekens), "meta_description": string (120-160 tekens), "target_audience": string}. '
             . 'Gebruik natuurlijke, realistische zoektermen (incl. lokale/branchetermen waar passend).';
        $res = aiComplete($sys, $usr, 0.5);
        if (!$res['ok']) {
            $error = $res['error'];
        } else {
            $json = trim(preg_replace('/^```(?:json)?|```$/m', '', $res['text']));
            $data = json_decode($json, true);
            if (!is_array($data)) {
                $error = 'De AI-analyse kon niet worden gelezen. Probeer het opnieuw.';
            } else {
                $extra = $data['extra_keywords'] ?? [];
                if (is_array($extra)) $extra = implode(', ', array_map('strval', $extra));
                $kwAnalysis = [
                    'source'           => 'ai',
                    'focus'            => trim((string)($data['focus_keyword'] ?? '')),
                    'extra'            => trim((string)$extra),
                    'meta_title'       => trim((string)($data['meta_title'] ?? '')),
                    'meta_description' => trim((string)($data['meta_description'] ?? '')),
                    'audience'         => trim((string)($data['target_audience'] ?? '')),
                ];
            }
        }
    } else {
        // Geen AI: lokale woordanalyse
        $local = seoKeywordsFromText($projectDesc);
        $kwAnalysis = [
            'source'   => 'lokaal',
            'focus'    => $local['phrases'][0] ?? ($local['keywords'][0] ?? ''),
            'extra'    => implode(', ', array_slice(array_merge($local['phrases'], $local['keywords']), 0, 8)),
            'keywords' => $local['keywords'],
            'phrases'  => $local['phrases'],
        ];
    }
}

// --- POST: analyse-resultaat overnemen in de SEO-velden ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_keywords'])) {
    $fields = [
        'focus_keyword'  => trim($_POST['focus_keyword'] ?? ''),
        'extra_keywords' => trim($_POST['extra_keywords'] ?? ''),
    ];
    if (trim($_POST['meta_title'] ?? '') !== '')       $fields['meta_title'] = trim($_POST['meta_title']);
    if (trim($_POST['meta_description'] ?? '') !== '')  $fields['meta_description'] = trim($_POST['meta_description']);
    if (trim($_POST['target_audience'] ?? '') !== '')   $fields['target_audience'] = trim($_POST['target_audience']);
    upsertSeo($db, $projectId, $fields);
    $success = 'Zoekwoorden overgenomen in je SEO-velden.';
    $seo = loadSeo($db, $projectId);
}

$checkedItems  = $seo['checklist'] ? (json_decode($seo['checklist'], true) ?: []) : [];
$scanResults   = $seo['scan_results'] ? (json_decode($seo['scan_results'], true) ?: []) : [];
$defaultScanUrl = $project['preview_url'] ?: ($client['website'] ?? '');

$statusColor = ['good' => 'var(--success)', 'warn' => 'var(--warning)', 'bad' => 'var(--danger)'];
$statusIcon  = ['good' => '&#10003;', 'warn' => '&#33;', 'bad' => '&#10007;'];

// ---- SEO-code genereren uit de ingevulde gegevens + klantprofiel ----
$bizName = trim($client['name'] ?? '') ?: $project['name'];
$seoUrl  = $seo['scanned_url'] ?: ($project['preview_url'] ?: ($client['website'] ?? ''));

$genTitle = trim($seo['meta_title']) !== ''
    ? trim($seo['meta_title'])
    : trim(($seo['focus_keyword'] !== '' ? $seo['focus_keyword'] . ' | ' : '') . $bizName);
$genDesc = trim($seo['meta_description']);

// HTML <head>-snippet opbouwen (alleen regels met inhoud)
$headLines = [];
if ($genTitle !== '') {
    $headLines[] = '<title>' . htmlspecialchars($genTitle, ENT_QUOTES) . '</title>';
}
if ($genDesc !== '') {
    $headLines[] = '<meta name="description" content="' . htmlspecialchars($genDesc, ENT_QUOTES) . '">';
}
if ($genTitle !== '' || $genDesc !== '') {
    if ($genTitle !== '') $headLines[] = '<meta property="og:title" content="' . htmlspecialchars($genTitle, ENT_QUOTES) . '">';
    if ($genDesc !== '')  $headLines[] = '<meta property="og:description" content="' . htmlspecialchars($genDesc, ENT_QUOTES) . '">';
    $headLines[] = '<meta property="og:type" content="website">';
    $headLines[] = '<meta property="og:locale" content="nl_NL">';
    if ($seoUrl !== '') $headLines[] = '<meta property="og:url" content="' . htmlspecialchars($seoUrl, ENT_QUOTES) . '">';
    $headLines[] = '<meta name="twitter:card" content="summary_large_image">';
}
$headSnippet = implode("\n", $headLines);

// JSON-LD (LocalBusiness) opbouwen uit klantgegevens
$ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $bizName];
if ($genDesc !== '')                       $ld['description'] = $genDesc;
if ($seoUrl !== '')                        $ld['url'] = $seoUrl;
if (!empty(trim($client['phone'] ?? '')))  $ld['telephone'] = trim($client['phone']);
if (!empty(trim($client['email'] ?? '')))  $ld['email'] = trim($client['email']);
if (!empty(trim($client['address'] ?? ''))) {
    $ld['address'] = ['@type' => 'PostalAddress', 'streetAddress' => trim($client['address'])];
}
$jsonLdInner = json_encode($ld, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$jsonLd = '<script type="application/ld+json">' . "\n" . $jsonLdInner . "\n" . '</script>';

$hasGen = $headSnippet !== '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SEO — <?= htmlspecialchars($project['name']) ?> — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <style>
    .seo-score-ring { display:flex;align-items:center;gap:20px;flex-wrap:wrap; }
    .seo-score-num { font-size:2.6rem;font-weight:800;line-height:1; }
    .seo-bar { height:10px;border-radius:6px;background:var(--bg-2);overflow:hidden;margin-top:6px; }
    .seo-bar > span { display:block;height:100%;border-radius:6px; }
    .seo-check { display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border); }
    .seo-check:last-child { border-bottom:none; }
    .seo-check-icon { flex-shrink:0;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.8rem;font-weight:700; }
    .seo-check-advice { font-size:0.82rem;color:var(--text-muted);margin-top:3px; }
    .char-count { font-size:0.78rem;color:var(--text-muted); }
    .char-count.over { color:var(--danger); }
    .char-count.ok { color:var(--success); }
    .seo-checkitem { display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--border); }
    .seo-checkitem:last-child { border-bottom:none; }
    .seo-checkitem input { margin-top:3px;flex-shrink:0;width:18px;height:18px;cursor:pointer; }
    .btn-spinner { display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.45);border-top-color:#fff;border-radius:50%;animation:btnspin 0.6s linear infinite;vertical-align:-2px;margin-right:4px; }
    @keyframes btnspin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
<div class="app-layout">
  <aside class="sidebar">
    <div class="sidebar-brand">WebsiteVoorJou</div>
    <ul class="sidebar-nav">
      <li><a href="<?= BASE_PATH ?>/portal/dashboard.php"><span class="nav-icon">&#127968;</span> Dashboard</a></li>
      <li><a href="<?= BASE_PATH ?>/portal/new-project.php"><span class="nav-icon">&#43;</span> Nieuw project</a></li>
      <li><a href="<?= BASE_PATH ?>/portal/questions.php"><span class="nav-icon">&#10067;</span> Mijn vragen</a></li>
      <li><a href="<?= BASE_PATH ?>/portal/profile.php"><span class="nav-icon">&#128100;</span> Mijn profiel</a></li>
    </ul>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="sidebar-user-role">Klant</div>
        </div>
      </div>
      <a href="<?= BASE_PATH ?>/logout.php" class="btn btn-outline btn-sm w-full" style="margin-top:8px;">Uitloggen</a>
    </div>
  </aside>

  <main class="main-content">
    <?php if ($success): ?>
      <div class="alert alert-success" data-dismiss="5000">&#10003; <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-header">
      <a href="<?= BASE_PATH ?>/portal/project.php?id=<?= $projectId ?>" style="font-size:0.85rem;color:var(--text-muted);">&#8592; Terug naar project</a>
      <h1 style="margin-top:4px;">&#128269; SEO voor <?= htmlspecialchars($project['name']) ?></h1>
      <p style="color:var(--text-muted);font-size:0.92rem;margin-top:4px;max-width:680px;">
        SEO (zoekmachine-optimalisatie) zorgt dat je beter gevonden wordt in Google. Doorloop de gids,
        vul je zoekwoorden in en scan je website om te zien wat al goed gaat en wat beter kan.
      </p>
    </div>

    <!-- Uitleg / gids -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">&#128218; In het kort: zo werkt SEO</h3></div>
      <div class="grid-2" style="gap:16px;">
        <div>
          <strong>1. Kies je zoekwoorden</strong>
          <p style="font-size:0.88rem;color:var(--text-muted);margin-top:4px;">Bedenk waarop klanten zoeken (bijv. "kapper Utrecht"). Hieronder kun je ze invullen.</p>
        </div>
        <div>
          <strong>2. Verwerk ze op je site</strong>
          <p style="font-size:0.88rem;color:var(--text-muted);margin-top:4px;">Gebruik je zoekwoord in de titel, koppen en tekst — natuurlijk, niet geforceerd.</p>
        </div>
        <div>
          <strong>3. Maak Google blij</strong>
          <p style="font-size:0.88rem;color:var(--text-muted);margin-top:4px;">Snelle, mobielvriendelijke site met goede titels en meta-omschrijvingen scoort beter.</p>
        </div>
        <div>
          <strong>4. Blijf bouwen</strong>
          <p style="font-size:0.88rem;color:var(--text-muted);margin-top:4px;">Verzamel reviews, maak een Google Bedrijfsprofiel en voeg regelmatig inhoud toe.</p>
        </div>
      </div>
    </div>

    <!-- Zoekwoorden-analyse op basis van projectbeschrijving -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">&#128273; Zoekwoorden-analyse</h3></div>
      <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:14px;">
        Nog geen website om te scannen? Analyseer je <strong>projectbeschrijving</strong> voor zoekwoord-suggesties.
        <?= aiEnabled() ? 'De AI stelt zoekwoorden, een paginatitel en meta-omschrijving voor.' : 'We halen de meest relevante termen uit je tekst.' ?>
      </p>

      <?php if ($projectDesc === ''): ?>
        <div class="alert alert-info" style="margin:0;">
          &#8505; Er is nog geen projectbeschrijving. Vul die in bij <a href="<?= BASE_PATH ?>/portal/project.php?id=<?= $projectId ?>">je project</a> — dan kunnen we zoekwoorden voorstellen.
        </div>
      <?php else: ?>
        <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">
          <strong style="color:var(--text);">Projectbeschrijving:</strong><br>
          <?= nl2br(htmlspecialchars(mb_strimwidth($projectDesc, 0, 400, '…'))) ?>
        </div>
        <form method="post">
          <input type="hidden" name="analyze_keywords" value="1">
          <button type="submit" class="btn btn-primary js-loading-btn" data-loading="Bezig met analyseren…">&#128269; Analyseer zoekwoorden<?= aiEnabled() ? ' met AI' : '' ?></button>
        </form>
      <?php endif; ?>

      <?php if ($kwAnalysis): ?>
        <div class="divider"></div>
        <?php if ($kwAnalysis['source'] === 'ai'): ?>
          <form method="post">
            <input type="hidden" name="apply_keywords" value="1">
            <div class="form-group">
              <label class="form-label">Belangrijkste zoekwoord</label>
              <input type="text" name="focus_keyword" class="form-control" value="<?= htmlspecialchars($kwAnalysis['focus']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Extra zoekwoorden</label>
              <input type="text" name="extra_keywords" class="form-control" value="<?= htmlspecialchars($kwAnalysis['extra']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Voorgestelde paginatitel</label>
              <input type="text" name="meta_title" class="form-control" value="<?= htmlspecialchars($kwAnalysis['meta_title']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Voorgestelde meta-omschrijving</label>
              <textarea name="meta_description" class="form-control" rows="2"><?= htmlspecialchars($kwAnalysis['meta_description']) ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Doelgroep</label>
              <input type="text" name="target_audience" class="form-control" value="<?= htmlspecialchars($kwAnalysis['audience']) ?>">
            </div>
            <p style="font-size:0.82rem;color:var(--text-muted);margin:0 0 12px;">&#128161; Controleer en pas aan waar nodig — daarna overnemen in je SEO-velden.</p>
            <button type="submit" class="btn btn-primary btn-sm">&#10003; Overnemen in mijn SEO-velden</button>
          </form>
        <?php else: ?>
          <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;">Gevonden termen in je beschrijving (lokale analyse):</p>
          <?php if (!empty($kwAnalysis['phrases'])): ?>
            <div style="margin-bottom:10px;">
              <?php foreach ($kwAnalysis['phrases'] as $p): ?>
                <span class="badge" style="background:rgba(108,99,255,0.1);color:var(--primary);margin:0 4px 4px 0;display:inline-block;"><?= htmlspecialchars($p) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div style="margin-bottom:14px;">
            <?php foreach ($kwAnalysis['keywords'] as $k): ?>
              <span class="badge" style="background:var(--bg-2);border:1px solid var(--border);margin:0 4px 4px 0;display:inline-block;"><?= htmlspecialchars($k) ?></span>
            <?php endforeach; ?>
          </div>
          <form method="post">
            <input type="hidden" name="apply_keywords" value="1">
            <input type="hidden" name="focus_keyword" value="<?= htmlspecialchars($kwAnalysis['focus']) ?>">
            <input type="hidden" name="extra_keywords" value="<?= htmlspecialchars($kwAnalysis['extra']) ?>">
            <button type="submit" class="btn btn-primary btn-sm">&#10003; Overnemen als zoekwoorden</button>
          </form>
          <?php if (!aiEnabled()): ?>
            <p style="font-size:0.8rem;color:var(--text-muted);margin:10px 0 0;">&#128161; Met AI (OpenAI-sleutel) krijg je bovendien een voorgestelde paginatitel, meta-omschrijving en doelgroep.</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Website scan -->
    <div class="card" style="margin-bottom:24px;">
      <div class="card-header"><h3 class="card-title">&#128202; Website-scan</h3></div>
      <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:14px;">
        We halen je website op en controleren de SEO-basis. Vul het adres van je (preview-)website in.
      </p>
      <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:1;min-width:240px;margin-bottom:0;">
          <label class="form-label">Website-adres</label>
          <input type="text" name="scan_url" class="form-control" placeholder="https://jouwsite.nl"
                 value="<?= htmlspecialchars($_POST['scan_url'] ?? ($seo['scanned_url'] ?: $defaultScanUrl)) ?>">
        </div>
        <input type="hidden" name="run_scan" value="1">
        <button type="submit" class="btn btn-primary">&#128269; Scan uitvoeren</button>
      </form>

      <?php if ($seo['scanned_at']): ?>
        <div class="divider"></div>
        <div class="seo-score-ring" style="margin-bottom:18px;">
          <?php
            $score = (int)$seo['scan_score'];
            $scoreColor = $score >= 80 ? 'var(--success)' : ($score >= 50 ? 'var(--warning)' : 'var(--danger)');
          ?>
          <div>
            <div class="seo-score-num" style="color:<?= $scoreColor ?>;"><?= $score ?><span style="font-size:1rem;color:var(--text-muted);">/100</span></div>
            <div style="font-size:0.8rem;color:var(--text-muted);">SEO-score</div>
          </div>
          <div style="flex:1;min-width:200px;">
            <div class="seo-bar"><span style="width:<?= $score ?>%;background:<?= $scoreColor ?>;"></span></div>
            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
              Laatst gescand: <?= formatDateTime($seo['scanned_at']) ?> &middot;
              <a href="<?= htmlspecialchars($seo['scanned_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($seo['scanned_url']) ?></a>
            </div>
          </div>
        </div>

        <?php if (!empty($scanResults)): ?>
          <div>
            <?php foreach ($scanResults as $c):
              $st = $c['status'] ?? 'warn';
            ?>
              <div class="seo-check">
                <div class="seo-check-icon" style="background:<?= $statusColor[$st] ?? 'var(--warning)' ?>;"><?= $statusIcon[$st] ?? '!' ?></div>
                <div style="flex:1;">
                  <strong style="font-size:0.92rem;"><?= htmlspecialchars($c['label'] ?? '') ?></strong>
                  <div style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($c['detail'] ?? '') ?></div>
                  <?php if (!empty($c['advice'])): ?>
                    <div class="seo-check-advice">&#128161; <?= htmlspecialchars($c['advice']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-top:12px;">Nog geen scan uitgevoerd.</p>
      <?php endif; ?>
    </div>

    <div class="grid-2">
      <!-- SEO velden -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">&#9999;&#65039; Jouw zoekwoorden &amp; teksten</h3></div>
        <form method="post">
          <input type="hidden" name="save_seo" value="1">
          <div class="form-group">
            <label class="form-label">Belangrijkste zoekwoord</label>
            <input type="text" name="focus_keyword" class="form-control" placeholder="bijv. kapper Utrecht"
                   value="<?= htmlspecialchars($seo['focus_keyword']) ?>">
            <p class="form-hint">Waarop wil je het liefst gevonden worden?</p>
          </div>
          <div class="form-group">
            <label class="form-label">Extra zoekwoorden</label>
            <input type="text" name="extra_keywords" class="form-control" placeholder="dameskapper, kleuren, kapsalon centrum"
                   value="<?= htmlspecialchars($seo['extra_keywords']) ?>">
            <p class="form-hint">Komma-gescheiden. Aanvullende termen waarop klanten zoeken.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Doelgroep</label>
            <input type="text" name="target_audience" class="form-control" placeholder="Wie zijn je ideale klanten?"
                   value="<?= htmlspecialchars($seo['target_audience']) ?>">
            <?php if ($audiencePrefilled): ?>
              <p class="form-hint">&#128161; Overgenomen uit je Social-merkstem. Pas gerust aan.</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Paginatitel (meta title)</label>
            <input type="text" name="meta_title" id="meta_title" class="form-control" maxlength="70"
                   placeholder="Kapper Utrecht | Salon Naam" value="<?= htmlspecialchars($seo['meta_title']) ?>">
            <p class="form-hint"><span class="char-count" id="title_count">0</span> tekens — ideaal 30–60.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Meta-omschrijving</label>
            <textarea name="meta_description" id="meta_description" class="form-control" rows="3" maxlength="200"
                      placeholder="Korte, wervende samenvatting die in Google onder je link verschijnt."><?= htmlspecialchars($seo['meta_description']) ?></textarea>
            <p class="form-hint"><span class="char-count" id="desc_count">0</span> tekens — ideaal 120–160.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Notities voor ons team</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Wensen of vragen rond SEO?"><?= htmlspecialchars($seo['notes'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
        </form>
      </div>

      <!-- Checklist -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">&#9989; SEO-checklist</h3></div>
        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">
          Vink af wat al geregeld is. Je voortgang wordt bewaard.
        </p>
        <?php $done = count(array_intersect(array_keys($CHECKLIST), $checkedItems)); $totalItems = count($CHECKLIST); ?>
        <div class="seo-bar" style="margin-bottom:4px;"><span style="width:<?= $totalItems ? round($done / $totalItems * 100) : 0 ?>%;background:var(--success);"></span></div>
        <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:12px;"><?= $done ?> van <?= $totalItems ?> afgerond</p>
        <form method="post">
          <input type="hidden" name="save_checklist" value="1">
          <?php foreach ($CHECKLIST as $key => $label): ?>
            <label class="seo-checkitem">
              <input type="checkbox" name="check[]" value="<?= $key ?>" <?= in_array($key, $checkedItems, true) ? 'checked' : '' ?>>
              <span style="font-size:0.9rem;"><?= htmlspecialchars($label) ?></span>
            </label>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:14px;">Checklist opslaan</button>
        </form>
      </div>
    </div>

    <!-- SEO-code generator -->
    <div class="card" style="margin-top:24px;">
      <div class="card-header"><h3 class="card-title">&#9889; Jouw kant-en-klare SEO-code</h3></div>
      <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:16px;">
        Op basis van wat je hierboven invult, genereren we automatisch de code voor je website.
        Vraag ons gerust om deze toe te passen — of plak hem zelf in de <code>&lt;head&gt;</code> van je site.
      </p>

      <?php if (!$hasGen): ?>
        <div class="alert alert-info" style="margin:0;">
          &#8505; Vul hierboven minstens een <strong>paginatitel</strong> of <strong>meta-omschrijving</strong> in (en sla op) om je SEO-code te genereren.
        </div>
      <?php else: ?>
        <div style="margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <strong style="font-size:0.92rem;">Meta-tags &amp; social media (in de &lt;head&gt;)</strong>
            <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars($headSnippet, ENT_QUOTES) ?>">Kopieer</button>
          </div>
          <pre style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;overflow:auto;font-size:0.82rem;line-height:1.5;margin:0;"><code><?= htmlspecialchars($headSnippet) ?></code></pre>
        </div>

        <div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <strong style="font-size:0.92rem;">Bedrijfsgegevens voor Google (structured data)</strong>
            <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars($jsonLd, ENT_QUOTES) ?>">Kopieer</button>
          </div>
          <p style="font-size:0.82rem;color:var(--text-muted);margin:0 0 6px;">
            Vergroot de kans op een uitgebreid resultaat in Google (met adres, telefoon e.d.).
            <?php if (empty(trim($client['address'] ?? '')) || empty(trim($client['phone'] ?? ''))): ?>
              <br>&#128161; Vul je <a href="<?= BASE_PATH ?>/portal/profile.php">adres en telefoonnummer</a> aan voor een completer resultaat.
            <?php endif; ?>
          </p>
          <pre style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;overflow:auto;font-size:0.82rem;line-height:1.5;margin:0;"><code><?= htmlspecialchars($jsonLd) ?></code></pre>
        </div>
      <?php endif; ?>
    </div>

  </main>
</div>
<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script>
  function bindCount(inputId, countId, min, max) {
    var el = document.getElementById(inputId), out = document.getElementById(countId);
    if (!el || !out) return;
    function upd() {
      var n = el.value.length;
      out.textContent = n;
      out.className = 'char-count' + (n > max ? ' over' : (n >= min && n <= max ? ' ok' : ''));
    }
    el.addEventListener('input', upd); upd();
  }
  bindCount('meta_title', 'title_count', 30, 60);
  bindCount('meta_description', 'desc_count', 120, 160);

  // Laadindicator op knoppen met .js-loading-btn (analyze_keywords zit in een verborgen veld,
  // dus de knop mag veilig worden uitgeschakeld).
  document.querySelectorAll('.js-loading-btn').forEach(function (btn) {
    var f = btn.closest('form');
    if (!f) return;
    f.addEventListener('submit', function () {
      btn.innerHTML = '<span class="btn-spinner"></span> ' + (btn.dataset.loading || 'Bezig…');
      btn.disabled = true;
    });
  });
</script>
</body>
</html>
