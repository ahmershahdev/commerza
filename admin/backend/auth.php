<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/data.php';
require_once __DIR__ . '/../../backend/mailer.php';

function admin_ensure_schema(mysqli $con): void
{
    $createTableSql =
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INT NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM("admin") NOT NULL DEFAULT "admin",
            profile_picture VARCHAR(255) DEFAULT NULL,
            reset_token VARCHAR(255) DEFAULT NULL,
            reset_token_expiry DATETIME DEFAULT NULL,
            two_factor_code_hash VARCHAR(255) DEFAULT NULL,
            two_factor_expires_at DATETIME DEFAULT NULL,
            two_factor_attempts INT NOT NULL DEFAULT 0,
            two_factor_last_sent_at DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            last_login_ip VARCHAR(45) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $con->query($createTableSql);

    $missingColumns = [
        'two_factor_code_hash' => 'VARCHAR(255) DEFAULT NULL',
        'two_factor_expires_at' => 'DATETIME DEFAULT NULL',
        'two_factor_attempts' => 'INT NOT NULL DEFAULT 0',
        'two_factor_last_sent_at' => 'DATETIME DEFAULT NULL',
    ];

    foreach ($missingColumns as $column => $definition) {
        $safeColumn = $con->real_escape_string($column);
        $check = $con->query("SHOW COLUMNS FROM admin_users LIKE '{$safeColumn}'");
        if (!($check instanceof mysqli_result) || $check->num_rows === 0) {
            $con->query("ALTER TABLE admin_users ADD COLUMN {$column} {$definition}");
        }
    }

    $configuredEmail = trim((string)getenv('COMMERZA_ADMIN_BOOTSTRAP_EMAIL'));
    $defaultEmail = filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)
        ? strtolower($configuredEmail)
        : 'commerza.ahmer@gmail.com';

    $configuredPassword = trim((string)getenv('COMMERZA_ADMIN_BOOTSTRAP_PASSWORD'));
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''))));
    $isLocalHost = $host === ''
        || $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1')
        || str_starts_with($host, '[::1]');

    $defaultPassword = $configuredPassword !== ''
        ? $configuredPassword
        : ($isLocalHost ? 'Commerza@2026' : '');

    $countResult = $con->query('SELECT COUNT(*) AS total FROM admin_users');
    $total = 0;
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $total = $row ? (int)$row['total'] : 0;
    }

    if ($total > 0) {
        return;
    }

    if (!filter_var($defaultEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $passwordPolicyError = null;
    if ($defaultPassword === '' || !commerza_password_validate($defaultPassword, $passwordPolicyError)) {
        return;
    }

    $hash = commerza_password_hash($defaultPassword);
    $insertStmt = $con->prepare(
        'INSERT INTO admin_users (full_name, email, password_hash, role, is_active)
         VALUES (?, ?, ?, "admin", 1)'
    );

    if (!$insertStmt) {
        return;
    }

    $name = 'Commerza Admin';
    $insertStmt->bind_param('sss', $name, $defaultEmail, $hash);
    $insertStmt->execute();
    $insertStmt->close();
}

admin_ensure_schema($con);

function admin_generate_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['admin_csrf_token'];
}

function admin_validate_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['admin_csrf_token'])) {
        return false;
    }

    return hash_equals((string)$_SESSION['admin_csrf_token'], (string)$token);
}

function admin_get_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        $parts = explode(',', (string)$candidate);
        $ip = trim((string)$parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function admin_get_reset_key(mysqli $con): string
{
    $legacyDefaultKey = 'COMMERZA-RESET-2026';
    $envKey = trim((string)getenv('COMMERZA_ADMIN_RESET_KEY'));

    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $envKey !== '' ? $envKey : $legacyDefaultKey;
    }

    $keyName = 'admin_reset_key';
    $stmt->bind_param('s', $keyName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $value = trim((string)($row['setting_val'] ?? ''));

    if ($envKey !== '' && ($value === '' || hash_equals($legacyDefaultKey, $value))) {
        return $envKey;
    }

    if ($value !== '') {
        return $value;
    }

    return $envKey !== '' ? $envKey : $legacyDefaultKey;
}

function admin_get_by_email(mysqli $con, string $email): ?array
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $stmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            password_hash,
            reset_token,
            reset_token_expiry,
            two_factor_code_hash,
            two_factor_expires_at,
            two_factor_attempts,
            is_active
         FROM admin_users
         WHERE email = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function admin_get_by_id(mysqli $con, int $adminId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, full_name, email, role, is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function admin_get_primary_admin(mysqli $con): ?array
{
    $result = $con->query(
        'SELECT id, full_name, email, reset_token, reset_token_expiry, is_active
         FROM admin_users
         WHERE is_active = 1
         ORDER BY id ASC
         LIMIT 1'
    );

    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row ?: null;
}

function admin_update_last_login(mysqli $con, int $adminId): void
{
    $ip = admin_get_client_ip();

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET last_login_at = NOW(), last_login_ip = ?
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('si', $ip, $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_login_user(mysqli $con, array $admin): void
{
    session_regenerate_id(true);

    $_SESSION['admin_user_id'] = (int)$admin['id'];
    $_SESSION['admin_authenticated_at'] = time();
    unset($_SESSION['admin_2fa_pending']);

    admin_update_last_login($con, (int)$admin['id']);
    admin_generate_csrf_token();
}

function admin_logout_user(): void
{
    unset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_authenticated_at'],
        $_SESSION['admin_csrf_token'],
        $_SESSION['admin_2fa_pending']
    );
}

function admin_safe_redirect_target(?string $value, string $fallback = 'admin-panel.php'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $fallback;
    }

    if (strpos($value, '/') !== false || strpos($value, '\\') !== false) {
        return $fallback;
    }

    $allowed = [
        'admin-panel.php',
        'admin-login.php',
        'admin-verify-2fa.php',
        'admin-forgot-password.php',
        'admin-forgot-email.php',
    ];

    return in_array($value, $allowed, true) ? $value : $fallback;
}

function admin_require_login(mysqli $con): array
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($adminId <= 0) {
        header('Location: admin-login.php');
        exit;
    }

    $admin = admin_get_by_id($con, $adminId);
    if (!$admin || (int)$admin['is_active'] !== 1) {
        admin_logout_user();
        header('Location: admin-login.php');
        exit;
    }

    return $admin;
}

function admin_require_login_api(mysqli $con): array
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($adminId <= 0) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Unauthorized.',
        ]);
        exit;
    }

    $admin = admin_get_by_id($con, $adminId);
    if (!$admin || (int)$admin['is_active'] !== 1) {
        admin_logout_user();
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Unauthorized.',
        ]);
        exit;
    }

    return $admin;
}

function admin_has_permission(array $admin, string $permission): bool
{
    $permission = strtolower(trim($permission));
    if ($permission === '') {
        return true;
    }

    $role = strtolower(trim((string)($admin['role'] ?? 'admin')));

    // Current schema supports admin role only; this keeps future role checks centralized.
    if ($role === 'admin') {
        return true;
    }

    return false;
}

function admin_require_permission_api(array $admin, string $permission): void
{
    if (admin_has_permission($admin, $permission)) {
        return;
    }

    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Forbidden.',
    ]);
    exit;
}

function admin_api_scope(string $prefix, string $action = 'default'): string
{
    $prefix = strtolower(trim($prefix));
    $action = strtolower(trim($action));

    if ($prefix === '') {
        $prefix = 'admin_api';
    }

    if ($action === '') {
        $action = 'default';
    }

    $normalizedPrefix = preg_replace('/[^a-z0-9_\-.]+/', '_', $prefix) ?? 'admin_api';
    $normalizedAction = preg_replace('/[^a-z0-9_\-.]+/', '_', $action) ?? 'default';

    return trim($normalizedPrefix . '.' . $normalizedAction, '.');
}

function admin_api_rate_limit_guard(
    mysqli $con,
    array $admin,
    string $scope,
    int $maxAttempts = 120,
    int $windowSeconds = 60,
    int $blockSeconds = 120,
    int $escalatedBlockSeconds = 300
): void {
    $identifier = strtolower(trim((string)($admin['email'] ?? '')));
    if ($identifier === '') {
        $identifier = 'admin_' . (int)($admin['id'] ?? 0);
    }

    $ipAddress = admin_get_client_ip();
    $rate = commerza_rate_limit_check(
        $con,
        $scope,
        $identifier,
        $ipAddress,
        max(1, $maxAttempts),
        max(60, $windowSeconds),
        max(60, $blockSeconds),
        max($blockSeconds, $escalatedBlockSeconds)
    );

    if ((bool)($rate['allowed'] ?? true)) {
        return;
    }

    $retryAfter = max(1, (int)($rate['retry_after'] ?? $blockSeconds));

    commerza_security_log_rate_limit_block(
        $con,
        $scope,
        'admin',
        $identifier,
        $ipAddress,
        $retryAfter
    );

    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'message' => 'Too many requests. Please retry shortly.',
        'retry_after' => $retryAfter,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_api_log_security_event(
    mysqli $con,
    array $admin,
    string $eventType,
    string $severity = 'info',
    array $details = []
): void {
    $actorIdentifier = strtolower(trim((string)($admin['email'] ?? '')));
    if ($actorIdentifier === '') {
        $actorIdentifier = 'admin_' . (int)($admin['id'] ?? 0);
    }

    commerza_security_log_event($con, [
        'event_type' => trim($eventType) !== '' ? $eventType : 'admin.action',
        'severity' => $severity,
        'actor_type' => 'admin',
        'actor_identifier' => $actorIdentifier,
        'admin_id' => (int)($admin['id'] ?? 0),
        'ip_address' => admin_get_client_ip(),
        'details' => $details,
    ]);
}

function admin_request_id_value(array $request = []): string
{
    $headerValue = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($headerValue !== '') {
        return $headerValue;
    }

    return trim((string)($request['request_id'] ?? ''));
}

function admin_enforce_post_idempotency_guard(mysqli $con): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        return;
    }

    $scriptName = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (!str_contains($scriptName, '/admin/backend/')) {
        return;
    }

    if (str_ends_with($scriptName, '/auth.php')) {
        return;
    }

    $scriptBase = strtolower((string)basename($scriptName, '.php'));
    if ($scriptBase === '') {
        $scriptBase = 'admin_api';
    }

    $action = strtolower(trim((string)($_POST['action'] ?? 'post')));
    if ($action === '') {
        $action = 'post';
    }

    $scope = 'admin_' . preg_replace('/[^a-z0-9_\-.]+/', '_', $scriptBase) . '.' . preg_replace('/[^a-z0-9_\-.]+/', '_', $action);
    $requestId = admin_request_id_value($_POST);
    $idempotency = commerza_idempotency_consume($con, $scope, $requestId, 21600);

    if ((bool)($idempotency['ok'] ?? false)) {
        return;
    }

    $status = (int)($idempotency['status'] ?? 409);
    if ($status <= 0) {
        $status = 409;
    }

    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => (string)($idempotency['message'] ?? 'Duplicate request detected and ignored.'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

admin_enforce_post_idempotency_guard($con);

function admin_normalize_numeric_code(string $code): string
{
    $digits = preg_replace('/\D+/', '', trim($code));
    if (!is_string($digits)) {
        return '';
    }

    if (strlen($digits) > 6) {
        $digits = substr($digits, 0, 6);
    }

    return $digits;
}

function admin_store_reset_code(mysqli $con, int $adminId, string $code): bool
{
    $code = admin_normalize_numeric_code($code);

    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $hash = commerza_password_hash($code);

    $stmt = $con->prepare(
        'UPDATE admin_users
            SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $hash, $adminId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function admin_verify_reset_code_status(mysqli $con, int $adminId, string $code): array
{
    $code = admin_normalize_numeric_code($code);

    if ($adminId <= 0) {
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
        ];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return [
            'ok' => false,
            'status' => 'invalid_code_format',
            'message' => 'Enter a valid 6-digit reset code.',
        ];
    }

    $stmt = $con->prepare(
        'SELECT
            reset_token,
            CASE
                WHEN reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW() THEN 1
                ELSE 0
            END AS reset_code_is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify reset code right now.',
        ];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
        ];
    }

    $hash = (string)($row['reset_token'] ?? '');
    $isActive = (int)($row['reset_code_is_active'] ?? 0) === 1;

    if ($hash === '') {
        return [
            'ok' => false,
            'status' => 'missing_code',
            'message' => 'No active reset code found. Request a new code.',
        ];
    }

    if (!$isActive) {
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Reset code expired. Request a new code.',
        ];
    }

    if (!commerza_password_verify($code, $hash)) {
        return [
            'ok' => false,
            'status' => 'invalid_code',
            'message' => 'Invalid reset code.',
        ];
    }

    return [
        'ok' => true,
        'status' => 'verified',
        'message' => 'Reset code verified.',
    ];
}

function admin_verify_reset_code(mysqli $con, array $admin, string $code): bool
{
    $adminId = (int)($admin['id'] ?? 0);
    $status = admin_verify_reset_code_status($con, $adminId, $code);

    return (bool)($status['ok'] ?? false);
}

function admin_clear_reset_code(mysqli $con, int $adminId): void
{
    $stmt = $con->prepare(
        'UPDATE admin_users
         SET reset_token = NULL, reset_token_expiry = NULL
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_public_url(string $path = ''): string
{
    if (function_exists('commerza_public_base_url')) {
        $base = rtrim((string)commerza_public_base_url(), '/');
    } else {
        $configured = trim((string)(getenv('COMMERZA_APP_URL') ?: getenv('COMMERZA_PUBLIC_URL') ?: getenv('APP_URL') ?: ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            $base = rtrim($configured, '/');
        } else {
            $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
            $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            $cfVisitor = strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));
            $isHttps = ($https !== '' && $https !== 'off')
                || ($forwardedProto !== '' && str_contains($forwardedProto, 'https'))
                || ($cfVisitor !== '' && str_contains($cfVisitor, '"https"'))
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
            $scheme = $isHttps ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost')));
            if ($host === '') {
                $host = 'localhost';
            }

            $base = $scheme . '://' . $host;
        }
    }

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function admin_email_setting(mysqli $con, string $key, string $fallback = ''): string
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

    if (!is_array($row)) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function admin_email_context(): array
{
    global $con;

    $siteName = 'Commerza';
    $supportEmail = trim((string)(getenv('COMMERZA_SUPPORT_EMAIL') ?: 'support@ahmershah.dev'));

    if ($con instanceof mysqli) {
        $siteName = admin_email_setting($con, 'site_name', $siteName);

        $siteEmail = strtolower(trim(admin_email_setting($con, 'site_email', '')));
        if ($siteEmail !== '' && filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
            $supportEmail = $siteEmail;
        }
    }

    if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $supportEmail = 'support@ahmershah.dev';
    }

    return [
        'site_name' => $siteName,
        'support_email' => $supportEmail,
    ];
}

function admin_mail_from_email(): string
{
    global $con;

    $defaultSender = commerza_mail_default_sender();
    $candidates = [
        getenv('COMMERZA_SUPPORT_EMAIL'),
        $defaultSender['email'] ?? '',
        getenv('COMMERZA_SMTP_USERNAME'),
    ];

    if ($con instanceof mysqli) {
        $candidates[] = admin_email_setting($con, 'site_email', '');
    }

    foreach ($candidates as $candidate) {
        $email = strtolower(trim((string)$candidate));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }

    return 'support@ahmershah.dev';
}

function admin_email_layout(string $title, string $intro, string $bodyHtml): string
{
    $context = admin_email_context();
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeSiteName = htmlspecialchars((string)($context['site_name'] ?? 'Commerza'), ENT_QUOTES, 'UTF-8');
    $safeLogo = htmlspecialchars(
        commerza_mail_logo_src(admin_public_url('/frontend/assets/images/logo/commerza-logo.webp')),
        ENT_QUOTES,
        'UTF-8'
    );
    $safeHome = htmlspecialchars(admin_public_url('/'), ENT_QUOTES, 'UTF-8');
    $safeSupportEmail = htmlspecialchars((string)($context['support_email'] ?? 'support@ahmershah.dev'), ENT_QUOTES, 'UTF-8');
    $socialLinks = '<a href="https://instagram.com/commerza" style="color:#ffb066;text-decoration:none;">Instagram</a> <span style="color:#666;">|</span> <a href="https://facebook.com/commerza" style="color:#ffb066;text-decoration:none;">Facebook</a> <span style="color:#666;">|</span> <a href="https://www.linkedin.com/in/syedahmershah" style="color:#ffb066;text-decoration:none;">LinkedIn</a> <span style="color:#666;">|</span> <a href="https://github.com/ahmershahdev" style="color:#ffb066;text-decoration:none;">GitHub</a>';

    return '<!DOCTYPE html>
<html>
    <body style="margin:0;padding:0;background:#080808;font-family:Segoe UI,Arial,sans-serif;color:#f5f5f5;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
                        <tr>
                            <td style="padding:18px 24px;background:linear-gradient(90deg,#161616,#101010);border-bottom:1px solid #2b2b2b;">
                                <a href="' . $safeHome . '" style="text-decoration:none;display:inline-flex;align-items:center;gap:10px;">
                                    <img src="' . $safeLogo . '" alt="Commerza" width="44" height="44" style="display:block;border-radius:8px;object-fit:cover;">
                                    <span style="color:#ff8a2b;font-size:18px;font-weight:800;letter-spacing:.6px;">' . $safeSiteName . ' Admin Security</span>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:22px 28px 8px 28px;">
                                <h1 style="margin:0;color:#ff9d45;font-size:22px;">' . $safeTitle . '</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 28px 0 28px;">
                                <p style="margin:0;color:#d7d7d7;line-height:1.65;">' . $safeIntro . '</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:16px 28px 26px 28px;line-height:1.65;color:#ececec;">' . $bodyHtml . '</td>
                        </tr>
                        <tr>
                            <td style="padding:12px 28px;background:#0f0f0f;border-top:1px solid #2b2b2b;">
                                <p style="margin:0;color:#999;font-size:12px;">If you did not initiate this request, secure your admin account immediately.</p>
                                <p style="margin:8px 0 0 0;color:#999;font-size:12px;">Support: <a href="mailto:' . $safeSupportEmail . '" style="color:#ffb066;text-decoration:none;">' . $safeSupportEmail . '</a></p>
                                <p style="margin:8px 0 0 0;color:#999;font-size:12px;">Connect: ' . $socialLinks . '</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>';
}

function admin_send_password_reset_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $context = admin_email_context();
    $siteName = (string)($context['site_name'] ?? 'Commerza');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = $siteName . ' Admin Password Reset Code';

    $body =
        '<p style="margin:0 0 10px 0;">Hello ' . $safeName . ',</p>' .
        '<p style="margin:0 0 14px 0;">Use this 6-digit code to reset your admin password. The code expires in <strong>30 minutes</strong>.</p>' .
        '<div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:4px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>' .
        '<p style="margin:16px 0 0 0;color:#cfcfcf;">For your security, do not share this code with anyone.</p>';

    $html = admin_email_layout('Admin Password Reset', 'A secure verification step is required to continue.', $body);

    $fromEmail = admin_mail_from_email();

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $html,
        $fromEmail,
        $siteName . ' Admin',
        $errorMessage
    );
}

function admin_generate_two_factor_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function admin_two_factor_user_agent_hash(): string
{
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return hash('sha256', $userAgent);
}

function admin_set_two_factor_pending_session(array $admin, string $nextTarget): void
{
    $_SESSION['admin_2fa_pending'] = [
        'admin_id' => (int)($admin['id'] ?? 0),
        'email' => (string)($admin['email'] ?? ''),
        'next' => admin_safe_redirect_target($nextTarget, 'admin-panel.php'),
        'created_at' => time(),
        'ip' => admin_get_client_ip(),
        'ua_hash' => admin_two_factor_user_agent_hash(),
    ];
}

function admin_get_two_factor_pending_session(): ?array
{
    $pending = $_SESSION['admin_2fa_pending'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $adminId = isset($pending['admin_id']) ? (int)$pending['admin_id'] : 0;
    $email = strtolower(trim((string)($pending['email'] ?? '')));
    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    $ip = (string)($pending['ip'] ?? '');
    $uaHash = (string)($pending['ua_hash'] ?? '');
    $next = admin_safe_redirect_target((string)($pending['next'] ?? ''), 'admin-panel.php');

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $createdAt <= 0) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    if ((time() - $createdAt) > 900) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    if ($ip !== admin_get_client_ip() || $uaHash !== admin_two_factor_user_agent_hash()) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    return [
        'admin_id' => $adminId,
        'email' => $email,
        'next' => $next,
        'created_at' => $createdAt,
    ];
}

function admin_clear_two_factor_pending_session(): void
{
    unset($_SESSION['admin_2fa_pending']);
}

function admin_send_two_factor_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $context = admin_email_context();
    $siteName = (string)($context['site_name'] ?? 'Commerza');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = $siteName . ' Admin Login Verification Code';

    $body =
        '<p style="margin:0 0 10px 0;">Hello ' . $safeName . ',</p>' .
        '<p style="margin:0 0 14px 0;">Use this 6-digit code to complete your admin login. The code expires in <strong>10 minutes</strong>.</p>' .
        '<div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:4px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>' .
        '<p style="margin:16px 0 0 0;color:#cfcfcf;">If this was not you, reset your admin password immediately.</p>';

    $html = admin_email_layout('Two-Factor Login Verification', 'Enter this code to finish secure admin authentication.', $body);

    $fromEmail = admin_mail_from_email();

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $html,
        $fromEmail,
        $siteName . ' Admin Security',
        $errorMessage
    );
}

function admin_clear_two_factor_challenge(mysqli $con, int $adminId): void
{
    $stmt = $con->prepare(
        'UPDATE admin_users
         SET two_factor_code_hash = NULL,
             two_factor_expires_at = NULL,
             two_factor_attempts = 0
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_issue_two_factor_challenge(mysqli $con, array $admin, string $nextTarget, ?string &$errorMessage = null): bool
{
    $adminId = (int)($admin['id'] ?? 0);
    $email = strtolower(trim((string)($admin['email'] ?? '')));
    $fullName = (string)($admin['full_name'] ?? 'Admin');

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid admin account state.';
        return false;
    }

    $code = admin_generate_two_factor_code();
    $hash = commerza_password_hash($code);
    if ($hash === '') {
        $errorMessage = 'Unable to generate verification code.';
        return false;
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET two_factor_code_hash = ?,
             two_factor_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
             two_factor_attempts = 0,
             two_factor_last_sent_at = NOW()
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );

    if (!$stmt) {
        $errorMessage = 'Unable to start two-factor authentication.';
        return false;
    }

    $stmt->bind_param('si', $hash, $adminId);
    $ok = $stmt->execute();
    $affectedRows = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok || $affectedRows !== 1) {
        $errorMessage = 'Unable to start two-factor authentication.';
        return false;
    }

    $mailError = null;
    $mailSent = admin_send_two_factor_code_email($email, $fullName, $code, $mailError);
    if (!$mailSent) {
        admin_clear_two_factor_challenge($con, $adminId);
        $errorMessage = $mailError ?: 'Unable to send verification email.';
        return false;
    }

    admin_set_two_factor_pending_session($admin, $nextTarget);
    return true;
}

function admin_verify_two_factor_code(mysqli $con, int $adminId, string $code): array
{
    $code = admin_normalize_numeric_code($code);

    if ($adminId <= 0) {
        return [
            'ok' => false,
            'status' => 'invalid_session',
            'message' => 'Two-factor session expired. Please login again.',
            'admin' => null,
        ];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return [
            'ok' => false,
            'status' => 'invalid_code_format',
            'message' => 'Enter the 6-digit verification code.',
            'admin' => null,
        ];
    }

    if (!$con->begin_transaction()) {
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $selectStmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            is_active,
            two_factor_code_hash,
            two_factor_expires_at,
            two_factor_attempts,
            CASE
                WHEN two_factor_expires_at IS NOT NULL AND two_factor_expires_at >= NOW() THEN 1
                ELSE 0
            END AS two_factor_is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$selectStmt) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $selectStmt->bind_param('i', $adminId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $admin = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();

    if (!$admin || (int)($admin['is_active'] ?? 0) !== 1) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'invalid_session',
            'message' => 'Two-factor session expired. Please login again.',
            'admin' => null,
        ];
    }

    $codeHash = (string)($admin['two_factor_code_hash'] ?? '');
    $attempts = (int)($admin['two_factor_attempts'] ?? 0);
    $isChallengeActive = (int)($admin['two_factor_is_active'] ?? 0) === 1;

    if ($codeHash === '' || !$isChallengeActive) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Verification code expired. Please login again.',
            'admin' => null,
        ];
    }

    if ($attempts >= 6) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid verification attempts. Please login again.',
            'admin' => null,
        ];
    }

    if (commerza_password_verify($code, $codeHash)) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        if (!$con->commit()) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        return [
            'ok' => true,
            'status' => 'verified',
            'message' => 'Verification successful.',
            'admin' => $admin,
        ];
    }

    $nextAttempts = $attempts + 1;
    if ($nextAttempts >= 6) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
    } else {
        $attemptStmt = $con->prepare(
            'UPDATE admin_users
             SET two_factor_attempts = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$attemptStmt) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        $attemptStmt->bind_param('ii', $nextAttempts, $adminId);
        $attemptStmt->execute();
        $attemptStmt->close();
    }

    if (!$con->commit()) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    if ($nextAttempts >= 6) {
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid verification attempts. Please login again.',
            'admin' => null,
        ];
    }

    return [
        'ok' => false,
        'status' => 'invalid_code',
        'message' => 'Invalid verification code.',
        'admin' => null,
    ];
}
