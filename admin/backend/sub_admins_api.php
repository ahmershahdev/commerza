<?php
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';

/** @var mysqli|null $con */
$con = (isset($con) && $con instanceof mysqli)
    ? $con
    : (($GLOBALS['con'] ?? null) instanceof mysqli ? $GLOBALS['con'] : null);

function sub_admins_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function sub_admins_api_request_body(): array
{
    static $body = null;

    if (is_array($body)) {
        return $body;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    $body = is_array($decoded) ? $decoded : [];

    return $body;
}

function sub_admins_api_phone(string $value): string
{
    $normalized = preg_replace('/\s+/', '', trim($value));
    if (!is_string($normalized) || $normalized === '') {
        return '';
    }

    if (preg_match('/^\d{11,15}$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function sub_admins_api_bool_value($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function sub_admins_api_role_descriptions(): array
{
    return [
        'operations_manager' => 'Handles products, orders, customers, coupons, and analytics operations.',
        'customer_support' => 'Focused on customer records, order handling, and review moderation.',
        'marketing_website' => 'Manages website content, media, campaigns, and growth activities.',
        'read_only' => 'Can only view dashboard and analytics. No management actions allowed.',
        'view_only' => 'Can only view dashboard snapshots.',
        'custom' => 'Custom profile with hand-picked permissions and hidden tabs.',
    ];
}

function sub_admins_api_profile_payload(): array
{
    $labels = admin_role_labels();
    $presets = admin_role_permission_presets();
    $descriptions = sub_admins_api_role_descriptions();
    $roles = ['operations_manager', 'customer_support', 'marketing_website', 'read_only', 'view_only', 'custom'];

    $payload = [];
    foreach ($roles as $role) {
        $payload[] = [
            'key' => $role,
            'label' => (string)($labels[$role] ?? $role),
            'description' => (string)($descriptions[$role] ?? ''),
            'defaultPermissions' => array_values((array)($presets[$role] ?? [])),
        ];
    }

    return $payload;
}

function sub_admins_api_decode_list($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function sub_admins_api_payload_permissions(array $body): array
{
    $permissions = sub_admins_api_decode_list($body['permissions'] ?? []);
    return admin_sanitize_permissions($permissions);
}

function sub_admins_api_payload_hidden_tabs(array $body): array
{
    $hiddenTabs = sub_admins_api_decode_list($body['hidden_tabs'] ?? ($body['hiddenTabs'] ?? []));
    return admin_sanitize_hidden_tabs($hiddenTabs);
}

function sub_admins_api_row_payload(array $row): array
{
    $role = admin_normalize_role((string)($row['role'] ?? 'custom'));
    $isActive = (int)($row['is_active'] ?? 0) === 1;
    $suspendedUntil = trim((string)($row['suspended_until'] ?? ''));
    $suspendedReason = trim((string)($row['suspended_reason'] ?? ''));

    $status = 'active';
    if (!$isActive && $suspendedUntil === '') {
        $status = 'suspended_until_changed';
    } elseif ($suspendedUntil !== '') {
        $untilTs = strtotime($suspendedUntil);
        if ($untilTs !== false && $untilTs > time()) {
            $status = 'suspended_temporary';
        }
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'fullName' => (string)($row['full_name'] ?? ''),
        'email' => strtolower(trim((string)($row['email'] ?? ''))),
        'phone' => (string)($row['phone'] ?? ''),
        'role' => $role,
        'roleLabel' => admin_role_label($role),
        'permissions' => admin_sanitize_permissions(
            admin_decode_json_string_list((string)($row['permissions_json'] ?? ''))
        ),
        'hiddenTabs' => admin_sanitize_hidden_tabs(
            admin_decode_json_string_list((string)($row['hidden_tabs_json'] ?? ''))
        ),
        'emailVerified' => trim((string)($row['email_verified_at'] ?? '')) !== '',
        'emailVerifiedAt' => (string)($row['email_verified_at'] ?? ''),
        'status' => $status,
        'isActive' => $isActive,
        'suspendedUntil' => $suspendedUntil,
        'suspendedReason' => $suspendedReason,
        'invitedByAdminId' => (int)($row['invited_by_admin_id'] ?? 0),
        'lastLoginAt' => (string)($row['last_login_at'] ?? ''),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}

function sub_admins_api_fetch_rows(mysqli $con): array
{
    $rows = [];
    $result = $con->query(
        'SELECT
            id,
            full_name,
            email,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            email_verified_at,
            suspended_until,
            suspended_reason,
            invited_by_admin_id,
            last_login_at,
            created_at,
            is_active
         FROM admin_users
         WHERE deleted_at IS NULL
           AND role <> "admin"
         ORDER BY created_at DESC, id DESC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = sub_admins_api_row_payload($row);
    }

    return $rows;
}

function sub_admins_api_response_payload(mysqli $con): array
{
    return [
        'subAdmins' => sub_admins_api_fetch_rows($con),
        'roles' => sub_admins_api_profile_payload(),
        'permissions' => admin_permissions_payload(),
        'tabs' => admin_tabs_payload(),
    ];
}

function sub_admins_api_csrf_token(array $body): string
{
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '') {
        $token = (string)($body['csrf_token'] ?? ($_POST['csrf_token'] ?? ''));
    }

    return trim($token);
}

if (!($con instanceof mysqli)) {
    sub_admins_api_json(['ok' => false, 'message' => 'Service unavailable.'], 500);
}

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'sub_admins.manage');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = sub_admins_api_request_body();

$action = 'list';
if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'list')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($body['action'] ?? ($_POST['action'] ?? 'list'))));
}

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_sub_admins_api', $action),
    120,
    60,
    120,
    300
);

if ($action === 'list') {
    sub_admins_api_json([
        'ok' => true,
        'payload' => sub_admins_api_response_payload($con),
    ]);
}

if ($method !== 'POST') {
    sub_admins_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$csrfToken = sub_admins_api_csrf_token($body);
if (!admin_validate_csrf_token($csrfToken)) {
    sub_admins_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
}

if ($action === 'create') {
    $fullName = trim((string)($body['full_name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phone = sub_admins_api_phone((string)($body['phone'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $role = admin_normalize_role((string)($body['role'] ?? 'custom'));
    $permissions = sub_admins_api_payload_permissions($body);
    $hiddenTabs = sub_admins_api_payload_hidden_tabs($body);

    if ($role === 'admin') {
        $role = 'custom';
    }

    if (strlen($fullName) < 3 || strlen($fullName) > 100) {
        sub_admins_api_json(['ok' => false, 'message' => 'Full name must be 3 to 100 characters.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        sub_admins_api_json(['ok' => false, 'message' => 'Provide a valid email address.'], 422);
    }

    if ((string)($body['phone'] ?? '') !== '' && $phone === '') {
        sub_admins_api_json(['ok' => false, 'message' => 'Phone must be 11 to 15 digits.'], 422);
    }

    $passwordPolicyError = null;
    if (!commerza_password_validate($password, $passwordPolicyError)) {
        sub_admins_api_json([
            'ok' => false,
            'message' => $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description(),
        ], 422);
    }

    if ($role === 'custom' && empty($permissions)) {
        sub_admins_api_json([
            'ok' => false,
            'message' => 'Custom role requires at least one permission.',
        ], 422);
    }

    $existsStmt = $con->prepare(
        'SELECT id
         FROM admin_users
         WHERE email = ?
           AND deleted_at IS NULL
         LIMIT 1'
    );

    if (!$existsStmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to validate admin email.'], 500);
    }

    $existsStmt->bind_param('s', $email);
    $existsStmt->execute();
    $existsStmt->store_result();
    $exists = $existsStmt->num_rows > 0;
    $existsStmt->close();

    if ($exists) {
        sub_admins_api_json(['ok' => false, 'message' => 'An active admin with this email already exists.'], 409);
    }

    $passwordHash = commerza_password_hash($password);
    if ($passwordHash === '') {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to create account password hash.'], 500);
    }

    $permissionsJson = json_encode($permissions, JSON_UNESCAPED_SLASHES);
    $hiddenTabsJson = json_encode($hiddenTabs, JSON_UNESCAPED_SLASHES);
    if (!is_string($permissionsJson)) {
        $permissionsJson = '[]';
    }
    if (!is_string($hiddenTabsJson)) {
        $hiddenTabsJson = '[]';
    }

    $invitedBy = (int)($admin['id'] ?? 0);
    $insertStmt = $con->prepare(
        'INSERT INTO admin_users (
            full_name,
            email,
            password_hash,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            invited_by_admin_id,
            email_verified_at,
            is_active
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 1)'
    );

    if (!$insertStmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to create sub-admin account.'], 500);
    }

    $phoneValue = $phone !== '' ? $phone : null;
    $insertStmt->bind_param(
        'sssssssi',
        $fullName,
        $email,
        $passwordHash,
        $role,
        $permissionsJson,
        $hiddenTabsJson,
        $phoneValue,
        $invitedBy
    );
    $created = $insertStmt->execute();
    $newAdminId = (int)$insertStmt->insert_id;
    $insertStmt->close();

    if (!$created || $newAdminId <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to create sub-admin account.'], 500);
    }

    $createdAdmin = admin_get_by_id($con, $newAdminId);
    $verificationMessage = 'Verification code sent to the sub-admin email.';

    if (!$createdAdmin) {
        sub_admins_api_json(['ok' => false, 'message' => 'Sub-admin was created but could not be loaded.'], 500);
    }

    $issueError = null;
    if (!admin_issue_email_verification_challenge($con, $createdAdmin, $issueError)) {
        $verificationMessage = 'Sub-admin created, but verification email failed: ' . ($issueError ?: 'mail delivery unavailable.');
    }

    admin_api_log_security_event($con, $admin, 'sub_admin.created', 'info', [
        'sub_admin_id' => $newAdminId,
        'role' => $role,
        'email' => $email,
        'permissions_count' => count($permissions),
        'hidden_tabs_count' => count($hiddenTabs),
    ]);

    sub_admins_api_json([
        'ok' => true,
        'message' => 'Sub-admin account created. ' . $verificationMessage,
        'payload' => sub_admins_api_response_payload($con),
    ]);
}

if ($action === 'update') {
    $targetId = (int)($body['admin_id'] ?? 0);
    $fullName = trim((string)($body['full_name'] ?? ''));
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phoneRaw = (string)($body['phone'] ?? '');
    $phone = sub_admins_api_phone($phoneRaw);
    $password = (string)($body['password'] ?? '');
    $role = admin_normalize_role((string)($body['role'] ?? 'custom'));
    $permissions = sub_admins_api_payload_permissions($body);
    $hiddenTabs = sub_admins_api_payload_hidden_tabs($body);

    if ($targetId <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Invalid sub-admin id.'], 422);
    }

    if ($targetId === (int)($admin['id'] ?? 0)) {
        sub_admins_api_json(['ok' => false, 'message' => 'Use Security section to update your own account settings.'], 422);
    }

    if ($role === 'admin') {
        $role = 'custom';
    }

    if (strlen($fullName) < 3 || strlen($fullName) > 100) {
        sub_admins_api_json(['ok' => false, 'message' => 'Full name must be 3 to 100 characters.'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        sub_admins_api_json(['ok' => false, 'message' => 'Provide a valid email address.'], 422);
    }

    if ($phoneRaw !== '' && $phone === '') {
        sub_admins_api_json(['ok' => false, 'message' => 'Phone must be 11 to 15 digits.'], 422);
    }

    if ($role === 'custom' && empty($permissions)) {
        sub_admins_api_json(['ok' => false, 'message' => 'Custom role requires at least one permission.'], 422);
    }

    $targetStmt = $con->prepare(
        'SELECT id, email, role, email_verified_at
         FROM admin_users
         WHERE id = ?
           AND deleted_at IS NULL
           AND role <> "admin"
         LIMIT 1'
    );

    if (!$targetStmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to load sub-admin account.'], 500);
    }

    $targetStmt->bind_param('i', $targetId);
    $targetStmt->execute();
    $targetResult = $targetStmt->get_result();
    $targetRow = $targetResult ? $targetResult->fetch_assoc() : null;
    $targetStmt->close();

    if (!is_array($targetRow)) {
        sub_admins_api_json(['ok' => false, 'message' => 'Sub-admin account not found.'], 404);
    }

    $dupStmt = $con->prepare(
        'SELECT id
         FROM admin_users
         WHERE email = ?
           AND id <> ?
           AND deleted_at IS NULL
         LIMIT 1'
    );

    if (!$dupStmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to validate admin email.'], 500);
    }

    $dupStmt->bind_param('si', $email, $targetId);
    $dupStmt->execute();
    $dupStmt->store_result();
    $duplicate = $dupStmt->num_rows > 0;
    $dupStmt->close();

    if ($duplicate) {
        sub_admins_api_json(['ok' => false, 'message' => 'Another admin account already uses this email.'], 409);
    }

    $passwordHash = '';
    if (trim($password) !== '') {
        $passwordPolicyError = null;
        if (!commerza_password_validate($password, $passwordPolicyError)) {
            sub_admins_api_json([
                'ok' => false,
                'message' => $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description(),
            ], 422);
        }

        $passwordHash = commerza_password_hash($password);
        if ($passwordHash === '') {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update password hash.'], 500);
        }
    }

    $permissionsJson = json_encode($permissions, JSON_UNESCAPED_SLASHES);
    $hiddenTabsJson = json_encode($hiddenTabs, JSON_UNESCAPED_SLASHES);
    if (!is_string($permissionsJson)) {
        $permissionsJson = '[]';
    }
    if (!is_string($hiddenTabsJson)) {
        $hiddenTabsJson = '[]';
    }

    $emailChanged = strcasecmp((string)$targetRow['email'], $email) !== 0;
    $phoneValue = $phone !== '' ? $phone : null;

    if ($passwordHash !== '') {
        $stmt = $con->prepare(
            'UPDATE admin_users
             SET full_name = ?,
                 email = ?,
                 phone = ?,
                 role = ?,
                 permissions_json = ?,
                 hidden_tabs_json = ?,
                 password_hash = ?,
                 email_verified_at = CASE WHEN ? = 1 THEN NULL ELSE email_verified_at END,
                 verification_code_hash = CASE WHEN ? = 1 THEN NULL ELSE verification_code_hash END,
                 verification_expires_at = CASE WHEN ? = 1 THEN NULL ELSE verification_expires_at END,
                 verification_attempts = CASE WHEN ? = 1 THEN 0 ELSE verification_attempts END
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$stmt) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update sub-admin account.'], 500);
        }

        $changedFlag = $emailChanged ? 1 : 0;
        $stmt->bind_param(
            'sssssssiiiii',
            $fullName,
            $email,
            $phoneValue,
            $role,
            $permissionsJson,
            $hiddenTabsJson,
            $passwordHash,
            $changedFlag,
            $changedFlag,
            $changedFlag,
            $changedFlag,
            $targetId
        );
    } else {
        $stmt = $con->prepare(
            'UPDATE admin_users
             SET full_name = ?,
                 email = ?,
                 phone = ?,
                 role = ?,
                 permissions_json = ?,
                 hidden_tabs_json = ?,
                 email_verified_at = CASE WHEN ? = 1 THEN NULL ELSE email_verified_at END,
                 verification_code_hash = CASE WHEN ? = 1 THEN NULL ELSE verification_code_hash END,
                 verification_expires_at = CASE WHEN ? = 1 THEN NULL ELSE verification_expires_at END,
                 verification_attempts = CASE WHEN ? = 1 THEN 0 ELSE verification_attempts END
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$stmt) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update sub-admin account.'], 500);
        }

        $changedFlag = $emailChanged ? 1 : 0;
        $stmt->bind_param(
            'ssssssiiiii',
            $fullName,
            $email,
            $phoneValue,
            $role,
            $permissionsJson,
            $hiddenTabsJson,
            $changedFlag,
            $changedFlag,
            $changedFlag,
            $changedFlag,
            $targetId
        );
    }

    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to update sub-admin account.'], 500);
    }

    $resendNotice = '';
    if ($emailChanged) {
        $fresh = admin_get_by_id($con, $targetId);
        $issueError = null;
        if ($fresh && !admin_issue_email_verification_challenge($con, $fresh, $issueError)) {
            $resendNotice = ' Email changed, but verification email failed: ' . ($issueError ?: 'mail delivery unavailable.');
        } else {
            $resendNotice = ' Email changed and a new verification code was sent.';
        }
    }

    admin_api_log_security_event($con, $admin, 'sub_admin.updated', 'info', [
        'sub_admin_id' => $targetId,
        'role' => $role,
        'email_changed' => $emailChanged,
        'password_updated' => $passwordHash !== '',
        'permissions_count' => count($permissions),
        'hidden_tabs_count' => count($hiddenTabs),
    ]);

    sub_admins_api_json([
        'ok' => true,
        'message' => 'Sub-admin access updated.' . $resendNotice,
        'payload' => sub_admins_api_response_payload($con),
    ]);
}

if ($action === 'set-suspension') {
    $targetId = (int)($body['admin_id'] ?? 0);
    $mode = strtolower(trim((string)($body['mode'] ?? 'active')));
    $reason = trim((string)($body['reason'] ?? ''));
    $durationMinutes = (int)($body['duration_minutes'] ?? 0);

    if ($targetId <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Invalid sub-admin id.'], 422);
    }

    if ($targetId === (int)($admin['id'] ?? 0)) {
        sub_admins_api_json(['ok' => false, 'message' => 'You cannot suspend your own account from this tab.'], 422);
    }

    $targetStmt = $con->prepare(
        'SELECT id
         FROM admin_users
         WHERE id = ?
           AND deleted_at IS NULL
           AND role <> "admin"
         LIMIT 1'
    );

    if (!$targetStmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to load sub-admin account.'], 500);
    }

    $targetStmt->bind_param('i', $targetId);
    $targetStmt->execute();
    $targetStmt->store_result();
    $exists = $targetStmt->num_rows > 0;
    $targetStmt->close();

    if (!$exists) {
        sub_admins_api_json(['ok' => false, 'message' => 'Sub-admin account not found.'], 404);
    }

    if (strlen($reason) > 255) {
        $reason = substr($reason, 0, 255);
    }

    if ($mode === 'active') {
        $stmt = $con->prepare(
            'UPDATE admin_users
             SET is_active = 1,
                 suspended_until = NULL,
                 suspended_reason = NULL
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$stmt) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        $stmt->bind_param('i', $targetId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        admin_api_log_security_event($con, $admin, 'sub_admin.reactivated', 'info', [
            'sub_admin_id' => $targetId,
        ]);

        sub_admins_api_json([
            'ok' => true,
            'message' => 'Sub-admin access restored.',
            'payload' => sub_admins_api_response_payload($con),
        ]);
    }

    if ($mode === 'temporary') {
        if ($durationMinutes < 15 || $durationMinutes > 525600) {
            sub_admins_api_json([
                'ok' => false,
                'message' => 'Temporary suspension duration must be between 15 and 525600 minutes.',
            ], 422);
        }

        $suspendedUntil = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));

        $stmt = $con->prepare(
            'UPDATE admin_users
             SET is_active = 1,
                 suspended_until = ?,
                 suspended_reason = ?
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$stmt) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        $reasonValue = $reason !== '' ? $reason : null;
        $stmt->bind_param('ssi', $suspendedUntil, $reasonValue, $targetId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        admin_api_log_security_event($con, $admin, 'sub_admin.suspended_temporary', 'warning', [
            'sub_admin_id' => $targetId,
            'duration_minutes' => $durationMinutes,
            'suspended_until' => $suspendedUntil,
            'reason' => $reason,
        ]);

        sub_admins_api_json([
            'ok' => true,
            'message' => 'Sub-admin suspended temporarily.',
            'payload' => sub_admins_api_response_payload($con),
        ]);
    }

    if ($mode === 'until_changed') {
        $stmt = $con->prepare(
            'UPDATE admin_users
             SET is_active = 0,
                 suspended_until = NULL,
                 suspended_reason = ?
             WHERE id = ?
               AND deleted_at IS NULL
             LIMIT 1'
        );

        if (!$stmt) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        $reasonValue = $reason !== '' ? $reason : null;
        $stmt->bind_param('si', $reasonValue, $targetId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            sub_admins_api_json(['ok' => false, 'message' => 'Unable to update suspension state.'], 500);
        }

        admin_api_log_security_event($con, $admin, 'sub_admin.suspended_until_changed', 'warning', [
            'sub_admin_id' => $targetId,
            'reason' => $reason,
        ]);

        sub_admins_api_json([
            'ok' => true,
            'message' => 'Sub-admin suspended until reactivated by admin.',
            'payload' => sub_admins_api_response_payload($con),
        ]);
    }

    sub_admins_api_json(['ok' => false, 'message' => 'Invalid suspension mode.'], 422);
}

if ($action === 'resend-verification') {
    $targetId = (int)($body['admin_id'] ?? 0);
    if ($targetId <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Invalid sub-admin id.'], 422);
    }

    $target = admin_get_by_id($con, $targetId);
    if (!$target || admin_is_deleted($target)) {
        sub_admins_api_json(['ok' => false, 'message' => 'Sub-admin account not found.'], 404);
    }

    if (admin_is_email_verified($target)) {
        sub_admins_api_json(['ok' => false, 'message' => 'This sub-admin email is already verified.'], 422);
    }

    $issueError = null;
    if (!admin_issue_email_verification_challenge($con, $target, $issueError)) {
        sub_admins_api_json([
            'ok' => false,
            'message' => $issueError ?: 'Unable to resend verification code.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'sub_admin.verification_resent', 'info', [
        'sub_admin_id' => $targetId,
    ]);

    sub_admins_api_json([
        'ok' => true,
        'message' => 'Verification code sent to sub-admin email.',
        'payload' => sub_admins_api_response_payload($con),
    ]);
}

if ($action === 'delete') {
    $targetId = (int)($body['admin_id'] ?? 0);
    if ($targetId <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Invalid sub-admin id.'], 422);
    }

    if ($targetId === (int)($admin['id'] ?? 0)) {
        sub_admins_api_json(['ok' => false, 'message' => 'You cannot delete your own account from this tab.'], 422);
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET deleted_at = NOW(),
             is_active = 0,
             suspended_until = NULL,
             suspended_reason = NULL,
             reset_token = NULL,
             reset_token_expiry = NULL,
             verification_code_hash = NULL,
             verification_expires_at = NULL,
             verification_attempts = 0,
             two_factor_code_hash = NULL,
             two_factor_expires_at = NULL,
             two_factor_attempts = 0
         WHERE id = ?
           AND deleted_at IS NULL
           AND role <> "admin"
         LIMIT 1'
    );

    if (!$stmt) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to delete sub-admin account.'], 500);
    }

    $stmt->bind_param('i', $targetId);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        sub_admins_api_json(['ok' => false, 'message' => 'Unable to delete sub-admin account.'], 500);
    }

    if ($affected <= 0) {
        sub_admins_api_json(['ok' => false, 'message' => 'Sub-admin account not found.'], 404);
    }

    admin_api_log_security_event($con, $admin, 'sub_admin.deleted', 'warning', [
        'sub_admin_id' => $targetId,
    ]);

    sub_admins_api_json([
        'ok' => true,
        'message' => 'Sub-admin account deleted.',
        'payload' => sub_admins_api_response_payload($con),
    ]);
}

sub_admins_api_json(['ok' => false, 'message' => 'Invalid action.'], 400);
