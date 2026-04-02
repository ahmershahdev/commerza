<?php

require_once __DIR__ . '/data.php';

function commerza_coupon_has_order_column(mysqli $con, string $column): bool
{
    $column = trim($column);
    if ($column === '' || preg_match('/^[a-zA-Z0-9_]+$/', $column) !== 1) {
        return false;
    }

    $escaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM orders LIKE '{$escaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_ensure_coupon_schema(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS coupons (
            id INT NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            title VARCHAR(120) DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            discount_type ENUM("percent", "fixed") NOT NULL DEFAULT "fixed",
            discount_value DECIMAL(10,2) NOT NULL,
            min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_discount DECIMAL(10,2) DEFAULT NULL,
            usage_limit INT DEFAULT NULL,
            per_user_limit INT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coupon_code (code),
            KEY idx_coupon_active_expiry (is_active, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $con->query(
        'CREATE TABLE IF NOT EXISTS coupon_redemptions (
            id INT NOT NULL AUTO_INCREMENT,
            coupon_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            order_id INT DEFAULT NULL,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coupon_order (coupon_id, order_id),
            KEY idx_coupon_user (coupon_id, user_id),
            KEY idx_coupon_used_at (used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $missingColumns = [
        'coupon_code' => 'VARCHAR(50) DEFAULT NULL',
        'discount_total' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    ];

    foreach ($missingColumns as $column => $definition) {
        if (!commerza_coupon_has_order_column($con, $column)) {
            $con->query("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
        }
    }

    $initialized = true;
}

function commerza_coupon_normalize_code(string $code): string
{
    $clean = strtoupper(trim($code));
    return preg_replace('/[^A-Z0-9_-]/', '', $clean) ?? '';
}

function commerza_coupon_session_code(): string
{
    $raw = (string)($_SESSION['cart_coupon_code'] ?? '');
    return commerza_coupon_normalize_code($raw);
}

function commerza_coupon_set_session_code(string $code): void
{
    $normalized = commerza_coupon_normalize_code($code);
    if ($normalized === '') {
        unset($_SESSION['cart_coupon_code']);
        return;
    }

    $_SESSION['cart_coupon_code'] = $normalized;
}

function commerza_coupon_clear_session_code(): void
{
    unset($_SESSION['cart_coupon_code']);
}

function commerza_coupon_fetch_by_code(mysqli $con, string $code): ?array
{
    $normalized = commerza_coupon_normalize_code($code);
    if ($normalized === '') {
        return null;
    }

    $stmt = $con->prepare(
        'SELECT
            id,
            code,
            title,
            description,
            discount_type,
            discount_value,
            min_order,
            max_discount,
            usage_limit,
            per_user_limit,
            expires_at,
            is_active
         FROM coupons
         WHERE code = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $normalized);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

function commerza_coupon_usage_count(mysqli $con, int $couponId): int
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS total
         FROM coupon_redemptions
         WHERE coupon_id = ?'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $couponId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)($row['total'] ?? 0) : 0;
}

function commerza_coupon_user_usage_count(mysqli $con, int $couponId, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $con->prepare(
        'SELECT COUNT(*) AS total
         FROM coupon_redemptions
         WHERE coupon_id = ? AND user_id = ?'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('ii', $couponId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)($row['total'] ?? 0) : 0;
}

function commerza_coupon_discount_amount(array $coupon, float $subtotal): float
{
    $subtotal = max(0.0, (float)$subtotal);
    if ($subtotal <= 0) {
        return 0.0;
    }

    $discountType = (string)($coupon['discount_type'] ?? 'fixed');
    $discountValue = (float)($coupon['discount_value'] ?? 0);

    if ($discountValue <= 0) {
        return 0.0;
    }

    if ($discountType === 'percent') {
        $discount = $subtotal * ($discountValue / 100);

        $maxDiscount = $coupon['max_discount'] !== null
            ? (float)$coupon['max_discount']
            : null;

        if ($maxDiscount !== null && $maxDiscount > 0) {
            $discount = min($discount, $maxDiscount);
        }
    } else {
        $discount = $discountValue;
    }

    $discount = min($discount, $subtotal);
    return round(max(0, $discount), 2);
}

function commerza_coupon_validate(mysqli $con, array $coupon, float $subtotal, int $userId): array
{
    if ((int)($coupon['is_active'] ?? 0) !== 1) {
        return [
            'ok' => false,
            'message' => 'This coupon is inactive.',
        ];
    }

    $expiresAt = trim((string)($coupon['expires_at'] ?? ''));
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
        return [
            'ok' => false,
            'message' => 'This coupon has expired.',
        ];
    }

    $minOrder = (float)($coupon['min_order'] ?? 0);
    if ($subtotal < $minOrder) {
        return [
            'ok' => false,
            'message' => 'Order total must be at least PKR ' . number_format($minOrder, 2) . ' for this coupon.',
        ];
    }

    $couponId = (int)($coupon['id'] ?? 0);
    if ($couponId <= 0) {
        return [
            'ok' => false,
            'message' => 'This coupon is invalid.',
        ];
    }

    $usageLimit = (int)($coupon['usage_limit'] ?? 0);
    if ($usageLimit > 0 && commerza_coupon_usage_count($con, $couponId) >= $usageLimit) {
        return [
            'ok' => false,
            'message' => 'This coupon has reached its usage limit.',
        ];
    }

    $perUserLimit = (int)($coupon['per_user_limit'] ?? 0);
    if ($perUserLimit > 0 && $userId > 0) {
        $userUsage = commerza_coupon_user_usage_count($con, $couponId, $userId);
        if ($userUsage >= $perUserLimit) {
            return [
                'ok' => false,
                'message' => 'You have already used this coupon the maximum number of times.',
            ];
        }
    }

    $discount = commerza_coupon_discount_amount($coupon, $subtotal);
    if ($discount <= 0) {
        return [
            'ok' => false,
            'message' => 'This coupon does not apply to your order.',
        ];
    }

    return [
        'ok' => true,
        'discount' => $discount,
        'message' => 'Coupon applied successfully.',
    ];
}

function commerza_coupon_get_state(mysqli $con, float $subtotal, int $userId, ?string $codeOverride = null): array
{
    commerza_ensure_coupon_schema($con);

    $rawCode = $codeOverride !== null ? $codeOverride : commerza_coupon_session_code();
    $code = commerza_coupon_normalize_code((string)$rawCode);

    if ($code === '') {
        return [
            'ok' => false,
            'code' => '',
            'coupon_id' => 0,
            'discount' => 0.0,
            'message' => '',
            'coupon' => null,
        ];
    }

    $coupon = commerza_coupon_fetch_by_code($con, $code);
    if (!$coupon) {
        if ($codeOverride === null) {
            commerza_coupon_clear_session_code();
        }

        return [
            'ok' => false,
            'code' => $code,
            'coupon_id' => 0,
            'discount' => 0.0,
            'message' => 'Coupon code was not found.',
            'coupon' => null,
        ];
    }

    $validation = commerza_coupon_validate($con, $coupon, $subtotal, $userId);
    if (!(bool)($validation['ok'] ?? false)) {
        if ($codeOverride === null) {
            commerza_coupon_clear_session_code();
        }

        return [
            'ok' => false,
            'code' => (string)$coupon['code'],
            'coupon_id' => (int)$coupon['id'],
            'discount' => 0.0,
            'message' => (string)($validation['message'] ?? 'Coupon could not be applied.'),
            'coupon' => $coupon,
        ];
    }

    $discount = (float)($validation['discount'] ?? 0);

    return [
        'ok' => true,
        'code' => (string)$coupon['code'],
        'coupon_id' => (int)$coupon['id'],
        'discount' => $discount,
        'message' => (string)($validation['message'] ?? 'Coupon applied successfully.'),
        'coupon' => $coupon,
    ];
}

function commerza_coupon_resolve_checkout_coupon(mysqli $con, float $subtotal, int $userId, string $requestedCode = ''): array
{
    $requestedCode = commerza_coupon_normalize_code($requestedCode);

    if ($requestedCode !== '') {
        $state = commerza_coupon_get_state($con, $subtotal, $userId, $requestedCode);
        if ((bool)($state['ok'] ?? false)) {
            commerza_coupon_set_session_code($requestedCode);
        }

        return $state;
    }

    return commerza_coupon_get_state($con, $subtotal, $userId, null);
}

function commerza_coupon_register_redemption(mysqli $con, int $couponId, int $userId, int $orderId, float $discountAmount): bool
{
    if ($couponId <= 0 || $orderId <= 0 || $discountAmount <= 0) {
        return false;
    }

    commerza_ensure_coupon_schema($con);

    $userParam = $userId > 0 ? $userId : null;
    $discountAmount = round(max(0, $discountAmount), 2);

    $stmt = $con->prepare(
        'INSERT INTO coupon_redemptions (coupon_id, user_id, order_id, discount_amount)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            discount_amount = VALUES(discount_amount),
            used_at = CURRENT_TIMESTAMP'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iiid', $couponId, $userParam, $orderId, $discountAmount);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function commerza_coupon_format_payload(array $state): ?array
{
    if (!(bool)($state['ok'] ?? false)) {
        return null;
    }

    $coupon = is_array($state['coupon'] ?? null) ? $state['coupon'] : [];

    return [
        'id' => (int)($state['coupon_id'] ?? 0),
        'code' => (string)($state['code'] ?? ''),
        'title' => (string)($coupon['title'] ?? ''),
        'discount' => (float)($state['discount'] ?? 0),
        'discount_type' => (string)($coupon['discount_type'] ?? 'fixed'),
        'discount_value' => (float)($coupon['discount_value'] ?? 0),
        'min_order' => (float)($coupon['min_order'] ?? 0),
        'max_discount' => $coupon['max_discount'] !== null ? (float)$coupon['max_discount'] : null,
        'expires_at' => (string)($coupon['expires_at'] ?? ''),
    ];
}
