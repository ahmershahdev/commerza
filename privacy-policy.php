<?php
require_once __DIR__ . '/backend/core/data.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
	|| ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

$host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
if ($host === '') {
	$host = 'localhost';
}

$configuredAppUrl = trim((string)getenv('COMMERZA_APP_URL'));
if ($configuredAppUrl !== '' && filter_var($configuredAppUrl, FILTER_VALIDATE_URL)) {
	$siteBaseUrl = rtrim($configuredAppUrl, '/');
} else {
	$siteBaseUrl = ($isHttps ? 'https' : 'http') . '://' . $host;
}

$homeUrl = $siteBaseUrl . '/';
$privacyUrl = $siteBaseUrl . '/privacy-policy.php';
$logoUrl = $siteBaseUrl . '/frontend/assets/images/logo/commerza-logo.webp';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Read Commerza Privacy Policy to understand what data we collect, how we use it, and your privacy rights." />
	<meta name="robots" content="index, follow" />
	<meta name="author" content="Syed Ahmer Shah" />
	<meta property="og:title" content="Privacy Policy | Commerza" />
	<meta property="og:description" content="How Commerza collects, uses, and protects your personal information." />
	<meta property="og:url" content="<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>" />
	<meta property="og:type" content="website" />
	<meta property="og:image" content="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" />
	<title>Privacy Policy | Commerza</title>
	<link rel="canonical" href="<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>" />

	<link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
	<link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="frontend/assets/css/pages/privacy-policy-inline.css">
	<script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
		{
			"@context": "https://schema.org",
			"@graph": [{
					"@type": "Organization",
					"@id": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>#organization",
					"name": "Commerza",
					"url": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>",
					"logo": "<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>",
					"contactPoint": {
						"@type": "ContactPoint",
						"contactType": "Privacy Support",
						"email": "commerza.ahmer@gmail.com",
						"telephone": "+923148396293"
					}
				},
				{
					"@type": "WebSite",
					"@id": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>#website",
					"url": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>",
					"name": "Commerza",
					"publisher": {
						"@id": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>#organization"
					}
				},
				{
					"@type": "BreadcrumbList",
					"@id": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>#breadcrumb",
					"itemListElement": [{
							"@type": "ListItem",
							"position": 1,
							"name": "Home",
							"item": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>"
						},
						{
							"@type": "ListItem",
							"position": 2,
							"name": "Privacy Policy",
							"item": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>"
						}
					]
				},
				{
					"@type": "WebPage",
					"@id": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>#webpage",
					"url": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>",
					"name": "Privacy Policy | Commerza",
					"description": "How Commerza collects, uses, stores, and protects personal data for account, order, and payment services.",
					"inLanguage": "en",
					"isPartOf": {
						"@id": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>#website"
					},
					"breadcrumb": {
						"@id": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>#breadcrumb"
					}
				},
				{
					"@type": "PrivacyPolicy",
					"@id": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>#policy",
					"name": "Commerza Privacy Policy",
					"url": "<?= htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8') ?>",
					"dateModified": "2026-01-01",
					"publisher": {
						"@id": "<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>#organization"
					}
				}
			]
		}
	</script>
</head>

<body class="dark-theme">
	<header>
		<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
			<div class="container-fluid">
				<a class="navbar-brand fw-bold" href="index.php">
					<img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy" class="navbar-logo me-2" />
					<span class="brand-text">COMMERZA</span>
				</a>

				<div class="d-flex align-items-center order-lg-2">
					<ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
						<li class="nav-item position-relative me-3">
							<a class="nav-link nav-icon-link" href="cart.php" aria-label="View cart">
								<i class="bi bi-cart3"></i>
								<span class="nav-badge" id="cart-count">0</span>
							</a>
						</li>
						<li class="nav-item position-relative me-3">
							<a class="nav-link nav-icon-link" href="wishlist.php" aria-label="View wishlist">
								<i class="bi bi-heart"></i>
								<span class="nav-badge" id="wishlist-count">0</span>
							</a>
						</li>
						<li class="nav-item">
							<a class="nav-link nav-icon-link" href="account.php" aria-label="Account"><i class="bi bi-person"></i></a>
						</li>
					</ul>
					<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas"
						aria-controls="navbarOffcanvas" aria-label="Toggle navigation">
						<span class="navbar-toggler-icon"></span>
					</button>
				</div>

				<div class="collapse navbar-collapse order-lg-1" id="navbarSupportedContent">
					<ul class="navbar-nav me-auto mb-2 mb-lg-0">
						<li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
						<li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
						<li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
						<li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
						<li class="nav-item"><a class="nav-link" href="privacy-policy.php" aria-current="page">Privacy</a></li>
					</ul>
				</div>
			</div>
		</nav>

		<div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="offcanvasNavbarLabel">
			<div class="offcanvas-header">
				<h5 class="offcanvas-title" id="offcanvasNavbarLabel">
					<img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy" class="offcanvas-logo me-2" />
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
					<a href="wishlist.php" class="offcanvas-action-btn">
						<i class="bi bi-heart"></i>
						<span>Wishlist</span>
						<span class="offcanvas-badge" id="wishlist-count-mobile">0</span>
					</a>
					<a href="account.php" class="offcanvas-action-btn">
						<i class="bi bi-person"></i>
						<span>Account</span>
					</a>
				</div>
				<ul class="navbar-nav">
					<li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
					<li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
					<li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
					<li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
					<li class="nav-item"><a class="nav-link" href="privacy-policy.php" aria-current="page">Privacy</a></li>
				</ul>
			</div>
		</div>
	</header>

	<main class="container my-5">
		<?php commerza_render_page_breadcrumb('Privacy Policy'); ?>
		<section class="page-hero mb-5">
			<div class="hero-content">
				<span class="hero-badge"><i class="bi bi-shield-lock"></i> Legal</span>
				<h1 class="mt-3">Privacy Policy</h1>
				<p class="product-desc mt-2">Effective date: January 1, 2026. This policy explains what personal data Commerza collects, why we collect it, and how we protect it.</p>
			</div>
		</section>

		<section class="mb-4">

			<div class="accordion policy-faq-accordion" id="faqAccordion">
				<div class="accordion-item">
					<h3 class="accordion-header" id="privacyFaqOne">
						<button class="accordion-button collapsed" type="button"
							data-bs-toggle="collapse" data-bs-target="#privacyFaqPanelOne"
							aria-expanded="false" aria-controls="privacyFaqPanelOne">
							How can I request a data update?
						</button>
					</h3>
					<div id="privacyFaqPanelOne" class="accordion-collapse collapse" aria-labelledby="privacyFaqOne" data-bs-parent="#faqAccordion">
						<div class="accordion-body product-desc">
							Email your registered account address and requested correction to
							<a class="policy-mail" href="mailto:commerza.ahmer@gmail.com?subject=Commerza%20Data%20Correction%20Request">commerza.ahmer@gmail.com</a>.
						</div>
					</div>
				</div>

				<div class="accordion-item">
					<h3 class="accordion-header" id="privacyFaqTwo">
						<button class="accordion-button collapsed" type="button"
							data-bs-toggle="collapse" data-bs-target="#privacyFaqPanelTwo"
							aria-expanded="false" aria-controls="privacyFaqPanelTwo">
							How do I report a privacy or security concern?
						</button>
					</h3>
					<div id="privacyFaqPanelTwo" class="accordion-collapse collapse" aria-labelledby="privacyFaqTwo" data-bs-parent="#faqAccordion">
						<div class="accordion-body product-desc">
							Send full details, screenshots, and your account email (if applicable) to
							<a class="policy-mail" href="mailto:commerza.ahmer@gmail.com?subject=Commerza%20Privacy%20Security%20Concern">commerza.ahmer@gmail.com</a>.
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">1. Information We Collect</h2>
				<p class="product-desc mb-0">Depending on your activity, we may collect your name, email, phone number, delivery address, account credentials (hashed), order details, payment references, support messages, and limited technical logs such as IP and session identifiers.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">2. Why We Process Your Data</h2>
				<p class="product-desc mb-0">We process data to create and secure accounts, fulfill orders, detect abuse, prevent fraud, verify payments, provide support, send service notifications, and improve site performance and customer experience.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">3. Legal Basis and Consent</h2>
				<p class="product-desc mb-0">Data processing is based on contract performance (orders and account services), security interests (fraud and abuse prevention), legal obligations, and user consent where required for optional communications.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">4. Payments and Financial Data</h2>
				<p class="product-desc mb-0">Commerza currently operates with Cash on Delivery for checkout. When payment references are collected for verification or dispute handling, only the minimum required metadata is stored. Sensitive card-number data is not stored in Commerza databases.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">5. Cookies, Sessions, and Storage</h2>
				<p class="product-desc mb-0">Commerza uses session cookies and browser storage to keep users logged in, maintain cart/wishlist state, enforce CSRF protection, and preserve essential security context. You may clear browser storage, but some features may stop working correctly.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">6. Data Sharing and Third Parties</h2>
				<p class="product-desc mb-0">We do not sell personal information. Data is shared only with trusted service providers needed for payments, notifications, infrastructure, and support operations, and only to the extent required for those services.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">7. Retention and Deletion</h2>
				<p class="product-desc mb-0">We retain account and order records as needed for customer service, financial reconciliation, fraud prevention, and legal compliance. Data that is no longer required is removed or anonymized where practical.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">8. Security Safeguards</h2>
				<p class="product-desc mb-0">We apply technical controls such as input validation, session protections, token-based request checks, and access restrictions for sensitive actions. No method is perfect, but we continuously improve safeguards against unauthorized access.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">9. Your Privacy Rights</h2>
				<p class="product-desc mb-0">You may request correction of inaccurate data, account updates, and assistance with data-related concerns. We may ask for verification details before processing any request to protect account security.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">10. Children’s Privacy</h2>
				<p class="product-desc mb-0">Commerza is not intended for children under 13. If we discover that a child account was created without appropriate authorization, we will take steps to remove associated personal information.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">11. Policy Changes</h2>
				<p class="product-desc mb-0">We may update this policy to reflect legal requirements, operational changes, or new features. Revised versions are posted on this page with an updated effective date.</p>
			</div>
		</section>

		<section class="card product-card">
			<div class="card-body">
				<h2 class="product-name mb-3">12. Contact</h2>
				<p class="product-desc mb-0">For privacy questions, data requests, or security concerns, contact <a class="policy-mail" href="mailto:commerza.ahmer@gmail.com?subject=Commerza%20Privacy%20Support">commerza.ahmer@gmail.com</a> and include your registered email for faster verification.</p>
			</div>
		</section>
	</main>

	<footer class="footer">
		<div class="container-fluid">
			<div class="row py-5">
				<div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
					<h3 class="footer-heading">Commerza</h3>
					<p class="footer-text">Premium watches and accessories for the modern lifestyle. Quality craftsmanship meets contemporary design.</p>
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
						<li><a href="privacy-policy.php" aria-current="page">Privacy Policy</a></li>
					</ul>
				</div>
				<div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
					<h3 class="footer-heading">Connect</h3>
					<div class="social-links">
						<a href="https://www.facebook.com/commerza.ahmer" target="_blank" rel="noopener" aria-label="Commerza on Facebook"><i class="bi bi-facebook"></i></a>
						<a href="https://x.com/commerza_ahmer" target="_blank" rel="noopener" aria-label="Commerza on X"><i class="bi bi-twitter"></i></a>
						<a href="https://www.instagram.com/commerza.ahmer" target="_blank" rel="noopener" aria-label="Commerza on Instagram"><i class="bi bi-instagram"></i></a>
					</div>
					<p class="footer-text mt-3">Email: commerza.ahmer@gmail.com</p>
					<p class="footer-text">Phone: +92 314 8396293</p>
				</div>
			</div>
			<div class="row">
				<div class="col-12 text-center py-3 border-top">
					<p class="footer-copyright">&copy; 2026 Commerza. All rights reserved.</p>
				</div>
			</div>
		</div>
	</footer>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="frontend/assets/js/modules/core/global-protection.js"></script>
	<script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>