<?php
require_once __DIR__ . '/backend/data.php';

$appBaseHref = rtrim(commerza_public_base_url(), '/') . '/';
$publicSettings = $GLOBALS['commerza_public_site_settings_payload'] ?? [];
$siteBrandName = trim((string)($publicSettings['brand']['name'] ?? 'Commerza'));
if ($siteBrandName === '') {
  $siteBrandName = 'Commerza';
}

$siteLogoPath = trim((string)($publicSettings['brand']['logo'] ?? 'frontend/assets/images/logo/commerza-logo.webp'));
if ($siteLogoPath === '') {
  $siteLogoPath = 'frontend/assets/images/logo/commerza-logo.webp';
}

$siteFaviconPath = trim((string)($publicSettings['brand']['favicon'] ?? 'frontend/assets/images/favicon/commerza-watches-icon.ico'));
if ($siteFaviconPath === '') {
  $siteFaviconPath = 'frontend/assets/images/favicon/commerza-watches-icon.ico';
}

$pageCanonicalUrl = commerza_absolute_url('/returns');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="<?= htmlspecialchars('Returns & Refunds - ' . $siteBrandName . '. Hassle-free return policy for your premium watch purchases within 30 days.', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= htmlspecialchars('Returns & Refunds | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('View ' . $siteBrandName . '\'s hassle-free return and refund policy.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('Returns & Refunds | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": <?= json_encode('Returns & Refunds | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode($siteBrandName . ' return and refund policy page.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>

  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body class="dark-theme">
  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Logo" loading="lazy"
            class="navbar-logo me-2" />
          <span class="brand-text"><?= htmlspecialchars(strtoupper($siteBrandName), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="d-flex align-items-center order-lg-2">
          <ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
            <li class="nav-item">
              <a class="nav-link nav-icon-link" href="account.php" aria-label="Account"><i class="bi bi-person"></i></a>
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
          <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Logo" loading="lazy" class="offcanvas-logo me-2" />
          <span class="brand-text"><?= htmlspecialchars(strtoupper($siteBrandName), ENT_QUOTES, 'UTF-8') ?></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="offcanvas-user-actions">
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
    <?php commerza_render_page_breadcrumb('Returns'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-arrow-counterclockwise"></i> Returns</span>
        <h1 class="mt-3" style="color: #ff6600">Returns & Refunds</h1>
        <p class="product-desc mt-2">Simple, transparent return policy for premium purchases.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="contact.php" class="btn product-btn-buy">Start a Return</a>
          <a href="faq.php" class="btn product-btn-cart">View FAQs</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-check2-circle"></i></div>
            <h3 class="product-name">Easy Approval</h3>
            <p class="product-desc">Request returns in a few steps.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-shield-exclamation"></i></div>
            <h3 class="product-name">Clear Eligibility</h3>
            <p class="product-desc">Know exactly what’s covered.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-cash-coin"></i></div>
            <h3 class="product-name">Fast Refunds</h3>
            <p class="product-desc">Refunds processed after inspection.</p>
          </div>
        </div>
      </div>
    </section>
    <div class="text-center mb-5">
      <h1 style="color: #ff6600">Returns & Refunds</h1>
      <p class="product-desc mt-3">
        We want you to be fully satisfied with your purchase. Please review
        our return policy below.
      </p>
    </div>

    <div class="row mb-4">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">Return Policy</h4>
            <p class="product-desc">
              You may return eligible products within
              <strong>7 days</strong> of delivery. Items must be unused, in
              original condition, and include all packaging and accessories.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-6 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-3">
              <i class="bi bi-check-circle me-2" style="color: #ff6600"></i>
              Eligible for Return
            </h4>
            <ul class="product-desc">
              <li>Defective or damaged items</li>
              <li>Incorrect product received</li>
              <li>Unused items with original packaging</li>
              <li>Items reported within the return window</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-3">
              <i class="bi bi-x-circle me-2" style="color: #ff6600"></i>
              Not Eligible for Return
            </h4>
            <ul class="product-desc">
              <li>Used or worn products</li>
              <li>Items without original packaging</li>
              <li>Damage caused by misuse</li>
              <li>Return requests after 7 days</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">Refund Process</h4>
            <ol class="product-desc">
              <li>Contact our support team with your order details.</li>
              <li>Receive return approval and instructions.</li>
              <li>Ship the product back to our service center.</li>
              <li>Refund will be processed after inspection.</li>
            </ol>
            <p class="product-desc mt-3">
              Refunds are issued to the original payment method within
              <strong>5–10 business days</strong> after approval.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center">
      <h3 class="mb-3" style="color: #ff6600">Need Help with a Return?</h3>
      <p class="product-desc mb-4">
        Our customer support team is ready to assist you.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Support</a>
    </div>
  </main>

  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading"><?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?></h3>
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
            <li><a aria-current="page" href="returns.php">Returns</a></li>
            <li><a href="faq.php">FAQ</a></li>
            <li><a href="warranty.php">Warranty</a></li>
            <li><a href="terms-of-service.php">Terms of Service</a></li>
            <li><a href="privacy-policy.php">Privacy Policy</a></li>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Connect</h3>
          <div class="social-links">
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on Facebook"><i class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on X"><i class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on Instagram"><i class="bi bi-instagram"></i></a>
          </div>
          <p class="footer-text mt-3">Email: commerza.ahmer@gmail.com</p>
          <p class="footer-text">Phone: +92 314 8396293</p>
        </div>
      </div>
      <div class="row">
        <div class="col-12 text-center py-3 border-top">
          <p class="footer-copyright">
            &copy; 2026 <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </footer>
  <script src="frontend/assets/js/modules/core/site-settings.js" defer></script>
  <script src="frontend/assets/js/pages/returns.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>