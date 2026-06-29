<?php
/**
 * Social media post-hulp voor de klant.
 * Zelfstandig in WebsiteVoorJou: platform-parameters en prompt-templates zijn
 * overgenomen/aangepast uit de SMM-tool (config/platforms.php, src/PromptBuilder.php),
 * zodat dit op de live server werkt zonder externe afhankelijkheden.
 *
 * Werkt standaard in copy-paste modus (genereert een prompt). Zodra er een
 * OPENAI_API_KEY in de config staat, kan dezelfde prompt ook live worden
 * gegenereerd via socialGenerate().
 */

/**
 * Platform-parameters per social-media-account.
 * Bron: SMM "Claude als Social Media Manager" — Module 2 & 3.
 */
function socialPlatforms(): array {
    return [
        'linkedin' => [
            'label' => 'LinkedIn', 'max_chars' => 3000, 'hashtags' => '3-5 hashtags',
            'notes' => 'Hook in regel 1 · 3-5 regels dan "meer lezen" · beloont lange verhalen',
            'prompt' => 'Schrijf een verhaal in 5 alinea\'s met een persoonlijke opening en professionele CTA.',
            'best_times' => 'di–do 08:00–10:00 en 17:00–18:00',
        ],
        'x' => [
            'label' => 'X / Twitter', 'max_chars' => 280, 'hashtags' => 'geen hashtags of max 1',
            'notes' => 'Eén scherpe gedachte · thread als het langer is',
            'prompt' => 'Schrijf een scherpe stelling van max 240 tekens — geen clichés, geen vulling.',
            'best_times' => 'ma–vr 09:00, 12:00 en 17:00',
        ],
        'instagram' => [
            'label' => 'Instagram', 'max_chars' => 2200, 'hashtags' => '5-15 hashtags in de comment',
            'notes' => 'Eerste 125 tekens zijn cruciaal · leeft op hooks',
            'prompt' => 'Schrijf een hook (regel 1) + uitleg + hashtags in reactie-formaat.',
            'best_times' => 'di en vr 09:00–11:00, za 10:00–13:00',
        ],
        'threads' => [
            'label' => 'Threads', 'max_chars' => 500, 'hashtags' => 'geen hashtags',
            'notes' => 'Conversationeel · reactie uitlokken',
            'prompt' => 'Schrijf een conversationele post alsof je het aan een vriend vertelt, eindig met een vraag (max 450 tekens).',
            'best_times' => 'ma–vr 10:00–12:00 en 19:00–21:00',
        ],
        'bluesky' => [
            'label' => 'Bluesky', 'max_chars' => 300, 'hashtags' => 'geen hashtags',
            'notes' => 'Vergelijkbaar met vroeger Twitter · authentic first',
            'prompt' => 'Schrijf authentiek en direct, max 280 tekens, geen marketing-taal.',
            'best_times' => 'geen algoritme — consistentie boven timing',
        ],
        'facebook' => [
            'label' => 'Facebook', 'max_chars' => 63206, 'hashtags' => 'optioneel',
            'notes' => 'Langere posts presteren beter · stel een vraag aan het einde',
            'prompt' => 'Schrijf een langere post met een vraag op het einde die reacties uitlokt.',
            'best_times' => 'di–do 13:00–16:00',
        ],
        'tiktok' => [
            'label' => 'TikTok', 'max_chars' => 2200, 'hashtags' => '3-5 trending hashtags',
            'notes' => 'Alleen caption · hook in eerste zin',
            'prompt' => 'Schrijf alleen de caption: hook + context in max 150 tekens + 3 hashtags.',
            'best_times' => 'ma–zo 18:00–22:00',
        ],
        'youtube' => [
            'label' => 'YouTube', 'max_chars' => 5000, 'hashtags' => 'max 3 in de beschrijving',
            'notes' => 'Eerste 100 tekens tellen · keywords vroeg · timestamps includeren',
            'prompt' => 'Schrijf de video-beschrijving: eerste zin = samenvatting, dan keywords, dan timestamps.',
            'best_times' => 'do–vr 15:00–17:00, za–zo 10:00–12:00',
        ],
        'pinterest' => [
            'label' => 'Pinterest', 'max_chars' => 500, 'hashtags' => 'keywords als zinnen, niet als tags',
            'notes' => 'Actionable taal · zoekwoordgedreven',
            'prompt' => 'Schrijf een beschrijving met actionable taal en zoekwoorden als zinnen (max 500 tekens).',
            'best_times' => 'za–zo 20:00–23:00',
        ],
        'mastodon' => [
            'label' => 'Mastodon', 'max_chars' => 500, 'hashtags' => 'enkele, optioneel',
            'notes' => 'Geen algoritme · authentiek en communitygedreven',
            'prompt' => 'Schrijf authentiek en gemeenschapsgericht, max 450 tekens.',
            'best_times' => 'geen algoritme — consistentie boven timing',
        ],
    ];
}

/** Doel-opties voor een post. */
function socialGoals(): array {
    return ['engagement' => 'Engagement', 'awareness' => 'Naamsbekendheid', 'verkoop' => 'Verkoop / leads', 'educatie' => 'Educatie / uitleg'];
}

/**
 * Bouwt de system prompt (tone of voice) op uit de merk-/projectgegevens.
 * $brand: business, audience, voice, pillars, example (mogen leeg zijn).
 */
function socialSystemPrompt(string $companyName, array $brand): string {
    $business = trim($brand['business'] ?? '') ?: 'Niet gespecificeerd.';
    $audience = trim($brand['audience'] ?? '') ?: 'Niet gespecificeerd.';
    $voice    = trim($brand['voice'] ?? '') ?: 'Toegankelijk, helder en betrouwbaar; geen jargon.';
    $pillars  = trim($brand['pillars'] ?? '');
    $example  = trim($brand['example'] ?? '');

    $out  = "Je bent de social media manager van {$companyName}.\n";
    $out .= "Je schrijft altijd in de unieke stem van dit bedrijf.\n\n";
    $out .= "OVER HET BEDRIJF:\n";
    $out .= "Naam: {$companyName}\n";
    $out .= "Wat we doen: {$business}\n";
    $out .= "Doelgroep: {$audience}\n\n";
    $out .= "TONE OF VOICE:\n{$voice}\n";
    if ($pillars !== '') {
        $out .= "\nCONTENTPIJLERS (thema's waarover we posten):\n{$pillars}\n";
    }
    if ($example !== '') {
        $out .= "\nVOORBEELDPOST (zo klinken onze beste posts):\n{$example}\n";
        $out .= "Schrijf altijd in de toon en stijl van dit voorbeeld.\n";
    }
    $out .= "\nSchrijf in het Nederlands, tenzij anders gevraagd. Vraag bij onduidelijkheid — gok nooit over merk of boodschap.";
    return $out;
}

/** Module 2 — platform-specifieke post met 3 varianten. */
function socialPromptPlatformPost(array $p, string $onderwerp, string $doel, string $cta): string {
    return "Schrijf een post voor {$p['label']} over het volgende onderwerp:\n"
        . "Onderwerp: {$onderwerp}\n"
        . "Doel van deze post: {$doel}\n"
        . "CTA: {$cta}\n"
        . "Platform-instructie: {$p['prompt']}\n"
        . "Max tekens: {$p['max_chars']} · Hashtags: {$p['hashtags']}\n\n"
        . "Gebruik altijd onze tone of voice (zie systeeminstructies).\n"
        . "Geef 3 varianten: A (informatief) / B (persoonlijk verhaal) / C (uitdagend/provocatief).\n"
        . "Variant C mag iets scherper. Houd je aan de tekenlimiet.";
}

/** Module 4 — één idee → posts voor de gekozen platforms. */
function socialPromptMultiply(string $idee, array $platformKeys): string {
    $all = socialPlatforms();
    $lines = [];
    $n = 1;
    foreach ($platformKeys as $k) {
        if (!isset($all[$k])) continue;
        $p = $all[$k];
        $lines[] = "{$n}. " . strtoupper($p['label']) . " — {$p['prompt']} (max {$p['max_chars']} tekens, hashtags: {$p['hashtags']})";
        $n++;
    }
    $list = implode("\n", $lines);
    return "Ik heb het volgende idee/inzicht/resultaat:\n{$idee}\n\n"
        . "Maak hier unieke platformposts van — één per platform hieronder:\n{$list}\n\n"
        . "Elk format heeft zijn eigen taal — gebruik NOOIT dezelfde tekst.\n"
        . "Alle posts moeten onze tone of voice hebben en binnen de tekenlimiet blijven.";
}

/** Module 3 — volledige contentweek. $freq: platform-key => posts per week. */
function socialPromptWeek(string $datum, array $freq, string $thema, string $evenementen): string {
    $all = socialPlatforms();
    $actief = [];
    $freqRegels = [];
    foreach ($freq as $k => $perWeek) {
        if (!isset($all[$k]) || (int)$perWeek < 1) continue;
        $p = $all[$k];
        $actief[] = $p['label'];
        $freqRegels[] = "{$p['label']}: {$perWeek}×/week (beste tijden: {$p['best_times']})";
    }
    $actiefStr = implode(', ', $actief) ?: 'geen';
    $freqStr   = implode("\n", $freqRegels) ?: 'geen';
    $thema     = $thema !== '' ? $thema : 'geen specifiek thema';
    $even      = $evenementen !== '' ? $evenementen : 'geen';

    return "Plan mijn volledige contentweek voor de week van {$datum}.\n"
        . "Actieve platforms: {$actiefStr}\n\n"
        . "Postfrequentie per platform:\n{$freqStr}\n\n"
        . "Thema van deze week: {$thema}\n"
        . "Evenementen of hooks deze week: {$even}\n\n"
        . "Geef een weekoverzicht per dag in dit format:\n"
        . "MAANDAG:\n[PLATFORM] ([TIJD]): [ONDERWERP + doel]\nDINSDAG:\n[...] etc.\n\n"
        . "Zorg voor: spreiding over thema's, variatie in format (tekst/vraag/lijst/verhaal), "
        . "geen dubbele boodschappen op dezelfde dag, en de beste posttijden per platform.\n"
        . "Schrijf daarna direct de volledige post voor maandag uit.";
}

/** Is live AI-generatie geconfigureerd? */
function socialAiEnabled(): bool {
    return defined('OPENAI_API_KEY') && OPENAI_API_KEY !== '';
}

/**
 * Live generatie via OpenAI (chat completions) — zelfstandig via cURL.
 * Geeft ['ok'=>bool, 'text'=>string, 'error'=>string] terug.
 */
function socialGenerate(string $systemPrompt, string $userPrompt): array {
    $out = ['ok' => false, 'text' => '', 'error' => ''];
    if (!socialAiEnabled()) {
        $out['error'] = 'Live generatie staat uit. Vul een OPENAI_API_KEY in de config in.';
        return $out;
    }
    if (!function_exists('curl_init')) {
        $out['error'] = 'De server kan geen API-aanroepen doen (curl ontbreekt).';
        return $out;
    }

    $payload = json_encode([
        'model'       => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => 0.8,
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        $out['error'] = 'Verbinding met de AI-service mislukt.' . ($err ? ' (' . $err . ')' : '');
        return $out;
    }
    $data = json_decode($resp, true);
    if ($code >= 400) {
        // Toon alleen de boodschap, nooit request-details die de key kunnen bevatten.
        $out['error'] = 'AI-fout: ' . ($data['error']['message'] ?? ('HTTP ' . $code));
        return $out;
    }
    $text = $data['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        $out['error'] = 'De AI gaf geen tekst terug.';
        return $out;
    }
    $out['ok']   = true;
    $out['text'] = trim($text);
    return $out;
}

/* ============================================================
 *  Publiceren & inplannen
 * ============================================================ */

/** Is er een echte publisher geconfigureerd voor dit platform? (anders dry-run) */
function socialPublisherEnabled(string $platform): bool {
    switch ($platform) {
        case 'linkedin':
            return defined('LINKEDIN_ACCESS_TOKEN') && LINKEDIN_ACCESS_TOKEN !== ''
                && defined('LINKEDIN_AUTHOR_URN') && LINKEDIN_AUTHOR_URN !== '';
        case 'facebook':
            return defined('FACEBOOK_PAGE_ID') && FACEBOOK_PAGE_ID !== ''
                && defined('FACEBOOK_ACCESS_TOKEN') && FACEBOOK_ACCESS_TOKEN !== '';
        case 'instagram':
            return defined('INSTAGRAM_USER_ID') && INSTAGRAM_USER_ID !== ''
                && defined('INSTAGRAM_ACCESS_TOKEN') && INSTAGRAM_ACCESS_TOKEN !== '';
        default:
            return false;
    }
}

/** Vereist dit platform een afbeelding om te kunnen publiceren? */
function socialRequiresImage(string $platform): bool {
    return $platform === 'instagram';
}

/**
 * Publiceert één post naar een platform.
 * Zonder geconfigureerde publisher: veilige dry-run (er gaat niets de deur uit).
 * Geeft ['ok'=>bool, 'dry_run'=>bool, 'message'=>string] terug.
 */
function socialPublish(string $platform, string $content, string $imageUrl = ''): array {
    if (!socialPublisherEnabled($platform)) {
        $all = socialPlatforms();
        $label = $all[$platform]['label'] ?? $platform;
        return ['ok' => true, 'dry_run' => true, 'message' => 'Dry-run: nog geen koppeling voor ' . $label . ' — niets gepubliceerd.'];
    }
    switch ($platform) {
        case 'linkedin':  return socialPublishLinkedIn($content);
        case 'facebook':  return socialPublishFacebook($content, $imageUrl);
        case 'instagram': return socialPublishInstagram($content, $imageUrl);
        default:          return ['ok' => false, 'dry_run' => false, 'message' => 'Onbekend platform: ' . $platform];
    }
}

/** Eenvoudige Graph API POST. Geeft [httpCode, decodedArray] terug. */
function socialGraphPost(string $path, array $params): array {
    $base = 'https://graph.facebook.com/' . (defined('META_GRAPH_VERSION') ? META_GRAPH_VERSION : 'v21.0') . '/';
    $ch = curl_init($base . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 40,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return [0, ['error' => ['message' => 'Verbinding mislukt' . ($err ? ': ' . $err : '')]]];
    }
    return [$code, json_decode($resp, true) ?: []];
}

/** Facebook Pagina: tekstpost via /feed, of foto via /photos als er een afbeelding is. */
function socialPublishFacebook(string $content, string $imageUrl = ''): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'dry_run' => false, 'message' => 'curl ontbreekt op de server.'];
    }
    if (trim($imageUrl) !== '') {
        [$code, $data] = socialGraphPost(FACEBOOK_PAGE_ID . '/photos', [
            'url'          => $imageUrl,
            'caption'      => $content,
            'access_token' => FACEBOOK_ACCESS_TOKEN,
        ]);
    } else {
        [$code, $data] = socialGraphPost(FACEBOOK_PAGE_ID . '/feed', [
            'message'      => $content,
            'access_token' => FACEBOOK_ACCESS_TOKEN,
        ]);
    }
    if ($code >= 200 && $code < 300 && (isset($data['id']) || isset($data['post_id']))) {
        return ['ok' => true, 'dry_run' => false, 'message' => 'Gepubliceerd op Facebook.'];
    }
    return ['ok' => false, 'dry_run' => false, 'message' => 'Facebook-fout: ' . ($data['error']['message'] ?? ('HTTP ' . $code))];
}

/** Instagram: tweetraps (container aanmaken → publiceren). Vereist een afbeelding-URL. */
function socialPublishInstagram(string $content, string $imageUrl = ''): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'dry_run' => false, 'message' => 'curl ontbreekt op de server.'];
    }
    if (trim($imageUrl) === '') {
        return ['ok' => false, 'dry_run' => false, 'message' => 'Instagram vereist een afbeelding-URL.'];
    }
    // Stap 1: media container
    [$code, $data] = socialGraphPost(INSTAGRAM_USER_ID . '/media', [
        'image_url'    => $imageUrl,
        'caption'      => $content,
        'access_token' => INSTAGRAM_ACCESS_TOKEN,
    ]);
    if ($code < 200 || $code >= 300 || empty($data['id'])) {
        return ['ok' => false, 'dry_run' => false, 'message' => 'Instagram-fout (container): ' . ($data['error']['message'] ?? ('HTTP ' . $code))];
    }
    $creationId = $data['id'];
    // Stap 2: publiceren
    [$code2, $data2] = socialGraphPost(INSTAGRAM_USER_ID . '/media_publish', [
        'creation_id'  => $creationId,
        'access_token' => INSTAGRAM_ACCESS_TOKEN,
    ]);
    if ($code2 >= 200 && $code2 < 300 && !empty($data2['id'])) {
        return ['ok' => true, 'dry_run' => false, 'message' => 'Gepubliceerd op Instagram.'];
    }
    return ['ok' => false, 'dry_run' => false, 'message' => 'Instagram-fout (publish): ' . ($data2['error']['message'] ?? ('HTTP ' . $code2))];
}

/** Daadwerkelijke LinkedIn-publicatie via de UGC Posts API. */
function socialPublishLinkedIn(string $content): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'dry_run' => false, 'message' => 'curl ontbreekt op de server.'];
    }
    $payload = json_encode([
        'author'          => LINKEDIN_AUTHOR_URN,
        'lifecycleState'  => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary'    => ['text' => $content],
                'shareMediaCategory' => 'NONE',
            ],
        ],
        'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . LINKEDIN_ACCESS_TOKEN,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'dry_run' => false, 'message' => 'Verbinding met LinkedIn mislukt.' . ($err ? ' (' . $err . ')' : '')];
    }
    if ($code === 201 || $code === 200) {
        return ['ok' => true, 'dry_run' => false, 'message' => 'Gepubliceerd op LinkedIn.'];
    }
    $data = json_decode($resp, true);
    $msg  = $data['message'] ?? ('HTTP ' . $code);
    return ['ok' => false, 'dry_run' => false, 'message' => 'LinkedIn-fout: ' . $msg];
}

/**
 * Publiceert één opgeslagen post (rij uit project_social_posts) en werkt de status bij.
 * Geeft de result-array van socialPublish terug.
 */
function socialPublishPost(PDO $db, array $post): array {
    $res = socialPublish($post['platform'], $post['content'], $post['image_url'] ?? '');
    if ($res['ok']) {
        $msg = $res['dry_run'] ? $res['message'] : 'Gepubliceerd.';
        $db->prepare("UPDATE project_social_posts SET status = 'geplaatst', posted_at = NOW(), result_msg = ? WHERE id = ?")
           ->execute([mb_substr($msg, 0, 255), $post['id']]);
    } else {
        $db->prepare("UPDATE project_social_posts SET status = 'mislukt', result_msg = ? WHERE id = ?")
           ->execute([mb_substr($res['message'], 0, 255), $post['id']]);
    }
    return $res;
}

/**
 * Verwerkt alle ingeplande posts waarvan het tijdstip bereikt is.
 * Gebruikt door scheduler.php. Geeft ['processed'=>int, 'published'=>int, 'failed'=>int, 'lines'=>[]] terug.
 */
function socialProcessDuePosts(PDO $db): array {
    $summary = ['processed' => 0, 'published' => 0, 'failed' => 0, 'lines' => []];
    $stmt = $db->query("SELECT * FROM project_social_posts WHERE status = 'ingepland' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() ORDER BY scheduled_at ASC");
    $due = $stmt->fetchAll();
    foreach ($due as $post) {
        $res = socialPublishPost($db, $post);
        $summary['processed']++;
        $res['ok'] ? $summary['published']++ : $summary['failed']++;
        $summary['lines'][] = sprintf(
            '#%d %s [%s] %s',
            $post['id'], $post['platform'],
            $res['ok'] ? ($res['dry_run'] ? 'DRY-RUN' : 'GEPLAATST') : 'MISLUKT',
            $res['message']
        );
    }
    return $summary;
}
