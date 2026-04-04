<?php
header('Content-Type: application/json');

include __DIR__ . '/data.php';
require_once __DIR__ . '/notifications.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        wishlist_json(['ok' => false, 'message' => 'Invalid product id.'], 422);
    }

    $productStmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    if (!$productStmt) {
        wishlist_json(['ok' => false, 'message' => 'Unable to verify product.'], 500);
    }

    $productStmt->bind_param('i', $productId);
    $productStmt->execute();
    $productStmt->store_result();
    $exists = $productStmt->num_rows > 0;
    $productStmt->close();

    if (!$exists) {
        wishlist_json(['ok' => false, 'message' => 'Product does not exist.'], 404);
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

wishlist_json(['ok' => false, 'message' => 'Invalid action.'], 400);
