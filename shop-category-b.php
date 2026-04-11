<?php
include "backend/core/data.php";
include_once "backend/helpers/nav_state.php";
$nav_counts = commerza_get_nav_counts($con);

$category_b_feature_video = 'frontend/assets/videos/products/sports/sports_watches_carousel.mp4';
$categoryBVideoStmt = $con->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ? LIMIT 1');
if ($categoryBVideoStmt) {
  $settingKey = 'category_b_feature_video';
  $categoryBVideoStmt->bind_param('s', $settingKey);
  $categoryBVideoStmt->execute();
  $categoryBVideoResult = $categoryBVideoStmt->get_result();
  $categoryBVideoRow = $categoryBVideoResult ? $categoryBVideoResult->fetch_assoc() : null;
  $categoryBVideoStmt->close();

  $savedVideo = trim((string)($categoryBVideoRow['setting_val'] ?? ''));
  if (
    $savedVideo !== '' &&
    strpos($savedVideo, '..') === false &&
    strpos($savedVideo, '\\') === false &&
    preg_match('#^[a-zA-Z0-9/_\-.]+$#', $savedVideo) === 1
  ) {
    $category_b_feature_video = $savedVideo;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description"
    content="Commerza - Premium automatic watches with elegant leather and gold dials. Explore our collection of luxury timepieces crafted for modern lifestyle.">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta property="og:title" content="Commerza | Lifestyle & Performance Series">
  <meta property="og:description"
    content="Elevate your daily look with the Signature Collection or gear up with the Tactical Division.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/shop-category-b.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Commerza | Modern Style & Performance – The Lifestyle Series">
  <meta name="twitter:description" content="Explore minimalist and sports timepieces in Commerza's lifestyle and utility series.">
  <meta name="twitter:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>Commerza | Modern Style & Performance – The Lifestyle Series</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/shop-category-b.php" />
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
  <link rel="stylesheet" href="frontend/assets/css/pages/shop-category-b-inline.css">
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "The Lifestyle & Utility Series - Commerza",
      "description": "Explore Commerza's lifestyle collection: The Signature Collection with minimalist watches and The Sports & Sales Division with performance timepieces.",
      "url": "https://commerza.ahmershah.dev/shop-category-b.php",
      "about": ["Lifestyle Watches", "Minimalist Watches", "Sports Watches"],
      "keywords": "minimalist watches, sports watches, lifestyle timepieces",
      "isPartOf": {
        "@type": "WebSite",
        "name": "Commerza",
        "url": "https://commerza.ahmershah.dev/"
      },
      "publisher": {
        "@type": "Organization",
        "name": "Commerza",
        "logo": "https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp"
      }
    }
  </script>
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": "The Signature Collection & Sports & Sales Division",
      "description": "Minimalist and sports timepieces from Commerza's lifestyle and utility series.",
      "url": "https://commerza.ahmershah.dev/shop-category-b.php",
      "about": ["Lifestyle Watches", "Minimalist Watches", "Sports Watches"],
      "keywords": "minimalist watches, sports watches, lifestyle timepieces",
      "isPartOf": {
        "@type": "WebSite",
        "name": "Commerza",
        "url": "https://commerza.ahmershah.dev/"
      },
      "mainEntity": {
        "@type": "ItemList",
        "itemListElement": [{
            "@type": "Product",
            "position": 1,
            "name": "DENIM 3 - The Minimalist Watch",
            "description": "Understated elegance in denim tones, featuring clean design and timeless appeal for everyday sophistication.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/DENIM 3 - The Minimalist Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "5500",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 2,
            "name": "DI-STAR - CHAIN WATCH WITH DATE TWO TONE",
            "description": "Sophisticated two-tone design with chain link style, date display, and refined aesthetic perfect for any occasion.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/DI-STAR - CHAIN WATCH WITH DATE TWO TONE.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "6500",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 3,
            "name": "Fued - Tomi Face Gear Dual Leather Straps Watch",
            "description": "Versatile watch with dual interchangeable leather straps for flexible styling, combining practicality with refined minimalist design.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Fued - Tomi Face Gear Dual Leather Straps Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8000",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 4,
            "name": "Galcia - Round Minimalist Watch WITH DATE",
            "description": "Classic round dial with date window, embodying minimalist philosophy with excellent legibility and timeless appeal.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Galcia - Round Minimalist Watch WITH DATE.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "9000",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 5,
            "name": "Square Tom - Minimalist Watch",
            "description": "Bold square dial minimalist timepiece featuring clean edges and refined minimalism for contemporary style.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Square Tom - Minimalist Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8500",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 6,
            "name": "TOMI T 105 - Tomi Face Gear Black Dial",
            "description": "Professional timepiece with black dial and advanced gear mechanism, balancing sophistication with functional performance.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/TOMI T 105 - Tomi Face Gear Black Dial.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "9900",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 7,
            "name": "TOMI- Round Minimalist Watch WITH DATE",
            "description": "Elegant round minimalist watch with date function, perfect companion for both casual and formal occasions.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/TOMI- Round Minimalist Watch WITH DATE.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "11800",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 8,
            "name": "X - Round Minimalist Watch (Half Cut)",
            "description": "Unique minimalist watch with creative half-cut design, showcasing modern artistry and elegant simplicity.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/X - Round Minimalist Watch (Half Cut).webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8200",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 9,
            "name": "Aura - Never Stop Minimal Watch with Date -N905",
            "description": "Sleek sports watch with minimalist design and date function, engineered for active lifestyle and daily durability.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Aura - Never Stop Minimal Watch with Date -N905.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "5500",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 10,
            "name": "Chrona - Never Stop Minimal Watch - N928",
            "description": "Performance-focused sports watch with chronograph functionality, perfect for timing and precision athletic activities.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Chrona - Never Stop Minimal Watch - N928.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "6500",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 11,
            "name": "Dagahra- Never Stop Casual sports Watch with date - N911",
            "description": "Casual sports watch with date display, combining comfort with functionality for everyday athletic pursuits.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Dagahra- Never Stop Casual sports Watch with date - N911.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8000",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 12,
            "name": "Newmoon - Never Stop Chronograph sports Watch with date - N902",
            "description": "Advanced chronograph sports watch with date, featuring multiple timing functions and rugged design for serious athletes.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Newmoon - Never Stop Chronograph sports Watch with date - N902.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "9000",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 13,
            "name": "RECDIS - Skmei 3 Time Sports Watch With Stainless Steel",
            "description": "Professional sports watch with triple time zones, stainless steel construction, ideal for international travelers and athletes.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/RECDIS - Skmei 3 Time Sports Watch With Stainless Steel.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "13400",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 14,
            "name": "TOKDIS - Dual Time Sports Watch With Stainless Steel",
            "description": "Robust dual-time sports watch with premium stainless steel build, featuring reliable timekeeping for active pursuits across time zones.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/TOKDIS - Dual Time Sports Watch With Stainless Steel.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "12200",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 15,
            "name": "Yraz - Never Stop Casual sports Watch with date",
            "description": "Casual sports watch with date window, versatile design perfect for everyday activities and weekend adventures with reliable performance.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Yraz - Never Stop Casual sports Watch with date.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "15100",
              "availability": "https://schema.org/InStock"
            }
          }
        ]
      }
    }
  </script>
</head>

<body class="dark-theme premium-cards">

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
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-uppercase" aria-current="page" href="#" id="shopDropdown"
                role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Series
              </a>
              <ul class="dropdown-menu border-2 border-dark" aria-labelledby="shopDropdown">
                <li><a class="dropdown-item" href="shop-category-a.php">The
                    Automatic Vault</a></li>
                <li><a class="dropdown-item" href="shop-category-a.php#smart">Smart Evolution
                    Series</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" aria-current="page" href="shop-category-b.php">The
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
            <a class="nav-link ps-4" aria-current="page" href="shop-category-b.php">The Signature Collection</a>
          </li>
          <li class="nav-item">
            <a class="nav-link ps-4" href="shop-category-b.php#tactical">The Sports & Sales Division</a>
          </li>
        </ul>
      </div>
    </div>
  </header>

  <main class="shop-category-main">
    <section class="container-fluid page-breadcrumb-shell">
      <nav aria-label="Breadcrumb">
        <ol class="breadcrumb page-breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Shop Category B</li>
        </ol>
      </nav>
    </section>
    <div class="container-fluid">
      <div class="row mt-2">
        <div class="col">
          <div id="customAlert" class="alert alert-danger text-center"
            style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
            You cannot add more than 10 products!
          </div>

          <section class="hero-block mt-4">
            <div class="row align-items-center g-4">
              <div class="col-12 col-lg-7">
                <p class="hero-kicker">Lifestyle & Utility Series</p>
                <h1 class="hero-title">Everyday Style, Built to Perform</h1>
                <p class="hero-subtitle">Minimalist silhouettes for refined looks and sports-ready builds for
                  high-impact days.</p>
                <div class="hero-actions">
                  <a href="#signature" class="btn hero-btn-primary">Shop Signature</a>
                  <a href="#tactical" class="btn hero-btn-outline text-white">Shop Sports Division</a>
                </div>
                <div class="hero-stats">
                  <div class="stat-item">
                    <span class="stat-value">2x</span>
                    <span class="stat-label">Finishing Layers</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-value">24H</span>
                    <span class="stat-label">Everyday Wear</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-value">Steel</span>
                    <span class="stat-label">Durability</span>
                  </div>
                </div>
              </div>
              <div class="col-12 col-lg-5">
                <div class="hero-grid">
                  <div class="hero-tile tile-large">
                    <img src="frontend/assets/images/products/minimal/TOMI- Round Minimalist Watch WITH DATE.webp"
                      alt="minimalist watch" loading="lazy">
                  </div>
                  <div class="hero-tile">
                    <img src="frontend/assets/images/products/minimal/Galcia - Round Minimalist Watch WITH DATE.webp"
                      alt="round minimalist watch" loading="lazy">
                  </div>
                  <div class="hero-tile">
                    <img
                      src="frontend/assets/images/products/sports/Newmoon - Never Stop Chronograph sports Watch with date - N902.webp"
                      alt="chronograph sports watch" loading="lazy">
                  </div>
                  <div class="hero-tile">
                    <img
                      src="frontend/assets/images/products/sports/TOKDIS - Dual Time Sports Watch With Stainless Steel.webp"
                      alt="dual time sports watch" loading="lazy">
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="highlight-strip mt-5">
            <h2 class="visually-hidden">Series highlights</h2>
            <div class="row g-4">
              <div class="col-12 col-md-4">
                <div class="highlight-card">
                  <div class="highlight-icon">01</div>
                  <h3 class="highlight-title">Minimalist Edge</h3>
                  <p class="highlight-text">Clean lines, balanced proportions, and effortless daily style.</p>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="highlight-card">
                  <div class="highlight-icon">02</div>
                  <h3 class="highlight-title">Performance Ready</h3>
                  <p class="highlight-text">Chronographs and dual-time models built for active lifestyles.</p>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="highlight-card">
                  <div class="highlight-icon">03</div>
                  <h3 class="highlight-title">Durable Build</h3>
                  <p class="highlight-text">Stainless steel cases and reliable movements for long wear.</p>
                </div>
              </div>
            </div>
          </section>

          <section class="series-pulse-board mt-4" aria-label="Category B quick buying guide">
            <div class="series-pulse-grid">
              <article class="series-pulse-chip">
                <span class="chip-kicker">Style Cue</span>
                <strong>Signature Collection</strong>
                <p>Use this collection for cleaner silhouettes, formal fits, and understated daily confidence.</p>
              </article>
              <article class="series-pulse-chip">
                <span class="chip-kicker">Performance Cue</span>
                <strong>Sports Division</strong>
                <p>Switch here for chronograph utility, tougher cases, and movement-friendly strap builds.</p>
              </article>
              <article class="series-pulse-chip">
                <span class="chip-kicker">Selection Cue</span>
                <strong>Shortlist Fast</strong>
                <p>Start with section filter, then price sort, and finalize by comparing dial visibility.</p>
              </article>
            </div>
          </section>

          <section class="filter-bar mt-5">
            <div class="filter-header">
              <div>
                <span class="filter-kicker">Refine</span>
                <h3 class="filter-title">Filter Watches</h3>
              </div>
              <span class="filter-hint">Sort by section, movement, or price.</span>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-4">
                <label for="filter-section-btn" class="form-label text-white">Section</label>
                <div class="dropdown w-100">
                  <button id="filter-section-btn" class="btn filter-dropdown-btn dropdown-toggle w-100 text-start"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    All Sections
                  </button>
                  <ul class="dropdown-menu filter-dropdown-menu w-100" id="filter-section-menu"></ul>
                  <input type="hidden" id="filter-section" value="all">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label for="filter-movement-btn" class="form-label text-white">Movement</label>
                <div class="dropdown w-100">
                  <button id="filter-movement-btn" class="btn filter-dropdown-btn dropdown-toggle w-100 text-start"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    All Movements
                  </button>
                  <ul class="dropdown-menu filter-dropdown-menu w-100" id="filter-movement-menu"></ul>
                  <input type="hidden" id="filter-movement" value="all">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label for="filter-sort-btn" class="form-label text-white">Sort</label>
                <div class="dropdown w-100">
                  <button id="filter-sort-btn" class="btn filter-dropdown-btn dropdown-toggle w-100 text-start"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Featured
                  </button>
                  <ul class="dropdown-menu filter-dropdown-menu w-100" id="filter-sort-menu">
                    <li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-sort"
                        data-value="default" data-label="Featured">Featured</a></li>
                    <li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-sort"
                        data-value="price-asc" data-label="Price: Low to High">Price: Low to High</a></li>
                    <li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-sort"
                        data-value="price-desc" data-label="Price: High to Low">Price: High to Low</a></li>
                    <li><a class="dropdown-item filter-dropdown-item" href="#" data-target="filter-sort"
                        data-value="name-asc" data-label="Name: A to Z">Name: A to Z</a></li>
                  </ul>
                  <input type="hidden" id="filter-sort" value="default">
                </div>
              </div>
              <div class="col-12 col-md-6">
                <button id="apply-filter" class="btn product-btn-buy w-100">Apply Filter</button>
              </div>
              <div class="col-12 col-md-6">
                <button id="reset-filter" class="btn product-btn-cart w-100">Reset</button>
              </div>
            </div>
          </section>

          <section class="filter-results mt-5 d-none" id="filtered-products-wrapper">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <p class="section-kicker">Filtered</p>
                <h2 class="section-title">Filtered Results</h2>
              </div>
              <span class="text-secondary" id="filtered-count"></span>
            </div>
            <div id="filtered-products-container" class="row mt-4"></div>
          </section>

          <section class="product-section-block" data-section-id="signature-collection">
            <h1 class="text-center mt-5 mb-4" id="signature">COMMERZA | The
              Lifestyle & Utility Series
            </h1>

            <h2 class="text-center mt-5 mb-2">The Signature Collection
            </h2>
            <p class="text-center text-secondary mb-4">Refined silhouettes and minimalist dials for timeless style.</p>

            <!-- Dynamic Products Container for The Signature Collection -->
            <div id="signature-collection-products-container" class="row mt-4">
              <!-- Products will be loaded here by JavaScript -->
            </div>
          </section>

          <section class="product-section-block" data-section-id="sports-sales-division">
            <section class="container-fluid px-0 mb-5">
              <div class="ratio ratio-16x9">
                <video autoplay muted loop playsinline preload="metadata" loading="lazy" class="w-100 h-100"
                  style="object-fit: cover;" aria-label="Sports watches showcase">
                  <source src="<?= htmlspecialchars($category_b_feature_video) ?>" type="video/mp4">
                  Your browser does not support the video tag.
                </video>
              </div>
            </section>

            <h2 class="text-center mt-5 mb-2" id="tactical">The Sports & Sales Division
            </h2>
            <p class="text-center text-secondary mb-4">High-performance designs built for durability and daily movement.
            </p>

            <!-- Dynamic Products Container for The Sports & Sales Division -->
            <div id="sports-division-products-container" class="row mt-4">
              <!-- Products will be loaded here by JavaScript -->
            </div>
          </section>

          <div class="row mt-4">
            <div class="col d-flex justify-content-end">
              <nav aria-label="Product pagination">
                <ul class="pagination mb-0">
                  <li class="page-item">
                    <a class="page-link" href="shop-category-a.php">Prev</a>
                  </li>
                  <li class="page-item">
                    <a class="page-link" href="index.php">1</a>
                  </li>
                  <li class="page-item">
                    <a class="page-link" href="shop-category-a.php">2</a>
                  </li>
                  <li class="page-item active">
                    <a class="page-link" href="shop-category-b.php">3</a>
                  </li>
                  <li class="page-item disabled">
                    <a class="page-link" href="#">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
          <a href="#" id="backToTop" class="rounded-circle" title="Go to top">↑</a>
  </main>

  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Commerza</h3>
          <p class="footer-text">
            Commerza – Premium watches and accessories designed for the modern lifestyle. Exceptional
            craftsmanship, timeless design, and uncompromising quality.
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

  <script <?= commerza_csp_nonce_attr() ?> type="application/json">
    {
      "page": "shop-category-b",
      "name": "The Signature Collection & Sports & Sales Division",
      "category": "Lifestyle & Utility Watches",
      "subcategory": "Minimalist & Sports Timepieces",
      "section": "The Lifestyle & Utility Series",
      "collections": [{
          "collection": "The Signature Collection",
          "products": [{
              "id": 1,
              "name": "DENIM 3 - The Minimalist Watch",
              "description": "Understated elegance in denim tones, featuring clean design and timeless appeal for everyday sophistication.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/DENIM 3 - The Minimalist Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7000,
              "salePrice": 5500,
              "availability": "InStock"
            },
            {
              "id": 2,
              "name": "DI-STAR - CHAIN WATCH WITH DATE TWO TONE",
              "description": "Sophisticated two-tone design with chain link style, date display, and refined aesthetic perfect for any occasion.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/DI-STAR - CHAIN WATCH WITH DATE TWO TONE.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7500,
              "salePrice": 6500,
              "availability": "InStock"
            },
            {
              "id": 3,
              "name": "Fued - Tomi Face Gear Dual Leather Straps Watch",
              "description": "Versatile watch with dual interchangeable leather straps for flexible styling, combining practicality with refined minimalist design.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Fued - Tomi Face Gear Dual Leather Straps Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 9000,
              "salePrice": 8000,
              "availability": "InStock"
            },
            {
              "id": 4,
              "name": "Galcia - Round Minimalist Watch WITH DATE",
              "description": "Classic round dial with date window, embodying minimalist philosophy with excellent legibility and timeless appeal.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Galcia - Round Minimalist Watch WITH DATE.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 10000,
              "salePrice": 9000,
              "availability": "InStock"
            },
            {
              "id": 5,
              "name": "Square Tom - Minimalist Watch",
              "description": "Bold square dial minimalist timepiece featuring clean edges and refined minimalism for contemporary style.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/Square Tom - Minimalist Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 11200,
              "salePrice": 8500,
              "availability": "InStock"
            },
            {
              "id": 6,
              "name": "TOMI T 105 - Tomi Face Gear Black Dial",
              "description": "Professional timepiece with black dial and advanced gear mechanism, balancing sophistication with functional performance.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/TOMI T 105 - Tomi Face Gear Black Dial.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 13200,
              "salePrice": 9900,
              "availability": "InStock"
            },
            {
              "id": 7,
              "name": "TOMI- Round Minimalist Watch WITH DATE",
              "description": "Elegant round minimalist watch with date function, perfect companion for both casual and formal occasions.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/TOMI- Round Minimalist Watch WITH DATE.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 15800,
              "salePrice": 11800,
              "availability": "InStock"
            },
            {
              "id": 8,
              "name": "X - Round Minimalist Watch (Half Cut)",
              "description": "Unique minimalist watch with creative half-cut design, showcasing modern artistry and elegant simplicity.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/minimal/X - Round Minimalist Watch (Half Cut).webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 10800,
              "salePrice": 8200,
              "availability": "InStock"
            }
          ]
        },
        {
          "collection": "The Sports & Sales Division",
          "products": [{
              "id": 9,
              "name": "Aura - Never Stop Minimal Watch with Date -N905",
              "description": "Sleek sports watch with minimalist design and date function, engineered for active lifestyle and daily durability.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Aura - Never Stop Minimal Watch with Date -N905.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7000,
              "salePrice": 5500,
              "availability": "InStock"
            },
            {
              "id": 10,
              "name": "Chrona - Never Stop Minimal Watch - N928",
              "description": "Performance-focused sports watch with chronograph functionality, perfect for timing and precision athletic activities.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Chrona - Never Stop Minimal Watch - N928.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7500,
              "salePrice": 6500,
              "availability": "InStock"
            },
            {
              "id": 11,
              "name": "Dagahra- Never Stop Casual sports Watch with date - N911",
              "description": "Casual sports watch with date display, combining comfort with functionality for everyday athletic pursuits.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Dagahra- Never Stop Casual sports Watch with date - N911.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 9000,
              "salePrice": 8000,
              "availability": "InStock"
            },
            {
              "id": 12,
              "name": "Newmoon - Never Stop Chronograph sports Watch with date - N902",
              "description": "Advanced chronograph sports watch with date, featuring multiple timing functions and rugged design for serious athletes.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Newmoon - Never Stop Chronograph sports Watch with date - N902.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 10000,
              "salePrice": 9000,
              "availability": "InStock"
            },
            {
              "id": 13,
              "name": "RECDIS - Skmei 3 Time Sports Watch With Stainless Steel",
              "description": "Professional sports watch with triple time zones, stainless steel construction, ideal for international travelers and athletes.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/RECDIS - Skmei 3 Time Sports Watch With Stainless Steel.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 17800,
              "salePrice": 13400,
              "availability": "InStock"
            },
            {
              "id": 14,
              "name": "TOKDIS - Dual Time Sports Watch With Stainless Steel",
              "description": "Robust dual-time sports watch with premium stainless steel build, featuring reliable timekeeping for active pursuits across time zones.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/TOKDIS - Dual Time Sports Watch With Stainless Steel.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 16200,
              "salePrice": 12200,
              "availability": "InStock"
            },
            {
              "id": 15,
              "name": "Yraz - Never Stop Casual sports Watch with date",
              "description": "Casual sports watch with date window, versatile design perfect for everyday activities and weekend adventures with reliable performance.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/sports/Yraz - Never Stop Casual sports Watch with date.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 19800,
              "salePrice": 15100,
              "availability": "InStock"
            }
          ]
        }
      ]
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/modules/core/global-protection.js" defer></script>
  <script src="frontend/assets/js/modules/services/auth.js" defer></script>
  <script src="frontend/assets/js/modules/bootstrap/script.js" defer></script>
  <script src="frontend/assets/js/pages/shop-category-b.js"></script>
</body>

</html>
