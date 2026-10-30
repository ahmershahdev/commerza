<?php

declare(strict_types=1);

/**
 * Regenerate sitemap.xml with static pages and every live product.
 *
 * Usage: php scripts/quality/generate_sitemap.php [https://commerza.ahmershah.dev]
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/../../backend/core/data.php';

$base = rtrim($argv[1] ?? 'https://commerza.ahmershah.dev', '/');
$today = date('Y-m-d');
$static = [
    '/' => ['daily', '1.0'], '/products' => ['daily', '0.9'], '/shop-category-a' => ['weekly', '0.9'],
    '/shop-category-b' => ['weekly', '0.9'], '/about' => ['monthly', '0.6'], '/contact' => ['monthly', '0.6'],
    '/faq' => ['monthly', '0.6'], '/shipping' => ['monthly', '0.5'], '/returns' => ['monthly', '0.5'],
    '/warranty' => ['monthly', '0.5'], '/privacy-policy' => ['yearly', '0.3'], '/terms-of-service' => ['yearly', '0.3'],
];

$xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
foreach ($static as $path => [$freq, $priority]) {
    $xml[] = "  <url><loc>{$base}{$path}</loc><lastmod>{$today}</lastmod><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>";
}

$result = $con->query("SELECT slug, DATE(updated_at) AS updated FROM products WHERE slug IS NOT NULL AND slug <> '' AND deleted_at IS NULL ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $loc = htmlspecialchars($base . '/products/' . $row['slug'], ENT_XML1);
    $xml[] = "  <url><loc>{$loc}</loc><lastmod>{$row['updated']}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>";
}
$xml[] = '</urlset>';

file_put_contents(dirname(__DIR__, 2) . '/sitemap.xml', implode("\n", $xml) . "\n");
echo 'sitemap.xml written with ' . (count($xml) - 3) . " URLs\n";
