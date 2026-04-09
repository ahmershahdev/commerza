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

$pageCanonicalUrl = commerza_absolute_url('/about');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="<?= htmlspecialchars('Learn about ' . $siteBrandName . ' - Your trusted source for premium automatic watches. Discover our commitment to quality, craftsmanship, and exceptional timepiece design.', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= htmlspecialchars('About Us | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Learn about ' . $siteBrandName . '\'s commitment to quality and craftsmanship in premium watches.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('About Us | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": <?= json_encode('About Us | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode('About ' . $siteBrandName . ' and our premium watch craftsmanship.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>

  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    .about-shell {
      padding-top: 88px;
    }

    .about-story-card {
      border: 1px solid rgba(255, 102, 0, 0.22);
      border-radius: 16px;
      background: linear-gradient(150deg, rgba(18, 18, 18, 0.96), rgba(8, 8, 8, 0.94));
      box-shadow: 0 18px 36px rgba(0, 0, 0, 0.45);
      padding: 20px;
      height: 100%;
    }

    .about-story-step {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 14px;
      color: #d0d0d0;
      font-family: 'Inter', sans-serif;
    }

    .about-step-dot {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.72rem;
      font-family: 'JetBrains Mono', monospace;
      border: 1px solid rgba(255, 153, 0, 0.5);
      color: #ffcc66;
      background: rgba(255, 102, 0, 0.14);
      flex-shrink: 0;
      margin-top: 1px;
    }

    .about-highlight-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 14px;
    }

    .about-highlight-card {
      border: 1px solid rgba(255, 102, 0, 0.24);
      border-radius: 14px;
      background: rgba(12, 12, 12, 0.95);
      padding: 14px;
      min-height: 132px;
    }

    .about-highlight-card i {
      color: #ff8a2a;
      font-size: 1.2rem;
      margin-bottom: 8px;
      display: inline-block;
    }

    .about-highlight-card h3 {
      color: #ffe6cc;
      font-size: 0.95rem;
      margin-bottom: 6px;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }

    .about-app-launch {
      border: 1px solid rgba(255, 102, 0, 0.28);
      border-radius: 18px;
      background: radial-gradient(circle at 0% 0%, rgba(255, 102, 0, 0.22), rgba(10, 10, 10, 0.98) 56%);
      box-shadow: 0 18px 42px rgba(0, 0, 0, 0.55);
      padding: 24px;
    }

    .about-app-columns {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-top: 16px;
    }

    .about-app-col {
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: rgba(0, 0, 0, 0.4);
      padding: 12px;
    }

    .about-app-col h3 {
      color: #ffd3ad;
      font-size: 0.76rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace;
      margin: 0 0 8px;
    }

    .about-app-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 6px;
    }

    .about-app-list li {
      color: #ececec;
      font-size: 0.86rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .about-app-list i {
      color: #ff9b42;
    }

    .store-chip-wrap {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
      margin-top: 16px;
    }

    .store-chip {
      border-radius: 14px;
      border: 1px solid rgba(255, 255, 255, 0.16);
      background: rgba(0, 0, 0, 0.42);
      padding: 12px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .store-chip i {
      color: #ffcc66;
      font-size: 1.3rem;
      width: 28px;
      text-align: center;
    }

    .store-chip small {
      display: block;
      color: #b7b7b7;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.68rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .store-chip strong {
      color: #fff;
      display: block;
      font-size: 0.92rem;
      font-family: 'Montserrat', sans-serif;
      letter-spacing: 0.02em;
    }

    .store-chip span {
      margin-left: auto;
      color: #ffb067;
      font-size: 0.7rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace;
      border: 1px solid rgba(255, 176, 103, 0.4);
      border-radius: 999px;
      padding: 4px 8px;
      white-space: nowrap;
    }

    @media (max-width: 767.98px) {
      .about-app-columns {
        grid-template-columns: 1fr;
      }
    }
  </style>
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
              <a class="nav-link" aria-current="page" href="about.php">About</a>
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
            <a class="nav-link" aria-current="page" href="about.php">About</a>
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

  <main class="container my-5 about-shell">
    <?php commerza_render_page_breadcrumb('About'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-gem"></i> About <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?></span>
        <h1 class="mt-3" style="color: #ff6600">Precision Craft, Built For Real Life</h1>
        <p class="product-desc mt-2"><?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> started with a simple goal: build premium watches that look bold, wear comfortably, and stay reliable day after day. From the first sketch to final QC, every piece is selected for durability, clarity, and timeless style.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="index.php" class="btn product-btn-buy">Shop Collection</a>
          <a href="contact.php" class="btn product-btn-cart">Talk to Us</a>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <h2 class="visually-hidden">By the numbers</h2>
      <div class="stat-grid">
        <div class="stat-card">
          <h3 style="color:#ff6600; margin:0;">10k+</h3>
          <p class="product-desc mb-0">Happy Customers</p>
        </div>
        <div class="stat-card">
          <h3 style="color:#ff6600; margin:0;">24h</h3>
          <p class="product-desc mb-0">Support Response</p>
        </div>
        <div class="stat-card">
          <h3 style="color:#ff6600; margin:0;">12 Mo</h3>
          <p class="product-desc mb-0">Warranty Included</p>
        </div>
      </div>
    </section>

    <section class="row g-4 mb-5">
      <div class="col-12 col-lg-7">
        <div class="about-story-card">
          <h2 class="product-name mb-3">What Makes <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Different</h2>
          <p class="product-desc">We design around real use, not just display photos. That means balanced case weight, straps you can wear all day, and dial readability that holds up in daylight and low-light conditions. Every collection goes through visual, fit, and movement checks before release.</p>
          <p class="product-desc mb-0">Our catalogs blend classic silhouettes with modern finishes, so your watch looks right in formal settings, daily work, and weekend wear. We keep the process transparent, support responsive, and product guidance practical.</p>
        </div>
      </div>
      <div class="col-12 col-lg-5">
        <div class="about-story-card">
          <h2 class="product-name mb-3">Our Journey</h2>
          <div class="about-story-step">
            <span class="about-step-dot">01</span>
            <div>
              <strong class="text-light">Design First</strong>
              <p class="product-desc mb-0">Dial balance, case profile, and strap comfort are finalized before production.</p>
            </div>
          </div>
          <div class="about-story-step">
            <span class="about-step-dot">02</span>
            <div>
              <strong class="text-light">Material Curation</strong>
              <p class="product-desc mb-0">Only tested combinations of steel, crystal, plating, and leather move forward.</p>
            </div>
          </div>
          <div class="about-story-step mb-0">
            <span class="about-step-dot">03</span>
            <div>
              <strong class="text-light">Final Verification</strong>
              <p class="product-desc mb-0">Stock, presentation, and support-readiness are validated before launch.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="mb-5">
      <h2 class="mb-3" style="color:#ff6600;">The <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Standard</h2>
      <div class="about-highlight-grid">
        <article class="about-highlight-card">
          <i class="bi bi-shield-check"></i>
          <h3>Quality Control</h3>
          <p class="product-desc mb-0">Every SKU is reviewed for finish consistency, movement behavior, and dial clarity.</p>
        </article>
        <article class="about-highlight-card">
          <i class="bi bi-watch"></i>
          <h3>Wearability Focus</h3>
          <p class="product-desc mb-0">Case dimensions and strap feel are selected for long daily comfort.</p>
        </article>
        <article class="about-highlight-card">
          <i class="bi bi-truck"></i>
          <h3>Reliable Fulfillment</h3>
          <p class="product-desc mb-0">Dispatch timelines are kept practical, with secure packaging and clear support updates.</p>
        </article>
        <article class="about-highlight-card">
          <i class="bi bi-headset"></i>
          <h3>Human Support</h3>
          <p class="product-desc mb-0">Our team helps with fit, movement type, and after-purchase guidance quickly.</p>
        </article>
      </div>
    </section>

    <section class="about-app-launch mb-5" aria-labelledby="aboutAppLaunchTitle">
      <span class="hero-badge"><i class="bi bi-phone"></i> Mobile Roadmap</span>
      <h2 id="aboutAppLaunchTitle" class="mt-3" style="color:#ff6600;"><?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> App Is Coming Soon</h2>
      <p class="product-desc mb-0">We are building a faster mobile experience for watch discovery, personalized recommendations, wishlist sync, and order tracking. Early access starts soon.</p>

      <div class="about-app-columns" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> app roadmap details">
        <div class="about-app-col">
          <h3>User Experience 6</h3>
          <ul class="about-app-list">
            <li><i class="bi bi-stars"></i><span>Personalized model suggestions</span></li>
            <li><i class="bi bi-heart"></i><span>Wishlist sync across devices</span></li>
            <li><i class="bi bi-truck"></i><span>Live order and dispatch updates</span></li>
            <li><i class="bi bi-shield-lock"></i><span>Faster secure checkout</span></li>
            <li><i class="bi bi-bell"></i><span>Drop and restock notifications</span></li>
            <li><i class="bi bi-award"></i><span>Warranty and care reminders</span></li>
          </ul>
        </div>
        <div class="about-app-col">
          <h3>Launch Toolkit 6</h3>
          <ul class="about-app-list">
            <li><i class="bi bi-calendar-event"></i><span>Early access release calendar</span></li>
            <li><i class="bi bi-search"></i><span>Quick search and smart filters</span></li>
            <li><i class="bi bi-clock-history"></i><span>One-tap reorders from history</span></li>
            <li><i class="bi bi-geo-alt"></i><span>Saved address and delivery slots</span></li>
            <li><i class="bi bi-chat-square-dots"></i><span>In-app support conversation flow</span></li>
            <li><i class="bi bi-camera"></i><span>Photo-backed product reviews</span></li>
          </ul>
        </div>
      </div>

      <div class="store-chip-wrap">
        <div class="store-chip" aria-label="Google Play Store coming soon">
          <i class="bi bi-google-play"></i>
          <div>
            <small>Coming Soon On</small>
            <strong>Google Play Store</strong>
          </div>
          <span>Soon</span>
        </div>
        <div class="store-chip" aria-label="Apple App Store coming soon">
          <i class="bi bi-apple"></i>
          <div>
            <small>Coming Soon On</small>
            <strong>App Store</strong>
          </div>
          <span>Soon</span>
        </div>
      </div>
    </section>

    <div class="text-center">
      <h3 class="mb-3" style="color: #ff6600">Timeless Style Starts Here</h3>
      <p class="product-desc mb-4">Discover watches designed to match your ambition, routine, and signature look.</p>
      <a href="index.php" class="btn product-btn-buy px-5">Shop Now</a>
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
            <li><a aria-current="page" href="about.php">About Us</a></li>
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
  <script <?= commerza_csp_nonce_attr() ?>>
    document.addEventListener("DOMContentLoaded", function() {
      if (typeof applySiteSettings === "function") {
        applySiteSettings();
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>