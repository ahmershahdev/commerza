<?php

require_once __DIR__ . '/data.php';

header('Content-Type: application/json; charset=utf-8');

function newsletter_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function newsletter_api_rate_limit_guard(
    mysqli $con,
    string $scope,
    string $identifier,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $escalatedBlockSeconds = 1800
): void {
    $clientIp = commerza_client_ip();
    $rate = commerza_rate_limit_check(
        $con,
        $scope,
        $identifier,
        $clientIp,
        max(1, $maxAttempts),
        max(60, $windowSeconds),
        max(60, $blockSeconds),
        max($blockSeconds, $escalatedBlockSeconds)
    );

    if ((bool)($rate['allowed'] ?? true)) {
        return;
    }

    $retrySeconds = max(1, (int)($rate['retry_after'] ?? $blockSeconds));
    commerza_security_log_rate_limit_block(
        $con,
        $scope,
        'guest',
        $identifier,
        $clientIp,
        $retrySeconds
    );

    newsletter_api_json([
        'ok' => false,
        'message' => 'Too many subscription attempts. Please retry shortly.',
        'retry_after' => $retrySeconds,
    ], 429);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletter_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    newsletter_api_json([
        'ok' => false,
        'message' => 'Invalid request token.',
    ], 403);
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$source = strtolower(trim((string)($_POST['source'] ?? 'website')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    newsletter_api_json([
        'ok' => false,
        'message' => 'Please enter a valid email address.',
    ], 422);
}

$allowedSources = ['modal', 'inline', 'website', 'admin'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'website';
}

newsletter_api_rate_limit_guard($con, 'newsletter_subscribe_ip', 'newsletter_bucket', 20, 600, 600, 1800);
newsletter_api_rate_limit_guard($con, 'newsletter_subscribe_email', $email, 6, 3600, 1800, 7200);

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
    newsletter_api_json([
        'ok' => false,
        'message' => 'Unable to subscribe right now.',
    ], 500);
}

$stmt->bind_param('ss', $email, $source);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    newsletter_api_json([
        'ok' => false,
        'message' => 'Unable to subscribe right now.',
    ], 500);
}

newsletter_api_json([
    'ok' => true,
    'message' => 'Subscription saved.',
]);
