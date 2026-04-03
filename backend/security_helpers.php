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

function commerza_captcha_context_key(string $context): string
{
    $normalized = strtolower(trim($context));
    if ($normalized === '') {
        $normalized = 'default';
    }

    $normalized = preg_replace('/[^a-z0-9_\-]+/', '_', $normalized);
    if (!is_string($normalized) || $normalized === '') {
        return 'default';
    }

    return substr($normalized, 0, 80);
}

function commerza_captcha_builtin_issue(string $context): array
{
    $contextKey = commerza_captcha_context_key($context);
    $store = $_SESSION['commerza_builtin_captcha'] ?? [];
    if (!is_array($store)) {
        $store = [];
    }

    $challenge = $store[$contextKey] ?? null;
    $isReusable =
        is_array($challenge)
        && isset($challenge['question'], $challenge['nonce'], $challenge['answer_hash'], $challenge['expires_at'])
        && (int)$challenge['expires_at'] > time() + 30;

    if ($isReusable) {
        return [
            'question' => (string)$challenge['question'],
            'nonce' => (string)$challenge['nonce'],
        ];
    }

    try {
        $left = random_int(2, 12);
        $right = random_int(1, 9);
        $operator = random_int(0, 1) === 0 ? '+' : '-';
        $nonce = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        $left = mt_rand(2, 12);
        $right = mt_rand(1, 9);
        $operator = mt_rand(0, 1) === 0 ? '+' : '-';
        $nonce = substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 16);
    }

    if ($operator === '-' && $left < $right) {
        $tmp = $left;
        $left = $right;
        $right = $tmp;
    }

    $answer = $operator === '+' ? ($left + $right) : ($left - $right);
    $answerHash = hash('sha256', (string)$answer . '|' . $nonce . '|' . $contextKey);

    $store[$contextKey] = [
        'question' => $left . ' ' . $operator . ' ' . $right,
        'nonce' => $nonce,
        'answer_hash' => $answerHash,
        'expires_at' => time() + 600,
    ];

    $_SESSION['commerza_builtin_captcha'] = $store;

    return [
        'question' => $store[$contextKey]['question'],
        'nonce' => $nonce,
    ];
}

function commerza_captcha_builtin_verify(array $request, string $context, string $answerField): array
{
    $answerRaw = trim((string)($request[$answerField] ?? ''));
    $nonce = trim((string)($request['commerza_captcha_token'] ?? ''));

    if ($answerRaw === '' || $nonce === '') {
        return [
            'ok' => false,
            'message' => 'Please complete the CAPTCHA challenge.',
            'skipped' => false,
        ];
    }

    if (preg_match('/^-?\d{1,4}$/', $answerRaw) !== 1) {
        return [
            'ok' => false,
            'message' => 'Invalid CAPTCHA answer format.',
            'skipped' => false,
        ];
    }

    $store = $_SESSION['commerza_builtin_captcha'] ?? [];
    if (!is_array($store)) {
        $store = [];
    }

    $contextKey = commerza_captcha_context_key($context);
    $challenge = $store[$contextKey] ?? null;
    if (!is_array($challenge)) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA expired. Please refresh and try again.',
            'skipped' => false,
        ];
    }

    $expiresAt = (int)($challenge['expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        unset($store[$contextKey]);
        $_SESSION['commerza_builtin_captcha'] = $store;
        return [
            'ok' => false,
            'message' => 'CAPTCHA expired. Please refresh and try again.',
            'skipped' => false,
        ];
    }

    $storedNonce = (string)($challenge['nonce'] ?? '');
    $storedHash = (string)($challenge['answer_hash'] ?? '');
    if ($storedNonce === '' || $storedHash === '' || !hash_equals($storedNonce, $nonce)) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA validation failed. Please try again.',
            'skipped' => false,
        ];
    }

    $candidateHash = hash('sha256', $answerRaw . '|' . $nonce . '|' . $contextKey);
    if (!hash_equals($storedHash, $candidateHash)) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA answer is incorrect.',
            'skipped' => false,
        ];
    }

    unset($store[$contextKey]);
    $_SESSION['commerza_builtin_captcha'] = $store;

    return [
        'ok' => true,
        'message' => '',
        'skipped' => false,
    ];
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
    $requiredRaw = strtolower(trim(commerza_env_first_non_empty(['COMMERZA_CAPTCHA_REQUIRED'])));
    if ($requiredRaw === '') {
        $requiredRaw = '1';
    }

    $forceRequired = !in_array($requiredRaw, ['0', 'false', 'off', 'disabled', 'no'], true);
    $enabled = $forceRequired || !in_array($enabledRaw, ['0', 'false', 'off', 'disabled', 'no'], true);

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

    if ($enabled && ($provider === '' || $siteKey === '' || $secretKey === '')) {
        $provider = 'builtin';
        $siteKey = '';
        $secretKey = '';
        $scriptUrl = '';
        $verifyUrl = '';
        $responseField = 'commerza_captcha_answer';
    }

    $isUsable = $enabled && $provider !== '' && ($provider === 'builtin' || ($siteKey !== '' && $secretKey !== ''));

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

    if ((string)($config['provider'] ?? '') === 'builtin') {
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

    if ($provider === 'builtin') {
        $challenge = commerza_captcha_builtin_issue($context);
        $question = htmlspecialchars((string)($challenge['question'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nonce = htmlspecialchars((string)($challenge['nonce'] ?? ''), ENT_QUOTES, 'UTF-8');
        $field = htmlspecialchars((string)($config['response_field'] ?? 'commerza_captcha_answer'), ENT_QUOTES, 'UTF-8');

        return '<div class="captcha-wrapper mt-3">'
            . '<label class="form-label" for="captcha-' . $safeContext . '">Security Check: Solve ' . $question . '</label>'
            . '<input type="text" class="form-control" id="captcha-' . $safeContext . '" name="' . $field . '" placeholder="Enter answer" inputmode="numeric" pattern="-?[0-9]{1,4}" maxlength="4" required>'
            . '<input type="hidden" name="commerza_captcha_token" value="' . $nonce . '">'
            . '</div>';
    }

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

    if ((string)($config['provider'] ?? '') === 'builtin') {
        return commerza_captcha_builtin_verify($request, $context, (string)($config['response_field'] ?? 'commerza_captcha_answer'));
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