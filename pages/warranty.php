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

$contactSettings = [];
if (isset($publicSettings['contact']) && is_array($publicSettings['contact'])) {
  $contactSettings = $publicSettings['contact'];
}

$supportEmail = trim((string)($contactSettings['email'] ?? 'support@ahmershah.dev'));
if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
  $supportEmail = 'support@ahmershah.dev';
}

$supportPhone = trim((string)($contactSettings['phone'] ?? '+92 370 4831994'));
if ($supportPhone === '') {
  $supportPhone = '+92 370 4831994';
}

$refundSupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode($siteBrandName . ' Refund Support');
$warrantySupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode($siteBrandName . ' Warranty Support');

$pageCanonicalUrl = commerza_absolute_url('/warranty');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="<?= htmlspecialchars('Warranty - ' . $siteBrandName . '. Comprehensive warranty coverage on all premium automatic watches. Protect your investment.', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= htmlspecialchars('Warranty | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Comprehensive warranty coverage for all ' . $siteBrandName . ' premium watches.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('Warranty | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": <?= json_encode('Warranty | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode($siteBrandName . ' warranty policy and support coverage details.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>

  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/warranty-inline.css">
</head>

<body class="dark-theme">
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'warranty']); ?>

  <main class="container my-5">
    <?php commerza_render_page_breadcrumb('Warranty'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-patch-check"></i> Warranty</span>
        <h1 class="mt-3" style="color: #ff6600">Warranty Policy</h1>
        <p class="product-desc mt-2">Every <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> watch includes 12‑month coverage for peace of mind.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="contact.php" class="btn product-btn-buy">Claim Warranty</a>
          <a href="faq.php" class="btn product-btn-cart">Warranty FAQs</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-clipboard-check"></i></div>
            <h3 class="product-name">Easy Claims</h3>
            <p class="product-desc">Submit with your order details.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-tools"></i></div>
            <h3 class="product-name">Expert Service</h3>
            <p class="product-desc">Repairs handled by trained experts.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-card">
            <div class="icon-badge"><i class="bi bi-shield"></i></div>
            <h3 class="product-name">Covered Defects</h3>
            <p class="product-desc">Manufacturing issues included.</p>
          </div>
        </div>
      </div>
    </section>
    <div class="text-center mb-5">
      <h1 style="color: #ff6600">Warranty Policy</h1>
      <p class="product-desc mt-3">
        Your <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> watch is protected by our quality assurance warranty.
      </p>
    </div>

    <div class="row mb-4">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">Warranty Coverage</h4>
            <p class="product-desc">
              All <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> watches come with a
              <strong>12-month limited warranty</strong> from the date of
              purchase. This warranty covers manufacturing defects in
              materials and workmanship under normal use.
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
              What Is Covered
            </h4>
            <ul class="product-desc">
              <li>Manufacturing defects</li>
              <li>Movement malfunctions</li>
              <li>Dial, hands, or internal mechanism issues</li>
              <li>Battery defects (if applicable)</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card product-card h-100">
          <div class="card-body">
            <h4 class="product-name mb-3">
              <i class="bi bi-x-circle me-2" style="color: #ff6600"></i>
              What Is Not Covered
            </h4>
            <ul class="product-desc">
              <li>Normal wear and tear</li>
              <li>Damage caused by accidents or misuse</li>
              <li>Water damage beyond rated resistance</li>
              <li>Unauthorized repairs or modifications</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">How to Claim Warranty</h4>
            <p class="product-desc">
              To request warranty service, please follow these steps:
            </p>
            <ol class="product-desc">
              <li>Keep your purchase receipt or order confirmation.</li>
              <li>Contact our support team with your issue details.</li>
              <li>Send the product to our service center if requested.</li>
              <li>Our team will inspect and repair or replace the item.</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-12">
        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">7-Day Refund Policy</h4>
            <p class="product-desc">
              If your order is marked <strong>Delivered</strong>, you can request a refund within <strong>7 days</strong> from delivery confirmation.
              Submit your request from your account page using the <strong>Refund Me</strong> option. Refund requests are reviewed by our support team and
              updated by email.
            </p>
            <ul class="product-desc mb-3">
              <li>Refund requests outside the 7-day window may be declined.</li>
              <li>Orders with accepted refunds are marked as refunded in your account history.</li>
              <li>You can include issue details to speed up review.</li>
            </ul>
            <p class="product-desc mb-0">
              Need help with a refund decision? Email
              <a class="policy-mail" href="<?= htmlspecialchars($refundSupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
              with your order number.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="text-center">
      <h3 class="mb-3" about-cta-title>Need Warranty Support?</h3>
      <p class="product-desc mb-4">
        Our support team is here to help you with any warranty-related
        questions.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Support</a>
      <p class="product-desc mt-3 mb-0">or email <a class="policy-mail" href="<?= htmlspecialchars($warrantySupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a></p>
    </div>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'warranty']); ?>

  <script src="frontend/assets/js/modules/core/site-settings.js" defer></script>
  <script src="frontend/assets/js/pages/static-page.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>