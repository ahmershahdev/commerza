<?php
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../backend/helpers/notifications.php';

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

function orders_api_is_cod_payment_method(string $paymentMethod): bool
{
    $normalized = strtolower(trim($paymentMethod));
    if ($normalized === '') {
        return false;
    }

    return $normalized === 'cod'
        || str_contains($normalized, 'cash on delivery')
        || preg_match('/\bcod\b/i', $normalized) === 1;
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

function orders_api_get_setting(mysqli $con, string $key, string $fallback = ''): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function orders_api_upsert_setting(mysqli $con, string $key, string $value, string $label, string $group): bool
{
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

function orders_api_apply_action_rate_limit(mysqli $con, array $admin, string $action): void
{
    $rules = [
        'save-shipping-settings' => [20, 60, 30, 300],
        'add-blacklist' => [24, 60, 36, 300],
        'remove-blacklist' => [24, 60, 36, 300],
        'remove-blacklist-contact' => [24, 60, 36, 300],
        'save-blacklist-notice-visibility' => [16, 60, 24, 300],
        'update-status' => [40, 60, 60, 300],
        'update-logistics' => [28, 60, 40, 300],
        'delete-orders' => [12, 60, 18, 300],
        'delete-customers' => [10, 60, 16, 300],
        'update-refund-status' => [24, 60, 36, 300],
    ];

    $rule = $rules[$action] ?? null;
    if (!is_array($rule) || count($rule) < 4) {
        return;
    }

    admin_api_rate_limit_guard(
        $con,
        $admin,
        admin_api_scope('admin_orders_api_hard', $action),
        (int)$rule[0],
        (int)$rule[1],
        (int)$rule[2],
        (int)$rule[3]
    );
}

function orders_api_require_any_permission(array $admin, array $permissions): void
{
    foreach ($permissions as $permission) {
        if (admin_has_permission($admin, (string)$permission)) {
            return;
        }
    }

    orders_api_json([
        'ok' => false,
        'message' => 'Forbidden.',
    ], 403);
}

function orders_api_blacklist_notice_visible(mysqli $con): bool
{
    $raw = strtolower(trim(orders_api_get_setting($con, 'account_blacklist_notice_visible', '1')));
    if ($raw === '') {
        return true;
    }

    if (in_array($raw, ['0', 'false', 'no', 'off', 'hidden', 'hide'], true)) {
        return false;
    }

    return true;
}

function orders_api_shipping_config(mysqli $con): array
{
    $flatRaw = orders_api_get_setting($con, 'shipping_flat_fee', '1000');
    $freeRaw = orders_api_get_setting($con, 'free_shipping_over', '500');

    $flatFee = is_numeric($flatRaw) ? (float)$flatRaw : 1000.0;
    $freeShippingOver = is_numeric($freeRaw) ? (float)$freeRaw : 500.0;

    return [
        'flatFee' => round(max(0, $flatFee), 2),
        'freeShippingOver' => round(max(0, $freeShippingOver), 2),
    ];
}

function orders_api_ensure_blacklist_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS customer_blacklist (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_by_admin_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_blacklist_active (is_active, created_at),
            KEY idx_customer_blacklist_email (email),
            KEY idx_customer_blacklist_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function orders_api_normalize_blacklist_email(string $email): string
{
    $normalized = strtolower(trim($email));
    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL) || strlen($normalized) > 150) {
        return '';
    }

    return $normalized;
}

function orders_api_normalize_blacklist_phone(string $phone): string
{
    $normalized = preg_replace('/\s+/', '', trim($phone));
    if (!is_string($normalized) || preg_match('/^\d{11,15}$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function orders_api_blacklist_rows(mysqli $con): array
{
    orders_api_ensure_blacklist_table($con);

    $rows = [];
    $result = $con->query(
        'SELECT id, email, phone, reason, created_by_admin_id, created_at, updated_at
         FROM customer_blacklist
         WHERE is_active = 1
         ORDER BY created_at DESC, id DESC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'email' => (string)($row['email'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'reason' => (string)($row['reason'] ?? ''),
            'createdByAdminId' => (int)($row['created_by_admin_id'] ?? 0),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'updatedAt' => (string)($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function orders_api_blacklist_lookup_active(mysqli $con, string $email = '', string $phone = ''): ?array
{
    orders_api_ensure_blacklist_table($con);

    $normalizedEmail = orders_api_normalize_blacklist_email($email);
    $normalizedPhone = orders_api_normalize_blacklist_phone($phone);

    if ($normalizedEmail !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason
             FROM customer_blacklist
             WHERE is_active = 1
               AND LOWER(TRIM(email)) = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return $row;
            }
        }
    }

    if ($normalizedPhone !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason
             FROM customer_blacklist
             WHERE is_active = 1
               AND phone = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return $row;
            }
        }
    }

    return null;
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
            o.payment_method AS order_payment_method,
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
            'orderPaymentMethod' => (string)($row['order_payment_method'] ?? ''),
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
            u.username,
            u.email,
            u.phone,
            u.created_at,
            COUNT(o.id) AS order_count,
            COALESCE(SUM(o.grand_total), 0) AS total_spent
         FROM users u
         LEFT JOIN orders o ON o.user_id = u.id
         GROUP BY u.id, u.full_name, u.username, u.email, u.phone, u.created_at
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
                'username' => (string)($row['username'] ?? ''),
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
                    'username' => '',
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

    $blacklistRows = orders_api_blacklist_rows($con);
    $blockedEmails = [];
    $blockedPhones = [];

    foreach ($blacklistRows as $entry) {
        $entryEmail = orders_api_normalize_blacklist_email((string)($entry['email'] ?? ''));
        $entryPhone = orders_api_normalize_blacklist_phone((string)($entry['phone'] ?? ''));

        if ($entryEmail !== '') {
            $blockedEmails[$entryEmail] = (string)($entry['reason'] ?? '');
        }

        if ($entryPhone !== '') {
            $blockedPhones[$entryPhone] = (string)($entry['reason'] ?? '');
        }
    }

    foreach ($customers as &$customer) {
        $customerEmail = orders_api_normalize_blacklist_email((string)($customer['email'] ?? ''));
        $customerPhone = orders_api_normalize_blacklist_phone((string)($customer['phone'] ?? ''));
        $isBlacklisted = false;
        $blacklistReason = '';

        if ($customerEmail !== '' && isset($blockedEmails[$customerEmail])) {
            $isBlacklisted = true;
            $blacklistReason = (string)$blockedEmails[$customerEmail];
        }

        if (!$isBlacklisted && $customerPhone !== '' && isset($blockedPhones[$customerPhone])) {
            $isBlacklisted = true;
            $blacklistReason = (string)$blockedPhones[$customerPhone];
        }

        $customer['isBlacklisted'] = $isBlacklisted;
        $customer['blacklistReason'] = $blacklistReason;
    }
    unset($customer);

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
        'refundLoss' => 0.0,
        'netRevenue' => 0.0,
        'totalOrders' => 0,
        'pendingFulfillment' => 0,
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

    $lossResult = $con->query(
        'SELECT COALESCE(SUM(o.grand_total), 0) AS total
         FROM refund_requests r
         INNER JOIN orders o ON o.id = r.order_id
         WHERE r.status = "accepted"
           AND r.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    if ($lossResult) {
        $row = $lossResult->fetch_assoc();
        $metrics['refundLoss'] = (float)($row['total'] ?? 0);
    }

    $metrics['netRevenue'] = round($metrics['totalRevenue'] - $metrics['refundLoss'], 2);

    $ordersResult = $con->query(
        'SELECT COUNT(*) AS total
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    if ($ordersResult) {
        $row = $ordersResult->fetch_assoc();
        $metrics['totalOrders'] = (int)($row['total'] ?? 0);
    }

    $pendingFulfillmentResult = $con->query(
        'SELECT COUNT(*) AS total
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND LOWER(TRIM(status)) IN ("pending", "confirmed", "processing")'
    );
    if ($pendingFulfillmentResult) {
        $row = $pendingFulfillmentResult->fetch_assoc();
        $metrics['pendingFulfillment'] = (int)($row['total'] ?? 0);
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
            'loss' => 0.0,
            'net' => 0.0,
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

    $weeklyLossResult = $con->query(
        'SELECT
            DATE(r.updated_at) AS day,
            COALESCE(SUM(o.grand_total), 0) AS loss_total
         FROM refund_requests r
         INNER JOIN orders o ON o.id = r.order_id
         WHERE r.status = "accepted"
           AND r.updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(r.updated_at)
         ORDER BY DATE(r.updated_at) ASC'
    );

    if ($weeklyLossResult) {
        while ($row = $weeklyLossResult->fetch_assoc()) {
            $day = (string)($row['day'] ?? '');
            if (!isset($labels[$day])) {
                continue;
            }

            $labels[$day]['loss'] = (float)($row['loss_total'] ?? 0);
        }
    }

    foreach ($labels as $day => $item) {
        $labels[$day]['net'] = (float)$labels[$day]['revenue'] - (float)$labels[$day]['loss'];
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
        'blacklist' => orders_api_blacklist_rows($con),
        'blacklistNoticeVisible' => orders_api_blacklist_notice_visible($con),
        'shippingConfig' => orders_api_shipping_config($con),
    ];
}

if (!isset($con) || !($con instanceof mysqli)) {
    orders_api_json(['ok' => false, 'message' => 'Service unavailable.'], 500);
}

$admin = admin_require_login_api($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestBody = orders_api_request_body();
$action = 'summary';
if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'summary')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($requestBody['action'] ?? ($_POST['action'] ?? 'summary'))));
}

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_orders_api', $action),
    180,
    60,
    120,
    300
);

orders_api_apply_action_rate_limit($con, $admin, $action);

if ($action === 'summary') {
    orders_api_require_any_permission($admin, [
        'orders.manage',
        'customers.manage',
        'analytics.view',
        'dashboard.view',
    ]);

    orders_api_json([
        'ok' => true,
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'refund-summary') {
    orders_api_require_any_permission($admin, ['orders.manage']);

    orders_api_json([
        'ok' => true,
        'payload' => [
            'refunds' => orders_api_fetch_refunds($con),
        ],
    ]);
}

if ($action === 'save-shipping-settings') {
    orders_api_require_any_permission($admin, ['orders.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $flatFeeRaw = (string)($requestBody['flat_fee'] ?? ($_POST['flat_fee'] ?? '0'));
    $freeOverRaw = (string)($requestBody['free_shipping_over'] ?? ($_POST['free_shipping_over'] ?? '0'));

    if (!is_numeric($flatFeeRaw) || !is_numeric($freeOverRaw)) {
        orders_api_json(['ok' => false, 'message' => 'Shipping values must be numeric.'], 422);
    }

    $flatFee = round(max(0, (float)$flatFeeRaw), 2);
    $freeOver = round(max(0, (float)$freeOverRaw), 2);

    $ok = true;
    $ok = $ok && orders_api_upsert_setting($con, 'shipping_flat_fee', (string)$flatFee, 'Shipping Flat Fee', 'shipping');
    $ok = $ok && orders_api_upsert_setting($con, 'free_shipping_over', (string)$freeOver, 'Free Shipping Threshold', 'shipping');

    if (!$ok) {
        orders_api_json(['ok' => false, 'message' => 'Unable to save shipping settings.'], 500);
    }

    admin_api_log_security_event($con, $admin, 'shipping.settings_updated', 'info', [
        'shipping_flat_fee' => $flatFee,
        'free_shipping_over' => $freeOver,
    ]);

    orders_api_json([
        'ok' => true,
        'message' => 'Shipping settings updated.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'add-blacklist') {
    orders_api_require_any_permission($admin, ['orders.manage', 'customers.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $customerId = (int)($requestBody['customer_id'] ?? ($_POST['customer_id'] ?? 0));
    $email = orders_api_normalize_blacklist_email((string)($requestBody['email'] ?? ($_POST['email'] ?? '')));
    $phone = orders_api_normalize_blacklist_phone((string)($requestBody['phone'] ?? ($_POST['phone'] ?? '')));
    $reason = trim((string)($requestBody['reason'] ?? ($_POST['reason'] ?? '')));

    if (strlen($reason) > 255) {
        $reason = substr($reason, 0, 255);
    }

    if ($customerId > 0) {
        $stmt = $con->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                if ($email === '') {
                    $email = orders_api_normalize_blacklist_email((string)($row['email'] ?? ''));
                }
                if ($phone === '') {
                    $phone = orders_api_normalize_blacklist_phone((string)($row['phone'] ?? ''));
                }
            }
        }
    }

    if ($email === '' && $phone === '') {
        orders_api_json(['ok' => false, 'message' => 'Provide a valid email or phone to blacklist.'], 422);
    }

    orders_api_ensure_blacklist_table($con);

    $existing = orders_api_blacklist_lookup_active($con, $email, $phone);
    $adminId = (int)($admin['id'] ?? 0);
    $reasonValue = $reason !== '' ? $reason : null;

    if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
        $existingId = (int)$existing['id'];
        $updateStmt = $con->prepare(
            'UPDATE customer_blacklist
             SET email = ?, phone = ?, reason = ?, created_by_admin_id = ?, is_active = 1
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateStmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entry.'], 500);
        }

        $updateStmt->bind_param('sssii', $email, $phone, $reasonValue, $adminId, $existingId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entry.'], 500);
        }
    } else {
        $insertStmt = $con->prepare(
            'INSERT INTO customer_blacklist (email, phone, reason, created_by_admin_id, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );

        if (!$insertStmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to create blacklist entry.'], 500);
        }

        $insertStmt->bind_param('sssi', $email, $phone, $reasonValue, $adminId);
        $ok = $insertStmt->execute();
        $insertStmt->close();

        if (!$ok) {
            orders_api_json(['ok' => false, 'message' => 'Unable to create blacklist entry.'], 500);
        }
    }

    admin_api_log_security_event($con, $admin, 'customer.blacklisted', 'warning', [
        'customer_id' => $customerId,
        'email' => $email,
        'phone' => $phone,
        'reason' => $reason,
    ]);

    orders_api_json([
        'ok' => true,
        'message' => 'Customer contact blacklisted.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'remove-blacklist') {
    orders_api_require_any_permission($admin, ['orders.manage', 'customers.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $blacklistId = (int)($requestBody['blacklist_id'] ?? ($_POST['blacklist_id'] ?? 0));
    if ($blacklistId <= 0) {
        orders_api_json(['ok' => false, 'message' => 'Invalid blacklist id.'], 422);
    }

    orders_api_ensure_blacklist_table($con);

    $stmt = $con->prepare('UPDATE customer_blacklist SET is_active = 0 WHERE id = ? LIMIT 1');
    if (!$stmt) {
        orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entry.'], 500);
    }

    $stmt->bind_param('i', $blacklistId);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entry.'], 500);
    }

    admin_api_log_security_event($con, $admin, 'customer.blacklist_removed', 'info', [
        'blacklist_id' => $blacklistId,
        'affected_rows' => $affected,
    ]);

    orders_api_json([
        'ok' => true,
        'message' => $affected > 0 ? 'Blacklist entry removed.' : 'Blacklist entry was already inactive.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'remove-blacklist-contact') {
    orders_api_require_any_permission($admin, ['orders.manage', 'customers.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $email = orders_api_normalize_blacklist_email((string)($requestBody['email'] ?? ($_POST['email'] ?? '')));
    $phone = orders_api_normalize_blacklist_phone((string)($requestBody['phone'] ?? ($_POST['phone'] ?? '')));

    if ($email === '' && $phone === '') {
        orders_api_json(['ok' => false, 'message' => 'Provide an email or phone to whitelist.'], 422);
    }

    orders_api_ensure_blacklist_table($con);

    $stmt = null;
    if ($email !== '' && $phone !== '') {
        $stmt = $con->prepare('UPDATE customer_blacklist SET is_active = 0 WHERE is_active = 1 AND (email = ? OR phone = ?)');
        if (!$stmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entries.'], 500);
        }
        $stmt->bind_param('ss', $email, $phone);
    } elseif ($email !== '') {
        $stmt = $con->prepare('UPDATE customer_blacklist SET is_active = 0 WHERE is_active = 1 AND email = ?');
        if (!$stmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entries.'], 500);
        }
        $stmt->bind_param('s', $email);
    } else {
        $stmt = $con->prepare('UPDATE customer_blacklist SET is_active = 0 WHERE is_active = 1 AND phone = ?');
        if (!$stmt) {
            orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entries.'], 500);
        }
        $stmt->bind_param('s', $phone);
    }

    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        orders_api_json(['ok' => false, 'message' => 'Unable to update blacklist entries.'], 500);
    }

    admin_api_log_security_event($con, $admin, 'customer.blacklist_removed_contact', 'info', [
        'email' => $email,
        'phone' => $phone,
        'affected_rows' => $affected,
    ]);

    orders_api_json([
        'ok' => true,
        'message' => $affected > 0 ? 'Contact whitelisted successfully.' : 'No active blacklist entries found for this contact.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'save-blacklist-notice-visibility') {
    orders_api_require_any_permission($admin, ['orders.manage', 'customers.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $visibleRaw = $requestBody['visible_to_user'] ?? ($_POST['visible_to_user'] ?? null);
    $visibleText = strtolower(trim((string)$visibleRaw));
    $visible = true;

    if ($visibleRaw !== null) {
        if (
            $visibleRaw === false
            || $visibleRaw === 0
            || $visibleText === '0'
            || $visibleText === 'false'
            || $visibleText === 'no'
            || $visibleText === 'off'
            || $visibleText === 'hidden'
            || $visibleText === 'hide'
        ) {
            $visible = false;
        }
    }

    $value = $visible ? '1' : '0';
    $saved = orders_api_upsert_setting(
        $con,
        'account_blacklist_notice_visible',
        $value,
        'Account Blacklist Notice Visible',
        'security'
    );

    if (!$saved) {
        orders_api_json(['ok' => false, 'message' => 'Unable to save blacklist notice visibility.'], 500);
    }

    admin_api_log_security_event($con, $admin, 'customer.blacklist_notice_visibility_changed', 'info', [
        'visible_to_user' => $visible,
    ]);

    orders_api_json([
        'ok' => true,
        'message' => $visible
            ? 'Blacklist notice is now visible to blacklisted customers on account page.'
            : 'Blacklist notice is now hidden from blacklisted customers on account page.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'update-status') {
    orders_api_require_any_permission($admin, ['orders.manage']);

    if ($method !== 'POST') {
        orders_api_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = (string)($requestBody['csrf_token'] ?? '');
    }
    if (!admin_validate_csrf_token($csrfToken)) {
        orders_api_json(['ok' => false, 'message' => 'Forbidden.'], 403);
    }

    $orderNumber = trim((string)($requestBody['order_number'] ?? ($_POST['order_number'] ?? '')));
    $nextStatus = trim((string)($requestBody['status'] ?? ($_POST['status'] ?? '')));
    $refreshMode = strtolower(trim((string)($requestBody['refresh_mode'] ?? ($_POST['refresh_mode'] ?? 'minimal'))));
    $returnFullPayload = $refreshMode === 'full';

    $allowedStatuses = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Refunded'];

    if ($orderNumber === '' || !in_array($nextStatus, $allowedStatuses, true)) {
        orders_api_json(['ok' => false, 'message' => 'Invalid status update payload.'], 422);
    }

    if (!$con->begin_transaction()) {
        orders_api_json(['ok' => false, 'message' => 'Unable to lock order for update.'], 500);
    }

    $order = null;
    $orderId = 0;
    $oldStatus = 'Pending';
    $oldPaymentStatus = 'unpaid';

    $lockStmt = $con->prepare(
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
            payment_status,
            payment_method,
            created_at
         FROM orders
         WHERE order_number = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$lockStmt) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Unable to lock order for update.'], 500);
    }

    $lockStmt->bind_param('s', $orderNumber);
    $lockStmt->execute();
    $result = $lockStmt->get_result();
    $order = $result ? $result->fetch_assoc() : null;
    $lockStmt->close();

    if (!$order) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Order not found.'], 404);
    }

    $orderId = (int)($order['id'] ?? 0);
    $oldStatus = (string)($order['status'] ?? 'Pending');
    $oldPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? 'unpaid')));
    if ($oldPaymentStatus === '') {
        $oldPaymentStatus = 'unpaid';
    }

    $resolvedStatus = $nextStatus;
    $resolvedPaymentStatus = $oldPaymentStatus;

    if (
        strcasecmp($resolvedStatus, 'Delivered') === 0
        && orders_api_is_cod_payment_method((string)($order['payment_method'] ?? ''))
        && $resolvedPaymentStatus === 'unpaid'
    ) {
        $resolvedPaymentStatus = 'paid';
    }

    $statusChanged = $oldStatus !== $resolvedStatus;
    $paymentStatusChanged = $oldPaymentStatus !== $resolvedPaymentStatus;

    if ($statusChanged || $paymentStatusChanged) {
        $updateStmt = $con->prepare(
            'UPDATE orders
             SET status = ?,
                 payment_status = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateStmt) {
            $con->rollback();
            orders_api_json(['ok' => false, 'message' => 'Unable to update order status.'], 500);
        }

        $updateStmt->bind_param('ssi', $resolvedStatus, $resolvedPaymentStatus, $orderId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            $con->rollback();
            orders_api_json(['ok' => false, 'message' => 'Unable to update order status.'], 500);
        }

        $order['status'] = $resolvedStatus;
        $order['payment_status'] = $resolvedPaymentStatus;
    }

    if (!$con->commit()) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Unable to finalize order status update.'], 500);
    }

    if ($statusChanged) {
        commerza_notify_order_status_change($con, $order, $oldStatus, $resolvedStatus);
        admin_api_log_security_event($con, $admin, 'order.status_change', 'info', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'old_status' => $oldStatus,
            'new_status' => $resolvedStatus,
        ]);
    }

    $payload = $returnFullPayload
        ? orders_api_summary_payload($con)
        : [
            'order' => [
                'db_id' => $orderId,
                'orderId' => (string)($order['order_number'] ?? $orderNumber),
                'status' => (string)($order['status'] ?? $resolvedStatus),
                'paymentStatus' => (string)($order['payment_status'] ?? 'unpaid'),
            ],
            'metrics' => orders_api_fetch_metrics($con),
        ];

    $message = 'Order is already in the selected status.';
    if ($statusChanged) {
        $message = 'Order status updated.';
    } elseif ($paymentStatusChanged) {
        $message = 'Order payment status synced for COD delivery.';
    }

    orders_api_json([
        'ok' => true,
        'message' => $message,
        'payload' => $payload,
    ]);
}

if ($action === 'delete-orders') {
    orders_api_require_any_permission($admin, ['orders.manage']);

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

    if ($deleted > 0) {
        admin_api_log_security_event($con, $admin, 'order.bulk_delete', 'warning', [
            'deleted_count' => $deleted,
            'requested_count' => count($orderNumbers),
            'order_numbers' => array_slice($orderNumbers, 0, 50),
        ]);
    }

    orders_api_json([
        'ok' => true,
        'message' => $deleted > 0
            ? 'Selected orders deleted successfully.'
            : 'No matching orders were deleted.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'delete-customers') {
    orders_api_require_any_permission($admin, ['orders.manage', 'customers.manage']);

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

    if ($deleted > 0 || $failed > 0) {
        admin_api_log_security_event($con, $admin, 'customer.bulk_delete', $failed > 0 ? 'warning' : 'info', [
            'deleted_count' => $deleted,
            'failed_count' => $failed,
            'requested_count' => count($ids),
            'customer_ids' => array_slice($ids, 0, 50),
        ]);
    }

    orders_api_json([
        'ok' => true,
        'message' => $message,
        'payload' => orders_api_summary_payload($con),
    ]);
}

if ($action === 'update-refund-status') {
    orders_api_require_any_permission($admin, ['orders.manage']);

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

    if (!$con->begin_transaction()) {
        orders_api_json(['ok' => false, 'message' => 'Unable to lock refund request.'], 500);
    }

    $stmt = $con->prepare(
        'SELECT r.id, r.order_id, o.order_number, o.customer_name, o.customer_email
         FROM refund_requests r
         INNER JOIN orders o ON o.id = r.order_id
         WHERE r.id = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$stmt) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Unable to load refund request.'], 500);
    }

    $stmt->bind_param('i', $refundId);
    $stmt->execute();
    $result = $stmt->get_result();
    $refundRow = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$refundRow) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Refund request not found.'], 404);
    }

    $updateStmt = $con->prepare(
        'UPDATE refund_requests
         SET status = ?, admin_note = ?
         WHERE id = ?
         LIMIT 1'
    );

    if (!$updateStmt) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Unable to update refund request.'], 500);
    }

    $updateStmt->bind_param('ssi', $status, $adminNote, $refundId);
    $ok = $updateStmt->execute();
    $updateStmt->close();

    if (!$ok) {
        $con->rollback();
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
            $con->rollback();
            orders_api_json(['ok' => false, 'message' => 'Unable to sync refund status with order.'], 500);
        }

        $orderUpdateStmt->bind_param('i', $orderId);
        $orderUpdated = $orderUpdateStmt->execute();
        $orderUpdateStmt->close();

        if (!$orderUpdated) {
            $con->rollback();
            orders_api_json(['ok' => false, 'message' => 'Unable to sync refund status with order.'], 500);
        }
    }

    if (!$con->commit()) {
        $con->rollback();
        orders_api_json(['ok' => false, 'message' => 'Unable to finalize refund update.'], 500);
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

    admin_api_log_security_event($con, $admin, 'refund.status_change', 'info', [
        'refund_id' => $refundId,
        'order_id' => (int)($refundRow['order_id'] ?? 0),
        'order_number' => (string)($refundRow['order_number'] ?? ''),
        'status' => $status,
        'admin_note_length' => strlen($adminNote),
    ]);

    orders_api_json([
        'ok' => true,
        'message' => 'Refund request updated.',
        'payload' => orders_api_summary_payload($con),
    ]);
}

orders_api_json(['ok' => false, 'message' => 'Invalid action.'], 400);
