<?php

declare(strict_types=1);

/**
 * Deep security probe: headers/CSP, SQL injection, reflected XSS, path exposure,
 * rate limiting, and the upload malware/polyglot pipeline.
 *
 * Usage: php scripts/security/security_probe.php [base-url]
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/../../backend/core/data.php';
require_once __DIR__ . '/../../backend/helpers/media_image_helpers.php';

$base = rtrim($argv[1] ?? 'http://localhost/commerza', '/');
$results = [];
$probeJar = tempnam(sys_get_temp_dir(), 'cmrz');

function probe_request(string $url, string $method = 'GET', array $form = [], array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge($headers === [] ? ['Accept: application/json'] : [], $headers),
        CURLOPT_COOKIEFILE => $GLOBALS['probeJar'],
        CURLOPT_COOKIEJAR => $GLOBALS['probeJar'],
    ]);
    if ($form !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'headers' => strtolower(substr($raw, 0, $headerSize)), 'body' => substr($raw, $headerSize)];
}

function record(array &$results, string $name, bool $pass, string $detail): void
{
    $results[] = [$name, $pass, $detail];
}

// 1. Security headers and CSP nonce rotation
$a = probe_request($base . '/', 'GET', [], ['Accept: text/html']);
$b = probe_request($base . '/', 'GET', [], ['Accept: text/html']);
foreach (['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'cross-origin-opener-policy'] as $h) {
    record($results, "header:$h", str_contains($a['headers'], $h . ':'), $h);
}
preg_match("/'nonce-([^']+)'/", $a['headers'], $n1);
preg_match("/'nonce-([^']+)'/", $b['headers'], $n2);
record($results, 'csp:nonce-rotates', isset($n1[1], $n2[1]) && $n1[1] !== $n2[1] && strlen($n1[1]) >= 16, 'nonce differs per request');
record($results, 'csp:no-unsafe-inline-script', !preg_match("/script-src[^;]*'unsafe-inline'/", $a['headers']), "script-src without 'unsafe-inline'");
record($results, 'cookie:httponly-samesite', str_contains($a['headers'], 'httponly') && str_contains($a['headers'], 'samesite=lax'), 'session cookie flags');

// 2. SQL injection probes (must not error or leak SQL)
$sqli = ["' OR '1'='1", "1 UNION SELECT password_hash FROM users-- -", "1; DROP TABLE users;--", "\" OR sleep(3)-- -"];
foreach ($sqli as $i => $payload) {
    $start = microtime(true);
    $r1 = probe_request($base . '/backend/api/products_api.php?slug=' . rawurlencode($payload));
    $r2 = probe_request($base . '/products/' . rawurlencode($payload), 'GET', [], ['Accept: text/html']);
    $r3 = probe_request($base . '/backend/api/check_exists.php?field=email&value=' . rawurlencode($payload));
    $elapsed = microtime(true) - $start;
    $leak = preg_match('/sql syntax|mysqli|mariadb|uncaught|stack trace|\$2y\$/i', $r1['body'] . $r2['body'] . $r3['body']) === 1;
    record($results, "sqli:payload-$i", !$leak && $elapsed < 3 && $r1['status'] < 500 && $r2['status'] < 500 && $r3['status'] < 500, sprintf('%.2fs, statuses %d/%d/%d', $elapsed, $r1['status'], $r2['status'], $r3['status']));
}
$con->query('SELECT 1 FROM users LIMIT 1');
record($results, 'sqli:users-table-intact', $con->errno === 0, 'users table still exists');

// 3. Reflected XSS
$xss = '"><script>alert(1)</script><svg onload=alert(1)>';
foreach (['/products?q=', '/login?redirect=', '/order-tracking?order_id=', '/contact?subject='] as $route) {
    $r = probe_request($base . $route . rawurlencode($xss), 'GET', [], ['Accept: text/html']);
    record($results, 'xss:' . trim(strtok($route, '?'), '/'), !str_contains($r['body'], '<script>alert(1)</script>') && !str_contains($r['body'], '<svg onload=alert(1)>'), 'payload not reflected raw');
}

// 4. Sensitive path exposure
foreach (['/.env', '/.git/config', '/database/commerza.sql', '/backend/core/data.php', '/storage/mail/', '/scripts/dev/reset_admin_password.php', '/composer.json', '/templates/partials/site-header.php'] as $path) {
    $r = probe_request($base . $path, 'GET', [], ['Accept: text/html']);
    record($results, 'exposure:' . $path, in_array($r['status'], [403, 404], true), 'HTTP ' . $r['status']);
}
$r = probe_request($base . '/backend/api/products_api.php', 'TRACE');
// TRACE is answered by Apache core before .htaccess runs; disable with `TraceEnable Off` in httpd.conf (done in the Docker image).
record($results, 'method:trace-blocked', in_array($r['status'], [403, 405], true) || getenv('COMMERZA_PROBE_ALLOW_TRACE') === '1', 'HTTP ' . $r['status']);

// 5. Rate limiting / brute force on login (real session + CSRF token)
$page = probe_request($base . '/login', 'GET', [], ['Accept: text/html']);
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $page['body'], $tok);
$blocked = false;
for ($i = 1; $i <= 25 && !$blocked; $i++) {
    $r = probe_request($base . '/login', 'POST', ['csrf_token' => $tok[1] ?? '', 'user_login_identifier' => 'nobody@commerza.test', 'user_login_password' => 'Wrong-Pass-' . $i], ['Accept: text/html']);
    $blocked = $r['status'] === 429 || preg_match('/too many|try again|temporarily|rate limit|locked/i', $r['body']) === 1;
}
record($results, 'ratelimit:login-bruteforce', $blocked, $blocked ? "blocked at attempt {$i}" : 'not blocked in 25 attempts');

// 6. Upload pipeline: EICAR, PHP polyglot, script-in-image, real image
$tmp = sys_get_temp_dir();
$cases = [
    'eicar' => ['X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*', false],
    'php-polyglot' => ["GIF89a<?php system(\$_GET['c']); ?>", false],
    'svg-script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', false],
];
foreach ($cases as $name => [$content, $shouldPass]) {
    $file = $tmp . DIRECTORY_SEPARATOR . 'commerza-probe-' . $name . '.bin';
    file_put_contents($file, $content);
    $reason = null;
    $passed = commerza_upload_scan_file($file, $reason);
    @unlink($file);
    record($results, "upload:$name-rejected", $passed === $shouldPass, $reason ?: 'accepted');
}
$png = $tmp . DIRECTORY_SEPARATOR . 'commerza-probe-ok.png';
$img = imagecreatetruecolor(4, 4);
imagepng($img, $png);
$reason = null;
record($results, 'upload:clean-image-accepted', commerza_upload_scan_file($png, $reason), $reason ?: 'accepted');
@unlink($png);

// 7. Password hashing
$hash = commerza_password_hash('Probe-Pass-2026!');
record($results, 'hash:argon2id-or-bcrypt', str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$2y$12$'), substr($hash, 0, 10) . '...');

$failed = 0;
foreach ($results as [$name, $pass, $detail]) {
    printf("%-40s %s  %s\n", $name, $pass ? 'PASS' : 'FAIL', $detail);
    $failed += $pass ? 0 : 1;
}
printf("\nSummary: %d checks, %d failed\n", count($results), $failed);
exit($failed > 0 ? 1 : 0);
