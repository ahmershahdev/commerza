<?php
header('Content-Type: application/json');

include __DIR__ . '/data.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/server_timing_helpers.php';
require_once __DIR__ . '/wishlist_schema_helpers.php';

commerza_wishlist_ensure_schema($con);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$wishlistTimingAction = strtolower(trim((string)($_REQUEST['action'] ?? 'status')));
if ($wishlistTimingAction === '') {
    $wishlistTimingAction = 'status';
}
commerza_server_timing_start('wishlist', 'wishlist.' . $wishlistTimingAction);

function wishlist_json(array $payload, int $statusCode = 200): void
{
    global $con;
    if ($con instanceof mysqli) {
        $active = trim((string)($GLOBALS['commerza_wishlist_active_lock'] ?? ''));
        if ($active !== '') {
            wishlist_release_item_lock($con, $active);
            $GLOBALS['commerza_wishlist_active_lock'] = '';
        }
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('Vary: Cookie, Accept-Encoding, Accept');
    }

    commerza_server_timing_emit('wishlist');

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function get_or_create_wishlist_id(mysqli $con, int $userId): ?int
{
    $selectStmt = $con->prepare('SELECT id FROM wishlist WHERE user_id = ? LIMIT 1');
    if (!$selectStmt) {
        return null;
    }

    $selectStmt->bind_param('i', $userId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $selectStmt->close();
        return (int)$row['id'];
    }

    $selectStmt->close();

    $insertStmt = $con->prepare(
        'INSERT INTO wishlist (user_id)
         VALUES (?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    if (!$insertStmt) {
        return null;
    }

    $insertStmt->bind_param('i', $userId);
    $ok = $insertStmt->execute();
    $newId = $ok ? (int)$con->insert_id : null;
    $insertStmt->close();

    return $newId;
}

function fetch_wishlist_state(mysqli $con, int $wishlistId): array
{
    $ids = [];

    $stmt = $con->prepare('SELECT product_id FROM wishlist_items WHERE wishlist_id = ? ORDER BY added_at DESC');
    if ($stmt) {
        $stmt->bind_param('i', $wishlistId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['product_id'];
        }
        $stmt->close();
    }

    return [
        'ids' => $ids,
        'count' => count($ids),
    ];
}

function wishlist_normalize_product_name(string $value): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($value));
    if (!is_string($normalized)) {
        return '';
    }

    return strtolower($normalized);
}

function wishlist_normalize_product_code(string $value): string
{
    $normalized = preg_replace('/\s+/', '', trim($value));
    if (!is_string($normalized)) {
        return '';
    }

    return strtoupper($normalized);
}

function wishlist_validate_product_identity(
    mysqli $con,
    int $productId,
    string $expectedName,
    string $expectedCode
): array {
    $normalizedExpectedName = wishlist_normalize_product_name($expectedName);
    $normalizedExpectedCode = wishlist_normalize_product_code($expectedCode);

    if ($normalizedExpectedName === '' || $normalizedExpectedCode === '') {
        return [
            'ok' => false,
            'message' => 'Product verification data is missing. Refresh and try again.',
        ];
    }

    $stmt = $con->prepare('SELECT id, name, product_code FROM products WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return [
            'ok' => false,
            'message' => 'Unable to verify product right now.',
        ];
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return [
            'ok' => false,
            'message' => 'Product does not exist.',
        ];
    }

    $normalizedActualName = wishlist_normalize_product_name((string)($row['name'] ?? ''));
    $normalizedActualCode = wishlist_normalize_product_code((string)($row['product_code'] ?? ''));

    if ($normalizedExpectedName !== $normalizedActualName) {
        return [
            'ok' => false,
            'message' => 'Product verification failed. Please refresh and try again.',
        ];
    }

    if ($normalizedExpectedCode !== $normalizedActualCode) {
        return [
            'ok' => false,
            'message' => 'Product code mismatch. Please refresh and try again.',
        ];
    }

    return [
        'ok' => true,
    ];
}

function wishlist_item_lock_name(int $wishlistId, int $productId): string
{
    return 'commerza_wishlist_' . max(0, $wishlistId) . '_' . max(0, $productId);
}

function wishlist_acquire_item_lock(mysqli $con, string $lockName, int $timeoutSeconds = 2): bool
{
    $timeout = max(0, $timeoutSeconds);

    $stmt = $con->prepare('SELECT GET_LOCK(?, ?) AS acquired');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $lockName, $timeout);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $acquired = (int)($row['acquired'] ?? 0) === 1;
    if ($acquired) {
        $GLOBALS['commerza_wishlist_active_lock'] = $lockName;
    }

    return $acquired;
}

function wishlist_release_item_lock(mysqli $con, string $lockName): void
{
    $stmt = $con->prepare('SELECT RELEASE_LOCK(?)');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();

    if (($GLOBALS['commerza_wishlist_active_lock'] ?? '') === $lockName) {
        $GLOBALS['commerza_wishlist_active_lock'] = '';
    }
}

function wishlist_rate_limit_identifier(): string
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        return 'user_' . $userId;
    }

    $sessionId = trim((string)session_id());
    if ($sessionId !== '') {
        return 'session_' . substr($sessionId, 0, 64);
    }

    return 'guest';
}

function wishlist_rate_limit_guard(
    mysqli $con,
    string $scope,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $escalatedBlockSeconds = 300
): void {
    $identifier = wishlist_rate_limit_identifier();
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
        'user',
        $identifier,
        $clientIp,
        $retrySeconds
    );

    wishlist_json([
        'ok' => false,
        'message' => 'Too many wishlist requests. Please wait and retry.',
        'retry_after' => $retrySeconds,
        'csrf_token' => $_SESSION['csrf_token'],
    ], 429);
}

function wishlist_user_blacklist_entry(mysqli $con, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $con->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    $blocked = commerza_customer_blacklist_lookup(
        $con,
        (string)($row['email'] ?? ''),
        (string)($row['phone'] ?? '')
    );

    return is_array($blocked) ? $blocked : null;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = strtolower(trim((string)($_REQUEST['action'] ?? 'status')));

$loggedIn = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);

if ($action === 'status') {
    if (!$loggedIn) {
        wishlist_json([
            'ok' => true,
            'logged_in' => false,
            'count' => 0,
            'ids' => [],
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
    }

    $userId = (int)$_SESSION['user_id'];
    $wishlistId = get_or_create_wishlist_id($con, $userId);
    if (!$wishlistId) {
        wishlist_json(['ok' => false, 'message' => 'Unable to load wishlist.'], 500);
    }

    $state = fetch_wishlist_state($con, $wishlistId);

    wishlist_json([
        'ok' => true,
        'logged_in' => true,
        'count' => $state['count'],
        'ids' => $state['ids'],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

if ($action === 'toggle') {
    if ($method !== 'POST') {
        wishlist_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    if (!$loggedIn) {
        wishlist_json([
            'ok' => false,
            'logged_in' => false,
            'message' => 'Please login to use wishlist.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 401);
    }

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        wishlist_json([
            'ok' => false,
            'message' => 'Forbidden.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 403);
    }

    wishlist_rate_limit_guard($con, 'wishlist_toggle', 24, 60, 120, 360);

    $productId = (int)($_POST['product_id'] ?? 0);
    $postedProductName = trim((string)($_POST['product_name'] ?? ''));
    $postedProductCode = trim((string)($_POST['product_code'] ?? ''));

    if (strlen($postedProductName) > 255) {
        $postedProductName = substr($postedProductName, 0, 255);
    }

    if (strlen($postedProductCode) > 80) {
        $postedProductCode = substr($postedProductCode, 0, 80);
    }

    if ($productId <= 0) {
        wishlist_json(['ok' => false, 'message' => 'Invalid product id.'], 422);
    }

    $identityCheck = wishlist_validate_product_identity(
        $con,
        $productId,
        $postedProductName,
        $postedProductCode
    );

    if (!(bool)($identityCheck['ok'] ?? false)) {
        wishlist_json([
            'ok' => false,
            'message' => (string)($identityCheck['message'] ?? 'Product verification failed.'),
        ], 409);
    }

    $userId = (int)$_SESSION['user_id'];
    $blockedContact = wishlist_user_blacklist_entry($con, $userId);
    $wishlistId = get_or_create_wishlist_id($con, $userId);
    if (!$wishlistId) {
        wishlist_json(['ok' => false, 'message' => 'Unable to load wishlist.'], 500);
    }

    $itemLock = wishlist_item_lock_name($wishlistId, $productId);
    if (!wishlist_acquire_item_lock($con, $itemLock, 2)) {
        wishlist_json(['ok' => false, 'message' => 'Wishlist is busy. Please retry.'], 409);
    }

    $checkStmt = $con->prepare('SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1');
    if (!$checkStmt) {
        wishlist_release_item_lock($con, $itemLock);
        wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
    }

    $checkStmt->bind_param('ii', $wishlistId, $productId);
    $checkStmt->execute();
    $checkStmt->store_result();
    $itemExists = $checkStmt->num_rows > 0;
    $checkStmt->close();

    if (is_array($blockedContact) && !$itemExists) {
        wishlist_release_item_lock($con, $itemLock);
        wishlist_json([
            'ok' => false,
            'message' => commerza_customer_blacklist_feedback_message($blockedContact),
            'csrf_token' => $_SESSION['csrf_token'],
        ], 403);
    }

    $added = false;

    if ($itemExists) {
        $deleteStmt = $con->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1');
        if (!$deleteStmt) {
            wishlist_release_item_lock($con, $itemLock);
            wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
        }
        $deleteStmt->bind_param('ii', $wishlistId, $productId);
        $deleteOk = $deleteStmt->execute();
        $deleteStmt->close();

        if (!$deleteOk) {
            wishlist_release_item_lock($con, $itemLock);
            wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
        }

        $added = false;
    } else {
        $insertStmt = $con->prepare('INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)');
        if (!$insertStmt) {
            wishlist_release_item_lock($con, $itemLock);
            wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
        }

        $insertStmt->bind_param('ii', $wishlistId, $productId);
        $insertOk = $insertStmt->execute();
        $insertErrno = (int)$insertStmt->errno;
        $insertStmt->close();

        if (!$insertOk && $insertErrno !== 1062) {
            wishlist_release_item_lock($con, $itemLock);
            wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
        }

        $added = true;

        commerza_queue_engagement_reminder($con, $userId, $productId, 'wishlist');
    }

    $state = fetch_wishlist_state($con, $wishlistId);
    wishlist_release_item_lock($con, $itemLock);

    wishlist_json([
        'ok' => true,
        'logged_in' => true,
        'added' => $added,
        'count' => $state['count'],
        'ids' => $state['ids'],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

if ($action === 'remove') {
    if ($method !== 'POST') {
        wishlist_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    if (!$loggedIn) {
        wishlist_json([
            'ok' => false,
            'logged_in' => false,
            'message' => 'Please login to use wishlist.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 401);
    }

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        wishlist_json([
            'ok' => false,
            'message' => 'Forbidden.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 403);
    }

    wishlist_rate_limit_guard($con, 'wishlist_remove', 30, 60, 120, 360);

    $productId = (int)($_POST['product_id'] ?? 0);
    $postedProductName = trim((string)($_POST['product_name'] ?? ''));
    $postedProductCode = trim((string)($_POST['product_code'] ?? ''));

    if (strlen($postedProductName) > 255) {
        $postedProductName = substr($postedProductName, 0, 255);
    }

    if (strlen($postedProductCode) > 80) {
        $postedProductCode = substr($postedProductCode, 0, 80);
    }

    if ($productId <= 0) {
        wishlist_json(['ok' => false, 'message' => 'Invalid product id.'], 422);
    }

    $identityCheck = wishlist_validate_product_identity(
        $con,
        $productId,
        $postedProductName,
        $postedProductCode
    );

    if (!(bool)($identityCheck['ok'] ?? false)) {
        wishlist_json([
            'ok' => false,
            'message' => (string)($identityCheck['message'] ?? 'Product verification failed.'),
        ], 409);
    }

    $userId = (int)$_SESSION['user_id'];
    $wishlistId = get_or_create_wishlist_id($con, $userId);
    if (!$wishlistId) {
        wishlist_json(['ok' => false, 'message' => 'Unable to load wishlist.'], 500);
    }

    $itemLock = wishlist_item_lock_name($wishlistId, $productId);
    if (!wishlist_acquire_item_lock($con, $itemLock, 2)) {
        wishlist_json(['ok' => false, 'message' => 'Wishlist is busy. Please retry.'], 409);
    }

    $deleteStmt = $con->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1');
    if (!$deleteStmt) {
        wishlist_release_item_lock($con, $itemLock);
        wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
    }

    $deleteStmt->bind_param('ii', $wishlistId, $productId);
    $deleteOk = $deleteStmt->execute();
    $removed = (int)$deleteStmt->affected_rows > 0;
    $deleteStmt->close();

    if (!$deleteOk) {
        wishlist_release_item_lock($con, $itemLock);
        wishlist_json(['ok' => false, 'message' => 'Unable to update wishlist.'], 500);
    }

    $state = fetch_wishlist_state($con, $wishlistId);
    wishlist_release_item_lock($con, $itemLock);

    wishlist_json([
        'ok' => true,
        'logged_in' => true,
        'removed' => $removed,
        'count' => $state['count'],
        'ids' => $state['ids'],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

if ($action === 'clear') {
    if ($method !== 'POST') {
        wishlist_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    if (!$loggedIn) {
        wishlist_json([
            'ok' => false,
            'logged_in' => false,
            'message' => 'Please login to use wishlist.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 401);
    }

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        wishlist_json([
            'ok' => false,
            'message' => 'Forbidden.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 403);
    }

    wishlist_rate_limit_guard($con, 'wishlist_clear', 6, 300, 300, 900);

    $userId = (int)$_SESSION['user_id'];
    $wishlistId = get_or_create_wishlist_id($con, $userId);
    if (!$wishlistId) {
        wishlist_json(['ok' => false, 'message' => 'Unable to load wishlist.'], 500);
    }

    $clearStmt = $con->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ?');
    if (!$clearStmt) {
        wishlist_json(['ok' => false, 'message' => 'Unable to clear wishlist.'], 500);
    }

    $clearStmt->bind_param('i', $wishlistId);
    $clearOk = $clearStmt->execute();
    $clearStmt->close();

    if (!$clearOk) {
        wishlist_json(['ok' => false, 'message' => 'Unable to clear wishlist.'], 500);
    }

    wishlist_json([
        'ok' => true,
        'logged_in' => true,
        'count' => 0,
        'ids' => [],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

wishlist_json(['ok' => false, 'message' => 'Invalid action.'], 400);
