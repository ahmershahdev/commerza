<?php

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

    $defaultEmail = 'commerza.ahmer@gmail.com';
    $defaultPassword = 'Commerza@2026';

    $countResult = $con->query('SELECT COUNT(*) AS total FROM admin_users');
    $total = 0;
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $total = $row ? (int)$row['total'] : 0;
    }

    if ($total > 0) {
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

function admin_store_reset_code(mysqli $con, int $adminId, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $hash = commerza_password_hash($code);

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
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

function admin_verify_reset_code(array $admin, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $hash = (string)($admin['reset_token'] ?? '');
    $expiry = (string)($admin['reset_token_expiry'] ?? '');

    if ($hash === '' || $expiry === '') {
        return false;
    }

    $expiryTs = strtotime($expiry);
    if ($expiryTs === false || $expiryTs < time()) {
        return false;
    }

    return commerza_password_verify($code, $hash);
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

function admin_send_password_reset_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = 'Commerza Admin Password Reset Code';

    $body = '<!DOCTYPE html>
<html>
  <body style="margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
            <tr>
              <td style="padding:24px 28px;">
                <h1 style="margin:0 0 14px 0;color:#ff6600;font-size:22px;">Admin Password Reset</h1>
                <p style="margin:0 0 10px 0;line-height:1.6;color:#d7d7d7;">Hello ' . $safeName . ',</p>
                <p style="margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;">Use the following 6-digit code to reset your admin password. This code expires in 30 minutes.</p>
                <div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:3px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>
                <p style="margin:18px 0 0 0;line-height:1.6;color:#8f8f8f;font-size:13px;">If you did not request this, ignore this email.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $body,
        'no-reply@commerza.ahmershah.dev',
        'Commerza Admin',
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
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = 'Commerza Admin Login Verification Code';

    $body = '<!DOCTYPE html>
<html>
  <body style="margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
            <tr>
              <td style="padding:24px 28px;">
                <h1 style="margin:0 0 14px 0;color:#ff6600;font-size:22px;">Two-Factor Login Verification</h1>
                <p style="margin:0 0 10px 0;line-height:1.6;color:#d7d7d7;">Hello ' . $safeName . ',</p>
                <p style="margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;">Use this 6-digit code to complete your admin login. It expires in 10 minutes.</p>
                <div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:3px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>
                <p style="margin:18px 0 0 0;line-height:1.6;color:#8f8f8f;font-size:13px;">If you did not attempt to login, change your password immediately.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $body,
        'no-reply@commerza.ahmershah.dev',
        'Commerza Admin Security',
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
        'SELECT id, full_name, email, is_active, two_factor_code_hash, two_factor_expires_at, two_factor_attempts
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
    $expiresAt = (string)($admin['two_factor_expires_at'] ?? '');
    $attempts = (int)($admin['two_factor_attempts'] ?? 0);
    $expiryTs = strtotime($expiresAt);

    if ($codeHash === '' || $expiresAt === '' || $expiryTs === false || $expiryTs < time()) {
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
