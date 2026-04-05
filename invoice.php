<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/data.php';
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
    <style>
        :root {
            --invoice-bg: #060606;
            --invoice-card: #101010;
            --invoice-muted: #b7b7b7;
            --invoice-border: rgba(255, 102, 0, 0.24);
            --invoice-accent: #ff7a1a;
            --invoice-accent-soft: rgba(255, 122, 26, 0.14);
            --invoice-good: #21c36a;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at 10% 0%, rgba(255, 122, 26, 0.16), transparent 42%),
                radial-gradient(circle at 95% 10%, rgba(255, 204, 0, 0.09), transparent 36%),
                var(--invoice-bg);
            color: #f5f5f5;
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }

        .invoice-page {
            max-width: 1100px;
            padding-top: 28px;
            padding-bottom: 42px;
        }

        .invoice-shell {
            border: 1px solid var(--invoice-border);
            border-radius: 20px;
            background: linear-gradient(170deg, rgba(20, 20, 20, 0.96), rgba(8, 8, 8, 0.97));
            box-shadow: 0 25px 55px rgba(0, 0, 0, 0.55);
            overflow: hidden;
        }

        .invoice-header {
            border-bottom: 1px solid var(--invoice-border);
            padding: 24px 26px;
            background: linear-gradient(145deg, rgba(255, 122, 26, 0.12), rgba(10, 10, 10, 0.25));
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-wrap img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid rgba(255, 122, 26, 0.35);
            background: #000;
        }

        .brand-name {
            font-size: 1.28rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .brand-sub {
            color: var(--invoice-muted);
            margin: 0;
            font-size: 0.88rem;
        }

        .invoice-meta {
            display: grid;
            gap: 6px;
            justify-items: end;
            text-align: right;
        }

        .invoice-meta .invoice-title {
            margin: 0;
            color: var(--invoice-accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .invoice-meta .invoice-number {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
        }

        .meta-row {
            color: var(--invoice-muted);
            font-size: 0.84rem;
        }

        .invoice-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
        }

        .invoice-toolbar .btn {
            border-radius: 10px;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 8px 14px;
        }

        .invoice-toolbar .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.22);
            color: #f5f5f5;
        }

        .invoice-toolbar .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.38);
        }

        .invoice-toolbar .btn-accent {
            border: 1px solid rgba(255, 122, 26, 0.48);
            background: linear-gradient(120deg, rgba(255, 122, 26, 0.24), rgba(255, 122, 26, 0.12));
            color: #ffd8be;
        }

        .invoice-toolbar .btn-accent:hover {
            color: #fff;
            border-color: rgba(255, 122, 26, 0.75);
            background: linear-gradient(120deg, rgba(255, 122, 26, 0.42), rgba(255, 122, 26, 0.2));
        }

        .invoice-body {
            padding: 24px 26px 28px;
        }

        .invoice-section {
            background: rgba(10, 10, 10, 0.54);
            border: 1px solid rgba(255, 122, 26, 0.17);
            border-radius: 14px;
            padding: 14px;
            height: 100%;
        }

        .invoice-section h2 {
            margin: 0 0 8px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #ffc593;
        }

        .invoice-section p {
            margin: 0 0 3px;
            color: var(--invoice-muted);
            font-size: 0.9rem;
        }

        .invoice-section .strong {
            color: #fff;
            font-weight: 700;
        }

        .invoice-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 122, 26, 0.3);
            background: rgba(255, 122, 26, 0.12);
            color: #ffd3af;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .invoice-items-wrap {
            margin-top: 16px;
            border: 1px solid rgba(255, 122, 26, 0.2);
            border-radius: 14px;
            overflow: hidden;
            background: rgba(8, 8, 8, 0.72);
        }

        .invoice-items-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }

        .invoice-items-table thead th {
            background: rgba(255, 122, 26, 0.12);
            color: #ffe3cc;
            border-bottom: 1px solid rgba(255, 122, 26, 0.25);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 11px 12px;
        }

        .invoice-items-table td {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            color: #d5d5d5;
            font-size: 0.9rem;
            padding: 11px 12px;
            vertical-align: middle;
        }

        .invoice-items-table tbody tr:hover {
            background: rgba(255, 122, 26, 0.06);
        }

        .item-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 220px;
        }

        .item-thumb {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid rgba(255, 122, 26, 0.25);
            background: #050505;
            flex-shrink: 0;
        }

        .item-name {
            color: #fff;
            font-weight: 600;
            line-height: 1.35;
        }

        .invoice-summary {
            margin-top: 16px;
            margin-left: auto;
            max-width: 360px;
            border: 1px solid rgba(255, 122, 26, 0.24);
            border-radius: 14px;
            overflow: hidden;
            background: rgba(10, 10, 10, 0.72);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.9rem;
            color: var(--invoice-muted);
        }

        .summary-row:first-child {
            border-top: 0;
        }

        .summary-row strong {
            color: #fff;
        }

        .summary-row.total {
            background: rgba(255, 122, 26, 0.16);
            color: #ffe7d3;
            font-size: 1rem;
            font-weight: 700;
        }

        .summary-row.total strong {
            color: #fff;
            font-size: 1.03rem;
        }

        .invoice-note {
            margin-top: 16px;
            border: 1px solid rgba(255, 204, 0, 0.24);
            border-radius: 12px;
            background: rgba(255, 204, 0, 0.08);
            color: #ffeccc;
            padding: 11px 12px;
            font-size: 0.88rem;
        }

        .invoice-footer {
            margin-top: 18px;
            border-top: 1px solid rgba(255, 122, 26, 0.22);
            padding-top: 12px;
            color: var(--invoice-muted);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.82rem;
        }

        .invoice-alert {
            margin: 0;
            border-radius: 12px;
            border: 1px solid rgba(220, 53, 69, 0.35);
            background: rgba(220, 53, 69, 0.18);
            color: #ffd7dc;
            padding: 14px;
            font-weight: 600;
        }

        .text-right {
            text-align: right;
        }

        .text-good {
            color: var(--invoice-good);
            font-weight: 700;
        }

        @media (max-width: 767px) {
            .invoice-header,
            .invoice-body {
                padding: 16px;
            }

            .invoice-meta {
                justify-items: start;
                text-align: left;
                margin-top: 12px;
            }

            .invoice-toolbar {
                justify-content: flex-start;
            }

            .invoice-items-table thead {
                display: none;
            }

            .invoice-items-table,
            .invoice-items-table tbody,
            .invoice-items-table tr,
            .invoice-items-table td {
                display: block;
                width: 100%;
            }

            .invoice-items-table tr {
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                padding: 8px 0;
            }

            .invoice-items-table tr:first-child {
                border-top: 0;
            }

            .invoice-items-table td {
                border-top: 0;
                padding: 5px 10px;
            }

            .invoice-items-table td::before {
                content: attr(data-label);
                display: block;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                color: #ffcc9e;
                margin-bottom: 2px;
            }

            .text-right {
                text-align: left;
            }
        }

        @media print {
            body {
                background: #fff;
                color: #000;
            }

            .invoice-page {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .invoice-shell {
                border: 1px solid #d7d7d7;
                box-shadow: none;
                border-radius: 0;
                background: #fff;
            }

            .invoice-toolbar {
                display: none;
            }

            .invoice-header,
            .invoice-body {
                color: #111 !important;
                background: #fff !important;
            }

            .brand-sub,
            .meta-row,
            .invoice-section p,
            .summary-row,
            .invoice-footer,
            .invoice-items-table td {
                color: #333 !important;
            }

            .invoice-meta .invoice-title,
            .spec-label {
                color: #555 !important;
            }

            .invoice-note {
                border-color: #ccc;
                background: #f8f8f8;
                color: #333;
            }

            .invoice-items-table thead th {
                background: #f4f4f4 !important;
                color: #333 !important;
                border-bottom-color: #ddd !important;
            }

            .summary-row.total {
                background: #f0f0f0 !important;
                color: #111 !important;
            }
        }
    </style>
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

    <script <?= commerza_csp_nonce_attr() ?>>
        (function () {
            const printBtn = document.getElementById('printInvoiceBtn');
            if (!printBtn) {
                return;
            }

            printBtn.addEventListener('click', function () {
                window.print();
            });
        })();
    </script>
</body>

</html>
