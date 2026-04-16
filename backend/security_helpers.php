<?php

declare(strict_types=1);

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

    if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $isPublicIp = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;

        return !$isPublicIp;
    }

    if (strpos($host, '.') === false) {
        return true;
    }

    foreach (['.local', '.localhost', '.test', '.invalid', '.lan', '.home', '.internal'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return true;
        }
    }

    return false;
}

function commerza_captcha_local_bypass_enabled(): bool
{
    $raw = strtolower(trim((string)getenv('COMMERZA_CAPTCHA_BYPASS_LOCAL')));
    if ($raw === '') {
        return true;
    }

    return in_array($raw, ['1', 'true', 'on', 'yes'], true);
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
        return 'recaptcha';
    }

    return 'recaptcha';
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

function commerza_captcha_normalize_answer(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    $normalized = preg_replace('/[^a-z0-9 ]+/', '', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    return substr(trim($normalized), 0, 64);
}

function commerza_secure_nonce_hex(int $byteLength = 16): string
{
    $byteLength = max(16, $byteLength);

    try {
        return bin2hex(random_bytes($byteLength));
    } catch (Throwable $exception) {
        throw new RuntimeException('Unable to generate a secure nonce.', 0, $exception);
    }
}

function commerza_captcha_builtin_question_bank(): array
{
    return [
        [
            'question' => 'What is the capital of France?',
            'answers' => ['paris'],
            'type' => 'knowledge',
        ],
        [
            'question' => 'What is the capital of Pakistan?',
            'answers' => ['islamabad'],
            'type' => 'knowledge',
        ],
        [
            'question' => 'What is the capital of Japan?',
            'answers' => ['tokyo'],
            'type' => 'knowledge',
        ],
        [
            'question' => 'How many days are in one week?',
            'answers' => ['7', 'seven'],
            'type' => 'knowledge',
        ],
        [
            'question' => 'Which month comes after June?',
            'answers' => ['july'],
            'type' => 'knowledge',
        ],
        [
            'question' => 'What is the first letter of the English alphabet?',
            'answers' => ['a'],
            'type' => 'knowledge',
        ],
    ];
}

function commerza_captcha_builtin_generate_math_challenge(): array
{
    try {
        $operatorSeed = random_int(0, 3);
        if ($operatorSeed === 0) {
            $left = random_int(13, 99);
            $right = random_int(4, 37);
            return [
                'question' => 'Solve: ' . $left . ' + ' . $right,
                'answers' => [(string)($left + $right)],
                'type' => 'math',
            ];
        }

        if ($operatorSeed === 1) {
            $left = random_int(20, 140);
            $right = random_int(5, 39);
            if ($left < $right) {
                $tmp = $left;
                $left = $right;
                $right = $tmp;
            }

            return [
                'question' => 'Solve: ' . $left . ' - ' . $right,
                'answers' => [(string)($left - $right)],
                'type' => 'math',
            ];
        }

        if ($operatorSeed === 2) {
            $left = random_int(3, 14);
            $right = random_int(4, 12);
            return [
                'question' => 'Solve: ' . $left . ' x ' . $right,
                'answers' => [(string)($left * $right)],
                'type' => 'math',
            ];
        }

        $divisor = random_int(2, 12);
        $quotient = random_int(2, 12);
        $left = $divisor * $quotient;

        return [
            'question' => 'Solve: ' . $left . ' / ' . $divisor,
            'answers' => [(string)$quotient],
            'type' => 'math',
        ];
    } catch (Throwable $exception) {
        $left = mt_rand(10, 70);
        $right = mt_rand(3, 25);
        return [
            'question' => 'Solve: ' . $left . ' + ' . $right,
            'answers' => [(string)($left + $right)],
            'type' => 'math',
        ];
    }
}

function commerza_captcha_builtin_generate_challenge(): array
{
    $knowledgeBank = commerza_captcha_builtin_question_bank();
    $useKnowledge = false;

    try {
        $useKnowledge = random_int(1, 100) <= 55;
    } catch (Throwable $exception) {
        $useKnowledge = mt_rand(1, 100) <= 55;
    }

    if ($useKnowledge && !empty($knowledgeBank)) {
        try {
            $index = random_int(0, count($knowledgeBank) - 1);
        } catch (Throwable $exception) {
            $index = mt_rand(0, max(0, count($knowledgeBank) - 1));
        }

        $chosen = $knowledgeBank[$index] ?? null;
        if (is_array($chosen)) {
            return [
                'question' => (string)($chosen['question'] ?? 'What is the capital of France?'),
                'answers' => (array)($chosen['answers'] ?? ['paris']),
                'type' => (string)($chosen['type'] ?? 'knowledge'),
            ];
        }
    }

    return commerza_captcha_builtin_generate_math_challenge();
}

function commerza_captcha_builtin_issue(string $context): array
{
    $contextKey = commerza_captcha_context_key($context);
    $store = $_SESSION['commerza_builtin_captcha'] ?? [];
    if (!is_array($store)) {
        $store = [];
    }

    $challenge = $store[$contextKey] ?? null;
    $isReusable = false;

    if (
        is_array($challenge)
        && isset($challenge['question'], $challenge['nonce'], $challenge['expires_at'])
        && (int)$challenge['expires_at'] > time() + 30
    ) {
        $storedHashes = [];
        if (isset($challenge['answer_hashes']) && is_array($challenge['answer_hashes'])) {
            foreach ($challenge['answer_hashes'] as $hash) {
                $hash = trim((string)$hash);
                if ($hash !== '') {
                    $storedHashes[] = $hash;
                }
            }
        }

        if (empty($storedHashes)) {
            $legacyHash = trim((string)($challenge['answer_hash'] ?? ''));
            if ($legacyHash !== '') {
                $storedHashes[] = $legacyHash;
            }
        }

        $isReusable = !empty($storedHashes) && (int)($challenge['attempts'] ?? 0) < 3;
    }

    if ($isReusable) {
        return [
            'question' => (string)$challenge['question'],
            'nonce' => (string)$challenge['nonce'],
        ];
    }

    $challengeData = commerza_captcha_builtin_generate_challenge();
    $question = trim((string)($challengeData['question'] ?? 'What is the capital of France?'));
    if ($question === '') {
        $question = 'What is the capital of France?';
    }

    $answers = [];
    if (isset($challengeData['answers']) && is_array($challengeData['answers'])) {
        foreach ($challengeData['answers'] as $candidate) {
            $normalizedAnswer = commerza_captcha_normalize_answer((string)$candidate);
            if ($normalizedAnswer !== '') {
                $answers[] = $normalizedAnswer;
            }
        }
    }

    if (empty($answers)) {
        $answers[] = 'paris';
    }

    $nonce = commerza_secure_nonce_hex(16);

    $answerHashes = [];
    foreach ($answers as $answer) {
        $answerHashes[] = hash('sha256', $answer . '|' . $nonce . '|' . $contextKey);
    }

    $store[$contextKey] = [
        'question' => $question,
        'nonce' => $nonce,
        'answer_hashes' => array_values(array_unique($answerHashes)),
        'expires_at' => time() + 900,
        'issued_at' => time(),
        'min_solve_seconds' => 4,
        'attempts' => 0,
        'challenge_type' => (string)($challengeData['type'] ?? 'mixed'),
    ];

    $_SESSION['commerza_builtin_captcha'] = $store;

    return [
        'question' => $store[$contextKey]['question'],
        'nonce' => $nonce,
    ];
}

function commerza_captcha_builtin_verify(array $request, string $context, string $answerField, string $tokenField = 'commerza_captcha_token'): array
{
    $answerRaw = trim((string)($request[$answerField] ?? ''));
    $nonce = trim((string)($request[$tokenField] ?? ''));

    if ($answerRaw === '' || $nonce === '') {
        return [
            'ok' => false,
            'message' => 'Please complete the CAPTCHA challenge.',
            'skipped' => false,
        ];
    }

    $normalizedAnswer = commerza_captcha_normalize_answer($answerRaw);
    if ($normalizedAnswer === '') {
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
    $storedHashes = [];
    if (isset($challenge['answer_hashes']) && is_array($challenge['answer_hashes'])) {
        foreach ($challenge['answer_hashes'] as $hash) {
            $hash = trim((string)$hash);
            if ($hash !== '') {
                $storedHashes[] = $hash;
            }
        }
    }

    if (empty($storedHashes)) {
        $legacyHash = trim((string)($challenge['answer_hash'] ?? ''));
        if ($legacyHash !== '') {
            $storedHashes[] = $legacyHash;
        }
    }

    if ($storedNonce === '' || empty($storedHashes) || !hash_equals($storedNonce, $nonce)) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA validation failed. Please try again.',
            'skipped' => false,
        ];
    }

    $issuedAt = (int)($challenge['issued_at'] ?? 0);
    $minSolveSeconds = max(0, (int)($challenge['min_solve_seconds'] ?? 0));
    if ($issuedAt > 0 && $minSolveSeconds > 0 && (time() - $issuedAt) < $minSolveSeconds) {
        return [
            'ok' => false,
            'message' => 'Please wait a few seconds before submitting the challenge.',
            'skipped' => false,
        ];
    }

    $candidateHash = hash('sha256', $normalizedAnswer . '|' . $nonce . '|' . $contextKey);
    $answerValid = false;
    foreach ($storedHashes as $hash) {
        if (hash_equals($hash, $candidateHash)) {
            $answerValid = true;
            break;
        }
    }

    if (!$answerValid) {
        $attempts = (int)($challenge['attempts'] ?? 0) + 1;
        if ($attempts >= 5) {
            unset($store[$contextKey]);
        } else {
            $challenge['attempts'] = $attempts;
            $store[$contextKey] = $challenge;
        }

        $_SESSION['commerza_builtin_captcha'] = $store;

        return [
            'ok' => false,
            'message' => $attempts >= 5
                ? 'Too many incorrect CAPTCHA attempts. Please reload and try again.'
                : 'CAPTCHA answer is incorrect. Please try again.',
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
    if ($enabled && $provider === '') {
        $provider = 'recaptcha';
    }

    $siteKey = '';
    $secretKey = '';
    $v3SiteKey = '';
    $v3SecretKey = '';
    $v3MinScore = 0.65;
    $scriptUrl = '';
    $verifyUrl = '';
    $responseField = '';
    $v3ResponseField = 'g-recaptcha-v3-response';

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

        $v3SiteKey = commerza_security_setting(
            $con,
            'recaptcha_v3_site_key',
            commerza_env_first_non_empty(['COMMERZA_RECAPTCHA_V3_SITE_KEY'])
        );

        $v3SecretKey = commerza_security_setting(
            $con,
            'recaptcha_v3_secret_key',
            commerza_env_first_non_empty(['COMMERZA_RECAPTCHA_V3_SECRET_KEY'])
        );

        $v3MinScoreRaw = commerza_security_setting(
            $con,
            'recaptcha_v3_min_score',
            commerza_env_first_non_empty(['COMMERZA_RECAPTCHA_V3_MIN_SCORE'])
        );

        if ($v3MinScoreRaw !== '') {
            $parsedScore = (float)$v3MinScoreRaw;
            if ($parsedScore >= 0.1 && $parsedScore <= 0.99) {
                $v3MinScore = max(0.55, $parsedScore);
            }
        }

        $scriptUrl = 'https://www.recaptcha.net/recaptcha/api.js';
        $verifyUrl = 'https://www.recaptcha.net/recaptcha/api/siteverify';
        $responseField = 'g-recaptcha-response';
    }

    if ($enabled && $provider !== 'recaptcha') {
        $provider = 'recaptcha';
    }

    $isRecaptchaV2Usable = $enabled
        && $provider === 'recaptcha'
        && $siteKey !== ''
        && $secretKey !== '';

    $isRecaptchaV3Usable = $enabled
        && $provider === 'recaptcha'
        && $v3SiteKey !== ''
        && $v3SecretKey !== '';

    $cache = [
        'enabled' => $enabled,
        'required' => $enabled,
        'provider' => $provider,
        'v2_enabled' => $isRecaptchaV2Usable,
        'v3_enabled' => $isRecaptchaV3Usable,
        'site_key' => $siteKey,
        'secret_key' => $secretKey,
        'v3_site_key' => $v3SiteKey,
        'v3_secret_key' => $v3SecretKey,
        'v3_min_score' => $v3MinScore,
        'script_url' => $scriptUrl,
        'verify_url' => $verifyUrl,
        'response_field' => $responseField,
        'v3_response_field' => $v3ResponseField,
        'honeypot_field' => 'commerza_contact_website',
        'timer_field' => 'commerza_captcha_started_at',
        'fallback_answer_field' => 'commerza_captcha_answer',
        'fallback_token_field' => 'commerza_captcha_token',
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

    if (commerza_captcha_is_local_request() && commerza_captcha_local_bypass_enabled()) {
        return '';
    }

    if ((string)($config['provider'] ?? '') !== 'recaptcha') {
        return '';
    }

    $rawScriptUrl = (string)($config['script_url'] ?? 'https://www.recaptcha.net/recaptcha/api.js');
    $scriptUrl = htmlspecialchars($rawScriptUrl, ENT_QUOTES, 'UTF-8');
    $nonceAttr = function_exists('commerza_csp_nonce_attr') ? (' ' . commerza_csp_nonce_attr()) : '';
    $v3EnabledJs = !empty($config['v3_enabled']) ? 'true' : 'false';
    $v3SiteKeyJson = json_encode((string)($config['v3_site_key'] ?? ''), JSON_UNESCAPED_SLASHES);
    $scriptUrlJson = json_encode($rawScriptUrl, JSON_UNESCAPED_SLASHES);

    $loaderTag = '';
    if (!empty($config['v2_enabled']) || !empty($config['v3_enabled'])) {
        $loaderTag = '<script' . $nonceAttr . ' src="' . $scriptUrl . '" async defer></script>';
    }

    $networkGuard = <<<HTML
<script{$nonceAttr}>
(function () {
    if (window.__commerzaCaptchaNetworkGuardAttached) {
        return;
    }
    window.__commerzaCaptchaNetworkGuardAttached = true;

    var captchaScriptUrl = {$scriptUrlJson};
    var v3Enabled = {$v3EnabledJs};
    var v3SiteKey = {$v3SiteKeyJson};
    var reconnectAttempts = 0;
    var maxReconnectAttempts = 2;
    var warned = false;

    function hasCaptchaWidget() {
        return !!document.querySelector('.g-recaptcha, .captcha-widget, .commerza-captcha-wrapper');
    }

    function eachCaptchaContainer(callback) {
        var nodes = document.querySelectorAll('.commerza-captcha-wrapper');
        nodes.forEach(function (node) {
            callback(node);
        });
    }

    function showFallback(container, message) {
        if (!container) {
            return;
        }

        var shell = container.querySelector('.commerza-captcha-fallback');
        if (!shell) {
            return;
        }

        shell.style.display = 'block';
        var text = shell.querySelector('[data-commerza-captcha-fallback-message]');
        if (text && message) {
            text.textContent = message;
        }
    }

    function showFallbackEverywhere(message) {
        eachCaptchaContainer(function (container) {
            showFallback(container, message);
        });
    }

    function bindFallbackToggle(container) {
        if (!container || container.dataset.captchaToggleBound === '1') {
            return;
        }

        container.dataset.captchaToggleBound = '1';
        var toggle = container.querySelector('[data-commerza-fallback-toggle]');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            showFallback(container, 'Backup challenge is active. Read the question carefully and enter the exact answer.');
        });
    }

    function hardenFallbackInput(container) {
        if (!container || container.dataset.captchaInputHardened === '1') {
            return;
        }

        container.dataset.captchaInputHardened = '1';

        var noSelectNodes = container.querySelectorAll(
            '.commerza-captcha-fallback label, [data-commerza-captcha-fallback-message]'
        );
        noSelectNodes.forEach(function (node) {
            node.style.userSelect = 'none';
            node.style.webkitUserSelect = 'none';
            node.style.msUserSelect = 'none';
        });

        var answerInput = container.querySelector('input[data-commerza-captcha-answer]');
        if (!answerInput) {
            return;
        }

        answerInput.style.userSelect = 'none';
        answerInput.style.webkitUserSelect = 'none';
        answerInput.style.msUserSelect = 'none';

        ['paste', 'copy', 'cut', 'drop', 'contextmenu'].forEach(function (eventName) {
            answerInput.addEventListener(eventName, function (event) {
                event.preventDefault();
            });
        });

        answerInput.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && ['c', 'v', 'x'].indexOf((event.key || '').toLowerCase()) !== -1) {
                event.preventDefault();
            }
        });
    }

    function contextToAction(context) {
        var normalized = (context || 'default').toString().trim().toLowerCase();
        normalized = normalized.replace(/[^a-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '');
        if (!normalized) {
            return 'commerza_default';
        }
        return 'commerza_' + normalized.substring(0, 60);
    }

    function isElementVisible(element) {
        if (!element) {
            return false;
        }

        if (element.offsetParent !== null) {
            return true;
        }

        return element.getClientRects && element.getClientRects().length > 0;
    }

    function issueV3Token(form, action) {
        if (!v3Enabled || !form || !window.grecaptcha || typeof window.grecaptcha.execute !== 'function') {
            return Promise.resolve('');
        }

        return new Promise(function (resolve) {
            try {
                window.grecaptcha.ready(function () {
                    window.grecaptcha
                        .execute(v3SiteKey, { action: action })
                        .then(function (token) {
                            resolve((token || '').toString());
                        })
                        .catch(function () {
                            resolve('');
                        });
                });
            } catch (_error) {
                resolve('');
            }
        });
    }

    function refreshV3Tokens() {
        if (!v3Enabled) {
            return;
        }

        eachCaptchaContainer(function (container) {
            if (!isElementVisible(container)) {
                return;
            }

            var form = container.closest('form');
            if (!form) {
                return;
            }

            var tokenField = form.querySelector('input[name="g-recaptcha-v3-response"]');
            if (!tokenField) {
                return;
            }

            if ((tokenField.value || '').trim() !== '') {
                return;
            }

            var contextField = form.querySelector('input[name="commerza_captcha_context"]');
            var action = contextToAction(contextField ? contextField.value : 'default');
            issueV3Token(form, action).then(function (token) {
                tokenField.value = token;
            });
        });
    }

    function attachFormSubmitGuards() {
        eachCaptchaContainer(function (container) {
            bindFallbackToggle(container);
            hardenFallbackInput(container);

            var form = container.closest('form');
            if (!form || form.dataset.commerzaCaptchaSubmitBound === '1') {
                return;
            }

            form.dataset.commerzaCaptchaSubmitBound = '1';

            form.addEventListener('submit', function (event) {
                if (!v3Enabled) {
                    return;
                }

                var tokenField = form.querySelector('input[name="g-recaptcha-v3-response"]');
                if (!tokenField || (tokenField.value || '').trim() !== '') {
                    return;
                }

                if (!window.grecaptcha || typeof window.grecaptcha.execute !== 'function') {
                    showFallback(container, 'Google verification is unavailable. Please solve the backup challenge.');
                    return;
                }

                event.preventDefault();

                var contextField = form.querySelector('input[name="commerza_captcha_context"]');
                var action = contextToAction(contextField ? contextField.value : 'default');
                issueV3Token(form, action).then(function (token) {
                    tokenField.value = token;
                    form.submit();
                });
            });
        });
    }

    function renderFallbackAlert(message) {
        var existing = document.getElementById('commerza-captcha-network-alert');
        if (existing) {
            var text = existing.querySelector('[data-captcha-alert-text]');
            if (text) {
                text.textContent = message;
            }
            existing.style.display = 'block';
            return;
        }

        var box = document.createElement('div');
        box.id = 'commerza-captcha-network-alert';
        box.setAttribute('role', 'alert');
        box.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:9999;max-width:360px;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,102,0,.55);background:rgba(18,18,18,.96);color:#f2f2f2;box-shadow:0 12px 24px rgba(0,0,0,.45);font:600 13px/1.45 Inter,Arial,sans-serif;';

        box.innerHTML = '<div style="display:flex;align-items:flex-start;gap:10px;"><div style="flex:1;"><div style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#ffb36a;margin-bottom:4px;">Captcha Notice</div><div data-captcha-alert-text></div></div><button type="button" aria-label="Close" style="border:0;background:transparent;color:#d9d9d9;font-size:16px;line-height:1;cursor:pointer;padding:0 2px;">&times;</button></div>';

        var body = document.body || document.documentElement;
        body.appendChild(box);

        var textNode = box.querySelector('[data-captcha-alert-text]');
        if (textNode) {
            textNode.textContent = message;
        }

        var closeBtn = box.querySelector('button');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                box.style.display = 'none';
            });
        }
    }

    function showNetworkAlert(message) {
        if (warned) {
            return;
        }

        warned = true;

        if (typeof window.showNotif === 'function') {
            window.showNotif(message, 'warning');
            return;
        }

        if (typeof window.showAccountMessage === 'function') {
            window.showAccountMessage(message, 'warning');
            return;
        }

        renderFallbackAlert(message);
        showFallbackEverywhere(message);
    }

    function hideNetworkAlert() {
        warned = false;
        var existing = document.getElementById('commerza-captcha-network-alert');
        if (existing) {
            existing.style.display = 'none';
        }
    }

    function attemptReconnect() {
        if (!captchaScriptUrl || reconnectAttempts >= maxReconnectAttempts) {
            return;
        }

        reconnectAttempts += 1;

        var probe = document.createElement('script');
        probe.src = captchaScriptUrl;
        probe.async = true;
        probe.defer = true;
        probe.onload = function () {
            window.setTimeout(function () {
                attachFormSubmitGuards();
                hideNetworkAlert();
            }, 350);
        };
        probe.onerror = function () {
            if (reconnectAttempts >= maxReconnectAttempts) {
                showNetworkAlert('Google CAPTCHA is still unreachable. Backup challenge is now enabled.');
            }
        };

        (document.head || document.documentElement).appendChild(probe);
    }

    function checkCaptchaHealth() {
        if (!hasCaptchaWidget()) {
            return;
        }

        if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
            attachFormSubmitGuards();
            hideNetworkAlert();
            return;
        }

        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            showNetworkAlert('Internet seems offline. CAPTCHA will appear after the connection is restored.');
            return;
        }

        if (reconnectAttempts < maxReconnectAttempts) {
            attemptReconnect();
            return;
        }

        showNetworkAlert('CAPTCHA is loading slowly. Backup challenge is enabled so you can continue securely.');
    }

    function scheduleHealthCheck() {
        window.setTimeout(checkCaptchaHealth, 4000);
        window.setTimeout(checkCaptchaHealth, 9000);
    }

    window.addEventListener('offline', function () {
        if (!hasCaptchaWidget()) {
            return;
        }
        showNetworkAlert('Internet seems offline. CAPTCHA cannot be verified right now.');
    });

    window.addEventListener('online', function () {
        hideNetworkAlert();
        attemptReconnect();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            attachFormSubmitGuards();
            scheduleHealthCheck();
        }, { once: true });
    } else {
        attachFormSubmitGuards();
        scheduleHealthCheck();
    }
})();
</script>
HTML;

    return $loaderTag . $networkGuard;
}

function commerza_captcha_widget_html(mysqli $con, string $context = ''): string
{
    $config = commerza_captcha_config($con);
    if (!(bool)($config['enabled'] ?? false)) {
        return '';
    }

    if (commerza_captcha_is_local_request() && commerza_captcha_local_bypass_enabled()) {
        return '';
    }

    if ((string)($config['provider'] ?? '') !== 'recaptcha') {
        return '';
    }

    $contextKey = commerza_captcha_context_key($context);
    $challenge = commerza_captcha_builtin_issue($contextKey);
    $question = htmlspecialchars((string)($challenge['question'] ?? '3 + 4'), ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars((string)($challenge['nonce'] ?? ''), ENT_QUOTES, 'UTF-8');

    $honeypotField = htmlspecialchars((string)($config['honeypot_field'] ?? 'commerza_contact_website'), ENT_QUOTES, 'UTF-8');
    $timerField = htmlspecialchars((string)($config['timer_field'] ?? 'commerza_captcha_started_at'), ENT_QUOTES, 'UTF-8');
    $answerField = htmlspecialchars((string)($config['fallback_answer_field'] ?? 'commerza_captcha_answer'), ENT_QUOTES, 'UTF-8');
    $tokenField = htmlspecialchars((string)($config['fallback_token_field'] ?? 'commerza_captcha_token'), ENT_QUOTES, 'UTF-8');
    $v3Field = htmlspecialchars((string)($config['v3_response_field'] ?? 'g-recaptcha-v3-response'), ENT_QUOTES, 'UTF-8');
    $contextSafe = htmlspecialchars($contextKey, ENT_QUOTES, 'UTF-8');

    $v2Enabled = !empty($config['v2_enabled']);
    $v3Enabled = !empty($config['v3_enabled']);

    $widget = '';
    $renderGoogleWidget = $v2Enabled;
    if ($renderGoogleWidget) {
        $siteKey = htmlspecialchars((string)($config['site_key'] ?? ''), ENT_QUOTES, 'UTF-8');
        $widget = '<div class="g-recaptcha captcha-widget" data-theme="dark" data-sitekey="' . $siteKey . '" style="margin:0 auto;"></div>';
    }

    $fallbackMessage = $v2Enabled || $v3Enabled
        ? 'Google verification may not always appear. Read the question carefully and enter the exact answer to continue securely.'
        : 'Google CAPTCHA keys are not configured. Read the question carefully and enter the exact answer to continue securely.';

    $fallbackDisplay = (!$v2Enabled && !$v3Enabled) ? 'block' : 'none';
    $toggleDisplay = (!$v2Enabled && !$v3Enabled) ? 'none' : 'inline-flex';

    $wrapperStyle = 'display:flex;justify-content:center;width:100%;';
    $shellStyle = 'display:flex;flex-direction:column;justify-content:center;align-items:center;width:min(100%,390px);padding:12px;border-radius:16px;border:0;background:linear-gradient(180deg,rgba(18,18,18,.96),rgba(8,8,8,.96));box-shadow:0 16px 32px rgba(0,0,0,.45),inset 0 0 0 1px rgba(255,255,255,.03);';

    return '<div class="captcha-wrapper mt-3 commerza-captcha-wrapper" data-commerza-captcha-context="' . $contextSafe . '" style="' . $wrapperStyle . '">'
        . '<div class="captcha-shell" style="' . $shellStyle . '">'
        . '<input type="hidden" name="' . $timerField . '" value="' . (string)time() . '">'
        . '<input type="hidden" name="commerza_captcha_context" value="' . $contextSafe . '">'
        . '<input type="hidden" name="' . $v3Field . '" value="">'
        . '<div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">'
        . '<label>Leave this field empty</label>'
        . '<input type="text" name="' . $honeypotField . '" value="" autocomplete="off" tabindex="-1">'
        . '</div>'
        . $widget
        . '<button type="button" data-commerza-fallback-toggle style="display:' . $toggleDisplay . ';align-items:center;justify-content:center;margin-top:10px;padding:8px 10px;border-radius:10px;border:1px solid rgba(255,165,110,.35);background:rgba(255,122,26,.08);color:#ffd3ab;font:600 12px/1.35 Inter,Arial,sans-serif;cursor:pointer;">Use backup question</button>'
        . '<div class="commerza-captcha-fallback" style="display:' . $fallbackDisplay . ';margin-top:10px;padding:10px;border-radius:12px;border:1px solid rgba(255,168,96,.35);background:rgba(22,22,22,.74);">'
        . '<div data-commerza-captcha-fallback-message style="color:#ffd8b6;font-size:12px;line-height:1.45;margin-bottom:8px;user-select:none;-webkit-user-select:none;">' . htmlspecialchars($fallbackMessage, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<label style="display:block;color:#f3e7da;font-size:13px;font-weight:600;margin-bottom:6px;user-select:none;-webkit-user-select:none;">Security question: ' . $question . '</label>'
        . '<div style="color:#d0d0d0;font-size:11px;line-height:1.4;margin-bottom:8px;user-select:none;-webkit-user-select:none;">Type the exact answer and submit.</div>'
        . '<input type="text" name="' . $answerField . '" inputmode="text" pattern="[-A-Za-z0-9 ]{1,64}" maxlength="64" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-commerza-captcha-answer="1" class="form-control bg-secondary border-0 text-light" style="min-height:42px;user-select:none;-webkit-user-select:none;">'
        . '<input type="hidden" name="' . $tokenField . '" value="' . $token . '">'
        . '</div>'
        . '</div>'
        . '</div>';
}

function commerza_captcha_http_post_json(string $url, array $payload): array
{
    $lastResult = [
        'ok' => false,
        'status' => 0,
        'data' => null,
        'error' => 'CAPTCHA verification failed.',
    ];

    $maxAttempts = 3;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
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
            CURLOPT_TIMEOUT => 12,
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
            $lastResult = [
                'ok' => false,
                'status' => $status,
                'data' => null,
                'error' => $curlError !== '' ? $curlError : 'Empty CAPTCHA verification response.',
            ];

            continue;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $lastResult = [
                'ok' => false,
                'status' => $status,
                'data' => null,
                'error' => 'Invalid CAPTCHA verification response.',
            ];

            continue;
        }

        $ok = $status >= 200 && $status < 300;
        $lastResult = [
            'ok' => $ok,
            'status' => $status,
            'data' => $decoded,
            'error' => $ok ? '' : ($curlError !== '' ? $curlError : 'CAPTCHA verification HTTP error.'),
        ];

        if ($ok) {
            return $lastResult;
        }

        if ($status >= 400 && $status < 500 && $status !== 429) {
            return $lastResult;
        }
    }

    return $lastResult;
}

function commerza_captcha_recaptcha_action_for_context(string $context): string
{
    $key = commerza_captcha_context_key($context);
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9_\-]+/', '_', $key);
    if (!is_string($key)) {
        $key = 'default';
    }

    $key = trim($key, '_');
    if ($key === '') {
        $key = 'default';
    }

    return 'commerza_' . substr($key, 0, 60);
}

function commerza_captcha_error_codes(array $verificationData): array
{
    $errorCodes = [];
    if (isset($verificationData['error-codes']) && is_array($verificationData['error-codes'])) {
        foreach ($verificationData['error-codes'] as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $errorCodes[] = $code;
            }
        }
    }

    return $errorCodes;
}

function commerza_captcha_normalize_host(string $host): string
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return '';
    }

    if (strlen($normalized) > 2 && $normalized[0] === '[' && substr($normalized, -1) === ']') {
        $normalized = trim($normalized, '[]');
    }

    $normalized = preg_replace('/:\d+$/', '', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    if (strpos($normalized, 'www.') === 0) {
        $normalized = substr($normalized, 4);
    }

    return trim($normalized);
}

function commerza_captcha_hostname_matches_request(string $hostname): bool
{
    $tokenHost = commerza_captcha_normalize_host($hostname);
    if ($tokenHost === '') {
        return false;
    }

    $requestHosts = [
        (string)($_SERVER['HTTP_HOST'] ?? ''),
        (string)($_SERVER['SERVER_NAME'] ?? ''),
    ];

    $hasComparableHost = false;
    foreach ($requestHosts as $requestHostRaw) {
        $requestHost = commerza_captcha_normalize_host($requestHostRaw);
        if ($requestHost === '') {
            continue;
        }

        $hasComparableHost = true;
        if (hash_equals($requestHost, $tokenHost)) {
            return true;
        }
    }

    if (!$hasComparableHost) {
        return true;
    }

    return false;
}

function commerza_captcha_verify_recaptcha_token(
    array $config,
    string $secret,
    string $token,
    string $context,
    float $minScore = 0.0
): array {
    if ($secret === '') {
        return [
            'ok' => false,
            'message' => 'CAPTCHA configuration is incomplete. Please contact support.',
            'transport_error' => false,
            'error_codes' => [],
        ];
    }

    if ($token === '') {
        return [
            'ok' => false,
            'message' => 'Please complete the CAPTCHA challenge.',
            'transport_error' => false,
            'error_codes' => [],
        ];
    }

    $tokenLength = strlen($token);
    if ($tokenLength < 20 || $tokenLength > 4096) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA token is invalid. Please retry.',
            'transport_error' => false,
            'error_codes' => [],
        ];
    }

    $payload = [
        'secret' => $secret,
        'response' => $token,
    ];

    $clientIp = function_exists('commerza_client_ip') ? commerza_client_ip() : '';
    if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
        $payload['remoteip'] = $clientIp;
    }

    $verification = commerza_captcha_http_post_json((string)($config['verify_url'] ?? ''), $payload);
    if (!(bool)($verification['ok'] ?? false) || !is_array($verification['data'] ?? null)) {
        return [
            'ok' => false,
            'message' => 'Unable to validate Google CAPTCHA right now. Please use the backup challenge and submit again.',
            'transport_error' => true,
            'error_codes' => [],
        ];
    }

    $data = $verification['data'];
    if (empty($data['success'])) {
        $errorCodes = commerza_captcha_error_codes($data);
        $message = 'CAPTCHA verification failed. Please try again.';

        if (in_array('timeout-or-duplicate', $errorCodes, true)) {
            $message = 'CAPTCHA expired. Please complete it again.';
        } elseif (in_array('missing-input-response', $errorCodes, true)) {
            $message = 'Please complete the CAPTCHA challenge.';
        } elseif (in_array('invalid-input-response', $errorCodes, true)) {
            $message = 'CAPTCHA response is invalid. Please retry.';
        }

        return [
            'ok' => false,
            'message' => $message,
            'transport_error' => false,
            'error_codes' => $errorCodes,
        ];
    }

    if ($minScore > 0) {
        $expectedAction = commerza_captcha_recaptcha_action_for_context($context);
        $actualAction = strtolower(trim((string)($data['action'] ?? '')));

        if ($actualAction === '' || !hash_equals($expectedAction, $actualAction)) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA action check failed. Please retry the form.',
                'transport_error' => false,
                'error_codes' => [],
                'action_mismatch' => true,
            ];
        }

        $hostname = trim((string)($data['hostname'] ?? ''));
        if ($hostname === '' || !commerza_captcha_hostname_matches_request($hostname)) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA host validation failed. Please retry the form.',
                'transport_error' => false,
                'error_codes' => [],
                'hostname_mismatch' => true,
            ];
        }

        $challengeTsRaw = trim((string)($data['challenge_ts'] ?? ''));
        $challengeTs = $challengeTsRaw === '' ? false : strtotime($challengeTsRaw);
        if ($challengeTs === false) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA token timestamp is invalid. Please retry.',
                'transport_error' => false,
                'error_codes' => [],
                'token_expired' => true,
            ];
        }

        $tokenAgeSeconds = time() - (int)$challengeTs;
        if ($tokenAgeSeconds < -120 || $tokenAgeSeconds > 300) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA token expired. Please retry or use the backup challenge.',
                'transport_error' => false,
                'error_codes' => [],
                'token_expired' => true,
            ];
        }

        $scoreRaw = $data['score'] ?? null;
        $score = is_numeric($scoreRaw) ? (float)$scoreRaw : -1.0;
        if ($score < 0.0 || $score > 1.0) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA score data is invalid. Please retry the form.',
                'transport_error' => false,
                'error_codes' => [],
                'score_invalid' => true,
            ];
        }

        if ($score < $minScore) {
            return [
                'ok' => false,
                'message' => 'Security verification score is too low. Please use the backup challenge and submit again.',
                'transport_error' => false,
                'error_codes' => [],
                'score_low' => true,
                'score' => $score,
            ];
        }
    }

    return [
        'ok' => true,
        'message' => '',
        'transport_error' => false,
        'error_codes' => [],
        'data' => $data,
    ];
}

function commerza_captcha_verify_submission(mysqli $con, array $request, string $context = ''): array
{
    $config = commerza_captcha_config($con);

    if (!(bool)($config['enabled'] ?? false)) {
        if ((bool)($config['required'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'CAPTCHA is required but not configured correctly. Please contact support.',
                'skipped' => false,
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'skipped' => true,
        ];
    }

    if (commerza_captcha_is_local_request() && commerza_captcha_local_bypass_enabled()) {
        return [
            'ok' => true,
            'message' => '',
            'skipped' => true,
        ];
    }

    if ((string)($config['provider'] ?? '') !== 'recaptcha') {
        return [
            'ok' => false,
            'message' => 'CAPTCHA configuration is invalid. Please contact support.',
            'skipped' => false,
        ];
    }

    $honeypotField = (string)($config['honeypot_field'] ?? 'commerza_contact_website');
    $timerField = (string)($config['timer_field'] ?? 'commerza_captcha_started_at');
    $answerField = (string)($config['fallback_answer_field'] ?? 'commerza_captcha_answer');
    $fallbackTokenField = (string)($config['fallback_token_field'] ?? 'commerza_captcha_token');
    $v2Field = (string)($config['response_field'] ?? 'g-recaptcha-response');
    $v3Field = (string)($config['v3_response_field'] ?? 'g-recaptcha-v3-response');

    if (trim((string)($request[$honeypotField] ?? '')) !== '') {
        return [
            'ok' => false,
            'message' => 'CAPTCHA validation failed. Please try again.',
            'skipped' => false,
        ];
    }

    $startedAtRaw = trim((string)($request[$timerField] ?? ''));
    if ($startedAtRaw === '' || preg_match('/^\d{9,12}$/', $startedAtRaw) !== 1) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA challenge is missing. Please reload and try again.',
            'skipped' => false,
        ];
    }

    $startedAt = (int)$startedAtRaw;
    $now = time();
    if ($startedAt > ($now + 60) || $startedAt < ($now - 7200)) {
        return [
            'ok' => false,
            'message' => 'CAPTCHA challenge expired. Please reload and try again.',
            'skipped' => false,
        ];
    }

    if (($now - $startedAt) < 2) {
        return [
            'ok' => false,
            'message' => 'Please wait a moment and submit again.',
            'skipped' => false,
        ];
    }

    $fallbackAnswer = trim((string)($request[$answerField] ?? ''));
    $fallbackToken = trim((string)($request[$fallbackTokenField] ?? ''));
    $hasFallbackAttempt = $fallbackAnswer !== '';

    if ($hasFallbackAttempt) {
        $fallbackResult = commerza_captcha_builtin_verify($request, $context, $answerField, $fallbackTokenField);
        if (!(bool)($fallbackResult['ok'] ?? false)) {
            return $fallbackResult;
        }

        return [
            'ok' => true,
            'message' => '',
            'skipped' => false,
            'used_fallback' => true,
        ];
    }

    $v2Enabled = !empty($config['v2_enabled']);
    $v3Enabled = !empty($config['v3_enabled']);

    if (!$v2Enabled && !$v3Enabled) {
        $fallbackResult = commerza_captcha_builtin_verify($request, $context, $answerField, $fallbackTokenField);
        if (!(bool)($fallbackResult['ok'] ?? false)) {
            return $fallbackResult;
        }

        return [
            'ok' => true,
            'message' => '',
            'skipped' => false,
            'used_fallback' => true,
        ];
    }

    $v2Token = trim((string)($request[$v2Field] ?? ''));
    $v3Token = trim((string)($request[$v3Field] ?? ''));
    $verifiedByV2 = false;
    $verifiedByV3 = false;

    if ($v3Enabled) {
        if ($v3Token !== '') {
            $v3Verification = commerza_captcha_verify_recaptcha_token(
                $config,
                (string)($config['v3_secret_key'] ?? ''),
                $v3Token,
                $context,
                (float)($config['v3_min_score'] ?? 0.45)
            );

            if (!(bool)($v3Verification['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($v3Verification['message'] ?? 'CAPTCHA verification failed. Please try again.'),
                    'skipped' => false,
                    'error_codes' => (array)($v3Verification['error_codes'] ?? []),
                    'allow_fallback' => !empty($v3Verification['transport_error'])
                        || !empty($v3Verification['score_low'])
                        || !empty($v3Verification['action_mismatch'])
                        || !empty($v3Verification['hostname_mismatch'])
                        || !empty($v3Verification['token_expired'])
                        || !empty($v3Verification['score_invalid']),
                ];
            }

            $verifiedByV3 = true;
        } elseif ($v2Enabled && $v2Token !== '') {
            $v2Verification = commerza_captcha_verify_recaptcha_token(
                $config,
                (string)($config['secret_key'] ?? ''),
                $v2Token,
                $context,
                0.0
            );

            if (!(bool)($v2Verification['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($v2Verification['message'] ?? 'CAPTCHA verification failed. Please try again.'),
                    'skipped' => false,
                    'error_codes' => (array)($v2Verification['error_codes'] ?? []),
                    'allow_fallback' => !empty($v2Verification['transport_error']),
                ];
            }

            $verifiedByV2 = true;
        } else {
            return [
                'ok' => false,
                'message' => 'Complete one verification method: Google CAPTCHA or backup challenge.',
                'skipped' => false,
                'allow_fallback' => true,
            ];
        }
    } elseif ($v2Enabled) {
        if ($v2Token === '') {
            return [
                'ok' => false,
                'message' => 'Please complete the Google CAPTCHA checkbox or use the backup challenge.',
                'skipped' => false,
                'allow_fallback' => true,
            ];
        }

        $v2Verification = commerza_captcha_verify_recaptcha_token(
            $config,
            (string)($config['secret_key'] ?? ''),
            $v2Token,
            $context,
            0.0
        );

        if (!(bool)($v2Verification['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($v2Verification['message'] ?? 'CAPTCHA verification failed. Please try again.'),
                'skipped' => false,
                'error_codes' => (array)($v2Verification['error_codes'] ?? []),
                'allow_fallback' => !empty($v2Verification['transport_error']),
            ];
        }

        $verifiedByV2 = true;
    }

    return [
        'ok' => true,
        'message' => '',
        'skipped' => false,
        'used_fallback' => false,
        'v2_verified' => $verifiedByV2,
        'v3_verified' => $verifiedByV3,
    ];
}

function commerza_request_id_from_server(array $request = []): string
{
    $headerValue = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($headerValue !== '') {
        return $headerValue;
    }

    return trim((string)($request['request_id'] ?? ''));
}

function commerza_is_valid_request_id(string $requestId): bool
{
    $requestId = trim($requestId);
    if ($requestId === '') {
        return false;
    }

    return preg_match('/^[A-Za-z0-9._:-]{12,128}$/', $requestId) === 1;
}

function commerza_ensure_idempotency_table(mysqli $con): bool
{
    static $ready = false;

    if ($ready) {
        return true;
    }

    $sql =
        'CREATE TABLE IF NOT EXISTS request_idempotency (
            id BIGINT NOT NULL AUTO_INCREMENT,
            scope_key VARCHAR(120) NOT NULL,
            request_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_scope_request (scope_key, request_hash),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $ok = $con->query($sql) === true;
    if ($ok) {
        $ready = true;
    }

    return $ok;
}

function commerza_idempotency_consume(mysqli $con, string $scope, string $requestId, int $ttlSeconds = 86400): array
{
    $normalizedScope = strtolower(trim($scope));
    if ($normalizedScope === '') {
        $normalizedScope = 'default';
    }

    if (!commerza_is_valid_request_id($requestId)) {
        return [
            'ok' => false,
            'duplicate' => false,
            'status' => 422,
            'message' => 'Invalid or missing X-Request-ID.',
        ];
    }

    if (!commerza_ensure_idempotency_table($con)) {
        return [
            'ok' => false,
            'duplicate' => false,
            'status' => 500,
            'message' => 'Unable to initialize idempotency protection.',
        ];
    }

    static $cleanupDone = false;
    if (!$cleanupDone) {
        $ttl = max(300, $ttlSeconds);
        $hours = (int)max(1, ceil($ttl / 3600));
        $con->query('DELETE FROM request_idempotency WHERE created_at < (NOW() - INTERVAL ' . $hours . ' HOUR)');
        $cleanupDone = true;
    }

    $requestHash = hash('sha256', trim($requestId));

    $stmt = $con->prepare(
        'INSERT INTO request_idempotency (scope_key, request_hash)
         VALUES (?, ?)'
    );

    if (!$stmt) {
        return [
            'ok' => false,
            'duplicate' => false,
            'status' => 500,
            'message' => 'Unable to apply idempotency protection.',
        ];
    }

    $stmt->bind_param('ss', $normalizedScope, $requestHash);
    $executed = $stmt->execute();
    $errorNumber = (int)$stmt->errno;
    $stmt->close();

    if ($executed) {
        return [
            'ok' => true,
            'duplicate' => false,
            'status' => 200,
            'message' => '',
        ];
    }

    if ($errorNumber === 1062) {
        return [
            'ok' => false,
            'duplicate' => true,
            'status' => 409,
            'message' => 'Duplicate request detected and ignored.',
        ];
    }

    return [
        'ok' => false,
        'duplicate' => false,
        'status' => 500,
        'message' => 'Unable to apply idempotency protection.',
    ];
}
