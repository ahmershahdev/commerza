<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';

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
admin_require_permission_api($admin, 'viewers.manage');

function admin_viewers_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_viewers_get_setting(mysqli $con, string $key, string $default): string
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

function admin_viewers_get_int_setting(mysqli $con, string $key, int $default, int $min, int $max): int
{
    $raw = admin_viewers_get_setting($con, $key, (string)$default);
    $value = (int)$raw;

    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function admin_viewers_upsert_setting(
    mysqli $con,
    string $key,
    string $value,
    string $label,
    string $group
): bool {
    $stmt = $con->prepare(
        'INSERT INTO site_settings (setting_key, setting_val, label, setting_group)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_val = VALUES(setting_val),
            label = VALUES(label),
            setting_group = VALUES(setting_group)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssss', $key, $value, $label, $group);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function admin_viewers_table_ready(mysqli $con): bool
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

function admin_viewers_normalize_ip(string $value): string
{
    return strtolower(trim($value));
}

function admin_viewers_count_unique_rows(array $rows): int
{
    if ($rows === []) {
        return 0;
    }

    $identities = [];
    $activeUserIps = [];

    foreach ($rows as $row) {
        $userIdRaw = $row['user_id'] ?? null;
        if ($userIdRaw === null || $userIdRaw === '') {
            continue;
        }

        if (!is_numeric((string)$userIdRaw)) {
            continue;
        }

        $identities['u:' . (int)$userIdRaw] = true;

        $ipKey = admin_viewers_normalize_ip((string)($row['ip_address'] ?? ''));
        if ($ipKey !== '') {
            $activeUserIps[$ipKey] = true;
        }
    }

    foreach ($rows as $row) {
        $userIdRaw = $row['user_id'] ?? null;
        if ($userIdRaw !== null && $userIdRaw !== '' && is_numeric((string)$userIdRaw)) {
            continue;
        }

        $ipKey = admin_viewers_normalize_ip((string)($row['ip_address'] ?? ''));
        if ($ipKey !== '' && isset($activeUserIps[$ipKey])) {
            continue;
        }

        $sessionKey = trim((string)($row['session_key'] ?? ''));
        if ($ipKey !== '') {
            $identities['i:' . $ipKey] = true;
            continue;
        }

        if ($sessionKey !== '') {
            $identities['s:' . $sessionKey] = true;
        }
    }

    return count($identities);
}

function admin_viewers_config(mysqli $con): array
{
    $mode = strtolower(trim(admin_viewers_get_setting($con, 'live_viewers_mode', 'real')));
    $mode = in_array($mode, ['real', 'fake'], true) ? $mode : 'real';

    $fakeMin = admin_viewers_get_int_setting($con, 'live_viewers_fake_min', 120, 1, 5000);
    $fakeMax = admin_viewers_get_int_setting($con, 'live_viewers_fake_max', 165, 1, 5000);
    $windowSeconds = admin_viewers_get_int_setting($con, 'live_viewers_window_seconds', 180, 30, 3600);

    if ($fakeMax < $fakeMin) {
        $tmp = $fakeMin;
        $fakeMin = $fakeMax;
        $fakeMax = $tmp;
    }

    return [
        'mode' => $mode,
        'fake_min' => $fakeMin,
        'fake_max' => $fakeMax,
        'window_seconds' => $windowSeconds,
    ];
}

function admin_viewers_stats(mysqli $con, int $windowSeconds): array
{
    if (!admin_viewers_table_ready($con)) {
        return [
            'active_now' => 0,
            'tracked_products' => 0,
            'top_products' => [],
        ];
    }

    $threshold = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    $rows = [];
    try {
        $rowsStmt = $con->prepare(
            'SELECT product_id, user_id, session_key, ip_address
             FROM live_product_viewers
             WHERE last_seen_at >= ?'
        );
    } catch (Throwable $exception) {
        $rowsStmt = null;
    }

    if ($rowsStmt) {
        try {
            $rowsStmt->bind_param('s', $threshold);
            $rowsStmt->execute();
            $result = $rowsStmt->get_result();
            while ($result && ($row = $result->fetch_assoc())) {
                $rows[] = $row;
            }
            $rowsStmt->close();
        } catch (Throwable $exception) {
            $rowsStmt->close();
        }
    }

    if ($rows === []) {
        return [
            'active_now' => 0,
            'tracked_products' => 0,
            'top_products' => [],
        ];
    }

    $rowsByProduct = [];
    foreach ($rows as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }

        if (!isset($rowsByProduct[$productId])) {
            $rowsByProduct[$productId] = [];
        }
        $rowsByProduct[$productId][] = $row;
    }

    if ($rowsByProduct === []) {
        return [
            'active_now' => 0,
            'tracked_products' => 0,
            'top_products' => [],
        ];
    }

    $productNames = [];
    try {
        $productResult = $con->query('SELECT id, name FROM products');
    } catch (Throwable $exception) {
        $productResult = null;
    }

    if ($productResult) {
        while ($productRow = $productResult->fetch_assoc()) {
            $productId = (int)($productRow['id'] ?? 0);
            if ($productId > 0 && isset($rowsByProduct[$productId])) {
                $productNames[$productId] = (string)($productRow['name'] ?? 'Product');
            }
        }
        $productResult->free();
    }

    $topProducts = [];
    foreach ($rowsByProduct as $productId => $productRows) {
        $topProducts[] = [
            'id' => (int)$productId,
            'name' => (string)($productNames[(int)$productId] ?? 'Product'),
            'viewers' => admin_viewers_count_unique_rows($productRows),
        ];
    }

    usort(
        $topProducts,
        static function (array $left, array $right): int {
            $viewerDelta = (int)($right['viewers'] ?? 0) <=> (int)($left['viewers'] ?? 0);
            if ($viewerDelta !== 0) {
                return $viewerDelta;
            }

            return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
        }
    );

    $topProducts = array_slice($topProducts, 0, 5);

    return [
        'active_now' => admin_viewers_count_unique_rows($rows),
        'tracked_products' => count($rowsByProduct),
        'top_products' => $topProducts,
    ];
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '{}', true);
if (!is_array($body)) {
    $body = [];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = 'get';
if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'get')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? ($body['action'] ?? 'get'))));
}

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_viewers_api', $action),
    90,
    60,
    120,
    300
);

if ($action === 'get') {
    $config = admin_viewers_config($con);
    $stats = admin_viewers_stats($con, (int)$config['window_seconds']);
    $storageReady = admin_viewers_table_ready($con);

    admin_viewers_json([
        'ok' => true,
        'payload' => [
            'settings' => $config,
            'stats' => $stats,
            'storage_ready' => $storageReady,
        ],
    ]);
}

if ($action !== 'save') {
    admin_viewers_json([
        'ok' => false,
        'message' => 'Invalid action.',
    ], 400);
}

if ($method !== 'POST') {
    admin_viewers_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!admin_validate_csrf_token($csrfToken)) {
    admin_viewers_json([
        'ok' => false,
        'message' => 'Forbidden.',
    ], 403);
}

$mode = strtolower(trim((string)($body['mode'] ?? 'real')));
$mode = in_array($mode, ['real', 'fake'], true) ? $mode : 'real';

$fakeMin = (int)($body['fake_min'] ?? 120);
$fakeMax = (int)($body['fake_max'] ?? 165);
$windowSeconds = (int)($body['window_seconds'] ?? 180);

$fakeMin = max(1, min(5000, $fakeMin));
$fakeMax = max(1, min(5000, $fakeMax));
$windowSeconds = max(30, min(3600, $windowSeconds));

if ($fakeMax < $fakeMin) {
    $tmp = $fakeMin;
    $fakeMin = $fakeMax;
    $fakeMax = $tmp;
}

$ok = true;
$ok = $ok && admin_viewers_upsert_setting($con, 'live_viewers_mode', $mode, 'Live Viewers Mode', 'analytics');
$ok = $ok && admin_viewers_upsert_setting($con, 'live_viewers_fake_min', (string)$fakeMin, 'Live Viewers Fake Min', 'analytics');
$ok = $ok && admin_viewers_upsert_setting($con, 'live_viewers_fake_max', (string)$fakeMax, 'Live Viewers Fake Max', 'analytics');
$ok = $ok && admin_viewers_upsert_setting($con, 'live_viewers_window_seconds', (string)$windowSeconds, 'Live Viewers Window Seconds', 'analytics');

if (!$ok) {
    admin_viewers_json([
        'ok' => false,
        'message' => 'Unable to save viewer settings.',
    ], 500);
}

admin_api_log_security_event($con, $admin, 'analytics.viewers_settings_updated', 'info', [
    'mode' => $mode,
    'fake_min' => $fakeMin,
    'fake_max' => $fakeMax,
    'window_seconds' => $windowSeconds,
]);

$config = admin_viewers_config($con);
$stats = admin_viewers_stats($con, (int)$config['window_seconds']);
$storageReady = admin_viewers_table_ready($con);

admin_viewers_json([
    'ok' => true,
    'message' => 'Viewer settings updated.',
    'payload' => [
        'settings' => $config,
        'stats' => $stats,
        'storage_ready' => $storageReady,
    ],
]);
