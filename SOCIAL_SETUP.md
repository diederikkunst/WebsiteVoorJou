# Social media & AI — instelhandleiding

Deze handleiding beschrijft hoe je de **SEO-/social-functies** van WebsiteVoorJou activeert:
AI-generatie van posts en het **echt publiceren** naar LinkedIn, Facebook en Instagram.

> Zonder sleutels werkt alles al, maar veilig: posts worden als **prompt** gegenereerd
> (copy-paste naar ChatGPT/Claude) en publiceren draait in **dry-run** (er gaat niets de deur uit).
> Je vult alleen in wat je wilt gebruiken — alles is optioneel en onafhankelijk.

---

## 0. Waar zet ik de sleutels?

Zet gevoelige sleutels **niet** rechtstreeks in `config.php` (die staat in git), maar in
`config.local.php` in de projectroot. Dat bestand wordt als eerste geladen en overschrijft
de standaardwaarden. Maak het aan als het nog niet bestaat:

```php
<?php
// config.local.php — NIET in versiebeheer (staat in .gitignore)

// AI-generatie van posts
define('OPENAI_API_KEY', 'sk-...');
define('OPENAI_MODEL',   'gpt-4o');          // optioneel, dit is de standaard

// LinkedIn
define('LINKEDIN_ACCESS_TOKEN', '...');
define('LINKEDIN_AUTHOR_URN',   'urn:li:person:XXXX');

// Facebook
define('FACEBOOK_PAGE_ID',      '123456789');
define('FACEBOOK_ACCESS_TOKEN', '...');

// Instagram
define('INSTAGRAM_USER_ID',      '178414...');
define('INSTAGRAM_ACCESS_TOKEN', '...');
```

> Controleer dat `config.local.php` in [.gitignore](.gitignore) staat zodat je sleutels nooit
> gecommit worden.

---

## 1. Database-migraties

Draai deze eenmalig op de database (als nog niet gedaan):

```bash
mysql -u h_000b391b_wvj -p h_000b391b_wvj < migrations/2026_06_28_project_seo.sql
mysql -u h_000b391b_wvj -p h_000b391b_wvj < migrations/2026_06_28_project_social.sql
mysql -u h_000b391b_wvj -p h_000b391b_wvj < migrations/2026_06_28_project_social_posts.sql
mysql -u h_000b391b_wvj -p h_000b391b_wvj < migrations/2026_06_28_social_posts_image.sql
```

---

## 2. OpenAI (AI-generatie van posts)

Hiermee verschijnt op de social-pagina de knop **"⚡ Genereren met AI"**.

1. Ga naar <https://platform.openai.com/api-keys> en log in.
2. Klik **Create new secret key**, geef een naam, kopieer de sleutel (`sk-...`).
   Je ziet hem maar één keer.
3. Zet in `config.local.php`:
   ```php
   define('OPENAI_API_KEY', 'sk-...');
   ```
4. Optioneel goedkoper model: `define('OPENAI_MODEL', 'gpt-4o-mini');`
5. Zorg dat er tegoed/billing op het OpenAI-account staat.

---

## 3. LinkedIn

Vereist een token met scope **`w_member_social`** en de auteur-URN.

1. Maak een app op <https://www.linkedin.com/developers/apps> (knop **Create app**;
   koppel een bedrijfspagina).
2. Tabblad **Products** → vraag **Share on LinkedIn** (en evt. **Sign In with LinkedIn**) aan.
3. Tabblad **Auth** → noteer **Client ID** en **Client Secret**, en zet een
   **Authorized redirect URL** (bijv. `https://websitevoorjou.nl/auth/linkedin-callback.php`).
4. Doorloop de OAuth-flow om een **access token** met scope `w_member_social` te krijgen
   (tijdelijk kan dit ook via de **OAuth 2.0 token generator** in het LinkedIn-portal).
5. Bepaal je auteur-URN:
   - Persoonlijk profiel: `urn:li:person:XXXX` (de `sub` uit het `/v2/userinfo`-endpoint).
   - Bedrijfspagina: `urn:li:organization:XXXX` (het pagina-ID).
6. Zet in `config.local.php`:
   ```php
   define('LINKEDIN_ACCESS_TOKEN', '...');
   define('LINKEDIN_AUTHOR_URN',   'urn:li:person:XXXX');
   ```

> LinkedIn-tokens verlopen (meestal na ~60 dagen). Plan op tijd vernieuwen.

---

## 4. Facebook (Pagina)

Tekstposts kunnen direct; een afbeelding is optioneel. Gebruikt de Meta Graph API.

1. Maak een app op <https://developers.facebook.com/apps> → type **Business**.
2. Voeg het product **Facebook Login** toe en open de **Graph API Explorer**
   (<https://developers.facebook.com/tools/explorer>).
3. Kies je app, en vraag deze permissies aan:
   `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`.
4. Genereer een **User access token**, en haal daarmee het **Page access token** op:
   - Roep in de Explorer `me/accounts` aan → kopieer het `access_token` en `id` van je pagina.
   - Dit Page-token gebruik je als `FACEBOOK_ACCESS_TOKEN`, het `id` als `FACEBOOK_PAGE_ID`.
5. Maak het token **langlevend** (anders verloopt het in ~1 uur):
   ```
   https://graph.facebook.com/v21.0/oauth/access_token?grant_type=fb_exchange_token
     &client_id=APP_ID&client_secret=APP_SECRET&fb_exchange_token=KORT_TOKEN
   ```
   Haal daarna opnieuw `me/accounts` op voor een langlevend **Page-token**.
6. Zet in `config.local.php`:
   ```php
   define('FACEBOOK_PAGE_ID',      '123456789');
   define('FACEBOOK_ACCESS_TOKEN', '...');   // langlevend Page-token
   ```

> Voor een productie-app moet Meta **App Review** doorlopen worden voor `pages_manage_posts`,
> tenzij je alleen post naar pagina's waar je zelf beheerder van bent (ontwikkelmodus).

---

## 5. Instagram (Professional/Business-account)

> **Belangrijk:** Instagram staat **geen tekst-only posts** toe. Elke post heeft een
> **publiek bereikbare afbeelding-URL** nodig (die vul je per post in op de social-pagina).

Vereisten: een **Instagram Professional-account** dat gekoppeld is aan een **Facebook-pagina**.

1. Koppel je Instagram-account aan je Facebook-pagina (Instagram-app → Instellingen →
   Account → Gekoppelde accounts, of via Meta Business Suite).
2. Gebruik dezelfde Meta-app als bij Facebook. Vraag extra permissies aan:
   `instagram_basic`, `instagram_content_publish`, plus de pagina-permissies uit stap 4.
3. Vind je **Instagram Business-account-ID** via de Graph API Explorer:
   ```
   GET me/accounts                              → page-id
   GET {page-id}?fields=instagram_business_account   → { "instagram_business_account": { "id": "1784..." } }
   ```
   Die `id` is je `INSTAGRAM_USER_ID`.
4. Het token is hetzelfde (langlevende) Page-token als bij Facebook.
5. Zet in `config.local.php`:
   ```php
   define('INSTAGRAM_USER_ID',      '1784...');
   define('INSTAGRAM_ACCESS_TOKEN', '...');   // zelfde langlevend Page-token
   ```

Werkwijze in de app: de klant zet bij een Instagram-post een **afbeelding-URL** (de afbeelding
moet publiek bereikbaar zijn — bijv. een geüploade afbeelding op de eigen website).

---

## 6. Scheduler (automatisch publiceren van ingeplande posts)

[scheduler.php](scheduler.php) verwerkt alle posts waarvan het tijdstip bereikt is. Zonder
platformtokens draait dit in **dry-run**; met tokens publiceert het echt.

Handmatig testen:

```bash
php scheduler.php
```

Periodiek laten draaien (elke 5 minuten):

**Windows Taakplanner**
- Programma/script: `php`
- Argumenten: `C:\Projects\WebSiteVoorJou\scheduler.php`
- Trigger: herhaal elke 5 minuten.

**Linux/cron** (typisch op de productieserver)
```cron
*/5 * * * * php /pad/naar/WebsiteVoorJou/scheduler.php >> /pad/naar/WebsiteVoorJou/storage/scheduler.log 2>&1
```

**Plesk** (jouw host) → *Websites & Domains → Scheduled Tasks → Add Task*
- Type: **Run a command** (of "Run a PHP script")
- Command: `php /var/www/vhosts/websitevoorjou.nl/httpdocs/scheduler.php`
- Interval: elke 5 minuten (`*/5 * * * *`)

Een lockbestand (`storage/scheduler.lock`) voorkomt dubbele verwerking bij overlappende runs.

### Alternatief: aanroepen via een URL (geen CLI-cron nodig)

Werkt CLI-cron niet, of wil je Plesk's *"Fetch a URL"* of een externe cron-dienst (bijv.
cron-job.org) gebruiken? Dan kan de scheduler ook via een beveiligde URL:

1. Zet een geheime sleutel in `config.local.php` (lokaal) of `config.secret.php` (productie):
   ```php
   define('SCHEDULER_KEY', 'kies-hier-een-lang-willekeurig-geheim');
   ```
2. Laat elke 5 minuten deze URL ophalen:
   ```
   https://websitevoorjou.nl/scheduler.php?key=kies-hier-een-lang-willekeurig-geheim
   ```

Zonder geldige `key` weigert het script (403). Zonder ingestelde `SCHEDULER_KEY` is de URL-route
volledig geblokkeerd en werkt alleen de command line.

> Deel de URL met sleutel niet publiek. Gebruik HTTPS (zodat de sleutel versleuteld gaat).

---

## 7. Veiligheid — checklist

- Sleutels staan in `config.local.php`, niet in `config.php`, en `config.local.php` staat in `.gitignore`.
- Tokens worden nooit gelogd of in foutmeldingen getoond (alleen de API-boodschap).
- Stond een sleutel ooit in een gedeeld/gecommit bestand? **Roteer** hem in het betreffende dashboard.
- Beperk Meta-tokens tot de minimale permissies en gebruik langlevende Page-tokens.

---

## 8. Snel overzicht — wat doet wat

| Functie | Werkt zonder sleutel | Sleutel nodig voor |
|--------|----------------------|--------------------|
| Prompt genereren (copy-paste) | ✅ | — |
| "⚡ Genereren met AI" | — | `OPENAI_API_KEY` |
| Post opslaan / inplannen | ✅ | — |
| Scheduler (dry-run) | ✅ | — |
| Echt publiceren LinkedIn | — | `LINKEDIN_*` |
| Echt publiceren Facebook | — | `FACEBOOK_*` |
| Echt publiceren Instagram | — | `INSTAGRAM_*` (+ afbeelding-URL per post) |
