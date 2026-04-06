<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/data.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function order_tracking_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 405);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

$orderId = strtoupper(preg_replace('/\s+/', '', trim((string)($_POST['order_id'] ?? ''))));
$email = strtolower(trim((string)($_POST['order_email'] ?? '')));

if ($orderId !== '' && strpos($orderId, '#') !== 0) {
    $orderId = '#' . $orderId;
}

if (!preg_match('/^#ORD-[A-Z0-9]{4,20}$/', $orderId)) {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Please enter a valid Order ID (example: #ORD-1234).',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

if ($email === '') {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Please enter the email used during checkout.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Please enter a valid email address.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

$clientIp = commerza_client_ip();
$rateIdentifier = $orderId . '|' . $email;
$rate = commerza_rate_limit_check(
    $con,
    'order_tracking_lookup',
    $rateIdentifier,
    $clientIp,
    16,
    600,
    600,
    1800,
    86400
);

if (!$rate['allowed']) {
    $retrySeconds = max(1, (int)$rate['retry_after']);
    commerza_security_log_rate_limit_block(
        $con,
        'order_tracking_lookup',
        'user',
        $rateIdentifier,
        $clientIp,
        $retrySeconds
    );

    order_tracking_api_json([
        'ok' => false,
        'message' => 'Too many tracking attempts. Please retry shortly.',
        'retry_after' => $retrySeconds,
        'csrf_token' => $_SESSION['csrf_token'],
    ], 429);
}

$stmt = $con->prepare(
    'SELECT id, order_number, customer_name, customer_email, customer_phone, address, grand_total, status, payment_status, payment_method, created_at
     FROM orders
     WHERE order_number = ? AND customer_email = ?
     LIMIT 1'
);

if ($stmt) {
    $stmt->bind_param('ss', $orderId, $email);
}

if (!$stmt) {
    order_tracking_api_json([
        'ok' => false,
        'message' => 'Unable to track your order right now. Please try again.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

$stmt->execute();
$result = $stmt->get_result();
$order = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$order) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    order_tracking_api_json([
        'ok' => false,
        'message' => 'No order found with the provided details.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 404);
}

$orderItems = [];
$itemsStmt = $con->prepare(
    'SELECT product_name, product_img, unit_price, quantity, line_total
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC'
);

if ($itemsStmt) {
    $orderIdInt = (int)$order['id'];
    $itemsStmt->bind_param('i', $orderIdInt);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    while ($itemsResult && ($row = $itemsResult->fetch_assoc())) {
        $orderItems[] = [
            'product_name' => (string)($row['product_name'] ?? ''),
            'product_img' => (string)($row['product_img'] ?? ''),
            'unit_price' => (float)($row['unit_price'] ?? 0),
            'quantity' => (int)($row['quantity'] ?? 0),
            'line_total' => (float)($row['line_total'] ?? 0),
        ];
    }

    $itemsStmt->close();
}

$createdAt = (string)($order['created_at'] ?? '');
$createdAtTs = strtotime($createdAt);
$createdLabel = $createdAtTs ? date('M d, Y h:i A', $createdAtTs) : $createdAt;

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

order_tracking_api_json([
    'ok' => true,
    'csrf_token' => $_SESSION['csrf_token'],
    'payload' => [
        'order' => [
            'order_number' => (string)($order['order_number'] ?? ''),
            'customer_name' => (string)($order['customer_name'] ?? ''),
            'customer_email' => (string)($order['customer_email'] ?? ''),
            'customer_phone' => (string)($order['customer_phone'] ?? ''),
            'address' => (string)($order['address'] ?? ''),
            'grand_total' => (float)($order['grand_total'] ?? 0),
            'status' => (string)($order['status'] ?? ''),
            'payment_status' => (string)($order['payment_status'] ?? ''),
            'payment_method' => (string)($order['payment_method'] ?? ''),
            'created_label' => (string)$createdLabel,
        ],
        'items' => $orderItems,
    ],
]);
