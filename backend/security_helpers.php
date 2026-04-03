<?php

function commerza_password_algo()
{
    if (defined('PASSWORD_ARGON2ID')) {
        return PASSWORD_ARGON2ID;
    }

    return PASSWORD_BCRYPT;
}

function commerza_password_hash_options(): array
{
    $algo = commerza_password_algo();

    if ($algo === PASSWORD_ARGON2ID) {
        return [
            'memory_cost' => 1 << 16,
            'time_cost' => 3,
            'threads' => 1,
        ];
    }

    return [
        'cost' => 12,
    ];
}

function commerza_password_hash(string $password): string
{
    $hash = password_hash($password, commerza_password_algo(), commerza_password_hash_options());
    if (is_string($hash) && $hash !== '') {
        return $hash;
    }

    $fallback = password_hash($password, PASSWORD_DEFAULT);
    return is_string($fallback) ? $fallback : '';
}

function commerza_password_verify(string $password, string $hash): bool
{
    if ($hash === '') {
        return false;
    }

    return password_verify($password, $hash);
}

function commerza_password_needs_rehash(string $hash): bool
{
    if ($hash === '') {
        return false;
    }

    return password_needs_rehash($hash, commerza_password_algo(), commerza_password_hash_options());
}

function commerza_password_policy_description(): string
{
    return 'Password must be 10-64 characters, include uppercase, lowercase, number, and special character, and must not contain spaces.';
}

function commerza_password_validate(string $password, ?string &$message = null): bool
{
    $length = strlen($password);
    if ($length < 10 || $length > 64) {
        $message = commerza_password_policy_description();
        return false;
    }

    if (preg_match('/\s/', $password) === 1) {
        $message = commerza_password_policy_description();
        return false;
    }

    $isValid = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,64}$/', $password) === 1;
    if (!$isValid) {
        $message = commerza_password_policy_description();
        return false;
    }

    return true;
}

function commerza_security_setting(mysqli $con, string $key, string $fallback = ''): string
{
    static $cache = [];

    $normalizedKey = strtolower(trim($key));
    if ($normalizedKey === '') {
        return $fallback;
    }

    if (array_key_exists($normalizedKey, $cache)) {
        $cachedValue = (string)$cache[$normalizedKey];
        return $cachedValue !== '' ? $cachedValue : $fallback;
    }

    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $normalizedKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $value = trim((string)($row['setting_val'] ?? ''));
    $cache[$normalizedKey] = $value;

    return $value !== '' ? $value : $fallback;
}

function commerza_env_first_non_empty(array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)getenv((string)$key));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function commerza_captcha_is_local_request(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }

    $host = explode(':', $host)[0];

    return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
}

function commerza_captcha_normalize_provider(string $value): string
{
    $provider = strtolower(trim($value));

    if (in_array($provider, ['none', 'off', 'disabled', '0'], true)) {
        return '';
    }

    if (in_array($provider, ['google', 'recaptcha', 'recaptcha_v2'], true)) {
        return 'recaptcha';
    }

    if (in_array($provider, ['turnstile', 'cloudflare', 'cf-turnstile'], true)) {
        return 'turnstile';
    }

    return 'turnstile';
}

function commerza_captcha_config(mysqli $con): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $enabledRaw = commerza_security_setting(
        $con,
        'captcha_enabled',
        commerza_env_first_non_empty(['COMMERZA_CAPTCHA_ENABLED'])
    );

    $enabledRaw = strtolower(trim($enabledRaw));
    $enabled = !in_array($enabledRaw, ['0', 'false', 'off', 'disabled', 'no'], true);

    $providerRaw = commerza_security_setting(
        $con,
        'captcha_provider',
        commerza_env_first_non_empty(['COMMERZA_CAPTCHA_PROVIDER'])
    );

    $provider = commerza_captcha_normalize_provider($providerRaw);

    $siteKey = '';
    $secretKey = '';
    $scriptUrl = '';
    $verifyUrl = '';
    $responseField = '';

    if ($provider === 'recaptcha') {
        $siteKey = commerza_security_setting(
            $con,
            'recaptcha_site_key',
            commerza_env_first_non_empty(['COMMERZA_RECAPTCHA_SITE_KEY', 'RECAPTCHA_SITE_KEY'])
        );
        $secretKey = commerza_security_setting(
            $con,
            'recaptcha_secret_key',
            commerza_env_first_non_empty(['COMMERZA_RECAPTCHA_SECRET_KEY', 'RECAPTCHA_SECRET_KEY'])
        );
        $scriptUrl = 'https://www.google.com/recaptcha/api.js';
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $responseField = 'g-recaptcha-response';
    } elseif ($provider === 'turnstile') {
        $siteKey = commerza_security_setting(
            $con,
            'turnstile_site_key',
            commerza_env_first_non_empty(['COMMERZA_TURNSTILE_SITE_KEY', 'TURNSTILE_SITE_KEY'])
        );
        $secretKey = commerza_security_setting(
            $con,
            'turnstile_secret_key',
            commerza_env_first_non_empty(['COMMERZA_TURNSTILE_SECRET_KEY', 'TURNSTILE_SECRET_KEY'])
        );
        $scriptUrl = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $responseField = 'cf-turnstile-response';
    }

    $isUsable = $enabled && $provider !== '' && $siteKey !== '' && $secretKey !== '';

    $cache = [
        'enabled' => $isUsable,
        'provider' => $provider,
        'site_key' => $siteKey,
        'secret_key' => $secretKey,
        'script_url' => $scriptUrl,
        'verify_url' => $verifyUrl,
        'response_field' => $responseField,
    ];

    return $cache;
}

function commerza_captcha_is_enabled(mysqli $con): bool
{
    $config = commerza_captcha_config($con);
    return (bool)($config['enabled'] ?? false);
}

function commerza_captcha_script_tag(mysqli $con): string
{
    $config = commerza_captcha_config($con);
    if (!(bool)($config['enabled'] ?? false)) {
        return '';
    }

    $scriptUrl = htmlspecialchars((string)$config['script_url'], ENT_QUOTES, 'UTF-8');
    $nonceAttr = function_exists('commerza_csp_nonce_attr') ? (' ' . commerza_csp_nonce_attr()) : '';

    return '<script' . $nonceAttr . ' src="' . $scriptUrl . '" async defer></script>';
}

function commerza_captcha_widget_html(mysqli $con, string $context = ''): string
{
    $config = commerza_captcha_config($con);
    if (!(bool)($config['enabled'] ?? false)) {
        return '';
    }

    $provider = (string)$config['provider'];
    $siteKey = htmlspecialchars((string)$config['site_key'], ENT_QUOTES, 'UTF-8');
    $safeContext = htmlspecialchars($context, ENT_QUOTES, 'UTF-8');

    if ($provider === 'recaptcha') {
        $widget = '<div class="g-recaptcha" data-sitekey="' . $siteKey . '"></div>';
        return '<div class="captcha-wrapper mt-3">' . $widget . '</div>';
    }

    $actionAttr = $safeContext !== '' ? (' data-action="' . $safeContext . '"') : '';
    $widget = '<div class="cf-turnstile" data-sitekey="' . $siteKey . '"' . $actionAttr . '></div>';

    return '<div class="captcha-wrapper mt-3">' . $widget . '</div>';
}

function commerza_captcha_http_post_json(string $url, array $payload): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => null,
            'error' => 'Unable to initialize CAPTCHA request.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = trim((string)curl_error($ch));
    curl_close($ch);

    if (!is_string($response) || $response === '') {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => $curlError !== '' ? $curlError : 'Empty CAPTCHA verification response.',
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'error' => 'Invalid CAPTCHA verification response.',
        ];
    }

    $ok = $status >= 200 && $status < 300;

    return [
        'ok' => $ok,
        'status' => $status,
        'data' => $decoded,
        'error' => $ok ? '' : ($curlError !== '' ? $curlError : 'CAPTCHA verification HTTP error.'),
    ];
}

function commerza_captcha_verify_submission(mysqli $con, array $request, string $context = ''): array
{
    $config = commerza_captcha_config($con);

    if (!(bool)($config['enabled'] ?? false)) {
        return [
            'ok' => true,
            'message' => '',
            'skipped' => true,
        ];
    }

    if (
        commerza_captcha_is_local_request()
        && trim((string)getenv('COMMERZA_CAPTCHA_BYPASS_LOCAL')) === '1'
    ) {
        return [
            'ok' => true,
            'message' => '',
            'skipped' => true,
        ];
    }

    $field = (string)($config['response_field'] ?? '');
    $token = trim((string)($request[$field] ?? ''));

    if ($token === '') {
        return [
            'ok' => false,
            'message' => 'Please complete the CAPTCHA challenge.',
            'skipped' => false,
        ];
    }

    $payload = [
        'secret' => (string)$config['secret_key'],
        'response' => $token,
    ];

    $clientIp = function_exists('commerza_client_ip') ? commerza_client_ip() : '';
    if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
        $payload['remoteip'] = $clientIp;
    }

    if ((string)$config['provider'] === 'turnstile' && $context !== '') {
        $payload['action'] = $context;
    }

    $verification = commerza_captcha_http_post_json((string)$config['verify_url'], $payload);
    if (!(bool)($verification['ok'] ?? false) || !is_array($verification['data'] ?? null)) {
        return [
            'ok' => false,
            'message' => 'Unable to validate CAPTCHA right now. Please try again.',
            'skipped' => false,
        ];
    }

    $data = $verification['data'];
    $isSuccess = !empty($data['success']);

    if ($isSuccess && (string)$config['provider'] === 'turnstile' && $context !== '') {
        $returnedAction = trim((string)($data['action'] ?? ''));
        if ($returnedAction !== '' && !hash_equals($context, $returnedAction)) {
            $isSuccess = false;
        }
    }

    if (!$isSuccess) {
        $errorCodes = [];
        if (isset($data['error-codes']) && is_array($data['error-codes'])) {
            foreach ($data['error-codes'] as $code) {
                $code = trim((string)$code);
                if ($code !== '') {
                    $errorCodes[] = $code;
                }
            }
        }

        $message = 'CAPTCHA verification failed. Please try again.';
        if (in_array('timeout-or-duplicate', $errorCodes, true)) {
            $message = 'CAPTCHA expired. Please complete it again.';
        }

        return [
            'ok' => false,
            'message' => $message,
            'skipped' => false,
            'error_codes' => $errorCodes,
        ];
    }

    return [
        'ok' => true,
        'message' => '',
        'skipped' => false,
    ];
}