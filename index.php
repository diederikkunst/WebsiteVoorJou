<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$success = '';
$error   = '';

// Haal portfolio-URLs op uit settings
$portfolioSites = [];
try {
    $db2 = getDB();
    $rows = $db2->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('portfolio_url_1','portfolio_url_2','portfolio_url_3') ORDER BY setting_key")->fetchAll();
    foreach ($rows as $r) {
        if (!empty($r['setting_value'])) $portfolioSites[] = $r['setting_value'];
    }
} catch (\Throwable $e) { /* tabel bestaat nog niet */ }

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

            // Controleer of het e-maildomein daadwerkelijk bestaat (MX/DNS)
            if (!isValidEmail($email)) {
                $error = 'Dit e-mailadres lijkt niet te bestaan. Controleer op typefouten in het domein.';
            }

            // Valideer wachtwoord als account aanmaken
            if (!$error && $createAccount) {
                if (strlen($password) < 8) {
                    $error = 'Wachtwoord moet minimaal 8 tekens zijn.';
                } elseif ($password !== $password2) {
                    $error = 'Wachtwoorden komen niet overeen.';
                } else {
                    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $error = 'Er bestaat al een account met dit e-mailadres. <a href="' . BASE_PATH . '/login.php">Inloggen?</a>';
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

                    header('Location: ' . BASE_PATH . '/portal/dashboard.php');
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

  <title>WebsiteVoorJou — Professionele website laten maken met AI | Vanaf €999</title>
  <meta name="description" content="Ontvang binnen enkele dagen een gratis website preview op maat. WebsiteVoorJou bouwt professionele websites met AI — snel, betaalbaar en zonder gedoe. Pakketten vanaf €999.">
  <meta name="keywords" content="website laten maken, website bouwen, AI website, professionele website, website Nederland, goedkope website, website preview gratis">
  <meta name="robots" content="index, follow">
  <meta name="author" content="WebsiteVoorJou — KunstIT">
  <link rel="canonical" href="https://websitevoorjou.nl/">

  <!-- Open Graph -->
  <meta property="og:type"        content="website">
  <meta property="og:locale"      content="nl_NL">
  <meta property="og:url"         content="https://websitevoorjou.nl/">
  <meta property="og:site_name"   content="WebsiteVoorJou">
  <meta property="og:title"       content="WebsiteVoorJou — Professionele website laten maken met AI">
  <meta property="og:description" content="Gratis website preview binnen enkele dagen. Professioneel, snel en betaalbaar — pakketten vanaf €999.">
  <meta property="og:image"       content="https://websitevoorjou.nl/logo.png">
  <meta property="og:image:width"  content="800">
  <meta property="og:image:height" content="800">
  <meta property="og:image:alt"   content="WebsiteVoorJou logo">

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary">
  <meta name="twitter:title"       content="WebsiteVoorJou — Professionele website laten maken met AI">
  <meta name="twitter:description" content="Gratis website preview binnen enkele dagen. Pakketten vanaf €999.">
  <meta name="twitter:image"       content="https://websitevoorjou.nl/logo.png">

  <!-- JSON-LD Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebSite",
        "@id": "https://websitevoorjou.nl/#website",
        "url": "https://websitevoorjou.nl/",
        "name": "WebsiteVoorJou",
        "description": "Professionele websites laten maken met AI — snel, betaalbaar en op maat.",
        "inLanguage": "nl-NL",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "https://websitevoorjou.nl/#contact",
          "query-input": "required name=search_term_string"
        }
      },
      {
        "@type": "LocalBusiness",
        "@id": "https://websitevoorjou.nl/#business",
        "name": "WebsiteVoorJou",
        "alternateName": "KunstIT",
        "url": "https://websitevoorjou.nl/",
        "logo": "https://websitevoorjou.nl/logo.png",
        "image": "https://websitevoorjou.nl/logo.png",
        "email": "info@websitevoorjou.nl",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "Goudkruid 78",
          "postalCode": "3068SZ",
          "addressLocality": "Rotterdam",
          "addressCountry": "NL"
        },
        "vatID": "NL001570862B65",
        "description": "WebsiteVoorJou bouwt professionele websites met behulp van AI — snel, betaalbaar en volledig op maat voor ondernemers in Nederland.",
        "priceRange": "€€",
        "areaServed": "NL",
        "serviceType": "Webdesign en webontwikkeling",
        "sameAs": []
      },
      {
        "@type": "FAQPage",
        "@id": "https://websitevoorjou.nl/#faq",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "Hoe lang duurt het voordat ik mijn preview zie?",
            "acceptedAnswer": { "@type": "Answer", "text": "In de meeste gevallen heb je binnen 2 tot 5 werkdagen een gepersonaliseerde preview in je inbox. Bij complexere concepten kan dit iets langer duren, maar we houden je altijd op de hoogte." }
          },
          {
            "@type": "Question",
            "name": "Is de preview echt gratis, zonder verplichtingen?",
            "acceptedAnswer": { "@type": "Answer", "text": "Ja, 100% vrijblijvend. Je ontvangt een gratis website-concept op maat. Pas als je er tevreden mee bent en wil dat we het live zetten, betaal je voor een pakket. Geen credit card nodig bij de aanvraag." }
          },
          {
            "@type": "Question",
            "name": "Kan ik de website later nog aanpassen?",
            "acceptedAnswer": { "@type": "Answer", "text": "Absoluut. Met pakket Zilver en hoger kun je altijd aanpassingen aanvragen. Met pakket Goud beheer je zelf de inhoud via een CMS. Je kunt op elk moment upgraden." }
          },
          {
            "@type": "Question",
            "name": "Wat heb ik nodig om te beginnen?",
            "acceptedAnswer": { "@type": "Answer", "text": "Eigenlijk niets meer dan een paar zinnen over je bedrijf. Wat doe je, voor wie, en wat wil je bereiken? Wij nemen het van daar over. Heb je al een logo of huisstijl? Stuur het mee, maar het is geen vereiste." }
          },
          {
            "@type": "Question",
            "name": "Hoe zit het met hosting en onderhoud?",
            "acceptedAnswer": { "@type": "Answer", "text": "We regelen alles van hosting tot updates. Je hoeft je nergens druk om te maken. De precieze details bespreken we per pakket, maar veiligheid, uptime en updates zijn altijd inbegrepen." }
          }
        ]
      }
    ]
  }
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <a href="/" class="navbar-brand">Website<span>VoorJou</span></a>
    <ul class="navbar-nav">
      <li><a href="#over-ons">Over ons</a></li>
      <li><a href="#portfolio">Portfolio</a></li>
      <li><a href="#pakketten">Pakketten</a></li>
      <li><a href="#hoe-het-werkt">Hoe het werkt</a></li>
      <li><a href="#faq">FAQ</a></li>
      <li><a href="#contact">Contact</a></li>
      <li><a href="<?= BASE_PATH ?>/login.php" class="btn btn-outline btn-sm">Inloggen</a></li>
      <li><a href="<?= BASE_PATH ?>/register.php" class="btn btn-primary btn-sm" style="color:#fff;">Account aanmaken</a></li>
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
        <span>&#9989;</span> Gratis preview — geen creditcard nodig
      </div>
      <h1>Professionele website.<br><span class="gradient-text">Wij regelen alles.</span></h1>
      <p>Geen technische kennis nodig. Geen tijdverspilling. Stuur ons een beschrijving van je bedrijf en ontvang binnen enkele werkdagen een werkende website preview — volledig op maat.</p>
      <p class="hero-sub">Pakketten vanaf <strong style="color:var(--text);">€499</strong> eenmalig. Geen verborgen kosten.</p>
      <div class="hero-actions">
        <a href="#contact" class="btn btn-primary btn-lg">Ontvang je gratis preview</a>
        <a href="#pakketten" class="btn btn-outline btn-lg">Bekijk pakketten</a>
      </div>
      <div class="hero-trust">
        <div class="hero-trust-item"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Binnen enkele werkdagen</div>
        <div class="hero-trust-item"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Vaste prijs, geen verrassingen</div>
        <div class="hero-trust-item"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> KvK 24444475</div>
      </div>
    </div>
    <div class="hero-visual">
      <img src="<?= BASE_PATH ?>/blijeondernemers.png" alt="Blije ondernemers met hun nieuwe website" class="hero-photo">
    </div>
  </div>
</section>

<!-- Over ons -->
<section class="section" id="over-ons" style="background: linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%);">
  <div class="container">
    <div class="about-grid">
      <div class="about-image">
        <div class="about-photo-wrap">
          <img src="<?= BASE_PATH ?>/nieuwewebsite.png" alt="Voorbeeld van een nieuwe website" class="about-photo">
        </div>
      </div>
      <div>
        <div class="hero-tag" style="margin-bottom:20px;">
          <span>&#128161;</span> Onze missie
        </div>
        <h2>Voor ondernemers die <span class="gradient-text">gewoon een goede website</span> willen</h2>
        <p style="font-size:1.05rem;margin-top:16px;margin-bottom:8px;">
          Als ondernemer heb je geen tijd om maanden te wachten op een dure webbureau, of je te verdiepen in techniek. Je wil gewoon een professionele website die klanten aantrekt — en snel.
        </p>
        <p>
          Dat is precies wat wij doen. Stuur ons je beschrijving, en wij bouwen het. Geen verrassingen in de prijs, geen eindeloze vergaderingen. Gewoon resultaat.
        </p>
        <div class="about-features">
          <div class="about-feature">
            <div class="about-feature-icon">&#128273;</div>
            <div>
              <h4>Geen technische kennis nodig</h4>
              <p>Wij vertalen jouw wensen naar een professionele website. Jij hoeft niets te weten van code of design.</p>
            </div>
          </div>
          <div class="about-feature">
            <div class="about-feature-icon">&#128176;</div>
            <div>
              <h4>Transparante vaste prijs</h4>
              <p>Je weet vooraf wat het kost. Geen urenadministratie, geen meerwerk-facturen achteraf.</p>
            </div>
          </div>
          <div class="about-feature">
            <div class="about-feature-icon">&#129309;</div>
            <div>
              <h4>Wij blijven beschikbaar</h4>
              <p>Na oplevering kun je altijd bij ons terecht voor aanpassingen en vragen. Geen anoniem ticketsysteem.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Portfolio -->
<?php if (!empty($portfolioSites)): ?>
<section class="section" id="portfolio" style="background: linear-gradient(180deg, var(--bg-2) 0%, var(--bg) 100%);">
  <div class="container">
    <div class="section-header">
      <h2>Voorbeelden van ons werk</h2>
      <p>Een greep uit de websites die we voor onze klanten hebben gebouwd.</p>
    </div>
    <div class="grid-3" style="gap:32px;">
      <?php foreach ($portfolioSites as $siteUrl):
        $host = parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl;
        $label = preg_replace('/\.websitevoorjou\.nl$/', '', $host);
        $label = ucfirst($label);
        $thumbUrl = 'https://image.thum.io/get/width/800/crop/500/noanimate/' . $siteUrl;
      ?>
      <div class="portfolio-card">
        <a href="<?= htmlspecialchars($siteUrl) ?>" target="_blank" rel="noopener" class="portfolio-thumb-link">
          <div class="portfolio-thumb">
            <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($label) ?>" loading="lazy">
            <div class="portfolio-overlay">
              <span>&#128065; Bekijk website</span>
            </div>
          </div>
        </a>
        <div class="portfolio-info">
          <div class="portfolio-name"><?= htmlspecialchars($label) ?></div>
          <a href="<?= htmlspecialchars($siteUrl) ?>" target="_blank" rel="noopener" class="portfolio-url"><?= htmlspecialchars($host) ?> &#8599;</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Pakketten -->
<section class="section" id="pakketten">
  <div class="container">
    <div class="section-header">
      <h2>Eerlijke prijzen, geen verrassingen</h2>
      <p>Eenmalige vaste prijs, volledig op maat gemaakt voor jouw bedrijf. Je betaalt alleen als je tevreden bent met de preview.</p>
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
        <div class="package-price">&euro;499<span> eenmalig</span></div>
        <p class="package-desc">Jouw website live op jouw eigen domein — wij regelen hosting en oplevering.</p>
        <ul class="package-features">
          <li>Alles uit Brons</li>
          <li>Live op jouw eigen domein</li>
          <li>Professionele hosting inbegrepen</li>
          <li>SSL-certificaat</li>
        </ul>
        <div class="package-cta">
          <a href="#contact" class="btn btn-outline w-full">Kies Zilver</a>
        </div>
      </div>

      <!-- Goud -->
      <div class="package-card featured">
        <div class="package-badge">Meest gekozen</div>
        <div class="package-icon">&#11088;</div>
        <div class="package-tier tier-goud">Goud</div>
        <div class="package-price">&euro;999<span> eenmalig</span></div>
        <p class="package-desc">Zelf je teksten en afbeeldingen beheren, zonder technische kennis.</p>
        <ul class="package-features">
          <li>Alles uit Zilver</li>
          <li>Eenvoudig CMS — zelf aanpassen</li>
          <li>Blog of nieuwspagina</li>
          <li>Contactformulier met e-mail</li>
          <li>Google Analytics koppeling</li>
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
        <a href="<?= BASE_PATH ?>/auth/google.php" class="btn btn-outline w-full" style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:16px;">
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
          <li><a href="#pakketten">Zilver — &euro;499</a></li>
          <li><a href="#pakketten">Goud — &euro;999</a></li>
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
          <li><a href="<?= BASE_PATH ?>/algemene-voorwaarden.php">Algemene voorwaarden</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">Account</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_PATH ?>/login.php">Inloggen</a></li>
          <li><a href="<?= BASE_PATH ?>/portal/dashboard.php">Mijn projecten</a></li>
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

<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
