<?php

require_once __DIR__ . '/bootstrap_helpers.php';

commerza_bootstrap_env();
require_once __DIR__ . '/../cache/cache_helpers.php';

commerza_block_direct_system_backend_navigation();

$isHttps = commerza_request_is_https();

require_once __DIR__ . '/meta_normalizer.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/identity_schema.php';
require_once __DIR__ . '/remember_sessions.php';

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

$portEnv = getenv('COMMERZA_DB_PORT');
if ($portEnv === false) {
    $portEnv = getenv('DB_PORT');
}
$dbPort = is_string($portEnv) && preg_match('/^[0-9]{1,5}$/', trim($portEnv)) === 1
    ? (int)trim($portEnv)
    : 0;
if ($dbPort < 0 || $dbPort > 65535) {
    $dbPort = 0;
}

$socketEnv = getenv('COMMERZA_DB_SOCKET');
if ($socketEnv === false) {
    $socketEnv = getenv('DB_SOCKET');
}
$dbSocket = $socketEnv === false ? '' : trim((string)$socketEnv);

$passEnv = getenv('COMMERZA_DB_PASS');
if ($passEnv === false) {
    $passEnv = getenv('DB_PASS');
}
$pass = $passEnv === false ? '' : (string)$passEnv;

$con = mysqli_connect(
    $host,
    $user,
    $pass,
    $db,
    $dbPort,
    $dbSocket !== '' ? $dbSocket : null
);

if (!$con) {
    http_response_code(500);
    exit('Service unavailable.');
}

mysqli_set_charset($con, 'utf8mb4');

if (!function_exists('commerza_pdo_connection')) {
    function commerza_pdo_connection(): ?PDO
    {
        static $initialized = false;
        static $pdo = null;

        if ($initialized) {
            return $pdo;
        }

        $initialized = true;

        if (!class_exists('PDO')) {
            return null;
        }

        $pdoHost = trim((string)(getenv('COMMERZA_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost'));
        $pdoUser = trim((string)(getenv('COMMERZA_DB_USER') ?: getenv('DB_USER') ?: 'root'));
        $pdoDb = trim((string)(getenv('COMMERZA_DB_NAME') ?: getenv('DB_NAME') ?: 'commerza'));

        $pdoPortEnv = getenv('COMMERZA_DB_PORT');
        if ($pdoPortEnv === false) {
            $pdoPortEnv = getenv('DB_PORT');
        }
        $pdoPort = is_string($pdoPortEnv) && preg_match('/^[0-9]{1,5}$/', trim($pdoPortEnv)) === 1
            ? (int)trim($pdoPortEnv)
            : 0;
        if ($pdoPort < 0 || $pdoPort > 65535) {
            $pdoPort = 0;
        }

        $pdoSocketEnv = getenv('COMMERZA_DB_SOCKET');
        if ($pdoSocketEnv === false) {
            $pdoSocketEnv = getenv('DB_SOCKET');
        }
        $pdoSocket = $pdoSocketEnv === false ? '' : trim((string)$pdoSocketEnv);

        $pdoPassEnv = getenv('COMMERZA_DB_PASS');
        if ($pdoPassEnv === false) {
            $pdoPassEnv = getenv('DB_PASS');
        }
        $pdoPass = $pdoPassEnv === false ? '' : (string)$pdoPassEnv;

        if ($pdoSocket !== '') {
            $dsn = 'mysql:unix_socket=' . $pdoSocket . ';dbname=' . $pdoDb . ';charset=utf8mb4';
        } else {
            $dsn = 'mysql:host=' . $pdoHost . ';dbname=' . $pdoDb . ';charset=utf8mb4';
            if ($pdoPort > 0) {
                $dsn .= ';port=' . $pdoPort;
            }
        }

        try {
            $pdo = new PDO($dsn, $pdoUser, $pdoPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $error) {
            $pdo = null;
        }

        return $pdo;
    }
}

$GLOBALS['commerza_public_site_settings_payload'] = commerza_build_public_site_settings_payload($con);

commerza_ensure_users_identity_schema($con);

require_once __DIR__ . '/../jobs/expiry_cleanup.php';
require_once __DIR__ . '/../security/rate_limit.php';
require_once __DIR__ . '/../security/security_helpers.php';
require_once __DIR__ . '/../security/security_events.php';

if (
    function_exists('commerza_should_run_expiry_cleanup')
    && function_exists('commerza_run_expiry_cleanup')
    && commerza_should_run_expiry_cleanup()
) {
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

commerza_try_restore_session_from_cookie($con);
