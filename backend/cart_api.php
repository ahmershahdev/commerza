<?php
header('Content-Type: application/json');

include __DIR__ . '/data.php';
require_once __DIR__ . '/cart_helpers.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/coupon_helpers.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function cart_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function cart_api_snapshot_response(mysqli $con): array
{
    $snapshot = commerza_fetch_cart_snapshot($con);
    $userId = commerza_is_logged_in_user() ? (int)$_SESSION['user_id'] : 0;
    $subtotal = (float)($snapshot['subtotal'] ?? 0);
    $shipping = 0.0;

    $couponState = commerza_coupon_get_state($con, $subtotal, $userId);
    $discount = (bool)($couponState['ok'] ?? false)
        ? (float)($couponState['discount'] ?? 0)
        : 0.0;

    $total = round(max(0, $subtotal + $shipping - $discount), 2);
    $couponPayload = commerza_coupon_format_payload($couponState);

    return [
        'ok' => true,
        'logged_in' => commerza_is_logged_in_user(),
        'count' => $snapshot['count'],
        'subtotal' => $snapshot['subtotal'],
        'items' => $snapshot['items'],
        'pricing' => [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => $total,
        ],
        'coupon' => $couponPayload,
        'coupon_notice' => (!$couponPayload && !empty($couponState['code']) && !empty($couponState['message']))
            ? (string)$couponState['message']
            : '',
        'csrf_token' => $_SESSION['csrf_token'],
    ];
}

function cart_api_validate_product(mysqli $con, int $productId): bool
{
    $stmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = strtolower(trim((string)($_REQUEST['action'] ?? 'status')));

commerza_ensure_coupon_schema($con);

if ($action === 'status') {
    cart_api_json(cart_api_snapshot_response($con));
}

if ($method !== 'POST') {
    cart_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    cart_api_json([
        'ok' => false,
        'message' => 'Forbidden.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantityToAdd = (int)($_POST['quantity'] ?? 1);

    if ($productId <= 0 || $quantityToAdd <= 0) {
        cart_api_json(['ok' => false, 'message' => 'Invalid cart payload.'], 422);
    }

    if ($quantityToAdd > 10) {
        cart_api_json(['ok' => false, 'message' => 'Quantity is too high.'], 422);
    }

    if (!cart_api_validate_product($con, $productId)) {
        cart_api_json(['ok' => false, 'message' => 'Product does not exist.'], 404);
    }

    $cartId = commerza_get_cart_id($con, true);
    if (!$cartId) {
        cart_api_json(['ok' => false, 'message' => 'Unable to access cart.'], 500);
    }

    $existingQty = 0;
    $existingStmt = $con->prepare('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
    if ($existingStmt) {
        $existingStmt->bind_param('ii', $cartId, $productId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
        $existingStmt->close();
        if ($existingRow) {
            $existingQty = (int)$existingRow['quantity'];
        }
    }

    $currentTotal = commerza_get_cart_total_qty($con, $cartId);
    $newTotal = $currentTotal - $existingQty + ($existingQty + $quantityToAdd);
    if ($newTotal > 10) {
        cart_api_json(['ok' => false, 'message' => 'Cart limit is 10 items.'], 422);
    }

    $upsertStmt = $con->prepare(
        'INSERT INTO cart_items (cart_id, product_id, quantity, added_at)
         VALUES (?, ?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE
           quantity = quantity + VALUES(quantity),
           added_at = CURRENT_TIMESTAMP'
    );

    if (!$upsertStmt) {
        cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
    }

    $upsertStmt->bind_param('iii', $cartId, $productId, $quantityToAdd);
    $ok = $upsertStmt->execute();
    $upsertStmt->close();

    if (!$ok) {
        cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
    }

    commerza_touch_cart($con, $cartId);

    if (commerza_is_logged_in_user()) {
        commerza_queue_engagement_reminder($con, (int)$_SESSION['user_id'], $productId, 'cart');
    }

    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Added to cart.';
    cart_api_json($response);
}

if ($action === 'apply_coupon') {
    $code = commerza_coupon_normalize_code((string)($_POST['code'] ?? ''));

    if ($code === '') {
        cart_api_json(['ok' => false, 'message' => 'Please enter a valid coupon code.'], 422);
    }

    $snapshot = commerza_fetch_cart_snapshot($con);
    $subtotal = (float)($snapshot['subtotal'] ?? 0);

    if ((int)($snapshot['count'] ?? 0) <= 0 || $subtotal <= 0) {
        cart_api_json(['ok' => false, 'message' => 'Add items to cart before applying a coupon.'], 422);
    }

    $userId = commerza_is_logged_in_user() ? (int)$_SESSION['user_id'] : 0;
    $couponState = commerza_coupon_get_state($con, $subtotal, $userId, $code);

    if (!(bool)($couponState['ok'] ?? false)) {
        cart_api_json([
            'ok' => false,
            'message' => (string)($couponState['message'] ?? 'Coupon could not be applied.'),
            'csrf_token' => $_SESSION['csrf_token'],
        ], 422);
    }

    commerza_coupon_set_session_code((string)$couponState['code']);

    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Coupon applied successfully.';
    cart_api_json($response);
}

if ($action === 'remove_coupon') {
    commerza_coupon_clear_session_code();
    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Coupon removed.';
    cart_api_json($response);
}

if ($action === 'set_qty') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($productId <= 0) {
        cart_api_json(['ok' => false, 'message' => 'Invalid product id.'], 422);
    }

    $cartId = commerza_get_cart_id($con, false);
    if (!$cartId) {
        cart_api_json(['ok' => false, 'message' => 'Cart not found.'], 404);
    }

    $existingQty = 0;
    $existingStmt = $con->prepare('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
    if ($existingStmt) {
        $existingStmt->bind_param('ii', $cartId, $productId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
        $existingStmt->close();
        if ($existingRow) {
            $existingQty = (int)$existingRow['quantity'];
        }
    }

    if ($existingQty <= 0) {
        cart_api_json(['ok' => false, 'message' => 'Item not found in cart.'], 404);
    }

    if ($quantity <= 0) {
        $deleteStmt = $con->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
        if (!$deleteStmt) {
            cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
        }
        $deleteStmt->bind_param('ii', $cartId, $productId);
        $deleteStmt->execute();
        $deleteStmt->close();
        commerza_remove_cart_if_empty($con, $cartId);

        $response = cart_api_snapshot_response($con);
        $response['message'] = 'Item removed from cart.';
        cart_api_json($response);
    }

    if ($quantity > 10) {
        cart_api_json(['ok' => false, 'message' => 'Quantity is too high.'], 422);
    }

    $currentTotal = commerza_get_cart_total_qty($con, $cartId);
    $newTotal = $currentTotal - $existingQty + $quantity;
    if ($newTotal > 10) {
        cart_api_json(['ok' => false, 'message' => 'Cart limit is 10 items.'], 422);
    }

    $updateStmt = $con->prepare('UPDATE cart_items SET quantity = ?, added_at = CURRENT_TIMESTAMP WHERE cart_id = ? AND product_id = ? LIMIT 1');
    if (!$updateStmt) {
        cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
    }

    $updateStmt->bind_param('iii', $quantity, $cartId, $productId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
    }

    commerza_touch_cart($con, $cartId);

    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Cart quantity updated.';
    cart_api_json($response);
}

if ($action === 'remove') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        cart_api_json(['ok' => false, 'message' => 'Invalid product id.'], 422);
    }

    $cartId = commerza_get_cart_id($con, false);
    if (!$cartId) {
        cart_api_json(cart_api_snapshot_response($con));
    }

    $deleteStmt = $con->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
    if (!$deleteStmt) {
        cart_api_json(['ok' => false, 'message' => 'Unable to update cart.'], 500);
    }

    $deleteStmt->bind_param('ii', $cartId, $productId);
    $deleteStmt->execute();
    $deleteStmt->close();

    commerza_remove_cart_if_empty($con, $cartId);

    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Item removed from cart.';
    cart_api_json($response);
}

if ($action === 'clear') {
    commerza_coupon_clear_session_code();

    $cartId = commerza_get_cart_id($con, false);
    if ($cartId) {
        $clearStmt = $con->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        if ($clearStmt) {
            $clearStmt->bind_param('i', $cartId);
            $clearStmt->execute();
            $clearStmt->close();
        }

        commerza_remove_cart_if_empty($con, $cartId);
    }

    $response = cart_api_snapshot_response($con);
    $response['message'] = 'Cart cleared.';
    cart_api_json($response);
}

cart_api_json(['ok' => false, 'message' => 'Invalid action.'], 400);
