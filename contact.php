<?php
declare(strict_types=1);

include "backend/data.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$contact_name = '';
$contact_email = '';
$contact_subject = '';
$contact_inquiry_type = 'general';
$contact_order_ref = '';
$contact_phone = '';
$contact_preferred_reply = 'email';
$contact_message = '';

$contactInquiryLabels = [
  'general' => 'General',
  'order' => 'Order Support',
  'shipping' => 'Shipping',
  'returns' => 'Returns',
  'warranty' => 'Warranty',
  'technical' => 'Technical Issue',
  'payment' => 'Payment',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit("Forbidden.");
    }

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'contact_form');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

    $contact_name = trim((string)($_POST['contact_name'] ?? ''));
    $contact_email = strtolower(trim((string)($_POST['contact_email'] ?? '')));
    $contact_subject = trim((string)($_POST['contact_subject'] ?? ''));
    $contact_inquiry_type = strtolower(trim((string)($_POST['contact_inquiry_type'] ?? 'general')));
    $contact_order_ref = strtoupper(trim((string)($_POST['contact_order_ref'] ?? '')));
    $contact_phone = trim((string)($_POST['contact_phone'] ?? ''));
    $contact_preferred_reply = strtolower(trim((string)($_POST['contact_preferred_reply'] ?? 'email')));
    $contact_message = trim((string)($_POST['contact_message'] ?? ''));

    $allowedInquiryTypes = ['general', 'order', 'shipping', 'returns', 'warranty', 'technical', 'payment'];
    $allowedReplyModes = ['email', 'phone', 'either'];

    if (strlen($contact_name) < 3 || strlen($contact_name) > 100) {
        $errors[] = "Name must be 3-100 characters.";
    }

    if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL) || strlen($contact_email) > 150) {
        $errors[] = "Invalid email address.";
    }

    if (strlen($contact_subject) < 3 || strlen($contact_subject) > 120) {
      $errors[] = "Subject must be 3-120 characters.";
    }

    if (!in_array($contact_inquiry_type, $allowedInquiryTypes, true)) {
      $errors[] = "Invalid inquiry type selected.";
    }

    if ($contact_order_ref !== '' && !preg_match('/^#?[A-Z0-9\-]{4,32}$/', $contact_order_ref)) {
      $errors[] = "Order reference can only contain letters, numbers, and hyphens.";
    }

    if ($contact_phone !== '' && !preg_match('/^\+?[0-9\s\-]{8,20}$/', $contact_phone)) {
      $errors[] = "Phone number format is invalid.";
    }

    if (!in_array($contact_preferred_reply, $allowedReplyModes, true)) {
      $errors[] = "Invalid preferred reply option.";
    }

    if (strlen($contact_message) < 10 || strlen($contact_message) > 3000) {
        $errors[] = "Message must be 10-3000 characters.";
    }

    $clientIp = commerza_client_ip();
    $rateIdentifier = $contact_email !== '' ? $contact_email : ($clientIp !== '' ? $clientIp : 'anonymous');

    if (empty($errors)) {
      $rate = commerza_rate_limit_check(
        $con,
        'contact_form',
        $rateIdentifier,
        $clientIp,
        2,
        2700,
        2700,
        14400,
        86400
      );

      if (!$rate['allowed']) {
        $retrySeconds = max(1, (int)$rate['retry_after']);
        $retryMinutes = (int)ceil($retrySeconds / 60);
        commerza_security_log_rate_limit_block(
          $con,
          'contact_form',
          'user',
          $contact_email !== '' ? $contact_email : 'anonymous',
          $clientIp,
          $retrySeconds
        );
        $errors[] = "Too many messages sent. Try again in " . $retryMinutes . " minute(s) (" . $retrySeconds . " seconds).";
      }
    }

    if (empty($errors)) {
        $dupStmt = $con->prepare("SELECT id FROM contact_messages WHERE email = ? AND message = ? AND created_at >= (NOW() - INTERVAL 10 MINUTE) LIMIT 1");

        if (!$dupStmt) {
            $errors[] = "Something went wrong. Please try again.";
        } else {
            $dupStmt->bind_param("ss", $contact_email, $contact_message);
            $dupStmt->execute();
            $dupResult = $dupStmt->get_result();

            if ($dupResult && $dupResult->num_rows > 0) {
                $errors[] = "Duplicate message detected. Please wait before submitting again.";
            }

            $dupStmt->close();
        }
    }

    if (empty($errors)) {
      $ip_value = $clientIp !== '' ? $clientIp : null;
      $contactMessagePayload =
        "Inquiry Type: " . ucfirst($contact_inquiry_type) . "\n" .
        "Subject: " . $contact_subject . "\n" .
        "Order Ref: " . ($contact_order_ref !== '' ? $contact_order_ref : 'Not provided') . "\n" .
        "Preferred Reply: " . ucfirst($contact_preferred_reply) . "\n" .
        "Phone: " . ($contact_phone !== '' ? $contact_phone : 'Not provided') . "\n\n" .
        "Message:\n" . $contact_message;

        $insertStmt = $con->prepare("INSERT INTO contact_messages (name, email, message, ip_address) VALUES (?, ?, ?, ?)");

        if (!$insertStmt) {
            $errors[] = "Something went wrong. Please try again.";
        } else {
            $insertStmt->bind_param("ssss", $contact_name, $contact_email, $contactMessagePayload, $ip_value);

            if ($insertStmt->execute()) {
              commerza_security_log_event($con, [
                'event_type' => 'contact_message_submitted',
                'severity' => 'info',
                'actor_type' => 'user',
                'actor_identifier' => $contact_email,
                'ip_address' => $clientIp,
              ]);
                commerza_rate_limit_reset($con, 'contact_form', $rateIdentifier, $clientIp);
                $success = "Message sent successfully. We will get back to you soon.";
                $contact_name = '';
                $contact_email = '';
                $contact_subject = '';
                $contact_inquiry_type = 'general';
                $contact_order_ref = '';
                $contact_phone = '';
                $contact_preferred_reply = 'email';
                $contact_message = '';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $errors[] = "Something went wrong. Please try again.";
            }

            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="Contact Commerza - Get in touch with our customer service team. We're here to help with inquiries about our premium watch collection.">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="Contact Us | Commerza">
  <meta property="og:description" content="Get in touch with Commerza customer service for premium watch inquiries.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/contact.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Contact Us | Commerza">
  <meta name="twitter:description" content="Get in touch with Commerza customer service for premium watch inquiries.">
  <meta name="twitter:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Contact Us | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/contact.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ContactPage",
      "name": "Contact Us | Commerza",
      "url": "https://commerza.ahmershah.dev/contact.php",
      "description": "Contact Commerza customer support and sales team."
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
    label {
      color: white;
      user-select: none;
    }

    .contact-input {
      background-color: #ffffff;
      border: 1px solid #a01818 !important;
      border-radius: 12px;
      color: #111111;
      padding: 10px 20px;
    }

    .contact-input:focus {
      border: 2px solid orangered !important;
      box-shadow: none;
    }

    .contact-touch-list {
      display: grid;
      gap: 0.65rem;
    }

    .contact-touch-item {
      display: grid;
      grid-template-columns: 38px 1fr;
      gap: 10px;
      align-items: flex-start;
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 12px;
      background: rgba(8, 8, 8, 0.72);
      padding: 10px 12px;
    }

    .contact-touch-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(255, 102, 0, 0.35);
      background: rgba(255, 102, 0, 0.14);
      color: #ff6600;
      font-size: 1rem;
    }

    .contact-touch-meta-title {
      margin: 0;
      color: #ffcc9f;
      font-size: 0.75rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 700;
    }

    .contact-touch-meta-value {
      margin: 0;
      color: #d6d6d6;
      font-size: 0.92rem;
      line-height: 1.35;
    }

    .contact-touch-meta-value a {
      color: #ffd5b8;
      font-weight: 600;
      text-decoration: none;
    }

    .contact-touch-meta-value a:hover,
    .contact-touch-meta-value a:focus-visible {
      color: #ffcc00;
      text-decoration: underline;
      outline: none;
    }

    .contact-touch-social {
      margin-top: 0.25rem;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      border-top: 1px dashed rgba(255, 102, 0, 0.25);
      padding-top: 10px;
    }

    .contact-touch-note {
      margin: 0;
      color: #b8b8b8;
      font-size: 0.82rem;
    }

    .contact-dropdown-toggle {
      background-color: #ffffff;
      border: 1px solid #a01818 !important;
      border-radius: 12px;
      color: #111111;
      padding: 10px 20px;
      text-align: left;
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
    }

    .contact-dropdown-toggle:focus,
    .contact-dropdown-toggle:active {
      border: 2px solid orangered !important;
      box-shadow: none !important;
      color: #111111;
      background-color: #ffffff;
    }

    .contact-dropdown-menu {
      background: #111;
      border: 1px solid rgba(255, 102, 0, 0.35);
      border-radius: 10px;
      overflow: hidden;
      padding: 4px;
    }

    .contact-dropdown-menu .dropdown-item {
      color: #f2f2f2;
      border-radius: 8px;
      padding: 8px 10px;
    }

    .contact-dropdown-menu .dropdown-item:hover,
    .contact-dropdown-menu .dropdown-item:focus,
    .contact-dropdown-menu .dropdown-item.active {
      background: rgba(255, 102, 0, 0.16);
      color: #ffcfab;
    }

    #serverAlert,
    #successAlert {
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
      width: 420px;
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
              <a class="nav-link" aria-current="page" href="contact.php">Contact</a>
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
            <a class="nav-link" aria-current="page" href="contact.php">Contact</a>
          </li>
          <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
          <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="container my-5">
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-chat-dots"></i> Support</span>
        <h1 class="mt-3" style="color: #ff6600">Contact Commerza</h1>
        <p class="product-desc mt-2">We’re here to help with orders, products, and warranty questions.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="#contactForm" class="btn product-btn-buy">Contact Us</a>
          <a href="tel:+923148396293" class="btn product-btn-cart">Call Support</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-lightning-charge"></i></div>
            <h3 class="product-name">Fast Responses</h3>
            <p class="product-desc">We reply within 24 hours on business days.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-shield-check"></i></div>
            <h3 class="product-name">Secure Support</h3>
            <p class="product-desc">Your information stays private and protected.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-geo-alt"></i></div>
            <h3 class="product-name">Based in Pakistan</h3>
            <p class="product-desc">Serving customers nationwide with care.</p>
          </div>
        </div>
      </div>
    </section>

    <div class="text-center mb-5">
      <h1 style="color: #ff6600">Contact Us</h1>
      <p class="product-desc mt-3">
        Have questions or need help? We’d love to hear from you.
      </p>
    </div>

    <div class="row">
      <div class="col-md-5 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-4">Get in Touch</h4>

            <div class="contact-touch-list">
              <div class="contact-touch-item">
                <span class="contact-touch-icon"><i class="bi bi-geo-alt"></i></span>
                <div>
                  <p class="contact-touch-meta-title">Address</p>
                  <p class="contact-touch-meta-value" id="contactAddress">Barrage Colony, HYD, PK</p>
                </div>
              </div>

              <div class="contact-touch-item">
                <span class="contact-touch-icon"><i class="bi bi-envelope"></i></span>
                <div>
                  <p class="contact-touch-meta-title">Email</p>
                  <p class="contact-touch-meta-value"><a id="contactEmail" href="mailto:commerza.ahmer@gmail.com">commerza.ahmer@gmail.com</a></p>
                </div>
              </div>

              <div class="contact-touch-item">
                <span class="contact-touch-icon"><i class="bi bi-telephone"></i></span>
                <div>
                  <p class="contact-touch-meta-title">Phone</p>
                  <p class="contact-touch-meta-value"><a id="contactPhone" href="tel:+923148396293">+92 314 8396293</a></p>
                </div>
              </div>

              <div class="contact-touch-social">
                <p class="contact-touch-note">Average response time: within 24 hours.</p>
                <div class="social-links">
                <a href="https://www.facebook.com/commerza.ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on Facebook"><i
                    class="bi bi-facebook"></i></a>
                <a href="https://x.com/commerza_ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on X"><i
                    class="bi bi-twitter"></i></a>
                <a href="https://www.instagram.com/commerza.ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on Instagram"><i
                    class="bi bi-instagram"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-7 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-4">Send a Message</h4>

            <form action="contact.php" method="POST" id="contactForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

              <div class="mb-3">
                <label for="contact-name" class="form-label">Your Name</label>
                <input type="text" id="contact-name" name="contact_name" class="form-control contact-input"
                  placeholder="Enter your name" required autofocus autocomplete="name" minlength="3" maxlength="100"
                  value="<?= htmlspecialchars($contact_name) ?>" />
              </div>

              <div class="mb-3">
                <label for="contact-email" class="form-label">Email Address</label>
                <input type="email" id="contact-email" name="contact_email" class="form-control contact-input"
                  placeholder="Enter your email" required autocomplete="email" maxlength="150"
                  value="<?= htmlspecialchars($contact_email) ?>" />
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="contact-inquiry-type" class="form-label">Inquiry Type</label>
                  <div class="dropdown contact-inquiry-dropdown">
                    <button
                      class="btn contact-dropdown-toggle dropdown-toggle"
                      type="button"
                      id="contactInquiryDropdownBtn"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                    >
                      <span id="contactInquiryLabel"><?= htmlspecialchars($contactInquiryLabels[$contact_inquiry_type] ?? $contactInquiryLabels['general']) ?></span>
                    </button>
                    <ul class="dropdown-menu contact-dropdown-menu w-100" aria-labelledby="contactInquiryDropdownBtn">
                      <?php foreach ($contactInquiryLabels as $inquiryValue => $inquiryLabel): ?>
                        <li>
                          <button
                            type="button"
                            class="dropdown-item contact-inquiry-option <?= $contact_inquiry_type === $inquiryValue ? 'active' : '' ?>"
                            data-value="<?= htmlspecialchars($inquiryValue) ?>"
                          >
                            <?= htmlspecialchars($inquiryLabel) ?>
                          </button>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <input type="hidden" id="contact-inquiry-type" name="contact_inquiry_type" value="<?= htmlspecialchars($contact_inquiry_type) ?>" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <label for="contact-subject" class="form-label">Subject</label>
                  <input type="text" id="contact-subject" name="contact_subject" class="form-control contact-input"
                    placeholder="Short subject" required autocomplete="off" minlength="3" maxlength="120"
                    value="<?= htmlspecialchars($contact_subject) ?>" />
                </div>
              </div>

              <div class="row g-3 mt-0">
                <div class="col-md-6">
                  <label for="contact-order-ref" class="form-label">Order Reference (optional)</label>
                  <input type="text" id="contact-order-ref" name="contact_order_ref" class="form-control contact-input"
                    placeholder="#ORD-1234" autocomplete="off" maxlength="32"
                    value="<?= htmlspecialchars($contact_order_ref) ?>" />
                </div>
                <div class="col-md-6">
                  <label for="contact-phone" class="form-label">Phone (optional)</label>
                  <input type="text" id="contact-phone" name="contact_phone" class="form-control contact-input"
                    placeholder="+92 300 0000000" autocomplete="tel" maxlength="20"
                    value="<?= htmlspecialchars($contact_phone) ?>" />
                </div>
              </div>

              <div class="mb-3 mt-3">
                <label for="contact-preferred-reply" class="form-label">Preferred Reply Method</label>
                <select id="contact-preferred-reply" name="contact_preferred_reply" class="form-control contact-input" required>
                  <option value="email" <?= $contact_preferred_reply === 'email' ? 'selected' : '' ?>>Email</option>
                  <option value="phone" <?= $contact_preferred_reply === 'phone' ? 'selected' : '' ?>>Phone</option>
                  <option value="either" <?= $contact_preferred_reply === 'either' ? 'selected' : '' ?>>Either</option>
                </select>
              </div>

              <div class="mb-3">
                <label for="contact-message" class="form-label">Message</label>
                <textarea id="contact-message" name="contact_message" class="form-control contact-input" rows="5"
                  placeholder="Describe your issue in detail so we can help faster" required autocomplete="off" minlength="10" maxlength="3000"><?= htmlspecialchars($contact_message) ?></textarea>
              </div>

              <?= commerza_captcha_widget_html($con, 'contact_form') ?>

              <button type="submit" class="btn product-btn-buy px-4" id="contactSubmitBtn">
                Send Message
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
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
            <li><a aria-current="page" href="contact.php">Contact</a></li>
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
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" rel="noopener noreferrer" aria-label="Commerza on Instagram"><i
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
  <script src="frontend/assets/js/global-protection.js" defer></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="frontend/assets/js/script.js" defer></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function () {
      $("#serverAlert, #successAlert").each(function () {
        const element = $(this);
        setTimeout(function () {
          element.fadeOut(400);
        }, 3500);
      });

      let submitted = false;

      const inquiryInput = $('#contact-inquiry-type');
      const inquiryLabel = $('#contactInquiryLabel');
      $('.contact-inquiry-option').on('click', function () {
        const nextValue = ($(this).data('value') || '').toString().trim();
        const nextLabel = ($(this).text() || '').toString().trim();

        if (!nextValue || !nextLabel) {
          return;
        }

        inquiryInput.val(nextValue);
        inquiryLabel.text(nextLabel);
        $('.contact-inquiry-option').removeClass('active');
        $(this).addClass('active');
      });

      $("#contactForm").on("submit", function () {
        if (submitted) {
          return false;
        }
        submitted = true;
        $("#contactSubmitBtn").prop("disabled", true).text("Sending...");
      });
    });
  </script>
</body>

</html>
