<?php

declare(strict_types=1);

/**
 * SEO audit for public storefront routes.
 *
 * Usage: php scripts/quality/seo_audit.php [base-url]
 * Exits non-zero when a page fails a hard rule (title <= 60, description <= 150, canonical, JSON-LD, single h1).
 */

$base = rtrim($argv[1] ?? 'http://localhost/commerza', '/');
$routes = ['/', '/products', '/products/louis-vuitton-escale-automatic-39mm-platinum', '/shop-category-a', '/shop-category-b',
    '/about', '/contact', '/faq', '/shipping', '/returns', '/warranty', '/privacy-policy', '/terms-of-service'];

$failures = 0;
foreach ($routes as $route) {
    $ctx = stream_context_create(['http' => ['header' => "Accept: text/html\r\n", 'ignore_errors' => true, 'timeout' => 20]]);
    $html = (string)@file_get_contents($base . $route, false, $ctx);
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xp = new DOMXPath($doc);

    $title = trim((string)($xp->query('//title')->item(0)?->textContent ?? ''));
    $desc = trim((string)($xp->query('//meta[@name="description"]/@content')->item(0)?->nodeValue ?? ''));
    $canonical = (string)($xp->query('//link[@rel="canonical"]/@href')->item(0)?->nodeValue ?? '');
    $h1 = $xp->query('//h1')->length;
    $og = $xp->query('//meta[starts-with(@property,"og:")]')->length;
    $twitter = $xp->query('//meta[starts-with(@name,"twitter:")]')->length;
    $breadcrumb = $xp->query('//nav[contains(@aria-label,"readcrumb")]')->length;
    $imgNoAlt = $xp->query('//img[not(@alt) or @alt=""]')->length;
    $internal = $xp->query('//a[@href and not(starts-with(@href,"http")) and not(starts-with(@href,"#")) and not(starts-with(@href,"mailto")) and not(starts-with(@href,"tel"))]')->length;

    $ldTypes = [];
    $ldInvalid = 0;
    foreach ($xp->query('//script[@type="application/ld+json"]') as $node) {
        $data = json_decode($node->textContent, true);
        if (!is_array($data)) {
            $ldInvalid++;
            continue;
        }
        foreach (($data['@graph'] ?? [$data]) as $item) {
            $ldTypes[] = is_array($item['@type'] ?? null) ? implode('/', $item['@type']) : (string)($item['@type'] ?? '?');
        }
    }

    $problems = [];
    if ($title === '' || mb_strlen($title) > 60) $problems[] = 'title ' . mb_strlen($title);
    if ($desc === '' || mb_strlen($desc) > 150) $problems[] = 'desc ' . mb_strlen($desc);
    if ($canonical === '') $problems[] = 'no canonical';
    if ($ldTypes === [] || $ldInvalid > 0) $problems[] = 'json-ld';
    if ($h1 !== 1) $problems[] = "h1=$h1";
    $failures += $problems === [] ? 0 : 1;

    printf("%-58s %s | t=%d d=%d og=%d tw=%d bc=%d links=%d noalt=%d ld=[%s]\n",
        $route, $problems === [] ? 'OK  ' : 'FAIL', mb_strlen($title), mb_strlen($desc), $og, $twitter, $breadcrumb,
        $internal, $imgNoAlt, implode(',', array_unique($ldTypes)));
    if ($problems !== []) echo '    -> ' . implode('; ', $problems) . "\n";
}

exit($failures > 0 ? 1 : 0);
