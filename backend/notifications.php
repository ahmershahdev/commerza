<?php

require_once __DIR__ . '/mailer.php';

function commerza_notifications_get_setting(mysqli $con, string $key, string $fallback = ''): string
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

function commerza_notifications_get_site_name(mysqli $con): string
{
    return commerza_notifications_get_setting($con, 'site_name', 'Commerza');
}

function commerza_notifications_get_from_email(mysqli $con): string
{
    $siteEmail = commerza_notifications_get_setting($con, 'site_email', '');
    if (filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
        return $siteEmail;
    }

    return 'no-reply@commerza.ahmershah.dev';
}

function commerza_notifications_get_admin_email(mysqli $con): string
{
    $result = $con->query(
        'SELECT email
         FROM admin_users
         WHERE is_active = 1
         ORDER BY id ASC
         LIMIT 1'
    );

    if ($result) {
        $row = $result->fetch_assoc();
        $candidate = trim((string)($row['email'] ?? ''));
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }

    $fallback = commerza_notifications_get_setting($con, 'site_email', '');
    return filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : '';
}

function commerza_notifications_public_url(string $path = ''): string
{
    $configuredBase = trim((string)(getenv('COMMERZA_PUBLIC_URL') ?: getenv('APP_URL') ?: ''));
    if ($configuredBase !== '' && filter_var($configuredBase, FILTER_VALIDATE_URL)) {
        $base = rtrim($configuredBase, '/');
    } else {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '') {
            $host = 'localhost';
        }

        $base = $scheme . '://' . $host;
    }

    if ($path === '') {
        return $base;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return $base . $path;
}

function commerza_notifications_layout(string $title, string $intro, string $bodyHtml, string $siteName): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html>
<html>
  <body style="margin:0;padding:0;background:#0a0a0a;font-family:Arial,sans-serif;color:#ececec;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0a0a0a;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#151515;border:1px solid #2a2a2a;border-radius:12px;overflow:hidden;">
            <tr>
              <td style="padding:24px 28px 10px 28px;">
                <h1 style="margin:0;color:#ff6a00;font-size:24px;letter-spacing:0.5px;">' . $safeTitle . '</h1>
              </td>
            </tr>
            <tr>
              <td style="padding:8px 28px 0 28px;">
                <p style="margin:0;color:#d0d0d0;line-height:1.6;">' . $safeIntro . '</p>
              </td>
            </tr>
            <tr>
              <td style="padding:20px 28px 28px 28px;color:#f2f2f2;line-height:1.6;">' . $bodyHtml . '</td>
            </tr>
            <tr>
              <td style="padding:14px 28px;background:#101010;border-top:1px solid #2a2a2a;">
                <p style="margin:0;color:#9f9f9f;font-size:12px;">Sent by ' . $safeSiteName . ' notifications</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';
}

function commerza_notifications_send(
    mysqli $con,
    string $toEmail,
    string $subject,
    string $title,
    string $intro,
    string $bodyHtml,
    ?string &$errorMessage = null
): bool {
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid recipient email.';
        return false;
    }

    $siteName = commerza_notifications_get_site_name($con);
    $fromEmail = commerza_notifications_get_from_email($con);
    $fromName = $siteName . ' Notifications';

    $html = commerza_notifications_layout($title, $intro, $bodyHtml, $siteName);

    return commerza_send_html_mail(
        $toEmail,
        $subject,
        $html,
        $fromEmail,
        $fromName,
        $errorMessage
    );
}

function commerza_notify_user_login(mysqli $con, int $userId, string $userEmail, string $userName, string $ipAddress): bool
{
    $safeName = htmlspecialchars(trim($userName) !== '' ? $userName : 'Customer', ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars($ipAddress !== '' ? $ipAddress : 'Unknown', ENT_QUOTES, 'UTF-8');
    $safeUserId = htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars(date('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>We detected a new login to your account.</p>' .
        '<ul style="padding-left:20px;margin:12px 0;">' .
        '<li><strong>User ID:</strong> ' . $safeUserId . '</li>' .
        '<li><strong>IP Address:</strong> ' . $safeIp . '</li>' .
        '<li><strong>Time:</strong> ' . $safeTime . '</li>' .
        '</ul>' .
        '<p>If this was not you, reset your password immediately.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $userEmail,
        'Commerza login alert',
        'New Account Login',
        'Your account was accessed successfully.',
        $body,
        $error
    );
}

function commerza_notify_admin_login(mysqli $con, string $adminEmail, string $adminName, string $ipAddress): bool
{
    $safeName = htmlspecialchars(trim($adminName) !== '' ? $adminName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars($ipAddress !== '' ? $ipAddress : 'Unknown', ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars(date('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>An admin login was recorded on your panel.</p>' .
        '<ul style="padding-left:20px;margin:12px 0;">' .
        '<li><strong>IP Address:</strong> ' . $safeIp . '</li>' .
        '<li><strong>Time:</strong> ' . $safeTime . '</li>' .
        '</ul>' .
        '<p>If this was not expected, rotate credentials immediately.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $adminEmail,
        'Commerza admin login alert',
        'Admin Login Detected',
        'An admin account has signed in.',
        $body,
        $error
    );
}

function commerza_order_items_html(array $items): string
{
    if (empty($items)) {
        return '<p style="margin:0;">No line items available.</p>';
    }

    $rows = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = htmlspecialchars((string)($item['name'] ?? $item['product_name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $quantity = (int)($item['quantity'] ?? 1);
        $lineTotal = (float)($item['line_total'] ?? $item['lineTotal'] ?? 0);

        $rows[] = '<tr>' .
            '<td style="padding:8px 10px;border-bottom:1px solid #2a2a2a;">' . $name . '</td>' .
            '<td style="padding:8px 10px;border-bottom:1px solid #2a2a2a;text-align:center;">' . $quantity . '</td>' .
            '<td style="padding:8px 10px;border-bottom:1px solid #2a2a2a;text-align:right;">PKR ' . number_format($lineTotal, 2) . '</td>' .
            '</tr>';
    }

    if (empty($rows)) {
        return '<p style="margin:0;">No line items available.</p>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#101010;border:1px solid #2a2a2a;border-radius:8px;overflow:hidden;">' .
        '<thead>' .
        '<tr>' .
        '<th style="padding:10px;text-align:left;color:#ffb366;border-bottom:1px solid #2a2a2a;">Item</th>' .
        '<th style="padding:10px;text-align:center;color:#ffb366;border-bottom:1px solid #2a2a2a;">Qty</th>' .
        '<th style="padding:10px;text-align:right;color:#ffb366;border-bottom:1px solid #2a2a2a;">Total</th>' .
        '</tr>' .
        '</thead>' .
        '<tbody>' . implode('', $rows) . '</tbody>' .
        '</table>';
}

function commerza_notify_order_placed(mysqli $con, array $order, array $items = []): void
{
    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $paymentMethod = htmlspecialchars((string)($order['payment_method'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $total = (float)($order['grand_total'] ?? 0);
    $status = htmlspecialchars((string)($order['status'] ?? 'Pending'), ENT_QUOTES, 'UTF-8');

    $summary =
        '<p><strong>Order Number:</strong> ' . $orderNumber . '</p>' .
        '<p><strong>Status:</strong> ' . $status . '</p>' .
        '<p><strong>Payment Method:</strong> ' . $paymentMethod . '</p>' .
        '<p><strong>Order Total:</strong> PKR ' . number_format($total, 2) . '</p>';

    $itemsHtml = commerza_order_items_html($items);

    $userEmail = trim((string)($order['customer_email'] ?? ''));
    if (filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $userBody =
            '<p>Hello ' . $customerName . ',</p>' .
            '<p>Your order has been placed successfully.</p>' .
            $summary .
            '<h3 style="margin:18px 0 10px 0;color:#ffb366;">Order Items</h3>' .
            $itemsHtml;

        $error = null;
        commerza_notifications_send(
            $con,
            $userEmail,
            'Commerza order confirmation',
            'Order Placed Successfully',
            'Thank you for shopping with Commerza.',
            $userBody,
            $error
        );
    }

    $adminEmail = commerza_notifications_get_admin_email($con);
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminBody =
            '<p>A new order was placed.</p>' .
            '<p><strong>Customer:</strong> ' . $customerName . '</p>' .
            '<p><strong>Email:</strong> ' . htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') . '</p>' .
            $summary .
            '<h3 style="margin:18px 0 10px 0;color:#ffb366;">Order Items</h3>' .
            $itemsHtml;

        $error = null;
        commerza_notifications_send(
            $con,
            $adminEmail,
            'Commerza new order notification',
            'New Order Received',
            'A customer has completed checkout.',
            $adminBody,
            $error
        );
    }
}

function commerza_notify_order_status_change(mysqli $con, array $order, string $oldStatus, string $newStatus): void
{
    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $customerEmail = trim((string)($order['customer_email'] ?? ''));
    $old = htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8');
    $new = htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Order <strong>' . $orderNumber . '</strong> status changed.</p>' .
        '<p><strong>From:</strong> ' . $old . '</p>' .
        '<p><strong>To:</strong> ' . $new . '</p>';

    if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $userBody =
            '<p>Hello ' . $customerName . ',</p>' .
            '<p>There is an update on your order.</p>' .
            $body;

        $error = null;
        commerza_notifications_send(
            $con,
            $customerEmail,
            'Commerza order status update',
            'Order Status Updated',
            'We have updated your order timeline.',
            $userBody,
            $error
        );
    }

    $adminEmail = commerza_notifications_get_admin_email($con);
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminBody =
            '<p>An order status was updated by admin.</p>' .
            '<p><strong>Customer:</strong> ' . $customerName . '</p>' .
            '<p><strong>Email:</strong> ' . htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8') . '</p>' .
            $body;

        $error = null;
        commerza_notifications_send(
            $con,
            $adminEmail,
            'Commerza admin order status update',
            'Order Status Changed',
            'A manual order status update occurred.',
            $adminBody,
            $error
        );
    }
}

function commerza_notify_order_shipped(mysqli $con, array $order): bool
{
    $customerEmail = trim((string)($order['customer_email'] ?? ''));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');

    $deliveryEstimateRaw = trim((string)($order['delivery_estimate'] ?? ''));
    $deliveryEstimateText = '';
    $deliveryEstimateTs = $deliveryEstimateRaw !== '' ? strtotime($deliveryEstimateRaw) : false;
    if ($deliveryEstimateTs !== false) {
        $deliveryEstimateText = date('d M Y, h:i A', $deliveryEstimateTs);
    }

    $body =
        '<p>Hello ' . $customerName . ',</p>' .
        '<p>Your order <strong>' . $orderNumber . '</strong> has been shipped.</p>' .
        ($deliveryEstimateText !== ''
            ? '<p><strong>Estimated delivery:</strong> ' . htmlspecialchars($deliveryEstimateText, ENT_QUOTES, 'UTF-8') . '</p>'
            : '<p>We will deliver your package as soon as possible.</p>') .
        '<p>You can monitor updates from your account and order tracking page.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $customerEmail,
        'Commerza order shipped',
        'Order Shipped',
        'Good news. Your order is now on the way.',
        $body,
        $error
    );
}

function commerza_notify_order_delivered(mysqli $con, array $order): bool
{
    $customerEmail = trim((string)($order['customer_email'] ?? ''));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $customerName . ',</p>' .
        '<p>Your order <strong>' . $orderNumber . '</strong> is marked as delivered.</p>' .
        '<p>Thank you for shopping with Commerza. If anything is not right, contact support right away.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $customerEmail,
        'Commerza order delivered',
        'Order Delivered',
        'Your package has arrived.',
        $body,
        $error
    );
}

function commerza_notify_review_request_after_delivery(mysqli $con, array $order, int $orderId = 0): bool
{
    $customerEmail = trim((string)($order['customer_email'] ?? ''));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $accountUrl = commerza_notifications_public_url('/account.php');
    $safeAccountUrl = htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $customerName . ',</p>' .
        '<p>We hope you are enjoying order <strong>' . $orderNumber . '</strong>.</p>' .
        '<p>Your feedback matters. Please leave a product review from your account.</p>' .
        '<p><a href="' . $safeAccountUrl . '" style="display:inline-block;padding:10px 14px;background:#ff6a00;color:#111;text-decoration:none;font-weight:700;border-radius:8px;">Write a Review</a></p>' .
        '<p style="color:#bdbdbd;font-size:13px;">This request is sent after delivery to improve product quality and service.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $customerEmail,
        'How was your Commerza order?',
        'Share Your Review',
        'Tell us about your delivered order experience.',
        $body,
        $error
    );
}

function commerza_notifications_ensure_reminder_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS engagement_reminders (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            reminder_type ENUM("cart", "wishlist") NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_engagement_user_product_type (user_id, product_id, reminder_type),
            KEY idx_engagement_pending (sent_at, last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function commerza_queue_engagement_reminder(mysqli $con, int $userId, int $productId, string $type): bool
{
    $type = strtolower(trim($type));
    if ($userId <= 0 || $productId <= 0 || !in_array($type, ['cart', 'wishlist'], true)) {
        return false;
    }

    commerza_notifications_ensure_reminder_table($con);

    $stmt = $con->prepare(
        'INSERT INTO engagement_reminders (user_id, product_id, reminder_type, created_at, last_seen_at, sent_at)
         VALUES (?, ?, ?, NOW(), NOW(), NULL)
         ON DUPLICATE KEY UPDATE
            last_seen_at = NOW(),
            sent_at = NULL'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iis', $userId, $productId, $type);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function commerza_is_item_still_saved(mysqli $con, int $userId, int $productId, string $type): bool
{
    if ($type === 'cart') {
        $stmt = $con->prepare(
            'SELECT ci.id
             FROM cart c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.user_id = ? AND ci.product_id = ?
             LIMIT 1'
        );
    } else {
        $stmt = $con->prepare(
            'SELECT wi.id
             FROM wishlist w
             INNER JOIN wishlist_items wi ON wi.wishlist_id = w.id
             WHERE w.user_id = ? AND wi.product_id = ?
             LIMIT 1'
        );
    }

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function commerza_send_pending_engagement_reminders(mysqli $con, int $olderThanMinutes = 180): array
{
    commerza_notifications_ensure_reminder_table($con);

    $olderThanMinutes = max(30, $olderThanMinutes);
    $threshold = date('Y-m-d H:i:s', time() - ($olderThanMinutes * 60));

    $stmt = $con->prepare(
        'SELECT r.id, r.user_id, r.product_id, r.reminder_type, u.full_name, u.email, p.name AS product_name
         FROM engagement_reminders r
         INNER JOIN users u ON u.id = r.user_id
         INNER JOIN products p ON p.id = r.product_id
         WHERE r.sent_at IS NULL AND r.last_seen_at <= ?
         ORDER BY r.last_seen_at ASC
         LIMIT 200'
    );

    if (!$stmt) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    }

    $stmt->bind_param('s', $threshold);
    $stmt->execute();
    $result = $stmt->get_result();

    $processed = 0;
    $sent = 0;
    $failed = 0;

    while ($row = $result ? $result->fetch_assoc() : null) {
        if (!$row) {
            break;
        }

        $processed++;
        $reminderId = (int)$row['id'];
        $userId = (int)$row['user_id'];
        $productId = (int)$row['product_id'];
        $type = (string)$row['reminder_type'];
        $userEmail = trim((string)$row['email']);

        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $failed++;
            continue;
        }

        if (!commerza_is_item_still_saved($con, $userId, $productId, $type)) {
            $markStmt = $con->prepare('UPDATE engagement_reminders SET sent_at = NOW() WHERE id = ? LIMIT 1');
            if ($markStmt) {
                $markStmt->bind_param('i', $reminderId);
                $markStmt->execute();
                $markStmt->close();
            }
            continue;
        }

        $safeName = htmlspecialchars((string)($row['full_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $safeProduct = htmlspecialchars((string)($row['product_name'] ?? 'your item'), ENT_QUOTES, 'UTF-8');
        $bucket = $type === 'cart' ? 'cart' : 'wishlist';
        $subject = $type === 'cart'
            ? 'You left an item in your Commerza cart'
            : 'Your Commerza wishlist is waiting';

        $body =
            '<p>Hello ' . $safeName . ',</p>' .
            '<p>You still have <strong>' . $safeProduct . '</strong> saved in your ' . htmlspecialchars($bucket, ENT_QUOTES, 'UTF-8') . '.</p>' .
            '<p>Return to Commerza and complete your order when you are ready.</p>';

        $error = null;
        $ok = commerza_notifications_send(
            $con,
            $userEmail,
            $subject,
            'Friendly Reminder',
            'A quick follow-up from your recent activity.',
            $body,
            $error
        );

        if ($ok) {
            $sent++;
            $markStmt = $con->prepare('UPDATE engagement_reminders SET sent_at = NOW() WHERE id = ? LIMIT 1');
            if ($markStmt) {
                $markStmt->bind_param('i', $reminderId);
                $markStmt->execute();
                $markStmt->close();
            }
        } else {
            $failed++;
        }
    }

    $stmt->close();

    return [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
    ];
}

function commerza_send_monthly_profit_email(mysqli $con, ?DateTimeImmutable $month = null): bool
{
    if ($month === null) {
        $month = new DateTimeImmutable('first day of last month 00:00:00');
    } else {
        $month = $month->setDate((int)$month->format('Y'), (int)$month->format('m'), 1)->setTime(0, 0, 0);
    }

    $nextMonth = $month->modify('+1 month');
    $start = $month->format('Y-m-d H:i:s');
    $end = $nextMonth->format('Y-m-d H:i:s');

    $stmt = $con->prepare(
        'SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(grand_total), 0) AS gross_revenue,
            COALESCE(SUM(CASE WHEN status = "Delivered" THEN grand_total ELSE 0 END), 0) AS delivered_revenue,
            COALESCE(SUM(CASE WHEN status = "Cancelled" THEN grand_total ELSE 0 END), 0) AS cancelled_value
         FROM orders
         WHERE created_at >= ? AND created_at < ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$summary) {
        return false;
    }

    $adminEmail = commerza_notifications_get_admin_email($con);
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $periodLabel = htmlspecialchars($month->format('F Y'), ENT_QUOTES, 'UTF-8');
    $totalOrders = (int)($summary['total_orders'] ?? 0);
    $grossRevenue = (float)($summary['gross_revenue'] ?? 0);
    $deliveredRevenue = (float)($summary['delivered_revenue'] ?? 0);
    $cancelledValue = (float)($summary['cancelled_value'] ?? 0);

    $body =
        '<p>Monthly business report for <strong>' . $periodLabel . '</strong>.</p>' .
        '<ul style="padding-left:20px;margin:14px 0;">' .
        '<li><strong>Total orders:</strong> ' . $totalOrders . '</li>' .
        '<li><strong>Gross revenue:</strong> PKR ' . number_format($grossRevenue, 2) . '</li>' .
        '<li><strong>Delivered revenue:</strong> PKR ' . number_format($deliveredRevenue, 2) . '</li>' .
        '<li><strong>Cancelled value:</strong> PKR ' . number_format($cancelledValue, 2) . '</li>' .
        '</ul>' .
        '<p>Delivered revenue is used as the month profit baseline in this report.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $adminEmail,
        'Commerza monthly profit report - ' . $month->format('Y-m'),
        'Monthly Profit Summary',
        'Your scheduled monthly report is ready.',
        $body,
        $error
    );
}

function commerza_notify_signup_verification_code(mysqli $con, string $userEmail, string $userName, string $code): bool
{
    $safeName = htmlspecialchars(trim($userName) !== '' ? $userName : 'Customer', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>Use the verification code below to complete your Commerza account registration.</p>' .
        '<div style="margin:16px 0;padding:14px 16px;background:#101010;border:1px dashed #ff6a00;border-radius:8px;text-align:center;">' .
        '<span style="font-size:28px;letter-spacing:5px;font-weight:700;color:#ffcc00;">' . $safeCode . '</span>' .
        '</div>' .
        '<p>This code expires in <strong>15 minutes</strong>.</p>' .
        '<p>If you did not request this signup, you can ignore this message.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $userEmail,
        'Commerza signup verification code',
        'Verify Your Account',
        'Complete your account registration securely.',
        $body,
        $error
    );
}

function commerza_notify_signup_success(mysqli $con, string $userEmail, string $userName): bool
{
    $safeName = htmlspecialchars(trim($userName) !== '' ? $userName : 'Customer', ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>Your Commerza account has been created successfully.</p>' .
        '<p>You can now log in, save products to wishlist, and track your orders from one dashboard.</p>' .
        '<p>Thank you for joining Commerza.</p>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $userEmail,
        'Welcome to Commerza',
        'Account Created Successfully',
        'Your profile is now active.',
        $body,
        $error
    );
}

function commerza_notify_admin_refund_request(mysqli $con, array $order, string $reason = ''): bool
{
    $adminEmail = commerza_notifications_get_admin_email($con);
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $customerEmail = htmlspecialchars((string)($order['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $safeReason = htmlspecialchars(trim($reason), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>A new refund request has been submitted.</p>' .
        '<p><strong>Order:</strong> ' . $orderNumber . '</p>' .
        '<p><strong>Customer:</strong> ' . $customerName . '</p>' .
        '<p><strong>Email:</strong> ' . $customerEmail . '</p>' .
        ($safeReason !== '' ? '<p><strong>Reason:</strong> ' . $safeReason . '</p>' : '');

    $error = null;
    return commerza_notifications_send(
        $con,
        $adminEmail,
        'Commerza refund request received',
        'Refund Request Received',
        'A customer submitted a new refund request.',
        $body,
        $error
    );
}

function commerza_notify_refund_status_update(mysqli $con, array $order, string $refundStatus, string $adminNote = ''): bool
{
    $customerEmail = trim((string)($order['customer_email'] ?? ''));
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeStatus = htmlspecialchars($refundStatus, ENT_QUOTES, 'UTF-8');
    $safeOrder = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $safeNote = htmlspecialchars(trim($adminNote), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>Your refund request for order <strong>' . $safeOrder . '</strong> was updated.</p>' .
        '<p><strong>Current Status:</strong> ' . $safeStatus . '</p>' .
        ($safeNote !== '' ? '<p><strong>Admin Note:</strong> ' . $safeNote . '</p>' : '');

    $error = null;
    return commerza_notifications_send(
        $con,
        $customerEmail,
        'Commerza refund update - ' . $safeStatus,
        'Refund Request Status Updated',
        'There is an update on your refund request.',
        $body,
        $error
    );
}

function commerza_send_weekly_analytics_email(mysqli $con, ?DateTimeImmutable $weekEnding = null): bool
{
    if ($weekEnding === null) {
        $weekEnding = new DateTimeImmutable('today 23:59:59');
    }

    $weekStart = $weekEnding->modify('-6 days')->setTime(0, 0, 0);
    $start = $weekStart->format('Y-m-d H:i:s');
    $end = $weekEnding->format('Y-m-d H:i:s');

    $stmt = $con->prepare(
        'SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(grand_total), 0) AS gross_revenue,
            COALESCE(SUM(CASE WHEN status = "Delivered" THEN grand_total ELSE 0 END), 0) AS delivered_revenue,
            COALESCE(SUM(CASE WHEN status = "Cancelled" THEN 1 ELSE 0 END), 0) AS cancelled_orders,
            COALESCE(SUM(CASE WHEN status = "Refunded" THEN 1 ELSE 0 END), 0) AS refunded_orders
         FROM orders
         WHERE created_at >= ? AND created_at <= ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$summary) {
        return false;
    }

    $adminEmail = commerza_notifications_get_admin_email($con);
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $totalOrders = (int)($summary['total_orders'] ?? 0);
    $grossRevenue = (float)($summary['gross_revenue'] ?? 0);
    $deliveredRevenue = (float)($summary['delivered_revenue'] ?? 0);
    $cancelledOrders = (int)($summary['cancelled_orders'] ?? 0);
    $refundedOrders = (int)($summary['refunded_orders'] ?? 0);

    $range = htmlspecialchars($weekStart->format('d M Y') . ' - ' . $weekEnding->format('d M Y'), ENT_QUOTES, 'UTF-8');

    $body =
        '<p>Weekly analytics summary for <strong>' . $range . '</strong>.</p>' .
        '<ul style="padding-left:20px;margin:14px 0;">' .
        '<li><strong>Total orders:</strong> ' . $totalOrders . '</li>' .
        '<li><strong>Gross revenue:</strong> PKR ' . number_format($grossRevenue, 2) . '</li>' .
        '<li><strong>Delivered revenue:</strong> PKR ' . number_format($deliveredRevenue, 2) . '</li>' .
        '<li><strong>Cancelled orders:</strong> ' . $cancelledOrders . '</li>' .
        '<li><strong>Refunded orders:</strong> ' . $refundedOrders . '</li>' .
        '</ul>';

    $error = null;
    return commerza_notifications_send(
        $con,
        $adminEmail,
        'Commerza weekly analytics report',
        'Weekly Analytics Summary',
        'Your 7-day performance snapshot is ready.',
        $body,
        $error
    );
}
