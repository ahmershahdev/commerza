<?php

require_once __DIR__ . '/data.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid request token.',
    ]);
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$source = strtolower(trim((string)($_POST['source'] ?? 'website')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Please enter a valid email address.',
    ]);
    exit;
}

$allowedSources = ['modal', 'inline', 'website', 'admin'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'website';
}

$insertSql =
    'INSERT INTO newsletter_subscribers (email, source, is_active, unsubscribed_at, subscribed_at, updated_at)
     VALUES (?, ?, 1, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
     ON DUPLICATE KEY UPDATE
       source = VALUES(source),
       is_active = 1,
       unsubscribed_at = NULL,
       updated_at = CURRENT_TIMESTAMP';

$stmt = $con->prepare($insertSql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to subscribe right now.',
    ]);
    exit;
}

$stmt->bind_param('ss', $email, $source);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to subscribe right now.',
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Subscription saved.',
], JSON_UNESCAPED_SLASHES);
