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
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body class="dark-theme">
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'returns']); ?>

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
      <h2 class="h1"  style="color: #ff6600">Returns & Refunds</h2>
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

    <div class="text-center about-cta-title">
      <h3 class="mb-3 about-cta-title">Need Help with a Return?</h3>
      <p class="product-desc mb-4">
        Our customer support team is ready to assist you.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Support</a>
    </div>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'returns']); ?>
  <script src="frontend/assets/js/modules/core/site-settings.js" defer></script>
  <script src="frontend/assets/js/pages/static-page.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>