<?php
require_once dirname(__DIR__) . '/backend/core/data.php';
require_once COMMERZA_ROOT . '/backend/helpers/nav_state.php';
$nav_counts = commerza_get_nav_counts($con);

$category_a_feature_video = 'frontend/assets/videos/products/smart/automatic_watches_carousel.mp4';
$categoryVideoStmt = $con->prepare('SELECT setting_val FROM site_settings WHERE setting_key = ? LIMIT 1');
if ($categoryVideoStmt) {
  $settingKey = 'category_a_feature_video';
  $categoryVideoStmt->bind_param('s', $settingKey);
  $categoryVideoStmt->execute();
  $categoryVideoResult = $categoryVideoStmt->get_result();
  $categoryVideoRow = $categoryVideoResult ? $categoryVideoResult->fetch_assoc() : null;
  $categoryVideoStmt->close();

  $savedVideo = trim((string)($categoryVideoRow['setting_val'] ?? ''));
  if (
    $savedVideo !== '' &&
    strpos($savedVideo, '..') === false &&
    strpos($savedVideo, '\\') === false &&
    preg_match('#^[a-zA-Z0-9/_\-.]+$#', $savedVideo) === 1
  ) {
    $category_a_feature_video = $savedVideo;
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
  <meta property="og:title" content="Commerza | The Elite Engineering Series">
  <meta property="og:description"
    content="Discover the intersection of tradition and technology. Shop the Automatic Vault and Smart Grid collections.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/shop-category-a.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza_logo.svg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Commerza | The Elite Engineering Series">
  <meta name="twitter:description" content="Discover the intersection of tradition and technology. Shop the Automatic Vault and Smart Grid collections.">
  <meta name="twitter:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza_logo.svg">
  <title>Commerza | The Elite Engineering Series – Precision & Tech</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/shop-category-a.php" />
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico">
  <link rel="stylesheet" href="frontend/assets/css/modules/core/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/shop-category-a-inline.css">
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "The Elite Engineering Series - Commerza",
      "description": "Explore Commerza's elite collection: The Automatic Vault with premium automatic watches and Smart Evolution Series with cutting-edge smart watches.",
      "url": "https://commerza.ahmershah.dev/shop-category-a.php",
      "about": ["Premium Watches", "Mechanical Timepieces", "Smart Watches"],
      "keywords": "automatic watches, smart watches, premium timepieces",
      "isPartOf": {
        "@type": "WebSite",
        "name": "Commerza",
        "url": "https://commerza.ahmershah.dev/"
      },
      "publisher": {
        "@type": "Organization",
        "name": "Commerza",
        "logo": "https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza_logo.svg"
      }
    }
  </script>
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "name": "The Automatic Vault & Smart Evolution Series",
      "description": "Premium automatic watches and advanced smart watches from Commerza",
      "url": "https://commerza.ahmershah.dev/shop-category-a.php",
      "about": ["Premium Watches", "Mechanical Timepieces", "Smart Watches"],
      "keywords": "automatic watches, smart watches, premium timepieces",
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
              "availability": "https://schema.org/InStock"
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
              "availability": "https://schema.org/InStock"
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
              "availability": "https://schema.org/InStock"
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
              "availability": "https://schema.org/InStock"
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
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 6,
            "name": "Brown Gold Premium Watch",
            "description": "Premium brown leather with luxurious golden dial, crafted with premium automatic movement.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/brown-premium-watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "10200",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 7,
            "name": "Premium Black Gold Watch",
            "description": "Premium black leather strap with premium golden dial, smooth and refined automatic movement.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/premium-black-gold.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "12400",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 8,
            "name": "Black Minimalist Watch",
            "description": "Modern minimalist design with black leather strap, clean and simple automatic movement.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-minimalist.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8300",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 9,
            "name": "Fajr - Hybrid Digital Rectangle Watch",
            "description": "Advanced hybrid digital display with rectangular design, combining intelligent timekeeping with modern aesthetics and reliable smart features.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Fajr - Hybrid Digital Rectangle Watch.webp",
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
            "name": "Fajr - Hybrid Digital Round Watch",
            "description": "Elegant hybrid digital timepiece with round dial, seamlessly blending traditional watch aesthetics with cutting-edge smart connectivity.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Fajr - Hybrid Digital Round Watch.webp",
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
            "name": "Hango X - Skmei Digital Watch - Black",
            "description": "Professional digital sports watch in sleek black finish, featuring multi-function display with water resistance and precision timing capabilities.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Hango X - Skmei Digital Watch - Black.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "8900",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 12,
            "name": "I-8 Pro Max Smart Watch",
            "description": "Premium smart watch with advanced health monitoring, comprehensive fitness tracking, and seamless connectivity for the modern professional.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/I-8 Pro Max Smart Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "10800",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 13,
            "name": "S-8 Pro Max Smart Watch",
            "description": "Top-tier smart watch featuring advanced AI assistant, comprehensive health analytics, extended battery life, and premium build quality.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/S-8 Pro Max Smart Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "13800",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 14,
            "name": "T800 Ultra 2 49 mm",
            "description": "Rugged ultra-performance smart watch with expansive 49mm display, military-grade durability, extended battery endurance, and all-terrain capabilities.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/T800 Ultra 2 49 mm.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "12600",
              "availability": "https://schema.org/InStock"
            }
          },
          {
            "@type": "Product",
            "position": 15,
            "name": "Ultra 8 Smart Watch",
            "description": "Ultra-premium smart watch with sophisticated design, intuitive interface, comprehensive wellness monitoring, and seamless daily integration.",
            "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Ultra 8 Smart Watch.webp",
            "brand": {
              "@type": "Brand",
              "name": "Commerza"
            },
            "offers": {
              "@type": "Offer",
              "priceCurrency": "PKR",
              "price": "15500",
              "availability": "https://schema.org/InStock"
            }
          }
        ]
      }
    }
  </script>
</head>

<body class="dark-theme premium-cards">

  <?php commerza_partial('site-header', ['variant' => 'shop', 'activePage' => 'shop-a', 'navCounts' => $nav_counts]); ?>
  <main class="container-fluid shop-category-main">
    <section class="page-breadcrumb-shell">
      <nav aria-label="Breadcrumb">
        <ol class="breadcrumb page-breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Shop Category A</li>
        </ol>
      </nav>
    </section>
    <div class="row mt-2">
      <div class="col">
        <div id="customAlert" class="alert alert-danger text-center"
          style="display:none; position:fixed; top:20px; right:0; left:0; margin:auto; width:300px; z-index:9998;">
          You cannot add more than 10 products!
        </div>

        <section class="hero-block mt-4">
          <div class="row align-items-center g-4">
            <div class="col-12 col-lg-7">
              <p class="hero-kicker">Elite Engineering Series</p>
              <h1 class="hero-title">Precision Meets Smart Innovation</h1>
              <p class="hero-subtitle">Explore automatic movements crafted for collectors and smart wearables engineered
                for everyday performance.</p>
              <div class="hero-actions">
                <a href="#automatic" class="btn hero-btn-primary">Shop Automatic Vault</a>
                <a href="#smart" class="btn hero-btn-outline text-white">Shop Smart Evolution</a>
              </div>
              <div class="hero-stats">
                <div class="stat-item">
                  <span class="stat-value">48H</span>
                  <span class="stat-label">Power Reserve</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value">5 ATM</span>
                  <span class="stat-label">Water Resistance</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value">AI</span>
                  <span class="stat-label">Smart Insights</span>
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-5">
              <div class="hero-grid">
                <div class="hero-tile tile-large">
                  <img src="frontend/assets/images/products/featured/premium-black-gold.webp"
                    alt="premium black gold automatic watch" loading="lazy">
                </div>
                <div class="hero-tile">
                  <img src="frontend/assets/images/products/featured/white-gold-steel.webp" alt="white gold steel watch"
                    loading="lazy">
                </div>
                <div class="hero-tile">
                  <img src="frontend/assets/images/products/smart/I-8 Pro Max Smart Watch.webp" alt="smart watch"
                    loading="lazy">
                </div>
                <div class="hero-tile">
                  <img src="frontend/assets/images/products/featured/black-gold-dial.webp" alt="black gold dial watch"
                    loading="lazy">
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
                <h3 class="highlight-title">Mechanical Craft</h3>
                <p class="highlight-text">Automatic calibers tuned for reliable performance and smooth sweep seconds.
                </p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="highlight-card">
                <div class="highlight-icon">02</div>
                <h3 class="highlight-title">Smart Comfort</h3>
                <p class="highlight-text">Lightweight smart watches with all-day tracking and seamless pairing.</p>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div class="highlight-card">
                <div class="highlight-icon">03</div>
                <h3 class="highlight-title">Premium Finish</h3>
                <p class="highlight-text">Gold accents, sapphire-inspired clarity, and refined dial detailing.</p>
              </div>
            </div>
          </div>
        </section>

        <section class="series-pulse-board mt-4" aria-label="Category A quick buying guide">
          <div class="series-pulse-grid">
            <article class="series-pulse-chip">
              <span class="chip-kicker">Collector Cue</span>
              <strong>Automatic Vault</strong>
              <p>Choose this line when you want mechanical depth, heavier presence, and statement dials.</p>
            </article>
            <article class="series-pulse-chip">
              <span class="chip-kicker">Daily Cue</span>
              <strong>Smart Evolution</strong>
              <p>Pick this lane for lighter wear, fitness tracking, and quick pairing for workday routines.</p>
            </article>
            <article class="series-pulse-chip">
              <span class="chip-kicker">Price Cue</span>
              <strong>Filter + Compare</strong>
              <p>Use movement and price sorting together to shortlist faster before checking out.</p>
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

        <section class="product-section-block" data-section-id="automatic-vault">
          <h1 class="text-center mt-5 mb-4" id="automatic">COMMERZA | The
            Elite Engineering Series
          </h1>

          <section class="container-fluid px-0 mb-5">
            <div class="ratio ratio-16x9">
              <video autoplay muted loop playsinline preload="metadata" loading="lazy" class="w-100 h-100"
                style="object-fit: cover;" aria-label="Automatic watches showcase">
                <source src="<?= htmlspecialchars($category_a_feature_video) ?>" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            </div>
          </section>

          <h2 class="text-center mt-5 mb-2"> The Automatic Vault
          </h2>
          <p class="text-center text-secondary mb-4">Hand-finished cases, luminous dials, and movements engineered for
            collectors.</p>

          <div id="automatic-vault-products-container" class="row mt-4">

          </div>
        </section>

        <section class="product-section-block" data-section-id="smart-evolution">
          <h2 class="text-center mt-5 mb-2" id="smart">Smart Evolution Series
          </h2>
          <p class="text-center text-secondary mb-4">Modern essentials with smart tracking, sleek builds, and all-day
            reliability.</p>

          <div id="smart-evolution-products-container" class="row mt-4">

          </div>
        </section>

        <div class="row mt-4">
          <div class="col d-flex justify-content-end">
            <nav aria-label="Product pagination">
              <ul class="pagination mb-0">
                <li class="page-item">
                  <a class="page-link" href="index.php">Prev</a>
                </li>
                <li class="page-item">
                  <a class="page-link" href="index.php">1</a>
                </li>
                <li class="page-item active">
                  <a class="page-link" href="shop-category-a.php">2</a>
                </li>
                <li class="page-item">
                  <a class="page-link" href="shop-category-b.php">3</a>
                </li>
                <li class="page-item">
                  <a class="page-link" href="shop-category-b.php">Next</a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
        <a href="#" id="backToTop" class="rounded-circle" title="Go to top">↑</a>
      </div>
    </div>
  </main>

  <?php commerza_partial('site-footer', ['activePage' => 'shop-a']); ?>

  <script <?= commerza_csp_nonce_attr() ?> type="application/json">
    {
      "page": "shop-category-a",
      "name": "The Automatic Vault & Smart Evolution Series",
      "category": "Premium Watches",
      "subcategory": "Mechanical & Smart Timepieces",
      "section": "The Elite Engineering Series",
      "collections": [{
          "collection": "The Automatic Vault",
          "products": [{
              "id": 1,
              "name": "White Gold Steel Watch",
              "description": "Premium white dial with golden accents, stainless steel case with automatic movement mechanism.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/white-gold-steel.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 8500,
              "salePrice": 6200,
              "availability": "InStock"
            },
            {
              "id": 2,
              "name": "Black White Gold Watch",
              "description": "Premium black leather strap with white and golden dial, smooth automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-white-gold.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 9200,
              "salePrice": 7100,
              "availability": "InStock"
            },
            {
              "id": 3,
              "name": "Skeleton Gold Steel Watch",
              "description": "White and golden skeleton display with premium steel case, clear and clean design with automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/skeleton-gold-steel.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 11500,
              "salePrice": 8900,
              "availability": "InStock"
            },
            {
              "id": 4,
              "name": "Brown White Gold Watch",
              "description": "Rich brown leather strap with white and golden dial, premium automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/brown-gold-dial.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 12800,
              "salePrice": 9600,
              "availability": "InStock"
            },
            {
              "id": 5,
              "name": "Black Gold Dial Watch",
              "description": "Elegant black leather strap with stunning golden dial, precision automatic movement mechanism.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-gold-dial.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 10200,
              "salePrice": 7800,
              "availability": "InStock"
            },
            {
              "id": 6,
              "name": "Brown Gold Premium Watch",
              "description": "Premium brown leather with luxurious golden dial, crafted with premium automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/brown-premium-watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 13500,
              "salePrice": 10200,
              "availability": "InStock"
            },
            {
              "id": 7,
              "name": "Premium Black Gold Watch",
              "description": "Premium black leather strap with premium golden dial, smooth and refined automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/premium-black-gold.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 16500,
              "salePrice": 12400,
              "availability": "InStock"
            },
            {
              "id": 8,
              "name": "Black Minimalist Watch",
              "description": "Modern minimalist design with black leather strap, clean and simple automatic movement.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/featured/black-minimalist.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 11000,
              "salePrice": 8300,
              "availability": "InStock"
            }
          ]
        },
        {
          "collection": "Smart Evolution Series",
          "products": [{
              "id": 9,
              "name": "Fajr - Hybrid Digital Rectangle Watch",
              "description": "Advanced hybrid digital display with rectangular design, combining intelligent timekeeping with modern aesthetics and reliable smart features.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Fajr - Hybrid Digital Rectangle Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7000,
              "salePrice": 5500,
              "availability": "InStock"
            },
            {
              "id": 10,
              "name": "Fajr - Hybrid Digital Round Watch",
              "description": "Elegant hybrid digital timepiece with round dial, seamlessly blending traditional watch aesthetics with cutting-edge smart connectivity.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Fajr - Hybrid Digital Round Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 7500,
              "salePrice": 6500,
              "availability": "InStock"
            },
            {
              "id": 11,
              "name": "Hango X - Skmei Digital Watch - Black",
              "description": "Professional digital sports watch in sleek black finish, featuring multi-function display with water resistance and precision timing capabilities.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Hango X - Skmei Digital Watch - Black.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 11500,
              "salePrice": 8900,
              "availability": "InStock"
            },
            {
              "id": 12,
              "name": "I-8 Pro Max Smart Watch",
              "description": "Premium smart watch with advanced health monitoring, comprehensive fitness tracking, and seamless connectivity for the modern professional.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/I-8 Pro Max Smart Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 14200,
              "salePrice": 10800,
              "availability": "InStock"
            },
            {
              "id": 13,
              "name": "S-8 Pro Max Smart Watch",
              "description": "Top-tier smart watch featuring advanced AI assistant, comprehensive health analytics, extended battery life, and premium build quality.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/S-8 Pro Max Smart Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 18500,
              "salePrice": 13800,
              "availability": "InStock"
            },
            {
              "id": 14,
              "name": "T800 Ultra 2 49 mm",
              "description": "Rugged ultra-performance smart watch with expansive 49mm display, military-grade durability, extended battery endurance, and all-terrain capabilities.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/T800 Ultra 2 49 mm.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 16800,
              "salePrice": 12600,
              "availability": "InStock"
            },
            {
              "id": 15,
              "name": "Ultra 8 Smart Watch",
              "description": "Ultra-premium smart watch with sophisticated design, intuitive interface, comprehensive wellness monitoring, and seamless daily integration.",
              "image": "https://commerza.ahmershah.dev/frontend/assets/images/products/smart/Ultra 8 Smart Watch.webp",
              "brand": "Commerza",
              "priceCurrency": "PKR",
              "price": 20500,
              "salePrice": 15500,
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
  <script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js" defer></script>
  <script src="frontend/assets/js/pages/shop-category-a.js"></script>
</body>

</html>