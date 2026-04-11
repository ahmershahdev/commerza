<?php
require_once __DIR__ . '/backend/core/data.php';

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
  <link rel="stylesheet" href="frontend/assets/css/pages/products-inline.css">
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
    <?php commerza_render_page_breadcrumb('Products'); ?>
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
        <p id="reviewLockNotice" class="small text-warning mb-3 d-none"></p>
        <form id="productReviewForm" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
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
            <input type="file" id="reviewImages" class="form-control review-file-input" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
            <div id="reviewFileSelection" class="review-file-selection">No images selected yet.</div>
            <div class="upload-progress-shell mt-2 d-none" id="reviewUploadProgress">
              <small class="text-secondary d-block" data-upload-stage>Waiting to optimize selected images...</small>
              <div class="progress mt-1" style="height: 6px;">
                <div class="progress-bar bg-warning" role="progressbar" data-upload-bar style="width: 0%">0%</div>
              </div>
            </div>
            <div id="reviewRemoveExistingWrap" class="form-check mt-2 d-none">
              <input class="form-check-input" type="checkbox" value="1" id="reviewRemoveExistingImages">
              <label class="form-check-label text-light small" for="reviewRemoveExistingImages">Remove previously uploaded review images when updating.</label>
            </div>
            <small class="text-secondary d-block mt-1">JPG, PNG, WEBP, or GIF. Max 2 images, each less than 6 MB. Selected images are converted to optimized WEBP before upload.</small>
          </div>
          <div class="col-12">
            <button type="submit" class="btn product-btn-buy" id="reviewSubmitBtn">Submit Review</button>
          </div>
        </form>
      </div>
    </section>

    <section class="mt-5 mb-5">
      <h2 class="product-section-title mb-3">Related Products</h2>
      <div class="row g-3" id="related-products-container"></div>
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
  <script src="frontend/assets/js/modules/core/global-protection.js" defer></script>
  <script src="frontend/assets/js/modules/services/auth.js" defer></script>
  <script src="frontend/assets/js/pages/products.js" data-csrf-token="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="frontend/assets/js/modules/bootstrap/script.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>
