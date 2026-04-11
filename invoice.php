<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/core/data.php';
require_once __DIR__ . '/admin/backend/auth.php';

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

$orderInput = strtoupper(preg_replace('/\s+/', '', trim((string)($_GET['order'] ?? $_GET['order_number'] ?? ''))));
if ($orderInput !== '' && strpos($orderInput, '#') !== 0) {
    $orderInput = '#' . $orderInput;
}

$errors = [];
$order = null;
$orderItems = [];

if ($orderInput === '' || preg_match('/^#ORD-[A-Z0-9]{4,30}$/', $orderInput) !== 1) {
    $errors[] = 'Invalid order number format.';
} else {
    $orderStmt = $con->prepare(
        'SELECT
            id,
            order_number,
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
            notes,
            created_at,
            updated_at
         FROM orders
         WHERE order_number = ?
         LIMIT 1'
    );

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

function invoice_format_datetime(string $value): string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M d, Y h:i A', $timestamp);
}

$invoiceNumber = $order ? (string)$order['order_number'] : $orderInput;
$invoiceDate = $order ? invoice_format_datetime((string)($order['created_at'] ?? '')) : '';
$invoiceUpdatedDate = $order ? invoice_format_datetime((string)($order['updated_at'] ?? '')) : '';
$adminPanelUrl = 'admin/frontend/admin-panel.php';
$logoUrl = commerza_absolute_url('frontend/assets/images/logo/commerza-logo.webp');
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

<body>
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
                                <p>
                                    <span class="invoice-status-pill">
                                        <i class="bi bi-circle-fill"></i>
                                        <?= htmlspecialchars((string)$order['status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="badge text-bg-<?= htmlspecialchars(invoice_status_badge_class((string)$order['status']), ENT_QUOTES, 'UTF-8') ?> ms-2">
                                        <?= htmlspecialchars((string)$order['payment_status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </p>
                                <p><span class="strong">Payment Method:</span> <?= htmlspecialchars((string)$order['payment_method'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Coupon:</span> <?= htmlspecialchars((string)($order['coupon_code'] ?: 'Not Applied'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p><span class="strong">Order Ref:</span> <?= htmlspecialchars((string)$order['order_number'], ENT_QUOTES, 'UTF-8') ?></p>
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
                        <div class="summary-row"><span>Discount</span><strong>- <?= htmlspecialchars(invoice_currency((float)$order['discount_total']), ENT_QUOTES, 'UTF-8') ?></strong></div>
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
                        <span><?= htmlspecialchars(invoice_format_datetime((string)($order['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="frontend/assets/js/pages/invoice.js"></script>
</body>

</html>
