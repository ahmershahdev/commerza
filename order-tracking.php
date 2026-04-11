<?php
include "backend/data.php";

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$order_lookup = null;
$order_items = [];
$order_id_value = '';
$email_value = '';
$order_created_label = '';

function order_status_badge_class(string $status): string
{
  switch ($status) {
    case 'Delivered':
      return 'success';
    case 'Cancelled':
    case 'Refunded':
      return 'danger';
    case 'Shipped':
      return 'primary';
    case 'Processing':
    case 'Confirmed':
      return 'info';
    default:
      return 'warning';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $order_id_value = strtoupper(preg_replace('/\s+/', '', trim((string)($_POST['order_id'] ?? ''))));
  $email_value = strtolower(trim((string)($_POST['order_email'] ?? '')));

  if ($order_id_value !== '' && strpos($order_id_value, '#') !== 0) {
    $order_id_value = '#' . $order_id_value;
  }

  if (!preg_match('/^#ORD-[A-Z0-9]{4,20}$/', $order_id_value)) {
    $errors[] = 'Please enter a valid Order ID (example: #ORD-1234).';
  }

  if ($email_value === '') {
    $errors[] = 'Please enter the email used during checkout.';
  } elseif (!filter_var($email_value, FILTER_VALIDATE_EMAIL) || strlen($email_value) > 150) {
    $errors[] = 'Please enter a valid email address.';
  }

  if (empty($errors)) {
    $stmt = $con->prepare(
      "SELECT id, order_number, customer_name, customer_email, customer_phone, address, grand_total, status, payment_status, payment_method, created_at
       FROM orders
       WHERE order_number = ? AND customer_email = ?
       LIMIT 1"
    );

    if ($stmt) {
      $stmt->bind_param('ss', $order_id_value, $email_value);
    }

    if (!$stmt) {
      $errors[] = 'Unable to track your order right now. Please try again.';
    } else {
      $stmt->execute();
      $result = $stmt->get_result();
      $order_lookup = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      if (!$order_lookup) {
        $errors[] = 'No order found with the provided details.';
      } else {
        $createdAt = (string)($order_lookup['created_at'] ?? '');
        $createdAtTs = strtotime($createdAt);
        $order_created_label = $createdAtTs ? date('M d, Y h:i A', $createdAtTs) : $createdAt;

        $itemsStmt = $con->prepare(
          "SELECT product_name, product_img, unit_price, quantity, line_total
           FROM order_items
           WHERE order_id = ?
           ORDER BY id ASC"
        );

        if ($itemsStmt) {
          $orderId = (int)$order_lookup['id'];
          $itemsStmt->bind_param('i', $orderId);
          $itemsStmt->execute();
          $itemsResult = $itemsStmt->get_result();
          while ($itemsResult && $row = $itemsResult->fetch_assoc()) {
            $order_items[] = $row;
          }
          $itemsStmt->close();
        }
      }
    }
  }

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Track your Commerza order status with order ID and email lookup.">
  <meta property="og:title" content="Order Tracking | Commerza">
  <meta property="og:description" content="Track your Commerza order in real time.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/order-tracking.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Order Tracking | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/order-tracking.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Order Tracking | Commerza",
      "url": "https://commerza.ahmershah.dev/order-tracking.php",
      "description": "Track order delivery status for Commerza purchases."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/order-tracking-inline.css">
</head>

<body class="dark-theme">
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
              <a class="nav-link nav-icon-link" href="cart.php" aria-label="View cart">
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
          <a href="cart.php" class="offcanvas-action-btn">
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
    <?php commerza_render_page_breadcrumb('Order Tracking'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-search"></i> Track Order</span>
        <h1 class="mt-3" style="color: #ff6600">Order Tracking</h1>
        <p class="product-desc mt-2">Enter your order details to view live status.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="account.php" class="btn product-btn-buy">Go to Account</a>
          <a href="contact.php" class="btn product-btn-cart">Need Help</a>
        </div>
      </div>
    </section>

    <section class="tracking-shell mb-5">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="tracking-form-card">
            <div class="tracking-header">
              <span class="tracking-kicker">Order Lookup</span>
              <h2 class="tracking-title">Track your shipment</h2>
              <p class="tracking-subtitle">Use the Order ID and checkout email from your receipt.</p>
            </div>
            <form id="orderTrackingForm" class="row g-3" action="order-tracking.php" method="POST" novalidate>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <div class="col-12">
                <label for="orderIdInput" class="form-label text-white">Order ID</label>
                <input type="text" id="orderIdInput" class="form-control tracking-input" placeholder="#ORD-0000"
                  name="order_id" required value="<?= htmlspecialchars($order_id_value) ?>">
              </div>
              <div class="col-12">
                <label for="orderEmailInput" class="form-label text-white">Email</label>
                <input type="email" id="orderEmailInput" class="form-control tracking-input"
                  placeholder="you@example.com" name="order_email" required value="<?= htmlspecialchars($email_value) ?>">
              </div>
              <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn product-btn-buy" id="orderTrackingSubmitBtn">Track Order</button>
                <a href="account.php" class="btn product-btn-cart">View Orders</a>
              </div>
              <div class="col-12">
                <small class="text-secondary">Live lookup runs securely via AJAX, so this page will not reload.</small>
              </div>
            </form>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="tracking-panel">
            <div class="tracking-header">
              <span class="tracking-kicker">Tracking Steps</span>
              <h2 class="tracking-title">What happens next</h2>
              <p class="tracking-subtitle">We update statuses as soon as your parcel moves.</p>
            </div>
            <div class="tracking-steps">
              <div class="tracking-step">
                <span class="step-index">01</span>
                <div>
                  <h5>Order Confirmed</h5>
                  <p>We verify your order and prepare it for dispatch.</p>
                </div>
              </div>
              <div class="tracking-step">
                <span class="step-index">02</span>
                <div>
                  <h5>In Transit</h5>
                  <p>Your package is on its way with live updates.</p>
                </div>
              </div>
              <div class="tracking-step">
                <span class="step-index">03</span>
                <div>
                  <h5>Delivered</h5>
                  <p>Confirm delivery and enjoy your new timepiece.</p>
                </div>
              </div>
            </div>
            <div class="tracking-tips">
              <div class="tip-card">
                <i class="bi bi-shield-check"></i>
                <span>Secure lookup, no public visibility.</span>
              </div>
              <div class="tip-card">
                <i class="bi bi-headset"></i>
                <span>Need help? Our support team is ready.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Order tracking guide">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0" style="color: #ff6600; font-size: 1.2rem;">Step-by-Step Tracking Guide</h2>
        <span class="step-chip">Use this checklist for accurate tracking updates.</span>
      </div>
      <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
          <article class="order-guide-card">
            <span class="order-step-pill">Step 1</span>
            <h3>Confirm Order ID Format</h3>
            <p>Use the receipt format, for example #ORD-1234, and avoid extra spaces or symbols.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="order-guide-card">
            <span class="order-step-pill">Step 2</span>
            <h3>Use Checkout Email</h3>
            <p>Enter the same email used when placing the order so records match securely.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="order-guide-card">
            <span class="order-step-pill">Step 3</span>
            <h3>Read Status Timeline</h3>
            <p>Check status, payment note, and item list together for full order context.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="order-guide-card">
            <span class="order-step-pill">Step 4</span>
            <h3>Escalate if Delayed</h3>
            <p>If status does not move for long, contact support with order ID and email.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Order tracking precautions">
      <div class="order-precaution-panel">
        <h3><i class="bi bi-exclamation-triangle me-2"></i>Tracking Precautions</h3>
        <ul class="order-precaution-list">
          <li><i class="bi bi-check2-circle"></i><span>Status updates can lag briefly during courier handoff windows.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Use exact order email and ID; small typos will return no record.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Do not share personal address screenshots publicly when requesting support.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>If you hit too many lookups, wait and retry after the cooldown window.</span></li>
        </ul>
      </div>
    </section>

    <section id="orderTrackingResult" class="tracking-result">
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars(implode(' ', $errors)) ?>
          </div>
        <?php elseif ($order_lookup): ?>
          <div class="card product-card mb-4">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                  <h3 class="product-name mb-1"><?= htmlspecialchars((string)$order_lookup['order_number']) ?></h3>
                  <p class="product-desc mb-0">Placed on <?= htmlspecialchars($order_created_label) ?></p>
                </div>
                <span class="badge rounded-pill bg-<?= htmlspecialchars(order_status_badge_class((string)$order_lookup['status'])) ?>">
                  <?= htmlspecialchars((string)$order_lookup['status']) ?>
                </span>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <div class="p-3 rounded" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 102, 0, 0.2);">
                    <p class="mb-1 text-secondary">Customer</p>
                    <p class="mb-1 text-white fw-semibold"><?= htmlspecialchars((string)$order_lookup['customer_name']) ?></p>
                    <p class="mb-1 text-secondary"><?= htmlspecialchars((string)$order_lookup['customer_email']) ?></p>
                    <p class="mb-0 text-secondary"><?= htmlspecialchars((string)$order_lookup['customer_phone']) ?></p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded" style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(255, 102, 0, 0.2);">
                    <p class="mb-1 text-secondary">Shipping Address</p>
                    <p class="mb-0 text-white"><?= nl2br(htmlspecialchars((string)$order_lookup['address'])) ?></p>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <p class="text-secondary mb-2">Items</p>
                <?php if (empty($order_items)): ?>
                  <p class="text-secondary mb-0">No line items found for this order.</p>
                <?php else: ?>
                  <?php foreach ($order_items as $item): ?>
                    <div class="d-flex align-items-center gap-3 mb-2 p-2 rounded" style="background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.06);">
                      <?php if (!empty($item['product_img'])): ?>
                        <img src="<?= htmlspecialchars((string)$item['product_img']) ?>" alt="<?= htmlspecialchars((string)$item['product_name']) ?>" style="width: 56px; height: 56px; object-fit: cover; border-radius: 8px;">
                      <?php endif; ?>
                      <div class="flex-grow-1">
                        <p class="mb-0 text-white fw-semibold"><?= htmlspecialchars((string)$item['product_name']) ?></p>
                        <small class="text-secondary">Qty: <?= (int)$item['quantity'] ?> | Unit: <?= number_format((float)$item['unit_price'], 0) ?> PKR</small>
                      </div>
                      <p class="mb-0 text-white fw-semibold"><?= number_format((float)$item['line_total'], 0) ?> PKR</p>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top border-secondary-subtle">
                <p class="mb-0 text-secondary">Payment: <?= htmlspecialchars((string)$order_lookup['payment_method']) ?> (<?= htmlspecialchars((string)$order_lookup['payment_status']) ?>)</p>
                <p class="mb-0 text-white fw-bold">Total: <?= number_format((float)$order_lookup['grand_total'], 0) ?> PKR</p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>

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
            <li><a href="order-tracking.php" aria-current="page">Order Tracking</a></li>
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

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js"></script>
  <script src="frontend/assets/js/auth.js"></script>
  <script src="frontend/assets/js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="frontend/assets/js/pages/order-tracking.js"></script>
</body>

</html>