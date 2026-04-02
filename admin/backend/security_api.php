<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
    exit;
}

$admin = admin_require_login_api($con);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
}

if (!admin_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}

$action = strtolower(trim((string)($body['action'] ?? '')));

if ($action === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Action is required.',
    ]);
    exit;
}

$stmt = $con->prepare(
    'SELECT id, full_name, email, password_hash, is_active
     FROM admin_users
     WHERE id = ?
     LIMIT 1'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Server error.',
    ]);
    exit;
}

$adminId = (int)$admin['id'];
$stmt->bind_param('i', $adminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$currentAdmin || (int)$currentAdmin['is_active'] !== 1) {
    admin_logout_user();
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

if ($action === 'update-email') {
    $currentPassword = (string)($body['currentPassword'] ?? '');
    $resetKey = trim((string)($body['resetKey'] ?? ''));
    $newEmail = strtolower(trim((string)($body['newEmail'] ?? '')));
    $confirmEmail = strtolower(trim((string)($body['confirmEmail'] ?? '')));

    if ($currentPassword === '' || $resetKey === '') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Password and reset key are required.',
        ]);
        exit;
    }

    if (!password_verify($currentPassword, (string)$currentAdmin['password_hash'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid current password.',
        ]);
        exit;
    }

    if (!hash_equals(admin_get_reset_key($con), $resetKey)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid reset key.',
        ]);
        exit;
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 150) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Enter a valid email address.',
        ]);
        exit;
    }

    if ($newEmail !== $confirmEmail) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Email confirmation does not match.',
        ]);
        exit;
    }

    $duplicateStmt = $con->prepare(
        'SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1'
    );

    if (!$duplicateStmt) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Server error.',
        ]);
        exit;
    }

    $duplicateStmt->bind_param('si', $newEmail, $adminId);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
    $duplicateStmt->close();

    if ($duplicateRow) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Email is already used by another admin account.',
        ]);
        exit;
    }

    $updateStmt = $con->prepare(
        'UPDATE admin_users SET email = ? WHERE id = ? LIMIT 1'
    );

    if (!$updateStmt) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update email.',
        ]);
        exit;
    }

    $updateStmt->bind_param('si', $newEmail, $adminId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update email.',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Admin email updated successfully.',
        'email' => $newEmail,
    ]);
    exit;
}

if ($action === 'update-password') {
    $currentEmail = strtolower(trim((string)($body['currentEmail'] ?? '')));
    $resetKey = trim((string)($body['resetKey'] ?? ''));
    $newPassword = (string)($body['newPassword'] ?? '');
    $confirmPassword = (string)($body['confirmPassword'] ?? '');

    if ($currentEmail === '' || $resetKey === '') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Email and reset key are required.',
        ]);
        exit;
    }

    if ($currentEmail !== strtolower((string)$currentAdmin['email'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Current email does not match your account.',
        ]);
        exit;
    }

    if (!hash_equals(admin_get_reset_key($con), $resetKey)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid reset key.',
        ]);
        exit;
    }

    if ($newPassword === '' || $newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Passwords do not match.',
        ]);
        exit;
    }

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,64}$/', $newPassword)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Password must be 8-64 chars with upper, lower, number, and special character.',
        ]);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updateStmt = $con->prepare(
        'UPDATE admin_users
         SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL
         WHERE id = ?
         LIMIT 1'
    );

    if (!$updateStmt) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update password.',
        ]);
        exit;
    }

    $updateStmt->bind_param('si', $hash, $adminId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update password.',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Admin password updated successfully.',
    ]);
    exit;
}

if ($action === 'update-reset-key') {
    $currentEmail = strtolower(trim((string)($body['currentEmail'] ?? '')));
    $currentPassword = (string)($body['currentPassword'] ?? '');
    $newKey = trim((string)($body['newKey'] ?? ''));
    $confirmKey = trim((string)($body['confirmKey'] ?? ''));

    if ($currentEmail === '' || $currentPassword === '') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Email and password are required.',
        ]);
        exit;
    }

    if ($currentEmail !== strtolower((string)$currentAdmin['email'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Current email does not match your account.',
        ]);
        exit;
    }

    if (!password_verify($currentPassword, (string)$currentAdmin['password_hash'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Invalid current password.',
        ]);
        exit;
    }

    if ($newKey === '' || $newKey !== $confirmKey) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Reset key confirmation does not match.',
        ]);
        exit;
    }

    if (strlen($newKey) < 8 || strlen($newKey) > 64) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Reset key must be between 8 and 64 characters.',
        ]);
        exit;
    }

    $settingKey = 'admin_reset_key';
    $label = 'Admin Reset Key';
    $group = 'security';

    $stmt = $con->prepare(
        'INSERT INTO site_settings (setting_key, setting_val, label, setting_group)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), label = VALUES(label), setting_group = VALUES(setting_group)'
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update reset key.',
        ]);
        exit;
    }

    $stmt->bind_param('ssss', $settingKey, $newKey, $label, $group);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Unable to update reset key.',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Reset key updated successfully.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode([
    'ok' => false,
    'message' => 'Unsupported action.',
]);
