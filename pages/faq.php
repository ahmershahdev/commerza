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

$faqSupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode($siteBrandName . ' FAQ Support');

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
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/faq-inline.css">
</head>

<body class="dark-theme">
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'faq']); ?>

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
      <h2 class="h1">Frequently Asked Questions</h2>
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
            We currently accept Cash on Delivery (COD) and secure Stripe card payments.
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
            <a class="policy-mail" href="<?= htmlspecialchars($faqSupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>.
          </div>
        </div>
      </div>
    </div>

    <div class="text-center mt-5 about-cta-title">
      <h3>Still have questions?</h3>
      <p class="product-desc mb-4">
        Our support team is always happy to help.
      </p>
      <a href="contact.php" class="btn product-btn-buy px-5">Contact Us</a>
    </div>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'faq']); ?>
  <script src="frontend/assets/js/modules/core/site-settings.js" defer></script>
  <script src="frontend/assets/js/pages/static-page.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>