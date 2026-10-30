<?php
require_once dirname(__DIR__) . '/backend/core/data.php';

$appBaseHref = rtrim(commerza_public_base_url(), '/') . '/';
$publicSettings = $GLOBALS['commerza_public_site_settings_payload'] ?? [];
$siteBrandName = trim((string)($publicSettings['brand']['name'] ?? 'Commerza'));
if ($siteBrandName === '') {
  $siteBrandName = 'Commerza';
}

$siteLogoPath = trim((string)($publicSettings['brand']['logo'] ?? 'frontend/assets/images/logo/commerza_logo.svg'));
if ($siteLogoPath === '') {
  $siteLogoPath = 'frontend/assets/images/logo/commerza_logo.svg';
}

$siteFaviconPath = trim((string)($publicSettings['brand']['favicon'] ?? 'frontend/assets/images/favicon/commerza-watches-icon.ico'));
if ($siteFaviconPath === '') {
  $siteFaviconPath = 'frontend/assets/images/favicon/commerza-watches-icon.ico';
}

$pageCanonicalUrl = commerza_absolute_url('/shipping');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars('Shipping Information - ' . $siteBrandName . '. Learn about our fast, secure shipping options for premium watches worldwide.', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= htmlspecialchars('Shipping Information | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Learn about ' . $siteBrandName . '\'s shipping options and delivery information.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('Shipping Information | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": <?= json_encode('Shipping Information | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode('Shipping and delivery information for ' . $siteBrandName . ' orders.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>

  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body class="dark-theme">
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'shipping']); ?>

  <main class="container my-5">
    <?php commerza_render_page_breadcrumb('Shipping'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-truck"></i> Shipping</span>
        <h1 class="mt-3" style="color: #ff6600">Shipping Information</h1>
        <p class="product-desc mt-2">Fast, secure delivery options designed for premium timepieces.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="contact.php" class="btn product-btn-buy">Ask Shipping</a>
          <a href="returns.php" class="btn product-btn-cart">Returns Policy</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-box-seam"></i></div>
            <h3 class="product-name">Secure Packaging</h3>
            <p class="product-desc">Protective materials for safe delivery.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-clock-history"></i></div>
            <h3 class="product-name">Quick Dispatch</h3>
            <p class="product-desc">Orders processed within 1–2 business days.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-geo"></i></div>
            <h3 class="product-name">Nationwide Reach</h3>
            <p class="product-desc">Delivery across Pakistan with tracking.</p>
          </div>
        </div>
      </div>
    </section>
    <div class="text-center mb-5">
      <h2 class="h1"  style="color: #ff6600">Shipping Information</h2>
      <p class="product-desc mt-3">
        Everything you need to know about our shipping process.
      </p>
    </div>

    <div class="row mb-4">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">Order Processing</h4>
            <p class="product-desc">
              Orders are processed within
              <strong>1–2 business days</strong> after payment confirmation.
              Once shipped, you will receive a tracking number via email.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-4 mb-4">
        <div class="card product-card h-100 text-center">
          <div class="card-body">
            <i class="bi bi-truck fs-1 mb-3" style="color: var(--cmrz-light-navy, #ff6600)"></i>
            <h3 class="product-name">Standard Shipping</h3>
            <p class="product-desc">
              Delivery within 5–7 business days.
              <br /><strong>Cost:</strong> 1000 PKR
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="card product-card h-100 text-center">
          <div class="card-body">
            <i class="bi bi-lightning-charge fs-1 mb-3" style="color: var(--cmrz-light-navy, #ff6600)"></i>
            <h3 class="product-name">Express Shipping</h3>
            <p class="product-desc">
              Delivery within 2–3 business days.
              <br /><strong>Cost:</strong> 1200 PKR
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="card product-card h-100 text-center">
          <div class="card-body">
            <i class="bi bi-globe fs-1 mb-3" style="color: var(--cmrz-light-navy, #ff6600)"></i>
            <h3 class="product-name">International Shipping</h3>
            <p class="product-desc">
              Delivery within 7–14 business days.
              <br /><strong>Cost:</strong> Calculated at checkout
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-md-6 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-3">Tracking Your Order</h4>
            <p class="product-desc">
              Once your order ships, a tracking number will be emailed to you.
              You can use it to monitor your shipment in real time.
            </p>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-3">Delays & Issues</h4>
            <p class="product-desc">
              Shipping delays may occur due to weather, customs clearance, or
              courier issues. If your order is delayed, please contact our
              support team.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center">
      <h3 class="mb-3 about-cta-title">Questions About Shipping?</h3>
      <p class="product-desc mb-4">
        Our support team is here to help with any shipping-related inquiries.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Support</a>
    </div>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'shipping']); ?>

  <script src="frontend/assets/js/modules/core/site-settings.js" defer></script>
  <script src="frontend/assets/js/pages/static-page.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>