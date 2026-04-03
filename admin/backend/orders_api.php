<?php
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../backend/notifications.php';

/** @var mysqli|null $con */
$con = (isset($con) && $con instanceof mysqli)
    ? $con
    : (($GLOBALS['con'] ?? null) instanceof mysqli ? $GLOBALS['con'] : null);

function orders_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function orders_api_request_body(): array
{
    static $body = null;

    if (is_array($body)) {
        return $body;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    $body = is_array($decoded) ? $decoded : [];

    return $body;
}

function orders_api_refund_has_column(mysqli $con, string $column): bool
{
    $column = trim($column);
    if ($column === '' || preg_match('/^[a-zA-Z0-9_]+$/', $column) !== 1) {
        return false;
    }

    $escaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM refund_requests LIKE '{$escaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function orders_api_order_has_column(mysqli $con, string $column): bool
{
    $column = trim($column);
    if ($column === '' || preg_match('/^[a-zA-Z0-9_]+$/', $column) !== 1) {
        return false;
    }

    $escaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM orders LIKE '{$escaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function orders_api_table_has_column(mysqli $con, string $table, string $column): bool
{
    $table = trim($table);
    $column = trim($column);

    if ($table === '' || $column === '') {
        return false;
    }

    if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1 || preg_match('/^[a-zA-Z0-9_]+$/', $column) !== 1) {
        return false;
    }

    $escapedTable = $con->real_escape_string($table);
    $escapedColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function orders_api_prepare_delete_by_int_stmt(mysqli $con, string $table, string $column): ?mysqli_stmt
{
    if (!orders_api_table_has_column($con, $table, $column)) {
        return null;
    }

    $stmt = $con->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
    return $stmt ?: null;
}

function orders_api_prepare_delete_by_email_stmt(mysqli $con, string $table, string $column): ?mysqli_stmt
{
    if (!orders_api_table_has_column($con, $table, $column)) {
        return null;
    }

    $stmt = $con->prepare("DELETE FROM `{$table}` WHERE LOWER(TRIM(`{$column}`)) = ?");
    return $stmt ?: null;
}

function orders_api_close_statements(array $statements): void
{
    foreach ($statements as $stmt) {
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }
}

function orders_api_delete_user_profile_picture(string $relativePath): void
{
    $relativePath = trim(str_replace('\\', '/', $relativePath));
    if ($relativePath === '' || strpos($relativePath, 'frontend/assets/images/users/') !== 0) {
        return;
    }

    if (strpos($relativePath, '..') !== false) {
        return;
    }

    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function orders_api_ensure_order_coupon_columns(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $missingColumns = [
        'discount_total' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'coupon_code' => 'VARCHAR(50) DEFAULT NULL',
    ];

    foreach ($missingColumns as $column => $definition) {
        if (!orders_api_order_has_column($con, $column)) {
            $con->query("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
        }
    }

    $initialized = true;
}

function orders_api_ensure_refund_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS refund_requests (
            id INT NOT NULL AUTO_INCREMENT,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            reason VARCHAR(500) DEFAULT NULL,
            status ENUM("pending", "accepted", "rejected") NOT NULL DEFAULT "pending",
            admin_note VARCHAR(500) DEFAULT NULL,
            evidence_path VARCHAR(255) DEFAULT NULL,
            evidence_name VARCHAR(255) DEFAULT NULL,
            evidence_size INT NOT NULL DEFAULT 0,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_refund_order (order_id),
            KEY idx_refund_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $missingColumns = [
        'evidence_path' => 'VARCHAR(255) DEFAULT NULL',
        'evidence_name' => 'VARCHAR(255) DEFAULT NULL',
        'evidence_size' => 'INT NOT NULL DEFAULT 0',
    ];

    foreach ($missingColumns as $column => $definition) {
        if (!orders_api_refund_has_column($con, $column)) {
            $con->query("ALTER TABLE refund_requests ADD COLUMN {$column} {$definition}");
        }
    }

    $initialized = true;
}

function orders_api_fetch_refunds(mysqli $con): array
{
    orders_api_ensure_refund_table($con);

    $rows = [];
    $result = $con->query(
        'SELECT
            r.id,
            r.order_id,
            r.user_id,
            r.reason,
            r.status,
            r.admin_note,
            r.evidence_path,
            r.evidence_name,
            r.evidence_size,
            r.requested_at,
            r.updated_at,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.status AS order_status,
                o.payment_status AS order_payment_status,
            o.updated_at AS order_updated_at
         FROM refund_requests r
         INNER JOIN orders o ON o.id = r.order_id
         ORDER BY r.requested_at DESC, r.id DESC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $orderUpdatedAt = (string)($row['order_updated_at'] ?? '');
        $eligibleUntil = '';
        if ($orderUpdatedAt !== '') {
            $eligibleUntil = date('Y-m-d H:i:s', strtotime($orderUpdatedAt . ' +7 days'));
        }

        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'orderId' => (int)($row['order_id'] ?? 0),
            'userId' => (int)($row['user_id'] ?? 0),
            'orderNumber' => (string)($row['order_number'] ?? ''),
            'customerName' => (string)($row['customer_name'] ?? ''),
            'customerEmail' => (string)($row['customer_email'] ?? ''),
            'orderStatus' => (string)($row['order_status'] ?? ''),
            'orderPaymentStatus' => (string)($row['order_payment_status'] ?? 'unpaid'),
            'reason' => (string)($row['reason'] ?? ''),
            'status' => (string)($row['status'] ?? 'pending'),
            'adminNote' => (string)($row['admin_note'] ?? ''),
            'evidencePath' => (string)($row['evidence_path'] ?? ''),
            'evidenceName' => (string)($row['evidence_name'] ?? ''),
            'evidenceSize' => (int)($row['evidence_size'] ?? 0),
            'requestedAt' => (string)($row['requested_at'] ?? ''),
            'updatedAt' => (string)($row['updated_at'] ?? ''),
            'eligibleUntil' => $eligibleUntil,
        ];
    }

    return $rows;
}

function orders_api_fetch_order_items(mysqli $con, int $orderId): array
{
    $items = [];

    $stmt = $con->prepare(
        'SELECT product_name, product_img, unit_price, quantity, line_total
         FROM order_items
         WHERE order_id = ?
         ORDER BY id ASC'
    );

    if (!$stmt) {
        return $items;
    }

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result ? $result->fetch_assoc() : null) {
        if (!$row) {
            break;
        }

        $items[] = [
            'name' => (string)($row['product_name'] ?? 'Item'),
            'image' => (string)($row['product_img'] ?? ''),
            'price' => (float)($row['unit_price'] ?? 0),
            'quantity' => (int)($row['quantity'] ?? 0),
            'line_total' => (float)($row['line_total'] ?? 0),
        ];
    }

    $stmt->close();
    return $items;
}

function orders_api_fetch_orders(mysqli $con): array
{
    $orders = [];

    orders_api_ensure_order_coupon_columns($con);

    $result = $con->query(
        'SELECT
            id,
            order_number,
            user_id,
            customer_name,
            customer_email,
            customer_phone,
            address,
            subtotal,
            shipping_cost,
            discount_total,
            coupon_code,
            grand_total,
            status,
            payment_status,
            payment_method,
            created_at
         FROM orders
         ORDER BY created_at DESC'
    );

    if (!$result) {
        return $orders;
    }

    while ($row = $result->fetch_assoc()) {
        $orderId = (int)$row['id'];
        $orders[] = [
            'db_id' => $orderId,
            'orderId' => (string)($row['order_number'] ?? ''),
            'orderDate' => (string)($row['created_at'] ?? ''),
            'customerName' => (string)($row['customer_name'] ?? ''),
            'email' => (string)($row['customer_email'] ?? ''),
            'phone' => (string)($row['customer_phone'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'subtotal' => (float)($row['subtotal'] ?? 0),
            'shipping' => (float)($row['shipping_cost'] ?? 0),
            'discount' => (float)($row['discount_total'] ?? 0),
            'couponCode' => (string)($row['coupon_code'] ?? ''),
            'total' => (float)($row['grand_total'] ?? 0),
            'status' => (string)($row['status'] ?? 'Pending'),
            'paymentMethod' => (string)($row['payment_method'] ?? 'N/A'),
            'paymentStatus' => (string)($row['payment_status'] ?? 'unpaid'),
            'userId' => (int)($row['user_id'] ?? 0),
            'items' => orders_api_fetch_order_items($con, $orderId),
        ];
    }

    return $orders;
}

function orders_api_fetch_customers(mysqli $con): array
{
    $customerMap = [];

    $usersResult = $con->query(
        'SELECT
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.created_at,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.grand_total), 0) AS total_spent
         FROM users u
         LEFT JOIN orders o ON o.user_id = u.id
         GROUP BY u.id, u.full_name, u.email, u.phone, u.created_at
         ORDER BY u.created_at DESC'
    );

    if ($usersResult) {
        while ($row = $usersResult->fetch_assoc()) {
            $email = strtolower(trim((string)($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            $customerMap[$email] = [
                'id' => (int)($row['id'] ?? 0),
                'userId' => (int)($row['id'] ?? 0),
                'name' => (string)($row['full_name'] ?? 'Customer'),
                'email' => (string)($row['email'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'orderCount' => (int)($row['order_count'] ?? 0),
                'totalSpent' => (float)($row['total_spent'] ?? 0),
                'registeredAt' => (string)($row['created_at'] ?? ''),
            ];
        }
    }

    $ordersResult = $con->query(
        'SELECT
            LOWER(TRIM(customer_email)) AS email_key,
            MAX(customer_name) AS customer_name,
            MAX(customer_email) AS customer_email,
            MAX(customer_phone) AS customer_phone,
            MIN(created_at) AS first_seen,
            COUNT(*) AS order_count,
            COALESCE(SUM(grand_total), 0) AS total_spent
         FROM orders
         WHERE customer_email IS NOT NULL AND TRIM(customer_email) <> ""
         GROUP BY email_key'
    );

    if ($ordersResult) {
        while ($row = $ordersResult->fetch_assoc()) {
            $email = strtolower(trim((string)($row['email_key'] ?? '')));
            if ($email === '') {
                continue;
            }

            if (!isset($customerMap[$email])) {
                $customerMap[$email] = [
                    'id' => 0,
                    'userId' => 0,
                    'name' => (string)($row['customer_name'] ?? 'Customer'),
                    'email' => (string)($row['customer_email'] ?? ''),
                    'phone' => (string)($row['customer_phone'] ?? ''),
                    'orderCount' => (int)($row['order_count'] ?? 0),
                    'totalSpent' => (float)($row['total_spent'] ?? 0),
                    'registeredAt' => (string)($row['first_seen'] ?? ''),
                ];
                continue;
            }

            $customerMap[$email]['orderCount'] = max(
                (int)$customerMap[$email]['orderCount'],
                (int)($row['order_count'] ?? 0)
            );
            $customerMap[$email]['totalSpent'] = max(
                (float)$customerMap[$email]['totalSpent'],
                (float)($row['total_spent'] ?? 0)
            );

            $existingRegisteredAt = strtotime((string)($customerMap[$email]['registeredAt'] ?? ''));
            $firstSeen = (string)($row['first_seen'] ?? '');
            $firstSeenTs = strtotime($firstSeen);

            if (
                $firstSeen !== '' &&
                ($existingRegisteredAt === false || ($firstSeenTs !== false && $firstSeenTs < $existingRegisteredAt))
            ) {
                $customerMap[$email]['registeredAt'] = $firstSeen;
            }

            if (
                $customerMap[$email]['phone'] === '' &&
                trim((string)($row['customer_phone'] ?? '')) !== ''
            ) {
                $customerMap[$email]['phone'] = (string)($row['customer_phone'] ?? '');
            }
        }
    }

    $customers = array_values($customerMap);

    usort($customers, static function (array $a, array $b): int {
        $left = strtotime((string)($a['registeredAt'] ?? '')) ?: 0;
        $right = strtotime((string)($b['registeredAt'] ?? '')) ?: 0;
        return $right <=> $left;
    });

    return $customers;
}

function orders_api_fetch_metrics(mysqli $con): array
{
    $metrics = [
        'totalRevenue' => 0.0,
        'totalOrders' => 0,
        'totalCustomers' => 0,
        'totalProducts' => 0,
        'avgOrderValue' => 0.0,
        'returningCustomerRate' => 0.0,
        'pendingRefunds' => 0,
        'acceptedRefunds' => 0,
        'rejectedRefunds' => 0,
        'weeklyPerformance' => [],
        'topProducts' => [],
    ];

    orders_api_ensure_refund_table($con);

    $revenueResult = $con->query(
        'SELECT COALESCE(SUM(grand_total), 0) AS total
         FROM orders
         WHERE status = "Delivered" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    if ($revenueResult) {
        $row = $revenueResult->fetch_assoc();
        $metrics['totalRevenue'] = (float)($row['total'] ?? 0);
    }

    $ordersResult = $con->query(
        'SELECT COUNT(*) AS total
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    if ($ordersResult) {
        $row = $ordersResult->fetch_assoc();
        $metrics['totalOrders'] = (int)($row['total'] ?? 0);
    }

    $customersResult = $con->query(
        'SELECT COUNT(*) AS total
         FROM users
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    if ($customersResult) {
        $row = $customersResult->fetch_assoc();
        $metrics['totalCustomers'] = (int)($row['total'] ?? 0);
    }

    $productsResult = $con->query('SELECT COUNT(*) AS total FROM products');
    if ($productsResult) {
        $row = $productsResult->fetch_assoc();
        $metrics['totalProducts'] = (int)($row['total'] ?? 0);
    }

    if ($metrics['totalOrders'] > 0) {
        $metrics['avgOrderValue'] = round($metrics['totalRevenue'] / max(1, $metrics['totalOrders']), 2);
    }

    $returningResult = $con->query(
        'SELECT
            COUNT(*) AS total_customers,
            COALESCE(SUM(CASE WHEN customer_orders > 1 THEN 1 ELSE 0 END), 0) AS returning_customers
         FROM (
            SELECT LOWER(TRIM(customer_email)) AS email_key, COUNT(*) AS customer_orders
            FROM orders
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND customer_email IS NOT NULL
              AND TRIM(customer_email) <> ""
            GROUP BY email_key
         ) x'
    );

    if ($returningResult) {
        $row = $returningResult->fetch_assoc();
        $totalCustomers = (int)($row['total_customers'] ?? 0);
        $returningCustomers = (int)($row['returning_customers'] ?? 0);
        if ($totalCustomers > 0) {
            $metrics['returningCustomerRate'] = round(($returningCustomers / $totalCustomers) * 100, 2);
        }
    }

    $refundSummaryResult = $con->query(
        'SELECT
            COALESCE(SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END), 0) AS pending_total,
            COALESCE(SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END), 0) AS accepted_total,
            COALESCE(SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END), 0) AS rejected_total
         FROM refund_requests'
    );

    if ($refundSummaryResult) {
        $row = $refundSummaryResult->fetch_assoc();
        $metrics['pendingRefunds'] = (int)($row['pending_total'] ?? 0);
        $metrics['acceptedRefunds'] = (int)($row['accepted_total'] ?? 0);
        $metrics['rejectedRefunds'] = (int)($row['rejected_total'] ?? 0);
    }

    $weeklyMap = [];
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $labels[$day] = [
            'date' => $day,
            'label' => date('D', strtotime($day)),
            'revenue' => 0.0,
            'orders' => 0,
        ];
    }

    $weeklyResult = $con->query(
        'SELECT
            DATE(created_at) AS day,
            COUNT(*) AS order_count,
            COALESCE(SUM(grand_total), 0) AS revenue
         FROM orders
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at) ASC'
    );

    if ($weeklyResult) {
        while ($row = $weeklyResult->fetch_assoc()) {
            $day = (string)($row['day'] ?? '');
            if (!isset($labels[$day])) {
                continue;
            }

            $labels[$day]['revenue'] = (float)($row['revenue'] ?? 0);
            $labels[$day]['orders'] = (int)($row['order_count'] ?? 0);
        }
    }

    foreach ($labels as $day => $item) {
        $weeklyMap[] = $item;
    }
    $metrics['weeklyPerformance'] = $weeklyMap;

    $topProductsResult = $con->query(
        'SELECT
            oi.product_name,
            COALESCE(SUM(oi.quantity), 0) AS total_qty,
            COALESCE(SUM(oi.line_total), 0) AS total_revenue
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY oi.product_name
         ORDER BY total_qty DESC, total_revenue DESC
         LIMIT 5'
    );

    if ($topProductsResult) {
        while ($row = $topProductsResult->fetch_assoc()) {
            $metrics['topProducts'][] = [
                'name' => (string)($row['product_name'] ?? 'Product'),
                'orders' => (int)($row['total_qty'] ?? 0),
                'revenue' => (float)($row['total_revenue'] ?? 0),
            ];
        }
    }

    return $metrics;
}

function orders_api_find_order(mysqli $con, string $orderNumber): ?array
{
    $stmt = $con->prepare(
        'SELECT
            id,
            order_number,
            customer_name,
            customer_email,
            customer_phone,
            address,
            subtotal,
            shipping_cost,
            grand_total,
            status,
            payment_method,
            created_at
         FROM orders
         WHERE order_number = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function orders_api_summary_payload(mysqli $con): array
{
    return [
        'orders' => orders_api_fetch_orders($con),
        'customers' => orders_api_fetch_customers($con),
        'metrics' => orders_api_fetch_metrics($con),
        'refunds' => orders_api_fetch_refunds($con),
    ];
}

if (!isset($con) || !($con instanceof mysqli)) {
    orders_api_json(['ok' => false, 'message' => 'Service unavailable.'], 500);
}

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'orders.manage');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestBody = orders_api_request_body();
$action = 'summary';
if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'summary')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($requestBody['action'] ?? ($_POST['action'] ?? 'summary'))));
}

if ($action === 'summary') {
    orders_api_json([
        'ok' => true,
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'refund-summary') {
    orders_api_json([
        'ok' => true,
        'payload' => [
            'refunds' => orders_api_fetch_refunds($con),
        ],
    ]);
}

if ($action === 'update-status') {
    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $orderNumber = trim((string)($_POST['order_number'] ?? ''));
    $nextStatus = trim((string)($_POST['status'] ?? ''));

    $allowedStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Refunded'];

    if ($orderNumber === '' || !in_array($nextStatus, $allowedStatuses, true)) {
        orders_api_json(['ok' => false, 'message' => 'Invalid status update payload.'], 422);
    }

    $order = orders_api_find_order($con, $orderNumber);
    if (!$order) {
        orders_api_json(['ok' => false, 'message' => 'Order not found.'], 404);
    }

    $oldStatus = (string)($order['status'] ?? 'Pending');

    if ($oldStatus !== $nextStatus) {
        $updateStmt = $con->prepare(
            'UPDATE orders
             SET status = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateStmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update order status.'], 500);
        }

        $orderId = (int)$order['id'];
        $updateStmt->bind_param('si', $nextStatus, $orderId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update order status.'], 500);
        }

        $order['status'] = $nextStatus;
        commerza_notify_order_status_change($con, $order, $oldStatus, $nextStatus);
    }

    orders_api_json([
        'ok' => true,
        'message' => 'Order status updated.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'delete-orders') {
    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $body = orders_api_request_body();
    $orderNumbers = $body['order_numbers'] ?? $_POST['order_numbers'] ?? [];
    if (!is_array($orderNumbers)) {
        $orderNumbers = [];
    }

    $orderNumbers = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $orderNumbers
    ))));

    if (empty($orderNumbers)) {
        orders_api_json(['ok' => false, 'message' => 'Select at least one order.'], 422);
    }

    orders_api_ensure_refund_table($con);

    $lookupOrderStmt = $con->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
    if (!$lookupOrderStmt) {
        orders_api_json(['ok' => false, 'message' => 'Unable to load selected orders.'], 500);
    }

    $deleteRefundStmt = $con->prepare('DELETE FROM refund_requests WHERE order_id = ?');
    if (!$deleteRefundStmt) {
        $lookupOrderStmt->close();
        orders_api_json(['ok' => false, 'message' => 'Unable to delete selected orders.'], 500);
    }

    $deleteStmt = $con->prepare('DELETE FROM orders WHERE order_number = ? LIMIT 1');
    if (!$deleteStmt) {
        $lookupOrderStmt->close();
        $deleteRefundStmt->close();
        orders_api_json(['ok' => false, 'message' => 'Unable to delete selected orders.'], 500);
    }

    $deleted = 0;
    foreach ($orderNumbers as $orderNumber) {
        if ($orderNumber === '') {
            continue;
        }

        $orderId = 0;
        $lookupOrderStmt->bind_param('s', $orderNumber);
        $lookupOrderStmt->execute();
        $orderResult = $lookupOrderStmt->get_result();
        $orderRow = $orderResult ? $orderResult->fetch_assoc() : null;
        if ($orderRow) {
            $orderId = (int)($orderRow['id'] ?? 0);
        }

        if ($orderId > 0) {
            $deleteRefundStmt->bind_param('i', $orderId);
            $deleteRefundStmt->execute();
        }

        $deleteStmt->bind_param('s', $orderNumber);
        if ($deleteStmt->execute()) {
            $deleted += max(0, (int)$deleteStmt->affected_rows);
        }
    }

    $lookupOrderStmt->close();
    $deleteRefundStmt->close();
    $deleteStmt->close();

    orders_api_json([
        'ok' => true,
        'message' => $deleted > 0
            ? 'Selected orders deleted successfully.'
            : 'No matching orders were deleted.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'delete-customers') {
    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $body = orders_api_request_body();
    $customerIds = $body['customer_ids'] ?? $_POST['customer_ids'] ?? [];
    if (!is_array($customerIds)) {
        $customerIds = [];
    }

    $ids = array_values(array_unique(array_filter(array_map(
        static fn($value): int => (int)$value,
        $customerIds
    ), static fn(int $id): bool => $id > 0)));

    if (empty($ids)) {
        orders_api_json(['ok' => false, 'message' => 'Select at least one customer.'], 422);
    }

    orders_api_ensure_refund_table($con);

    $fetchUserStmt = $con->prepare('SELECT email, profile_picture FROM users WHERE id = ? LIMIT 1');
    $fetchOrderIdsByUserStmt = $con->prepare('SELECT id FROM orders WHERE user_id = ?');
    $fetchOrderIdsByEmailStmt = $con->prepare('SELECT id FROM orders WHERE LOWER(TRIM(customer_email)) = ?');
    $deleteOrderStmt = $con->prepare('DELETE FROM orders WHERE id = ? LIMIT 1');
    $deleteUserStmt = $con->prepare('DELETE FROM users WHERE id = ? LIMIT 1');

    $orderCleanupTargets = [
        ['refund_requests', 'order_id'],
        ['coupon_redemptions', 'order_id'],
        ['product_reviews', 'order_id'],
    ];

    $userCleanupTargets = [
        ['refund_requests', 'user_id'],
        ['coupon_redemptions', 'user_id'],
        ['product_reviews', 'user_id'],
        ['live_product_viewers', 'user_id'],
        ['engagement_reminders', 'user_id'],
        ['user_sessions', 'user_id'],
        ['cart', 'user_id'],
        ['wishlist', 'user_id'],
        ['compare_list', 'user_id'],
    ];

    $emailCleanupTargets = [
        ['newsletter_subscribers', 'email'],
        ['email_suppressed', 'email'],
        ['rate_limits', 'identifier'],
    ];

    $orderCleanupStatements = [];
    $userCleanupStatements = [];
    $emailCleanupStatements = [];

    foreach ($orderCleanupTargets as $target) {
        [$table, $column] = $target;
        $stmt = orders_api_prepare_delete_by_int_stmt($con, $table, $column);
        if ($stmt instanceof mysqli_stmt) {
            $orderCleanupStatements[] = $stmt;
        }
    }

    foreach ($userCleanupTargets as $target) {
        [$table, $column] = $target;
        $stmt = orders_api_prepare_delete_by_int_stmt($con, $table, $column);
        if ($stmt instanceof mysqli_stmt) {
            $userCleanupStatements[] = $stmt;
        }
    }

    foreach ($emailCleanupTargets as $target) {
        [$table, $column] = $target;
        $stmt = orders_api_prepare_delete_by_email_stmt($con, $table, $column);
        if ($stmt instanceof mysqli_stmt) {
            $emailCleanupStatements[] = $stmt;
        }
    }

    if (
        !$fetchUserStmt ||
        !$fetchOrderIdsByUserStmt ||
        !$fetchOrderIdsByEmailStmt ||
        !$deleteOrderStmt ||
        !$deleteUserStmt
    ) {
        if ($fetchUserStmt) {
            $fetchUserStmt->close();
        }
        if ($fetchOrderIdsByUserStmt) {
            $fetchOrderIdsByUserStmt->close();
        }
        if ($fetchOrderIdsByEmailStmt) {
            $fetchOrderIdsByEmailStmt->close();
        }
        if ($deleteOrderStmt) {
            $deleteOrderStmt->close();
        }
        if ($deleteUserStmt) {
            $deleteUserStmt->close();
        }
        orders_api_close_statements($orderCleanupStatements);
        orders_api_close_statements($userCleanupStatements);
        orders_api_close_statements($emailCleanupStatements);
        orders_api_json(['ok' => false, 'message' => 'Unable to delete selected customers.'], 500);
    }

    $deleted = 0;
    $failed = 0;
    foreach ($ids as $customerId) {
        if (!$con->begin_transaction()) {
            $failed++;
            continue;
        }

        $operationOk = true;
        $customerEmail = '';
        $profilePicturePath = '';

        $fetchUserStmt->bind_param('i', $customerId);
        $fetchUserStmt->execute();
        $customerResult = $fetchUserStmt->get_result();
        $customerRow = $customerResult ? $customerResult->fetch_assoc() : null;
        if ($customerRow) {
            $customerEmail = strtolower(trim((string)($customerRow['email'] ?? '')));
            $profilePicturePath = trim((string)($customerRow['profile_picture'] ?? ''));
        } else {
            $con->rollback();
            continue;
        }

        $orderIds = [];

        $fetchOrderIdsByUserStmt->bind_param('i', $customerId);
        $fetchOrderIdsByUserStmt->execute();
        $ordersByUserResult = $fetchOrderIdsByUserStmt->get_result();
        while ($orderRow = $ordersByUserResult ? $ordersByUserResult->fetch_assoc() : null) {
            if (!$orderRow) {
                break;
            }
            $orderIds[] = (int)($orderRow['id'] ?? 0);
        }

        if ($customerEmail !== '') {
            $fetchOrderIdsByEmailStmt->bind_param('s', $customerEmail);
            $fetchOrderIdsByEmailStmt->execute();
            $ordersByEmailResult = $fetchOrderIdsByEmailStmt->get_result();
            while ($orderRow = $ordersByEmailResult ? $ordersByEmailResult->fetch_assoc() : null) {
                if (!$orderRow) {
                    break;
                }
                $orderIds[] = (int)($orderRow['id'] ?? 0);
            }
        }

        $orderIds = array_values(array_unique(array_filter($orderIds, static fn(int $id): bool => $id > 0)));

        foreach ($orderIds as $orderId) {
            foreach ($orderCleanupStatements as $cleanupStmt) {
                $cleanupStmt->bind_param('i', $orderId);
                if (!$cleanupStmt->execute()) {
                    $operationOk = false;
                    break;
                }
            }

            if (!$operationOk) {
                break;
            }

            $deleteOrderStmt->bind_param('i', $orderId);
            if (!$deleteOrderStmt->execute()) {
                $operationOk = false;
                break;
            }
        }

        if ($operationOk) {
            foreach ($userCleanupStatements as $cleanupStmt) {
                $cleanupStmt->bind_param('i', $customerId);
                if (!$cleanupStmt->execute()) {
                    $operationOk = false;
                    break;
                }
            }
        }

        if ($operationOk && $customerEmail !== '') {
            foreach ($emailCleanupStatements as $cleanupStmt) {
                $cleanupStmt->bind_param('s', $customerEmail);
                if (!$cleanupStmt->execute()) {
                    $operationOk = false;
                    break;
                }
            }
        }

        $userDeletedRows = 0;

        if ($operationOk) {
            $deleteUserStmt->bind_param('i', $customerId);
            if (!$deleteUserStmt->execute()) {
                $operationOk = false;
            } else {
                $userDeletedRows = max(0, (int)$deleteUserStmt->affected_rows);
            }
        }

        if ($operationOk) {
            if ($con->commit()) {
                $deleted += $userDeletedRows;
                if ($userDeletedRows > 0) {
                    orders_api_delete_user_profile_picture($profilePicturePath);
                }
            } else {
                $con->rollback();
                $failed++;
            }
        } else {
            $con->rollback();
            $failed++;
        }
    }

    $fetchUserStmt->close();
    $fetchOrderIdsByUserStmt->close();
    $fetchOrderIdsByEmailStmt->close();
    $deleteOrderStmt->close();
    $deleteUserStmt->close();
    orders_api_close_statements($orderCleanupStatements);
    orders_api_close_statements($userCleanupStatements);
    orders_api_close_statements($emailCleanupStatements);

    $message = 'No matching customers were deleted.';
    if ($deleted > 0) {
        $message = 'Selected customers deleted successfully.';
        if ($failed > 0) {
            $message .= ' Some records could not be deleted and were rolled back.';
        }
    } elseif ($failed > 0) {
        $message = 'Unable to delete selected customers completely.';
    }

    orders_api_json([
        'ok' => true,
        'message' => $message,
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'update-refund-status') {
    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    orders_api_ensure_refund_table($con);

    $body = orders_api_request_body();
    $refundId = (int)($body['refund_id'] ?? ($_POST['refund_id'] ?? 0));
    $status = strtolower(trim((string)($body['status'] ?? ($_POST['status'] ?? 'pending'))));
    $adminNote = trim((string)($body['admin_note'] ?? ($_POST['admin_note'] ?? '')));
    if (strlen($adminNote) > 500) {
        $adminNote = substr($adminNote, 0, 500);
    }

    if ($refundId <= 0 || !in_array($status, ['pending', 'accepted', 'rejected'], true)) {
        orders_api_json(['ok' => false, 'message' => 'Invalid refund update payload.'], 422);
    }

    $stmt = $con->prepare(
        'SELECT r.id, r.order_id, o.order_number, o.customer_name, o.customer_email
         FROM refund_requests r
         INNER JOIN orders o ON o.id = r.order_id
         WHERE r.id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        orders_api_json(['ok' => false, 'message' => 'Unable to load refund request.'], 500);
    }

    $stmt->bind_param('i', $refundId);
    $stmt->execute();
    $result = $stmt->get_result();
    $refundRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$refundRow) {
        orders_api_json(['ok' => false, 'message' => 'Refund request not found.'], 404);
    }

    $updateStmt = $con->prepare(
        'UPDATE refund_requests
         SET status = ?, admin_note = ?
         WHERE id = ?
         LIMIT 1'
    );

    if (!$updateStmt) {
        orders_api_json(['ok' => false, 'message' => 'Unable to update refund request.'], 500);
    }

    $updateStmt->bind_param('ssi', $status, $adminNote, $refundId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        orders_api_json(['ok' => false, 'message' => 'Unable to update refund request.'], 500);
    }

    $orderId = (int)($refundRow['order_id'] ?? 0);
    if ($orderId > 0) {
        $orderUpdateStmt = null;

        if ($status === 'accepted') {
            $orderUpdateStmt = $con->prepare(
                'UPDATE orders
                 SET status = "Refunded", payment_status = "refunded"
                 WHERE id = ?
                 LIMIT 1'
            );
        } elseif ($status === 'pending') {
            $orderUpdateStmt = $con->prepare(
                'UPDATE orders
                 SET status = CASE WHEN status = "Refunded" THEN "Delivered" ELSE status END,
                     payment_status = CASE WHEN payment_status = "unpaid" THEN "unpaid" ELSE "partially_refunded" END
                 WHERE id = ?
                 LIMIT 1'
            );
        } else {
            $orderUpdateStmt = $con->prepare(
                'UPDATE orders
                 SET status = CASE WHEN status = "Refunded" THEN "Delivered" ELSE status END,
                     payment_status = CASE WHEN payment_status IN ("partially_refunded", "refunded") THEN "paid" ELSE payment_status END
                 WHERE id = ?
                 LIMIT 1'
            );
        }

        if (!$orderUpdateStmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to sync refund status with order.'], 500);
        }

        $orderUpdateStmt->bind_param('i', $orderId);
        $orderUpdated = $orderUpdateStmt->execute();
        $orderUpdateStmt->close();

        if (!$orderUpdated) {
            orders_api_json(['ok' => false, 'message' => 'Unable to sync refund status with order.'], 500);
        }
    }

    commerza_notify_refund_status_update(
        $con,
        [
            'order_number' => (string)($refundRow['order_number'] ?? ''),
            'customer_name' => (string)($refundRow['customer_name'] ?? ''),
            'customer_email' => (string)($refundRow['customer_email'] ?? ''),
        ],
        ucfirst($status),
        $adminNote
    );

    orders_api_json([
        'ok' => true,
        'message' => 'Refund request updated.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

orders_api_json(['ok' => false, 'message' => 'Invalid action.'], 400);
