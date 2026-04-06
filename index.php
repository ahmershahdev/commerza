<?php
include "backend/data.php";
include_once "backend/nav_state.php";
$nav_counts = commerza_get_nav_counts($con);

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$appBaseHref = rtrim(commerza_public_base_url(), '/') . '/';

$home_feature_video = 'frontend/assets/videos/slider/steel_watch_1.mp4';
$homeVideoStmt = $con->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ? LIMIT 1');
if ($homeVideoStmt) {
  $settingKey = 'home_feature_video';
  $homeVideoStmt->bind_param('s', $settingKey);
  $homeVideoStmt->execute();
  $homeVideoResult = $homeVideoStmt->get_result();
  $homeVideoRow = $homeVideoResult ? $homeVideoResult->fetch_assoc() : null;
  $homeVideoStmt->close();

  $savedVideo = trim((string)($homeVideoRow['setting_val'] ?? ''));
  if (
    $savedVideo !== '' &&
    strpos($savedVideo, '..') === false &&
    strpos($savedVideo, '\\') === false &&
    preg_match('#^[a-zA-Z0-9/_\-.]+$#', $savedVideo) === 1
  ) {
    $home_feature_video = $savedVideo;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="description"
    content="Commerza brings you premium automatic watches—crafted with elegant leather, gold dials, and modern design. Discover luxury timepieces for your unique lifestyle.">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="Commerza Premium Automatic Watches">
  <meta property="og:description"
    content="Explore Commerza's collection of premium automatic watches with elegant leather and gold dials.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Commerza Premium Automatic Watches">
  <meta name="twitter:description" content="Explore Commerza's collection of premium automatic watches with elegant leather and gold dials.">
  <meta name="twitter:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Commerza | Full-Stack Ecommerce</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/" />
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico">
  <link rel="stylesheet" href="frontend/assets/css/style.css">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <style>
    .app-coming-home {
      border: 1px solid rgba(255, 130, 64, 0.42);
      border-radius: 28px;
      padding: clamp(20px, 3vw, 32px);
      background:
        radial-gradient(circle at 12% 4%, rgba(255, 184, 108, 0.24), transparent 35%),
        radial-gradient(circle at 92% 98%, rgba(255, 120, 58, 0.18), transparent 44%),
        linear-gradient(155deg, rgba(12, 12, 13, 0.98), rgba(26, 16, 14, 0.96));
      box-shadow: 0 26px 58px rgba(0, 0, 0, 0.44);
      overflow: hidden;
      position: relative;
    }

    .app-coming-stage-glow {
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 50% -6%, rgba(255, 217, 164, 0.24), transparent 34%),
        radial-gradient(circle at 50% 108%, rgba(255, 122, 66, 0.16), transparent 44%);
      z-index: 1;
      pointer-events: none;
    }

    .app-coming-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
      gap: 22px;
      position: relative;
      z-index: 2;
      align-items: stretch;
    }

    .app-coming-copy {
      border: 1px solid rgba(255, 168, 116, 0.24);
      border-radius: 18px;
      padding: 20px;
      background: linear-gradient(180deg, rgba(16, 16, 16, 0.76), rgba(10, 10, 10, 0.64));
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
    }

    .app-coming-copy .section-kicker {
      margin-bottom: 8px;
      color: #ffc189;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.7rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .app-coming-copy .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.55rem, 2.6vw, 2.15rem);
      letter-spacing: 0.01em;
      margin-bottom: 10px;
      color: #ffe6cc;
    }

    .app-coming-copy p {
      color: #e8d6c9;
      font-family: 'Inter', sans-serif;
      margin-bottom: 10px;
      line-height: 1.58;
    }

    .app-bullet-row {
      display: flex;
      flex-wrap: wrap;
      gap: 9px;
      margin-top: 12px;
    }

    .app-bullet {
      border: 1px solid rgba(255, 177, 128, 0.36);
      background: rgba(72, 22, 12, 0.46);
      border-radius: 999px;
      color: #ffd9b7;
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.67rem;
      letter-spacing: 0.07em;
      padding: 6px 11px;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .app-launch-timeline {
      margin-top: 16px;
      display: grid;
      gap: 9px;
    }

    .app-launch-step {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      color: #f5e4d4;
      font-size: 0.89rem;
      line-height: 1.44;
    }

    .app-launch-step span {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 136, 66, 0.25);
      border: 1px solid rgba(255, 179, 111, 0.38);
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.68rem;
      color: #ffd8b4;
      flex-shrink: 0;
      margin-top: 1px;
    }

    .app-device-frame {
      border: 1px solid rgba(255, 138, 81, 0.36);
      border-radius: 18px;
      background: rgba(12, 8, 8, 0.72);
      padding: 15px;
      backdrop-filter: blur(4px);
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .app-device-head {
      font-size: 0.72rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #ffc58c;
      font-family: 'JetBrains Mono', monospace;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .app-live-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ff6d32;
      box-shadow: 0 0 0 0 rgba(255, 109, 50, 0.7);
      animation: pulseLive 1.6s ease-in-out infinite;
    }

    .app-feature-columns {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .app-feature-col {
      border: 1px solid rgba(255, 177, 128, 0.2);
      border-radius: 12px;
      background: rgba(8, 8, 10, 0.66);
      padding: 10px;
    }

    .app-feature-col h3 {
      margin: 0 0 8px;
      color: #ffdebf;
      font-size: 0.74rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace;
    }

    .app-feature-list {
      display: grid;
      gap: 6px;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .app-feature-list li {
      color: #f4ede6;
      font-size: 0.87rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .app-feature-list i {
      color: #ff9640;
    }

    .store-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .store-pill {
      border: 1px solid rgba(255, 172, 116, 0.33);
      border-radius: 12px;
      padding: 10px 11px;
      background: rgba(25, 11, 9, 0.74);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .store-pill:hover,
    .store-pill:focus-within {
      transform: translateY(-1px);
      border-color: rgba(255, 197, 145, 0.56);
    }

    .store-pill strong {
      display: block;
      color: #fff;
      font-size: 0.88rem;
      line-height: 1.25;
    }

    .store-pill small {
      color: #d5b8a7;
      display: block;
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      font-family: 'JetBrains Mono', monospace;
      margin-bottom: 2px;
    }

    .store-pill i {
      color: #ffd19c;
      font-size: 1.08rem;
      width: 22px;
      text-align: center;
      flex-shrink: 0;
    }

    .store-pill span {
      border: 1px solid rgba(255, 176, 103, 0.5);
      color: #ffca8f;
      border-radius: 999px;
      padding: 4px 8px;
      font-size: 0.67rem;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      font-family: 'JetBrains Mono', monospace;
      white-space: nowrap;
      flex-shrink: 0;
    }

    @keyframes pulseLive {
      0% {
        box-shadow: 0 0 0 0 rgba(255, 109, 50, 0.7);
      }

      70% {
        box-shadow: 0 0 0 8px rgba(255, 109, 50, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(255, 109, 50, 0);
      }
    }

    @media (max-width: 991.98px) {
      .app-coming-grid {
        grid-template-columns: 1fr;
      }

      .store-row {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 575.98px) {
      .app-coming-home {
        padding: 18px;
      }

      .app-coming-copy {
        padding: 16px;
      }

      .app-device-frame {
        padding: 13px;
      }

      .app-feature-columns {
        grid-template-columns: 1fr;
      }
    }
  </style>
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [{
          "@type": "Organization",
          "@id": "https://commerza.ahmershah.dev/#organization",
          "name": "Commerza",
          "url": "https://commerza.ahmershah.dev/",
          "logo": "https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp",
          "description": "Premium automatic watches with elegant leather and gold dials.",
          "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "Customer Service",
            "email": "commerza.ahmer@gmail.com",
            "telephone": "+923148396293",
            "areaServed": "PK",
            "availableLanguage": ["en", "ur"]
          },
          "sameAs": [
            "https://www.facebook.com/commerza.ahmer",
            "https://x.com/commerza_ahmer",
            "https://www.instagram.com/commerza.ahmer"
          ]
        },
        {
          "@type": "WebSite",
          "@id": "https://commerza.ahmershah.dev/#website",
          "url": "https://commerza.ahmershah.dev/",
          "name": "Commerza",
          "publisher": {
            "@id": "https://commerza.ahmershah.dev/#organization"
          },
          "potentialAction": {
            "@type": "SearchAction",
            "target": "https://commerza.ahmershah.dev/products.php?name={search_term_string}",
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "Store",
          "@id": "https://commerza.ahmershah.dev/#store",
          "name": "Commerza",
          "url": "https://commerza.ahmershah.dev/",
          "image": "https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp",
          "priceRange": "$$",
          "currenciesAccepted": "PKR",
          "paymentAccepted": "Cash on Delivery, Stripe",
          "areaServed": {
            "@type": "Country",
            "name": "Pakistan"
          },
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "Barrage Colony",
            "addressLocality": "Hyderabad",
            "addressCountry": "PK"
          },
          "parentOrganization": {
            "@id": "https://commerza.ahmershah.dev/#organization"
          }
        }
      ]
    }
  </script>
</head>

<body class="dark-theme home-premium premium-cards">

  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="navbar-logo me-2">
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
              <a class="nav-link" aria-current="page" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="about.php">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="contact.php">Contact</a>
            </li>
            <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
            <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-uppercase" href="#" id="shopDropdown" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                Series
              </a>
              <ul class="dropdown-menu border-2 border-dark" aria-labelledby="shopDropdown">
                <li><a class="dropdown-item" href="shop-category-a.php">The Automatic Vault</a></li>
                <li><a class="dropdown-item" href="shop-category-a.php#smart">Smart Evolution
                    Series</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="shop-category-b.php">The
                    Signature Collection</a>
                </li>
                <li><a class="dropdown-item" href="shop-category-b.php#tactical">The Sports & Sales
                    Division</a></li>
              </ul>
            </li>
          </ul>
          <form class="d-flex search-form d-none d-lg-flex" name="product_query" action="/search" method="GET">
            <input class="form-control search-input" type="search" placeholder="Search products..."
              aria-label="Search" />
            <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
          </form>
        </div>
      </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="offcanvas-logo me-2">
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
        <form class="d-flex search-form mb-4" name="product_query_mobile" action="/search" method="GET">
          <input class="form-control search-input" type="search" placeholder="Search products..." aria-label="Search" />
          <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" aria-current="page" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.php">Contact</a>
          </li>
          <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
          <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
          <li class="nav-item">
            <a class="nav-link text-uppercase fw-bold mt-3 mb-2"
              style="color: #ffcc00; font-size: 0.85rem; letter-spacing: 2px;">Series</a>
          </li>
          <li class="nav-item">
            <a class="nav-link ps-4" href="shop-category-a.php">The Automatic Vault</a>
          </li>
          <li class="nav-item">
            <a class="nav-link ps-4" href="shop-category-a.php#smart">Smart Evolution Series</a>
          </li>
          <li class="nav-item">
            <a class="nav-link ps-4" href="shop-category-b.php">The Signature Collection</a>
          </li>
          <li class="nav-item">
            <a class="nav-link ps-4" href="shop-category-b.php#tactical">The Sports & Sales Division</a>
          </li>
        </ul>
        <div class="offcanvas-footer mt-5">
          <div class="social-links">
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="Commerza on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="Commerza on Instagram"><i
                class="bi bi-instagram"></i></a>
          </div>
          <p class="footer-text mt-3">commerza.ahmer@gmail.com</p>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="container-fluid">
      <div class="row">
        <div class="col">
          <div id="customAlert" class="alert alert-danger text-center"
            style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
            You cannot add more than 10 products!
          </div>
          <div class="ticker-container bg-dark text-white">
            <div class="ticker-scroll">
              <span>SALE IS LIVE: PREMIUM AUTOMATIC WATCHES UP TO 20% OFF </span>
              <span>COLLECTION UPDATE: NEW SKELETON SERIES NOW AVAILABLE </span>
              <span>FREE SHIPPING: NATIONWIDE DELIVERY ON ALL PREMIUM ORDERS </span>
              <span>SALE IS LIVE: PREMIUM AUTOMATIC WATCHES UP TO 20% OFF </span>
              <span>COLLECTION UPDATE: NEW SKELETON SERIES NOW AVAILABLE </span>
              <span>FREE SHIPPING: NATIONWIDE DELIVERY ON ALL PREMIUM ORDERS </span>
            </div>
          </div>
          <div id="carouselExampleIndicators" class="carousel slide carousel-fade hero-carousel" data-bs-ride="carousel"
            data-bs-interval="3500" data-bs-pause="false">
            <div class="carousel-controls-top">
              <button class="carousel-control-btn carousel-play-pause" id="carouselPlayPause" type="button"
                aria-label="Play/Pause carousel">
                <i class="bi bi-pause-fill"></i>
              </button>
              <div class="carousel-nav-arrows">
                <button class="carousel-control-btn carousel-prev-btn" type="button"
                  data-bs-target="#carouselExampleIndicators" data-bs-slide="prev" aria-label="Previous slide">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="carousel-control-btn carousel-next-btn" type="button"
                  data-bs-target="#carouselExampleIndicators" data-bs-slide="next" aria-label="Next slide">
                  <i class="bi bi-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="carousel-indicators gap-2">
              <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active"
                aria-current="true" aria-label="Slide 1"></button>
              <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1"
                aria-label="Slide 2"></button>
              <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2"
                aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner">
              <div class="carousel-item active">
                <img src="frontend/assets/images/slider/watch-banner-chronograph.webp" class="d-block w-100 c-img"
                  loading="lazy" alt="luxury chronograph watch banner premium collection">
                <div class="carousel-overlay">
                  <div class="carousel-content">
                    <span class="carousel-label">Premium Collection</span>
                    <h2 class="carousel-heading">Chronograph Precision</h2>
                    <p class="carousel-text">Engineered movements with dual finish cases</p>
                    <a href="shop-category-a.php" class="btn carousel-btn">Explore Now</a>
                  </div>
                </div>
              </div>
              <div class="carousel-item">
                <img src="frontend/assets/images/slider/watch-banner-collection.webp" class="d-block w-100 c-img"
                  loading="lazy" alt="complete watch collection showcase all styles">
                <div class="carousel-overlay">
                  <div class="carousel-content">
                    <span class="carousel-label">Complete Series</span>
                    <h2 class="carousel-heading">Every Style, One Place</h2>
                    <p class="carousel-text">From minimalist to bold statement pieces</p>
                    <a href="shop-category-b.php" class="btn carousel-btn">View Collection</a>
                  </div>
                </div>
              </div>
              <div class="carousel-item">
                <img src="frontend/assets/images/slider/watch-banner-premium.webp" class="d-block w-100 c-img"
                  loading="lazy" alt="premium watches exclusive luxury timepieces">
                <div class="carousel-overlay">
                  <div class="carousel-content">
                    <span class="carousel-label">Exclusive Launch</span>
                    <h2 class="carousel-heading">Limited Editions</h2>
                    <p class="carousel-text">Hand assembled luxury with skeleton dials</p>
                    <a href="shop-category-b.php" class="btn carousel-btn">Shop Limited</a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="hero-block mt-4">
        <div class="row align-items-center g-4">
          <div class="col-12 col-lg-6">
            <p class="hero-kicker">Precision. Heritage. Innovation.</p>
            <h1 class="hero-title">Premium Automatic Watches by Commerza</h1>
            <p class="hero-subtitle">Discover hand finished timepieces crafted for modern life. Bold
              silhouettes, luminous dials, and movements engineered to last.</p>
            <div class="hero-actions">
              <a href="shop-category-b.php" class="btn hero-btn-outline text-white">Explore Signature Series</a>
            </div>
            <div class="hero-stats">
              <div class="stat-item">
                <span class="stat-value">24K</span>
                <span class="stat-label">Polished Accents</span>
              </div>
              <div class="stat-item">
                <span class="stat-value">48H</span>
                <span class="stat-label">Power Reserve</span>
              </div>
              <div class="stat-item">
                <span class="stat-value">5 ATM</span>
                <span class="stat-label">Water Resistance</span>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="hero-grid">
              <div class="hero-tile tile-large">
                <img src="frontend/assets/images/products/featured/premium-black-gold.webp"
                  alt="premium black gold automatic watch" loading="lazy">
              </div>
              <div class="hero-tile">
                <img src="frontend/assets/images/products/featured/white-gold-steel.webp"
                  alt="white gold steel automatic watch" loading="lazy">
              </div>
              <div class="hero-tile">
                <img src="frontend/assets/images/products/featured/skeleton-gold-steel.webp"
                  alt="skeleton gold steel automatic watch" loading="lazy">
              </div>
              <div class="hero-tile">
                <img src="frontend/assets/images/products/featured/black-gold-dial.webp"
                  alt="black gold dial automatic watch" loading="lazy">
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="highlight-strip mt-5">
        <h2 class="visually-hidden">Commerza highlights</h2>
        <div class="row g-4">
          <div class="col-12 col-md-4">
            <div class="highlight-card">
              <div class="highlight-icon">01</div>
              <h3 class="highlight-title">Sapphire Clarity</h3>
              <p class="highlight-text">Scratch‘resistant crystal with anti‘glare coating for unmatched
                dial visibility.</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="highlight-card">
              <div class="highlight-icon">02</div>
              <h3 class="highlight-title">Precision Movement</h3>
              <p class="highlight-text">Automatic calibers tuned for accuracy, tested across temperature
                ranges.</p>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="highlight-card">
              <div class="highlight-icon">03</div>
              <h3 class="highlight-title">Heritage Craft</h3>
              <p class="highlight-text">Premium leather, brushed steel, and gold accents for timeless
                elegance.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="category-grid mt-5">
        <div class="row g-4">
          <div class="col-12 col-md-6 col-lg-4">
            <a class="category-card" href="shop-category-a.php">
              <img src="frontend/assets/images/products/featured/black-white-gold.webp" alt="automatic vault collection"
                loading="lazy">
              <div class="category-overlay">
                <h3>Automatic Vault</h3>
                <span>Explore Collection</span>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a class="category-card" href="shop-category-a.php#smart">
              <img src="frontend/assets/images/products/featured/black-minimalist.webp" alt="smart evolution series"
                loading="lazy">
              <div class="category-overlay">
                <h3>Smart Evolution</h3>
                <span>Modern Essentials</span>
              </div>
            </a>
          </div>
          <div class="col-12 col-md-6 col-lg-4">
            <a class="category-card" href="shop-category-b.php">
              <img src="frontend/assets/images/products/featured/brown-premium-watch.webp" alt="signature collection"
                loading="lazy">
              <div class="category-overlay">
                <h3>Signature Collection</h3>
                <span>Limited Craft</span>
              </div>
            </a>
          </div>
        </div>
      </section>


      <section class="container-fluid px-0 mb-5">
        <div class="ratio ratio-16x9">
          <video autoplay muted loop playsinline preload="metadata" loading="lazy" class="w-100 h-100"
            style="object-fit: cover;" aria-label="Steel watch 3D animation">
            <source src="<?= htmlspecialchars($home_feature_video) ?>" type="video/mp4">
            Your browser does not support the video tag.
          </video>
        </div>
      </section>
      <section class="section-heading mt-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <p class="section-kicker">Featured Drops</p>
            <h2 class="section-title">Best‘Selling Timepieces</h2>
          </div>
          <a class="btn hero-btn-outline text-white" href="shop-category-a.php">View All</a>
        </div>
      </section>

      <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "ItemList",
          "category": "Premium Watches & Accessories",
          "subcategory": "Luxury Timepieces",
          "section": "Featured Collection",
          "itemListElement": [{
              "@type": "Product",
              "position": 1,
              "name": "White Gold Steel Watch",
              "description": "Premium white dial with golden accents, stainless steel case with automatic movement mechanism.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/white-gold-steel.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "6200",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/white-gold-steel-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 2,
              "name": "Black White Gold Watch",
              "description": "Premium black leather strap with white and golden dial, smooth automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-white-gold.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "7100",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/black-white-gold-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 3,
              "name": "Skeleton Gold Steel Watch",
              "description": "White and golden skeleton display with premium steel case, clear and clean design with automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/skeleton-gold-steel.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "8900",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/skeleton-gold-steel-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 4,
              "name": "Brown White Gold Watch",
              "description": "Rich brown leather strap with white and golden dial, premium automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/brown-gold-dial.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "9600",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/brown-white-gold-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 5,
              "name": "Black Gold Dial Watch",
              "description": "Elegant black leather strap with stunning golden dial, precision automatic movement mechanism.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-gold-dial.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "7800",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/black-gold-dial-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 6,
              "name": "Brown Gold Premium Watch",
              "description": "Rich brown leather with premium gold accents and sophisticated dial design.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/brown-premium-watch.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "10200",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/brown-gold-premium-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 7,
              "name": "Premium Black Gold Watch",
              "description": "Luxurious premium black watch with bold gold elements and superior craftsmanship.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/premium-black-gold.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "12400",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/premium-black-gold-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 8,
              "name": "Black Minimalist Watch",
              "description": "Clean minimalist design with sleek black finish and timeless appeal.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-minimalist.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "8300",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/black-minimalist-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 9,
              "name": "Black Feather Watch",
              "description": "Elegant black watch with subtle design details and lightweight comfort.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-feather.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "14200",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/black-feather-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            },
            {
              "@type": "Product",
              "position": 10,
              "name": "Luxury White Gold Watch",
              "description": "Ultimate luxury timepiece with white gold accents and premium dial craftsmanship.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/luxury-white-gold.webp",
              "brand": {
                "@type": "Brand",
                "name": "Commerza"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "PKR",
                "price": "16800",
                "priceValidUntil": "2026-12-31",
                "availability": "https://schema.org/InStock",
                "url": "https://commerza.ahmershah.dev/product/luxury-white-gold-watch",
                "itemCondition": "https://schema.org/NewCondition"
              }
            }
          ]
        }
      </script>

      <div id="featured-products-container" class="row mt-4">
      </div>

      <section class="experience-strip mt-5">
        <div class="row g-4 align-items-center">
          <div class="col-12 col-lg-6">
            <img src="frontend/assets/images/slider/watch-banner-premium.webp" alt="luxury watch closeup" loading="lazy"
              class="experience-image">
          </div>
          <div class="col-12 col-lg-6">
            <p class="section-kicker">The Commerza Standard</p>
            <h2 class="section-title">Engineered For Every Moment</h2>
            <p class="experience-text">Our automatic movements are calibrated in multiple positions for
              consistent timekeeping. Pair them with hand‘stitched straps, luminous indexes, and bold
              cases that command attention.</p>
            <div class="experience-points">
              <div class="point-card">
                <span class="point-title">Dual Finish Case</span>
                <span class="point-sub">Brushed steel with polished gold bezel</span>
              </div>
              <div class="point-card">
                <span class="point-title">Lume Night Dial</span>
                <span class="point-sub">High‘visibility markers for after‘hours wear</span>
              </div>
              <div class="point-card">
                <span class="point-title">Balanced Weight</span>
                <span class="point-sub">Comfort‘first fit for long days</span>
              </div>
            </div>
            <a href="about.php" class="btn hero-btn-primary mt-3">Our Craftsmanship</a>
          </div>
        </div>
      </section>

      <section class="testimonial-grid mt-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <p class="section-kicker">Collectors Speak</p>
            <h2 class="section-title">Trusted by Watch Enthusiasts</h2>
          </div>
        </div>
        <div class="testimonial-marquee">
          <div class="testimonial-track">
            <div class="testimonial-card">
              <p class="testimonial-text">"The Skeleton Gold Steel feels premium in every detail. The
                movement is smooth and the dial steals attention."</p>
              <div class="testimonial-meta">
                <span class="meta-name">A. Khan</span>
                <span class="meta-role">Lahore</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"I've worn the Black Gold Dial daily. It keeps time accurately
                and looks incredible under low light."</p>
              <div class="testimonial-meta">
                <span class="meta-name">S. Malik</span>
                <span class="meta-role">Karachi</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"Fast shipping and stellar packaging. The leather strap quality
                is beyond what I expected."</p>
              <div class="testimonial-meta">
                <span class="meta-name">R. Ahmed</span>
                <span class="meta-role">Islamabad</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"The automatic movement is mesmerizing. I can watch it for hours
                through the exhibition case back."</p>
              <div class="testimonial-meta">
                <span class="meta-name">M. Hassan</span>
                <span class="meta-role">Rawalpindi</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"Excellent build quality and attention to detail. The weight feels
                perfect on the wrist."</p>
              <div class="testimonial-meta">
                <span class="meta-name">F. Ali</span>
                <span class="meta-role">Multan</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"Customer service is outstanding. They helped me choose the perfect
                watch for my collection."</p>
              <div class="testimonial-meta">
                <span class="meta-name">Z. Iqbal</span>
                <span class="meta-role">Faisalabad</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"The luminous hands are perfect for night visibility. Absolutely love
                the craftsmanship."</p>
              <div class="testimonial-meta">
                <span class="meta-name">N. Raza</span>
                <span class="meta-role">Peshawar</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"Premium materials and flawless finishing. This watch rivals luxury
                brands at triple the price."</p>
              <div class="testimonial-meta">
                <span class="meta-name">H. Shah</span>
                <span class="meta-role">Quetta</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"The Skeleton Gold Steel feels premium in every detail. The
                movement is smooth and the dial steals attention."</p>
              <div class="testimonial-meta">
                <span class="meta-name">A. Khan</span>
                <span class="meta-role">Lahore</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"I've worn the Black Gold Dial daily. It keeps time accurately
                and looks incredible under low light."</p>
              <div class="testimonial-meta">
                <span class="meta-name">S. Malik</span>
                <span class="meta-role">Karachi</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"Fast shipping and stellar packaging. The leather strap quality
                is beyond what I expected."</p>
              <div class="testimonial-meta">
                <span class="meta-name">R. Ahmed</span>
                <span class="meta-role">Islamabad</span>
              </div>
            </div>
            <div class="testimonial-card">
              <p class="testimonial-text">"The automatic movement is mesmerizing. I can watch it for hours
                through the exhibition case back."</p>
              <div class="testimonial-meta">
                <span class="meta-name">M. Hassan</span>
                <span class="meta-role">Rawalpindi</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="app-coming-home mt-5" aria-labelledby="homeAppSoonTitle">
        <div class="app-coming-stage-glow" aria-hidden="true"></div>
        <div class="app-coming-grid">
          <div class="app-coming-copy">
            <p class="section-kicker">Mobile App Update</p>
            <h2 id="homeAppSoonTitle" class="section-title">Commerza Mobile App Is Coming Soon</h2>
            <p>We are building the Commerza mobile app to make watch discovery, wishlist sync, and checkout much faster on mobile.</p>
            <p>Launch announcements will be shared with our community first, followed by full availability on both major app stores.</p>
            <div class="app-bullet-row" aria-hidden="true">
              <span class="app-bullet">Wishlist Sync</span>
              <span class="app-bullet">Order Tracking</span>
              <span class="app-bullet">Smart Checkout</span>
              <span class="app-bullet">Drop Alerts</span>
            </div>
            <div class="app-launch-timeline" aria-label="Mobile app rollout timeline">
              <div class="app-launch-step"><span>1</span>Closed beta testing and stability tuning</div>
              <div class="app-launch-step"><span>2</span>Final polish for checkout, notifications, and support</div>
              <div class="app-launch-step"><span>3</span>Public release on Google Play Store and Apple App Store</div>
            </div>
          </div>
          <div class="app-device-frame">
            <div class="app-device-head"><span class="app-live-dot"></span>Store launch status</div>
            <div class="app-feature-columns" aria-label="Commerza app feature roadmap">
              <div class="app-feature-col">
                <h3>Shopping Features</h3>
                <ul class="app-feature-list">
                  <li><i class="bi bi-stars"></i><span>Personalized watch recommendations</span></li>
                  <li><i class="bi bi-search"></i><span>Lightning filters for movement, dial, and finish</span></li>
                  <li><i class="bi bi-heart"></i><span>One account, synced wishlist everywhere</span></li>
                  <li><i class="bi bi-bell"></i><span>Back-in-stock and limited-drop push alerts</span></li>
                </ul>
              </div>
              <div class="app-feature-col">
                <h3>Checkout And Support</h3>
                <ul class="app-feature-list">
                  <li><i class="bi bi-shield-lock"></i><span>Secure one-tap checkout with saved profiles</span></li>
                  <li><i class="bi bi-truck"></i><span>Live order timeline with courier checkpoints</span></li>
                  <li><i class="bi bi-chat-square-dots"></i><span>Direct support chat from inside order cards</span></li>
                  <li><i class="bi bi-clock-history"></i><span>Quick reorder from purchase history</span></li>
                </ul>
              </div>
            </div>
            <div class="store-row">
              <div class="store-pill" aria-label="Coming soon on Google Play Store">
                <i class="bi bi-google-play"></i>
                <div>
                  <small>Coming Soon On</small>
                  <strong>Google Play Store</strong>
                </div>
                <span>Android</span>
              </div>
              <div class="store-pill" aria-label="Coming soon on Apple App Store">
                <i class="bi bi-apple"></i>
                <div>
                  <small>Coming Soon On</small>
                  <strong>Apple App Store</strong>
                </div>
                <span>iOS</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div class="modal fade" id="newsletterModal" tabindex="-1" aria-labelledby="newsletterModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered newsletter-modal-dialog">
          <div class="modal-content newsletter-modal-content">
            <div class="modal-header newsletter-modal-header">
              <div>
                <p class="newsletter-modal-kicker mb-1">Commerza Insider</p>
                <h5 class="modal-title newsletter-modal-title" id="newsletterModalLabel">Join the Commerza Circle</h5>
              </div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                aria-label="Close"></button>
            </div>
            <div class="modal-body newsletter-modal-body">
              <p class="newsletter-modal-text">Get early access to limited drops, launch offers, and collector updates before public release.</p>
              <form id="newsletterForm" class="newsletter-modal-form mt-3">
                <label for="newsletterEmail" class="form-label newsletter-modal-label">Email Address</label>
                <div class="newsletter-modal-input-wrap">
                  <i class="bi bi-envelope"></i>
                  <input type="email" class="form-control search-input newsletter-modal-input" id="newsletterEmail" placeholder="you@example.com"
                    required maxlength="150">
                </div>
                <div class="newsletter-modal-points" aria-hidden="true">
                  <span><i class="bi bi-bell"></i> Drop Alerts</span>
                  <span><i class="bi bi-stars"></i> Insider Offers</span>
                  <span><i class="bi bi-clock-history"></i> Early Access</span>
                </div>
                <button type="submit" class="btn product-btn-buy w-100 mt-3">Subscribe Now</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-4">
        <div class="col d-flex justify-content-end">
          <nav aria-label="Product pagination">
            <ul class="pagination mb-0">
              <li class="page-item disabled">
                <a class="page-link" href="#">Prev</a>
              </li>
              <li class="page-item active">
                <a class="page-link" href="index.php">1</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="shop-category-a.php">2</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="shop-category-b.php">3</a>
              </li>
              <li class="page-item">
                <a class="page-link" href="shop-category-a.php">Next</a>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      <section class="newsletter-cta mt-5">
        <div class="row align-items-center g-4">
          <div class="col-12 col-lg-7">
            <p class="section-kicker">Stay In Sync</p>
            <h2 class="section-title">Get first access to limited drops</h2>
            <p class="newsletter-text">Join the Commerza list for early launches, exclusive offers, and collector stories.</p>
            <div class="newsletter-points" aria-hidden="true">
              <span class="newsletter-point"><i class="bi bi-lightning-charge"></i>Priority launch alerts</span>
              <span class="newsletter-point"><i class="bi bi-gift"></i>Member-only offers</span>
              <span class="newsletter-point"><i class="bi bi-shield-check"></i>No spam, easy unsubscribe</span>
            </div>
          </div>
          <div class="col-12 col-lg-5">
            <form class="newsletter-form newsletter-form-upgraded">
              <div class="newsletter-input-wrap">
                <i class="bi bi-envelope"></i>
                <input type="email" class="newsletter-input" placeholder="Enter your email" aria-label="Email"
                  required maxlength="150">
              </div>
              <button type="submit" class="btn hero-btn-primary">Notify Me</button>
            </form>
            <p class="newsletter-footnote">Weekly highlights only. Unsubscribe anytime.</p>
          </div>
        </div>
      </section>
      <a href="#" id="backToTop" class="rounded-circle" title="Go to top">↑</a>
  </main>

  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Commerza</h3>
          <p class="footer-text">
            Commerza Premium watches and accessories designed for the modern lifestyle. Exceptional
            craftsmanship, timeless design, and uncompromising quality.
          </p>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Quick Links</h3>
          <ul class="footer-links">
            <li><a aria-current="page" href="index.php">Home</a></li>
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js" defer></script>
  <script src="frontend/assets/js/auth.js" defer></script>
  <script src="frontend/assets/js/script.js" defer></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    window.CommerzaCsrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>";

    document.addEventListener("DOMContentLoaded", function() {
      if (typeof window.commerzaOnReady !== "function") {
        return;
      }

      window.commerzaOnReady(function() {
        loadProductsBySection("featured-collection", "featured-products-container");
      });
    });
  </script>
</body>

</html>