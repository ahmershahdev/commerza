<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../backend/coupon_helpers.php';
require_once __DIR__ . '/../../backend/notifications.php';

/** @var mysqli|null $con */
$con = (isset($con) && $con instanceof mysqli)
    ? $con
    : (($GLOBALS['con'] ?? null) instanceof mysqli ? $GLOBALS['con'] : null);

if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
    exit;
}

function coupons_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function coupons_api_request_body(): array
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

function coupons_api_action(): string
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET' && isset($_GET['action'])) {
        return strtolower(trim((string)$_GET['action']));
    }

    if ($method === 'POST' && isset($_POST['action'])) {
        return strtolower(trim((string)$_POST['action']));
    }

    $body = coupons_api_request_body();
    return strtolower(trim((string)($body['action'] ?? 'list')));
}

function coupons_api_csrf_from_request(): string
{
    $headerToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    if (isset($_POST['csrf_token'])) {
        return trim((string)$_POST['csrf_token']);
    }

    $body = coupons_api_request_body();
    return trim((string)($body['csrf_token'] ?? ''));
}

function coupons_api_require_csrf(): void
{
    $token = coupons_api_csrf_from_request();
    if (!admin_validate_csrf_token($token)) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Forbidden.',
        ], 403);
    }
}

function coupons_api_format_discount_label(array $coupon): string
{
    $discountType = (string)($coupon['discount_type'] ?? 'fixed');
    $discountValue = (float)($coupon['discount_value'] ?? 0);

    if ($discountType === 'percent') {
        return number_format($discountValue, 2) . '% off';
    }

    return 'PKR ' . number_format($discountValue, 2) . ' off';
}

function coupons_api_row_payload(array $row): array
{
    $isActive = (int)($row['is_active'] ?? 0) === 1;
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    $maxDiscount = (float)($row['max_discount'] ?? 0);
    $usageLimit = (int)($row['usage_limit'] ?? 0);
    $perUserLimit = (int)($row['per_user_limit'] ?? 0);

    return [
        'id' => (int)($row['id'] ?? 0),
        'code' => (string)($row['code'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'discountType' => (string)($row['discount_type'] ?? 'fixed'),
        'discountValue' => (float)($row['discount_value'] ?? 0),
        'discountLabel' => coupons_api_format_discount_label($row),
        'minOrder' => (float)($row['min_order'] ?? 0),
        'maxDiscount' => $maxDiscount > 0 ? $maxDiscount : null,
        'usageLimit' => $usageLimit > 0 ? $usageLimit : null,
        'perUserLimit' => $perUserLimit > 0 ? $perUserLimit : null,
        'usedCount' => (int)($row['used_count'] ?? 0),
        'expiresAt' => $expiresAt,
        'isExpired' => $expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time(),
        'isActive' => $isActive,
        'createdAt' => (string)($row['created_at'] ?? ''),
        'updatedAt' => (string)($row['updated_at'] ?? ''),
    ];
}

function coupons_api_fetch_all(mysqli $con): array
{
    $rows = [];

    $result = $con->query(
        'SELECT
            c.id,
            c.code,
            c.title,
            c.description,
            c.discount_type,
            c.discount_value,
            c.min_order,
            c.max_discount,
            c.usage_limit,
            c.per_user_limit,
            c.expires_at,
            c.is_active,
            c.created_at,
            c.updated_at,
            COALESCE(u.used_count, 0) AS used_count
         FROM coupons c
         LEFT JOIN (
            SELECT coupon_id, COUNT(*) AS used_count
            FROM coupon_redemptions
            GROUP BY coupon_id
         ) u ON u.coupon_id = c.id
         ORDER BY c.created_at DESC, c.id DESC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = coupons_api_row_payload($row);
    }

    return $rows;
}

function coupons_api_parse_recipients($raw): array
{
    $emails = [];

    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/[\s,;]+/', (string)$raw) ?: [];
    }

    foreach ($parts as $candidate) {
        $email = strtolower(trim((string)$candidate));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $emails[$email] = true;
    }

    return array_keys($emails);
}

function coupons_api_placeholder_map(array $coupon): array
{
    $expiresAt = trim((string)($coupon['expires_at'] ?? ''));
    $readableExpiry = $expiresAt !== '' ? date('d M Y H:i', strtotime($expiresAt)) : 'No expiry';

    return [
        '{{code}}' => (string)($coupon['code'] ?? ''),
        '{{title}}' => (string)($coupon['title'] ?? 'Commerza offer'),
        '{{discount}}' => coupons_api_format_discount_label($coupon),
        '{{min_order}}' => 'PKR ' . number_format((float)($coupon['min_order'] ?? 0), 2),
        '{{expires_at}}' => $readableExpiry,
    ];
}

function coupons_api_apply_placeholders(string $content, array $coupon): string
{
    $map = coupons_api_placeholder_map($coupon);
    return strtr($content, $map);
}

function coupons_api_load_coupon_by_id(mysqli $con, int $couponId): ?array
{
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
            is_active,
            created_at,
            updated_at
         FROM coupons
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $couponId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

function coupons_api_boolean_value($value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_numeric($value)) {
        return ((int)$value) === 1 ? 1 : 0;
    }

    $text = strtolower(trim((string)$value));
    return in_array($text, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'coupons.manage');
commerza_ensure_coupon_schema($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = coupons_api_action();

if ($method === 'GET') {
    if ($action !== 'list') {
        coupons_api_json([
            'ok' => false,
            'message' => 'Invalid action.',
        ], 400);
    }

    coupons_api_json([
        'ok' => true,
        'payload' => [
            'coupons' => coupons_api_fetch_all($con),
        ],
    ]);
}

if ($method !== 'POST') {
    coupons_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

coupons_api_require_csrf();
$body = coupons_api_request_body();

if ($action === 'save-coupon') {
    $couponId = (int)($body['id'] ?? 0);
    $code = commerza_coupon_normalize_code((string)($body['code'] ?? ''));
    $title = trim((string)($body['title'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $discountType = strtolower(trim((string)($body['discount_type'] ?? 'fixed')));
    $discountValue = round((float)($body['discount_value'] ?? 0), 2);
    $minOrder = round(max(0, (float)($body['min_order'] ?? 0)), 2);
    $maxDiscountRaw = $body['max_discount'] ?? null;
    $usageLimitRaw = $body['usage_limit'] ?? null;
    $perUserLimitRaw = $body['per_user_limit'] ?? null;
    $expiresRaw = trim((string)($body['expires_at'] ?? ''));
    $isActive = coupons_api_boolean_value($body['is_active'] ?? 1);

    if ($code === '' || strlen($code) < 3 || strlen($code) > 50) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Coupon code must be 3 to 50 characters.',
        ], 422);
    }

    if (!in_array($discountType, ['percent', 'fixed'], true)) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Discount type must be percent or fixed.',
        ], 422);
    }

    if ($discountValue <= 0) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Discount value must be greater than zero.',
        ], 422);
    }

    if ($discountType === 'percent' && $discountValue > 95) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Percent discount cannot be more than 95%.',
        ], 422);
    }

    $maxDiscount = null;
    if ($maxDiscountRaw !== null && $maxDiscountRaw !== '') {
        $parsedMax = round((float)$maxDiscountRaw, 2);
        if ($parsedMax <= 0) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Max discount must be greater than zero.',
            ], 422);
        }
        $maxDiscount = $parsedMax;
    }

    if ($discountType === 'fixed') {
        $maxDiscount = null;
    }

    $usageLimit = null;
    if ($usageLimitRaw !== null && $usageLimitRaw !== '') {
        $parsedUsage = (int)$usageLimitRaw;
        if ($parsedUsage < 0) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Usage limit cannot be negative.',
            ], 422);
        }
        if ($parsedUsage > 0) {
            $usageLimit = $parsedUsage;
        }
    }

    $perUserLimit = null;
    if ($perUserLimitRaw !== null && $perUserLimitRaw !== '') {
        $parsedPerUser = (int)$perUserLimitRaw;
        if ($parsedPerUser < 0) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Per-user limit cannot be negative.',
            ], 422);
        }
        if ($parsedPerUser > 0) {
            $perUserLimit = $parsedPerUser;
        }
    }

    $expiresAt = null;
    if ($expiresRaw !== '') {
        $timestamp = strtotime($expiresRaw);
        if ($timestamp === false) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Invalid expiry date format.',
            ], 422);
        }

        $expiresAt = date('Y-m-d H:i:s', $timestamp);
    }

    if (strlen($title) > 120) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Title can be up to 120 characters.',
        ], 422);
    }

    if (strlen($description) > 255) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Description can be up to 255 characters.',
        ], 422);
    }

    if ($couponId > 0) {
        $stmt = $con->prepare(
            'UPDATE coupons
             SET
                code = ?,
                title = ?,
                description = ?,
                discount_type = ?,
                discount_value = ?,
                min_order = ?,
                max_discount = ?,
                usage_limit = ?,
                per_user_limit = ?,
                expires_at = ?,
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
             LIMIT 1'
        );

        if (!$stmt) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Unable to update coupon.',
            ], 500);
        }

        $stmt->bind_param(
            'ssssdddiisii',
            $code,
            $title,
            $description,
            $discountType,
            $discountValue,
            $minOrder,
            $maxDiscount,
            $usageLimit,
            $perUserLimit,
            $expiresAt,
            $isActive,
            $couponId
        );

        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            coupons_api_json([
                'ok' => false,
                'message' => 'Coupon code already exists or update failed.',
            ], 409);
        }

        coupons_api_json([
            'ok' => true,
            'message' => 'Coupon updated successfully.',
            'payload' => [
                'coupons' => coupons_api_fetch_all($con),
            ],
        ]);
    }

    $createdBy = (int)($admin['id'] ?? 0);

    $stmt = $con->prepare(
        'INSERT INTO coupons (
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
            is_active,
            created_by
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Unable to create coupon.',
        ], 500);
    }

    $stmt->bind_param(
        'ssssdddiisii',
        $code,
        $title,
        $description,
        $discountType,
        $discountValue,
        $minOrder,
        $maxDiscount,
        $usageLimit,
        $perUserLimit,
        $expiresAt,
        $isActive,
        $createdBy
    );

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Coupon code already exists or create failed.',
        ], 409);
    }

    coupons_api_json([
        'ok' => true,
        'message' => 'Coupon created successfully.',
        'payload' => [
            'coupons' => coupons_api_fetch_all($con),
        ],
    ]);
}

if ($action === 'delete-coupon') {
    $couponId = (int)($body['id'] ?? 0);
    if ($couponId <= 0) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Invalid coupon id.',
        ], 422);
    }

    $con->begin_transaction();

    try {
        $deleteUsageStmt = $con->prepare('DELETE FROM coupon_redemptions WHERE coupon_id = ?');
        if ($deleteUsageStmt) {
            $deleteUsageStmt->bind_param('i', $couponId);
            $deleteUsageStmt->execute();
            $deleteUsageStmt->close();
        }

        $deleteCouponStmt = $con->prepare('DELETE FROM coupons WHERE id = ? LIMIT 1');
        if (!$deleteCouponStmt) {
            throw new RuntimeException('Unable to delete coupon.');
        }

        $deleteCouponStmt->bind_param('i', $couponId);
        $deleteCouponStmt->execute();
        $affected = $deleteCouponStmt->affected_rows;
        $deleteCouponStmt->close();

        if ($affected !== 1) {
            throw new RuntimeException('Coupon not found.');
        }

        $con->commit();
    } catch (Throwable $error) {
        $con->rollback();
        coupons_api_json([
            'ok' => false,
            'message' => $error->getMessage() ?: 'Unable to delete coupon.',
        ], 500);
    }

    coupons_api_json([
        'ok' => true,
        'message' => 'Coupon deleted successfully.',
        'payload' => [
            'coupons' => coupons_api_fetch_all($con),
        ],
    ]);
}

if ($action === 'send-coupon-email') {
    $couponId = (int)($body['coupon_id'] ?? 0);
    $rawRecipients = $body['recipients'] ?? [];
    $subject = trim((string)($body['subject'] ?? ''));
    $message = trim((string)($body['message'] ?? ''));

    if ($couponId <= 0) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Please select a valid coupon.',
        ], 422);
    }

    $coupon = coupons_api_load_coupon_by_id($con, $couponId);
    if (!$coupon) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Coupon was not found.',
        ], 404);
    }

    $recipients = coupons_api_parse_recipients($rawRecipients);
    if (count($recipients) === 0) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Please add at least one valid recipient email.',
        ], 422);
    }

    if (count($recipients) > 250) {
        coupons_api_json([
            'ok' => false,
            'message' => 'Please send to at most 250 recipients at once.',
        ], 422);
    }

    $defaultSubject = 'Your Commerza coupon code: ' . (string)($coupon['code'] ?? '');
    if ($subject === '') {
        $subject = $defaultSubject;
    }

    $defaultMessage = "Hi there,\n\nUse code {{code}} to get {{discount}} on your next order.\nMinimum order: {{min_order}}\nExpires: {{expires_at}}\n\nThanks for shopping with Commerza.";
    $composedMessage = coupons_api_apply_placeholders($message !== '' ? $message : $defaultMessage, $coupon);

    $safeMessage = nl2br(htmlspecialchars($composedMessage, ENT_QUOTES, 'UTF-8'));

    $bodyHtml =
        '<p style="margin:0 0 12px 0;">Use this coupon at checkout:</p>' .
        '<div style="margin:0 0 14px 0;padding:12px 14px;background:#101010;border:1px dashed #ff6a00;border-radius:8px;display:inline-block;">' .
        '<span style="font-size:22px;letter-spacing:2px;font-weight:700;color:#ffcc00;">' . htmlspecialchars((string)$coupon['code'], ENT_QUOTES, 'UTF-8') . '</span>' .
        '</div>' .
        '<p style="margin:0 0 12px 0;"><strong>Offer:</strong> ' . htmlspecialchars(coupons_api_format_discount_label($coupon), ENT_QUOTES, 'UTF-8') . '</p>' .
        '<p style="margin:0 0 12px 0;"><strong>Minimum order:</strong> PKR ' . number_format((float)($coupon['min_order'] ?? 0), 2) . '</p>' .
        '<p style="margin:0 0 12px 0;"><strong>Expires:</strong> ' . htmlspecialchars((string)($coupon['expires_at'] ?? 'No expiry'), ENT_QUOTES, 'UTF-8') . '</p>' .
        '<hr style="border:none;border-top:1px solid #2a2a2a;margin:16px 0;">' .
        '<p style="margin:0;">' . $safeMessage . '</p>';

    $sent = 0;
    $failed = 0;
    $lastError = '';

    foreach ($recipients as $recipient) {
        $errorMessage = null;
        $ok = commerza_notifications_send(
            $con,
            $recipient,
            $subject,
            'Exclusive Coupon Offer',
            'A special offer from Commerza is waiting for you.',
            $bodyHtml,
            $errorMessage
        );

        if ($ok) {
            $sent++;
        } else {
            $failed++;
            $lastError = $errorMessage ? (string)$errorMessage : 'Failed to send one or more emails.';
        }
    }

    coupons_api_json([
        'ok' => $sent > 0,
        'message' => $sent > 0
            ? 'Coupon email sent to ' . $sent . ' recipient(s).' . ($failed > 0 ? ' Failed: ' . $failed . '.' : '')
            : ($lastError !== '' ? $lastError : 'No email was sent.'),
        'payload' => [
            'sent' => $sent,
            'failed' => $failed,
            'coupons' => coupons_api_fetch_all($con),
        ],
    ], $sent > 0 ? 200 : 500);
}

coupons_api_json([
    'ok' => false,
    'message' => 'Invalid action.',
], 400);
