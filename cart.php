<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/core/data.php';
require_once __DIR__ . '/backend/helpers/cart_helpers.php';
require_once __DIR__ . '/backend/helpers/notifications.php';
require_once __DIR__ . '/backend/helpers/coupon_helpers.php';

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

function cart_json_response(array $payload, int $statusCode = 200): void
{
  http_response_code($statusCode);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

function cart_cod_otp_session_key(): string
{
  return 'checkout_cod_otp_pending';
}

function cart_cod_otp_pending_get(): ?array
{
  $pending = $_SESSION[cart_cod_otp_session_key()] ?? null;
  return is_array($pending) ? $pending : null;
}

function cart_cod_otp_pending_set(array $pending): void
{
  $_SESSION[cart_cod_otp_session_key()] = $pending;
}

function cart_cod_otp_pending_clear(): void
{
  unset($_SESSION[cart_cod_otp_session_key()]);
}

function cart_cod_high_value_threshold_amount(): float
{
  $configured = trim((string)getenv('COMMERZA_COD_OTP_THRESHOLD'));
  $threshold = is_numeric($configured) ? (float)$configured : 15000.0;
  if ($threshold < 0) {
    $threshold = 0;
  }

  return round($threshold, 2);
}

function cart_cod_high_value_hard_limit_amount(): float
{
  $configured = trim((string)getenv('COMMERZA_COD_HIGH_VALUE_HARD_LIMIT'));
  if (!is_numeric($configured)) {
    return 0.0;
  }

  $limit = (float)$configured;
  if ($limit <= 0) {
    return 0.0;
  }

  return round($limit, 2);
}

function cart_cod_otp_required(string $paymentMethod, float $grandTotal): bool
{
  if (strtolower(trim($paymentMethod)) !== 'cod') {
    return false;
  }

  $threshold = cart_cod_high_value_threshold_amount();
  if ($threshold <= 0) {
    return false;
  }

  return round($grandTotal, 2) >= $threshold;
}

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

function cart_checkout_rate_limit_error(mysqli $con, bool $isLoggedIn): ?string
{
  $identifier = 'guest';
  if ($isLoggedIn) {
    $identifier = 'user_' . (int)($_SESSION['user_id'] ?? 0);
  } else {
    $sessionId = trim((string)session_id());
    if ($sessionId !== '') {
      $identifier = 'session_' . substr($sessionId, 0, 64);
    }
  }

  $clientIp = commerza_client_ip();
  $rate = commerza_rate_limit_check(
    $con,
    'checkout_place_order_page',
    $identifier,
    $clientIp,
    8,
    600,
    600,
    1800
  );

  if ((bool)($rate['allowed'] ?? true)) {
    return null;
  }

  $retrySeconds = max(1, (int)($rate['retry_after'] ?? 600));
  if (function_exists('commerza_security_log_rate_limit_block')) {
    commerza_security_log_rate_limit_block(
      $con,
      'checkout_place_order_page',
      $isLoggedIn ? 'user' : 'guest',
      $identifier,
      $clientIp,
      $retrySeconds
    );
  }

  return 'Too many checkout attempts. Please wait ' . $retrySeconds . ' second(s) and try again.';
}

$codHighValueThreshold = cart_cod_high_value_threshold_amount();
$codHighValueHardLimit = cart_cod_high_value_hard_limit_amount();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'request_cod_otp') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    cart_json_response([
      'ok' => false,
      'message' => 'Forbidden.',
    ], 403);
  }

  if (!$is_logged_in) {
    cart_json_response([
      'ok' => false,
      'message' => 'Please login before requesting COD verification.',
    ], 401);
  }

  $user_id = (int)($_SESSION['user_id'] ?? 0);
  if ($user_id <= 0) {
    cart_json_response([
      'ok' => false,
      'message' => 'Please login before requesting COD verification.',
    ], 401);
  }

  $paymentMethod = strtolower(trim((string)($_POST['payment_method'] ?? 'cod')));
  if ($paymentMethod !== 'cod') {
    cart_json_response([
      'ok' => false,
      'message' => 'COD verification is available only for Cash on Delivery orders.',
    ], 422);
  }

  if ($codHighValueThreshold <= 0) {
    cart_json_response([
      'ok' => false,
      'message' => 'COD verification is not required for this store configuration.',
    ], 422);
  }

  $customerName = trim((string)($_POST['customer_name'] ?? ''));
  $customerEmail = strtolower(trim((string)($_POST['customer_email'] ?? '')));
  if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL) || strlen($customerEmail) > 150) {
    cart_json_response([
      'ok' => false,
      'message' => 'Enter a valid checkout email before requesting COD verification.',
    ], 422);
  }

  $accountEmail = strtolower(trim((string)($current_user['email'] ?? '')));
  if ($accountEmail !== '' && $customerEmail !== $accountEmail) {
    cart_json_response([
      'ok' => false,
      'message' => 'For security, COD verification must use your account email address.',
    ], 422);
  }

  $clientIp = commerza_client_ip();
  $identifier = 'user_' . $user_id;
  $rate = commerza_rate_limit_check(
    $con,
    'checkout_cod_otp_send',
    $identifier,
    $clientIp,
    4,
    900,
    900,
    3600
  );

  if (!(bool)($rate['allowed'] ?? true)) {
    $retrySeconds = max(1, (int)($rate['retry_after'] ?? 900));
    if (function_exists('commerza_security_log_rate_limit_block')) {
      commerza_security_log_rate_limit_block(
        $con,
        'checkout_cod_otp_send',
        'user',
        $identifier,
        $clientIp,
        $retrySeconds
      );
    }

    cart_json_response([
      'ok' => false,
      'message' => 'Too many verification code requests. Please wait ' . $retrySeconds . ' second(s).',
    ], 429);
  }

  $pending = cart_cod_otp_pending_get();
  $lastSentAt = (int)($pending['last_sent_at'] ?? 0);
  if (
    is_array($pending)
    && (int)($pending['user_id'] ?? 0) === $user_id
    && strtolower(trim((string)($pending['email'] ?? ''))) === $customerEmail
    && $lastSentAt > 0
    && (time() - $lastSentAt) < 60
  ) {
    $wait = max(1, 60 - (time() - $lastSentAt));
    cart_json_response([
      'ok' => false,
      'message' => 'Please wait ' . $wait . ' second(s) before requesting another code.',
    ], 429);
  }

  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  $orderTotalHint = (float)($_POST['order_total'] ?? 0);
  $mailError = null;
  $mailSent = function_exists('commerza_notify_cod_checkout_verification_code')
    ? commerza_notify_cod_checkout_verification_code(
      $con,
      $customerEmail,
      $customerName,
      $code,
      $orderTotalHint,
      $codHighValueThreshold,
      $mailError
    )
    : false;

  if (!$mailSent) {
    cart_json_response([
      'ok' => false,
      'message' => $mailError ?: 'Unable to send verification code. Please try another method or retry later.',
    ], 500);
  }

  cart_cod_otp_pending_set([
    'user_id' => $user_id,
    'email' => $customerEmail,
    'code_hash' => hash('sha256', $code),
    'attempts' => 0,
    'expires_at' => time() + (15 * 60),
    'last_sent_at' => time(),
    'verified_at' => 0,
    'verified_expires_at' => 0,
    'verified_user_id' => 0,
    'verified_email' => '',
  ]);

  cart_json_response([
    'ok' => true,
    'message' => 'Verification code sent to your email. The code is valid for 15 minutes.',
  ], 200);
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

  $checkoutRateLimitError = cart_checkout_rate_limit_error($con, $is_logged_in);
  if ($checkoutRateLimitError !== null) {
    $errors[] = $checkoutRateLimitError;
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
  $payment_sender = trim((string)($_POST['payment_sender'] ?? ''));
  $payment_reference = strtoupper(trim((string)($_POST['payment_reference'] ?? '')));

  $payment_methods = [
    'cod' => 'Cash on Delivery (COD)',
    'jazzcash' => 'JazzCash (Sandbox)',
    'easypaisa' => 'Easypaisa (Sandbox)',
    'paypal' => 'PayPal (Sandbox)',
    'stripe' => 'Stripe (Sandbox)',
    'card' => 'Credit/Debit Card (Stripe Sandbox)',
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

  if ($payment_method !== 'cod') {
    if (strlen($payment_sender) < 3 || strlen($payment_sender) > 120) {
      $errors[] = 'Payer account/wallet detail must be 3-120 characters for sandbox payments.';
    }

    if (!preg_match('/^[A-Z0-9_-]{4,80}$/', $payment_reference)) {
      $errors[] = 'Sandbox transaction reference must be 4-80 characters and can contain letters, numbers, _ or -.';
    }

    $payment_notes[] = 'Sandbox payment mode selected: ' . $payment_method_label;
    if ($payment_sender !== '') {
      $payment_notes[] = 'Sandbox payer detail: ' . $payment_sender;
    }
    if ($payment_reference !== '') {
      $payment_notes[] = 'Sandbox reference: ' . $payment_reference;
    }
  } else {
    $payment_notes[] = 'Cash on Delivery selected.';
  }

  if (empty($errors)) {
    $blockedContact = commerza_customer_blacklist_lookup($con, $customer_email, $customer_phone);

    if (!is_array($blockedContact) && $is_logged_in) {
      $accountEmail = strtolower(trim((string)($current_user['email'] ?? '')));
      $accountPhone = preg_replace('/\s+/', '', trim((string)($current_user['phone'] ?? '')));
      $accountPhone = (string)($accountPhone ?? '');
      $blockedContact = commerza_customer_blacklist_lookup($con, $accountEmail, $accountPhone);
    }

    if (is_array($blockedContact)) {
      $errors[] = commerza_customer_blacklist_feedback_message($blockedContact);
    }
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

    if ($payment_method === 'cod' && $codHighValueHardLimit > 0 && $grand_total > $codHighValueHardLimit) {
      $errors[] = 'COD is not available above PKR ' . number_format($codHighValueHardLimit, 2) . '. Please choose another payment method.';
    }

    $codOtpNeeded = cart_cod_otp_required($payment_method, $grand_total);
    if ($payment_method === 'cod' && !$codOtpNeeded) {
      cart_cod_otp_pending_clear();
    }

    if ($payment_method === 'cod' && $codOtpNeeded) {
      $otpPending = cart_cod_otp_pending_get();
      $otpInput = trim((string)($_POST['cod_email_otp'] ?? ''));
      $otpVerified = false;

      if (is_array($otpPending)) {
        $verifiedAt = (int)($otpPending['verified_at'] ?? 0);
        $verifiedExpiresAt = (int)($otpPending['verified_expires_at'] ?? 0);
        $verifiedUserId = (int)($otpPending['verified_user_id'] ?? 0);
        $verifiedEmail = strtolower(trim((string)($otpPending['verified_email'] ?? '')));

        if (
          $verifiedAt > 0
          && $verifiedExpiresAt >= time()
          && $verifiedUserId === $user_id
          && $verifiedEmail !== ''
          && $verifiedEmail === $customer_email
        ) {
          $otpVerified = true;
        }
      }

      if (!$otpVerified) {
        if (!is_array($otpPending)) {
          $errors[] = 'High-value COD orders require email verification. Request and enter the 6-digit code before placing your order.';
        } else {
          $pendingUserId = (int)($otpPending['user_id'] ?? 0);
          $pendingEmail = strtolower(trim((string)($otpPending['email'] ?? '')));
          $expiresAt = (int)($otpPending['expires_at'] ?? 0);

          if ($pendingUserId !== $user_id || $pendingEmail === '' || $pendingEmail !== $customer_email) {
            cart_cod_otp_pending_clear();
            $errors[] = 'COD verification session is invalid for this checkout email. Request a new code.';
          } elseif ($expiresAt <= 0 || $expiresAt < time()) {
            cart_cod_otp_pending_clear();
            $errors[] = 'COD verification code expired. Request a new code.';
          } elseif (!preg_match('/^\d{6}$/', $otpInput)) {
            $errors[] = 'Enter the 6-digit COD verification code sent to your email.';
          } else {
            $expectedHash = (string)($otpPending['code_hash'] ?? '');
            $enteredHash = hash('sha256', $otpInput);

            if ($expectedHash === '' || !hash_equals($expectedHash, $enteredHash)) {
              $attempts = max(0, (int)($otpPending['attempts'] ?? 0)) + 1;
              $otpPending['attempts'] = $attempts;

              if ($attempts >= 6) {
                cart_cod_otp_pending_clear();
                $errors[] = 'Too many invalid COD verification attempts. Request a new code.';
              } else {
                cart_cod_otp_pending_set($otpPending);
                $remaining = max(0, 6 - $attempts);
                $errors[] = 'Invalid COD verification code. Remaining attempts: ' . $remaining . '.';
              }
            } else {
              $otpPending['verified_at'] = time();
              $otpPending['verified_expires_at'] = time() + (5 * 60);
              $otpPending['verified_user_id'] = $user_id;
              $otpPending['verified_email'] = $customer_email;
              $otpPending['code_hash'] = '';
              $otpPending['attempts'] = 0;
              cart_cod_otp_pending_set($otpPending);
            }
          }
        }
      }

      if (empty($errors)) {
        $payment_notes[] = 'High-value COD email verification completed.';
      }
    }

    $checkout_items_signature = [];
    foreach ($normalized_items as $signature_row) {
      $checkout_items_signature[] = [
        'product_id' => (int)($signature_row['product_id'] ?? 0),
        'quantity' => (int)($signature_row['quantity'] ?? 0),
        'unit_price' => round((float)($signature_row['unit_price'] ?? 0), 2),
        'line_total' => round((float)($signature_row['line_total'] ?? 0), 2),
      ];
    }

    $checkout_guard_payload = [
      'user_id' => $user_id,
      'customer_email' => $customer_email,
      'customer_phone' => $customer_phone,
      'customer_address' => $customer_address,
      'payment_method' => $payment_method_label,
      'payment_sender' => $payment_sender,
      'payment_reference' => $payment_reference,
      'subtotal' => round($subtotal, 2),
      'shipping_cost' => round($shipping_cost, 2),
      'discount_total' => round($discount_total, 2),
      'grand_total' => round($grand_total, 2),
      'coupon_code' => $coupon_code,
      'items' => $checkout_items_signature,
    ];

    $checkout_guard_json = json_encode($checkout_guard_payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($checkout_guard_json) || $checkout_guard_json === '') {
      $checkout_guard_json = json_encode([
        'fallback' => true,
        'ts' => microtime(true),
        'user_id' => $user_id,
      ]);
    }

    $checkout_guard_request_id = hash('sha256', (string)$checkout_guard_json);
    $checkout_guard = commerza_idempotency_consume(
      $con,
      'checkout_recent_cart_' . $user_id,
      $checkout_guard_request_id,
      30
    );

    if (!(bool)($checkout_guard['ok'] ?? false)) {
      $errors[] = (bool)($checkout_guard['duplicate'] ?? false)
        ? 'Duplicate order submission detected from the same cart within 30 seconds. Please wait and try again.'
        : (string)($checkout_guard['message'] ?? 'Unable to verify duplicate checkout protection.');
    }

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

        if ($payment_method === 'cod') {
          cart_cod_otp_pending_clear();
        }

        if (function_exists('commerza_notify_order_placed')) {
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
        } else {
          commerza_security_log_event($con, [
            'event_type' => 'checkout_notification_helper_missing',
            'severity' => 'warning',
            'actor_type' => 'user',
            'actor_identifier' => (string)$customer_email,
            'user_id' => $user_id,
            'ip_address' => commerza_client_ip(),
            'details' => [
              'order_number' => $order_number,
            ],
          ]);
        }

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
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/cart-inline.css">
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
            <p>Choose COD or sandbox payment, complete CAPTCHA, then submit your final order.</p>
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
                <label for="paymentMethodBtn" class="form-label" style="color: #fff;">Payment Method *</label>
                <div class="dropdown">
                  <button class="btn payment-dropdown-toggle dropdown-toggle w-100" type="button" id="paymentMethodBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    Cash on Delivery (COD)
                  </button>
                  <ul class="dropdown-menu payment-dropdown-menu" id="paymentMethodMenu">
                    <li><a class="checkout-payment-option active" href="#" data-value="cod" data-label="Cash on Delivery (COD)" data-icon="bi-cash-coin" data-desc="Pay when your order reaches your doorstep.">Cash on Delivery (COD)<small>Pay cash when order is delivered</small></a></li>
                    <li><a class="checkout-payment-option" href="#" data-value="jazzcash" data-label="JazzCash (Sandbox)" data-icon="bi-phone" data-desc="Sandbox wallet mode. Add payer and transaction reference below.">JazzCash (Sandbox)<small>Use JazzCash test credentials only</small></a></li>
                    <li><a class="checkout-payment-option" href="#" data-value="easypaisa" data-label="Easypaisa (Sandbox)" data-icon="bi-wallet2" data-desc="Sandbox wallet mode. Add payer and transaction reference below.">Easypaisa (Sandbox)<small>Use Easypaisa sandbox flow</small></a></li>
                    <li><a class="checkout-payment-option" href="#" data-value="paypal" data-label="PayPal (Sandbox)" data-icon="bi-paypal" data-desc="Sandbox PayPal mode. Add payer and transaction reference below.">PayPal (Sandbox)<small>Use PayPal sandbox buyer account</small></a></li>
                    <li><a class="checkout-payment-option" href="#" data-value="stripe" data-label="Stripe (Sandbox)" data-icon="bi-credit-card" data-desc="Sandbox Stripe mode. Add payer and transaction reference below.">Stripe (Sandbox)<small>Use Stripe test mode details</small></a></li>
                    <li><a class="checkout-payment-option" href="#" data-value="card" data-label="Credit/Debit Card (Stripe Sandbox)" data-icon="bi-credit-card-2-front" data-desc="Stripe card sandbox mode. Add payer and transaction reference below.">Credit/Debit Card (Stripe Sandbox) <span class="checkout-payment-tag">Recommended</span><small>Best for card testing with Stripe sandbox cards</small></a></li>
                  </ul>
                </div>
                <input type="hidden" id="paymentMethod" name="payment_method" value="cod">
                <p class="payment-hint">Online methods run in sandbox mode and require a payer detail + reference.</p>
                <div id="paymentMethodCard" class="payment-method-card" aria-live="polite">
                  <div class="payment-method-icon"><i id="paymentMethodIcon" class="bi bi-cash-coin"></i></div>
                  <div>
                    <p class="mb-1 text-white fw-bold payment-method-title-row"><span id="paymentMethodTitle">Cash on Delivery</span><span id="paymentMethodBadge" class="checkout-payment-tag d-none">Recommended</span></p>
                    <p id="paymentMethodDesc" class="payment-hint mb-0">Pay when your order reaches your doorstep.</p>
                  </div>
                </div>
                <div id="paymentExtraFields" class="payment-extra-fields d-none">
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <label for="paymentSender" class="form-label text-white">Payer Wallet / Account *</label>
                      <input type="text" class="form-control checkout-field" id="paymentSender" name="payment_sender" maxlength="120" placeholder="Wallet number or sandbox account email">
                    </div>
                    <div class="col-12 col-md-6">
                      <label for="paymentReference" class="form-label text-white">Sandbox Transaction Reference *</label>
                      <input type="text" class="form-control checkout-field" id="paymentReference" name="payment_reference" maxlength="80" placeholder="Example: TEST-ORDER-1234" pattern="[A-Za-z0-9_-]{4,80}">
                    </div>
                  </div>
                  <small class="field-hint d-block mt-2">Use sandbox/test values only. Live settlement is not enabled in this flow.</small>
                </div>
                <div id="codOtpSection" class="payment-extra-fields d-none">
                  <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-8">
                      <label for="codEmailOtp" class="form-label text-white">COD Verification Code</label>
                      <input type="text" class="form-control checkout-field" id="codEmailOtp" name="cod_email_otp" maxlength="6" minlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="Enter 6-digit email code">
                    </div>
                    <div class="col-12 col-md-4">
                      <button type="button" class="btn btn-outline-warning w-100" id="sendCodOtpBtn">Send OTP</button>
                    </div>
                  </div>
                  <small id="codOtpHint" class="field-hint d-block mt-2">For COD orders of PKR <?= number_format((float)$codHighValueThreshold, 2) ?> or above, verify the code sent to your account email before placing order.</small>
                  <div id="codOtpFeedback" class="small mt-2" aria-live="polite"></div>
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
  <script src="frontend/assets/js/modules/core/global-protection.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script <?= commerza_csp_nonce_attr() ?> id="commerzaCartPageConfig" type="application/json">
    <?= json_encode([
      'isLoggedIn' => (bool)$is_logged_in,
      'captchaEnabled' => (bool)$checkoutCaptchaEnabled,
      'captchaFieldName' => (string)$checkoutCaptchaField,
      'codHighValueThreshold' => (float)$codHighValueThreshold,
      'prefillData' => [
        'name' => (string)$current_user['full_name'],
        'email' => (string)$current_user['email'],
        'phone' => (string)$current_user['phone'],
        'address' => (string)$current_user['address'],
      ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
  </script>
  <script src="frontend/assets/js/pages/cart-checkout-mode.js"></script>
  <script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js"></script>
  <script src="frontend/assets/js/pages/cart.js"></script>
</body>

</html>
