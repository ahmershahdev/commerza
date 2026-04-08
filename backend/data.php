<?php

function commerza_unquote_env_value(string $value): string
{
    $trimmed = trim($value);
    $length = strlen($trimmed);

    if ($length >= 2) {
        $first = $trimmed[0];
        $last = $trimmed[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $trimmed = substr($trimmed, 1, -1);
        }
    }

    return str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $trimmed);
}

function commerza_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        $rawValue = substr($line, $position + 1);
        $value = commerza_unquote_env_value($rawValue);

        if ($value === '') {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function commerza_bootstrap_env(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $projectRoot = dirname(__DIR__);
    commerza_load_env_file($projectRoot . DIRECTORY_SEPARATOR . '.env');
    commerza_load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env');

    $loaded = true;
}

commerza_bootstrap_env();
require_once __DIR__ . '/cache_helpers.php';

function commerza_request_is_browser_navigation(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    $fetchDest = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
    $fetchMode = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '')));
    if ($fetchDest === 'document' || $fetchMode === 'navigate') {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept !== '' && str_contains($accept, 'text/html')) {
        return true;
    }

    return false;
}

function commerza_request_targets_system_backend_php(): bool
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') {
        return false;
    }

    return preg_match('#/(?:admin/)?backend/[^/]+\.php$#i', $scriptName) === 1;
}

function commerza_block_direct_system_backend_navigation(): void
{
    if (!commerza_request_targets_system_backend_php()) {
        return;
    }

    if (!commerza_request_is_browser_navigation()) {
        return;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

commerza_block_direct_system_backend_navigation();

function commerza_request_is_https(): bool
{
    $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $cfVisitor = strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));

    if ($https !== '' && $https !== 'off') {
        return true;
    }

    if ($forwardedProto !== '' && str_contains($forwardedProto, 'https')) {
        return true;
    }

    if ($cfVisitor !== '' && str_contains($cfVisitor, '"https"')) {
        return true;
    }

    return ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
}

$isHttps = commerza_request_is_https();

function commerza_csp_nonce_value(): string
{
    static $nonce = '';

    if ($nonce !== '') {
        return $nonce;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $existing = trim((string)($_SESSION['commerza_csp_nonce'] ?? ''));
        if ($existing !== '') {
            $nonce = $existing;
            return $nonce;
        }
    }

    try {
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    } catch (Throwable $exception) {
        $nonce = substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 24);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['commerza_csp_nonce'] = $nonce;
    }

    return $nonce;
}

function commerza_csp_nonce_attr(): string
{
    $nonce = commerza_csp_nonce_value();

    if ($nonce === '') {
        return '';
    }

    return 'nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
}

function commerza_content_security_policy_header(): string
{
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "manifest-src 'self'",
        "worker-src 'self' blob:",
        "img-src 'self' data: blob: https:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://js.stripe.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com https://maps.googleapis.com https://www.googletagmanager.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
        "connect-src 'self' https://api.stripe.com https://r.stripe.com https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com https://maps.googleapis.com https://maps.gstatic.com https://www.google-analytics.com https://www.googletagmanager.com",
        "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://www.google.com https://www.google.com/maps https://maps.google.com https://www.recaptcha.net https://challenges.cloudflare.com",
        "form-action 'self'",
    ];

    if (commerza_request_is_https()) {
        $directives[] = 'upgrade-insecure-requests';
    }

    return implode('; ', $directives);
}

function commerza_public_base_url(): string
{
    static $cached = null;

    if (is_string($cached)) {
        return $cached;
    }

    $configured = trim((string)(
        getenv('COMMERZA_APP_URL')
        ?: getenv('COMMERZA_PUBLIC_URL')
        ?: getenv('APP_URL')
        ?: ''
    ));

    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        $cached = rtrim($configured, '/');
        return $cached;
    }

    $isHttps = commerza_request_is_https();
    $scheme = $isHttps ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost')));
    if ($host === '') {
        $host = 'localhost';
    }

    $prefix = '';

    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projectRoot = realpath(dirname(__DIR__));

    if (is_string($docRoot) && $docRoot !== '' && is_string($projectRoot) && $projectRoot !== '') {
        $normalizedDocRoot = strtolower(str_replace('\\', '/', rtrim($docRoot, DIRECTORY_SEPARATOR)));
        $normalizedProjectRoot = strtolower(str_replace('\\', '/', rtrim($projectRoot, DIRECTORY_SEPARATOR)));

        if ($normalizedDocRoot !== '' && str_starts_with($normalizedProjectRoot, $normalizedDocRoot)) {
            $relative = trim(substr($projectRoot, strlen($docRoot)), "\\/");
            if ($relative !== '') {
                $prefix = '/' . str_replace('\\', '/', $relative);
            }
        }
    }

    if ($prefix === '') {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $normalizedScript = preg_replace('#/+#', '/', $scriptName);
        $normalizedScript = is_string($normalizedScript) ? trim($normalizedScript, '/') : trim($scriptName, '/');
        $segments = $normalizedScript === ''
            ? []
            : array_values(array_filter(explode('/', $normalizedScript), static function ($segment): bool {
                return $segment !== '';
            }));

        $isSafeSegment = static function (string $segment): bool {
            if ($segment === '') {
                return false;
            }

            if (preg_match('/^[a-z]:$/i', $segment) === 1) {
                return false;
            }

            if (str_contains($segment, ':')) {
                return false;
            }

            if (str_ends_with(strtolower($segment), '.php')) {
                return false;
            }

            return preg_match('/^[a-z0-9._-]+$/i', $segment) === 1;
        };

        $projectRootPath = realpath(dirname(__DIR__));
        $projectFolder = basename(is_string($projectRootPath) && $projectRootPath !== '' ? $projectRootPath : dirname(__DIR__));

        if ($projectFolder !== '') {
            foreach ($segments as $segment) {
                $candidate = (string)$segment;
                if ($isSafeSegment($candidate) && strcasecmp($candidate, $projectFolder) === 0) {
                    $prefix = '/' . trim($candidate);
                    break;
                }
            }
        }

        if ($prefix === '' && count($segments) >= 2) {
            $firstSegment = trim((string)$segments[0]);
            if ($isSafeSegment($firstSegment)) {
                $prefix = '/' . $firstSegment;
            }
        }
    }

    if ($prefix !== '' && str_contains($prefix, ':')) {
        $prefix = '';
    }

    $cached = $scheme . '://' . $host . $prefix;
    return $cached;
}

function commerza_absolute_url(string $path = ''): string
{
    $base = commerza_public_base_url();
    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function commerza_render_page_breadcrumb(string $currentLabel, array $parents = [], bool $includeHome = true): void
{
    static $stylePrinted = false;

    $current = trim($currentLabel);
    if ($current === '') {
        return;
    }

    $crumbs = [];

    if ($includeHome && strcasecmp($current, 'home') !== 0) {
        $crumbs[] = [
            'label' => 'Home',
            'href' => 'index.php',
        ];
    }

    foreach ($parents as $parent) {
        if (!is_array($parent)) {
            continue;
        }

        $label = trim((string)($parent['label'] ?? ''));
        $href = trim((string)($parent['href'] ?? ''));

        if ($label === '' || $href === '') {
            continue;
        }

        $crumbs[] = [
            'label' => $label,
            'href' => $href,
        ];
    }

    if (!$stylePrinted) {
        $stylePrinted = true;
        echo '<style>'
            . '.page-breadcrumb-shell{position:relative;z-index:2;display:block;margin-bottom:1rem;}'
            . '.page-breadcrumb{background:rgba(12,12,12,.66)!important;border:1px solid rgba(255,102,0,.28)!important;border-radius:999px!important;display:inline-flex!important;flex-wrap:wrap;margin:0;padding:7px 14px;box-shadow:0 8px 24px rgba(0,0,0,.28);}'
            . '.page-breadcrumb .breadcrumb-item,.page-breadcrumb .breadcrumb-item a{color:#ffc898!important;text-decoration:none!important;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;}'
            . '.page-breadcrumb .breadcrumb-item.active{color:#ffe9d4!important;}'
            . '.page-breadcrumb .breadcrumb-item+.breadcrumb-item::before{color:rgba(255,215,176,.78)!important;}'
            . '@media (max-width:575.98px){.page-breadcrumb{border-radius:14px!important;padding:7px 10px;}.page-breadcrumb .breadcrumb-item,.page-breadcrumb .breadcrumb-item a{font-size:.68rem;letter-spacing:.04em;}}'
            . '</style>';
    }

    echo '<section class="page-breadcrumb-shell mb-3">';
    echo '<nav aria-label="Breadcrumb">';
    echo '<ol class="breadcrumb page-breadcrumb mb-0">';

    foreach ($crumbs as $crumb) {
        $label = htmlspecialchars((string)$crumb['label'], ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars((string)$crumb['href'], ENT_QUOTES, 'UTF-8');
        echo '<li class="breadcrumb-item"><a href="' . $href . '">' . $label . '</a></li>';
    }

    echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($current, ENT_QUOTES, 'UTF-8') . '</li>';
    echo '</ol>';
    echo '</nav>';
    echo '</section>';
}

function commerza_is_backend_request(): bool
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return str_contains($script, '/backend/') || str_contains($script, '/admin/backend/');
}

function commerza_clean_route_map(): array
{
    static $map = null;

    if (is_array($map)) {
        return $map;
    }

    $map = [
        'index.php' => '/home',
        '404.php' => '/error',
        'about.php' => '/about',
        'contact.php' => '/contact',
        'faq.php' => '/faq',
        'shipping.php' => '/shipping',
        'returns.php' => '/returns',
        'privacy-policy.php' => '/privacy-policy',
        'terms-of-service.php' => '/terms-of-service',
        'warranty.php' => '/warranty',
        'shop-category-a.php' => '/shop-category-a',
        'shop-category-b.php' => '/shop-category-b',
        'products.php' => '/products',
        'cart.php' => '/cart',
        'wishlist.php' => '/wishlist',
        'compare.php' => '/compare',
        'login.php' => '/login',
        'signup.php' => '/signup',
        'forgot-password.php' => '/forgot-password',
        'reset-password.php' => '/reset-password',
        'order-tracking.php' => '/order-tracking',
        'account.php' => '/account',
        'invoice.php' => '/invoice',
        'oauth.php' => '/oauth',
        'admin-login.php' => '/admin-login',
        'admin-panel.php' => '/admin-panel',
        'admin-forgot-password.php' => '/admin-forgot-password',
        'admin-forgot-email.php' => '/admin-forgot-email',
        'admin-verify-2fa.php' => '/admin-verify-2fa',
    ];

    return $map;
}

function commerza_maybe_redirect_clean_route(): void
{
    if (PHP_SAPI === 'cli' || headers_sent() || commerza_is_backend_request()) {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return;
    }

    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
    if ($requestPath === '' || !str_ends_with(strtolower($requestPath), '.php')) {
        return;
    }

    $filename = strtolower(basename($requestPath));
    $routeMap = commerza_clean_route_map();
    if (!isset($routeMap[$filename])) {
        return;
    }

    if ($filename === 'products.php') {
        $hasSlugQuery = isset($_GET['slug']) && trim((string)$_GET['slug']) !== '';
        if ($hasSlugQuery) {
            return;
        }
    }

    if ($filename === 'account.php' && isset($_GET['u']) && trim((string)$_GET['u']) !== '') {
        return;
    }

    $targetUrl = commerza_absolute_url($routeMap[$filename]);
    $queryString = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
    if ($queryString !== '') {
        $targetUrl .= '?' . $queryString;
    }

    $normalizePath = static function (string $path): string {
        $normalized = '/' . trim(str_replace('\\', '/', $path), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;
        return strtolower(rtrim($normalized, '/'));
    };

    $targetPath = (string)(parse_url($targetUrl, PHP_URL_PATH) ?? '');
    if ($normalizePath($requestPath) === $normalizePath($targetPath)) {
        return;
    }

    header('Location: ' . $targetUrl, true, 301);
    exit;
}

function commerza_is_sensitive_cache_page(): bool
{
    $sensitive = [
        'login.php',
        'signup.php',
        'forgot-password.php',
        'reset-password.php',
        'account.php',
        'cart.php',
        'wishlist.php',
        'compare.php',
        'order-tracking.php',
        'oauth.php',
        'admin-login.php',
        'admin-forgot-password.php',
        'admin-forgot-email.php',
        'admin-verify-2fa.php',
        'admin-panel.php',
    ];

    $script = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return in_array($script, $sensitive, true);
}

function commerza_apply_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $hasAuthenticatedSession = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);

    $noStore = $method !== 'GET'
        || commerza_is_backend_request()
        || commerza_is_sensitive_cache_page()
        || $hasAuthenticatedSession;

    if ($noStore) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        return;
    }

    header('Cache-Control: public, max-age=900, s-maxage=900, stale-while-revalidate=1800, stale-if-error=86400');
    header('Surrogate-Control: max-age=900');
    header('Vary: Accept-Encoding, Accept');
}

function commerza_local_vendor_prefix(): string
{
    static $prefix = null;

    if (is_string($prefix)) {
        return $prefix;
    }

    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $prefix = str_contains($script, '/admin/frontend/') ? '../../' : '';

    return $prefix;
}

function commerza_cdn_fallback_asset_for_url(string $url): string
{
    $normalized = strtolower(trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8')));
    if ($normalized === '') {
        return '';
    }

    $prefix = commerza_local_vendor_prefix();

    if (
        str_contains($normalized, 'cdn.jsdelivr.net/npm/bootstrap@')
        && str_contains($normalized, '/dist/css/bootstrap.min.css')
    ) {
        return $prefix . 'frontend/assets/vendor/bootstrap/bootstrap.min.css';
    }

    if (
        str_contains($normalized, 'cdn.jsdelivr.net/npm/bootstrap@')
        && str_contains($normalized, '/dist/js/bootstrap.bundle.min.js')
    ) {
        return $prefix . 'frontend/assets/vendor/bootstrap/bootstrap.bundle.min.js';
    }

    if (
        str_contains($normalized, 'cdn.jsdelivr.net/npm/bootstrap-icons@')
        && str_contains($normalized, '/font/bootstrap-icons.min.css')
    ) {
        return $prefix . 'frontend/assets/vendor/bootstrap-icons/bootstrap-icons.min.css';
    }

    if (str_contains($normalized, 'code.jquery.com/jquery-')) {
        return $prefix . 'frontend/assets/vendor/jquery/jquery-3.7.1.min.js';
    }

    if (
        str_contains($normalized, 'cdn.jsdelivr.net/npm/chart.js@')
        && str_contains($normalized, '/dist/chart.umd.min.js')
    ) {
        return $prefix . 'frontend/assets/vendor/chart/chart.umd.min.js';
    }

    if (str_contains($normalized, 'fonts.googleapis.com/css2?')) {
        return $prefix . 'frontend/assets/vendor/fonts/google-fallback.css';
    }

    return '';
}

function commerza_insert_tag_attribute(string $tag, string $attribute): string
{
    $attr = trim($attribute);
    if ($tag === '' || $attr === '') {
        return $tag;
    }

    $tagEnd = strpos($tag, '>');
    if ($tagEnd === false) {
        return $tag;
    }

    return substr($tag, 0, $tagEnd)
        . ' '
        . $attr
        . substr($tag, $tagEnd);
}

function commerza_collect_preload_assets(string $buffer): array
{
    $assets = [];
    $seen = [];

    $styleTags = [];
    if (preg_match_all('/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*\bhref\s*=\s*(["\'])([^"\']+)\2[^>]*>/i', $buffer, $styleTags, PREG_SET_ORDER) === 1 || !empty($styleTags)) {
        foreach ($styleTags as $tag) {
            $href = trim((string)($tag[3] ?? ''));
            if ($href === '') {
                continue;
            }

            $key = 'style|' . strtolower($href);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $assets[] = [
                'as' => 'style',
                'href' => $href,
            ];

            if (count($assets) >= 3) {
                break;
            }
        }
    }

    $scriptTags = [];
    if (preg_match_all('/<script\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1[^>]*>\s*<\/script>/i', $buffer, $scriptTags, PREG_SET_ORDER) === 1 || !empty($scriptTags)) {
        $scriptCount = 0;
        foreach ($scriptTags as $tag) {
            $src = trim((string)($tag[2] ?? ''));
            $normalized = strtolower($src);
            $isExternalScript = preg_match('#^(https?:)?//#i', $src) === 1;

            if (
                $src === ''
                || $isExternalScript
                || str_contains($normalized, 'recaptcha')
                || str_contains($normalized, 'captcha')
                || str_contains($normalized, 'googletagmanager')
                || str_contains($normalized, 'google-analytics')
                || str_contains($normalized, 'maps.googleapis')
                || str_contains($normalized, 'stripe.com')
            ) {
                continue;
            }

            $key = 'script|' . strtolower($src);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $assets[] = [
                'as' => 'script',
                'href' => $src,
            ];

            $scriptCount++;
            if ($scriptCount >= 3) {
                break;
            }
        }
    }

    return $assets;
}

function commerza_inject_preload_links(string $buffer): string
{
    if ($buffer === '' || stripos($buffer, '</head>') === false) {
        return $buffer;
    }

    if (stripos($buffer, 'rel="preload"') !== false) {
        return $buffer;
    }

    $assets = commerza_collect_preload_assets($buffer);
    if (empty($assets)) {
        return $buffer;
    }

    $tags = [];
    foreach ($assets as $asset) {
        $href = trim((string)($asset['href'] ?? ''));
        $as = strtolower(trim((string)($asset['as'] ?? '')));

        if ($href === '' || !in_array($as, ['style', 'script', 'image', 'font'], true)) {
            continue;
        }

        $isExternal = preg_match('#^(https?:)?//#i', $href) === 1;
        $needsCrossOrigin = $isExternal && in_array($as, ['font', 'fetch'], true);
        $tag = '<link rel="preload" as="' . htmlspecialchars($as, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($needsCrossOrigin) {
            $tag .= ' crossorigin="anonymous"';
        }
        $tag .= '>';
        $tags[] = $tag;
    }

    if (empty($tags)) {
        return $buffer;
    }

    $html = "\n  " . implode("\n  ", $tags) . "\n";
    return preg_replace('/<\/head>/i', $html . '</head>', $buffer, 1) ?? $buffer;
}

function commerza_optimize_stylesheet_links(string $buffer): string
{
    return preg_replace_callback(
        '/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $href = '';
            if (preg_match('/\bhref\s*=\s*(["\'])([^"\']+)\1/i', $tag, $hrefMatch) === 1) {
                $href = (string)($hrefMatch[2] ?? '');
            }

            $fallbackHref = $href !== '' ? commerza_cdn_fallback_asset_for_url($href) : '';
            if ($fallbackHref !== '' && preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $safeFallback = htmlspecialchars($fallbackHref, ENT_QUOTES, 'UTF-8');
                $tag = commerza_insert_tag_attribute(
                    $tag,
                    'onerror="this.onerror=null;this.href=\'' . $safeFallback . '\'"'
                );
            }

            if (
                preg_match('/\bmedia\s*=\s*(["\'])print\1/i', $tag) === 1
                || preg_match('/\bonload\s*=\s*(["\'])[^"\']*this\.media\s*=\s*["\']all["\'][^"\']*\1/i', $tag) === 1
            ) {
                return $tag;
            }

            if (preg_match('/\bmedia\s*=\s*(["\']).*?\1/i', $tag) === 1) {
                return $tag;
            }

            $tag = commerza_insert_tag_attribute($tag, 'media="print"');
            $tag = commerza_insert_tag_attribute($tag, 'onload="this.media=\'all\'"');

            return $tag;
        },
        $buffer
    ) ?? $buffer;
}

function commerza_optimize_head_script_defer(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    $headMatch = [];
    if (preg_match('/<head\b[^>]*>.*<\/head>/is', $buffer, $headMatch, PREG_OFFSET_CAPTURE) !== 1) {
        return $buffer;
    }

    $headHtml = (string)($headMatch[0][0] ?? '');
    $headOffset = (int)($headMatch[0][1] ?? 0);
    if ($headHtml === '') {
        return $buffer;
    }

    $optimizedHead = preg_replace_callback(
        '/<script\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1[^>]*>\s*<\/script>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $srcRaw = (string)($matches[2] ?? '');
            $fallbackSrc = commerza_cdn_fallback_asset_for_url($srcRaw);
            if ($fallbackSrc !== '' && preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $safeFallback = htmlspecialchars($fallbackSrc, ENT_QUOTES, 'UTF-8');
                $tag = commerza_insert_tag_attribute(
                    $tag,
                    'onerror="this.onerror=null;this.src=\'' . $safeFallback . '\'"'
                );
            }

            if (
                preg_match('/\b(?:defer|async)\b/i', $tag) === 1
                || preg_match('/\btype\s*=\s*(["\'])module\1/i', $tag) === 1
            ) {
                return $tag;
            }

            $src = strtolower(trim(html_entity_decode($srcRaw, ENT_QUOTES, 'UTF-8')));
            if (
                $src === ''
                || str_contains($src, 'jquery')
                || str_contains($src, 'recaptcha')
                || str_contains($src, 'captcha')
                || str_contains($src, 'googletagmanager.com')
                || str_contains($src, 'google-analytics.com')
            ) {
                return $tag;
            }

            $tagEnd = strpos($tag, '>');
            if ($tagEnd === false) {
                return $tag;
            }

            return substr($tag, 0, $tagEnd) . ' defer' . substr($tag, $tagEnd);
        },
        $headHtml
    ) ?? $headHtml;

    if ($optimizedHead === $headHtml) {
        return $buffer;
    }

    return substr($buffer, 0, $headOffset)
        . $optimizedHead
        . substr($buffer, $headOffset + strlen($headHtml));
}

function commerza_optimize_script_tag_fallbacks(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    return preg_replace_callback(
        '/<script\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1[^>]*>\s*<\/script>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '' || preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) === 1) {
                return $tag;
            }

            $srcRaw = (string)($matches[2] ?? '');
            if ($srcRaw === '') {
                return $tag;
            }

            $fallbackSrc = commerza_cdn_fallback_asset_for_url($srcRaw);
            if ($fallbackSrc === '') {
                return $tag;
            }

            $safeFallback = htmlspecialchars($fallbackSrc, ENT_QUOTES, 'UTF-8');
            return commerza_insert_tag_attribute(
                $tag,
                'onerror="this.onerror=null;this.src=\'' . $safeFallback . '\'"'
            );
        },
        $buffer
    ) ?? $buffer;
}

function commerza_guess_image_alt_from_src(string $src): string
{
    $path = (string)(parse_url($src, PHP_URL_PATH) ?? $src);
    $filename = basename($path);
    $name = preg_replace('/\.[a-z0-9]+$/i', '', $filename);
    $name = is_string($name) ? $name : '';
    $name = trim(str_replace(['-', '_'], ' ', $name));
    $name = preg_replace('/\s+/', ' ', $name) ?: '';

    if ($name === '') {
        return 'Image';
    }

    $name = ucwords(strtolower($name));
    return $name !== '' ? $name : 'Image';
}

function commerza_optimize_image_loading(string $buffer): string
{
    return preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $insert = '';
            if (preg_match('/\bloading\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $insert .= ' loading="lazy"';
            }
            if (preg_match('/\bdecoding\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $insert .= ' decoding="async"';
            }

            if (preg_match('/\balt\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $src = '';
                if (preg_match('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $tag, $srcMatch) === 1) {
                    $src = (string)($srcMatch[2] ?? '');
                }
                $alt = commerza_guess_image_alt_from_src($src);
                $insert .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
            }

            if ($insert === '') {
                return $tag;
            }

            if (preg_match('/\s*\/>$/', $tag) === 1) {
                return preg_replace('/\s*\/>$/', $insert . ' />', $tag, 1) ?? $tag;
            }

            return preg_replace('/\s*>$/', $insert . '>', $tag, 1) ?? $tag;
        },
        $buffer
    ) ?? $buffer;
}

function commerza_placeholder_enhancer_style_tag(): string
{
    return '<style ' . commerza_csp_nonce_attr() . ' id="commerzaPlaceholderStyle">'
        . 'input::placeholder,textarea::placeholder{color:rgba(186,194,208,.92)!important;opacity:1;font-style:italic;}'
        . '</style>';
}

function commerza_placeholder_enhancer_script_tag(): string
{
    $script = <<<'JS'
(function () {
    function normalizeText(value) {
        return (value || "")
            .toString()
            .replace(/\*/g, "")
            .replace(/\s+/g, " ")
            .replace(/[\s:]+$/g, "")
            .trim();
    }

    function findLabelText(field) {
        var fieldId = (field.getAttribute("id") || "").trim();
        if (!fieldId) {
            return "";
        }

        if (window.CSS && typeof window.CSS.escape === "function") {
            var escapedId = window.CSS.escape(fieldId);
            var escapedLabel = document.querySelector('label[for="' + escapedId + '"]');
            if (escapedLabel) {
                return normalizeText(escapedLabel.textContent);
            }
        }

        var labels = document.querySelectorAll("label[for]");
        for (var index = 0; index < labels.length; index += 1) {
            var current = labels[index];
            if ((current.getAttribute("for") || "") === fieldId) {
                return normalizeText(current.textContent);
            }
        }

        return "";
    }

    function placeholderByType(type) {
        var presets = {
            email: "Enter your email",
            password: "Enter your password",
            tel: "Enter your phone number",
            search: "Search here",
            url: "Enter a valid URL",
            number: "Enter a value"
        };
        return presets[type] || "";
    }

    function placeholderForField(field) {
        var type = (field.getAttribute("type") || "text").toLowerCase();
        var byType = placeholderByType(type);
        if (byType) {
            return byType;
        }

        var byLabel = findLabelText(field);
        if (byLabel) {
            return "Enter " + byLabel.toLowerCase();
        }

        var ariaLabel = normalizeText(field.getAttribute("aria-label") || "");
        if (ariaLabel) {
            return "Enter " + ariaLabel.toLowerCase();
        }

        var fieldName = normalizeText(field.getAttribute("name") || "").replace(/[_-]+/g, " ");
        if (fieldName) {
            return "Enter " + fieldName.toLowerCase();
        }

        return "Enter value";
    }

    function applyPlaceholders() {
        var skipped = {
            hidden: true,
            submit: true,
            button: true,
            checkbox: true,
            radio: true,
            file: true,
            image: true,
            reset: true,
            color: true,
            range: true,
            date: true,
            time: true,
            "datetime-local": true,
            month: true,
            week: true
        };

        document.querySelectorAll("input,textarea").forEach(function (field) {
            if (!field || field.hasAttribute("placeholder") || field.disabled) {
                return;
            }

            if (field.tagName === "INPUT") {
                var type = (field.getAttribute("type") || "text").toLowerCase();
                if (skipped[type]) {
                    return;
                }
            }

            field.setAttribute("placeholder", placeholderForField(field));
            field.dataset.commerzaPlaceholderAuto = "1";
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", applyPlaceholders, { once: true });
    } else {
        applyPlaceholders();
    }
})();
JS;

    return '<script ' . commerza_csp_nonce_attr() . ' id="commerzaPlaceholderEnhancer">'
        . $script
        . '</script>';
}

function commerza_frontend_manual_stylesheet_links_html(): string
{
    static $html = null;

    if (is_string($html) && $html !== '') {
        return $html;
    }

    $paths = [
        'frontend/assets/css/modules/base.css',
        'frontend/assets/css/modules/navigation.css',
        'frontend/assets/css/modules/search.css',
        'frontend/assets/css/modules/carousel.css',
        'frontend/assets/css/modules/products.css',
        'frontend/assets/css/modules/footer.css',
        'frontend/assets/css/modules/layout-sections.css',
        'frontend/assets/css/modules/newsletter.css',
        'frontend/assets/css/modules/wishlist-tracking.css',
        'frontend/assets/css/modules/search-suggestions.css',
        'frontend/assets/css/modules/offcanvas.css',
        'frontend/assets/css/modules/breadcrumbs.css',
        'frontend/assets/css/modules/page-hero-wishlist.css',
    ];

    $tags = [];
    foreach ($paths as $path) {
        $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">';
    }

    $html = implode("\n  ", $tags);
    return $html;
}

function commerza_site_settings_inline_json_tag(): string
{
    $payload = $GLOBALS['commerza_public_site_settings_payload'] ?? null;
    if (!is_array($payload) || empty($payload)) {
        return '';
    }

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
    );

    if (!is_string($json) || $json === '') {
        return '';
    }

    return '<script ' . commerza_csp_nonce_attr() . ' id="commerzaSiteSettingsData" type="application/json">'
        . $json
        . '</script>';
}

function commerza_head_upsert_title(string $buffer, string $title): string
{
    $value = trim($title);
    if ($value === '') {
        return $buffer;
    }

    $safe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    if (preg_match('/<title>.*?<\/title>/is', $buffer) === 1) {
        return preg_replace('/<title>.*?<\/title>/is', '<title>' . $safe . '</title>', $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  <title>{$safe}</title>\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_meta_name(string $buffer, string $name, string $content): string
{
    $metaName = strtolower(trim($name));
    $value = trim($content);
    if ($metaName === '' || $value === '') {
        return $buffer;
    }

    $safeName = preg_replace('/[^a-z0-9:_-]/i', '', $metaName) ?: '';
    if ($safeName === '') {
        return $buffer;
    }

    $tag = '<meta name="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<meta\b[^>]*\bname\s*=\s*(["\'])' . preg_quote($safeName, '/') . '\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_meta_property(string $buffer, string $property, string $content): string
{
    $metaProperty = strtolower(trim($property));
    $value = trim($content);
    if ($metaProperty === '' || $value === '') {
        return $buffer;
    }

    $safeProperty = preg_replace('/[^a-z0-9:_-]/i', '', $metaProperty) ?: '';
    if ($safeProperty === '') {
        return $buffer;
    }

    $tag = '<meta property="' . htmlspecialchars($safeProperty, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<meta\b[^>]*\bproperty\s*=\s*(["\'])' . preg_quote($safeProperty, '/') . '\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_canonical(string $buffer, string $canonical): string
{
    $value = trim($canonical);
    if ($value === '') {
        return $buffer;
    }

    $tag = '<link rel="canonical" href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<link\b[^>]*\brel\s*=\s*(["\'])canonical\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_current_page_meta_key(): string
{
    $script = strtolower(trim((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''))));
    return $script !== '' ? $script : 'index.php';
}

function commerza_page_meta_overrides(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $cached = [];
    $con = $GLOBALS['con'] ?? null;
    if (!($con instanceof mysqli)) {
        return $cached;
    }

    $page = commerza_current_page_meta_key();

    $stmt = $con->prepare(
        'SELECT page, meta_title, meta_description, canonical_url, og_title, og_description, og_image, json_ld
         FROM page_meta
         WHERE page = ?
         LIMIT 1'
    );

    if (!$stmt) {
        $stmt = $con->prepare(
            'SELECT page, meta_title, meta_description
             FROM page_meta
             WHERE page = ?
             LIMIT 1'
        );
    }

    if (!$stmt) {
        return $cached;
    }

    $stmt->bind_param('s', $page);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return $cached;
    }

    $cached = [
        'meta_title' => trim((string)($row['meta_title'] ?? '')),
        'meta_description' => trim((string)($row['meta_description'] ?? '')),
        'canonical_url' => trim((string)($row['canonical_url'] ?? '')),
        'og_title' => trim((string)($row['og_title'] ?? '')),
        'og_description' => trim((string)($row['og_description'] ?? '')),
        'og_image' => trim((string)($row['og_image'] ?? '')),
        'json_ld' => trim((string)($row['json_ld'] ?? '')),
    ];

    return $cached;
}

function commerza_html_meta_normalize(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    $base = commerza_public_base_url();
    $buffer = str_replace(
        ['https://commerza.ahmershah.dev', 'http://commerza.ahmershah.dev'],
        $base,
        $buffer
    );

    $buffer = preg_replace(
        '/frontend\/assets\/js\/global-protection\.js(?!\?v=)/i',
        'frontend/assets/js/global-protection.js?v=20260408',
        $buffer
    ) ?? $buffer;

    if (stripos($buffer, '</head>') === false) {
        return $buffer;
    }

    if (stripos($buffer, '<base ') === false) {
        $baseHref = htmlspecialchars(rtrim(commerza_public_base_url(), '/') . '/', ENT_QUOTES, 'UTF-8');
        $baseTag = "\n  <base href=\"{$baseHref}\">\n";
        $buffer = preg_replace_callback('/<head(\\s[^>]*)?>/i', static function (array $matches) use ($baseTag): string {
            return $matches[0] . $baseTag;
        }, $buffer, 1) ?? $buffer;
    }

    $manualStyles = commerza_frontend_manual_stylesheet_links_html();
    $buffer = preg_replace_callback(
        '/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*\bhref\s*=\s*(["\'])([^"\']+)\2[^>]*>/i',
        static function (array $matches) use ($manualStyles): string {
            $tag = (string)($matches[0] ?? '');
            $href = html_entity_decode(strtolower(trim((string)($matches[3] ?? ''))), ENT_QUOTES, 'UTF-8');

            if ($href === '' || !str_contains($href, 'frontend/assets/css/style.css')) {
                return $tag;
            }

            if (str_contains($href, 'admin/frontend/assets/css/style.css')) {
                return $tag;
            }

            return $manualStyles;
        },
        $buffer,
        1
    ) ?? $buffer;

    $buffer = commerza_optimize_stylesheet_links($buffer);
    $buffer = commerza_optimize_head_script_defer($buffer);
    $buffer = commerza_optimize_script_tag_fallbacks($buffer);
    $buffer = commerza_optimize_image_loading($buffer);
    $buffer = commerza_inject_preload_links($buffer);

    if (stripos($buffer, 'id="commerzaPlaceholderStyle"') === false) {
        $placeholderStyle = commerza_placeholder_enhancer_style_tag();
        $buffer = preg_replace('/<\/head>/i', "\n  {$placeholderStyle}\n</head>", $buffer, 1) ?? $buffer;
    }

    if (stripos($buffer, 'id="commerzaPlaceholderEnhancer"') === false) {
        $placeholderScript = commerza_placeholder_enhancer_script_tag();
        if (stripos($buffer, '</body>') !== false) {
            $buffer = preg_replace('/<\/body>/i', "\n  {$placeholderScript}\n</body>", $buffer, 1) ?? $buffer;
        } else {
            $buffer = preg_replace('/<\/head>/i', "\n  {$placeholderScript}\n</head>", $buffer, 1) ?? $buffer;
        }
    }

    $metaOverrides = commerza_page_meta_overrides();
    if (!empty($metaOverrides)) {
        $titleOverride = trim((string)($metaOverrides['meta_title'] ?? ''));
        $descriptionOverride = trim((string)($metaOverrides['meta_description'] ?? ''));
        $canonicalOverride = trim((string)($metaOverrides['canonical_url'] ?? ''));
        $ogTitleOverride = trim((string)($metaOverrides['og_title'] ?? ''));
        $ogDescriptionOverride = trim((string)($metaOverrides['og_description'] ?? ''));
        $ogImageOverride = trim((string)($metaOverrides['og_image'] ?? ''));
        $jsonLdOverride = trim((string)($metaOverrides['json_ld'] ?? ''));

        if ($canonicalOverride !== '' && preg_match('#^https?://#i', $canonicalOverride) !== 1) {
            $canonicalOverride = commerza_absolute_url('/' . ltrim($canonicalOverride, '/'));
        }

        if ($ogImageOverride !== '' && preg_match('#^https?://#i', $ogImageOverride) !== 1) {
            $ogImageOverride = commerza_absolute_url('/' . ltrim($ogImageOverride, '/'));
        }

        if ($titleOverride !== '') {
            $buffer = commerza_head_upsert_title($buffer, $titleOverride);
        }

        if ($descriptionOverride !== '') {
            $buffer = commerza_head_upsert_meta_name($buffer, 'description', $descriptionOverride);
        }

        if ($canonicalOverride !== '') {
            $buffer = commerza_head_upsert_canonical($buffer, $canonicalOverride);
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:url', $canonicalOverride);
        }

        if ($ogTitleOverride === '' && $titleOverride !== '') {
            $ogTitleOverride = $titleOverride;
        }
        if ($ogDescriptionOverride === '' && $descriptionOverride !== '') {
            $ogDescriptionOverride = $descriptionOverride;
        }

        if ($ogTitleOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:title', $ogTitleOverride);
        }

        if ($ogDescriptionOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:description', $ogDescriptionOverride);
        }

        if ($ogImageOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:image', $ogImageOverride);
        }

        if ($jsonLdOverride !== '') {
            $decodedJsonLd = json_decode($jsonLdOverride, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJsonLd)) {
                $jsonLd = json_encode(
                    $decodedJsonLd,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                );

                if (is_string($jsonLd) && $jsonLd !== '') {
                    $buffer = preg_replace(
                        '/<script\b[^>]*\bid="commerzaSeoJsonLd"[^>]*>.*?<\/script>/is',
                        '',
                        $buffer
                    ) ?? $buffer;

                    $customJsonLdTag = "\n  <script " . commerza_csp_nonce_attr() . " id=\"commerzaSeoJsonLd\" type=\"application/ld+json\">\n"
                        . $jsonLd
                        . "\n  </script>\n";

                    $buffer = preg_replace('/<\/head>/i', $customJsonLdTag . '</head>', $buffer, 1) ?? $buffer;
                }
            }
        }
    }

    if (
        stripos($buffer, 'name="twitter:card"') === false
        && preg_match('/property="og:title"\s+content="([^"]*)"/i', $buffer, $ogTitle)
        && preg_match('/property="og:description"\s+content="([^"]*)"/i', $buffer, $ogDesc)
        && preg_match('/property="og:image"\s+content="([^"]*)"/i', $buffer, $ogImage)
    ) {
        $twitter = "\n  <meta name=\"twitter:card\" content=\"summary_large_image\">"
            . "\n  <meta name=\"twitter:title\" content=\"{$ogTitle[1]}\">"
            . "\n  <meta name=\"twitter:description\" content=\"{$ogDesc[1]}\">"
            . "\n  <meta name=\"twitter:image\" content=\"{$ogImage[1]}\">\n";
        $buffer = preg_replace('/<\/head>/i', $twitter . '</head>', $buffer, 1) ?? $buffer;
    }

    if (
        stripos($buffer, 'rel="canonical"') === false
        && preg_match('/property="og:url"\s+content="([^"]*)"/i', $buffer, $ogUrl)
    ) {
        $canonical = "\n  <link rel=\"canonical\" href=\"{$ogUrl[1]}\" />\n";
        $buffer = preg_replace('/<\/head>/i', $canonical . '</head>', $buffer, 1) ?? $buffer;
    }

    if (stripos($buffer, 'id="commerzaSiteSettingsData"') === false) {
        $settingsScript = commerza_site_settings_inline_json_tag();
        if ($settingsScript !== '') {
            $buffer = preg_replace('/<\/head>/i', "\n  {$settingsScript}\n</head>", $buffer, 1) ?? $buffer;
        }
    }

    $hasOrganizationSchema = preg_match('/"@type"\s*:\s*"Organization"/i', $buffer) === 1;
    $hasWebsiteSchema = preg_match('/"@type"\s*:\s*"WebSite"/i', $buffer) === 1;
    $hasPageSchema = preg_match('/"@type"\s*:\s*"(WebPage|FAQPage|Product|CollectionPage|ItemList|Article|ContactPage|AboutPage|CheckoutPage|ProfilePage|SearchResultsPage)"/i', $buffer) === 1;

    $title = 'Commerza';
    if (preg_match('/<title>(.*?)<\/title>/is', $buffer, $titleMatch)) {
        $titleText = trim(strip_tags(html_entity_decode((string)$titleMatch[1], ENT_QUOTES, 'UTF-8')));
        if ($titleText !== '') {
            $title = $titleText;
        }
    }

    $description = 'Commerza premium watches and ecommerce experience.';
    if (preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $buffer, $descriptionMatch)) {
        $descriptionText = trim(html_entity_decode((string)$descriptionMatch[1], ENT_QUOTES, 'UTF-8'));
        if ($descriptionText !== '') {
            $description = $descriptionText;
        }
    }

    $pageUrl = '';
    if (preg_match('/<link\s+rel="canonical"\s+href="([^"]*)"/i', $buffer, $canonicalMatch)) {
        $pageUrl = trim((string)$canonicalMatch[1]);
    }
    if ($pageUrl === '' && preg_match('/property="og:url"\s+content="([^"]*)"/i', $buffer, $ogUrlMatch)) {
        $pageUrl = trim((string)$ogUrlMatch[1]);
    }
    if ($pageUrl === '') {
        $scriptName = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $pageUrl = rtrim(commerza_public_base_url(), '/') . '/';
        if ($scriptName !== '') {
            $pageUrl .= $scriptName;
        }
    }

    $pageImage = rtrim(commerza_public_base_url(), '/') . '/frontend/assets/images/logo/commerza-logo.webp';
    if (preg_match('/property="og:image"\s+content="([^"]*)"/i', $buffer, $ogImageMatch)) {
        $candidateImage = trim((string)$ogImageMatch[1]);
        if ($candidateImage !== '') {
            $pageImage = $candidateImage;
        }
    }

    $graph = [];

    if (!$hasOrganizationSchema) {
        $graph[] = [
            '@type' => 'Organization',
            '@id' => rtrim(commerza_public_base_url(), '/') . '/#organization',
            'name' => 'Commerza',
            'url' => rtrim(commerza_public_base_url(), '/') . '/',
            'logo' => $pageImage,
            'sameAs' => [
                'https://www.facebook.com/commerza.ahmer',
                'https://www.instagram.com/commerza.ahmer',
                'https://x.com/commerza_ahmer',
            ],
        ];
    }

    if (!$hasWebsiteSchema) {
        $graph[] = [
            '@type' => 'WebSite',
            '@id' => rtrim(commerza_public_base_url(), '/') . '/#website',
            'name' => 'Commerza',
            'url' => rtrim(commerza_public_base_url(), '/') . '/',
            'publisher' => [
                '@id' => rtrim(commerza_public_base_url(), '/') . '/#organization',
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => rtrim(commerza_public_base_url(), '/') . '/products.php?name={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    if (!$hasPageSchema) {
        $graph[] = [
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $pageUrl,
            'description' => $description,
            'isPartOf' => [
                '@id' => rtrim(commerza_public_base_url(), '/') . '/#website',
            ],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $pageImage,
            ],
        ];
    }

    if (!empty($graph)) {
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (is_string($jsonLd) && $jsonLd !== '') {
            $script = "\n  <script " . commerza_csp_nonce_attr() . " type=\"application/ld+json\">\n"
                . $jsonLd
                . "\n  </script>\n";
            $buffer = preg_replace('/<\/head>/i', $script . '</head>', $buffer, 1) ?? $buffer;
        }
    }

    return $buffer;
}

function commerza_enable_meta_normalizer(): void
{
    static $enabled = false;

    if ($enabled || PHP_SAPI === 'cli' || commerza_is_backend_request()) {
        return;
    }

    $enabled = true;
    ob_start(static fn(string $buffer): string => commerza_html_meta_normalize($buffer));
}

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

commerza_maybe_redirect_clean_route();

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-DNS-Prefetch-Control: off');
    header('X-Download-Options: noopen');
    header('Origin-Agent-Cluster: ?1');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), browsing-topics=()');
    header('Content-Security-Policy: ' . commerza_content_security_policy_header());
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-XSS-Protection: 0');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    commerza_apply_cache_headers();
}

commerza_enable_meta_normalizer();

$host = trim((string)(getenv('COMMERZA_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost'));
$user = trim((string)(getenv('COMMERZA_DB_USER') ?: getenv('DB_USER') ?: 'root'));
$db = trim((string)(getenv('COMMERZA_DB_NAME') ?: getenv('DB_NAME') ?: 'commerza'));

$passEnv = getenv('COMMERZA_DB_PASS');
if ($passEnv === false) {
    $passEnv = getenv('DB_PASS');
}
$pass = $passEnv === false ? '' : (string)$passEnv;

$con = mysqli_connect($host, $user, $pass, $db);

if (!$con) {
    http_response_code(500);
    exit("Service unavailable.");
}

mysqli_set_charset($con, "utf8mb4");

function commerza_site_setting_cache_ttl(): int
{
    static $ttl = null;

    if (is_int($ttl) && $ttl > 0) {
        return $ttl;
    }

    $raw = getenv('COMMERZA_SITE_SETTINGS_CACHE_TTL');
    $candidate = $raw === false ? 300 : (int)$raw;
    if ($candidate < 30) {
        $candidate = 30;
    }

    if ($candidate > 3600) {
        $candidate = 3600;
    }

    $ttl = $candidate;
    return $ttl;
}

function commerza_site_setting_query_value(mysqli $con, string $normalizedKey): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('s', $normalizedKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return '';
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value;
}

function commerza_site_setting_value(mysqli $con, string $key, string $fallback = ''): string
{
    $normalizedKey = strtolower(trim($key));
    if ($normalizedKey === '') {
        return $fallback;
    }

    $value = '';
    if (function_exists('commerza_cache_remember')) {
        $cached = commerza_cache_remember(
            'site-setting:' . $normalizedKey,
            commerza_site_setting_cache_ttl(),
            static function () use ($con, $normalizedKey): string {
                return commerza_site_setting_query_value($con, $normalizedKey);
            }
        );

        $value = trim((string)$cached);
    } else {
        $value = commerza_site_setting_query_value($con, $normalizedKey);
    }

    return $value !== '' ? $value : $fallback;
}

function commerza_build_public_site_settings_payload(mysqli $con): array
{
    return [
        'brand' => [
            'name' => commerza_site_setting_value($con, 'site_name', 'COMMERZA'),
            'logo' => commerza_site_setting_value(
                $con,
                'logo_url',
                'frontend/assets/images/logo/commerza-logo.webp'
            ),
            'favicon' => commerza_site_setting_value(
                $con,
                'favicon_url',
                'frontend/assets/images/favicon/commerza-watches-icon.ico'
            ),
        ],
        'contact' => [
            'address' => commerza_site_setting_value($con, 'site_address', ''),
            'email' => commerza_site_setting_value($con, 'site_email', ''),
            'phone' => commerza_site_setting_value($con, 'site_phone', ''),
        ],
        'ticker' => [
            'enabled' => commerza_site_setting_value($con, 'ticker_enabled', '1') !== '0',
            'messages' => [],
        ],
        'socialLinks' => [],
        'sliderImages' => [],
        'featuredVideos' => [
            'home' => commerza_site_setting_value(
                $con,
                'home_feature_video',
                'frontend/assets/videos/slider/steel_watch_1.mp4'
            ),
            'categoryA' => commerza_site_setting_value(
                $con,
                'category_a_feature_video',
                'frontend/assets/videos/products/smart/automatic_watches_carousel.mp4'
            ),
        ],
    ];
}

$GLOBALS['commerza_public_site_settings_payload'] = commerza_build_public_site_settings_payload($con);

function commerza_users_table_exists(mysqli $con): bool
{
    $result = $con->query("SHOW TABLES LIKE 'users'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_has_column(mysqli $con, string $column): bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_has_unique_index_for_column(mysqli $con, string $column): bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW INDEX FROM users WHERE Column_name = '{$safeColumn}' AND Non_unique = 0");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_column_is_nullable(mysqli $con, string $column): ?bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    $nullable = strtoupper((string)($row['Null'] ?? 'YES')) === 'YES';
    return $nullable;
}

function commerza_username_slug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = str_replace([' ', '-'], '_', $normalized);
    $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized);

    if (!is_string($normalized)) {
        return '';
    }

    $normalized = preg_replace('/_+/', '_', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    $normalized = trim($normalized, '_');

    if ($normalized !== '' && preg_match('/^[a-z]/', $normalized) !== 1) {
        $normalized = 'u_' . $normalized;
    }

    if (strlen($normalized) > 24) {
        $normalized = substr($normalized, 0, 24);
        $normalized = rtrim($normalized, '_');
    }

    return $normalized;
}

function commerza_username_is_valid(string $username): bool
{
    return preg_match('/^[a-z][a-z0-9_]{2,23}$/', trim($username)) === 1;
}

function commerza_username_blacklist_type_from_reason(string $reason, string $category = ''): string
{
    $normalizedReason = strtolower(trim($reason));
    $normalizedCategory = strtoupper(trim($category));

    if (
        str_contains($normalizedReason, 'religious')
        || str_contains($normalizedReason, 'religion')
    ) {
        return 'religious';
    }

    if (
        str_contains($normalizedReason, 'explicit')
        || str_contains($normalizedReason, 'sexual')
        || str_contains($normalizedReason, 'adult')
    ) {
        return 'explicit';
    }

    if ($normalizedCategory === 'A' || $normalizedCategory === 'C') {
        return 'system';
    }

    return 'harmful';
}

function commerza_username_blacklist_feedback_message(array $blocked): string
{
    $type = strtolower(trim((string)($blocked['type'] ?? 'harmful')));

    if ($type === 'religious') {
        return 'This username contains religious targeting terms. Please choose a neutral username.';
    }

    if ($type === 'explicit') {
        return 'This username includes explicit sexual language. Please choose a cleaner username.';
    }

    if ($type === 'system') {
        return 'This username is reserved for Commerza system use. Please choose another username.';
    }

    return 'This username contains harmful or offensive language. Please choose a respectful username.';
}

function commerza_username_blacklist_lookup(mysqli $con, string $username): ?array
{
    $slug = commerza_username_slug($username);
    if ($slug === '') {
        return null;
    }

    static $entries = null;

    if (!is_array($entries)) {
        $entries = [];

        $hasTable = false;
        $tableResult = $con->query("SHOW TABLES LIKE 'username_blacklist'");
        if ($tableResult instanceof mysqli_result) {
            $hasTable = $tableResult->num_rows > 0;
            $tableResult->free();
        }

        if ($hasTable) {
            $result = $con->query(
                "SELECT term, category, reason
                 FROM username_blacklist
                 WHERE is_active = 1
                 ORDER BY CHAR_LENGTH(term) DESC, id ASC"
            );

            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $term = commerza_username_slug((string)($row['term'] ?? ''));
                    if ($term === '') {
                        continue;
                    }

                    $reason = trim((string)($row['reason'] ?? ''));
                    $category = trim((string)($row['category'] ?? ''));

                    $entries[] = [
                        'term' => $term,
                        'reason' => $reason,
                        'category' => $category,
                        'type' => commerza_username_blacklist_type_from_reason($reason, $category),
                    ];
                }

                $result->free();
            }
        }

        if (empty($entries)) {
            $entries = [
                ['term' => 'admin', 'reason' => 'System role name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'root', 'reason' => 'System role name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'commerza', 'reason' => 'Brand reserved name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'products', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'account', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'wishlist', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'porn', 'reason' => 'Explicit sexual term', 'category' => 'B', 'type' => 'explicit'],
                ['term' => 'sex', 'reason' => 'Explicit sexual term', 'category' => 'B', 'type' => 'explicit'],
                ['term' => 'islam', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'christian', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'hindu', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'nazi', 'reason' => 'Hateful extremist term', 'category' => 'B', 'type' => 'harmful'],
                ['term' => 'fuck', 'reason' => 'Offensive profanity term', 'category' => 'B', 'type' => 'harmful'],
            ];
        }
    }

    foreach ($entries as $entry) {
        $term = (string)($entry['term'] ?? '');
        if ($term === '') {
            continue;
        }

        $isMatch = false;

        if ($slug === $term) {
            $isMatch = true;
        } elseif (strlen($term) <= 3) {
            if (
                str_starts_with($slug, $term)
                || str_ends_with($slug, $term)
                || preg_match('/(^|_)' . preg_quote($term, '/') . '(_|$)/', $slug) === 1
            ) {
                $isMatch = true;
            }
        } elseif (str_contains($slug, $term)) {
            $isMatch = true;
        }

        if ($isMatch) {
            return [
                'term' => $term,
                'reason' => (string)($entry['reason'] ?? ''),
                'category' => (string)($entry['category'] ?? ''),
                'type' => (string)($entry['type'] ?? 'harmful'),
            ];
        }
    }

    return null;
}

function commerza_customer_blacklist_normalize_email(string $email): string
{
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
        return '';
    }

    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL) || strlen($normalized) > 150) {
        return '';
    }

    return $normalized;
}

function commerza_customer_blacklist_normalize_phone(string $phone): string
{
    $normalized = preg_replace('/\s+/', '', trim($phone));
    if (!is_string($normalized)) {
        return '';
    }

    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^\d{11,15}$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function commerza_customer_blacklist_ensure_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS customer_blacklist (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_by_admin_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_blacklist_active (is_active, created_at),
            KEY idx_customer_blacklist_email (email),
            KEY idx_customer_blacklist_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function commerza_customer_blacklist_lookup(mysqli $con, string $email = '', string $phone = ''): ?array
{
    commerza_customer_blacklist_ensure_table($con);

    $normalizedEmail = commerza_customer_blacklist_normalize_email($email);
    $normalizedPhone = commerza_customer_blacklist_normalize_phone($phone);

    if ($normalizedEmail === '' && $normalizedPhone === '') {
        return null;
    }

    if ($normalizedEmail !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason, created_at
             FROM customer_blacklist
             WHERE is_active = 1
               AND LOWER(TRIM(email)) = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => trim((string)($row['email'] ?? '')),
                    'phone' => trim((string)($row['phone'] ?? '')),
                    'reason' => trim((string)($row['reason'] ?? '')),
                    'match' => 'email',
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    if ($normalizedPhone !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason, created_at
             FROM customer_blacklist
             WHERE is_active = 1
               AND phone = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => trim((string)($row['email'] ?? '')),
                    'phone' => trim((string)($row['phone'] ?? '')),
                    'reason' => trim((string)($row['reason'] ?? '')),
                    'match' => 'phone',
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    return null;
}

function commerza_customer_blacklist_feedback_message(array $blocked): string
{
    $match = strtolower(trim((string)($blocked['match'] ?? 'contact')));
    $reason = trim((string)($blocked['reason'] ?? ''));

    if ($reason !== '') {
        if ($match === 'email') {
            return 'This email has been blocked by admin. Reason: ' . $reason;
        }

        if ($match === 'phone') {
            return 'This phone number has been blocked by admin. Reason: ' . $reason;
        }

        return 'This contact is blocked by admin. Reason: ' . $reason;
    }

    if ($match === 'email') {
        return 'This email has been blocked by admin. Please contact support.';
    }

    if ($match === 'phone') {
        return 'This phone number has been blocked by admin. Please contact support.';
    }

    return 'This contact is blocked by admin. Please contact support.';
}

function commerza_username_base_from_identity(string $fullName, string $email, int $fallbackId = 0): string
{
    $emailLocal = '';
    if ($email !== '') {
        $parts = explode('@', $email, 2);
        $emailLocal = (string)($parts[0] ?? '');
    }

    $candidates = [
        $fullName,
        $emailLocal,
        'user' . max(1, $fallbackId),
    ];

    foreach ($candidates as $candidate) {
        $slug = commerza_username_slug((string)$candidate);
        if ($slug !== '') {
            if (strlen($slug) < 3) {
                $slug = str_pad($slug, 3, '0');
            }
            return substr($slug, 0, 24);
        }
    }

    try {
        $random = 'user_' . substr(bin2hex(random_bytes(4)), 0, 6);
    } catch (Throwable $exception) {
        $random = 'user_' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    return substr(commerza_username_slug($random), 0, 24);
}

function commerza_username_slug_exists(mysqli $con, string $slug, int $excludeUserId = 0): bool
{
    if ($slug === '') {
        return false;
    }

    $sql = 'SELECT id FROM users WHERE username_slug = ?';
    if ($excludeUserId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return true;
    }

    if ($excludeUserId > 0) {
        $stmt->bind_param('si', $slug, $excludeUserId);
    } else {
        $stmt->bind_param('s', $slug);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row);
}

function commerza_username_resolve_unique(
    mysqli $con,
    string $preferred,
    string $fullName = '',
    string $email = '',
    int $excludeUserId = 0
): array {
    $base = commerza_username_slug($preferred);

    if (!commerza_username_is_valid($base)) {
        $base = commerza_username_base_from_identity($fullName, $email, $excludeUserId);
    }

    if (!commerza_username_is_valid($base)) {
        $base = commerza_username_slug('user_' . max(1, $excludeUserId));
    }

    if (!commerza_username_is_valid($base)) {
        $base = 'user001';
    }

    $candidate = $base;

    for ($attempt = 0; $attempt < 500; $attempt++) {
        if ($attempt > 0) {
            $suffix = (string)$attempt;
            $maxPrefixLength = max(1, 24 - strlen($suffix) - 1);
            $prefix = substr($base, 0, $maxPrefixLength);
            $candidate = rtrim($prefix, '_') . '_' . $suffix;
        }

        $slug = commerza_username_slug($candidate);
        if (!commerza_username_is_valid($slug)) {
            continue;
        }

        if (!commerza_username_slug_exists($con, $slug, $excludeUserId)) {
            return [
                'username' => $slug,
                'slug' => $slug,
            ];
        }
    }

    $fallbackBase = commerza_username_base_from_identity($fullName, $email, $excludeUserId);
    $fallback = commerza_username_slug($fallbackBase . '_' . substr((string)time(), -4));
    if (!commerza_username_is_valid($fallback)) {
        $fallback = 'user_' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    return [
        'username' => $fallback,
        'slug' => $fallback,
    ];
}

function commerza_ensure_users_identity_schema(mysqli $con): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    if (!commerza_users_table_exists($con)) {
        $ready = true;
        return;
    }

    $requiredColumns = [
        'username' => 'ALTER TABLE users ADD COLUMN username VARCHAR(24) DEFAULT NULL AFTER full_name',
        'username_slug' => 'ALTER TABLE users ADD COLUMN username_slug VARCHAR(48) DEFAULT NULL AFTER username',
        'profile_visibility' => "ALTER TABLE users ADD COLUMN profile_visibility ENUM('private','public') NOT NULL DEFAULT 'private' AFTER profile_picture",
        'username_changed_at' => 'ALTER TABLE users ADD COLUMN username_changed_at DATETIME DEFAULT NULL AFTER profile_visibility',
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!commerza_users_has_column($con, $column)) {
            $con->query($sql);
        }
    }

    $result = $con->query(
        "SELECT id, full_name, email, username, username_slug, profile_visibility
         FROM users
         WHERE username IS NULL
            OR TRIM(username) = ''
            OR username_slug IS NULL
            OR TRIM(username_slug) = ''
            OR profile_visibility IS NULL
            OR TRIM(profile_visibility) = ''
            OR username NOT REGEXP '^[a-z][a-z0-9_]{2,23}$'
            OR username_slug NOT REGEXP '^[a-z][a-z0-9_]{2,23}$'
         ORDER BY id ASC"
    );
    if ($result instanceof mysqli_result) {
        $updateStmt = $con->prepare(
            'UPDATE users
             SET username = ?, username_slug = ?, profile_visibility = ?
             WHERE id = ?
             LIMIT 1'
        );

        if ($updateStmt) {
            while ($row = $result->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $currentUsername = trim((string)($row['username'] ?? ''));
                $currentSlug = trim((string)($row['username_slug'] ?? ''));
                $fullName = (string)($row['full_name'] ?? '');
                $email = (string)($row['email'] ?? '');
                $visibility = strtolower(trim((string)($row['profile_visibility'] ?? 'private')));

                if (!in_array($visibility, ['private', 'public'], true)) {
                    $visibility = 'private';
                }

                $preferred = $currentUsername !== '' ? $currentUsername : $currentSlug;
                $resolved = commerza_username_resolve_unique($con, $preferred, $fullName, $email, $id);
                $normalizedUsername = (string)($resolved['username'] ?? '');
                $normalizedSlug = (string)($resolved['slug'] ?? $normalizedUsername);

                $usernameChanged = strcasecmp($currentUsername, $normalizedUsername) !== 0;
                $slugChanged = strcasecmp($currentSlug, $normalizedSlug) !== 0;
                $visibilityChanged = strtolower(trim((string)($row['profile_visibility'] ?? ''))) !== $visibility;

                if (!$usernameChanged && !$slugChanged && !$visibilityChanged) {
                    continue;
                }

                $updateStmt->bind_param('sssi', $normalizedUsername, $normalizedSlug, $visibility, $id);
                $updateStmt->execute();
            }

            $updateStmt->close();
        }

        $result->free();
    }

    if (commerza_users_has_column($con, 'profile_visibility')) {
        $con->query("UPDATE users SET profile_visibility = 'private' WHERE profile_visibility IS NULL OR TRIM(profile_visibility) = ''");
    }

    if (commerza_users_has_column($con, 'username') && commerza_users_column_is_nullable($con, 'username') === true) {
        $con->query('ALTER TABLE users MODIFY username VARCHAR(24) NOT NULL');
    }

    if (commerza_users_has_column($con, 'username_slug') && commerza_users_column_is_nullable($con, 'username_slug') === true) {
        $con->query('ALTER TABLE users MODIFY username_slug VARCHAR(48) NOT NULL');
    }

    if (!commerza_users_has_unique_index_for_column($con, 'username')) {
        $con->query('ALTER TABLE users ADD UNIQUE INDEX uq_users_username (username)');
    }

    if (!commerza_users_has_unique_index_for_column($con, 'username_slug')) {
        $con->query('ALTER TABLE users ADD UNIQUE INDEX uq_users_username_slug (username_slug)');
    }

    $ready = true;
}

commerza_ensure_users_identity_schema($con);

require_once __DIR__ . '/expiry_cleanup.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/security_events.php';

if (function_exists('commerza_should_run_expiry_cleanup') && commerza_should_run_expiry_cleanup()) {
    commerza_run_expiry_cleanup($con);
}

if (!defined('COMMERZA_REMEMBER_COOKIE')) {
    define('COMMERZA_REMEMBER_COOKIE', 'commerza_remember');
}

if (!defined('COMMERZA_REMEMBER_DAYS')) {
    define('COMMERZA_REMEMBER_DAYS', 30);
}

if (!defined('COMMERZA_REMEMBER_MAX_SESSIONS')) {
    define('COMMERZA_REMEMBER_MAX_SESSIONS', 5);
}

function commerza_remember_cookie_options(int $expires): array
{
    $isHttps = commerza_request_is_https();

    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function commerza_clear_remember_cookie(): void
{
    setcookie(COMMERZA_REMEMBER_COOKIE, '', commerza_remember_cookie_options(time() - 3600));
    unset($_COOKIE[COMMERZA_REMEMBER_COOKIE]);
}

function commerza_ensure_user_sessions_table(mysqli $con): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS user_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_sessions_user (user_id),
            KEY idx_user_sessions_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    // Keep the table compact by clearing rows that are already expired.
    $con->query('DELETE FROM user_sessions WHERE expires_at <= NOW()');

    $ready = true;
}

function commerza_prune_user_sessions_for_user(mysqli $con, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $maxSessions = (int)COMMERZA_REMEMBER_MAX_SESSIONS;
    if ($maxSessions <= 0) {
        return;
    }

    $offset = $maxSessions - 1;

    $cutoffStmt = $con->prepare(
        'SELECT id, created_at
         FROM user_sessions
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1 OFFSET ?'
    );

    if (!$cutoffStmt) {
        return;
    }

    $cutoffStmt->bind_param('ii', $userId, $offset);
    $cutoffStmt->execute();
    $cutoffResult = $cutoffStmt->get_result();
    $cutoffRow = $cutoffResult ? $cutoffResult->fetch_assoc() : null;
    $cutoffStmt->close();

    if (!$cutoffRow || empty($cutoffRow['id']) || empty($cutoffRow['created_at'])) {
        return;
    }

    $cutoffId = (int)$cutoffRow['id'];
    $cutoffCreatedAt = (string)$cutoffRow['created_at'];

    $deleteStmt = $con->prepare(
        'DELETE FROM user_sessions
         WHERE user_id = ?
           AND (
                created_at < ?
                OR (created_at = ? AND id < ?)
           )'
    );

    if (!$deleteStmt) {
        return;
    }

    $deleteStmt->bind_param('issi', $userId, $cutoffCreatedAt, $cutoffCreatedAt, $cutoffId);
    $deleteStmt->execute();
    $deleteStmt->close();
}

function commerza_issue_remember_token(mysqli $con, int $userId, string $rotateFromTokenHash = ''): bool
{
    if ($userId <= 0) {
        commerza_clear_remember_cookie();
        return false;
    }

    commerza_ensure_user_sessions_table($con);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = time() + (COMMERZA_REMEMBER_DAYS * 86400);
    $expiresAtSql = date('Y-m-d H:i:s', $expiresAt);
    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $insertStmt = $con->prepare(
        'INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        return false;
    }

    $insertStmt->bind_param('issss', $userId, $tokenHash, $ipAddress, $userAgent, $expiresAtSql);
    $insertOk = $insertStmt->execute();
    $insertStmt->close();

    if (!$insertOk) {
        return false;
    }

    if (
        $rotateFromTokenHash !== ''
        && preg_match('/^[a-f0-9]{64}$/', $rotateFromTokenHash)
        && !hash_equals($rotateFromTokenHash, $tokenHash)
    ) {
        $rotateStmt = $con->prepare('DELETE FROM user_sessions WHERE user_id = ? AND token = ? LIMIT 1');

        if ($rotateStmt) {
            $rotateStmt->bind_param('is', $userId, $rotateFromTokenHash);
            $rotateStmt->execute();
            $rotateStmt->close();
        }
    }

    commerza_prune_user_sessions_for_user($con, $userId);

    $payload = $userId . ':' . $rawToken;
    setcookie(COMMERZA_REMEMBER_COOKIE, $payload, commerza_remember_cookie_options($expiresAt));
    $_COOKIE[COMMERZA_REMEMBER_COOKIE] = $payload;

    return true;
}

function commerza_forget_current_remember_token(mysqli $con): void
{
    $cookieValue = (string)($_COOKIE[COMMERZA_REMEMBER_COOKIE] ?? '');
    commerza_clear_remember_cookie();

    if ($cookieValue === '') {
        return;
    }

    $parts = explode(':', $cookieValue, 2);
    if (count($parts) !== 2) {
        return;
    }

    $tokenHash = hash('sha256', (string)$parts[1]);

    commerza_ensure_user_sessions_table($con);
    $deleteStmt = $con->prepare('DELETE FROM user_sessions WHERE token = ? LIMIT 1');

    if (!$deleteStmt) {
        return;
    }

    $deleteStmt->bind_param('s', $tokenHash);
    $deleteStmt->execute();
    $deleteStmt->close();
}

function commerza_try_restore_session_from_cookie(mysqli $con): void
{
    if (!empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        return;
    }

    $cookieValue = (string)($_COOKIE[COMMERZA_REMEMBER_COOKIE] ?? '');
    if ($cookieValue === '') {
        return;
    }

    $parts = explode(':', $cookieValue, 2);
    if (count($parts) !== 2) {
        commerza_clear_remember_cookie();
        return;
    }

    $userId = (int)$parts[0];
    $rawToken = trim((string)$parts[1]);

    if ($userId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        commerza_clear_remember_cookie();
        return;
    }

    $tokenHash = hash('sha256', $rawToken);

    commerza_ensure_user_sessions_table($con);

    $stmt = $con->prepare(
        'SELECT u.id
         FROM user_sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ?
           AND s.token = ?
           AND s.expires_at > NOW()
         LIMIT 1'
    );

    if (!$stmt) {
        commerza_clear_remember_cookie();
        return;
    }

    $stmt->bind_param('is', $userId, $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessionRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$sessionRow) {
        commerza_clear_remember_cookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$sessionRow['id'];

    // Extend the remember-me token while the user stays active.
    commerza_issue_remember_token($con, (int)$sessionRow['id'], $tokenHash);
}

commerza_try_restore_session_from_cookie($con);
