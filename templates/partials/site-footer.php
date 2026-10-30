<?php
/**
 * Storefront footer.
 *
 * @var string $activePage key used for aria-current (home, about, contact, wishlist, order-tracking, shipping, ...)
 */
$activePage = $activePage ?? '';
$site = commerza_site_view_context();
$brand = commerza_e($site['brand_name']);
$quickLinks = [
  ['href' => 'index.php', 'label' => 'Home', 'page' => 'home'],
  ['href' => 'about.php', 'label' => 'About Us', 'page' => 'about'],
  ['href' => 'contact.php', 'label' => 'Contact', 'page' => 'contact'],
  ['href' => 'wishlist.php', 'label' => 'Wishlist', 'page' => 'wishlist'],
  ['href' => 'order-tracking.php', 'label' => 'Order Tracking', 'page' => 'order-tracking'],
];
$projectLinks = [
  ['href' => 'https://ahmershah.dev/projects/commerza', 'label' => 'Case Study'],
  ['href' => 'https://ahmershah.dev/blogs/building-commerza-security-first-ecommerce-engine', 'label' => 'Engineering Blog'],
];
$serviceLinks = [
  ['href' => 'shipping.php', 'label' => 'Shipping Info', 'page' => 'shipping'],
  ['href' => 'returns.php', 'label' => 'Returns', 'page' => 'returns'],
  ['href' => 'faq.php', 'label' => 'FAQ', 'page' => 'faq'],
  ['href' => 'warranty.php', 'label' => 'Warranty', 'page' => 'warranty'],
  ['href' => 'terms-of-service.php', 'label' => 'Terms of Service', 'page' => 'terms-of-service'],
  ['href' => 'privacy-policy.php', 'label' => 'Privacy Policy', 'page' => 'privacy-policy'],
];
?>
  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading"><?= $brand ?></h3>
          <p class="footer-text">
            Premium watches and accessories designed for the modern lifestyle. Exceptional
            craftsmanship, timeless design, and uncompromising quality.
          </p>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Quick Links</h3>
          <ul class="footer-links">
<?php foreach ($quickLinks as $link): ?>
            <li><a<?= commerza_nav_current($activePage, $link['page']) ?> href="<?= $link['href'] ?>"><?= $link['label'] ?></a></li>
<?php endforeach; ?>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Customer Service</h3>
          <ul class="footer-links">
<?php foreach ($serviceLinks as $link): ?>
            <li><a<?= commerza_nav_current($activePage, $link['page']) ?> href="<?= $link['href'] ?>"><?= $link['label'] ?></a></li>
<?php endforeach; ?>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Connect</h3>
          <div class="social-links">
<?php foreach ($site['social_links'] as $link): ?>
            <a href="<?= commerza_e($link['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $brand ?> on <?= commerza_e($link['label']) ?>"><i class="<?= commerza_e($link['icon']) ?>"></i></a>
<?php endforeach; ?>
          </div>
          <p class="footer-text mt-3">Email: <?= commerza_e($site['support_email']) ?></p>
          <p class="footer-text">Phone: <?= commerza_e($site['support_phone']) ?></p>
          <p class="footer-text">
<?php foreach ($projectLinks as $i => $link): ?>
            <?= $i > 0 ? '&middot;' : '' ?> <a href="<?= commerza_e($link['href']) ?>" target="_blank" rel="noopener"><?= commerza_e($link['label']) ?></a>
<?php endforeach; ?>
          </p>
        </div>
      </div>
      <div class="row">
        <div class="col-12 text-center py-3 border-top">
          <p class="footer-copyright">
            &copy; <?= date('Y') ?> <?= $brand ?>. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </footer>
