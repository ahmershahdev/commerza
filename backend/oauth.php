<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';

function oauth_redirect_with_error(string $message, string $mode): void
{
    $_SESSION['oauth_error'] = $message;
    $target = $mode === 'signup' ? '../signup.php' : '../login.php';
    header('Location: ' . $target);
    exit;
}

function oauth_get_setting(mysqli $con, string $key, string $fallback = ''): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function oauth_app_base_url(): string
{
    $env = trim((string)getenv('COMMERZA_APP_URL'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = '';

    if ($scriptName !== '') {
        $basePath = preg_replace('#/backend/oauth\.php$#i', '', $scriptName) ?? '';
        $basePath = rtrim($basePath, '/');
    }

    return $scheme . '://' . $host . $basePath;
}

function oauth_first_non_empty_env(array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)getenv($key));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function oauth_post_form(string $url, array $data): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [false, []];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [false, []];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        parse_str($response, $decoded);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    return [$status >= 200 && $status < 300, $decoded];
}

function oauth_get_json(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [false, []];
    }

    $httpHeaders = array_merge(['Accept: application/json'], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $httpHeaders,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [false, []];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        parse_str($response, $decoded);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    return [$status >= 200 && $status < 300, $decoded];
}

function oauth_generate_unique_phone(mysqli $con): string
{
    for ($i = 0; $i < 10; $i++) {
        $candidate = '9' . str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

        $stmt = $con->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
        if (!$stmt) {
            break;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return $candidate;
        }
    }

    return '9' . str_pad((string)time(), 10, '0', STR_PAD_LEFT);
}

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    exit('Service unavailable.');
}

$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
$mode = strtolower(trim((string)($_GET['mode'] ?? 'login')));
$mode = in_array($mode, ['login', 'signup'], true) ? $mode : 'login';

if (!in_array($provider, ['google', 'facebook'], true)) {
    oauth_redirect_with_error('Invalid OAuth provider.', $mode);
}

$appBase = oauth_app_base_url();

$googleClientId = oauth_get_setting($con, 'google_oauth_client_id', oauth_first_non_empty_env([
    'COMMERZA_GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_ID',
]) ?: '610222424311-q5lpn4u4hnootrc87p9rvuuh6qmlr5g4.apps.googleusercontent.com');

$googleClientSecret = oauth_get_setting($con, 'google_oauth_client_secret', oauth_first_non_empty_env([
    'COMMERZA_GOOGLE_CLIENT_SECRET',
    'GOOGLE_CLIENT_SECRET',
]) ?: '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');

$googleRedirectUri = oauth_get_setting(
    $con,
    'google_oauth_redirect_uri',
    $appBase . '/oauth.php?provider=google'
);

$facebookClientId = oauth_get_setting($con, 'facebook_oauth_client_id', oauth_first_non_empty_env([
    'COMMERZA_FACEBOOK_CLIENT_ID',
    'FACEBOOK_CLIENT_ID',
]));
$facebookClientSecret = oauth_get_setting($con, 'facebook_oauth_client_secret', oauth_first_non_empty_env([
    'COMMERZA_FACEBOOK_CLIENT_SECRET',
    'FACEBOOK_CLIENT_SECRET',
]));
$facebookRedirectUri = oauth_get_setting(
    $con,
    'facebook_oauth_redirect_uri',
    $appBase . '/oauth.php?provider=facebook'
);

$providerConfig = [
    'google' => [
        'client_id' => trim($googleClientId),
        'client_secret' => trim($googleClientSecret),
        'redirect_uri' => trim($googleRedirectUri),
    ],
    'facebook' => [
        'client_id' => trim($facebookClientId),
        'client_secret' => trim($facebookClientSecret),
        'redirect_uri' => trim($facebookRedirectUri),
    ],
];

$config = $providerConfig[$provider];

$currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isLocalhost = preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|::1)(:\d+)?$/', $currentHost) === 1;

if ($isLocalhost) {
    $expectedRedirectUri = $appBase . '/oauth.php?provider=' . $provider;
    $configuredHost = strtolower((string)parse_url((string)$config['redirect_uri'], PHP_URL_HOST));
    $configuredPath = (string)parse_url((string)$config['redirect_uri'], PHP_URL_PATH);

    $isConfiguredLocal = in_array($configuredHost, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    $looksLikeOAuthPath = stripos($configuredPath, '/oauth.php') !== false;

    if (!$isConfiguredLocal || !$looksLikeOAuthPath) {
        $config['redirect_uri'] = $expectedRedirectUri;
    }
}

if ($config['client_id'] === '' || $config['client_secret'] === '' || $config['redirect_uri'] === '') {
    oauth_redirect_with_error(
        ucfirst($provider) . ' OAuth is not configured yet. Add client ID/secret and redirect URI in server settings.',
        $mode
    );
}

$hasCode = isset($_GET['code']) && trim((string)$_GET['code']) !== '';
$hasError = isset($_GET['error']) && trim((string)$_GET['error']) !== '';

if (!$hasCode && !$hasError) {
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = [
        'value' => $state,
        'provider' => $provider,
        'mode' => $mode,
        'issued_at' => time(),
    ];

    if ($provider === 'google') {
        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online',
        ];

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
        exit;
    }

    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'email,public_profile',
        'state' => $state,
    ];

    header('Location: https://www.facebook.com/v20.0/dialog/oauth?' . http_build_query($params));
    exit;
}

$stateData = $_SESSION['oauth_state'] ?? null;
unset($_SESSION['oauth_state']);

if (!is_array($stateData)) {
    oauth_redirect_with_error('OAuth session expired. Please try again.', $mode);
}

$stateMode = in_array(($stateData['mode'] ?? ''), ['login', 'signup'], true) ? (string)$stateData['mode'] : $mode;

if (($stateData['provider'] ?? '') !== $provider) {
    oauth_redirect_with_error('OAuth state mismatch. Please try again.', $stateMode);
}

if ((int)($stateData['issued_at'] ?? 0) < (time() - 600)) {
    oauth_redirect_with_error('OAuth session expired. Please try again.', $stateMode);
}

$incomingState = (string)($_GET['state'] ?? '');
if ($incomingState === '' || !hash_equals((string)$stateData['value'], $incomingState)) {
    oauth_redirect_with_error('Invalid OAuth state. Please try again.', $stateMode);
}

if ($hasError) {
    oauth_redirect_with_error('OAuth was cancelled or denied by the provider.', $stateMode);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    oauth_redirect_with_error('Missing OAuth authorization code.', $stateMode);
}

$email = '';
$name = '';

if ($provider === 'google') {
    [$tokenOk, $tokenData] = oauth_post_form('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);

    if (!$tokenOk || empty($tokenData['access_token'])) {
        oauth_redirect_with_error('Google token exchange failed.', $stateMode);
    }

    [$profileOk, $profile] = oauth_get_json(
        'https://openidconnect.googleapis.com/v1/userinfo',
        ['Authorization: Bearer ' . $tokenData['access_token']]
    );

    if (!$profileOk) {
        oauth_redirect_with_error('Unable to fetch Google profile.', $stateMode);
    }

    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? ''));
} else {
    [$tokenOk, $tokenData] = oauth_get_json(
        'https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ])
    );

    if (!$tokenOk || empty($tokenData['access_token'])) {
        oauth_redirect_with_error('Facebook token exchange failed.', $stateMode);
    }

    [$profileOk, $profile] = oauth_get_json(
        'https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,name,email',
            'access_token' => $tokenData['access_token'],
        ])
    );

    if (!$profileOk) {
        oauth_redirect_with_error('Unable to fetch Facebook profile.', $stateMode);
    }

    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? ''));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    oauth_redirect_with_error('Could not read a valid email from OAuth provider.', $stateMode);
}

if ($name === '') {
    $name = ucfirst(strtok($email, '@'));
}

$stmt = $con->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    oauth_redirect_with_error('Server error. Please try again.', $stateMode);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$existingUser = $result ? $result->fetch_assoc() : null;
$stmt->close();

$userId = 0;

if ($existingUser) {
    $userId = (int)$existingUser['id'];
} else {
    $phone = oauth_generate_unique_phone($con);
    $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $displayName = strlen($name) > 100 ? substr($name, 0, 100) : $name;

    $insertStmt = $con->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash)
         VALUES (?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        oauth_redirect_with_error('Server error. Please try again.', $stateMode);
    }

    $insertStmt->bind_param('ssss', $displayName, $email, $phone, $passwordHash);
    $ok = $insertStmt->execute();
    $newUserId = (int)$insertStmt->insert_id;
    $insertStmt->close();

    if (!$ok || $newUserId <= 0) {
        oauth_redirect_with_error('Could not create account via OAuth.', $stateMode);
    }

    $userId = $newUserId;
}

if ($userId <= 0) {
    oauth_redirect_with_error('OAuth login failed. Please try again.', $stateMode);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($stateMode === 'signup') {
    $_SESSION['flash_success'] = 'Your account was created with ' . ucfirst($provider) . ' and you are now logged in.';
}

header('Location: ../account.php');
exit;
