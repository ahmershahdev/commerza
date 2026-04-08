<?php

declare(strict_types=1);

include "backend/data.php";
require_once "backend/cart_helpers.php";
require_once "backend/notifications.php";
require_once "backend/coupon_helpers.php";

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
  $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
  if (preg_match('#/cart\.php$#i', str_replace('\\', '/', $requestPath)) === 1) {
    $queryString = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
    $targetUrl = commerza_absolute_url('/cart');
    if ($queryString !== '') {
      $targetUrl .= '?' . $queryString;
    }
    header('Location: ' . $targetUrl, true, 301);
    exit;
  }
}

try {
  $checkout_request_id = 'checkout-' . bin2hex(random_bytes(12));
} catch (Throwable $exception) {
  $checkout_request_id = 'checkout-' . substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 24);
}

$errors = [];
$success = '';
$current_user = [
  'full_name' => '',
  'email' => '',
  'phone' => '',
  'address' => '',
];

$is_logged_in = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);

if ($is_logged_in) {
  $user_id = (int)$_SESSION['user_id'];
  $userStmt = $con->prepare("SELECT full_name, email, phone, address FROM users WHERE id = ? LIMIT 1");
  if ($userStmt) {
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($userResult && $userResult->num_rows === 1) {
      $current_user = $userResult->fetch_assoc();
    }
    $userStmt->close();
  }
}

$checkoutCaptchaConfig = commerza_captcha_config($con);
$checkoutCaptchaEnabled = (bool)($checkoutCaptchaConfig['enabled'] ?? false);
$checkoutCaptchaField = (string)($checkoutCaptchaConfig['response_field'] ?? '');
commerza_ensure_coupon_schema($con);

function generate_order_number(mysqli $con): string
{
  for ($i = 0; $i < 20; $i++) {
    $candidate = '#ORD-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $checkStmt = $con->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
    if (!$checkStmt) {
      return '#ORD-' . (string)time();
    }

    $checkStmt->bind_param("s", $candidate);
    $checkStmt->execute();
    $checkStmt->store_result();
    $exists = $checkStmt->num_rows > 0;
    $checkStmt->close();

    if (!$exists) {
      return $candidate;
    }
  }

  return '#ORD-' . (string)time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'place_order') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    http_response_code(403);
    exit('Forbidden.');
  }

  if (!$is_logged_in) {
    $errors[] = 'Please login before placing an order.';
  }

  $requestId = commerza_request_id_from_server($_POST);
  $idempotency = commerza_idempotency_consume($con, 'checkout_place_order', $requestId, 86400);
  if (!(bool)($idempotency['ok'] ?? false)) {
    $errors[] = (bool)($idempotency['duplicate'] ?? false)
      ? 'Duplicate checkout request was ignored. Please wait or refresh before trying again.'
      : (string)($idempotency['message'] ?? 'Unable to verify checkout request integrity.');
  }

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'checkout_place_order');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  $customer_name = trim((string)($_POST['customer_name'] ?? ''));
  $customer_email = strtolower(trim((string)($_POST['customer_email'] ?? '')));
  $customer_phone = preg_replace('/\s+/', '', trim((string)($_POST['customer_phone'] ?? '')));
  $customer_phone = (string)($customer_phone ?? '');
  $customer_address = trim((string)($_POST['customer_address'] ?? ''));
  $payment_method = strtolower(trim((string)($_POST['payment_method'] ?? 'cod')));

  $payment_methods = [
    'cod' => 'Cash on Delivery (COD)',
  ];

  $payment_method_label = $payment_methods[$payment_method] ?? '';
  $payment_status = 'unpaid';
  $payment_notes = [];

  if (strlen($customer_name) < 3 || strlen($customer_name) > 100) {
    $errors[] = 'Full name must be 3-100 characters.';
  }

  if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL) || strlen($customer_email) > 150) {
    $errors[] = 'Invalid email address.';
  }

  if (!preg_match('/^\d{11,15}$/', $customer_phone)) {
    $errors[] = 'Invalid phone number.';
  }

  if (strlen($customer_address) < 10 || strlen($customer_address) > 500) {
    $errors[] = 'Address must be 10-500 characters.';
  }

  if ($payment_method_label === '') {
    $errors[] = 'Please select a valid payment method.';
  }

  $normalized_items = [];
  $subtotal = 0.00;

  $cart_id = commerza_get_cart_id($con, false);
  $cart_snapshot = [
    'count' => 0,
    'subtotal' => 0,
    'items' => [],
  ];

  if (!$cart_id) {
    $errors[] = 'Your cart is empty.';
  } else {
    $cart_snapshot = commerza_fetch_cart_snapshot($con, $cart_id);
    if ((int)$cart_snapshot['count'] <= 0 || empty($cart_snapshot['items'])) {
      $errors[] = 'Your cart is empty.';
    }

    if ((int)$cart_snapshot['count'] > 10) {
      $errors[] = 'Cart item limit exceeded.';
    }
  }

  if (empty($errors)) {
    foreach ($cart_snapshot['items'] as $item) {
      if (!is_array($item)) {
        continue;
      }

      $item_name = trim((string)($item['name'] ?? ''));
      $quantity = (int)($item['quantity'] ?? 1);
      $product_image = trim((string)($item['image'] ?? ''));
      $product_id = isset($item['id']) ? (int)$item['id'] : null;
      $sale_price = isset($item['salePrice']) ? (float)$item['salePrice'] : 0.0;
      $price = isset($item['price']) ? (float)$item['price'] : 0.0;
      $unit_price = $sale_price > 0 ? $sale_price : $price;

      if ($item_name === '' || strlen($item_name) > 255) {
        $errors[] = 'Invalid item name found in cart.';
        break;
      }

      if ($quantity < 1 || $quantity > 10) {
        $errors[] = 'Invalid item quantity found in cart.';
        break;
      }

      if ($unit_price <= 0) {
        $errors[] = 'Invalid item price found in cart.';
        break;
      }

      if (strlen($product_image) > 255) {
        $product_image = '';
      }

      $line_total = round($unit_price * $quantity, 2);
      $subtotal += $line_total;

      $normalized_items[] = [
        'product_id' => $product_id,
        'name' => $item_name,
        'image' => $product_image,
        'unit_price' => $unit_price,
        'quantity' => $quantity,
        'line_total' => $line_total,
      ];
    }

    if (count($normalized_items) === 0) {
      $errors[] = 'Your cart is empty.';
    }
  }

  if (empty($errors) && $subtotal > 0) {
    $user_id = (int)$_SESSION['user_id'];
    $shipping_config = commerza_cart_shipping_config($con);
    $shipping_cost = commerza_cart_shipping_cost($subtotal, $shipping_config);
    $discount_total = 0.00;
    $coupon_code = '';
    $coupon_id = 0;
    $coupon_code_input = commerza_coupon_normalize_code((string)($_POST['coupon_code'] ?? ''));

    $coupon_state = commerza_coupon_resolve_checkout_coupon($con, $subtotal, $user_id, $coupon_code_input);

    if ($coupon_code_input !== '' && !(bool)($coupon_state['ok'] ?? false)) {
      $errors[] = (string)($coupon_state['message'] ?? 'Invalid coupon code.');
    }

    if ((bool)($coupon_state['ok'] ?? false)) {
      $discount_total = (float)($coupon_state['discount'] ?? 0);
      $coupon_code = (string)($coupon_state['code'] ?? '');
      $coupon_id = (int)($coupon_state['coupon_id'] ?? 0);
    }

    $grand_total = round(max(0, $subtotal + $shipping_cost - $discount_total), 2);
    $order_number = generate_order_number($con);

    $order_notes_text = implode("\n", array_filter($payment_notes));

    if (!empty($errors)) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (empty($errors)) {

      try {
        $con->begin_transaction();

        $items_for_stock_check = $normalized_items;
        usort($items_for_stock_check, static function (array $left, array $right): int {
          return ((int)($left['product_id'] ?? 0)) <=> ((int)($right['product_id'] ?? 0));
        });

        $stockSelectStmt = $con->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1 FOR UPDATE');
        $stockUpdateStmt = $con->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

        if (!$stockSelectStmt || !$stockUpdateStmt) {
          if ($stockSelectStmt) {
            $stockSelectStmt->close();
          }
          if ($stockUpdateStmt) {
            $stockUpdateStmt->close();
          }
          throw new RuntimeException('Unable to validate product stock.');
        }

        foreach ($items_for_stock_check as $row) {
          $stock_product_id = (int)($row['product_id'] ?? 0);
          $stock_quantity = (int)($row['quantity'] ?? 0);
          $stock_name = trim((string)($row['name'] ?? 'This product'));

          if ($stock_product_id <= 0 || $stock_quantity <= 0) {
            throw new RuntimeException('STOCK_ERROR: One product in your cart is invalid. Please refresh your cart and try again.');
          }

          $stockSelectStmt->bind_param('i', $stock_product_id);
          $stockSelectStmt->execute();
          $stockResult = $stockSelectStmt->get_result();
          $productRow = $stockResult ? $stockResult->fetch_assoc() : null;

          if (!$productRow) {
            throw new RuntimeException('STOCK_ERROR: One product in your cart is no longer available.');
          }

          $availableStock = (int)($productRow['stock'] ?? 0);
          if ($availableStock < $stock_quantity) {
            $itemLabel = $stock_name !== '' ? $stock_name : 'This product';
            throw new RuntimeException('STOCK_ERROR: ' . $itemLabel . ' has only ' . max($availableStock, 0) . ' item(s) left.');
          }

          $stockUpdateStmt->bind_param('iii', $stock_quantity, $stock_product_id, $stock_quantity);
          $stockUpdateStmt->execute();

          if ($stockUpdateStmt->affected_rows !== 1) {
            throw new RuntimeException('STOCK_ERROR: Stock changed while placing your order. Please review your cart and try again.');
          }
        }

        $stockSelectStmt->close();
        $stockUpdateStmt->close();

        $orderStmt = $con->prepare(
          "INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, address, subtotal, shipping_cost, discount_total, coupon_code, grand_total, status, payment_status, payment_method, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)"
        );

        if (!$orderStmt) {
          throw new RuntimeException('Unable to create order.');
        }

        $orderStmt->bind_param(
          "sissssdddsdsss",
          $order_number,
          $user_id,
          $customer_name,
          $customer_email,
          $customer_phone,
          $customer_address,
          $subtotal,
          $shipping_cost,
          $discount_total,
          $coupon_code,
          $grand_total,
          $payment_status,
          $payment_method_label,
          $order_notes_text
        );

        if (!$orderStmt->execute()) {
          throw new RuntimeException('Unable to save order.');
        }

        $order_id = (int)$con->insert_id;
        $orderStmt->close();

        $itemStmt = $con->prepare(
          "INSERT INTO order_items (order_id, product_id, product_name, product_img, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$itemStmt) {
          throw new RuntimeException('Unable to save order items.');
        }

        foreach ($normalized_items as $row) {
          $product_id = $row['product_id'];
          $product_name = $row['name'];
          $product_img = $row['image'];
          $unit_price = $row['unit_price'];
          $quantity = $row['quantity'];
          $line_total = $row['line_total'];

          $itemStmt->bind_param(
            "iissdid",
            $order_id,
            $product_id,
            $product_name,
            $product_img,
            $unit_price,
            $quantity,
            $line_total
          );

          if (!$itemStmt->execute()) {
            throw new RuntimeException('Unable to save an order item.');
          }
        }

        $itemStmt->close();

        if ($coupon_id > 0 && $discount_total > 0) {
          $couponApplied = commerza_coupon_register_redemption(
            $con,
            $coupon_id,
            $user_id,
            $order_id,
            $discount_total
          );

          if (!$couponApplied) {
            commerza_security_log_event($con, [
              'event_type' => 'coupon_redemption_failed_checkout',
              'severity' => 'warning',
              'actor_type' => 'user',
              'actor_identifier' => (string)$customer_email,
              'user_id' => $user_id,
              'ip_address' => commerza_client_ip(),
              'details' => [
                'coupon_id' => $coupon_id,
                'order_id' => $order_id,
                'reason' => 'coupon_limit_or_state_changed',
              ],
            ]);
            throw new RuntimeException('COUPON_ERROR: Coupon is no longer available. Please remove it and try again.');
          }
        }

        $clearCartStmt = $con->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        if ($clearCartStmt) {
          $clearCartStmt->bind_param('i', $cart_id);
          $clearCartStmt->execute();
          $clearCartStmt->close();
        }

        commerza_remove_cart_if_empty($con, $cart_id);
        if ($coupon_id > 0) {
          commerza_coupon_clear_session_code();
        }
        $con->commit();

        commerza_notify_order_placed(
          $con,
          [
            'order_number' => $order_number,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'address' => $customer_address,
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping_cost,
            'discount_total' => $discount_total,
            'coupon_code' => $coupon_code,
            'grand_total' => $grand_total,
            'status' => 'Pending',
            'payment_method' => $payment_method_label,
            'created_at' => date('Y-m-d H:i:s'),
          ],
          $normalized_items
        );

        $success = 'Order placed successfully via ' . $payment_method_label . '. Your order number is ' . $order_number . '.';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      } catch (Throwable $e) {
        $con->rollback();
        $errorMessage = (string)$e->getMessage();
        $dbErrorCode = (int)$con->errno;

        if ($dbErrorCode === 1213 || $dbErrorCode === 1205) {
          commerza_security_log_event($con, [
            'event_type' => 'checkout_transaction_contention',
            'severity' => 'warning',
            'actor_type' => 'user',
            'actor_identifier' => (string)($customer_email ?? ''),
            'user_id' => isset($user_id) ? (int)$user_id : 0,
            'ip_address' => commerza_client_ip(),
            'details' => [
              'db_error_code' => $dbErrorCode,
            ],
          ]);
          $errors[] = 'Checkout is temporarily busy due to high concurrent activity. Please try placing your order again.';
        } elseif (str_starts_with($errorMessage, 'STOCK_ERROR:')) {
          $errors[] = trim(substr($errorMessage, strlen('STOCK_ERROR:')));
        } elseif (str_starts_with($errorMessage, 'COUPON_ERROR:')) {
          $errors[] = trim(substr($errorMessage, strlen('COUPON_ERROR:')));
        } else {
          $errors[] = 'Something went wrong while placing the order. Please try again.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Review your Commerza cart and complete checkout with secure Cash on Delivery flow.">
  <meta property="og:title" content="Cart | Commerza">
  <meta property="og:description" content="Review items in your Commerza cart and complete secure checkout.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/cart.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Cart | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/cart.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Cart | Commerza",
      "url": "https://commerza.ahmershah.dev/cart.php",
      "description": "Commerza cart and secure checkout."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    .cart-img {
      height: 90px;
      width: 90px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(255, 102, 0, 0.22);
    }

    span,
    strong,
    hr {
      color: white;
    }

    img {
      user-select: none;
    }

    #serverAlert,
    #successAlert,
    #checkoutEmptyAlert {
      border: none;
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      position: fixed;
      top: 20px;
      right: 0;
      left: 0;
      margin: auto;
      width: 440px;
      max-width: calc(100% - 24px);
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }

    #serverAlert {
      background-color: #dc3545;
    }

    #successAlert {
      background-color: #198754;
    }

    #checkoutEmptyAlert {
      display: none;
      background: linear-gradient(135deg, #ffcc00, #ff8c00);
      color: #1f1400;
    }

    #checkoutModal .modal-content {
      background: linear-gradient(160deg, #1f1f1f 0%, #111111 100%);
      border: 1px solid rgba(255, 102, 0, 0.35);
      box-shadow: 0 24px 40px rgba(0, 0, 0, 0.45);
    }

    #checkoutModal .modal-header,
    #checkoutModal .modal-footer {
      border-color: rgba(255, 102, 0, 0.25) !important;
    }

    #checkoutModal .checkout-field {
      background: #121212 !important;
      border: 1px solid rgba(255, 102, 0, 0.35);
      color: #f2f2f2 !important;
      border-radius: 8px;
      padding: 11px 12px;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    #checkoutModal .checkout-field:focus {
      border-color: #ffcc00;
      box-shadow: 0 0 0 0.18rem rgba(255, 204, 0, 0.2);
    }

    #checkoutModal .form-select.checkout-field option {
      background: #111111;
      color: #f5f5f5;
    }

    #checkoutModal .payment-hint {
      font-size: 12px;
      color: #bdbdbd;
      margin-top: 6px;
    }

    #checkoutModal .payment-method-card {
      border: 1px solid rgba(255, 102, 0, 0.25);
      background: rgba(255, 255, 255, 0.03);
      border-radius: 10px;
      padding: 10px 12px;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 10px;
    }

    #checkoutModal .payment-method-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #111;
      background: linear-gradient(135deg, #ffcc00, #ff8c00);
      box-shadow: 0 10px 24px rgba(255, 140, 0, 0.25);
      font-size: 18px;
      flex: 0 0 40px;
    }

    .cart-layout-shell {
      align-items: flex-start;
    }

    .cart-list-panel .product-card {
      border: 1px solid rgba(255, 102, 0, 0.2);
      background: linear-gradient(165deg, rgba(23, 23, 23, 0.96), rgba(10, 10, 10, 0.95));
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
      transition: transform 0.24s ease, border-color 0.24s ease, box-shadow 0.24s ease;
    }

    .cart-item-row:hover {
      transform: translateY(-1px);
      border-color: rgba(255, 204, 0, 0.3);
      box-shadow: 0 16px 30px rgba(0, 0, 0, 0.32);
    }

    .cart-item-row.is-busy {
      opacity: 0.72;
      transform: scale(0.995);
      pointer-events: none;
    }

    .cart-item-row.is-updated {
      animation: cartItemPulse 0.52s ease;
    }

    .cart-item-row.is-cooldown {
      border-color: rgba(255, 153, 61, 0.28);
    }

    .cart-qty-control {
      border: 1px solid rgba(255, 102, 0, 0.18);
      border-radius: 999px;
      padding: 5px 7px;
      background: rgba(255, 102, 0, 0.07);
      transition: border-color 0.24s ease, background-color 0.24s ease;
    }

    .cart-item-row.is-cooldown .cart-qty-control {
      border-color: rgba(255, 204, 0, 0.36);
      background: rgba(255, 204, 0, 0.1);
    }

    .cart-qty-control .change-qty {
      min-width: 34px;
      min-height: 34px;
      border-radius: 999px;
      font-size: 1rem;
      line-height: 1;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 0 !important;
      background: transparent !important;
      box-shadow: none !important;
      color: #ffd7b5 !important;
      transition: color 0.2s ease, transform 0.2s ease;
    }

    .cart-qty-control .change-qty:hover,
    .cart-qty-control .change-qty:focus-visible {
      color: #ffcc00 !important;
      background: transparent !important;
      border: 0 !important;
      transform: translateY(-1px);
      outline: none;
    }

    .cart-qty-control .change-qty:disabled {
      color: #7f7f7f !important;
      opacity: 0.65;
      transform: none;
    }

    .cart-qty-value {
      min-width: 26px;
      display: inline-block;
      text-align: center;
      font-family: 'JetBrains Mono', monospace;
      transition: transform 0.2s ease, color 0.2s ease;
    }

    .cart-item-row.is-updated .cart-qty-value {
      animation: qtyValuePop 0.34s ease;
    }

    .cart-qty-hint {
      min-height: 1.2em;
      letter-spacing: 0.01em;
    }

    .cart-item-row.is-cooldown .cart-qty-hint {
      color: #ffcfa1 !important;
    }

    .summary-sticky {
      position: sticky;
      top: 96px;
    }

    .cart-summary-card {
      border: 1px solid rgba(255, 102, 0, 0.24);
      box-shadow: 0 18px 30px rgba(0, 0, 0, 0.35);
      background: linear-gradient(160deg, rgba(21, 21, 21, 0.98), rgba(8, 8, 8, 0.96));
    }

    .checkout-guide-card {
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 14px;
      background: linear-gradient(150deg, rgba(20, 20, 20, 0.95), rgba(8, 8, 8, 0.95));
      padding: 14px;
      height: 100%;
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.28);
    }

    .checkout-guide-step {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 2px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255, 204, 0, 0.35);
      background: rgba(255, 204, 0, 0.1);
      color: #ffd27a;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .checkout-guide-card h3 {
      color: #fff;
      font-size: 0.98rem;
      margin-bottom: 6px;
    }

    .checkout-guide-card p {
      color: #b6b6b6;
      margin-bottom: 0;
      font-size: 0.84rem;
      line-height: 1.5;
    }

    .checkout-precaution-panel {
      border: 1px solid rgba(255, 153, 61, 0.3);
      border-radius: 16px;
      background: linear-gradient(145deg, rgba(25, 22, 18, 0.92), rgba(12, 10, 8, 0.95));
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.3);
      padding: 16px;
    }

    .checkout-precaution-panel h3 {
      color: #ffd7a8;
      font-size: 1.02rem;
      margin-bottom: 12px;
    }

    .checkout-precaution-list {
      list-style: none;
      padding-left: 0;
      margin: 0;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px 12px;
    }

    .checkout-precaution-list li {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      color: #d0d0d0;
      font-size: 0.84rem;
      line-height: 1.45;
    }

    .checkout-precaution-list i {
      color: #ffb86b;
      margin-top: 1px;
    }

    #checkoutModal .checkout-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px 12px;
    }

    #checkoutModal .checkout-span-full {
      grid-column: 1 / -1;
    }

    #checkoutModal .modal-title {
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 0.9rem;
      font-weight: 700;
    }

    @keyframes cartItemPulse {
      0% {
        box-shadow: 0 0 0 rgba(255, 122, 26, 0.55);
      }

      50% {
        box-shadow: 0 0 0 2px rgba(255, 122, 26, 0.24);
      }

      100% {
        box-shadow: 0 0 0 rgba(255, 122, 26, 0);
      }
    }

    @keyframes qtyValuePop {
      0% {
        transform: scale(0.9);
      }

      55% {
        transform: scale(1.08);
      }

      100% {
        transform: scale(1);
      }
    }

    @media (max-width: 991px) {
      .summary-sticky {
        position: static;
      }
    }

    @media (max-width: 767px) {
      #checkoutModal .checkout-form-grid {
        grid-template-columns: 1fr;
      }

      .checkout-precaution-list {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body class="dark-theme">
  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="successAlert"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="navbar-logo me-2" />
          <span class="brand-text">COMMERZA</span>
        </a>
        <div class="d-flex align-items-center order-lg-2">
          <ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" aria-current="page" href="cart.php" aria-label="View cart">
                <i class="bi bi-cart3" id="cart-icon"></i>
                <span class="nav-badge" id="cart-count">0</span>
              </a>
            </li>
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" href="wishlist.php" aria-label="View wishlist">
                <i class="bi bi-heart"></i>
                <span class="nav-badge" id="wishlist-count">0</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link nav-icon-link" href="account.php" aria-label="Account"><i
                  class="bi bi-person"></i></a>
            </li>
          </ul>
          <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas"
            aria-controls="navbarOffcanvas" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        </div>
        <div class="collapse navbar-collapse order-lg-1" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="about.php">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="contact.php">Contact</a>
            </li>
            <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
            <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="offcanvas-logo me-2" />
          <span class="brand-text">COMMERZA</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="offcanvas-user-actions">
          <a href="cart.php" class="offcanvas-action-btn" aria-current="page">
            <i class="bi bi-cart3"></i>
            <span>Cart</span>
            <span class="offcanvas-badge" id="cart-count-mobile">0</span>
          </a>
          <a href="wishlist.php" class="offcanvas-action-btn">
            <i class="bi bi-heart"></i>
            <span>Wishlist</span>
            <span class="offcanvas-badge" id="wishlist-count-mobile">0</span>
          </a>
          <a href="account.php" class="offcanvas-action-btn">
            <i class="bi bi-person"></i>
            <span>Account</span>
          </a>
        </div>
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.php">Contact</a>
          </li>
          <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
          <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="container my-5">
    <?php commerza_render_page_breadcrumb('Cart'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-cart3"></i> Checkout</span>
        <h1 class="mt-3" style="color: #ff6600">Shopping Cart</h1>
        <p class="product-desc mt-2">Review your items and complete your order securely.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="index.php" class="btn product-btn-buy">Continue Shopping</a>
          <a href="shipping.php" class="btn product-btn-cart">Shipping Info</a>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-shield-check"></i></div>
            <h3 class="product-name">Secure Checkout</h3>
            <p class="product-desc">Your order details are protected.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-truck"></i></div>
            <h3 class="product-name">Fast Delivery</h3>
            <p class="product-desc">Nationwide shipping with tracking.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-arrow-counterclockwise"></i></div>
            <h3 class="product-name">Easy Returns</h3>
            <p class="product-desc">Simple 7-day return window.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Checkout guide">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0" style="color: #ff6600; font-size: 1.2rem;">Step-by-Step Checkout Guide</h2>
        <span class="step-chip">Follow these steps for a smooth order flow.</span>
      </div>
      <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
          <article class="checkout-guide-card">
            <span class="checkout-guide-step">Step 1</span>
            <h3>Review Cart Items</h3>
            <p>Check product names, quantity, and line totals before moving ahead.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="checkout-guide-card">
            <span class="checkout-guide-step">Step 2</span>
            <h3>Apply Coupon</h3>
            <p>Add your valid coupon code and confirm the discount appears in summary.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="checkout-guide-card">
            <span class="checkout-guide-step">Step 3</span>
            <h3>Verify Address & Contact</h3>
            <p>Use an active phone number and complete address to avoid dispatch delays.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="checkout-guide-card">
            <span class="checkout-guide-step">Step 4</span>
            <h3>Select Payment Method</h3>
            <p>Use COD, complete CAPTCHA, then submit your final order.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Checkout precautions">
      <div class="checkout-precaution-panel">
        <h3><i class="bi bi-exclamation-triangle me-2"></i>Precautions Before Placing Order</h3>
        <ul class="checkout-precaution-list">
          <li><i class="bi bi-check2-circle"></i><span>Keep quantity realistic to avoid stock conflicts during high demand.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Double-check phone and address because dispatch labels use this exact data.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Keep your phone active because COD confirmation may require a call.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Review final total after coupon application before clicking Complete Order.</span></li>
        </ul>
      </div>
    </section>

    <div id="customAlert" class="alert alert-danger text-center"
      style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
      Cart Full: Your 10-item limit has been reached.
    </div>

    <div id="checkoutEmptyAlert" role="alert" aria-live="assertive">
      Your cart is empty. Please add products before checkout.
    </div>

    <h2 class="mb-4" style="color: #ff6600">Step 5: Confirm Cart Items</h2>

    <div class="row cart-layout-shell">
      <div class="col-lg-8 mb-4 cart-list-panel" id="cart-items-container"></div>

      <div class="col-lg-4 summary-sticky">
        <div class="card product-card cart-summary-card">
          <div class="card-body">
            <h4 class="mb-3" style="color: #ff6600">Order Summary</h4>
            <p class="text-white mb-3">Total Items: <span id="total-items-qty">0</span></p>

            <div class="mb-3">
              <label for="couponCodeInput" class="form-label text-white mb-2">Coupon Code</label>
              <div class="input-group">
                <input type="text" class="form-control checkout-field" id="couponCodeInput" placeholder="Enter coupon code" maxlength="50" autocomplete="off">
                <button class="btn btn-outline-warning" type="button" id="applyCouponBtn">Apply</button>
              </div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <button class="btn btn-sm btn-outline-secondary d-none" type="button" id="removeCouponBtn">Remove</button>
                <span id="couponStatusText" class="small text-secondary"></span>
              </div>
            </div>

            <div class="d-flex justify-content-between mb-2">
              <span>Subtotal</span>
              <span id="cart-subtotal">0 PKR</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Shipping</span>
              <span id="cart-shipping"></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span>Discount</span>
              <span id="cart-discount">0 PKR</span>
            </div>
            <hr />
            <div class="d-flex justify-content-between mb-3">
              <strong>Total</strong>
              <strong id="cart-total" style="color: #e05e00">0 PKR</strong>
            </div>
            <button class="btn product-btn-buy w-100" data-bs-toggle="modal" data-bs-target="#checkoutModal">Proceed to
              Checkout</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="checkoutModalLabel" style="color: #ff6600;">Checkout</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="checkoutForm" method="POST" action="cart.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="place_order">
            <input type="hidden" name="request_id" id="checkoutRequestId" value="<?= htmlspecialchars($checkout_request_id) ?>">
            <input type="hidden" name="coupon_code" id="checkoutCouponCode" value="">

            <div class="checkout-form-grid">
              <div class="mb-3">
                <label for="customerName" class="form-label" style="color: #fff;">Full Name *</label>
                <input type="text" class="form-control checkout-field" id="customerName" name="customer_name" maxlength="100"
                  required
                  placeholder="Enter your full name" value="<?= htmlspecialchars((string)$current_user['full_name']) ?>">
              </div>

              <div class="mb-3">
                <label for="customerEmail" class="form-label" style="color: #fff;">Email *</label>
                <input type="email" class="form-control checkout-field" id="customerEmail" name="customer_email" maxlength="150"
                  required
                  placeholder="Enter your email address" value="<?= htmlspecialchars((string)$current_user['email']) ?>">
              </div>

              <div class="mb-3">
                <label for="customerPhone" class="form-label" style="color: #fff;">Phone Number *</label>
                <input type="tel" class="form-control checkout-field" id="customerPhone" name="customer_phone"
                  required minlength="11"
                  maxlength="15" placeholder="Enter your phone number" value="<?= htmlspecialchars((string)$current_user['phone']) ?>">
              </div>

              <div class="mb-3 checkout-span-full">
                <label for="customerAddress" class="form-label" style="color: #fff;">Address *</label>
                <textarea class="form-control checkout-field" id="customerAddress" name="customer_address" rows="3"
                  required
                  placeholder="Enter your address"><?= htmlspecialchars((string)$current_user['address']) ?></textarea>
              </div>

              <div class="mb-3 checkout-span-full">
                <label for="paymentMethod" class="form-label" style="color: #fff;">Payment Method *</label>
                <select class="form-select checkout-field" id="paymentMethod" name="payment_method" required>
                  <option value="cod">COD - Cash on Delivery</option>
                </select>
                <p class="payment-hint">COD is currently the only available payment method.</p>
                <div id="paymentMethodCard" class="payment-method-card" aria-live="polite">
                  <div class="payment-method-icon"><i id="paymentMethodIcon" class="bi bi-cash-coin"></i></div>
                  <div>
                    <p id="paymentMethodTitle" class="mb-1 text-white fw-bold">Cash on Delivery</p>
                    <p id="paymentMethodDesc" class="payment-hint mb-0">Pay when your order reaches your doorstep.</p>
                  </div>
                </div>
              </div>

              <div class="checkout-span-full">
                <?= commerza_captcha_widget_html($con, 'checkout_place_order') ?>
                <div id="checkoutCaptchaError" class="small text-danger mt-2" aria-live="polite"></div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" form="checkoutForm" class="btn product-btn-buy" id="completeCheckoutBtn">Complete Order</button>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Commerza</h3>
          <p class="footer-text">
            Premium watches and accessories for the modern lifestyle. Quality
            craftsmanship meets contemporary design.
          </p>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Quick Links</h3>
          <ul class="footer-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="contact.php">Contact</a></li>
            <li><a href="wishlist.php">Wishlist</a></li>
            <li><a href="order-tracking.php">Order Tracking</a></li>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Customer Service</h3>
          <ul class="footer-links">
            <li><a href="shipping.php">Shipping Info</a></li>
            <li><a href="returns.php">Returns</a></li>
            <li><a href="faq.php">FAQ</a></li>
            <li><a href="warranty.php">Warranty</a></li>
            <li><a href="terms-of-service.php">Terms of Service</a></li>
            <li><a href="privacy-policy.php">Privacy Policy</a></li>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Connect</h3>
          <div class="social-links">
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="Commerza on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="Commerza on Instagram"><i
                class="bi bi-instagram"></i></a>
          </div>
          <p class="footer-text mt-3">Email: commerza.ahmer@gmail.com</p>
          <p class="footer-text">Phone: +92 314 8396293</p>
        </div>
      </div>
      <div class="row">
        <div class="col-12 text-center py-3 border-top">
          <p class="footer-copyright">
            &copy; 2026 Commerza. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script <?= commerza_csp_nonce_attr() ?>>
    window.CommerzaUseServerCheckout = true;
  </script>
  <script src="frontend/assets/js/script.js"></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function() {
      const isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
      const captchaEnabled = <?= $checkoutCaptchaEnabled ? 'true' : 'false' ?>;
      const captchaFieldName = <?= json_encode($checkoutCaptchaField) ?>;
      const prefillData = {
        name: <?= json_encode((string)$current_user['full_name']) ?>,
        email: <?= json_encode((string)$current_user['email']) ?>,
        phone: <?= json_encode((string)$current_user['phone']) ?>,
        address: <?= json_encode((string)$current_user['address']) ?>
      };

      function buildRequestId(scope) {
        const prefix = (scope || 'checkout').toString().replace(/[^a-z0-9_-]/gi, '').toLowerCase();
        const timePart = Date.now().toString(36);

        let randomPart = '';
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
          const bytes = new Uint8Array(8);
          window.crypto.getRandomValues(bytes);
          randomPart = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
        } else {
          randomPart = Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2);
        }

        return `${prefix}-${timePart}-${randomPart}`;
      }

      function updatePaymentMethodPreview() {
        const selected = {
          icon: 'bi-cash-coin',
          title: 'Cash on Delivery',
          desc: 'Pay when your order reaches your doorstep.'
        };
        $('#paymentMethodIcon').attr('class', `bi ${selected.icon}`);
        $('#paymentMethodTitle').text(selected.title);
        $('#paymentMethodDesc').text(selected.desc);
      }

      $("#serverAlert, #successAlert").each(function() {
        const element = $(this);
        setTimeout(function() {
          element.fadeOut(400);
        }, 3500);
      });

      function showCheckoutEmptyAlert() {
        const alertBox = $('#checkoutEmptyAlert');
        alertBox.stop(true, true).fadeIn(220).delay(2200).fadeOut(280);
      }

      function readCaptchaToken() {
        if (!captchaEnabled || !captchaFieldName) {
          return 'ok';
        }

        const field = document.querySelector(`#checkoutForm [name="${captchaFieldName}"]`);
        if (!field) {
          return '';
        }

        return (field.value || '').toString().trim();
      }

      function setCheckoutCaptchaError(message) {
        $('#checkoutCaptchaError').text((message || '').toString());
      }

      $('#checkoutModal').on('show.bs.modal', function(event) {
        const totalItems = parseInt($('#total-items-qty').text(), 10) || 0;

        if (totalItems <= 0) {
          showCheckoutEmptyAlert();
          event.preventDefault();
          return;
        }

        if (!isLoggedIn) {
          window.location.href = 'login.php?redirect=cart.php';
          event.preventDefault();
          return;
        }

        if (!$('#customerName').val()) {
          $('#customerName').val(prefillData.name || '');
        }
        if (!$('#customerEmail').val()) {
          $('#customerEmail').val(prefillData.email || '');
        }
        if (!$('#customerPhone').val()) {
          $('#customerPhone').val(prefillData.phone || '');
        }
        if (!$('#customerAddress').val()) {
          $('#customerAddress').val(prefillData.address || '');
        }

        $('#paymentMethod').val('cod');
        updatePaymentMethodPreview();
      });

      $('#checkoutForm').on('submit', function(event) {
        if (!$('#checkoutRequestId').val()) {
          $('#checkoutRequestId').val(buildRequestId('checkout_place_order'));
        }

        setCheckoutCaptchaError('');

        const totalItems = parseInt($('#total-items-qty').text(), 10) || 0;
        const submitBtn = $('#completeCheckoutBtn');

        if (captchaEnabled && readCaptchaToken() === '') {
          event.preventDefault();
          setCheckoutCaptchaError('Please complete the CAPTCHA challenge before checkout.');
          return;
        }

        if (totalItems <= 0) {
          showCheckoutEmptyAlert();
          event.preventDefault();
          return;
        }

        if (!this.checkValidity()) {
          this.reportValidity();
          event.preventDefault();
          return;
        }

        submitBtn.prop('disabled', true).text('Placing Order...');
      });
    });
  </script>
</body>

</html>