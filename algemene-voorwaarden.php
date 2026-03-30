<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Algemene Voorwaarden — WebsiteVoorJou</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .av-wrap {
      max-width: 780px;
      margin: 0 auto;
      padding: 100px 24px 80px;
    }
    .av-wrap h1 {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 8px;
    }
    .av-meta {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-bottom: 48px;
      padding-bottom: 24px;
      border-bottom: 1px solid var(--border);
    }
    .av-article {
      margin-bottom: 40px;
    }
    .av-article h2 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--border);
    }
    .av-article h2 span {
      display: inline-block;
      width: 28px;
      height: 28px;
      line-height: 28px;
      text-align: center;
      border-radius: 50%;
      background: var(--gradient);
      color: #fff;
      font-size: 0.8rem;
      font-weight: 800;
      margin-right: 10px;
      flex-shrink: 0;
    }
    .av-article p,
    .av-article ul {
      color: var(--text-muted);
      line-height: 1.8;
      font-size: 0.95rem;
      margin-bottom: 10px;
    }
    .av-article ul {
      padding-left: 20px;
    }
    .av-article ul li {
      margin-bottom: 6px;
    }
    .av-back {
      display: inline-block;
      margin-bottom: 32px;
      font-size: 0.9rem;
      color: var(--text-muted);
    }
    .av-back:hover { color: var(--primary); }
  </style>
</head>
<body style="background: var(--bg);">

  <!-- Navbar -->
  <nav class="navbar">
    <div class="container navbar-inner">
      <a href="/" class="navbar-brand">WebsiteVoorJou</a>
      <ul class="navbar-nav">
        <li><a href="/#diensten">Diensten</a></li>
        <li><a href="/#pakketten">Pakketten</a></li>
        <li><a href="/#faq">FAQ</a></li>
        <li><a href="/#contact">Contact</a></li>
      </ul>
      <div style="display:flex;gap:8px;align-items:center;">
        <a href="/login.php" class="btn btn-outline btn-sm">Inloggen</a>
        <a href="/#contact" class="btn btn-primary btn-sm">Aanvragen</a>
      </div>
      <button class="navbar-toggle" aria-label="Menu">&#9776;</button>
    </div>
  </nav>

  <div class="av-wrap">
    <a href="/" class="av-back">&#8592; Terug naar home</a>

    <h1>Algemene Voorwaarden</h1>
    <div class="av-meta">
      WebsiteVoorJou &bull; KvK: 24444475 &bull; info@websitevoorjou.nl<br>
      Versie 1.0 &bull; Ingangsdatum: <?= date('d-m-Y') ?>
    </div>

    <!-- Artikel 1 -->
    <div class="av-article">
      <h2><span>1</span> Definities</h2>
      <ul>
        <li><strong>WebsiteVoorJou:</strong> de eenmanszaak WebsiteVoorJou, ingeschreven bij de KvK onder nummer 24444475.</li>
        <li><strong>Klant:</strong> de natuurlijke persoon of rechtspersoon die een overeenkomst aangaat met WebsiteVoorJou.</li>
        <li><strong>Opdracht:</strong> de werkzaamheden die WebsiteVoorJou uitvoert voor de klant, waaronder het ontwerpen en bouwen van een website.</li>
        <li><strong>Oplevering:</strong> het moment waarop WebsiteVoorJou de website ter goedkeuring aanbiedt aan de klant via een preview-link.</li>
      </ul>
    </div>

    <!-- Artikel 2 -->
    <div class="av-article">
      <h2><span>2</span> Toepasselijkheid</h2>
      <p>Deze algemene voorwaarden zijn van toepassing op alle offertes, overeenkomsten en leveringen van WebsiteVoorJou. Afwijkingen zijn alleen geldig als deze schriftelijk zijn overeengekomen. De toepasselijkheid van eventuele inkoop- of andere voorwaarden van de klant wordt uitdrukkelijk van de hand gewezen.</p>
    </div>

    <!-- Artikel 3 -->
    <div class="av-article">
      <h2><span>3</span> Aanlevering van materiaal</h2>
      <p>De klant is verantwoordelijk voor het tijdig aanleveren van alle benodigde materialen, waaronder:</p>
      <ul>
        <li>Teksten, afbeeldingen, logo's en andere content voor de website.</li>
        <li>Inloggegevens voor domeinnaam of hosting, indien van toepassing.</li>
        <li>Eventuele huisstijlrichtlijnen of voorkeuren.</li>
      </ul>
      <p>WebsiteVoorJou is niet aansprakelijk voor vertragingen in de oplevering als gevolg van het te laat of onvolledig aanleveren van materialen door de klant. De overeengekomen termijnen worden in dat geval dienovereenkomstig verschoven.</p>
    </div>

    <!-- Artikel 4 -->
    <div class="av-article">
      <h2><span>4</span> Verantwoordelijkheid voor website-inhoud</h2>
      <p>De klant is volledig en als enige verantwoordelijk voor de inhoud die op de website wordt geplaatst. Dit omvat onder meer:</p>
      <ul>
        <li>De juistheid, volledigheid en rechtmatigheid van alle teksten, afbeeldingen en overige content.</li>
        <li>Het naleven van wet- en regelgeving, waaronder auteursrechten en intellectuele eigendomsrechten van derden.</li>
        <li>Het voldoen aan de privacywetgeving (AVG/GDPR), waaronder het beschikbaar stellen van een privacyverklaring en cookiebeleid op de website.</li>
        <li>Het niet publiceren van onrechtmatige, misleidende of aanstootgevende inhoud.</li>
      </ul>
      <p>WebsiteVoorJou aanvaardt geen enkele aansprakelijkheid voor schade die voortvloeit uit de inhoud van de door de klant geplaatste of aangeleverde materialen.</p>
    </div>

    <!-- Artikel 5 -->
    <div class="av-article">
      <h2><span>5</span> Betaling</h2>
      <ul>
        <li>Facturen dienen te worden voldaan binnen <strong>30 dagen</strong> na factuurdatum, tenzij schriftelijk anders overeengekomen.</li>
        <li>Bij overschrijding van de betalingstermijn is de klant van rechtswege in verzuim en is WebsiteVoorJou gerechtigd wettelijke rente en incassokosten in rekening te brengen.</li>
        <li>Zolang de factuur niet volledig is voldaan, behoudt WebsiteVoorJou het recht de website offline te halen of de download te blokkeren.</li>
        <li>Reeds betaalde bedragen worden niet gerestitueerd, tenzij WebsiteVoorJou aantoonbaar tekortgeschoten is in de nakoming van de overeenkomst.</li>
      </ul>
    </div>

    <!-- Artikel 6 -->
    <div class="av-article">
      <h2><span>6</span> Wijzigingen na oplevering</h2>
      <p>Na volledige betaling en oplevering van de website kunnen wijzigingen, uitbreidingen of aanpassingen uitsluitend worden uitgevoerd <strong>tegen aanvullende betaling</strong>. Hieronder valt onder andere:</p>
      <ul>
        <li>Aanpassingen aan de vormgeving, structuur of functionaliteit van de website.</li>
        <li>Het toevoegen van nieuwe pagina's of secties.</li>
        <li>Technische aanpassingen of integraties met externe diensten.</li>
      </ul>
      <p>Kleine correcties van fouten die direct na oplevering worden gemeld (binnen 14 dagen) en aantoonbaar het gevolg zijn van een fout van WebsiteVoorJou, worden kosteloos hersteld.</p>
    </div>

    <!-- Artikel 7 -->
    <div class="av-article">
      <h2><span>7</span> Intellectueel eigendom</h2>
      <ul>
        <li>Alle door WebsiteVoorJou ontwikkelde ontwerpen, code en materialen blijven eigendom van WebsiteVoorJou totdat de factuur volledig is betaald.</li>
        <li>Na volledige betaling draagt WebsiteVoorJou de gebruiksrechten op de website over aan de klant.</li>
        <li>De klant garandeert dat aangeleverde materialen geen inbreuk maken op rechten van derden en vrijwaart WebsiteVoorJou voor aanspraken van derden dienaangaande.</li>
      </ul>
    </div>

    <!-- Artikel 8 -->
    <div class="av-article">
      <h2><span>8</span> Hosting en domeinnaam</h2>
      <p>Tenzij uitdrukkelijk schriftelijk anders overeengekomen, valt het afsluiten en beheren van hosting en een domeinnaam buiten de opdracht van WebsiteVoorJou. De klant is zelf verantwoordelijk voor:</p>
      <ul>
        <li>Het afsluiten van een hostingabonnement.</li>
        <li>De registratie en verlenging van de domeinnaam.</li>
        <li>Het correct instellen van DNS-records.</li>
      </ul>
      <p>WebsiteVoorJou kan hierbij advies geven, maar is niet aansprakelijk voor storingen of problemen bij externe hosting- of domeinproviders.</p>
    </div>

    <!-- Artikel 9 -->
    <div class="av-article">
      <h2><span>9</span> Annulering</h2>
      <ul>
        <li>Annulering dient schriftelijk te worden gemeld via info@websitevoorjou.nl.</li>
        <li>Bij annulering vóór de start van de werkzaamheden zijn geen kosten verschuldigd.</li>
        <li>Bij annulering nadat de werkzaamheden zijn gestart, is de klant een vergoeding verschuldigd voor de tot dat moment verrichte werkzaamheden, op basis van bestede uren en gemaakte kosten.</li>
        <li>Reeds goedgekeurde previews of geleverde ontwerpen worden bij annulering volledig gefactureerd.</li>
      </ul>
    </div>

    <!-- Artikel 10 -->
    <div class="av-article">
      <h2><span>10</span> Portfolio en referenties</h2>
      <p>WebsiteVoorJou behoudt zich het recht voor de voltooide website op te nemen in het eigen portfolio en te gebruiken als referentie, tenzij de klant hier uitdrukkelijk schriftelijk bezwaar tegen maakt voor de oplevering. Persoonsgegevens worden hierbij niet zonder toestemming gedeeld.</p>
    </div>

    <!-- Artikel 11 -->
    <div class="av-article">
      <h2><span>11</span> Aansprakelijkheid</h2>
      <ul>
        <li>De aansprakelijkheid van WebsiteVoorJou is te allen tijde beperkt tot het bedrag dat in het kader van de betreffende opdracht in rekening is gebracht.</li>
        <li>WebsiteVoorJou is niet aansprakelijk voor indirecte schade, gevolgschade, gederfde winst of omzetderving.</li>
        <li>WebsiteVoorJou is niet verantwoordelijk voor de werking van externe diensten, plug-ins of koppelingen van derden.</li>
      </ul>
    </div>

    <!-- Artikel 12 -->
    <div class="av-article">
      <h2><span>12</span> Privacy en gegevensverwerking</h2>
      <p>WebsiteVoorJou verwerkt persoonsgegevens van klanten uitsluitend ten behoeve van de uitvoering van de overeenkomst. Gegevens worden niet aan derden verstrekt zonder toestemming, tenzij dit wettelijk verplicht is. Voor meer informatie verwijzen wij naar ons privacybeleid.</p>
    </div>

    <!-- Artikel 13 -->
    <div class="av-article">
      <h2><span>13</span> Toepasselijk recht en geschillen</h2>
      <p>Op alle overeenkomsten tussen WebsiteVoorJou en de klant is uitsluitend <strong>Nederlands recht</strong> van toepassing. Geschillen worden bij voorkeur in onderling overleg opgelost. Indien dit niet mogelijk is, worden geschillen voorgelegd aan de bevoegde rechtbank.</p>
    </div>

    <!-- Contact -->
    <div style="margin-top:48px;padding:24px 28px;background:var(--bg-2);border:1px solid var(--border);border-radius:12px;">
      <p style="margin:0;font-size:0.9rem;color:var(--text-muted);">
        Vragen over deze voorwaarden? Neem contact op via
        <a href="mailto:info@websitevoorjou.nl" style="color:var(--primary);">info@websitevoorjou.nl</a>.
      </p>
    </div>
  </div>

  <footer>
    <div class="container">
      <div class="footer-grid">
        <div>
          <div class="footer-brand">WebsiteVoorJou</div>
          <p>Professionele websites voor elk budget.</p>
        </div>
        <div>
          <h4>Links</h4>
          <ul>
            <li><a href="/#diensten">Diensten</a></li>
            <li><a href="/#pakketten">Pakketten</a></li>
            <li><a href="/#contact">Contact</a></li>
            <li><a href="/algemene-voorwaarden.php">Algemene Voorwaarden</a></li>
          </ul>
        </div>
        <div>
          <h4>Contact</h4>
          <ul>
            <li><a href="mailto:info@websitevoorjou.nl">info@websitevoorjou.nl</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> WebsiteVoorJou. Alle rechten voorbehouden.</p>
      </div>
    </div>
  </footer>

  <script src="/assets/js/main.js"></script>
</body>
</html>
