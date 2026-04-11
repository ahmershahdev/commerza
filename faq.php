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

$pageCanonicalUrl = commerza_absolute_url('/faq');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars('Frequently Asked Questions - ' . $siteBrandName . '. Find answers about our premium watches, shipping, returns, and customer service.', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= htmlspecialchars('FAQ | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Find answers to frequently asked questions about ' . $siteBrandName . ' watches and services.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('FAQ | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "name": <?= json_encode('FAQ | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode('Frequently asked questions for ' . $siteBrandName . ' customers.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>

  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/faq-inline.css">
</head>

<body class="dark-theme">
  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Logo" loading="lazy" class="navbar-logo me-2" />
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
    <?php commerza_render_page_breadcrumb('FAQ'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-question-circle"></i> Help Center</span>
        <h1 class="mt-3" style="color: #ff6600">Frequently Asked Questions</h1>
        <p class="product-desc mt-2">Quick answers about orders, shipping, returns, and warranty.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="contact.php" class="btn product-btn-buy">Contact Support</a>
          <a href="shipping.php" class="btn product-btn-cart">Shipping Info</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-truck"></i></div>
            <h3 class="product-name">Shipping</h3>
            <p class="product-desc">Delivery timelines and tracking.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-arrow-counterclockwise"></i></div>
            <h3 class="product-name">Returns</h3>
            <p class="product-desc">Eligibility and refund process.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-shield-check"></i></div>
            <h3 class="product-name">Warranty</h3>
            <p class="product-desc">Coverage and claim steps.</p>
          </div>
        </div>
      </div>
    </section>
    <div class="text-center mb-5">
      <h1>Frequently Asked Questions</h1>
      <p class="product-desc mt-3">
        Find answers to common questions about our products and services.
      </p>
    </div>

    <div class="accordion" id="faqAccordion">
      <div class="accordion-item">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq1">
            What payment methods do you accept?
          </button>
        </h3>
        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body product-desc">
            We accept all major credit and debit cards, PayPal, and other
            secure online payment methods.
          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq2">
            How long does shipping take?
          </button>
        </h3>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body product-desc">
            Shipping usually takes 3–7 business days depending on your
            location and selected shipping method.
          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq3">
            Do your watches come with a warranty?
          </button>
        </h3>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body product-desc">
            Yes, all <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> watches include a 12-month limited warranty
            covering manufacturing defects.
          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq4">
            Can I return or exchange a product?
          </button>
        </h3>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body product-desc">
            Yes, returns and exchanges are accepted within 7 days of delivery,
            provided the item is unused and in original packaging.
          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#faq5">
            How can I contact customer support?
          </button>
        </h3>
        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body product-desc">
            You can reach us through our Contact page or email us directly at
            commerza.ahmer@gmail.com.
          </div>
        </div>
      </div>
    </div>

    <div class="text-center mt-5">
      <h3 style="color: #ff6600">Still have questions?</h3>
      <p class="product-desc mb-4">
        Our support team is always happy to help.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Us</a>
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
            <li><a href="returns.php">Returns</a></li>
            <li><a aria-current="page" href="faq.php">FAQ</a></li>
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
  <script src="frontend/assets/js/pages/faq.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>