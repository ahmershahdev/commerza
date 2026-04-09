<?php
include "backend/data.php";
require_once __DIR__ . '/backend/products_schema_helpers.php';

commerza_products_ensure_schema($con);

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

function get_or_create_wishlist_id(mysqli $con, int $userId): ?int
{
  $selectStmt = $con->prepare('SELECT id FROM wishlist WHERE user_id = ? LIMIT 1');
  if (!$selectStmt) {
    return null;
  }

  $selectStmt->bind_param('i', $userId);
  $selectStmt->execute();
  $result = $selectStmt->get_result();
  if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $selectStmt->close();
    return (int)$row['id'];
  }
  $selectStmt->close();

  $insertStmt = $con->prepare('INSERT INTO wishlist (user_id) VALUES (?)');
  if (!$insertStmt) {
    return null;
  }

  $insertStmt->bind_param('i', $userId);
  $ok = $insertStmt->execute();
  $newId = $ok ? (int)$con->insert_id : null;
  $insertStmt->close();

  return $newId;
}

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

$siteLogoPath = trim((string)($publicSettings['brand']['logo'] ?? 'frontend/assets/images/logo/commerza-logo.webp'));
if ($siteLogoPath === '') {
  $siteLogoPath = 'frontend/assets/images/logo/commerza-logo.webp';
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
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    #serverAlert,
    #successAlert {
      border: none;
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      position: fixed;
      top: 20px;
      right: 0;
      left: 0;
      margin: auto;
      width: 440px;
      max-width: calc(100% - 24px);
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }

    #serverAlert {
      background-color: #dc3545;
    }

    #successAlert {
      background-color: #198754;
    }

    .wishlist-img {
      width: 96px;
      height: 96px;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .wishlist-shell {
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 16px;
      padding: 16px;
      background: linear-gradient(150deg, rgba(20, 20, 20, 0.95), rgba(7, 7, 7, 0.96));
      box-shadow: 0 16px 32px rgba(0, 0, 0, 0.34);
    }

    .wishlist-playbook {
      margin-top: 12px;
      border: 1px solid rgba(255, 102, 0, 0.22);
      border-radius: 12px;
      background: rgba(7, 7, 7, 0.7);
      padding: 12px;
    }

    .wishlist-playbook h3 {
      color: #ffd2aa;
      font-size: 0.9rem;
      margin-bottom: 8px;
    }

    .wishlist-playbook-list {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 8px;
    }

    .wishlist-playbook-list li {
      display: grid;
      grid-template-columns: 24px 1fr;
      gap: 8px;
      align-items: start;
      color: #c9c9c9;
      font-size: 0.82rem;
      line-height: 1.45;
    }

    .wishlist-playbook-list .index {
      width: 24px;
      height: 24px;
      border-radius: 999px;
      border: 1px solid rgba(255, 204, 0, 0.35);
      background: rgba(255, 204, 0, 0.1);
      color: #ffd78b;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 1px;
    }

    .wishlist-decision-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .wishlist-decision-item {
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 12px;
      background: rgba(11, 11, 11, 0.75);
      padding: 11px;
      display: grid;
      gap: 6px;
    }

    .wishlist-decision-item .kicker {
      color: #ffcc9f;
      font-size: 0.68rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 700;
    }

    .wishlist-decision-item .value {
      color: #fff;
      font-size: 0.88rem;
      line-height: 1.4;
    }

    .wishlist-item-card {
      border: 1px solid rgba(255, 102, 0, 0.18);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.24);
      transition: transform 0.2s ease, border-color 0.2s ease, opacity 0.22s ease;
    }

    .wishlist-item-card:hover {
      transform: translateY(-1px);
      border-color: rgba(255, 204, 0, 0.32);
    }

    .wishlist-item-card.is-removing {
      opacity: 0;
      transform: scale(0.985);
    }

    .wishlist-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .wishlist-actions .btn {
      min-width: 112px;
    }

    .wishlist-meta {
      color: #9f9f9f;
      font-size: 0.78rem;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .wishlist-detail-strip {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 10px;
    }

    .wishlist-detail-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border-radius: 999px;
      border: 1px solid rgba(255, 102, 0, 0.24);
      background: rgba(255, 102, 0, 0.1);
      color: #ffd5ac;
      font-size: 0.72rem;
      letter-spacing: 0.03em;
      padding: 4px 10px;
      line-height: 1.2;
    }

    .wishlist-detail-chip i {
      color: #ffcc00;
    }

    .wishlist-guide-card {
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 14px;
      background: linear-gradient(152deg, rgba(20, 20, 20, 0.95), rgba(8, 8, 8, 0.96));
      box-shadow: 0 12px 24px rgba(0, 0, 0, 0.28);
      padding: 14px;
      height: 100%;
    }

    .wishlist-step-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      border: 1px solid rgba(255, 204, 0, 0.35);
      background: rgba(255, 204, 0, 0.1);
      color: #ffda94;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.72rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      padding: 2px 10px;
      margin-bottom: 8px;
    }

    .wishlist-guide-card h3 {
      color: #fff;
      font-size: 0.96rem;
      margin-bottom: 6px;
    }

    .wishlist-guide-card p {
      color: #b8b8b8;
      margin-bottom: 0;
      font-size: 0.84rem;
      line-height: 1.5;
    }

    .wishlist-precaution-panel {
      border: 1px solid rgba(255, 153, 61, 0.3);
      border-radius: 16px;
      background: linear-gradient(145deg, rgba(25, 22, 18, 0.92), rgba(10, 10, 9, 0.95));
      box-shadow: 0 14px 30px rgba(0, 0, 0, 0.3);
      padding: 16px;
    }

    .wishlist-precaution-panel h3 {
      color: #ffd7a8;
      font-size: 1.02rem;
      margin-bottom: 12px;
    }

    .wishlist-precaution-list {
      list-style: none;
      padding-left: 0;
      margin: 0;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px 12px;
    }

    .wishlist-precaution-list li {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      color: #d0d0d0;
      font-size: 0.84rem;
      line-height: 1.45;
    }

    .wishlist-precaution-list i {
      color: #ffb86b;
      margin-top: 1px;
    }

    @media (max-width: 575px) {
      .wishlist-actions .btn {
        width: 100%;
      }

      .wishlist-decision-grid {
        grid-template-columns: 1fr;
      }

      .wishlist-precaution-list {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body class="dark-theme">
  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="successAlert"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Logo" loading="lazy"
            class="navbar-logo me-2" />
          <span class="brand-text"><?= htmlspecialchars(strtoupper($siteBrandName), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="d-flex align-items-center order-lg-2">
          <ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" aria-current="page" href="wishlist.php" aria-label="View wishlist">
                <i class="bi bi-heart"></i>
                <span class="nav-badge" id="wishlist-count"><?= $wishlist_count ?></span>
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
          <img src="<?= htmlspecialchars($siteLogoPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> Logo" loading="lazy"
            class="offcanvas-logo me-2" />
          <span class="brand-text"><?= htmlspecialchars(strtoupper($siteBrandName), ENT_QUOTES, 'UTF-8') ?></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="offcanvas-user-actions">
          <a href="wishlist.php" class="offcanvas-action-btn" aria-current="page">
            <i class="bi bi-heart"></i>
            <span>Wishlist</span>
            <span class="offcanvas-badge" id="wishlist-count-mobile"><?= $wishlist_count ?></span>
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
    <?php commerza_render_page_breadcrumb('Wishlist'); ?>
    <section class="page-hero mb-5">
      <div class="hero-content">
        <span class="hero-badge"><i class="bi bi-heart"></i> Wishlist</span>
        <h1 class="mt-3" style="color: #ff6600">Your Wishlist</h1>
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
                <h3 style="color:#ff6600; margin:0;"><?= $wishlist_count ?></h3>
                <p class="product-desc mb-0">Items saved</p>
              </div>
              <div class="stat-card">
                <h3 style="color:#ff6600; margin:0;">Smart</h3>
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
        <h2 class="mb-0" style="color: #ff6600; font-size: 1.2rem;">Step-by-Step Wishlist Flow</h2>
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
          <i class="bi bi-heart" style="font-size: 3rem; color: #ff6600;"></i>
          <h3 class="text-white mt-3">Your wishlist is empty</h3>
          <p class="text-secondary">Start saving your favorite watches.</p>
          <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
        </div>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <div class="card product-card mb-3 wishlist-item-card" data-product-id="<?= (int)$item['id'] ?>">
            <div class="card-body d-flex align-items-center gap-3">
              <img src="<?= htmlspecialchars((string)$item['image']) ?>" class="wishlist-img me-2" alt="<?= htmlspecialchars((string)$item['name']) ?>" />
              <div class="flex-grow-1">
                <h3 class="product-name mb-1"><?= htmlspecialchars((string)$item['name']) ?></h3>
                <p class="wishlist-meta">Saved: <?= htmlspecialchars((string)date('M d, Y', strtotime((string)$item['added_at']))) ?></p>
                <div class="wishlist-detail-strip">
                  <span class="wishlist-detail-chip"><i class="bi bi-upc-scan"></i><?= htmlspecialchars((string)($item['product_code'] ?: ('CMRZ-' . str_pad((string)((int)$item['id']), 5, '0', STR_PAD_LEFT)))) ?></span>
                  <span class="wishlist-detail-chip"><i class="bi bi-shield-check"></i><?= htmlspecialchars((string)($item['warranty_info'] ?: '12-month seller warranty')) ?></span>
                  <span class="wishlist-detail-chip"><i class="bi bi-truck"></i><?= htmlspecialchars((string)($item['dispatch_info'] ?: 'Dispatch in 24-48 hours')) ?></span>
                </div>
                <div class="mb-2">
                  <span class="original-price" style="text-decoration: line-through; color: #b0b0b0;"><?= number_format((float)$item['price'], 2) ?> PKR</span>
                  <span class="sale-price" style="color: #ff6600; font-weight: bold; margin-left: 6px;"><?= number_format((float)($item['salePrice'] ?? $item['price']), 2) ?> PKR</span>
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
            <li><a href="about.php">About Us</a></li>
            <li><a href="contact.php">Contact</a></li>
            <li><a href="wishlist.php" aria-current="page">Wishlist</a></li>
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
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="<?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> on Instagram"><i
                class="bi bi-instagram"></i></a>
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

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js"></script>
  <script src="frontend/assets/js/modules/core/site-settings.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    if (typeof applySiteSettings === "function") {
      applySiteSettings();
    }

    $(function() {
      let csrfToken = ($('#wishlist-container').data('csrfToken') || '').toString();

      function refreshAlertFade() {
        $("#serverAlert, #successAlert").each(function() {
          const element = $(this);
          setTimeout(function() {
            element.fadeOut(400);
          }, 3500);
        });
      }

      function ensureAlert(id, cssClass) {
        let alertElement = $('#' + id);
        if (alertElement.length) {
          return alertElement;
        }

        alertElement = $('<div/>', {
          id,
          class: cssClass,
          role: 'alert'
        }).hide();

        $('body').append(alertElement);
        return alertElement;
      }

      function flashAlert(type, message) {
        if (!message) {
          return;
        }

        const isError = type === 'error';
        const id = isError ? 'serverAlert' : 'successAlert';
        const cssClass = isError ? 'alert alert-danger text-center' : 'alert alert-success text-center';

        const element = ensureAlert(id, cssClass);
        element.stop(true, true).text(message).fadeIn(160).delay(2600).fadeOut(320);
      }

      function syncCsrf(nextToken) {
        const token = (nextToken || '').toString().trim();
        if (!token) {
          return;
        }

        csrfToken = token;
        $('input[name="csrf_token"]').val(token);
        $('#wishlist-container').attr('data-csrf-token', token);
      }

      function updateWishlistCount(count) {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        $('#wishlist-count').text(safeCount);
        $('#wishlist-count-mobile').text(safeCount);
      }

      function updateCartCount(count) {
        const safeCount = Number.isFinite(Number(count)) ? Number(count) : 0;
        $('#cart-count').text(safeCount);
        $('#cart-count-mobile').text(safeCount);
      }

      function renderEmptyStateIfNeeded() {
        const cards = $('#wishlist-container .wishlist-item-card');
        if (cards.length > 0) {
          return;
        }

        $('#wishlist-container').html(`
          <div class="text-center py-5 wishlist-empty-state">
            <i class="bi bi-heart" style="font-size: 3rem; color: #ff6600;"></i>
            <h3 class="text-white mt-3">Your wishlist is empty</h3>
            <p class="text-secondary">Start saving your favorite watches.</p>
            <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
          </div>
        `);

        updateWishlistCount(0);
        $('#wishlistClearForm').addClass('d-none');
      }

      async function postWishlistAction(action, payload) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken || '');

        Object.entries(payload || {}).forEach(([key, value]) => {
          body.set(key, String(value));
        });

        const response = await fetch('backend/wishlist_api.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await response.json();
        syncCsrf(data?.csrf_token || '');

        if (!response.ok || !data?.ok) {
          throw new Error((data?.message || 'Unable to update wishlist.').toString());
        }

        if (typeof data?.count !== 'undefined') {
          updateWishlistCount(data.count);
        }

        return data;
      }

      async function addToCart(productId, retry = 0) {
        const body = new URLSearchParams();
        body.set('action', 'add');
        body.set('product_id', String(productId));
        body.set('quantity', '1');
        body.set('csrf_token', csrfToken || '');

        const response = await fetch('backend/cart_api.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
          },
          body: body.toString()
        });

        const data = await response.json();
        syncCsrf(data?.csrf_token || '');

        if ((response.status === 403 || response.status === 409) && data?.csrf_token && retry < 1) {
          return addToCart(productId, retry + 1);
        }

        if (!response.ok || !data?.ok) {
          throw new Error((data?.message || 'Unable to add item to cart.').toString());
        }

        if (typeof data?.count !== 'undefined') {
          updateCartCount(data.count);
        }

        return data;
      }

      $(document).on('submit', '.wishlist-remove-form', async function(event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('.wishlist-remove-btn');
        const card = form.closest('.wishlist-item-card');
        const productId = parseInt(button.data('productId') || form.find('input[name="product_id"]').val(), 10);

        if (!Number.isInteger(productId) || productId <= 0) {
          flashAlert('error', 'Invalid wishlist item.');
          return;
        }

        button.prop('disabled', true);

        try {
          const result = await postWishlistAction('toggle', {
            product_id: productId
          });
          if (result.added) {
            await postWishlistAction('toggle', {
              product_id: productId
            });
          }

          card.addClass('is-removing');
          window.setTimeout(() => {
            card.remove();
            renderEmptyStateIfNeeded();
          }, 210);

          flashAlert('success', 'Product removed from wishlist.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to remove product right now.');
          button.prop('disabled', false);
        }
      });

      $(document).on('click', '.wishlist-move-cart-btn', async function() {
        const button = $(this);
        const productId = parseInt(button.data('productId'), 10);
        const card = button.closest('.wishlist-item-card');

        if (!Number.isInteger(productId) || productId <= 0) {
          flashAlert('error', 'Invalid wishlist item.');
          return;
        }

        button.prop('disabled', true).text('Moving...');

        try {
          await addToCart(productId);
          const result = await postWishlistAction('toggle', {
            product_id: productId
          });
          if (result.added) {
            await postWishlistAction('toggle', {
              product_id: productId
            });
          }

          card.addClass('is-removing');
          window.setTimeout(() => {
            card.remove();
            renderEmptyStateIfNeeded();
          }, 210);

          flashAlert('success', 'Moved to cart successfully.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to move item to cart.');
          button.prop('disabled', false).text('Move to Cart');
        }
      });

      $(document).on('submit', '#wishlistClearForm', async function(event) {
        event.preventDefault();
        const form = $(this);
        const button = $('#wishlistClearBtn');

        button.prop('disabled', true).text('Clearing...');

        try {
          await postWishlistAction('clear', {});
          $('#wishlist-container .wishlist-item-card').remove();
          renderEmptyStateIfNeeded();
          flashAlert('success', 'Wishlist cleared successfully.');
        } catch (error) {
          flashAlert('error', error?.message || 'Unable to clear wishlist right now.');
          button.prop('disabled', false).text('Clear');
          return;
        }

        form.addClass('d-none');
      });

      refreshAlertFade();
    });
  </script>
</body>

</html>