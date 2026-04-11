<?php

declare(strict_types=1);

include "backend/data.php";
require_once __DIR__ . '/backend/nav_state.php';

$nav_counts = commerza_get_nav_counts($con);

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
  $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
  if (preg_match('#/compare\.php$#i', str_replace('\\', '/', $requestPath)) === 1) {
    $queryString = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
    $targetUrl = commerza_absolute_url('/compare');
    if ($queryString !== '') {
      $targetUrl .= '?' . $queryString;
    }
    header('Location: ' . $targetUrl, true, 301);
    exit;
  }
}

if (!isset($_SESSION['compare_product_ids']) || !is_array($_SESSION['compare_product_ids'])) {
  $_SESSION['compare_product_ids'] = [];
}

function sanitize_compare_ids($raw): array
{
  if (is_array($raw)) {
    $raw = implode(',', $raw);
  }

  $parts = explode(',', (string)$raw);
  $clean = [];

  foreach ($parts as $part) {
    $value = (int)trim($part);
    if ($value > 0) {
      $clean[$value] = $value;
    }
  }

  return array_slice(array_values($clean), 0, 4);
}

function compare_image_path(string $value): string
{
  $path = trim(str_replace('\\', '/', $value));
  if ($path === '') {
    return 'frontend/assets/images/logo/commerza-logo.webp';
  }

  if (preg_match('#^https?://#i', $path) === 1) {
    return preg_replace('/[\x00-\x1F\x7F]/', '', $path) ?? $path;
  }

  if (str_starts_with($path, '/')) {
    $path = ltrim($path, '/');
  }

  if (!str_starts_with($path, 'frontend/assets/')) {
    $path = 'frontend/assets/images/products/' . ltrim($path, '/');
  }

  $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $path);
  return $sanitized !== null && $sanitized !== ''
    ? $sanitized
    : 'frontend/assets/images/logo/commerza-logo.webp';
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

  if ($action === 'sync_compare') {
    $_SESSION['compare_product_ids'] = sanitize_compare_ids($_POST['compare_ids'] ?? '');
    header('Location: ' . commerza_absolute_url('/compare'));
    exit;
  }

  if ($action === 'remove_compare') {
    $remove_id = (int)($_POST['product_id'] ?? 0);
    $_SESSION['compare_product_ids'] = array_values(array_filter(
      $_SESSION['compare_product_ids'],
      static fn($id): bool => (int)$id !== $remove_id
    ));
    header('Location: ' . commerza_absolute_url('/compare'));
    exit;
  }

  if ($action === 'clear_compare') {
    $_SESSION['compare_product_ids'] = [];
    header('Location: ' . commerza_absolute_url('/compare'));
    exit;
  }
}

if (!empty($_GET['ids'])) {
  $_SESSION['compare_product_ids'] = sanitize_compare_ids($_GET['ids']);
}

$compare_ids = array_values(array_filter(
  $_SESSION['compare_product_ids'],
  static fn($id): bool => is_numeric($id) && (int)$id > 0
));

$compare_products = [];

if (!empty($compare_ids)) {
  $placeholders = implode(',', array_fill(0, count($compare_ids), '?'));
  $sql = "SELECT id, name, image, price, salePrice, stock, movement FROM products WHERE id IN ($placeholders)";

  $stmt = $con->prepare($sql);
  if ($stmt) {
    $types = str_repeat('i', count($compare_ids));
    $params = [$types];
    foreach ($compare_ids as $index => $value) {
      $params[] = &$compare_ids[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $params);
    $stmt->execute();
    $result = $stmt->get_result();

    $products_map = [];
    while ($row = $result->fetch_assoc()) {
      $row['image'] = compare_image_path((string)($row['image'] ?? ''));
      $products_map[(int)$row['id']] = $row;
    }

    foreach ($compare_ids as $id) {
      if (isset($products_map[(int)$id])) {
        $compare_products[] = $products_map[(int)$id];
      }
    }

    $stmt->close();
  }
}

function compare_effective_price(array $item): float
{
  $price = (float)($item['price'] ?? 0);
  $sale = (float)($item['salePrice'] ?? 0);

  if ($sale > 0 && $sale < $price) {
    return $sale;
  }

  return $price;
}

function compare_savings_amount(array $item): float
{
  $price = (float)($item['price'] ?? 0);
  $effective = compare_effective_price($item);
  return max(0, $price - $effective);
}

function compare_savings_percent(array $item): int
{
  $price = (float)($item['price'] ?? 0);
  $savings = compare_savings_amount($item);

  if ($price <= 0 || $savings <= 0) {
    return 0;
  }

  return (int)round(($savings / $price) * 100);
}

$effectivePrices = [];
$stockValues = [];

foreach ($compare_products as $item) {
  $effectivePrices[] = compare_effective_price($item);
  $stockValues[] = (int)($item['stock'] ?? 0);
}

$lowestEffectivePrice = !empty($effectivePrices) ? min($effectivePrices) : 0;
$highestStock = !empty($stockValues) ? max($stockValues) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Compare Commerza watches side by side." />
  <meta name="robots" content="noindex, follow" />
  <meta name="author" content="Syed Ahmer Shah" />
  <meta property="og:title" content="Compare | Commerza" />
  <meta property="og:description" content="Compare Commerza watches side by side." />
  <meta property="og:url" content="https://commerza.ahmershah.dev/compare.php" />
  <meta property="og:type" content="website" />
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp" />
  <title>Compare | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/compare.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Compare | Commerza",
      "url": "https://commerza.ahmershah.dev/compare.php",
      "description": "Compare Commerza products side by side."
    }
  </script>

  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/compare-inline.css">
</head>

<body class="dark-theme">
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
                <span class="nav-badge" id="cart-count"><?= (int)$nav_counts['cart_count'] ?></span>
              </a>
            </li>
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" href="wishlist.php" aria-label="View wishlist">
                <i class="bi bi-heart"></i>
                <span class="nav-badge" id="wishlist-count"><?= (int)$nav_counts['wishlist_count'] ?></span>
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
            <span class="offcanvas-badge" id="cart-count-mobile"><?= (int)$nav_counts['cart_count'] ?></span>
          </a>
          <a href="wishlist.php" class="offcanvas-action-btn">
            <i class="bi bi-heart"></i>
            <span>Wishlist</span>
            <span class="offcanvas-badge" id="wishlist-count-mobile"><?= (int)$nav_counts['wishlist_count'] ?></span>
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

  <main class="container my-5">
    <?php commerza_render_page_breadcrumb('Compare'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-sliders"></i> Compare</span>
        <h1 class="mt-3" style="color: #ff6600">Compare Watches</h1>
        <p class="product-desc mt-2">Review your saved products side by side.</p>
        <div class="hero-actions d-flex flex-wrap gap-2 mt-3">
          <a href="index.php" class="btn product-btn-buy">Browse Products</a>
          <a href="wishlist.php" class="btn product-btn-cart">Go to Wishlist</a>
        </div>
      </div>
    </section>

    <form action="compare.php" method="POST" id="compareSyncForm" class="d-none">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="sync_compare">
      <input type="hidden" name="compare_ids" id="compareIdsInput" value="">
    </form>

    <div id="compareSessionData" data-session-ids="<?= htmlspecialchars(implode(',', $compare_ids)) ?>"></div>

    <?php if (count($compare_products) === 0): ?>
      <section class="text-center py-5">
        <i class="bi bi-sliders" style="font-size: 3rem; color: #ff6600;"></i>
        <h3 class="text-white mt-3">No products to compare</h3>
        <p class="text-secondary">Add items from a product page to compare.</p>
        <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
      </section>
    <?php else: ?>
      <section id="compare-container">
        <div class="compare-shell">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="compare-meta d-flex flex-wrap gap-2 align-items-center">
              <span class="compare-summary-chip"><i class="bi bi-layers me-1"></i><?= count($compare_products) ?> product<?= count($compare_products) === 1 ? '' : 's' ?></span>
              <span class="compare-summary-chip"><i class="bi bi-tag me-1"></i>Best price highlighted</span>
              <span class="compare-summary-chip"><i class="bi bi-box-seam me-1"></i>Highest stock highlighted</span>
            </div>
            <form action="compare.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="clear_compare">
              <button type="submit" class="btn product-btn-cart">Clear Compare</button>
            </form>
          </div>

          <div class="compare-product-grid">
            <?php foreach ($compare_products as $item): ?>
              <?php
              $effective = compare_effective_price($item);
              $savingsPercent = compare_savings_percent($item);
              $savingsAmount = compare_savings_amount($item);
              ?>
              <article class="compare-product-card">
                <img src="<?= htmlspecialchars((string)$item['image']) ?>" alt="<?= htmlspecialchars((string)$item['name']) ?>" class="compare-product-image" />
                <h3 class="compare-product-name"><?= htmlspecialchars((string)$item['name']) ?></h3>
                <div class="compare-price-row">
                  <span class="compare-price-current">PKR <?= number_format($effective, 0) ?></span>
                  <?php if ($savingsAmount > 0): ?>
                    <span class="compare-price-old">PKR <?= number_format((float)$item['price'], 0) ?></span>
                    <span class="compare-badge good">-<?= (int)$savingsPercent ?>%</span>
                  <?php endif; ?>
                </div>
                <form action="compare.php" method="POST" class="mt-2">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="remove_compare">
                  <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                  <button class="btn product-btn-buy w-100" type="submit">Remove From Compare</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="compare-table-wrap">
            <table class="table table-dark table-bordered align-middle compare-matrix">
              <thead>
                <tr>
                  <th class="feature-col">Feature</th>
                  <?php foreach ($compare_products as $item): ?>
                    <th><?= htmlspecialchars((string)$item['name']) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th scope="row" class="feature-col">Image</th>
                  <?php foreach ($compare_products as $item): ?>
                    <td>
                      <img
                        src="<?= htmlspecialchars((string)$item['image']) ?>"
                        alt="<?= htmlspecialchars((string)$item['name']) ?>"
                        class="compare-table-product-image"
                        loading="lazy" />
                    </td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <th scope="row" class="feature-col">Current Price</th>
                  <?php foreach ($compare_products as $item): ?>
                    <?php $effective = compare_effective_price($item); ?>
                    <td>
                      <?php if ($effective > 0 && abs($effective - $lowestEffectivePrice) < 0.001): ?>
                        <span class="compare-cell-highlight"><i class="bi bi-award"></i>PKR <?= number_format($effective, 0) ?></span>
                      <?php else: ?>
                        PKR <?= number_format($effective, 0) ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <th scope="row" class="feature-col">Original Price</th>
                  <?php foreach ($compare_products as $item): ?>
                    <td>PKR <?= number_format((float)$item['price'], 0) ?></td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <th scope="row" class="feature-col">Savings</th>
                  <?php foreach ($compare_products as $item): ?>
                    <?php
                    $savingsAmount = compare_savings_amount($item);
                    $savingsPercent = compare_savings_percent($item);
                    ?>
                    <td>
                      <?php if ($savingsAmount > 0): ?>
                        <span class="compare-badge good">PKR <?= number_format($savingsAmount, 0) ?> (<?= (int)$savingsPercent ?>%)</span>
                      <?php else: ?>
                        <span class="text-secondary">No discount</span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <th scope="row" class="feature-col">Movement</th>
                  <?php foreach ($compare_products as $item): ?>
                    <td><?= htmlspecialchars((string)($item['movement'] ?: 'quartz')) ?></td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <th scope="row" class="feature-col">Availability</th>
                  <?php foreach ($compare_products as $item): ?>
                    <?php $stockValue = (int)($item['stock'] ?? 0); ?>
                    <td>
                      <?php if ($stockValue <= 0): ?>
                        <span class="text-danger">Out of stock</span>
                      <?php elseif ($stockValue === $highestStock): ?>
                        <span class="compare-cell-highlight"><i class="bi bi-box2-heart"></i><?= $stockValue ?> in stock</span>
                      <?php else: ?>
                        <span class="text-success"><?= $stockValue ?> in stock</span>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    <?php endif; ?>
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
  <script src="frontend/assets/js/global-protection.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="frontend/assets/js/pages/compare.js"></script>
</body>

</html>