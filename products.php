<?php
require_once __DIR__ . '/backend/data.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$appBaseHref = rtrim(commerza_public_base_url(), '/') . '/';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
  $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
  $normalizedRequestPath = str_replace('\\', '/', $requestPath);
  $rawQueryString = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
  $visibleQueryParams = [];
  if ($rawQueryString !== '') {
    parse_str($rawQueryString, $visibleQueryParams);
  }

  $normalizeSlug = static function (string $raw): string {
    $slug = strtolower(trim($raw));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    if (!is_string($slug)) {
      return '';
    }
    return trim($slug, '-');
  };

  $dbConnection = $GLOBALS['con'] ?? null;

  $resolveSlugFromProductId = static function (int $productId) use ($dbConnection, $normalizeSlug): string {
    if (!($dbConnection instanceof mysqli) || $productId <= 0) {
      return '';
    }

    $stmt = $dbConnection->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
    if (!$stmt) {
      return '';
    }

    $stmt->bind_param('i', $productId);
    if (!$stmt->execute()) {
      $stmt->close();
      return '';
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $name = trim((string)($row['name'] ?? ''));
    if ($name === '') {
      return '';
    }

    return $normalizeSlug($name);
  };

  if (preg_match('~/(?:prodcuts|product)/([^/?#]+)/?$~i', $normalizedRequestPath, $legacyMatch) === 1) {
    $legacySlug = $normalizeSlug(rawurldecode((string)($legacyMatch[1] ?? '')));
    if ($legacySlug !== '') {
      $forwardQueryParams = $visibleQueryParams;
      unset($forwardQueryParams['id'], $forwardQueryParams['slug']);
      $targetUrl = commerza_absolute_url('/products/' . rawurlencode($legacySlug));
      $query = http_build_query($forwardQueryParams);
      if ($query !== '') {
        $targetUrl .= '?' . $query;
      }
      header('Location: ' . $targetUrl, true, 301);
      exit;
    }
  }

  if (preg_match('~/(?:products)/([^/?#]+)/?$~i', $normalizedRequestPath, $canonicalMatch) === 1) {
    $canonicalSlug = $normalizeSlug(rawurldecode((string)($canonicalMatch[1] ?? '')));
    if ($canonicalSlug !== '') {
      $targetPath = '/products/' . rawurlencode($canonicalSlug);
      $forwardQueryParams = $visibleQueryParams;
      $hasIdQuery = array_key_exists('id', $visibleQueryParams);
      $hasSlugQuery = array_key_exists('slug', $visibleQueryParams);
      unset($forwardQueryParams['id'], $forwardQueryParams['slug']);

      if ($hasIdQuery || $hasSlugQuery) {
        $targetUrl = commerza_absolute_url($targetPath);
        $query = http_build_query($forwardQueryParams);
        if ($query !== '') {
          $targetUrl .= '?' . $query;
        }
        header('Location: ' . $targetUrl, true, 301);
        exit;
      }
    }
  }

  if (preg_match('~/(?:products|products\.php)$~i', $normalizedRequestPath) === 1) {
    $isLegacyProductsPhpRoute = preg_match('~/products\.php$~i', $normalizedRequestPath) === 1;
    $queryParams = $visibleQueryParams;
    $targetPath = '/products';
    $shouldRedirect = $isLegacyProductsPhpRoute;

    if (isset($queryParams['slug'])) {
      $slug = $normalizeSlug((string)$queryParams['slug']);

      if ($slug !== '') {
        $targetPath .= '/' . rawurlencode($slug);
        unset($queryParams['slug'], $queryParams['id']);
        $shouldRedirect = true;
      }
    } elseif (isset($queryParams['id'])) {
      $slugFromId = $resolveSlugFromProductId((int)$queryParams['id']);
      if ($slugFromId !== '') {
        $targetPath .= '/' . rawurlencode($slugFromId);
        unset($queryParams['id'], $queryParams['slug']);
        $shouldRedirect = true;
      }
    }

    $targetUrl = commerza_absolute_url($targetPath);
    $query = http_build_query($queryParams);
    $sourceQuery = http_build_query($visibleQueryParams);
    if ($query !== $sourceQuery) {
      $shouldRedirect = true;
    }

    if ($query !== '') {
      $targetUrl .= '?' . $query;
    }

    if ($shouldRedirect) {
      header('Location: ' . $targetUrl, true, 301);
      exit;
    }
  }
}

$productsCanonicalPath = '/products';
if (isset($_GET['slug'])) {
  $canonicalSlug = strtolower(trim((string)$_GET['slug']));
  $canonicalSlug = preg_replace('/[^a-z0-9]+/', '-', $canonicalSlug);
  $canonicalSlug = is_string($canonicalSlug) ? trim($canonicalSlug, '-') : '';
  if ($canonicalSlug !== '') {
    $productsCanonicalPath .= '/' . rawurlencode($canonicalSlug);
  }
}

$productsPageUrl = commerza_absolute_url($productsCanonicalPath);
$productsImageUrl = commerza_absolute_url('/frontend/assets/images/logo/commerza-logo.webp');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="Explore premium Commerza watches and accessories.">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="Product | Commerza">
  <meta property="og:description" content="Explore premium Commerza watches and accessories.">
  <meta property="og:url" content="<?= htmlspecialchars($productsPageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="product">
  <meta property="og:image" content="<?= htmlspecialchars($productsImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Product | Commerza">
  <meta name="twitter:description" content="Explore premium Commerza watches and accessories.">
  <meta name="twitter:image" content="<?= htmlspecialchars($productsImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>Product | Commerza</title>
  <link rel="canonical" href="<?= htmlspecialchars($productsPageUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Product | Commerza",
      "url": "<?= htmlspecialchars($productsPageUrl, ENT_QUOTES, 'UTF-8') ?>",
      "description": "Commerza product detail page for premium watches."
    }
  </script>

  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    .product-hero {
      margin-top: 90px;
    }

    .product-section-title {
      color: #ff6600;
      font-family: 'Montserrat', sans-serif;
      letter-spacing: 1px;
      text-transform: uppercase;
    }

    .product-info-strip {
      margin-top: 22px;
      border: 1px solid rgba(255, 102, 0, 0.24);
      border-radius: 16px;
      background: linear-gradient(140deg, rgba(18, 18, 18, 0.96), rgba(6, 6, 6, 0.94));
      box-shadow: 0 16px 34px rgba(0, 0, 0, 0.35);
      padding: 12px;
    }

    .product-info-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .product-info-item {
      background: rgba(10, 10, 10, 0.65);
      border: 1px solid rgba(255, 102, 0, 0.18);
      border-radius: 12px;
      padding: 12px;
      min-height: 98px;
    }

    .product-info-item i {
      color: #ff9b4a;
      font-size: 1rem;
      display: inline-block;
      margin-bottom: 8px;
    }

    .product-info-item h3 {
      color: #fff;
      margin: 0 0 4px;
      font-size: 0.82rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .product-info-item p {
      margin: 0;
      color: #b7b7b7;
      font-size: 0.82rem;
      line-height: 1.45;
    }

    .share-panel {
      border: 1px solid rgba(255, 102, 0, 0.24);
      border-radius: 16px;
      background: linear-gradient(145deg, rgba(19, 19, 19, 0.95), rgba(8, 8, 8, 0.96));
      box-shadow: 0 16px 34px rgba(0, 0, 0, 0.38);
      padding: 16px;
    }

    .share-description {
      color: #c9c9c9;
      font-size: 0.9rem;
      margin-top: 6px;
    }

    .share-buttons {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
    }

    .share-buttons .share-btn {
      border: 1px solid rgba(255, 102, 0, 0.28);
      color: #ffe4ce;
      background: rgba(255, 102, 0, 0.08);
      border-radius: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 12px;
      font-size: 0.83rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .share-buttons .share-btn i {
      font-size: 0.96rem;
    }

    .share-buttons .share-btn:hover,
    .share-buttons .share-btn:focus-visible {
      border-color: rgba(255, 204, 0, 0.6);
      color: #fff6cc;
      background: rgba(255, 204, 0, 0.14);
      transform: translateY(-1px);
      outline: none;
    }

    .share-buttons .share-btn.share-btn-facebook:hover {
      border-color: rgba(113, 154, 255, 0.75);
      background: rgba(113, 154, 255, 0.22);
      color: #e9f0ff;
    }

    .share-buttons .share-btn.share-btn-x:hover {
      border-color: rgba(255, 255, 255, 0.42);
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }

    .share-buttons .share-btn.share-btn-whatsapp:hover {
      border-color: rgba(37, 211, 102, 0.75);
      background: rgba(37, 211, 102, 0.2);
      color: #ddffe9;
    }

    .share-buttons .share-btn.share-btn-copy.is-copied {
      border-color: rgba(255, 204, 0, 0.75);
      background: rgba(255, 204, 0, 0.2);
      color: #fff6ce;
    }

    .reviews-track {
      display: flex;
      gap: 16px;
      animation: review-marquee 24s linear infinite;
      width: max-content;
    }

    .reviews-wrap {
      overflow: hidden;
    }

    .review-card {
      min-width: 280px;
      background: linear-gradient(150deg, rgba(24, 24, 24, 0.98), rgba(10, 10, 10, 0.95));
      border: 1px solid rgba(255, 153, 61, 0.34);
      border-radius: 14px;
      padding: 16px;
      color: #fff;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.28);
    }

    .review-form-card {
      background: linear-gradient(150deg, rgba(20, 20, 20, 0.98), rgba(8, 8, 8, 0.95));
      border: 1px solid rgba(255, 153, 61, 0.34);
      border-radius: 14px;
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.33);
      position: relative;
      overflow: hidden;
    }

    .review-form-card::before {
      content: "";
      position: absolute;
      inset: -30% auto auto -20%;
      width: 240px;
      height: 240px;
      background: radial-gradient(circle, rgba(255, 153, 61, 0.16), transparent 66%);
      pointer-events: none;
    }

    .review-form-card .form-control,
    .review-form-card .form-select {
      background: #191919;
      border: 1px solid rgba(255, 102, 0, 0.25);
      color: #f2f2f2;
    }

    .review-form-card .form-control:focus,
    .review-form-card .form-select:focus {
      border-color: #ffcc00;
      box-shadow: 0 0 0 0.15rem rgba(255, 204, 0, 0.2);
    }

    .review-file-input {
      border: 1px dashed rgba(255, 153, 61, 0.55) !important;
      background: linear-gradient(145deg, rgba(255, 102, 0, 0.08), rgba(17, 17, 17, 0.85)) !important;
      color: #ffe7d0 !important;
      border-radius: 12px;
      padding: 10px 12px;
      font-family: 'JetBrains Mono', monospace;
    }

    .review-file-input::file-selector-button {
      border: 1px solid rgba(255, 102, 0, 0.6);
      background: linear-gradient(90deg, #ff6600, #ff9e2a);
      color: #140b03;
      border-radius: 999px;
      padding: 6px 12px;
      margin-right: 10px;
      font-weight: 700;
      letter-spacing: 0.04em;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .review-file-input:hover::file-selector-button,
    .review-file-input:focus-visible::file-selector-button {
      background: linear-gradient(90deg, #ffcc00, #ff8a1f);
      color: #201300;
    }

    .review-file-selection {
      margin-top: 8px;
      color: #c6b49f;
      font-size: 0.78rem;
      font-family: 'JetBrains Mono', monospace;
      letter-spacing: 0.03em;
      min-height: 18px;
    }

    .review-stars-input {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-top: 6px;
      padding: 2px 0 0;
      border: 0;
      border-radius: 0;
      background: transparent;
    }

    .review-star-btn {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      border: 0 !important;
      background: transparent !important;
      box-shadow: none !important;
      color: rgba(255, 255, 255, 0.25);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.55rem;
      line-height: 1;
      transition: all 0.2s ease;
      cursor: pointer;
      position: relative;
      z-index: 2;
    }

    .review-star-btn i {
      pointer-events: none;
      transition: transform 0.2s ease;
    }

    .review-star-btn:hover,
    .review-star-btn:focus-visible {
      color: #ffd662;
      outline: none;
      transform: translateY(-1px) scale(1.05);
      filter: drop-shadow(0 0 8px rgba(255, 204, 0, 0.28));
    }

    .review-star-btn.active {
      color: #ffcc00;
      transform: translateY(-1px) scale(1.07);
      filter: drop-shadow(0 0 10px rgba(255, 204, 0, 0.36));
    }

    .review-star-btn.active i {
      transform: scale(1.06);
    }

    .review-star-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
      filter: none;
    }

    .review-stars-input.is-readonly {
      opacity: 0.84;
    }

    .review-stars-input.is-readonly .review-star-btn {
      transform: none;
      filter: none;
    }

    .review-rating-label {
      color: #d1d1d1;
      font-size: 0.86rem;
      margin-top: 8px;
      display: block;
    }

    .review-stars-line {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .review-stars {
      color: #ffcc00;
      letter-spacing: 1px;
      font-size: 1rem;
    }

    .review-score {
      color: #ffb066;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.4px;
      text-transform: uppercase;
    }

    .detail-highlights {
      margin: 12px 0 14px;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    .detail-highlight-item {
      background: rgba(0, 0, 0, 0.38);
      border: 1px solid rgba(255, 102, 0, 0.16);
      border-radius: 10px;
      padding: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-highlight-item i {
      color: #ff9a44;
      font-size: 0.95rem;
    }

    .detail-highlight-copy {
      display: flex;
      flex-direction: column;
      gap: 1px;
    }

    .detail-highlight-title {
      color: #c1c1c1;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      font-size: 0.67rem;
      line-height: 1.3;
    }

    .detail-highlight-value {
      color: #fff;
      font-size: 0.85rem;
      line-height: 1.35;
      font-weight: 600;
    }

    .product-assurance-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
    }

    .assurance-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 11px;
      border-radius: 999px;
      border: 1px solid rgba(255, 102, 0, 0.26);
      background: rgba(255, 102, 0, 0.11);
      color: #ffd7b5;
      font-size: 0.76rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace;
    }

    .assurance-chip i {
      color: #ffcc00;
    }

    @media (max-width: 991px) {
      .product-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 575px) {

      .product-info-grid,
      .detail-highlights {
        grid-template-columns: 1fr;
      }

      .share-buttons {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @keyframes review-marquee {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-50%);
      }
    }
  </style>
</head>

<body class="dark-theme premium-cards product-page" style="user-select: none;">
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

  <main class="container product-hero">
    <div id="product-detail-container"></div>

    <section class="product-info-strip" aria-label="Product buying advantages">
      <div class="product-info-grid">
        <article class="product-info-item">
          <i class="bi bi-patch-check"></i>
          <h3>Authentic Quality</h3>
          <p>Every product is quality-checked before dispatch for reliability and finish.</p>
        </article>
        <article class="product-info-item">
          <i class="bi bi-truck"></i>
          <h3>Fast Dispatch</h3>
          <p>Orders are processed quickly with regular status updates and tracking support.</p>
        </article>
        <article class="product-info-item">
          <i class="bi bi-shield-check"></i>
          <h3>Secure Checkout</h3>
          <p>Protected checkout and safe order handling for your personal information.</p>
        </article>
        <article class="product-info-item">
          <i class="bi bi-headset"></i>
          <h3>Dedicated Support</h3>
          <p>Need help with this model? Contact our team for quick product guidance.</p>
        </article>
      </div>
    </section>

    <section class="mt-5">
      <div class="share-panel">
        <h2 class="product-section-title mb-2">Share this product</h2>
        <p class="share-description">Show this item to friends and family, or copy a direct product link instantly.</p>
        <div class="share-buttons" id="product-share-buttons"></div>
      </div>
    </section>

    <section class="mt-5">
      <h2 class="product-section-title mb-3">Customer Reviews</h2>
      <div class="reviews-wrap" id="reviews-wrap">
        <div class="reviews-track" id="reviews-track"></div>
      </div>
    </section>

    <section class="mt-4 mb-5">
      <div class="review-form-card p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <h3 class="h5 mb-0 text-white">Share Your Review</h3>
          <span id="reviewsSummaryText" class="small text-secondary">Loading reviews...</span>
        </div>
        <p id="reviewEligibilityMessage" class="small text-secondary mb-3">Login and place an eligible order to review this product.</p>
        <form id="productReviewForm" class="row g-3">
          <input type="hidden" id="reviewProductId" value="">
          <div class="col-12 col-md-4">
            <label for="reviewRating" class="form-label text-light">Rating</label>
            <input type="hidden" id="reviewRating" value="" required>
            <div class="review-stars-input" id="reviewStarsInput" role="radiogroup" aria-label="Select review rating from 1 to 5 stars">
              <button type="button" class="review-star-btn" data-rating="1" aria-label="1 star" aria-pressed="false"><i class="bi bi-star-fill"></i></button>
              <button type="button" class="review-star-btn" data-rating="2" aria-label="2 stars" aria-pressed="false"><i class="bi bi-star-fill"></i></button>
              <button type="button" class="review-star-btn" data-rating="3" aria-label="3 stars" aria-pressed="false"><i class="bi bi-star-fill"></i></button>
              <button type="button" class="review-star-btn" data-rating="4" aria-label="4 stars" aria-pressed="false"><i class="bi bi-star-fill"></i></button>
              <button type="button" class="review-star-btn" data-rating="5" aria-label="5 stars" aria-pressed="false"><i class="bi bi-star-fill"></i></button>
            </div>
            <small class="review-rating-label" id="reviewRatingLabel">Select a rating to continue</small>
          </div>
          <div class="col-12 col-md-8">
            <label for="reviewText" class="form-label text-light">Your Feedback</label>
            <textarea id="reviewText" class="form-control" rows="3" maxlength="500" minlength="10" placeholder="Share your honest experience with this product." required></textarea>
          </div>
          <div class="col-12">
            <label for="reviewImages" class="form-label text-light">Upload Images (Optional)</label>
            <input type="file" id="reviewImages" class="form-control review-file-input" accept="image/png,image/jpeg" multiple>
            <div id="reviewFileSelection" class="review-file-selection">No images selected yet.</div>
            <small class="text-secondary d-block mt-1">PNG/JPG only, max 2 images, each less than 6 MB.</small>
          </div>
          <div class="col-12">
            <button type="submit" class="btn product-btn-buy" id="reviewSubmitBtn">Submit Review</button>
          </div>
        </form>
      </div>
    </section>

    <section class="mt-5 mb-5">
      <h2 class="product-section-title mb-3">Related Products</h2>
      <div class="row" id="related-products-container"></div>
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
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" rel="noopener"
              aria-label="Commerza on Facebook"><i class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" rel="noopener" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" rel="noopener"
              aria-label="Commerza on Instagram"><i class="bi bi-instagram"></i></a>
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
  <script <?= commerza_csp_nonce_attr() ?>>
    window.CommerzaCsrfToken = <?= json_encode((string)$_SESSION['csrf_token']) ?>;

    document.addEventListener("DOMContentLoaded", function() {
      const reviewInput = document.getElementById("reviewImages");
      const reviewSelection = document.getElementById("reviewFileSelection");

      if (!reviewInput || !reviewSelection) {
        return;
      }

      const refreshSelectionLabel = function() {
        const files = Array.from(reviewInput.files || []);
        if (files.length === 0) {
          reviewSelection.textContent = "No images selected yet.";
          return;
        }

        const names = files.map((file) => (file && file.name ? file.name : "image"));
        reviewSelection.textContent = names.join(" | ");
      };

      reviewInput.addEventListener("change", refreshSelectionLabel);
      refreshSelectionLabel();
    });
  </script>
  <script src="frontend/assets/js/script.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>