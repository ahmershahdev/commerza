<?php

declare(strict_types=1);

/**
 * Set (or create) an admin account password from the command line.
 *
 * Usage:
 *   php scripts/dev/reset_admin_password.php <email> <password>
 *
 * The password must satisfy the platform password policy (10-64 chars, upper, lower, digit, symbol).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../backend/core/data.php';

$email = strtolower(trim((string)($argv[1] ?? '')));
$password = (string)($argv[2] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    fwrite(STDERR, "Usage: php scripts/dev/reset_admin_password.php <email> <password>\n");
    exit(1);
}

$policyError = null;
if (!commerza_password_validate($password, $policyError)) {
    fwrite(STDERR, 'Password rejected: ' . ($policyError ?: 'does not meet policy') . "\n");
    exit(1);
}

$hash = commerza_password_hash($password);

$stmt = $con->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $id = (int)$row['id'];
    $update = $con->prepare(
        'UPDATE admin_users
         SET password_hash = ?, is_active = 1, email_verified_at = COALESCE(email_verified_at, NOW())
         WHERE id = ?'
    );
    $update->bind_param('si', $hash, $id);
    $update->execute();
    $update->close();
    echo "Password updated for admin #{$id} ({$email}).\n";
    exit(0);
}

$name = 'Commerza Admin';
$insert = $con->prepare(
    'INSERT INTO admin_users (full_name, email, password_hash, role, is_active, email_verified_at)
     VALUES (?, ?, ?, "admin", 1, NOW())'
);
$insert->bind_param('sss', $name, $email, $hash);
$insert->execute();
echo 'Admin created (#' . $insert->insert_id . ") for {$email}.\n";
$insert->close();
