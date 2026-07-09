<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/social.php';

requireLogin('/login.php');

$user   = currentUser();
$client = getClientForUser($user['id']);
$db     = getDB();

$projectId = (int)($_GET['id'] ?? 0);
if (!$client || !$projectId) {
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ?');
$stmt->execute([$projectId, $client['id']]);
$project = $stmt->fetch();
if (!$project) {
    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
    exit;
}

// Merkstem laden (of standaard afgeleid van project/SEO-gegevens)
function loadSocial(PDO $db, int $projectId): ?array {
    $s = $db->prepare('SELECT * FROM project_social WHERE project_id = ?');
    $s->execute([$projectId]);
    return $s->fetch() ?: null;
}
$social = loadSocial($db, $projectId);

// Gedeelde klantcontext (profiel + project + SEO + Social)
$ctx = clientContext($db, $project, $client);

// Merkstem: eigen ingevulde waarden, met de bekende klant-input als voorvulling.
// "Wat doen we" valt terug op de projectomschrijving die de klant bij dit project gaf.
$businessPrefilled = trim($social['business'] ?? '') === '' && $ctx['project_desc'] !== '';
$brand = [
    'business' => trim($social['business'] ?? '') ?: $ctx['project_desc'],
    'audience' => trim($social['audience'] ?? '') ?: $ctx['audience'],
    'voice'    => $social['voice'] ?? '',
    'pillars'  => $social['pillars'] ?? '',
    'example'  => $social['example_post'] ?? '',
    // Uit andere bronnen — gaan mee in de AI-prompt maar staan niet in het formulier
    'website'  => $ctx['website'],
    'keywords' => $ctx['keywords'],
];

$companyName = $ctx['company'];
$platforms   = socialPlatforms();
$goals       = socialGoals();

$error = $success = '';
$activeTab = $_POST['tab'] ?? 'post';
$genPrompt = '';     // gegenereerde prompt (copy-paste)
$genResult = null;   // ['ok'=>bool,'text'=>...,'error'=>...] bij live AI
$genMultiPosts = null; // [['platform'=>key,'label'=>..,'content'=>..], ...] bij "1 idee → meerdere posts"

// --- Merkstem opslaan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand'])) {
    $fields = [
        'business'     => trim($_POST['business'] ?? ''),
        'audience'     => trim($_POST['audience'] ?? ''),
        'voice'        => trim($_POST['voice'] ?? ''),
        'pillars'      => trim($_POST['pillars'] ?? ''),
        'example_post' => trim($_POST['example_post'] ?? ''),
    ];
    $exists = $db->prepare('SELECT id FROM project_social WHERE project_id = ?');
    $exists->execute([$projectId]);
    if ($exists->fetch()) {
        $db->prepare('UPDATE project_social SET business = ?, audience = ?, voice = ?, pillars = ?, example_post = ? WHERE project_id = ?')
           ->execute([$fields['business'], $fields['audience'], $fields['voice'], $fields['pillars'], $fields['example_post'], $projectId]);
    } else {
        $db->prepare('INSERT INTO project_social (project_id, business, audience, voice, pillars, example_post) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$projectId, $fields['business'], $fields['audience'], $fields['voice'], $fields['pillars'], $fields['example_post']]);
    }
    $success = 'Merkstem opgeslagen — je posts worden hier nu op afgestemd.';
    $social = loadSocial($db, $projectId);
    $brand = array_merge($brand, [
        'business' => $fields['business'],
        'audience' => $fields['audience'] !== '' ? $fields['audience'] : $ctx['audience'],
        'voice'    => $fields['voice'],
        'pillars'  => $fields['pillars'],
        'example'  => $fields['example_post'],
    ]);
    $activeTab = 'merkstem';
}

// --- Generatie-acties ---
$systemPrompt = socialSystemPrompt($companyName, $brand);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_post'])) {
    $activeTab = 'post';
    $pk        = $_POST['platform'] ?? 'linkedin';
    $onderwerp = trim($_POST['onderwerp'] ?? '');
    $doel      = $_POST['doel'] ?? 'engagement';
    $cta       = trim($_POST['cta'] ?? 'reageren') ?: 'reageren';
    if (!isset($platforms[$pk])) $pk = 'linkedin';
    // Leeg onderwerp → val terug op de projectbeschrijving (of het bedrijfsprofiel)
    if ($onderwerp === '') {
        $onderwerp = $ctx['project_desc'] ?: $brand['business'];
    }
    if (trim($onderwerp) === '') {
        $error = 'Vul een onderwerp in, of vul eerst de projectbeschrijving / merkstem in.';
    } else {
        $genPrompt = socialPromptPlatformPost($platforms[$pk], $onderwerp, $goals[$doel] ?? $doel, $cta);
        if (isset($_POST['use_ai']) && socialAiEnabled()) {
            $genResult = socialGenerate($systemPrompt, $genPrompt);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_multiply'])) {
    $activeTab = 'multiply';
    $idee = trim($_POST['idee'] ?? '');
    $keys = array_values(array_intersect(array_keys($platforms), $_POST['platforms'] ?? []));
    // Leeg idee → val terug op de projectbeschrijving (of het bedrijfsprofiel)
    if ($idee === '') {
        $idee = $ctx['project_desc'] ?: $brand['business'];
    }
    if (trim($idee) === '') {
        $error = 'Vul een idee in, of vul eerst de projectbeschrijving / merkstem in.';
    } elseif (empty($keys)) {
        $error = 'Kies minstens één platform.';
    } else {
        $genPrompt = socialPromptMultiply($idee, $keys);
        if (isset($_POST['use_ai']) && socialAiEnabled()) {
            // Vraag gestructureerde JSON zodat elke post apart opgeslagen kan worden
            $allowed = implode(', ', $keys);
            $jsonPrompt = "Idee/inzicht:\n{$idee}\n\n"
                . "Maak per onderstaand platform één publicatieklare post (in onze tone of voice, binnen de tekenlimiet, elk uniek).\n"
                . "Platforms (gebruik exact deze sleutels): {$allowed}\n\n"
                . 'Antwoord UITSLUITEND met geldige JSON in dit formaat, zonder toelichting of codeblokken: '
                . '{"posts":[{"platform":"<sleutel>","content":"<de posttekst>"}]}';
            $res = aiComplete($systemPrompt, $jsonPrompt, 0.8);
            if (!$res['ok']) {
                $genResult = $res; // toon foutmelding
            } else {
                $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $res['text']));
                $data  = json_decode($clean, true);
                $posts = is_array($data) ? ($data['posts'] ?? $data) : null;
                if (is_array($posts)) {
                    $genMultiPosts = [];
                    foreach ($posts as $p) {
                        $pk = strtolower(trim((string)($p['platform'] ?? '')));
                        $content = trim((string)($p['content'] ?? ''));
                        if (!isset($platforms[$pk])) {
                            // probeer op label te matchen
                            foreach ($platforms as $k => $meta) {
                                if (strcasecmp($meta['label'], (string)($p['platform'] ?? '')) === 0) { $pk = $k; break; }
                            }
                        }
                        if (isset($platforms[$pk]) && $content !== '') {
                            $genMultiPosts[] = ['platform' => $pk, 'label' => $platforms[$pk]['label'], 'content' => $content];
                        }
                    }
                    if (empty($genMultiPosts)) { $genMultiPosts = null; $genResult = $res; } // val terug op ruwe tekst
                } else {
                    $genResult = $res; // niet-parsebaar → toon ruwe tekst
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_week'])) {
    $activeTab = 'week';
    $datum = trim($_POST['datum'] ?? '');
    $thema = trim($_POST['thema'] ?? '');
    $even  = trim($_POST['evenementen'] ?? '');
    $freq  = [];
    foreach (($_POST['freq'] ?? []) as $k => $v) {
        if (isset($platforms[$k]) && (int)$v > 0) $freq[$k] = (int)$v;
    }
    if ($datum === '') {
        $error = 'Kies een startdatum voor de week.';
    } elseif (empty($freq)) {
        $error = 'Geef voor minstens één platform een aantal posts per week op.';
    } else {
        $genPrompt = socialPromptWeek($datum, $freq, $thema, $even);
        if (isset($_POST['use_ai']) && socialAiEnabled()) {
            $genResult = socialGenerate($systemPrompt, $genPrompt);
        }
    }
}

// --- Post opslaan / inplannen / beheren ---
function ownPost(PDO $db, int $projectId, int $postId): ?array {
    $s = $db->prepare('SELECT * FROM project_social_posts WHERE id = ? AND project_id = ?');
    $s->execute([$postId, $projectId]);
    return $s->fetch() ?: null;
}

// Meerdere gegenereerde posts in één keer opslaan als concept
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_posts'])) {
    $activeTab = 'posts';
    $pls  = $_POST['ml_platform'] ?? [];
    $cnts = $_POST['ml_content'] ?? [];
    $saved = 0;
    $stmt = $db->prepare('INSERT INTO project_social_posts (project_id, platform, content, status, created_by) VALUES (?, ?, ?, \'concept\', ?)');
    for ($i = 0; $i < count($pls); $i++) {
        $pk = $pls[$i] ?? '';
        $content = trim($cnts[$i] ?? '');
        if (isset($platforms[$pk]) && $content !== '') {
            $stmt->execute([$projectId, $pk, $content, $user['id']]);
            $saved++;
        }
    }
    $success = $saved > 0 ? "$saved post(s) opgeslagen als concept." : 'Geen posts opgeslagen.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
    $activeTab = 'posts';
    $pk        = $_POST['post_platform'] ?? '';
    $content   = trim($_POST['post_content'] ?? '');
    $imageUrl  = trim($_POST['post_image'] ?? '');
    $schedRaw  = trim($_POST['post_schedule'] ?? '');
    if (!isset($platforms[$pk])) {
        $error = 'Kies een geldig platform.';
    } elseif ($content === '') {
        $error = 'De post mag niet leeg zijn.';
    } elseif ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $error = 'De afbeelding-URL is ongeldig.';
    } elseif (socialRequiresImage($pk) && $imageUrl === '') {
        $error = 'Instagram vereist een afbeelding-URL bij de post.';
    } else {
        $schedSql = null; $status = 'concept';
        if ($schedRaw !== '') {
            $ts = strtotime($schedRaw);
            if ($ts === false) { $error = 'Ongeldige datum/tijd.'; }
            else { $schedSql = date('Y-m-d H:i:s', $ts); $status = 'ingepland'; }
        }
        if (!$error) {
            $db->prepare('INSERT INTO project_social_posts (project_id, platform, content, image_url, status, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$projectId, $pk, $content, $imageUrl, $status, $schedSql, $user['id']]);
            $success = $status === 'ingepland' ? 'Post ingepland.' : 'Post opgeslagen als concept.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_post'])) {
    $activeTab = 'posts';
    $post = ownPost($db, $projectId, (int)($_POST['post_id'] ?? 0));
    $schedRaw = trim($_POST['post_schedule'] ?? '');
    if (!$post) { $error = 'Post niet gevonden.'; }
    elseif ($schedRaw === '' || ($ts = strtotime($schedRaw)) === false) { $error = 'Kies een geldige datum/tijd.'; }
    else {
        $db->prepare("UPDATE project_social_posts SET scheduled_at = ?, status = 'ingepland', result_msg = '' WHERE id = ?")
           ->execute([date('Y-m-d H:i:s', $ts), $post['id']]);
        $success = 'Post ingepland.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_schedule'])) {
    $activeTab = 'posts';
    $post = ownPost($db, $projectId, (int)($_POST['post_id'] ?? 0));
    if ($post) {
        $db->prepare("UPDATE project_social_posts SET status = 'concept', scheduled_at = NULL WHERE id = ?")->execute([$post['id']]);
        $success = 'Planning geannuleerd — terug naar concept.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_post'])) {
    $activeTab = 'posts';
    $post = ownPost($db, $projectId, (int)($_POST['post_id'] ?? 0));
    if (!$post) { $error = 'Post niet gevonden.'; }
    else {
        $res = socialPublishPost($db, $post);
        if ($res['ok']) { $success = $res['message']; } else { $error = $res['message']; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $activeTab = 'posts';
    $post = ownPost($db, $projectId, (int)($_POST['post_id'] ?? 0));
    if ($post) {
        $db->prepare('DELETE FROM project_social_posts WHERE id = ?')->execute([$post['id']]);
        $success = 'Post verwijderd.';
    }
}

// Opgeslagen posts laden
$postsStmt = $db->prepare("SELECT * FROM project_social_posts WHERE project_id = ? ORDER BY (status = 'ingepland') DESC, COALESCE(scheduled_at, created_at) DESC");
$postsStmt->execute([$projectId]);
$savedPosts = $postsStmt->fetchAll();

$aiOn = socialAiEnabled();

// Helper voor tab-knoppen
function tabClass(string $tab, string $active): string {
    return 'social-tab' . ($tab === $active ? ' active' : '');
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Social posts — <?= htmlspecialchars($project['name']) ?> — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <style>
    .social-tabs { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:0; }
    .social-tab { padding:10px 16px;border:none;background:none;cursor:pointer;font-size:0.92rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid transparent;font-family:inherit; }
    .social-tab.active { color:var(--primary);border-bottom-color:var(--primary); }
    .social-panel { display:none; }
    .social-panel.active { display:block; }
    .social-result { background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;white-space:pre-wrap;font-size:0.9rem;line-height:1.6; }
    .social-prompt { background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:14px;white-space:pre-wrap;font-size:0.82rem;line-height:1.5;margin:0; }
    .platform-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px; }
    .platform-pick { display:flex;gap:8px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:0.9rem; }
    .freq-row { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--border); }
    .freq-row input { width:64px; }
    .btn.is-loading { opacity:0.85; cursor:progress; }
    button:disabled { cursor:not-allowed; }
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
      <h1 style="margin-top:4px;">&#128241; Social media posts</h1>
      <p style="color:var(--text-muted);font-size:0.92rem;margin-top:4px;max-width:700px;">
        Maak posts op maat voor elk platform. Stel eerst je merkstem in, kies dan een functie hieronder.
        <?php if ($aiOn): ?>
          Klik op <strong>Genereren met AI</strong> voor een kant-en-klare post, of kopieer de prompt naar je eigen AI.
        <?php else: ?>
          Je krijgt een kant-en-klare prompt die je in ChatGPT of Claude kunt plakken.
        <?php endif; ?>
      </p>
    </div>

    <?php if (!$aiOn): ?>
      <div class="alert alert-info" style="margin-bottom:20px;">
        &#128161; Tip: live AI-generatie staat nog uit. Zodra er een API-sleutel is ingesteld, verschijnt hier een knop om posts direct te laten schrijven. Tot die tijd kopieer je de prompt naar ChatGPT of Claude.
      </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="social-tabs">
      <button type="button" class="<?= tabClass('post', $activeTab) ?>" data-tab="post">Post per platform</button>
      <button type="button" class="<?= tabClass('multiply', $activeTab) ?>" data-tab="multiply">1 idee &rarr; meerdere posts</button>
      <button type="button" class="<?= tabClass('week', $activeTab) ?>" data-tab="week">Weekplanning</button>
      <button type="button" class="<?= tabClass('posts', $activeTab) ?>" data-tab="posts">Mijn posts<?= $savedPosts ? ' (' . count($savedPosts) . ')' : '' ?></button>
      <button type="button" class="<?= tabClass('cheat', $activeTab) ?>" data-tab="cheat">Spiekbrief</button>
      <button type="button" class="<?= tabClass('merkstem', $activeTab) ?>" data-tab="merkstem">Merkstem</button>
    </div>

    <?php
    // Herbruikbaar resultaatblok
    $renderResult = function () use ($genPrompt, $genResult, $platforms) {
        if ($genResult !== null) {
            if ($genResult['ok']) {
                echo '<div class="card" style="margin-top:20px;border-color:var(--primary);">';
                echo '<div class="card-header"><h3 class="card-title">&#10003; Gegenereerde post</h3>';
                echo '<button type="button" class="btn btn-sm btn-outline" data-copy="' . htmlspecialchars($genResult['text'], ENT_QUOTES) . '">Kopieer</button></div>';
                echo '<div class="social-result">' . htmlspecialchars($genResult['text']) . '</div>';

                // Opslaan / inplannen
                $selPlatform = $_POST['platform'] ?? 'linkedin';
                echo '<form method="post" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">';
                echo '<input type="hidden" name="tab" value="posts">';
                echo '<input type="hidden" name="post_content" value="' . htmlspecialchars($genResult['text'], ENT_QUOTES) . '">';
                echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">';
                echo '<div class="form-group" style="margin-bottom:0;"><label class="form-label">Platform</label><select name="post_platform" class="form-control">';
                foreach ($platforms as $k => $p) {
                    echo '<option value="' . $k . '"' . ($k === $selPlatform ? ' selected' : '') . '>' . htmlspecialchars($p['label']) . '</option>';
                }
                echo '</select></div>';
                echo '<div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;"><label class="form-label">Afbeelding-URL (verplicht voor Instagram)</label><input type="url" name="post_image" class="form-control" placeholder="https://..."></div>';
                echo '<div class="form-group" style="margin-bottom:0;"><label class="form-label">Inplannen (optioneel)</label><input type="datetime-local" name="post_schedule" class="form-control"></div>';
                echo '<button type="submit" name="save_post" value="1" class="btn btn-primary">&#128190; Opslaan / inplannen</button>';
                echo '</div>';
                echo '<p style="font-size:0.8rem;color:var(--text-muted);margin:8px 0 0;">Laat de datum leeg om als concept te bewaren. Beheer je posts in het tabblad <strong>Mijn posts</strong>.</p>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-danger" style="margin-top:20px;">&#10007; ' . htmlspecialchars($genResult['error']) . '</div>';
            }
        }
        if ($genPrompt !== '') {
            echo '<div class="card" style="margin-top:20px;">';
            echo '<div class="card-header"><h3 class="card-title">&#128203; Prompt voor ChatGPT / Claude</h3>';
            echo '<button type="button" class="btn btn-sm btn-outline" data-copy="' . htmlspecialchars($genPrompt, ENT_QUOTES) . '">Kopieer prompt</button></div>';
            echo '<p style="font-size:0.83rem;color:var(--text-muted);margin:0 0 10px;">Plak deze prompt in ChatGPT of Claude. De merkstem zit erin verwerkt.</p>';
            echo '<pre class="social-prompt">' . htmlspecialchars($genPrompt) . '</pre>';
            echo '</div>';
        }
    };
    ?>

    <!-- Tab: Post per platform -->
    <div class="social-panel <?= $activeTab === 'post' ? 'active' : '' ?>" data-panel="post">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Post per platform</h3></div>
        <form method="post">
          <input type="hidden" name="tab" value="post">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Platform</label>
              <select name="platform" class="form-control">
                <?php foreach ($platforms as $k => $p): ?>
                  <option value="<?= $k ?>" <?= ($_POST['platform'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($p['label']) ?> (max <?= $p['max_chars'] ?> tekens)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Doel</label>
              <select name="doel" class="form-control">
                <?php foreach ($goals as $k => $lbl): ?>
                  <option value="<?= $k ?>" <?= ($_POST['doel'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Onderwerp <span style="font-weight:400;color:var(--text-muted);">(optioneel)</span></label>
            <input type="text" name="onderwerp" class="form-control" placeholder="Waar gaat de post over?" value="<?= htmlspecialchars($_POST['onderwerp'] ?? '') ?>">
            <p class="form-hint">Laat leeg om automatisch de projectbeschrijving te gebruiken.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Call-to-action</label>
            <input type="text" name="cta" class="form-control" placeholder="bijv. reageren, bekijk de website, neem contact op" value="<?= htmlspecialchars($_POST['cta'] ?? 'reageren') ?>">
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" name="gen_post" value="1" class="btn btn-outline">&#128203; Prompt maken</button>
            <?php if ($aiOn): ?>
              <button type="submit" name="gen_post" value="1" class="btn btn-primary" formnovalidate>&#9889; Genereren met AI</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <?php if ($activeTab === 'post') $renderResult(); ?>
    </div>

    <!-- Tab: 1 idee -> meerdere posts -->
    <div class="social-panel <?= $activeTab === 'multiply' ? 'active' : '' ?>" data-panel="multiply">
      <div class="card">
        <div class="card-header"><h3 class="card-title">1 idee &rarr; meerdere posts</h3></div>
        <form method="post">
          <input type="hidden" name="tab" value="multiply">
          <div class="form-group">
            <label class="form-label">Jouw idee, inzicht of resultaat <span style="font-weight:400;color:var(--text-muted);">(optioneel)</span></label>
            <textarea name="idee" class="form-control" rows="3" placeholder="bijv. Klant X bespaarde 5 uur per week dankzij onze nieuwe website."><?= htmlspecialchars($_POST['idee'] ?? '') ?></textarea>
            <p class="form-hint">Laat leeg om automatisch de projectbeschrijving te gebruiken.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Voor welke platforms?</label>
            <?php $pickedM = $_POST['platforms'] ?? ['linkedin','instagram','facebook','x']; ?>
            <div class="platform-grid">
              <?php foreach ($platforms as $k => $p): ?>
                <label class="platform-pick">
                  <input type="checkbox" name="platforms[]" value="<?= $k ?>" <?= in_array($k, $pickedM, true) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($p['label']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" name="gen_multiply" value="1" class="btn btn-outline">&#128203; Prompt maken</button>
            <?php if ($aiOn): ?>
              <button type="submit" name="gen_multiply" value="1" class="btn btn-primary" formnovalidate>&#9889; Genereren met AI</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <?php if ($activeTab === 'multiply' && $genMultiPosts): ?>
        <div class="card" style="margin-top:20px;border-color:var(--primary);">
          <div class="card-header">
            <h3 class="card-title">&#10003; <?= count($genMultiPosts) ?> gegenereerde posts</h3>
            <form method="post" style="margin:0;">
              <input type="hidden" name="tab" value="posts">
              <?php foreach ($genMultiPosts as $mp): ?>
                <input type="hidden" name="ml_platform[]" value="<?= htmlspecialchars($mp['platform']) ?>">
                <input type="hidden" name="ml_content[]" value="<?= htmlspecialchars($mp['content'], ENT_QUOTES) ?>">
              <?php endforeach; ?>
              <button type="submit" name="save_all_posts" value="1" class="btn btn-sm btn-primary">&#128190; Bewaar alle als concept</button>
            </form>
          </div>
          <?php foreach ($genMultiPosts as $mp): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:12px;">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px;">
                <strong><?= htmlspecialchars($mp['label']) ?></strong>
                <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars($mp['content'], ENT_QUOTES) ?>">Kopieer</button>
              </div>
              <div class="social-result" style="font-size:0.88rem;margin-bottom:10px;"><?= htmlspecialchars($mp['content']) ?></div>
              <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin:0;">
                <input type="hidden" name="tab" value="posts">
                <input type="hidden" name="post_platform" value="<?= htmlspecialchars($mp['platform']) ?>">
                <input type="hidden" name="post_content" value="<?= htmlspecialchars($mp['content'], ENT_QUOTES) ?>">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label">Inplannen (optioneel)</label>
                  <input type="datetime-local" name="post_schedule" class="form-control">
                </div>
                <?php if (socialRequiresImage($mp['platform'])): ?>
                  <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                    <label class="form-label">Afbeelding-URL (verplicht)</label>
                    <input type="url" name="post_image" class="form-control" placeholder="https://...">
                  </div>
                <?php endif; ?>
                <button type="submit" name="save_post" value="1" class="btn btn-sm btn-primary">&#128190; Opslaan</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($activeTab === 'multiply') $renderResult(); ?>
    </div>

    <!-- Tab: Weekplanning -->
    <div class="social-panel <?= $activeTab === 'week' ? 'active' : '' ?>" data-panel="week">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Weekplanning</h3></div>
        <form method="post">
          <input type="hidden" name="tab" value="week">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Startdatum (maandag)</label>
              <input type="date" name="datum" class="form-control" value="<?= htmlspecialchars($_POST['datum'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Thema van de week (optioneel)</label>
              <input type="text" name="thema" class="form-control" placeholder="bijv. productlancering" value="<?= htmlspecialchars($_POST['thema'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Posts per week per platform</label>
            <?php $freqIn = $_POST['freq'] ?? []; ?>
            <?php foreach ($platforms as $k => $p): ?>
              <div class="freq-row">
                <span><?= htmlspecialchars($p['label']) ?> <span style="font-size:0.78rem;color:var(--text-muted);">— <?= htmlspecialchars($p['best_times']) ?></span></span>
                <input type="number" name="freq[<?= $k ?>]" min="0" max="14" class="form-control" value="<?= htmlspecialchars((string)($freqIn[$k] ?? '0')) ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Evenementen of hooks (optioneel)</label>
            <input type="text" name="evenementen" class="form-control" placeholder="bijv. beurs, feestdag, actie" value="<?= htmlspecialchars($_POST['evenementen'] ?? '') ?>">
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" name="gen_week" value="1" class="btn btn-outline">&#128203; Prompt maken</button>
            <?php if ($aiOn): ?>
              <button type="submit" name="gen_week" value="1" class="btn btn-primary" formnovalidate>&#9889; Genereren met AI</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <?php if ($activeTab === 'week') $renderResult(); ?>
    </div>

    <!-- Tab: Mijn posts -->
    <div class="social-panel <?= $activeTab === 'posts' ? 'active' : '' ?>" data-panel="posts">
      <?php
        $enabledPub = array_filter(array_keys($platforms), fn($k) => socialPublisherEnabled($k));
        $statusBadge = [
            'concept'   => '<span class="badge" style="background:var(--bg-2);color:var(--text-muted);border:1px solid var(--border);">Concept</span>',
            'ingepland' => '<span class="badge" style="background:rgba(217,119,6,0.12);color:var(--warning);">Ingepland</span>',
            'geplaatst' => '<span class="badge" style="background:rgba(5,150,105,0.12);color:var(--success);">Geplaatst</span>',
            'mislukt'   => '<span class="badge" style="background:rgba(220,38,38,0.12);color:var(--danger);">Mislukt</span>',
        ];
      ?>
      <?php if (empty($enabledPub)): ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
          &#128274; Echt publiceren staat nog uit — "Nu plaatsen" en de scheduler draaien in <strong>dry-run</strong> (er gaat niets de deur uit). Zodra er een platform-koppeling is ingesteld, wordt er echt gepubliceerd.
        </div>
      <?php else: ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
          &#10003; Echt publiceren is actief voor: <strong><?= htmlspecialchars(implode(', ', array_map(fn($k) => $platforms[$k]['label'], $enabledPub))) ?></strong>. Andere platforms draaien in dry-run.
        </div>
      <?php endif; ?>

      <!-- Handmatig toevoegen -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3 class="card-title">Post toevoegen</h3></div>
        <form method="post">
          <input type="hidden" name="tab" value="posts">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Platform</label>
              <select name="post_platform" class="form-control">
                <?php foreach ($platforms as $k => $p): ?>
                  <option value="<?= $k ?>"><?= htmlspecialchars($p['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Inplannen (optioneel)</label>
              <input type="datetime-local" name="post_schedule" class="form-control">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tekst van de post</label>
            <textarea name="post_content" class="form-control" rows="4" placeholder="Plak hier je post-tekst..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Afbeelding-URL</label>
            <input type="url" name="post_image" class="form-control" placeholder="https://... (verplicht voor Instagram, optioneel voor Facebook)">
            <p class="form-hint">Instagram kan alleen posts met een afbeelding plaatsen.</p>
          </div>
          <button type="submit" name="save_post" value="1" class="btn btn-primary btn-sm">&#128190; Opslaan / inplannen</button>
        </form>
      </div>

      <!-- Lijst -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Opgeslagen posts</h3></div>
        <?php if (empty($savedPosts)): ?>
          <p class="text-muted">Nog geen posts opgeslagen. Genereer een post en klik op "Opslaan", of voeg er hierboven één toe.</p>
        <?php else: ?>
          <?php foreach ($savedPosts as $sp): $pl = $platforms[$sp['platform']]['label'] ?? $sp['platform']; ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:12px;">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                  <strong><?= htmlspecialchars($pl) ?></strong>
                  <?= $statusBadge[$sp['status']] ?? htmlspecialchars($sp['status']) ?>
                  <?php if ($sp['status'] === 'ingepland' && $sp['scheduled_at']): ?>
                    <span style="font-size:0.82rem;color:var(--text-muted);">&#128197; <?= formatDateTime($sp['scheduled_at']) ?></span>
                  <?php elseif ($sp['status'] === 'geplaatst' && $sp['posted_at']): ?>
                    <span style="font-size:0.82rem;color:var(--text-muted);">&#10003; <?= formatDateTime($sp['posted_at']) ?></span>
                  <?php endif; ?>
                </div>
                <span style="font-size:0.78rem;color:var(--text-muted);"><?= mb_strlen($sp['content']) ?> tekens</span>
              </div>
              <div class="social-result" style="font-size:0.85rem;margin-bottom:10px;"><?= htmlspecialchars($sp['content']) ?></div>
              <?php if (!empty($sp['image_url'])): ?>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                  <img src="<?= htmlspecialchars($sp['image_url']) ?>" alt="" style="height:48px;width:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);" onerror="this.style.display='none'">
                  <a href="<?= htmlspecialchars($sp['image_url']) ?>" target="_blank" rel="noopener" style="font-size:0.8rem;color:var(--text-muted);word-break:break-all;"><?= htmlspecialchars($sp['image_url']) ?></a>
                </div>
              <?php elseif (socialRequiresImage($sp['platform'])): ?>
                <p style="font-size:0.8rem;color:var(--warning);margin:0 0 10px;">&#9888; Instagram heeft een afbeelding nodig — voeg er één toe voor je publiceert.</p>
              <?php endif; ?>
              <?php if ($sp['result_msg']): ?>
                <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 10px;">&#8505; <?= htmlspecialchars($sp['result_msg']) ?></p>
              <?php endif; ?>
              <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                <?php if ($sp['status'] !== 'geplaatst'): ?>
                  <button type="button" class="btn btn-sm btn-outline" data-copy="<?= htmlspecialchars($sp['content'], ENT_QUOTES) ?>">Kopieer</button>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="tab" value="posts">
                    <input type="hidden" name="post_id" value="<?= $sp['id'] ?>">
                    <button type="submit" name="publish_post" value="1" class="btn btn-sm btn-primary"
                      data-confirm="<?= socialPublisherEnabled($sp['platform']) ? 'Nu echt publiceren naar ' . htmlspecialchars($pl) . '?' : 'Nu verwerken (dry-run, er wordt nog niets echt gepubliceerd)?' ?>">
                      &#9889; Nu plaatsen
                    </button>
                  </form>
                  <?php if ($sp['status'] === 'concept'): ?>
                    <form method="post" style="margin:0;display:flex;gap:6px;align-items:flex-end;">
                      <input type="hidden" name="tab" value="posts">
                      <input type="hidden" name="post_id" value="<?= $sp['id'] ?>">
                      <input type="datetime-local" name="post_schedule" class="form-control" style="width:auto;">
                      <button type="submit" name="schedule_post" value="1" class="btn btn-sm btn-outline">&#128197; Inplannen</button>
                    </form>
                  <?php elseif ($sp['status'] === 'ingepland'): ?>
                    <form method="post" style="margin:0;">
                      <input type="hidden" name="tab" value="posts">
                      <input type="hidden" name="post_id" value="<?= $sp['id'] ?>">
                      <button type="submit" name="cancel_schedule" value="1" class="btn btn-sm btn-outline">Annuleren</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="tab" value="posts">
                  <input type="hidden" name="post_id" value="<?= $sp['id'] ?>">
                  <button type="submit" name="delete_post" value="1" class="btn btn-sm btn-danger" data-confirm="Post verwijderen?">&#10005;</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tab: Spiekbrief -->
    <div class="social-panel <?= $activeTab === 'cheat' ? 'active' : '' ?>" data-panel="cheat">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Platform-spiekbrief</h3></div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Platform</th><th>Max tekens</th><th>Hashtags</th><th>Beste posttijden</th><th>Hoe het werkt</th></tr>
            </thead>
            <tbody>
              <?php foreach ($platforms as $p): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($p['label']) ?></strong></td>
                  <td><?= number_format($p['max_chars'], 0, ',', '.') ?></td>
                  <td><?= htmlspecialchars($p['hashtags']) ?></td>
                  <td><?= htmlspecialchars($p['best_times']) ?></td>
                  <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($p['notes']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Merkstem -->
    <div class="social-panel <?= $activeTab === 'merkstem' ? 'active' : '' ?>" data-panel="merkstem">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Merkstem &amp; bedrijfsinfo</h3></div>
        <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:16px;">
          Hoe beter je dit invult, hoe beter de posts bij jouw merk passen. Deze info gaat automatisch mee bij elke post.
        </p>
        <?php if ($ctx['company'] || $ctx['website'] || $ctx['keywords']): ?>
        <div class="alert alert-info" style="margin-bottom:16px;font-size:0.85rem;">
          &#128279; We gebruiken automatisch je bekende gegevens in elke post:
          <strong><?= htmlspecialchars($ctx['company']) ?></strong><?php
            if ($ctx['website']) echo ' &middot; ' . htmlspecialchars($ctx['website']);
            if ($ctx['keywords']) echo ' &middot; zoekwoorden uit SEO: ' . htmlspecialchars($ctx['keywords']);
          ?>. Je hoeft die dus niet opnieuw in te vullen.
        </div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="tab" value="merkstem">
          <input type="hidden" name="save_brand" value="1">
          <div class="form-group">
            <label class="form-label">Wat doet je bedrijf / wat bied je aan?</label>
            <textarea name="business" class="form-control" rows="2" placeholder="bijv. Wij maken betaalbare websites voor het MKB"><?= htmlspecialchars($brand['business']) ?></textarea>
            <?php if ($businessPrefilled): ?>
              <p class="form-hint">&#128161; Overgenomen uit de omschrijving van dit project. Pas gerust aan.</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Doelgroep</label>
            <input type="text" name="audience" class="form-control" placeholder="Wie wil je bereiken?" value="<?= htmlspecialchars($brand['audience']) ?>">
            <?php if ($brand['audience'] !== '' && empty($social['audience'])): ?>
              <p class="form-hint">Automatisch overgenomen uit je SEO-doelgroep. Pas gerust aan.</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Tone of voice</label>
            <textarea name="voice" class="form-control" rows="2" placeholder="bijv. Toegankelijk en enthousiast, geen jargon, met een vleugje humor."><?= htmlspecialchars($brand['voice']) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Contentpijlers / thema's (optioneel)</label>
            <textarea name="pillars" class="form-control" rows="2" placeholder="bijv. Klantverhalen, tips & tricks, achter de schermen"><?= htmlspecialchars($brand['pillars']) ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Voorbeeldpost (optioneel)</label>
            <textarea name="example_post" class="form-control" rows="3" placeholder="Plak hier een post die goed klinkt voor jouw merk — als referentie voor de stijl."><?= htmlspecialchars($brand['example']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Merkstem opslaan</button>
        </form>
      </div>
    </div>

  </main>
</div>
<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
<script>
  // Tab-navigatie
  document.querySelectorAll('.social-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll('.social-tab').forEach(function (b) { b.classList.toggle('active', b === btn); });
      document.querySelectorAll('.social-panel').forEach(function (p) { p.classList.toggle('active', p.dataset.panel === tab); });
    });
  });
  // "Genereren met AI"-knoppen: use_ai aanzetten + laadindicator tonen
  document.querySelectorAll('button.btn-primary[name^="gen_"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.closest('form');
      if (!f) return;

      // use_ai meesturen
      if (!f.querySelector('input[name="use_ai"]')) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'use_ai'; hidden.value = '1';
        f.appendChild(hidden);
      }

      // Naam/waarde van de submit-knop als verborgen veld meesturen,
      // zodat we de knop veilig mogen uitschakelen zonder de POST te breken.
      var mirror = document.createElement('input');
      mirror.type = 'hidden'; mirror.name = btn.name; mirror.value = btn.value || '1';
      f.appendChild(mirror);

      // Laadindicator op de knop
      btn.classList.add('is-loading');
      btn.innerHTML = '<span class="btn-spinner"></span> Bezig met genereren…';
      btn.disabled = true;

      // Overige knoppen in dit formulier ook blokkeren
      f.querySelectorAll('button').forEach(function (b) { if (b !== btn) b.disabled = true; });
    });
  });
</script>
</body>
</html>
