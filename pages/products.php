<?php
require_once dirname(__DIR__) . '/backend/core/data.php';

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
$productsImageUrl = commerza_absolute_url('/frontend/assets/images/logo/commerza_logo.svg');
$seoProduct = null;
$seoSlug = strtolower(trim((string)($_GET['slug'] ?? '')));
if ($seoSlug !== '' && preg_match('/^[a-z0-9-]{1,120}$/', $seoSlug) === 1) {
  $seoStmt = $con->prepare('SELECT name, slug, description, image, price, salePrice, stock, product_code FROM products WHERE slug = ? AND deleted_at IS NULL LIMIT 1');
  if ($seoStmt) {
    $seoStmt->bind_param('s', $seoSlug);
    $seoStmt->execute();
    $seoProduct = $seoStmt->get_result()->fetch_assoc() ?: null;
    $seoStmt->close();
  }
}

$seoShorten = static function (string $text, int $max): string {
  $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
  return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
};

$seoTitle = 'Luxury Watch Collection | Commerza';
$seoDescription = 'Shop premium automatic, minimalist, sports and smart watches at Commerza with COD and secure card checkout.';
$seoHeading = 'Commerza Watch Collection';
$seoJsonLd = [
  '@context' => 'https://schema.org',
  '@graph' => [
    ['@type' => 'CollectionPage', 'name' => $seoTitle, 'url' => $productsPageUrl, 'description' => $seoDescription],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => commerza_absolute_url('/')],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Products', 'item' => commerza_absolute_url('/products')],
    ]],
  ],
];

if ($seoProduct) {
  $seoName = (string)$seoProduct['name'];
  $seoHeading = $seoName;
  $seoTitle = $seoShorten($seoName, 48) . ' | Commerza';
  $seoDescription = $seoShorten((string)($seoProduct['description'] ?: $seoName . ' at Commerza.'), 150);
  $seoImage = trim((string)$seoProduct['image']);
  if ($seoImage !== '') {
    $productsImageUrl = commerza_absolute_url($seoImage);
  }
  $seoPrice = (float)($seoProduct['salePrice'] !== null && (float)$seoProduct['salePrice'] > 0 ? $seoProduct['salePrice'] : $seoProduct['price']);
  $seoJsonLd['@graph'][0] = [
    '@type' => 'Product',
    'name' => $seoName,
    'description' => $seoDescription,
    'image' => $productsImageUrl,
    'sku' => (string)($seoProduct['product_code'] ?? ''),
    'brand' => ['@type' => 'Brand', 'name' => 'Commerza'],
    'url' => $productsPageUrl,
    'offers' => [
      '@type' => 'Offer',
      'priceCurrency' => 'PKR',
      'price' => number_format($seoPrice, 2, '.', ''),
      'availability' => (int)$seoProduct['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      'url' => $productsPageUrl,
    ],
  ];
  $seoJsonLd['@graph'][1]['itemListElement'][] = ['@type' => 'ListItem', 'position' => 3, 'name' => $seoName, 'item' => $productsPageUrl];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description" content="<?= commerza_e($seoDescription) ?>">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="<?= commerza_e($seoTitle) ?>">
  <meta property="og:description" content="<?= commerza_e($seoDescription) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($productsPageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="product">
  <meta property="og:image" content="<?= htmlspecialchars($productsImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= commerza_e($seoTitle) ?>">
  <meta name="twitter:description" content="<?= commerza_e($seoDescription) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($productsImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= commerza_e($seoTitle) ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($productsPageUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json"><?= json_encode($seoJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
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
  <?php commerza_partial('site-header', ['variant' => 'commerce', 'activePage' => 'products']); ?>

  <main class="container product-hero">
    <h1 class="visually-hidden"><?= commerza_e($seoHeading) ?></h1>
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
          <h3 class="h5 mb-0 text-white product-section-title">Share Your Review</h3>
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

  <?php commerza_partial('site-footer', ['activePage' => 'products']); ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/modules/core/global-protection.js" defer></script>
  <script src="frontend/assets/js/modules/services/auth.js" defer></script>
  <script src="frontend/assets/js/pages/products.js" data-csrf-token="<?= htmlspecialchars((string)$_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
</body>

</html>
