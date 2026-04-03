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

    $activeNow = 0;
    $trackedProducts = 0;
    $topProducts = [];

    try {
        $activeStmt = $con->prepare(
            'SELECT COUNT(DISTINCT session_key) AS total
             FROM live_product_viewers
             WHERE last_seen_at >= ?'
        );
    } catch (Throwable $exception) {
        $activeStmt = null;
    }

    if ($activeStmt) {
        try {
            $activeStmt->bind_param('s', $threshold);
            $activeStmt->execute();
            $result = $activeStmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $activeNow = (int)($row['total'] ?? 0);
            $activeStmt->close();
        } catch (Throwable $exception) {
            $activeStmt->close();
        }
    }

    try {
        $productsStmt = $con->prepare(
            'SELECT COUNT(DISTINCT product_id) AS total
             FROM live_product_viewers
             WHERE last_seen_at >= ?'
        );
    } catch (Throwable $exception) {
        $productsStmt = null;
    }

    if ($productsStmt) {
        try {
            $productsStmt->bind_param('s', $threshold);
            $productsStmt->execute();
            $result = $productsStmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $trackedProducts = (int)($row['total'] ?? 0);
            $productsStmt->close();
        } catch (Throwable $exception) {
            $productsStmt->close();
        }
    }

    try {
        $topStmt = $con->prepare(
            'SELECT p.id, p.name, COUNT(*) AS viewers
             FROM live_product_viewers v
             INNER JOIN products p ON p.id = v.product_id
             WHERE v.last_seen_at >= ?
             GROUP BY p.id, p.name
             ORDER BY viewers DESC, p.id ASC
             LIMIT 5'
        );
    } catch (Throwable $exception) {
        $topStmt = null;
    }

    if ($topStmt) {
        try {
            $topStmt->bind_param('s', $threshold);
            $topStmt->execute();
            $result = $topStmt->get_result();

            while ($row = $result ? $result->fetch_assoc() : null) {
                if (!$row) {
                    break;
                }

                $topProducts[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)($row['name'] ?? 'Product'),
                    'viewers' => (int)($row['viewers'] ?? 0),
                ];
            }

            $topStmt->close();
        } catch (Throwable $exception) {
            $topStmt->close();
        }
    }

    return [
        'active_now' => $activeNow,
        'tracked_products' => $trackedProducts,
        'top_products' => $topProducts,
    ];
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '{}', true);
if (!is_array($body)) {
    $body = [];
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? ($body['action'] ?? 'get'))));

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
