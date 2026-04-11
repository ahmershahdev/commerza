<?php

declare(strict_types=1);

require_once __DIR__ . '/../data.php';

function oauth_redirect_with_error(string $message, string $mode): void
{
    $_SESSION['oauth_error'] = $message;
    $target = $mode === 'signup' ? 'signup.php' : 'login.php';
    header('Location: ' . commerza_absolute_url($target));
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
    if (function_exists('commerza_public_base_url')) {
        return rtrim(commerza_public_base_url(), '/');
    }

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

function oauth_host_is_localhost(string $host): bool
{
    return preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|::1)(:\d+)?$/', strtolower(trim($host))) === 1;
}

function oauth_is_local_request(): bool
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (oauth_host_is_localhost($host)) {
        return true;
    }

    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

function oauth_format_provider_error(array $payload, string $fallback = ''): string
{
    $error = trim((string)($payload['error_description'] ?? ''));
    if ($error !== '') {
        return $error;
    }

    if (isset($payload['error']) && is_array($payload['error'])) {
        $error = trim((string)($payload['error']['message'] ?? ''));
        if ($error !== '') {
            return $error;
        }
    }

    $error = trim((string)($payload['error'] ?? ''));
    if ($error !== '') {
        return $error;
    }

    return trim($fallback);
}

function oauth_public_error(string $baseMessage, string $detail = ''): string
{
    $detail = trim($detail);
    if (!oauth_is_local_request() || $detail === '') {
        return $baseMessage;
    }

    if (strlen($detail) > 180) {
        $detail = substr($detail, 0, 180) . '...';
    }

    return $baseMessage . ' ' . $detail;
}

function oauth_post_form(string $url, array $data): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [false, [], 'Unable to initialize OAuth request.'];
    }

    $allowInsecureLocal = oauth_is_local_request() && trim((string)getenv('COMMERZA_OAUTH_STRICT_SSL')) !== '1';

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => !$allowInsecureLocal,
        CURLOPT_SSL_VERIFYHOST => $allowInsecureLocal ? 0 : 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = trim((string)curl_error($ch));
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [false, [], $curlError !== '' ? $curlError : 'Empty response from provider.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        parse_str($response, $decoded);
        if (!is_array($decoded)) {
            return [false, [], 'Invalid response from provider.'];
        }
    }

    $ok = $status >= 200 && $status < 300;
    $error = $ok ? '' : oauth_format_provider_error($decoded, $curlError);

    return [$ok, $decoded, $error];
}

function oauth_get_json(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [false, [], 'Unable to initialize OAuth request.'];
    }

    $httpHeaders = array_merge(['Accept: application/json'], $headers);
    $allowInsecureLocal = oauth_is_local_request() && trim((string)getenv('COMMERZA_OAUTH_STRICT_SSL')) !== '1';

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => !$allowInsecureLocal,
        CURLOPT_SSL_VERIFYHOST => $allowInsecureLocal ? 0 : 2,
        CURLOPT_HTTPHEADER => $httpHeaders,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = trim((string)curl_error($ch));
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [false, [], $curlError !== '' ? $curlError : 'Empty response from provider.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        parse_str($response, $decoded);
        if (!is_array($decoded)) {
            return [false, [], 'Invalid response from provider.'];
        }
    }

    $ok = $status >= 200 && $status < 300;
    $error = $ok ? '' : oauth_format_provider_error($decoded, $curlError);

    return [$ok, $decoded, $error];
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

$googleClientId = oauth_first_non_empty_env([
    'COMMERZA_GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_ID',
]);
if ($googleClientId === '') {
    $googleClientId = oauth_get_setting($con, 'google_oauth_client_id', '');
}

$googleClientSecret = oauth_first_non_empty_env([
    'COMMERZA_GOOGLE_CLIENT_SECRET',
    'GOOGLE_CLIENT_SECRET',
]);
if ($googleClientSecret === '') {
    $googleClientSecret = oauth_get_setting($con, 'google_oauth_client_secret', '');
}

$googleRedirectUri = oauth_first_non_empty_env([
    'COMMERZA_GOOGLE_REDIRECT_URI',
    'GOOGLE_REDIRECT_URI',
]);
if ($googleRedirectUri === '') {
    $googleRedirectUri = oauth_get_setting($con, 'google_oauth_redirect_uri', $appBase . '/oauth.php?provider=google');
}

$facebookClientId = oauth_first_non_empty_env([
    'COMMERZA_FACEBOOK_CLIENT_ID',
    'FACEBOOK_CLIENT_ID',
]);
if ($facebookClientId === '') {
    $facebookClientId = oauth_get_setting($con, 'facebook_oauth_client_id', '');
}

$facebookClientSecret = oauth_first_non_empty_env([
    'COMMERZA_FACEBOOK_CLIENT_SECRET',
    'FACEBOOK_CLIENT_SECRET',
]);
if ($facebookClientSecret === '') {
    $facebookClientSecret = oauth_get_setting($con, 'facebook_oauth_client_secret', '');
}

$facebookRedirectUri = oauth_first_non_empty_env([
    'COMMERZA_FACEBOOK_REDIRECT_URI',
    'FACEBOOK_REDIRECT_URI',
]);
if ($facebookRedirectUri === '') {
    $facebookRedirectUri = oauth_get_setting($con, 'facebook_oauth_redirect_uri', $appBase . '/oauth.php?provider=facebook');
}

$googleClientId = trim($googleClientId);
$googleClientSecret = trim($googleClientSecret);

if ($googleClientId === 'YOUR_GOOGLE_CLIENT_ID') {
    $googleClientId = '';
}

if ($googleClientSecret === 'YOUR_GOOGLE_CLIENT_SECRET') {
    $googleClientSecret = '';
}

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
$isLocalhost = oauth_host_is_localhost($currentHost);

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
    [$tokenOk, $tokenData, $tokenError] = oauth_post_form('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);

    if (!$tokenOk || empty($tokenData['access_token'])) {
        oauth_redirect_with_error(
            oauth_public_error('Google token exchange failed.', oauth_format_provider_error($tokenData, $tokenError)),
            $stateMode
        );
    }

    [$profileOk, $profile, $profileError] = oauth_get_json(
        'https://openidconnect.googleapis.com/v1/userinfo',
        ['Authorization: Bearer ' . $tokenData['access_token']]
    );

    if (!$profileOk) {
        oauth_redirect_with_error(
            oauth_public_error('Unable to fetch Google profile.', oauth_format_provider_error($profile, $profileError)),
            $stateMode
        );
    }

    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? ''));
} else {
    [$tokenOk, $tokenData, $tokenError] = oauth_get_json(
        'https://graph.facebook.com/v20.0/oauth/access_token?' . http_build_query([
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ])
    );

    if (!$tokenOk || empty($tokenData['access_token'])) {
        oauth_redirect_with_error(
            oauth_public_error('Facebook token exchange failed.', oauth_format_provider_error($tokenData, $tokenError)),
            $stateMode
        );
    }

    [$profileOk, $profile, $profileError] = oauth_get_json(
        'https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,name,email',
            'access_token' => $tokenData['access_token'],
        ])
    );

    if (!$profileOk) {
        oauth_redirect_with_error(
            oauth_public_error('Unable to fetch Facebook profile.', oauth_format_provider_error($profile, $profileError)),
            $stateMode
        );
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

$stmt = $con->prepare('SELECT id, full_name, username, username_slug, profile_visibility, phone FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    oauth_redirect_with_error('Server error. Please try again.', $stateMode);
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$existingUser = $result ? $result->fetch_assoc() : null;
$stmt->close();

$blockedOauthContact = $existingUser
    ? commerza_customer_blacklist_lookup($con, $email, (string)($existingUser['phone'] ?? ''))
    : commerza_customer_blacklist_lookup($con, $email, '');

if (is_array($blockedOauthContact)) {
    oauth_redirect_with_error(commerza_customer_blacklist_feedback_message($blockedOauthContact), $stateMode);
}

$userId = 0;

if ($existingUser) {
    $userId = (int)$existingUser['id'];

    $existingUsername = commerza_username_slug((string)($existingUser['username'] ?? ''));
    $existingSlug = commerza_username_slug((string)($existingUser['username_slug'] ?? ''));
    $existingVisibility = strtolower(trim((string)($existingUser['profile_visibility'] ?? 'private')));
    $needsUsernameRepair = !commerza_username_is_valid($existingUsername) || !commerza_username_is_valid($existingSlug);
    $needsVisibilityRepair = !in_array($existingVisibility, ['private', 'public'], true);

    if ($needsUsernameRepair || $needsVisibilityRepair) {
        $resolved = commerza_username_resolve_unique(
            $con,
            $existingUsername,
            (string)($existingUser['full_name'] ?? $name),
            $email,
            $userId
        );

        $fixedUsername = (string)($resolved['username'] ?? '');
        $fixedSlug = (string)($resolved['slug'] ?? $fixedUsername);
        $fixedVisibility = $needsVisibilityRepair ? 'private' : $existingVisibility;

        if (commerza_username_is_valid($fixedUsername) && commerza_username_is_valid($fixedSlug)) {
            $repairStmt = $con->prepare(
                'UPDATE users
                 SET username = ?, username_slug = ?, profile_visibility = ?
                 WHERE id = ?
                 LIMIT 1'
            );

            if ($repairStmt) {
                $repairStmt->bind_param('sssi', $fixedUsername, $fixedSlug, $fixedVisibility, $userId);
                $repairStmt->execute();
                $repairStmt->close();
            }
        }
    }
} else {
    $phone = oauth_generate_unique_phone($con);
    $passwordHash = commerza_password_hash(bin2hex(random_bytes(24)));
    $displayName = strlen($name) > 100 ? substr($name, 0, 100) : $name;
    $resolved = commerza_username_resolve_unique($con, '', $displayName, $email);
    $username = (string)($resolved['username'] ?? '');
    $usernameSlug = (string)($resolved['slug'] ?? $username);

    if (!commerza_username_is_valid($username) || !commerza_username_is_valid($usernameSlug)) {
        oauth_redirect_with_error('Could not generate username for OAuth account.', $stateMode);
    }

    $insertStmt = $con->prepare(
        'INSERT INTO users (full_name, username, username_slug, email, phone, password_hash, profile_visibility)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        oauth_redirect_with_error('Server error. Please try again.', $stateMode);
    }

    $profileVisibility = 'private';
    $insertStmt->bind_param('sssssss', $displayName, $username, $usernameSlug, $email, $phone, $passwordHash, $profileVisibility);
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

header('Location: ' . commerza_absolute_url('account.php'));
exit;
