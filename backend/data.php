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

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

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
    $scriptNonce = "'nonce-" . commerza_csp_nonce_value() . "'";

    return implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "img-src 'self' data: https:",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "script-src 'self' 'unsafe-inline' " . $scriptNonce . " https://cdn.jsdelivr.net https://code.jquery.com https://js.stripe.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net",
        "connect-src 'self' https://api.stripe.com https://r.stripe.com https://cdn.jsdelivr.net https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com",
        "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com",
        "form-action 'self'",
    ]);
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

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    $scriptName = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $segments = $scriptName === '' ? [] : explode('/', $scriptName);
    $prefix = '';

    if (count($segments) >= 2 && !str_ends_with((string)$segments[0], '.php')) {
        $prefix = '/' . trim((string)$segments[0]);
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

function commerza_is_backend_request(): bool
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return str_contains($script, '/backend/') || str_contains($script, '/admin/backend/');
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

    header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
    header('Vary: Accept-Encoding');
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

    if (stripos($buffer, '</head>') === false) {
        return $buffer;
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

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
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
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

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
?>