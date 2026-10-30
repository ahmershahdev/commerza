<?php
/**
 * Storefront navbar + mobile offcanvas.
 *
 * @var string $variant    simple (info pages) | commerce (cart/wishlist icons) | shop (adds series menu + search)
 * @var string $activePage key used for aria-current (home, about, contact, cart, wishlist, account, shop-a, shop-b)
 * @var array  $navCounts  optional ['cart_count' => int, 'wishlist_count' => int]
 * @var string $accountHref optional account link (defaults to account.php)
 * @var bool   $themeToggle optional dark/light toggle button in the offcanvas
 */
$variant = $variant ?? 'simple';
$activePage = $activePage ?? '';
$cartCount = (int)($navCounts['cart_count'] ?? 0);
$wishlistCount = (int)($navCounts['wishlist_count'] ?? 0);
$accountHref = commerza_e($accountHref ?? 'account.php');
$themeToggle = (bool)($themeToggle ?? false);
$site = commerza_site_view_context();
$brand = commerza_e($site['brand_name']);
$brandUpper = commerza_e(strtoupper($site['brand_name']));
$logo = commerza_e($site['logo']);
$showCommerceIcons = $variant !== 'simple';
$isShop = $variant === 'shop';
$series = [
  ['href' => 'shop-category-a.php', 'label' => 'The Automatic Vault', 'page' => 'shop-a'],
  ['href' => 'shop-category-a.php#smart', 'label' => 'Smart Evolution Series', 'page' => ''],
  ['href' => 'shop-category-b.php', 'label' => 'The Signature Collection', 'page' => 'shop-b'],
  ['href' => 'shop-category-b.php#tactical', 'label' => 'The Sports & Sales Division', 'page' => ''],
];
?>
  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="<?= $logo ?>" alt="<?= $brand ?> Logo" loading="lazy" class="navbar-logo me-2" />
          <span class="brand-text"><?= $brandUpper ?></span>
        </a>

        <div class="d-flex align-items-center order-lg-2">
          <ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
<?php if ($showCommerceIcons): ?>
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link"<?= commerza_nav_current($activePage, 'cart') ?> href="cart.php" aria-label="View cart">
                <i class="bi bi-cart3" id="cart-icon"></i>
                <span class="nav-badge" id="cart-count"><?= $cartCount ?></span>
              </a>
            </li>
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link"<?= commerza_nav_current($activePage, 'wishlist') ?> href="wishlist.php" aria-label="View wishlist">
                <i class="bi bi-heart"></i>
                <span class="nav-badge" id="wishlist-count"><?= $wishlistCount ?></span>
              </a>
            </li>
<?php endif; ?>
            <li class="nav-item">
              <a class="nav-link nav-icon-link"<?= commerza_nav_current($activePage, 'account') ?> href="<?= $accountHref ?>" aria-label="Account"><i class="bi bi-person"></i></a>
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
              <a class="nav-link"<?= commerza_nav_current($activePage, 'home') ?> href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link"<?= commerza_nav_current($activePage, 'about') ?> href="about.php">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link"<?= commerza_nav_current($activePage, 'contact') ?> href="contact.php">Contact</a>
            </li>
<?php if ($isShop): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle text-uppercase"<?= commerza_nav_current($activePage, 'shop-a', 'shop-b') ?> href="#" id="shopDropdown" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                Series
              </a>
              <ul class="dropdown-menu border-2 border-dark" aria-labelledby="shopDropdown">
<?php foreach ($series as $index => $item): ?>
<?php if ($index === 2): ?>
                <li>
                  <hr class="dropdown-divider">
                </li>
<?php endif; ?>
                <li><a class="dropdown-item"<?= $item['page'] !== '' ? commerza_nav_current($activePage, $item['page']) : '' ?> href="<?= commerza_e($item['href']) ?>"><?= commerza_e($item['label']) ?></a></li>
<?php endforeach; ?>
              </ul>
            </li>
<?php endif; ?>
          </ul>
<?php if ($isShop): ?>
          <form class="d-flex search-form d-none d-lg-flex" name="product_query" action="products" method="GET">
            <input class="form-control search-input" type="search" name="q" placeholder="Search products..."
              aria-label="Search" />
            <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
          </form>
<?php endif; ?>
        </div>
      </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">
          <img src="<?= $logo ?>" alt="<?= $brand ?> Logo" loading="lazy" class="offcanvas-logo me-2" />
          <span class="brand-text"><?= $brandUpper ?></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="offcanvas-user-actions">
          <a href="cart.php" class="offcanvas-action-btn"<?= commerza_nav_current($activePage, 'cart') ?>>
            <i class="bi bi-cart3"></i>
            <span>Cart</span>
            <span class="offcanvas-badge" id="cart-count-mobile"><?= $cartCount ?></span>
          </a>
          <a href="wishlist.php" class="offcanvas-action-btn"<?= commerza_nav_current($activePage, 'wishlist') ?>>
            <i class="bi bi-heart"></i>
            <span>Wishlist</span>
            <span class="offcanvas-badge" id="wishlist-count-mobile"><?= $wishlistCount ?></span>
          </a>
          <a href="<?= $accountHref ?>" class="offcanvas-action-btn"<?= commerza_nav_current($activePage, 'account') ?>>
            <i class="bi bi-person"></i>
            <span>Account</span>
          </a>
<?php if ($themeToggle): ?>
          <button type="button" class="offcanvas-action-btn offcanvas-action-btn-theme"
            data-theme-toggle="account-offcanvas" data-theme-origin="left-top">
            <i class="bi" data-theme-icon></i>
            <span data-theme-label>Dark Mode</span>
          </button>
<?php endif; ?>
        </div>
<?php if ($isShop): ?>
        <form class="d-flex search-form mb-4" name="product_query_mobile" action="products" method="GET">
          <input class="form-control search-input" type="search" name="q" placeholder="Search products..." aria-label="Search" />
          <button class="btn search-btn" type="submit"><i class="bi bi-search"></i></button>
        </form>
<?php endif; ?>
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link"<?= commerza_nav_current($activePage, 'home') ?> href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link"<?= commerza_nav_current($activePage, 'about') ?> href="about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link"<?= commerza_nav_current($activePage, 'contact') ?> href="contact.php">Contact</a>
          </li>
<?php if ($isShop): ?>
          <li class="nav-item">
            <a class="nav-link text-uppercase fw-bold mt-3 mb-2 series-nav-heading">Series</a>
          </li>
<?php foreach ($series as $item): ?>
          <li class="nav-item">
            <a class="nav-link ps-4"<?= $item['page'] !== '' ? commerza_nav_current($activePage, $item['page']) : '' ?> href="<?= commerza_e($item['href']) ?>"><?= commerza_e($item['label']) ?></a>
          </li>
<?php endforeach; ?>
<?php endif; ?>
        </ul>
<?php if ($isShop): ?>
        <div class="offcanvas-footer mt-5">
          <div class="social-links">
<?php foreach ($site['social_links'] as $link): ?>
            <a href="<?= commerza_e($link['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $brand ?> on <?= commerza_e($link['label']) ?>"><i class="<?= commerza_e($link['icon']) ?>"></i></a>
<?php endforeach; ?>
          </div>
          <p class="footer-text mt-3"><?= commerza_e($site['support_email']) ?></p>
        </div>
<?php endif; ?>
      </div>
    </div>
  </header>
