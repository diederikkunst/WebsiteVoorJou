<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/includes/auth.php';

    $name           = trim($_POST['name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $company        = trim($_POST['company'] ?? '');
    $message        = trim($_POST['message'] ?? '');
    $currentWebsite = trim($_POST['current_website'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password2      = $_POST['password2'] ?? '';
    $createAccount  = $password !== '';

    if ($name && $email && filter_var($email, FILTER_VALIDATE_EMAIL) && $message) {
        try {
            $db = getDB();

            // Valideer wachtwoord als account aanmaken
            if ($createAccount) {
                if (strlen($password) < 8) {
                    $error = 'Wachtwoord moet minimaal 8 tekens zijn.';
                } elseif ($password !== $password2) {
                    $error = 'Wachtwoorden komen niet overeen.';
                } else {
                    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $error = 'Er bestaat al een account met dit e-mailadres. <a href="/login.php">Inloggen?</a>';
                    }
                }
            }

            if (!$error) {
                $logo = null;
                if (!empty($_FILES['logo']['name'])) {
                    $logo = saveUpload($_FILES['logo'], 'contact_logos');
                }

                // Sla contactaanvraag op
                $stmt = $db->prepare('INSERT INTO contact_requests (name, email, phone, company, message, current_website, logo) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $email, $phone, $company, $message, $currentWebsite ?: null, $logo]);

                if ($createAccount) {
                    // Account aanmaken
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare('INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, \'client\', 1)')
                       ->execute([$name, $email, $hash]);
                    $userId = $db->lastInsertId();

                    // Client-record als lead aanmaken
                    $db->prepare('INSERT INTO clients (user_id, type, name, email, phone, website) VALUES (?, \'lead\', ?, ?, ?, ?)')
                       ->execute([$userId, $company ?: $name, $email, $phone ?: null, $currentWebsite ?: null]);

                    // Auto-login
                    $_SESSION['user_id']   = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'client';
                    session_regenerate_id(true);

                    header('Location: /portal/dashboard.php');
                    exit;
                }

                // Notificatie naar admin
                $adminHtml = '<p>Nieuwe contactaanvraag via websitevoorjou.nl:</p>'
                    . '<ul>'
                    . '<li><strong>Naam:</strong> ' . htmlspecialchars($name) . '</li>'
                    . '<li><strong>E-mail:</strong> ' . htmlspecialchars($email) . '</li>'
                    . ($phone   ? '<li><strong>Telefoon:</strong> ' . htmlspecialchars($phone) . '</li>' : '')
                    . ($company ? '<li><strong>Bedrijf:</strong> ' . htmlspecialchars($company) . '</li>' : '')
                    . ($currentWebsite ? '<li><strong>Huidige website:</strong> ' . htmlspecialchars($currentWebsite) . '</li>' : '')
                    . '<li><strong>Bericht:</strong><br>' . nl2br(htmlspecialchars($message)) . '</li>'
                    . '</ul>'
                    . '<p><a href="' . APP_URL . '/admin/contacts.php">Bekijk aanvraag in admin</a></p>';
                sendMail(MAIL_FROM, 'Nieuwe aanvraag: ' . $name, $adminHtml, 'WebsiteVoorJou', 'admin_notificatie');

                $success = 'Bedankt! We nemen binnen 2 werkdagen contact met je op.';
            }
        } catch (Exception $e) {
            $error = 'Er is iets misgegaan. Probeer het opnieuw.';
        }
    } else {
        $error = 'Vul alle verplichte velden correct in.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WebsiteVoorJou — Jouw website, snel gebouwd met AI</title>
  <meta name="description" content="Van concept naar online in no time. Stuur ons je bedrijfsbeschrijving en ontvang een gepersonaliseerde website preview. Professioneel, snel en betaalbaar.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <a href="/" class="navbar-brand">WebsiteVoorJou</a>
    <ul class="navbar-nav">
      <li><a href="#over-ons">Over ons</a></li>
      <li><a href="#pakketten">Pakketten</a></li>
      <li><a href="#hoe-het-werkt">Hoe het werkt</a></li>
      <li><a href="#faq">FAQ</a></li>
      <li><a href="#contact">Contact</a></li>
      <li><a href="/login.php" class="btn btn-outline btn-sm">Inloggen</a></li>
      <li><a href="/register.php" class="btn btn-primary btn-sm" style="color:#fff;">Account aanmaken</a></li>
    </ul>
    <button class="navbar-toggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Hero -->
<section class="hero" id="home">
  <div class="container" style="display:flex;gap:64px;align-items:center;flex-wrap:wrap;">
    <div class="hero-content">
      <div class="hero-tag">
        <span>&#9889;</span> Powered by AI &amp; vakmanschap
      </div>
      <h1>Jouw website,<br><span class="gradient-text">razendsnel live</span></h1>
      <p>Stuur ons je bedrijfsbeschrijving en ontvang binnen enkele dagen een professionele website preview. Geen gedoe, geen wachttijden — gewoon resultaat.</p>
      <div class="hero-actions">
        <a href="#contact" class="btn btn-primary btn-lg">Vraag jouw gratis preview aan</a>
        <a href="#pakketten" class="btn btn-outline btn-lg">Bekijk pakketten</a>
      </div>
    </div>
    <div class="hero-visual" style="flex:1;min-width:280px;">
      <div class="hero-grid">
        <div class="hero-card">
          <div class="hero-card-icon">&#127881;</div>
          <h4>Gratis preview</h4>
          <p class="text-muted">Binnen enkele dagen online</p>
        </div>
        <div class="hero-card">
          <div class="hero-card-icon">&#129302;</div>
          <h4>AI-gedreven</h4>
          <p class="text-muted">Sneller dan ooit</p>
        </div>
        <div class="hero-card">
          <div class="hero-card-icon">&#128640;</div>
          <h4>Direct live</h4>
          <p class="text-muted">Op jouw eigen domein</p>
        </div>
        <div class="hero-card">
          <div class="hero-card-icon">&#128736;</div>
          <h4>Volledig maatwerk</h4>
          <p class="text-muted">Precies zoals jij wil</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Over ons -->
<section class="section" id="over-ons" style="background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%);">
  <div class="container">
    <div class="about-grid">
      <div>
        <div class="hero-tag" style="margin-bottom:20px;">
          <span>&#128161;</span> Onze missie
        </div>
        <h2>Jarenlange passie, <span class="gradient-text">versterkt door AI</span></h2>
        <p style="font-size:1.05rem;margin-top:16px;margin-bottom:8px;">
          We bouwen al jaren software met één doel: zo efficiënt mogelijk, zonder in te leveren op kwaliteit. Elke regel code telt. Elke minuut telt.
        </p>
        <p>
          Met de komst van AI is dat nog verder gegaan. We kunnen nu sneller dan ooit professionele websites bouwen die er geweldig uitzien en perfect werken. En dat willen we met iedereen delen.
        </p>
        <div class="about-features">
          <div class="about-feature">
            <div class="about-feature-icon">&#128200;</div>
            <div>
              <h4>Razendsnel van idee naar resultaat</h4>
              <p>Stuur je beschrijving op en ontvang binnen enkele dagen al een werkende preview.</p>
            </div>
          </div>
          <div class="about-feature">
            <div class="about-feature-icon">&#127775;</div>
            <div>
              <h4>Topkwaliteit, geen concessies</h4>
              <p>AI versnelt ons werk, maar ons vakmanschap en oog voor detail blijven het fundament.</p>
            </div>
          </div>
          <div class="about-feature">
            <div class="about-feature-icon">&#129309;</div>
            <div>
              <h4>Een echte partner</h4>
              <p>We stoppen niet bij een website. We groeien mee als digitale partner voor jouw bedrijf.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="about-image">
        <div class="ai-visual">
          <span class="big-icon">&#129302;</span>
          <h3 style="margin-bottom:8px;">Slimmer bouwen</h3>
          <p>AI als co-piloot, wij aan het stuur</p>
          <div class="ai-stat">
            <div class="ai-stat-item">
              <h3>5x</h3>
              <p>Sneller dan traditioneel</p>
            </div>
            <div class="ai-stat-item">
              <h3>100%</h3>
              <p>Maatwerk resultaat</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Pakketten -->
<section class="section" id="pakketten">
  <div class="container">
    <div class="section-header">
      <h2>Kies jouw pakket</h2>
      <p>Van een gratis preview tot volledige bedrijfssoftware — er is een passende oplossing voor elke fase van je bedrijf.</p>
    </div>
    <div class="packages-grid">

      <!-- Brons -->
      <div class="package-card">
        <div class="package-icon">&#127881;</div>
        <div class="package-tier tier-brons">Brons</div>
        <div class="package-price">Gratis<span></span></div>
        <p class="package-desc">Website concept zonder verplichtingen — kijk wat er mogelijk is voor jouw bedrijf.</p>
        <ul class="package-features">
          <li>Persoonlijk website-concept op maat</li>
          <li>Preview van je website</li>
          <li>Klaar binnen enkele werkdagen</li>
          <li>Geen creditcard nodig</li>
          <li>Vrijblijvend bekijken</li>
        </ul>
        <div class="package-cta">
          <a href="#contact" class="btn btn-outline w-full">Vraag gratis aan</a>
        </div>
      </div>

      <!-- Zilver -->
      <div class="package-card">
        <div class="package-icon">&#127748;</div>
        <div class="package-tier tier-zilver">Zilver</div>
        <div class="package-price">&euro;999<span> eenmalig</span></div>
        <p class="package-desc">Jouw goedgekeurde concept live zetten op jouw eigen domein.</p>
        <ul class="package-features">
          <li>Alles uit Brons</li>
          <li>Live op jouw eigen domein</li>
          <li>Professionele hosting-setup</li>
        </ul>
        <div class="package-cta">
          <a href="#contact" class="btn btn-outline w-full">Kies Zilver</a>
        </div>
      </div>

      <!-- Goud -->
      <div class="package-card featured">
        <div class="package-badge">Meest populair</div>
        <div class="package-icon">&#11088;</div>
        <div class="package-tier tier-goud">Goud</div>
        <div class="package-price">&euro;2.999<span> eenmalig</span></div>
        <p class="package-desc">Jouw website met een krachtig CMS zodat je zelf de content beheert.</p>
        <ul class="package-features">
          <li>Alles uit Zilver</li>
          <li>Volledig CMS — zelf alles aanpassen</li>
          <li>Blog / nieuws module</li>
          <li>Contactformulieren</li>
        </ul>
        <div class="package-cta">
          <a href="#contact" class="btn btn-primary w-full">Kies Goud</a>
        </div>
      </div>

      <!-- Platinum -->
      <div class="package-card">
        <div class="package-icon">&#128142;</div>
        <div class="package-tier tier-platinum">Platinum</div>
        <div class="package-price">Op maat<span></span></div>
        <p class="package-desc">Bedrijfssoftware op maat — van klantportaal tot volledige ERP-integratie.</p>
        <ul class="package-features">
          <li>Alles uit Goud</li>
          <li>Maatwerk bedrijfsapplicaties</li>
          <li>API-koppelingen</li>
          <li>Database &amp; backoffice</li>
          <li>Dedicated ontwikkelteam</li>
          <li>Prioriteit support &amp; SLA</li>
        </ul>
        <div class="package-cta">
          <a href="#contact" class="btn btn-outline w-full">Plan een gesprek</a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- Hoe het werkt -->
<section class="section" id="hoe-het-werkt" style="background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 50%, var(--bg) 100%);">
  <div class="container">
    <div class="section-header">
      <h2>Van beschrijving naar live — <span class="gradient-text">zo simpel</span></h2>
      <p>In een paar stappen heb jij een frisse nieuwe website en eventueel nieuwe bedrijfssoftware. Geen gedoe, geen verrassingen.</p>
    </div>
    <div class="steps-container">
      <div class="step">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3>Stuur je beschrijving op</h3>
          <p>Vul het formulier hieronder in met een korte beschrijving van je bedrijf, je wensen en je doelgroep. Geen A4 nodig — een paar zinnen zijn al genoeg om te starten.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3>Ontvang je gratis preview</h3>
          <p>Binnen enkele werkdagen staat er een gepersonaliseerde website voor je klaar. Volledig afgestemd op jouw bedrijf en branche.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3>Tevreden? Kies je pakket</h3>
          <p>Bevalt de preview? Maak dan een account aan en we zorgen er samen voor dat jouw website online komt. Wil je hem aanpassen? Log in en deel je wensen direct met ons.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-number">4</div>
        <div class="step-content">
          <h3>Wil je zelf de inhoud beheren?</h3>
          <p>Upgrade naar <strong>Pakket Goud</strong> en beheer je eigen teksten, afbeeldingen en pagina's via een gebruiksvriendelijk CMS. Volledig in eigen hand.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-number">5</div>
        <div class="step-content">
          <h3>Klaar voor de volgende stap?</h3>
          <p>Heb je ook bedrijfssoftware nodig of wil je een bestaande applicatie vernieuwen? Deel je wensen via je account en we nemen snel contact met je op voor een maatwerkoplossing.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section-sm" id="faq">
  <div class="container">
    <div class="section-header">
      <h2>Veelgestelde vragen</h2>
      <p>Heb je een andere vraag? Stuur ons gewoon een berichtje.</p>
    </div>
    <div class="faq-container">
      <div class="faq-item">
        <button class="faq-question">Hoe lang duurt het voordat ik mijn preview zie? <span class="faq-icon">+</span></button>
        <div class="faq-answer">In de meeste gevallen heb je binnen 2 tot 5 werkdagen een gepersonaliseerde preview in je inbox. Bij complexere concepten kan dit iets langer duren, maar we houden je altijd op de hoogte.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Is de preview echt gratis, zonder verplichtingen? <span class="faq-icon">+</span></button>
        <div class="faq-answer">Ja, 100% vrijblijvend. Je ontvangt een gratis website-concept op maat. Pas als je er tevreden mee bent en wil dat we het live zetten, betaal je voor een pakket. Geen credit card nodig bij de aanvraag.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Kan ik de website later nog aanpassen? <span class="faq-icon">+</span></button>
        <div class="faq-answer">Absoluut. Met pakket Zilver en hoger kun je altijd aanpassingen aanvragen. Met pakket Goud beheer je zelf de inhoud via een CMS. Je kunt op elk moment upgraden.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Wat heb ik nodig om te beginnen? <span class="faq-icon">+</span></button>
        <div class="faq-answer">Eigenlijk niets meer dan een paar zinnen over je bedrijf. Wat doe je, voor wie, en wat wil je bereiken? Wij nemen het van daar over. Heb je al een logo of huisstijl? Stuur het mee, maar het is geen vereiste.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Wat valt onder "bedrijfssoftware" bij het Platinum pakket? <span class="faq-icon">+</span></button>
        <div class="faq-answer">Denk aan klantportalen, boekingssystemen, CRM-oplossingen, voorraadbeheer, facturatiesoftware of koppelingen met bestaande tools. Eigenlijk alles wat jouw bedrijfsproces digitaal ondersteunt. We bespreken de mogelijkheden graag in een vrijblijvend gesprek.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Op welk domein wordt mijn preview geplaatst? <span class="faq-icon">+</span></button>
        <div class="faq-answer">Je preview is beschikbaar op een uniek subdomein van websitevoorjou.nl, bijvoorbeeld jouwbedrijf.websitevoorjou.nl. Na akkoord zetten we alles over naar jouw eigen domein.</div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Hoe zit het met hosting en onderhoud? <span class="faq-icon">+</span></button>
        <div class="faq-answer">We regelen alles van hosting tot updates. Je hoeft je nergens druk om te maken. De precieze details bespreken we per pakket, maar veiligheid, uptime en updates zijn altijd inbegrepen.</div>
      </div>
    </div>
  </div>
</section>

<!-- Contact -->
<section class="section" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div>
        <div class="hero-tag" style="margin-bottom:20px;">
          <span>&#128172;</span> Direct contact
        </div>
        <h2>Klaar om te starten?</h2>
        <p style="font-size:1.05rem;margin-top:12px;margin-bottom:32px;">Vul het formulier in en ontvang binnen enkele werkdagen al een preview van jouw nieuwe website. Gratis, vrijblijvend en razendsnel.</p>
        <div class="contact-info">
          <div class="contact-item">
            <div class="contact-item-icon">&#128205;</div>
            <div>
              <h4>Nederland</h4>
              <p>We werken volledig remote en bedienen klanten door heel Nederland.</p>
            </div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon">&#128231;</div>
            <div>
              <h4>info@websitevoorjou.nl</h4>
              <p>Reactie binnen 1 werkdag.</p>
            </div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon">&#9200;</div>
            <div>
              <h4>Preview binnen enkele dagen</h4>
              <p>Snel zien wat er voor jou mogelijk is.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="contact-form">
        <h3 style="margin-bottom:8px;">Vraag jouw gratis preview aan</h3>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:20px;">Maak gelijk een account aan om je preview te volgen in het portaal.</p>

        <?php if (GOOGLE_CLIENT_ID): ?>
        <a href="/auth/google.php" class="btn btn-outline w-full" style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:16px;">
          <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
          Doorgaan met Google
        </a>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
          <div style="flex:1;height:1px;background:var(--border);"></div>
          <span style="font-size:0.8rem;color:var(--text-muted);">of vul het formulier in</span>
          <div style="flex:1;height:1px;background:var(--border);"></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success" data-dismiss="6000">&#10003; <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger">&#10007; <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="#contact" enctype="multipart/form-data">
          <input type="hidden" name="contact_form" value="1">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Naam *</label>
              <input type="text" name="name" class="form-control" placeholder="Jan de Vries" required>
            </div>
            <div class="form-group">
              <label class="form-label">Bedrijfsnaam</label>
              <input type="text" name="company" class="form-control" placeholder="Jouw Bedrijf B.V.">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">E-mailadres *</label>
              <input type="email" name="email" class="form-control" placeholder="jan@jouwbedrijf.nl" required>
            </div>
            <div class="form-group">
              <label class="form-label">Telefoonnummer</label>
              <input type="tel" name="phone" class="form-control" placeholder="+31 6 12345678">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Beschrijf je bedrijf en wensen *</label>
            <textarea name="message" class="form-control" rows="5" placeholder="Vertel ons in eigen woorden wat je doet, voor wie, en wat voor website je in gedachten hebt..." required></textarea>
            <p class="form-hint">Hoe meer details, hoe beter we de preview op jou kunnen afstemmen.</p>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Huidige website</label>
              <input type="url" name="current_website" class="form-control" placeholder="https://www.jouwbedrijf.nl">
              <p class="form-hint">Optioneel — als je al een website hebt.</p>
            </div>
            <div class="form-group">
              <label class="form-label">Logo</label>
              <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
              <p class="form-hint">Optioneel — jpg, png, svg (max 10MB).</p>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Wachtwoord <span style="color:var(--text-muted);font-weight:400;">(optioneel)</span></label>
              <input type="password" name="password" class="form-control" placeholder="Minimaal 8 tekens — voor portaal toegang"
                autocomplete="new-password" oninput="checkPass()">
            </div>
            <div class="form-group">
              <label class="form-label">Herhaal wachtwoord</label>
              <input type="password" name="password2" class="form-control" id="password2" placeholder="Herhaal wachtwoord"
                autocomplete="new-password" oninput="checkPass()">
              <p id="passMsg" class="form-hint" style="display:none;"></p>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-full btn-lg">
            Aanvraag versturen &#8594;
          </button>
          <p class="form-hint text-center" style="margin-top:12px;">100% gratis &amp; vrijblijvend. Geen creditcard nodig.</p>
        </form>
        <script>
        function checkPass() {
          var p1 = document.querySelector('[name="password"]').value;
          var p2 = document.getElementById('password2').value;
          var msg = document.getElementById('passMsg');
          if (!p1) { msg.style.display='none'; return; }
          msg.style.display = 'block';
          if (p1.length < 8) {
            msg.textContent = 'Minimaal 8 tekens vereist.'; msg.style.color = 'var(--danger)';
          } else if (p2 && p1 !== p2) {
            msg.textContent = 'Wachtwoorden komen niet overeen.'; msg.style.color = 'var(--danger)';
          } else if (p2 && p1 === p2) {
            msg.textContent = 'Wachtwoorden komen overeen.'; msg.style.color = 'var(--success)';
          } else {
            msg.style.display = 'none';
          }
        }
        </script>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">WebsiteVoorJou</div>
        <p class="footer-desc">Van concept naar online. Wij bouwen websites met passie, vakmanschap en de kracht van AI — snel, betaalbaar en precies zoals jij het wilt.</p>
      </div>
      <div>
        <h4 class="footer-heading">Pakketten</h4>
        <ul class="footer-links">
          <li><a href="#pakketten">Brons — Gratis preview</a></li>
          <li><a href="#pakketten">Zilver — &euro;999</a></li>
          <li><a href="#pakketten">Goud — &euro;2.999</a></li>
          <li><a href="#pakketten">Platinum — Op maat</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">Informatie</h4>
        <ul class="footer-links">
          <li><a href="#over-ons">Over ons</a></li>
          <li><a href="#hoe-het-werkt">Hoe het werkt</a></li>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#contact">Contact</a></li>
          <li><a href="/algemene-voorwaarden.php">Algemene voorwaarden</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">Account</h4>
        <ul class="footer-links">
          <li><a href="/login.php">Inloggen</a></li>
          <li><a href="/portal/dashboard.php">Mijn projecten</a></li>
          <li><a href="mailto:info@websitevoorjou.nl">Support</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> WebsiteVoorJou. Alle rechten voorbehouden.</span>
      <span>Gebouwd met &#9889; &amp; AI</span>
    </div>
  </div>
</footer>

<script src="/assets/js/main.js"></script>
</body>
</html>
