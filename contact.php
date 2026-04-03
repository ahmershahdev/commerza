<?php
include "backend/data.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$contact_name = '';
$contact_email = '';
$contact_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit("Forbidden.");
    }

    $contact_name = trim((string)($_POST['contact_name'] ?? ''));
    $contact_email = strtolower(trim((string)($_POST['contact_email'] ?? '')));
    $contact_message = trim((string)($_POST['contact_message'] ?? ''));

    if (strlen($contact_name) < 3 || strlen($contact_name) > 100) {
        $errors[] = "Name must be 3-100 characters.";
    }

    if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL) || strlen($contact_email) > 150) {
        $errors[] = "Invalid email address.";
    }

    if (strlen($contact_message) < 10 || strlen($contact_message) > 3000) {
        $errors[] = "Message must be 10-3000 characters.";
    }

    $clientIp = commerza_client_ip();
    $rateIdentifier = 'contact_submit';

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
        $insertStmt = $con->prepare("INSERT INTO contact_messages (name, email, message, ip_address) VALUES (?, ?, ?, ?)");

        if (!$insertStmt) {
            $errors[] = "Something went wrong. Please try again.";
        } else {
            $insertStmt->bind_param("ssss", $contact_name, $contact_email, $contact_message, $ip_value);

            if ($insertStmt->execute()) {
                $success = "Message sent successfully. We will get back to you soon.";
                $contact_name = '';
                $contact_email = '';
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

            <p class="product-desc mb-3">
              <i class="bi bi-geo-alt me-2" style="color: #ff6600"></i>
              <span id="contactAddress">Barrage Colony, HYD, PK</span>
            </p>

            <p class="product-desc mb-3">
              <i class="bi bi-envelope me-2" style="color: #ff6600"></i>
              <span id="contactEmail">commerza.ahmer@gmail.com</span>
            </p>

            <p class="product-desc mb-4">
              <i class="bi bi-telephone me-2" style="color: #ff6600"></i>
              <span id="contactPhone">+92 314 8396293</span>
            </p>

            <div class="social-links">
              <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="Commerza on Facebook"><i
                  class="bi bi-facebook"></i></a>
              <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="Commerza on X"><i
                  class="bi bi-twitter"></i></a>
              <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="Commerza on Instagram"><i
                  class="bi bi-instagram"></i></a>
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

              <div class="mb-3">
                <label for="contact-message" class="form-label">Message</label>
                <textarea id="contact-message" name="contact_message" class="form-control contact-input" rows="5"
                  placeholder="Write your message" required autocomplete="off" minlength="10" maxlength="3000"><?= htmlspecialchars($contact_message) ?></textarea>
              </div>

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
  <script src="frontend/assets/js/global-protection.js" defer></script>
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
