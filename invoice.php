<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/core/data.php';
require_once __DIR__ . '/admin/backend/auth/auth.php';

if (!($con instanceof mysqli)) {
    http_response_code(500);
    exit('Service unavailable.');
}

$adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
$adminUser = $adminId > 0 ? admin_get_by_id($con, $adminId) : null;

if (!$adminUser || (int)($adminUser['is_active'] ?? 0) !== 1) {
    admin_logout_user();
    header('Location: admin/frontend/admin-login.php');
    exit;
}

$orderInput = '';

$errors = [];
$order = null;
$orderItems = [];

function invoice_orders_column_exists(mysqli $con, string $column): bool
{
    static $cache = [];

    $normalized = strtolower(trim($column));
    if ($normalized === '' || preg_match('/^[a-z0-9_]+$/', $normalized) !== 1) {
        return false;
    }

    if (array_key_exists($normalized, $cache)) {
        return (bool)$cache[$normalized];
    }

    $safe = $con->real_escape_string($normalized);
    $result = $con->query("SHOW COLUMNS FROM orders LIKE '{$safe}'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    $cache[$normalized] = $exists;
    return $exists;
}

function invoice_extract_order_input(): string
{
    $queryValue = trim((string)($_GET['order'] ?? $_GET['order_number'] ?? ''));
    $pathValue = '';

    if ($queryValue === '') {
        $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if ($requestPath !== '') {
            $normalizedPath = rtrim($requestPath, '/');
            if (preg_match('#/invoice/([^/?#]+)$#i', $normalizedPath, $matches) === 1) {
                $pathValue = rawurldecode((string)($matches[1] ?? ''));
            }
        }
    }

    $rawValue = $queryValue !== '' ? $queryValue : $pathValue;
    $normalized = strtoupper(preg_replace('/\s+/', '', $rawValue));
    $normalized = ltrim($normalized, '#');

    if ($normalized === '') {
        return '';
    }

    return '#' . $normalized;
}

$orderInput = invoice_extract_order_input();

$hasDeliveryEstimateColumn = invoice_orders_column_exists($con, 'delivery_estimate');
$hasAdminNoteColumn = invoice_orders_column_exists($con, 'admin_note');

if ($orderInput === '' || preg_match('/^#ORD-[A-Z0-9]{4,30}$/', $orderInput) !== 1) {
    $errors[] = 'Invalid order number format.';
} else {
    $orderColumns = [
        'id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'subtotal',
        'shipping_cost',
        'discount_total',
        'coupon_code',
        'grand_total',
        'status',
        'payment_status',
        'payment_method',
        'notes',
    ];

    if ($hasDeliveryEstimateColumn) {
        $orderColumns[] = 'delivery_estimate';
    }

    if ($hasAdminNoteColumn) {
        $orderColumns[] = 'admin_note';
    }

    $orderColumns[] = 'created_at';
    $orderColumns[] = 'updated_at';

    $orderQuery = 'SELECT ' . implode(', ', $orderColumns) . ' FROM orders WHERE order_number = ? LIMIT 1';

    $orderStmt = $con->prepare($orderQuery);

    if (!$orderStmt) {
        $errors[] = 'Unable to load invoice details right now.';
    } else {
        $orderStmt->bind_param('s', $orderInput);
        $orderStmt->execute();
        $result = $orderStmt->get_result();
        $order = $result ? $result->fetch_assoc() : null;
        $orderStmt->close();

        if (!$order) {
            $errors[] = 'Order was not found.';
        }
    }

    if (empty($errors) && $order) {
        $itemsStmt = $con->prepare(
            'SELECT product_name, product_img, unit_price, quantity, line_total
             FROM order_items
             WHERE order_id = ?
             ORDER BY id ASC'
        );

        if ($itemsStmt) {
            $orderId = (int)$order['id'];
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();

            while ($itemsResult && ($row = $itemsResult->fetch_assoc())) {
                $orderItems[] = $row;
            }

            $itemsStmt->close();
        }
    }
}

function invoice_currency($value): string
{
    return number_format((float)$value, 0) . ' PKR';
}

function invoice_status_badge_class(string $status): string
{
    $normalized = strtolower(trim($status));

    if ($normalized === 'delivered') {
        return 'success';
    }

    if ($normalized === 'shipped') {
        return 'primary';
    }

    if ($normalized === 'cancelled' || $normalized === 'refunded') {
        return 'danger';
    }

    if ($normalized === 'processing' || $normalized === 'confirmed') {
        return 'info';
    }

    return 'warning';
}

function invoice_payment_badge_class(string $paymentStatus, string $paymentMethod): string
{
    $normalized = strtolower(trim($paymentStatus));

    if (in_array($normalized, ['paid', 'captured', 'completed', 'success', 'succeeded'], true)) {
        return 'success';
    }

    if (in_array($normalized, ['pending', 'awaiting', 'unpaid', 'processing'], true)) {
        return 'warning';
    }

    if (in_array($normalized, ['failed', 'declined', 'chargeback', 'cancelled', 'refunded'], true)) {
        return 'danger';
    }

    if ($normalized === 'partial') {
        return 'info';
    }

    $method = strtolower(trim($paymentMethod));
    if ($normalized === '' && in_array($method, ['cod', 'cash on delivery'], true)) {
        return 'info';
    }

    return 'muted';
}

function invoice_humanize_label(string $value, string $fallback): string
{
    $raw = trim($value);
    if ($raw === '') {
        return $fallback;
    }

    $spaced = preg_replace('/[_\-]+/', ' ', $raw);
    if ($spaced === null || $spaced === '') {
        $spaced = $raw;
    }

    return ucwords(strtolower($spaced));
}

function invoice_resolve_timezone(mysqli $con): DateTimeZone
{
    $configured = trim(commerza_site_setting_value($con, 'timezone', 'UTC'));
    if ($configured === '') {
        $configured = 'UTC';
    }

    try {
        return new DateTimeZone($configured);
    } catch (Throwable $error) {
        return new DateTimeZone('UTC');
    }
}

function invoice_format_datetime(string $value, DateTimeZone $timezone): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }

    try {
        $normalized = str_replace('T', ' ', $raw);
        $normalized = preg_replace('/\.\d+$/', '', $normalized) ?? $normalized;

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $timezone);
        if (!($date instanceof DateTimeImmutable)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $normalized, $timezone);
        }

        if (!($date instanceof DateTimeImmutable)) {
            $date = new DateTimeImmutable($raw, $timezone);
        }

        return $date->setTimezone($timezone)->format('M d, Y h:i A');
    } catch (Throwable $error) {
        return $raw;
    }
}

function invoice_logistics_step_index(string $status): int
{
    $normalized = strtolower(trim($status));

    if ($normalized === 'delivered') {
        return 3;
    }

    if ($normalized === 'shipped') {
        return 2;
    }

    if ($normalized === 'confirmed' || $normalized === 'processing' || $normalized === 'cancelled' || $normalized === 'refunded') {
        return 1;
    }

    return 0;
}

function invoice_logistics_steps(string $status, string $createdAt, string $updatedAt, string $deliveryEstimate): array
{
    $normalized = strtolower(trim($status));
    $isCancelled = in_array($normalized, ['cancelled', 'refunded'], true);
    $isDelivered = $normalized === 'delivered';
    $isShipped = $normalized === 'shipped';

    $steps = [
        [
            'title' => 'Order Received',
            'detail' => 'Payment and order details recorded.',
            'time' => $createdAt !== '' ? $createdAt : 'Timestamp pending',
        ],
        [
            'title' => 'Packing & Confirmation',
            'detail' => 'Items verified, packed, and dispatch prepared.',
            'time' => $updatedAt !== '' ? $updatedAt : 'Pending',
        ],
        [
            'title' => 'In Transit',
            'detail' => 'Shipment handed to courier and en route.',
            'time' => 'Awaiting courier handoff',
        ],
        [
            'title' => 'Delivered',
            'detail' => 'Order delivered to customer address.',
            'time' => 'Pending',
        ],
    ];

    if ($isShipped || $isDelivered) {
        $steps[2]['time'] = $updatedAt !== '' ? $updatedAt : ($deliveryEstimate !== '' ? 'ETA ' . $deliveryEstimate : 'In transit');
    } elseif ($deliveryEstimate !== '') {
        $steps[2]['time'] = 'ETA ' . $deliveryEstimate;
    }

    if ($isDelivered) {
        $steps[3]['time'] = $updatedAt !== '' ? $updatedAt : 'Delivered';
    } elseif ($deliveryEstimate !== '') {
        $steps[3]['time'] = 'Expected ' . $deliveryEstimate;
    }

    if ($isCancelled) {
        $statusLabel = invoice_humanize_label($status, 'Cancelled');
        $steps[3]['detail'] = 'Delivery flow closed because this order is ' . strtolower($statusLabel) . '.';
        $steps[3]['time'] = $statusLabel;
    }

    $activeIndex = invoice_logistics_step_index($status);
    foreach ($steps as $index => $step) {
        $state = 'pending';

        if ($index < $activeIndex) {
            $state = 'done';
        } elseif ($index === $activeIndex) {
            $state = 'active';
        }

        if ($isDelivered && $index === 3) {
            $state = 'done';
        }

        if ($isCancelled && $index >= 2) {
            $state = 'pending';
        }

        $steps[$index]['state'] = $state;
    }

    return $steps;
}

$invoiceTimezone = invoice_resolve_timezone($con);
$invoiceTimezoneLabel = $invoiceTimezone->getName();
$invoiceNumber = $order ? (string)$order['order_number'] : $orderInput;
$invoiceDate = $order ? invoice_format_datetime((string)($order['created_at'] ?? ''), $invoiceTimezone) : '';
$invoiceUpdatedDate = $order ? invoice_format_datetime((string)($order['updated_at'] ?? ''), $invoiceTimezone) : '';
$invoiceDeliveryEstimate = ($order && $hasDeliveryEstimateColumn)
    ? invoice_format_datetime((string)($order['delivery_estimate'] ?? ''), $invoiceTimezone)
    : '';
$invoiceLogisticsNote = ($order && $hasAdminNoteColumn)
    ? trim((string)($order['admin_note'] ?? ''))
    : '';
$invoiceStatusRaw = $order ? (string)($order['status'] ?? '') : '';
$invoicePaymentStatusRaw = $order ? (string)($order['payment_status'] ?? '') : '';
$invoicePaymentMethodRaw = $order ? (string)($order['payment_method'] ?? '') : '';
$invoiceStatusLabel = invoice_humanize_label($invoiceStatusRaw, 'Pending');
$invoicePaymentStatusLabel = invoice_humanize_label($invoicePaymentStatusRaw, 'Pending');
$invoicePaymentMethodLabel = invoice_humanize_label($invoicePaymentMethodRaw, 'Not Specified');
$invoiceStatusBadgeClass = invoice_status_badge_class($invoiceStatusRaw);
$invoicePaymentBadgeClass = invoice_payment_badge_class($invoicePaymentStatusRaw, $invoicePaymentMethodRaw);
$invoiceDiscountTotal = $order ? (float)($order['discount_total'] ?? 0) : 0.0;
$invoiceCouponCode = $order ? trim((string)($order['coupon_code'] ?? '')) : '';
$invoiceCouponLabel = $invoiceCouponCode !== '' ? $invoiceCouponCode : 'Not Applied';
$invoiceTotalUnits = 0;
foreach ($orderItems as $orderItemRow) {
    $invoiceTotalUnits += (int)($orderItemRow['quantity'] ?? 0);
}
$invoiceLineItems = count($orderItems);
$invoiceLogisticsStep = $order ? invoice_logistics_step_index($invoiceStatusRaw) : 0;
$invoiceLogisticsSteps = $order ? invoice_logistics_steps($invoiceStatusRaw, $invoiceDate, $invoiceUpdatedDate, $invoiceDeliveryEstimate) : [];
$invoiceOrderReference = $order ? (string)($order['order_number'] ?? $invoiceNumber) : $invoiceNumber;
$invoiceIsCancelled = in_array(strtolower(trim($invoiceStatusRaw)), ['cancelled', 'refunded'], true);
$invoiceGeneratedAt = $invoiceUpdatedDate !== '' ? $invoiceUpdatedDate : ($invoiceDate !== '' ? $invoiceDate : 'N/A');
$adminPanelUrl = 'admin/frontend/admin-panel.php';
$logoUrl = commerza_absolute_url('frontend/assets/images/logo/commerza_logo.svg');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice <?= htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8') ?> | Commerza</title>
    <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="frontend/assets/css/pages/invoice-inline.css">
</head>

<body class="dark-theme">
    <main class="invoice-page container">
        <div class="invoice-shell">
            <header class="invoice-header">
                <div class="row g-0 align-items-start">
                    <div class="col-lg-6">
                        <div class="brand-wrap">
                            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Commerza logo">
                            <div>
                                <p class="brand-name mb-0">Commerza</p>
                                <p class="brand-sub">Order Invoice</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="invoice-meta">
                            <p class="invoice-title">Invoice</p>
                            <p class="invoice-number mb-0"><?= htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if ($invoiceDate !== ''): ?>
                                <div class="meta-row">Created: <?= htmlspecialchars($invoiceDate, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <?php if ($invoiceUpdatedDate !== ''): ?>
                                <div class="meta-row">Updated: <?= htmlspecialchars($invoiceUpdatedDate, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <div class="meta-row">Timezone: <?= htmlspecialchars($invoiceTimezoneLabel, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="invoice-toolbar">
                            <a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars($adminPanelUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left"></i> Admin Panel</a>
                            <button type="button" class="btn btn-sm btn-accent" id="printInvoiceBtn"><i class="bi bi-printer"></i> Print</button>
                        </div>
                    </div>
                </div>
            </header>

            <div class="invoice-body">
                <?php if (!empty($errors)): ?>
                    <p class="invoice-alert"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></p>
                <?php elseif ($order): ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <div class="invoice-section">
                                <h2>Bill To</h2>
                                <p class="strong"><?= htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p><?= htmlspecialchars((string)$order['customer_email'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p><?= htmlspecialchars((string)($order['customer_phone'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p><?= nl2br(htmlspecialchars((string)$order['address'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="invoice-section">
                                <h2>Order & Payment</h2>
                                <div class="invoice-pill-group">
                                    <span class="invoice-pill invoice-pill-<?= htmlspecialchars($invoiceStatusBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-box-seam"></i>
                                        Status: <?= htmlspecialchars($invoiceStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="invoice-pill invoice-pill-<?= htmlspecialchars($invoicePaymentBadgeClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-credit-card-2-front"></i>
                                        Payment: <?= htmlspecialchars($invoicePaymentStatusLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <p><span class="strong">Payment Method:</span> <?= htmlspecialchars($invoicePaymentMethodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Coupon:</span> <?= htmlspecialchars($invoiceCouponLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Order Ref:</span> <?= htmlspecialchars($invoiceOrderReference, ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Delivery Estimate:</span> <?= htmlspecialchars($invoiceDeliveryEstimate !== '' ? $invoiceDeliveryEstimate : 'Not set', ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Internal Logistics Note:</span> <?= htmlspecialchars($invoiceLogisticsNote !== '' ? $invoiceLogisticsNote : 'None', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="invoice-section invoice-kpi-section">
                                <h2>Fulfillment Snapshot</h2>
                                <div class="invoice-kpi-grid">
                                    <div class="invoice-kpi-card">
                                        <span class="kpi-label">Line Items</span>
                                        <strong class="kpi-value"><?= (int)$invoiceLineItems ?></strong>
                                    </div>
                                    <div class="invoice-kpi-card">
                                        <span class="kpi-label">Units</span>
                                        <strong class="kpi-value"><?= (int)$invoiceTotalUnits ?></strong>
                                    </div>
                                    <div class="invoice-kpi-card">
                                        <span class="kpi-label">Last Update</span>
                                        <strong class="kpi-value"><?= htmlspecialchars($invoiceUpdatedDate !== '' ? $invoiceUpdatedDate : 'N/A', ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div class="invoice-kpi-card">
                                        <span class="kpi-label">Timezone</span>
                                        <strong class="kpi-value"><?= htmlspecialchars($invoiceTimezoneLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="invoice-section invoice-logistics">
                                <h2>Logistics Timeline</h2>
                                <p class="invoice-logistics-intro">Clear fulfillment stages for dispatch and print records.</p>
                                <?php if ($invoiceIsCancelled): ?>
                                    <p class="invoice-logistics-alert">This order is currently <?= htmlspecialchars(strtolower($invoiceStatusLabel), ENT_QUOTES, 'UTF-8') ?>. Courier progression has been stopped.</p>
                                <?php endif; ?>
                                <ol class="invoice-logistics-list">
                                    <?php foreach ($invoiceLogisticsSteps as $step): ?>
                                        <li class="invoice-logistics-item <?= htmlspecialchars((string)($step['state'] ?? 'pending'), ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="step-dot" aria-hidden="true"></span>
                                            <div>
                                                <span class="step-title"><?= htmlspecialchars((string)$step['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <small class="step-detail"><?= htmlspecialchars((string)$step['detail'], ENT_QUOTES, 'UTF-8') ?></small>
                                                <small class="step-time"><?= htmlspecialchars((string)($step['time'] ?? 'Pending'), ENT_QUOTES, 'UTF-8') ?></small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="invoice-items-wrap">
                        <table class="invoice-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 48%;">Item</th>
                                    <th style="width: 12%;" class="text-right">Qty</th>
                                    <th style="width: 20%;" class="text-right">Unit Price</th>
                                    <th style="width: 20%;" class="text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orderItems)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">No order items found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orderItems as $item): ?>
                                        <?php
                                        $itemImage = trim((string)($item['product_img'] ?? ''));
                                        $itemImageSafe = htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td data-label="Item">
                                                <div class="item-cell">
                                                    <?php if ($itemImage !== ''): ?>
                                                        <img class="item-thumb" src="<?= $itemImageSafe ?>" alt="<?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php endif; ?>
                                                    <span class="item-name"><?= htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Qty" class="text-right"><?= (int)($item['quantity'] ?? 0) ?></td>
                                            <td data-label="Unit Price" class="text-right"><?= htmlspecialchars(invoice_currency((float)($item['unit_price'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td data-label="Line Total" class="text-right text-good"><?= htmlspecialchars(invoice_currency((float)($item['line_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="invoice-summary">
                        <div class="summary-row"><span>Subtotal</span><strong><?= htmlspecialchars(invoice_currency((float)$order['subtotal']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <div class="summary-row"><span>Shipping</span><strong><?= htmlspecialchars(invoice_currency((float)$order['shipping_cost']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <div class="summary-row"><span>Discount</span><strong><?= $invoiceDiscountTotal > 0 ? '- ' : '' ?><?= htmlspecialchars(invoice_currency((float)$order['discount_total']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <div class="summary-row total"><span>Grand Total</span><strong><?= htmlspecialchars(invoice_currency((float)$order['grand_total']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    </div>

                    <?php if (trim((string)($order['notes'] ?? '')) !== ''): ?>
                        <div class="invoice-note">
                            <strong>Customer Note:</strong>
                            <?= nl2br(htmlspecialchars((string)$order['notes'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>

                    <div class="invoice-footer">
                        <span>Generated by Commerza Admin</span>
                        <span><?= htmlspecialchars($invoiceGeneratedAt, ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($invoiceTimezoneLabel, ENT_QUOTES, 'UTF-8') ?>)</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="frontend/assets/js/pages/invoice.js"></script>
</body>

</html>