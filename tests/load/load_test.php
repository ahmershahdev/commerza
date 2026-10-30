<?php

declare(strict_types=1);

/**
 * Concurrent load / stress test: every virtual user has its own cookie session and runs a
 * different customer journey in a loop (browse, search, product detail, cart, wishlist, auth pages ...).
 *
 * Usage:
 *   php tests/load/load_test.php [base-url] [users=15] [seconds=60]
 *   php tests/load/load_test.php http://localhost:8080 15 60      # load test (15 people)
 *   php tests/load/load_test.php http://localhost:8080 60 60      # stress test
 *   php tests/load/load_test.php http://localhost:8080 15 1       # smoke (one pass per journey)
 *
 * Exits 1 when the error rate exceeds 1% or p95 latency exceeds COMMERZA_LOAD_P95_MS (default 2000).
 */

$base = rtrim($argv[1] ?? 'http://localhost:8080', '/');
$users = max(1, (int)($argv[2] ?? 15));
$duration = max(1, (int)($argv[3] ?? 60));
$p95Budget = (int)(getenv('COMMERZA_LOAD_P95_MS') ?: 2000);

$journeys = [
    'home-browser' => [['GET', '/'], ['GET', '/about'], ['GET', '/faq']],
    'category-a-shopper' => [['GET', '/shop-category-a'], ['GET', '/products/louis-vuitton-escale-automatic-39mm-platinum']],
    'category-b-shopper' => [['GET', '/shop-category-b'], ['GET', '/products']],
    'searcher' => [['API', '/backend/api/products_api.php?action=suggest&q=automatic&limit=6'], ['API', '/backend/api/products_api.php?action=suggest&q=smart&limit=6']],
    'catalog-api' => [['API', '/backend/api/products_api.php?action=sections']],
    'cart-builder' => [['GET', '/cart'], ['CART', 5], ['CART', 12], ['API', '/backend/api/cart_api.php?action=status']],
    'cart-heavy' => [['CART', 25], ['CART', 25], ['GET', '/cart']],
    'wishlist-visitor' => [['GET', '/wishlist'], ['API', '/backend/api/wishlist_api.php?action=status']],
    'compare-user' => [['GET', '/compare'], ['GET', '/products']],
    'reader' => [['GET', '/shipping'], ['GET', '/returns'], ['GET', '/warranty']],
    'legal-reader' => [['GET', '/privacy-policy'], ['GET', '/terms-of-service']],
    'support-seeker' => [['GET', '/contact'], ['GET', '/order-tracking']],
    'new-customer' => [['GET', '/signup'], ['GET', '/login']],
    'password-reset' => [['GET', '/forgot-password'], ['GET', '/reset-password']],
    'review-reader' => [['API', '/backend/api/reviews_api.php?action=list&product_id=5'], ['API', '/backend/api/viewers_api.php?action=count&product_id=5']],
];
$journeyNames = array_keys($journeys);

$vus = [];
for ($i = 0; $i < $users; $i++) {
    $name = $journeyNames[$i % count($journeyNames)];
    $vus[$i] = ['journey' => $name, 'step' => 0, 'jar' => tempnam(sys_get_temp_dir(), 'cmrzvu'), 'csrf' => '', 'loops' => 0];
}

function vu_handle(array &$vu, array $journeys, string $base)
{
    $step = $journeys[$vu['journey']][$vu['step']];
    [$kind, $target] = $step;

    if ($kind === 'CART' && $vu['csrf'] === '') {
        $kind = 'API';
        $target = '/backend/api/cart_api.php?action=status';
    }

    $ch = curl_init();
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIEFILE => $vu['jar'],
        CURLOPT_COOKIEJAR => $vu['jar'],
        CURLOPT_FOLLOWLOCATION => false,
    ];

    if ($kind === 'CART') {
        $opts[CURLOPT_URL] = $base . '/backend/api/cart_api.php';
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query(['action' => 'add', 'product_id' => $target, 'quantity' => 1, 'csrf_token' => $vu['csrf']]);
        $opts[CURLOPT_HTTPHEADER] = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
        $label = 'POST cart add';
    } elseif ($kind === 'API') {
        $opts[CURLOPT_URL] = $base . $target;
        $opts[CURLOPT_HTTPHEADER] = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
        $label = 'API ' . strtok($target, '?');
    } else {
        $opts[CURLOPT_URL] = $base . $target;
        $opts[CURLOPT_HTTPHEADER] = ['Accept: text/html'];
        $label = 'GET ' . $target;
    }

    curl_setopt_array($ch, $opts);
    return [$ch, $label];
}

$stats = [];
$latencies = [];
$errors = 0;
$total = 0;
$mh = curl_multi_init();
$active = [];
$deadline = microtime(true) + $duration;
$smoke = $duration <= 1;

$schedule = static function (int $i) use (&$vus, &$active, $mh, $journeys, $base): void {
    [$ch, $label] = vu_handle($vus[$i], $journeys, $base);
    curl_multi_add_handle($mh, $ch);
    $active[(int)spl_object_id($ch)] = ['vu' => $i, 'label' => $label, 'start' => microtime(true), 'ch' => $ch];
};

foreach (array_keys($vus) as $i) {
    $schedule($i);
}

printf("Commerza load test: %d users, %ds, target %s\n", $users, $duration, $base);

while ($active !== []) {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.05);

    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        $meta = $active[(int)spl_object_id($ch)];
        unset($active[(int)spl_object_id($ch)]);

        $ms = (microtime(true) - $meta['start']) * 1000;
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $body = (string)curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $vu = &$vus[$meta['vu']];
        if ($vu['csrf'] === '' && preg_match('/"csrf_token"\s*:\s*"([a-f0-9]{32,})"/', $body, $m)) {
            $vu['csrf'] = $m[1];
        }

        // 2xx and expected redirects/limits are healthy; 5xx and transport failures are errors.
        $ok = $info['result'] === CURLE_OK && $status > 0 && $status < 500;
        $total++;
        $errors += $ok ? 0 : 1;
        $latencies[] = $ms;
        $stats[$meta['label']]['n'] = ($stats[$meta['label']]['n'] ?? 0) + 1;
        $stats[$meta['label']]['err'] = ($stats[$meta['label']]['err'] ?? 0) + ($ok ? 0 : 1);
        $stats[$meta['label']]['ms'][] = $ms;
        $stats[$meta['label']]['codes'][$status] = ($stats[$meta['label']]['codes'][$status] ?? 0) + 1;

        $vu['step']++;
        if ($vu['step'] >= count($journeys[$vu['journey']])) {
            $vu['step'] = 0;
            $vu['loops']++;
        }
        $done = $smoke ? $vu['loops'] >= 1 : microtime(true) >= $deadline;
        if (!$done) {
            $schedule($meta['vu']);
        }
        unset($vu);
    }
}
curl_multi_close($mh);

$pct = static function (array $values, float $p): float {
    sort($values);
    return $values === [] ? 0.0 : $values[(int)min(count($values) - 1, floor($p * count($values)))];
};

printf("\n%-62s %6s %5s %8s %8s  %s\n", 'Endpoint', 'Reqs', 'Err', 'p50 ms', 'p95 ms', 'Status codes');
ksort($stats);
foreach ($stats as $label => $s) {
    $codes = implode(' ', array_map(static fn($c, $n) => "{$c}x{$n}", array_keys($s['codes']), $s['codes']));
    printf("%-62s %6d %5d %8.0f %8.0f  %s\n", $label, $s['n'], $s['err'], $pct($s['ms'], 0.5), $pct($s['ms'], 0.95), $codes);
}

$elapsed = max(1, $duration);
$errorRate = $total > 0 ? $errors / $total * 100 : 100;
$p95 = $pct($latencies, 0.95);
printf("\nTotal: %d requests, %.1f req/s, errors %.2f%%, p50 %.0f ms, p95 %.0f ms, p99 %.0f ms\n",
    $total, $smoke ? $total : $total / $elapsed, $errorRate, $pct($latencies, 0.5), $p95, $pct($latencies, 0.99));

foreach ($vus as $vu) {
    @unlink($vu['jar']);
}

$pass = $errorRate <= 1.0 && ($smoke || $p95 <= $p95Budget);
echo $pass ? "RESULT: PASS\n" : "RESULT: FAIL (budget: errors <= 1%, p95 <= {$p95Budget} ms)\n";
exit($pass ? 0 : 1);
