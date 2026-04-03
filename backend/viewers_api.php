<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';

header('Content-Type: application/json; charset=utf-8');

if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Service unavailable.',
    ]);
    exit;
}

function viewers_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function viewers_setting(mysqli $con, string $key, string $default): string
{
    try {
        $stmt = $con->prepare(
            'SELECT setting_val
             FROM site_settings
             WHERE setting_key = ?
             LIMIT 1'
        );
    } catch (Throwable $exception) {
        return $default;
    }

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $default;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value === '' ? $default : $value;
}

function viewers_int_setting(mysqli $con, string $key, int $default, int $min, int $max): int
{
    $raw = viewers_setting($con, $key, (string)$default);
    $value = (int)$raw;

    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function viewers_client_ip(): string
{
    if (function_exists('commerza_client_ip')) {
        return commerza_client_ip();
    }

    $candidate = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    return $candidate !== '' ? $candidate : '0.0.0.0';
}

function viewers_fake_count(string $sessionKey, int $productId, int $min, int $max): int
{
    if ($max <= $min) {
        return $min;
    }

    $windowBucket = (int)floor(time() / 45);
    $seed = crc32($sessionKey . '|' . $productId . '|' . $windowBucket);
    $range = $max - $min + 1;

    return $min + (int)($seed % $range);
}

function viewers_table_ready(mysqli $con): bool
{
    try {
        $result = $con->query("SHOW TABLES LIKE 'live_product_viewers'");
    } catch (Throwable $exception) {
        return false;
    }

    if (!$result) {
        return false;
    }

    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function viewers_touch(mysqli $con, string $sessionKey, ?int $userId, int $productId): void
{
    $ipAddress = viewers_client_ip();

    try {
        $stmt = $con->prepare(
            'INSERT INTO live_product_viewers (session_key, user_id, product_id, ip_address, last_seen_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                last_seen_at = NOW()'
        );
    } catch (Throwable $exception) {
        return;
    }

    if (!$stmt) {
        return;
    }

    $nullableUserId = $userId;
    try {
        $stmt->bind_param('siis', $sessionKey, $nullableUserId, $productId, $ipAddress);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $exception) {
        $stmt->close();
    }
}

function viewers_real_count(mysqli $con, int $productId, int $windowSeconds): int
{
    $threshold = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    try {
        $stmt = $con->prepare(
            'SELECT COUNT(*) AS total
             FROM live_product_viewers
             WHERE product_id = ?
               AND last_seen_at >= ?'
        );
    } catch (Throwable $exception) {
        return 0;
    }

    if (!$stmt) {
        return 0;
    }

    try {
        $stmt->bind_param('is', $productId, $threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    } catch (Throwable $exception) {
        $stmt->close();
        return 0;
    }

    return (int)($row['total'] ?? 0);
}

function viewers_cleanup(mysqli $con, int $windowSeconds): void
{
    if (random_int(1, 20) !== 1) {
        return;
    }

    $cutoff = gmdate('Y-m-d H:i:s', time() - max($windowSeconds * 8, 1800));

    try {
        $stmt = $con->prepare(
            'DELETE FROM live_product_viewers
             WHERE last_seen_at < ?'
        );
    } catch (Throwable $exception) {
        return;
    }

    if (!$stmt) {
        return;
    }

    try {
        $stmt->bind_param('s', $cutoff);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $exception) {
        $stmt->close();
    }
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'count')));
$productId = (int)($_REQUEST['product_id'] ?? 0);
$sessionKey = session_id();
$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : null;

$mode = strtolower(trim(viewers_setting($con, 'live_viewers_mode', 'real')));
$mode = in_array($mode, ['real', 'fake'], true) ? $mode : 'real';
$fakeMin = viewers_int_setting($con, 'live_viewers_fake_min', 120, 1, 5000);
$fakeMax = viewers_int_setting($con, 'live_viewers_fake_max', 165, 1, 5000);
$windowSeconds = viewers_int_setting($con, 'live_viewers_window_seconds', 180, 30, 3600);

if ($fakeMax < $fakeMin) {
    $tmp = $fakeMin;
    $fakeMin = $fakeMax;
    $fakeMax = $tmp;
}

$tableReady = viewers_table_ready($con);

if ($action === 'heartbeat') {
    if ($productId <= 0) {
        viewers_json([
            'ok' => false,
            'message' => 'Invalid product id.',
        ], 422);
    }

    if ($tableReady) {
        viewers_touch($con, $sessionKey, $userId, $productId);
        viewers_cleanup($con, $windowSeconds);
    }

    viewers_json([
        'ok' => true,
        'message' => $tableReady ? 'Heartbeat saved.' : 'Storage unavailable. Run DB migration.',
        'storage_ready' => $tableReady,
    ]);
}

if ($action === 'count') {
    if ($productId <= 0) {
        viewers_json([
            'ok' => false,
            'message' => 'Invalid product id.',
        ], 422);
    }

    $touchRequested = (string)($_REQUEST['heartbeat'] ?? '') === '1';
    if ($touchRequested && $tableReady) {
        viewers_touch($con, $sessionKey, $userId, $productId);
    }

    if ($tableReady) {
        viewers_cleanup($con, $windowSeconds);
    }

    $displayCount = $mode === 'fake' || !$tableReady
        ? viewers_fake_count($sessionKey, $productId, $fakeMin, $fakeMax)
        : viewers_real_count($con, $productId, $windowSeconds);

    viewers_json([
        'ok' => true,
        'mode' => $mode,
        'product_id' => $productId,
        'display_count' => max($displayCount, 0),
        'window_seconds' => $windowSeconds,
        'storage_ready' => $tableReady,
    ]);
}

viewers_json([
    'ok' => false,
    'message' => 'Invalid action.',
], 400);
