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

    $projectRoot = dirname(__DIR__, 2);
    $backendRoot = dirname(__DIR__);
    commerza_load_env_file($projectRoot . DIRECTORY_SEPARATOR . '.env');
    commerza_load_env_file($backendRoot . DIRECTORY_SEPARATOR . '.env');

    $loaded = true;
}

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

    return preg_match('#/(?:admin/)?backend/.+\.php$#i', $scriptName) === 1;
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

function commerza_csp_nonce_value(): string
{
    static $nonce = '';

    if ($nonce !== '') {
        return $nonce;
    }

    $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

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
        "media-src 'self' blob: https:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com https://maps.googleapis.com https://www.googletagmanager.com https://js.stripe.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
        "connect-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com https://maps.googleapis.com https://maps.gstatic.com https://www.google-analytics.com https://www.googletagmanager.com https://api.stripe.com https://r.stripe.com https://m.stripe.network",
        "frame-src 'self' https://www.google.com https://www.google.com/maps https://maps.google.com https://www.recaptcha.net https://challenges.cloudflare.com https://js.stripe.com https://hooks.stripe.com",
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
    $projectRoot = realpath(dirname(__DIR__, 2));

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

        $projectRootPath = realpath(dirname(__DIR__, 2));
        $projectFolder = basename(is_string($projectRootPath) && $projectRootPath !== '' ? $projectRootPath : dirname(__DIR__, 2));

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

    $trimmedPath = trim($path);
    if ($trimmedPath !== '' && preg_match('#^https?://#i', $trimmedPath) === 1 && filter_var($trimmedPath, FILTER_VALIDATE_URL)) {
        return $trimmedPath;
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
        $breadcrumbCssHref = commerza_local_vendor_prefix() . 'frontend/assets/css/modules/layout/page-breadcrumb.css';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($breadcrumbCssHref, ENT_QUOTES, 'UTF-8') . '">';
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

    if ($filename === 'invoice.php') {
        $rawOrder = trim((string)($_GET['order'] ?? $_GET['order_number'] ?? ''));
        if ($rawOrder !== '') {
            $normalizedOrder = strtoupper(trim($rawOrder));
            $normalizedOrder = ltrim($normalizedOrder, '#');

            if (preg_match('/^ORD-[A-Z0-9]{4,30}$/', $normalizedOrder) === 1) {
                $targetUrl = commerza_absolute_url('/invoice/' . rawurlencode($normalizedOrder));

                $remainingQuery = $_GET;
                unset($remainingQuery['order'], $remainingQuery['order_number']);
                if (!empty($remainingQuery)) {
                    $targetUrl .= '?' . http_build_query($remainingQuery);
                }

                $requestPathNormalized = '/' . trim(str_replace('\\', '/', $requestPath), '/');
                $targetPathNormalized = '/' . trim((string)(parse_url($targetUrl, PHP_URL_PATH) ?? ''), '/');

                if (strtolower(rtrim($requestPathNormalized, '/')) !== strtolower(rtrim($targetPathNormalized, '/'))) {
                    header('Location: ' . $targetUrl, true, 301);
                    exit;
                }
            }
        }
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
    $hasAuthenticatedSession = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']) || isset($_SESSION['admin_user_id']);

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
        str_contains($normalized, 'cdn.jsdelivr.net/npm/bootstrap-icons')
        && (
            str_contains($normalized, '/font/bootstrap-icons.min.css')
            || str_contains($normalized, '/font/bootstrap-icons.css')
        )
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

    $openingTag = substr($tag, 0, $tagEnd);
    $remainder = substr($tag, $tagEnd);

    if (preg_match('/\/\s*$/', $openingTag) === 1) {
        $openingTag = preg_replace('/\/\s*$/', '', $openingTag) ?? $openingTag;
        return rtrim($openingTag) . ' ' . $attr . ' /' . $remainder;
    }

    return $openingTag . ' ' . $attr . $remainder;
}
