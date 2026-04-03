<?php
include "backend/data.php";

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
    "SELECT p.id, p.name, p.image, p.price, p.salePrice, wi.added_at
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="View and manage your saved Commerza wishlist products.">
  <meta property="og:title" content="Wishlist | Commerza">
  <meta property="og:description" content="Manage your saved Commerza wishlist items.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/wishlist.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Wishlist | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/wishlist.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Wishlist | Commerza",
      "url": "https://commerza.ahmershah.dev/wishlist.php",
      "description": "Manage your wishlist products on Commerza."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
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
            <h2 class="section-title">Build your collection</h2>
            <p class="product-desc">Compare styles, track prices, and move favorites to cart when you are ready.</p>
            <div class="wishlist-stats">
              <div class="stat-card">
                <h3 style="color:#ff6600; margin:0;"><?= $wishlist_count ?></h3>
                <p class="product-desc mb-0">Items saved</p>
              </div>
              <div class="stat-card">
                <h3 style="color:#ff6600; margin:0;">4x</h3>
                <p class="product-desc mb-0">Compare ready</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="info-card">
                <div class="icon-badge"><i class="bi bi-heart-fill"></i></div>
                <h3 class="product-name">Save Favorites</h3>
                <p class="product-desc">Keep your top picks in one place.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="info-card">
                <div class="icon-badge"><i class="bi bi-lightning"></i></div>
                <h3 class="product-name">Quick Checkout</h3>
                <p class="product-desc">Move items to cart in seconds.</p>
              </div>
            </div>
            <div class="col-md-12">
              <div class="info-card d-flex justify-content-between align-items-center">
                <div>
                  <div class="icon-badge"><i class="bi bi-bell"></i></div>
                  <h3 class="product-name">Watch Deals</h3>
                  <p class="product-desc mb-0">Track price drops and offers.</p>
                </div>
                <?php if ($wishlist_count > 0): ?>
                  <form action="wishlist.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="clear">
                    <button class="btn product-btn-cart" type="submit">Clear</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="wishlist-container" class="wishlist-list">
      <?php if ($wishlist_count === 0): ?>
        <div class="text-center py-5">
          <i class="bi bi-heart" style="font-size: 3rem; color: #ff6600;"></i>
          <h3 class="text-white mt-3">Your wishlist is empty</h3>
          <p class="text-secondary">Start saving your favorite watches.</p>
          <a href="index.php" class="btn product-btn-buy mt-3">Browse Products</a>
        </div>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <div class="card product-card mb-3">
            <div class="card-body d-flex align-items-center gap-3">
              <img src="<?= htmlspecialchars((string)$item['image']) ?>" class="wishlist-img me-2" alt="<?= htmlspecialchars((string)$item['name']) ?>" />
              <div class="flex-grow-1">
                <h3 class="product-name mb-1"><?= htmlspecialchars((string)$item['name']) ?></h3>
                <div class="mb-2">
                  <span class="original-price" style="text-decoration: line-through; color: #b0b0b0;"><?= number_format((float)$item['price'], 2) ?> PKR</span>
                  <span class="sale-price" style="color: #ff6600; font-weight: bold; margin-left: 6px;"><?= number_format((float)($item['salePrice'] ?? $item['price']), 2) ?> PKR</span>
                </div>
                <div class="d-flex gap-2">
                  <a href="products.php?id=<?= (int)$item['id'] ?>" class="btn product-btn-buy">View</a>
                  <form action="wishlist.php" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                    <button class="btn product-btn-cart" type="submit">Remove</button>
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
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="Commerza on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="Commerza on Instagram"><i
                class="bi bi-instagram"></i></a>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function () {
      $("#serverAlert, #successAlert").each(function () {
        const element = $(this);
        setTimeout(function () {
          element.fadeOut(400);
        }, 3500);
      });
    });
  </script>
</body>

</html>
