<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../../../backend/security/security_events.php';

function security_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function security_api_bind_params(mysqli_stmt $stmt, string $types, array &$values): bool
{
    if ($types === '') {
        return true;
    }

    $refs = [];
    $refs[] = $types;
    foreach ($values as $index => &$value) {
        $refs[] = &$values[$index];
    }

    return call_user_func_array([$stmt, 'bind_param'], $refs);
}

function security_api_decode_details($detailsRaw): ?array
{
    if (is_array($detailsRaw)) {
        return $detailsRaw;
    }

    if ($detailsRaw === null) {
        return null;
    }

    $json = trim((string)$detailsRaw);
    if ($json === '' || strtolower($json) === 'null') {
        return null;
    }

    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return ['raw' => $json];
}

function security_api_details_preview($detailsRaw): string
{
    $decoded = security_api_decode_details($detailsRaw);
    if (!$decoded) {
        return '-';
    }

    $json = json_encode($decoded, JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || trim($json) === '') {
        return '-';
    }

    if (strlen($json) <= 160) {
        return $json;
    }

    return substr($json, 0, 157) . '...';
}

function security_api_csrf_from_request(array $body): string
{
    $headerToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    return trim((string)($body['csrf_token'] ?? ''));
}

function security_api_validate_csrf(array $body): bool
{
    $token = security_api_csrf_from_request($body);
    $sessionToken = trim((string)($_SESSION['admin_csrf_token'] ?? ''));

    return $token !== ''
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    security_api_json([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ], 500);
}
/** @var mysqli $con */

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'security.manage');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$rawBody = file_get_contents('php://input');
$decodedBody = json_decode($rawBody ?: '{}', true);
$body = is_array($decodedBody) ? $decodedBody : [];
if (!empty($_POST) && is_array($_POST)) {
    $body = array_merge($_POST, $body);
}

$source = $method === 'GET' ? $_GET : $body;
$action = strtolower(trim((string)($source['action'] ?? '')));

if ($action === '') {
    security_api_json([
        'ok' => false,
        'message' => 'Action is required.',
    ], 400);
}

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_security_api', $action),
    80,
    60,
    120,
    300
);

$stmt = $con->prepare(
    'SELECT id, full_name, email, password_hash, is_active
     FROM admin_users
     WHERE id = ?
     LIMIT 1'
);

if (!$stmt) {
    security_api_json([
        'ok' => false,
        'message' => 'Server error.',
    ], 500);
}

$adminId = (int)$admin['id'];
$stmt->bind_param('i', $adminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$currentAdmin || (int)$currentAdmin['is_active'] !== 1) {
    admin_logout_user($con);
    security_api_json([
        'ok' => false,
        'message' => 'Unauthorized.',
    ], 401);
}

if ($action === 'list-events') {
    if (!in_array($method, ['GET', 'POST'], true)) {
        security_api_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    if ($method === 'POST') {
        if (!security_api_validate_csrf($body)) {
            security_api_json([
                'ok' => false,
                'message' => 'Invalid CSRF token.',
            ], 403);
        }
    }

    commerza_security_ensure_table($con);

    $filters = $method === 'GET' ? $_GET : $body;
    $page = max(1, (int)($filters['page'] ?? 1));
    $perPage = max(5, min(100, (int)($filters['per_page'] ?? 25)));
    $offset = ($page - 1) * $perPage;

    $eventType = trim((string)($filters['event_type'] ?? ''));
    if ($eventType !== '' && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $eventType) !== 1) {
        $eventType = '';
    }

    $severity = strtolower(trim((string)($filters['severity'] ?? '')));
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = '';
    }

    $actorType = trim((string)($filters['actor_type'] ?? ''));
    if ($actorType !== '' && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $actorType) !== 1) {
        $actorType = '';
    }

    $search = trim((string)($filters['search'] ?? ''));
    if (strlen($search) > 120) {
        $search = substr($search, 0, 120);
    }

    $fromFilter = trim((string)($filters['from'] ?? ''));
    $toFilter = trim((string)($filters['to'] ?? ''));

    $fromDate = '';
    $toDate = '';

    if ($fromFilter !== '') {
        $fromTs = strtotime($fromFilter);
        if ($fromTs !== false) {
            $fromDate = date('Y-m-d 00:00:00', $fromTs);
        }
    }

    if ($toFilter !== '') {
        $toTs = strtotime($toFilter);
        if ($toTs !== false) {
            $toDate = date('Y-m-d 23:59:59', $toTs);
        }
    }

    $where = [];
    $types = '';
    $params = [];

    if ($eventType !== '') {
        $where[] = 'event_type = ?';
        $types .= 's';
        $params[] = $eventType;
    }

    if ($severity !== '') {
        $where[] = 'severity = ?';
        $types .= 's';
        $params[] = $severity;
    }

    if ($actorType !== '') {
        $where[] = 'actor_type = ?';
        $types .= 's';
        $params[] = $actorType;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(event_type LIKE ? OR actor_identifier LIKE ? OR ip_address LIKE ? OR CAST(details_json AS CHAR) LIKE ?)';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($fromDate !== '') {
        $where[] = 'created_at >= ?';
        $types .= 's';
        $params[] = $fromDate;
    }

    if ($toDate !== '') {
        $where[] = 'created_at <= ?';
        $types .= 's';
        $params[] = $toDate;
    }

    $whereSql = empty($where) ? '1=1' : implode(' AND ', $where);

    $countSql = 'SELECT COUNT(*) AS total FROM security_events WHERE ' . $whereSql;
    $countStmt = $con->prepare($countSql);
    if (!$countStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to load security events.',
        ], 500);
    }

    $countParams = $params;
    if (!security_api_bind_params($countStmt, $types, $countParams)) {
        $countStmt->close();
        security_api_json([
            'ok' => false,
            'message' => 'Unable to apply security event filters.',
        ], 500);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $countStmt->close();

    $total = max(0, (int)($countRow['total'] ?? 0));
    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $listSql =
        'SELECT id, event_type, severity, actor_type, actor_identifier, user_id, admin_id, ip_address, details_json, created_at
         FROM security_events
         WHERE ' . $whereSql . '
         ORDER BY created_at DESC, id DESC
         LIMIT ? OFFSET ?';

    $listStmt = $con->prepare($listSql);
    if (!$listStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to load security events.',
        ], 500);
    }

    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;

    if (!security_api_bind_params($listStmt, $listTypes, $listParams)) {
        $listStmt->close();
        security_api_json([
            'ok' => false,
            'message' => 'Unable to apply security event pagination.',
        ], 500);
    }

    $listStmt->execute();
    $eventsResult = $listStmt->get_result();

    $events = [];
    while ($eventsResult && ($row = $eventsResult->fetch_assoc())) {
        $decodedDetails = security_api_decode_details($row['details_json'] ?? null);

        $events[] = [
            'id' => (int)($row['id'] ?? 0),
            'event_type' => (string)($row['event_type'] ?? ''),
            'severity' => (string)($row['severity'] ?? 'info'),
            'actor_type' => (string)($row['actor_type'] ?? ''),
            'actor_identifier' => (string)($row['actor_identifier'] ?? ''),
            'user_id' => (int)($row['user_id'] ?? 0),
            'admin_id' => (int)($row['admin_id'] ?? 0),
            'ip_address' => (string)($row['ip_address'] ?? ''),
            'details' => $decodedDetails,
            'details_preview' => security_api_details_preview($row['details_json'] ?? null),
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    $listStmt->close();

    security_api_json([
        'ok' => true,
        'payload' => [
            'events' => $events,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ],
    ]);
}

if ($method !== 'POST') {
    security_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (!security_api_validate_csrf($body)) {
    security_api_json([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
    ], 403);
}

if ($action === 'update-email') {
    $currentPassword = (string)($body['currentPassword'] ?? '');
    $resetKey = trim((string)($body['resetKey'] ?? ''));
    $newEmail = strtolower(trim((string)($body['newEmail'] ?? '')));
    $confirmEmail = strtolower(trim((string)($body['confirmEmail'] ?? '')));

    if ($currentPassword === '' || $resetKey === '') {
        security_api_json([
            'ok' => false,
            'message' => 'Password and reset key are required.',
        ], 422);
    }

    if (!commerza_password_verify($currentPassword, (string)$currentAdmin['password_hash'])) {
        security_api_json([
            'ok' => false,
            'message' => 'Invalid current password.',
        ], 422);
    }

    if (!hash_equals(admin_get_reset_key($con), $resetKey)) {
        security_api_json([
            'ok' => false,
            'message' => 'Invalid reset key.',
        ], 422);
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 150) {
        security_api_json([
            'ok' => false,
            'message' => 'Enter a valid email address.',
        ], 422);
    }

    if ($newEmail !== $confirmEmail) {
        security_api_json([
            'ok' => false,
            'message' => 'Email confirmation does not match.',
        ], 422);
    }

    $duplicateStmt = $con->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1');

    if (!$duplicateStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Server error.',
        ], 500);
    }

    $duplicateStmt->bind_param('si', $newEmail, $adminId);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
    $duplicateStmt->close();

    if ($duplicateRow) {
        security_api_json([
            'ok' => false,
            'message' => 'Email is already used by another admin account.',
        ], 422);
    }

    $updateStmt = $con->prepare('UPDATE admin_users SET email = ? WHERE id = ? LIMIT 1');

    if (!$updateStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update email.',
        ], 500);
    }

    $updateStmt->bind_param('si', $newEmail, $adminId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update email.',
        ], 500);
    }

    if (isset($_SESSION['admin_email_verify_pending']) && is_array($_SESSION['admin_email_verify_pending'])) {
        $_SESSION['admin_email_verify_pending']['email'] = $newEmail;
    }

    admin_api_log_security_event($con, $admin, 'admin.email_updated', 'warning', [
        'previous_email' => (string)$currentAdmin['email'],
        'new_email' => $newEmail,
    ]);

    security_api_json([
        'ok' => true,
        'message' => 'Admin email updated successfully.',
        'email' => $newEmail,
    ]);
}

if ($action === 'update-password') {
    $currentEmail = strtolower(trim((string)($body['currentEmail'] ?? '')));
    $resetKey = trim((string)($body['resetKey'] ?? ''));
    $newPassword = (string)($body['newPassword'] ?? '');
    $confirmPassword = (string)($body['confirmPassword'] ?? '');

    if ($currentEmail === '' || $resetKey === '') {
        security_api_json([
            'ok' => false,
            'message' => 'Email and reset key are required.',
        ], 422);
    }

    if ($currentEmail !== strtolower((string)$currentAdmin['email'])) {
        security_api_json([
            'ok' => false,
            'message' => 'Current email does not match your account.',
        ], 422);
    }

    if (!hash_equals(admin_get_reset_key($con), $resetKey)) {
        security_api_json([
            'ok' => false,
            'message' => 'Invalid reset key.',
        ], 422);
    }

    if ($newPassword === '' || $newPassword !== $confirmPassword) {
        security_api_json([
            'ok' => false,
            'message' => 'Passwords do not match.',
        ], 422);
    }

    $passwordPolicyError = null;
    if (!commerza_password_validate($newPassword, $passwordPolicyError)) {
        security_api_json([
            'ok' => false,
            'message' => $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description(),
        ], 422);
    }

    $hash = commerza_password_hash($newPassword);

    $updateStmt = $con->prepare(
        'UPDATE admin_users
         SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL
         WHERE id = ?
         LIMIT 1'
    );

    if (!$updateStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update password.',
        ], 500);
    }

    $updateStmt->bind_param('si', $hash, $adminId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update password.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'admin.password_updated', 'warning', [
        'admin_id' => $adminId,
    ]);

    security_api_json([
        'ok' => true,
        'message' => 'Admin password updated successfully.',
    ]);
}

if ($action === 'update-reset-key') {
    $currentEmail = strtolower(trim((string)($body['currentEmail'] ?? '')));
    $currentPassword = (string)($body['currentPassword'] ?? '');
    $newKey = trim((string)($body['newKey'] ?? ''));
    $confirmKey = trim((string)($body['confirmKey'] ?? ''));

    if ($currentEmail === '' || $currentPassword === '') {
        security_api_json([
            'ok' => false,
            'message' => 'Email and password are required.',
        ], 422);
    }

    if ($currentEmail !== strtolower((string)$currentAdmin['email'])) {
        security_api_json([
            'ok' => false,
            'message' => 'Current email does not match your account.',
        ], 422);
    }

    if (!commerza_password_verify($currentPassword, (string)$currentAdmin['password_hash'])) {
        security_api_json([
            'ok' => false,
            'message' => 'Invalid current password.',
        ], 422);
    }

    if ($newKey === '' || $newKey !== $confirmKey) {
        security_api_json([
            'ok' => false,
            'message' => 'Reset key confirmation does not match.',
        ], 422);
    }

    if (strlen($newKey) < 8 || strlen($newKey) > 64) {
        security_api_json([
            'ok' => false,
            'message' => 'Reset key must be between 8 and 64 characters.',
        ], 422);
    }

    $settingKey = 'admin_reset_key';
    $label = 'Admin Reset Key';
    $group = 'security';

    $insertStmt = $con->prepare(
        'INSERT INTO site_settings (setting_key, setting_val, label, setting_group)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), label = VALUES(label), setting_group = VALUES(setting_group)'
    );

    if (!$insertStmt) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update reset key.',
        ], 500);
    }

    $insertStmt->bind_param('ssss', $settingKey, $newKey, $label, $group);
    $ok = $insertStmt->execute();
    $insertStmt->close();

    if (!$ok) {
        security_api_json([
            'ok' => false,
            'message' => 'Unable to update reset key.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'admin.reset_key_rotated', 'critical', [
        'admin_id' => $adminId,
    ]);

    security_api_json([
        'ok' => true,
        'message' => 'Reset key updated successfully.',
    ]);
}

security_api_json([
    'ok' => false,
    'message' => 'Unsupported action.',
], 400);
