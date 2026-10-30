<?php
require_once dirname(__DIR__) . '/backend/core/data.php';
require_once COMMERZA_ROOT . '/backend/helpers/products_schema_helpers.php';
require_once COMMERZA_ROOT . '/backend/helpers/wishlist_schema_helpers.php';

commerza_products_ensure_schema($con);
commerza_wishlist_ensure_schema($con);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
  $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
  if (preg_match('#/wishlist\.php$#i', str_replace('\\', '/', $requestPath)) === 1) {
    $queryString = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
    $targetUrl = commerza_absolute_url('/wishlist');
    if ($queryString !== '') {
      $targetUrl .= '?' . $queryString;
    }
    header('Location: ' . $targetUrl, true, 301);
    exit;
  }
}

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
  header('Location: login.php?redirect=wishlist.php');
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = '';


$wishlist_id = get_or_create_wishlist_id($con, $user_id);

if (!$wishlist_id) {
  http_response_code(500);
  exit('Unable to load wishlist.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $action = (string)($_POST['action'] ?? '');

  if ($action === 'add') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id > 0) {
      $checkProductStmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
      if ($checkProductStmt) {
        $checkProductStmt->bind_param('i', $product_id);
        $checkProductStmt->execute();
        $checkProductStmt->store_result();
        $exists = $checkProductStmt->num_rows > 0;
        $checkProductStmt->close();

        if ($exists) {
          $addStmt = $con->prepare('INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE added_at = CURRENT_TIMESTAMP');
          if ($addStmt) {
            $addStmt->bind_param('ii', $wishlist_id, $product_id);
            $addStmt->execute();
            $addStmt->close();
            $success = 'Product added to wishlist.';
          } else {
            $errors[] = 'Unable to add product right now.';
          }
        } else {
          $errors[] = 'Selected product does not exist.';
        }
      } else {
        $errors[] = 'Unable to verify product.';
      }
    } else {
      $errors[] = 'Invalid product selection.';
    }
  }

  if ($action === 'remove') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id > 0) {
      $removeStmt = $con->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1');
      if ($removeStmt) {
        $removeStmt->bind_param('ii', $wishlist_id, $product_id);
        $removeStmt->execute();
        $removeStmt->close();
        $success = 'Product removed from wishlist.';
      } else {
        $errors[] = 'Unable to remove product right now.';
      }
    }
  }

  if ($action === 'clear') {
    $clearStmt = $con->prepare('DELETE FROM wishlist_items WHERE wishlist_id = ?');
    if ($clearStmt) {
      $clearStmt->bind_param('i', $wishlist_id);
      $clearStmt->execute();
      $clearStmt->close();
      $success = 'Wishlist cleared successfully.';
    } else {
      $errors[] = 'Unable to clear wishlist right now.';
    }
  }

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$items = [];
$listStmt = $con->prepare(
  "SELECT p.id, p.name, p.image, p.price, p.salePrice, p.product_code, p.warranty_info, p.dispatch_info, wi.added_at
     FROM wishlist_items wi
     INNER JOIN products p ON p.id = wi.product_id
     WHERE wi.wishlist_id = ?
     ORDER BY wi.added_at DESC"
);

if ($listStmt) {
  $listStmt->bind_param('i', $wishlist_id);
  $listStmt->execute();
  $result = $listStmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $items[] = $row;
  }
  $listStmt->close();
}

$wishlist_count = count($items);
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

$pageCanonicalUrl = commerza_absolute_url('/wishlist');
$pageOgImageUrl = commerza_absolute_url('/' . ltrim($siteLogoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="<?= htmlspecialchars('View and manage your saved ' . $siteBrandName . ' wishlist products.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars('Wishlist | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars('Manage your saved ' . $siteBrandName . ' wishlist items.', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($pageOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title><?= htmlspecialchars('Wishlist | ' . $siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="canonical" href="<?= htmlspecialchars($pageCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": <?= json_encode('Wishlist | ' . $siteBrandName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "url": <?= json_encode($pageCanonicalUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      "description": <?= json_encode('Manage your wishlist products on ' . $siteBrandName . '.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    }
  </script>
  <link rel="icon" href="<?= htmlspecialchars($siteFaviconPath, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/wishlist-inline.css">
</head>

<body class="dark-theme">
  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="successAlert"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php commerza_partial('site-header', ['variant' => 'commerce', 'activePage' => 'wishlist', 'navCounts' => ['wishlist_count' => (int)$wishlist_count]]); ?>

  <main class="container my-5">
    <?php commerza_render_page_breadcrumb('Wishlist'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-heart"></i> Wishlist</span>
        <h1 class="mt-3 wishlist-hero-title">Your Wishlist</h1>
        <p class="product-desc mt-2">Save your favorite watches for later.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="index.php" class="btn product-btn-buy">Browse Products</a>
          <a href="cart.php" class="btn product-btn-cart">Go to Cart</a>
        </div>
      </div>
    </section>

    <section class="wishlist-shell mb-4">
      <div class="row g-4 align-items-center">
        <div class="col-lg-6">
          <div class="wishlist-intro">
            <span class="section-kicker">Saved Picks</span>
            <h2 class="section-title">Build your collection with confidence</h2>
            <p class="product-desc">Turn saved products into a buying shortlist by checking price fit, delivery timing, and warranty terms in one flow.</p>
            <div class="wishlist-stats">
              <div class="stat-card">
                <h3 class="wishlist-stat-value"><?= $wishlist_count ?></h3>
                <p class="product-desc mb-0">Items saved</p>
              </div>
              <div class="stat-card">
                <h3 class="wishlist-stat-value">Smart</h3>
                <p class="product-desc mb-0">Decision mode</p>
              </div>
            </div>
            <div class="wishlist-playbook">
              <h3>Saved Picks Playbook</h3>
              <ul class="wishlist-playbook-list">
                <li><span class="index">1</span><span>Keep only models you can realistically buy this cycle.</span></li>
                <li><span class="index">2</span><span>Prioritize items with best dispatch timeline for your planned purchase date.</span></li>
                <li><span class="index">3</span><span>Move top 1-2 options to cart, then complete checkout without clutter.</span></li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="wishlist-decision-grid">
            <div class="wishlist-decision-item">
              <span class="kicker">Price Check</span>
              <span class="value">Compare current sale prices and keep only the best-value options.</span>
            </div>
            <div class="wishlist-decision-item">
              <span class="kicker">Warranty Focus</span>
              <span class="value">Prioritize products with warranty terms that match your usage plan.</span>
            </div>
            <div class="wishlist-decision-item">
              <span class="kicker">Dispatch Priority</span>
              <span class="value">Pick faster dispatch products first if you need an urgent delivery.</span>
            </div>
            <div class="wishlist-decision-item">
              <span class="kicker">Checkout Discipline</span>
              <span class="value">Move only final choices to cart to avoid accidental over-buying.</span>
            </div>
          </div>
          <div class="info-card d-flex justify-content-between align-items-center mt-3">
            <div>
              <div class="icon-badge"><i class="bi bi-bell"></i></div>
              <h3 class="product-name">Monitored Deals</h3>
              <p class="product-desc mb-2">Think of this as your personal holding area. We’ll flag any price changes or active promo codes while your items wait.</p>
              <hr class="my-2" style="border-top: 1px dashed #ccc;">
              <p class="product-desc mb-0">Track price drops and offers before moving products to cart.</p>
            </div>
            <?php if ($wishlist_count > 0): ?>
              <form action="wishlist.php" method="POST" id="wishlistClearForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="clear">
                <button class="btn product-btn-cart" id="wishlistClearBtn" type="submit">Clear</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Wishlist guide">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0 wishlist-flow-title">Step-by-Step Wishlist Flow</h2>
        <span class="step-chip">Use this flow to keep saved products organized.</span>
      </div>
      <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
          <article class="wishlist-guide-card">
            <span class="wishlist-step-pill">Step 1</span>
            <h3>Save Shortlisted Products</h3>
            <p>Add models you like while browsing so you can compare them later without losing track.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="wishlist-guide-card">
            <span class="wishlist-step-pill">Step 2</span>
            <h3>Review Price and Style</h3>
            <p>Open each saved item and confirm current price, movement type, and design before buying.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="wishlist-guide-card">
            <span class="wishlist-step-pill">Step 3</span>
            <h3>Move to Cart</h3>
            <p>When you are ready, move selected items to cart and finalize shipping/payment details.</p>
          </article>
        </div>
        <div class="col-sm-6 col-xl-3">
          <article class="wishlist-guide-card">
            <span class="wishlist-step-pill">Step 4</span>
            <h3>Keep It Clean</h3>
            <p>Remove old picks regularly to keep your wishlist relevant and easier to manage.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="mb-4" aria-label="Wishlist precautions">
      <div class="wishlist-precaution-panel">
        <h3><i class="bi bi-exclamation-triangle me-2"></i>Wishlist Precautions</h3>
        <ul class="wishlist-precaution-list">
          <li><i class="bi bi-check2-circle"></i><span>Price and stock can change, so always re-check details before moving to checkout.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Use your account login on the same profile to keep your saved list synced correctly.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Move only the products you are ready to buy to avoid accidental cart clutter.</span></li>
          <li><i class="bi bi-check2-circle"></i><span>Clear stale products after seasonal sales so your saved list stays useful.</span></li>
        </ul>
      </div>
    </section>

    <section id="wishlist-container" class="wishlist-list" data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <?php if ($wishlist_count === 0): ?>
        <div class="text-center py-5 wishlist-empty-state">
          <i class="bi bi-heart wishlist-empty-icon"></i>
          <h3 class="text-white mt-3">Your wishlist is empty</h3>
          <p class="text-secondary">Start saving your favorite watches.</p>
          <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
        </div>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <?php $displayProductCode = (string)($item['product_code'] ?: ('CMRZ-' . str_pad((string)((int)$item['id']), 5, '0', STR_PAD_LEFT))); ?>
          <div class="card product-card mb-3 wishlist-item-card"
            data-product-id="<?= (int)$item['id'] ?>"
            data-product-name="<?= htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-product-code="<?= htmlspecialchars($displayProductCode, ENT_QUOTES, 'UTF-8') ?>">
            <div class="card-body d-flex align-items-center gap-3">
              <img src="<?= htmlspecialchars((string)$item['image']) ?>" class="wishlist-img me-2" alt="<?= htmlspecialchars((string)$item['name']) ?>" />
              <div class="flex-grow-1">
                <h3 class="product-name mb-1"><?= htmlspecialchars((string)$item['name']) ?></h3>
                <p class="wishlist-meta">Saved: <?= htmlspecialchars((string)date('M d, Y', strtotime((string)$item['added_at']))) ?></p>
                <div class="wishlist-detail-strip">
                  <span class="wishlist-detail-chip"><i class="bi bi-upc-scan"></i><?= htmlspecialchars($displayProductCode) ?></span>
                  <span class="wishlist-detail-chip"><i class="bi bi-shield-check"></i><?= htmlspecialchars((string)($item['warranty_info'] ?: '12-month seller warranty')) ?></span>
                  <span class="wishlist-detail-chip"><i class="bi bi-truck"></i><?= htmlspecialchars((string)($item['dispatch_info'] ?: 'Dispatch in 24-48 hours')) ?></span>
                </div>
                <div class="mb-2">
                  <span class="original-price" style="text-decoration: line-through; color: #b0b0b0;"><?= number_format((float)$item['price'], 2) ?> PKR</span>
                  <span class="sale-price wishlist-sale-price"><?= number_format((float)($item['salePrice'] ?? $item['price']), 2) ?> PKR</span>
                </div>
                <div class="wishlist-actions">
                  <a href="products.php?id=<?= (int)$item['id'] ?>" class="btn product-btn-buy">View</a>
                  <button type="button" class="btn product-btn-buy wishlist-move-cart-btn" data-product-id="<?= (int)$item['id'] ?>">Move to Cart</button>
                  <form action="wishlist.php" method="POST" class="d-inline wishlist-remove-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                    <button class="btn product-btn-cart wishlist-remove-btn" type="submit" data-product-id="<?= (int)$item['id'] ?>">Remove</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'wishlist']); ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/modules/core/global-protection.js"></script>
  <script src="frontend/assets/js/modules/core/site-settings.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="frontend/assets/js/pages/wishlist.js"></script>
</body>

</html>