<?php
require_once __DIR__ . '/backend/data.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Read Commerza Terms of Service for website usage, orders, payments, returns, and account responsibilities." />
	<meta name="robots" content="index, follow" />
	<meta name="author" content="Syed Ahmer Shah" />
	<meta property="og:title" content="Terms of Service | Commerza" />
	<meta property="og:description" content="Commerza terms and conditions for using our website and services." />
	<meta property="og:url" content="https://commerza.ahmershah.dev/terms-of-service.php" />
	<meta property="og:type" content="website" />
	<meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp" />
	<title>Terms of Service | Commerza</title>
	<link rel="canonical" href="https://commerza.ahmershah.dev/terms-of-service.php" />

	<link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
	<link rel="stylesheet" href="frontend/assets/css/style.css" />
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
	<style>
		.policy-mail {
			color: #ff5a00;
			font-weight: 700;
			text-decoration: none;
		}

		.policy-mail:hover {
			color: #ff1f1f;
			text-decoration: underline;
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
					"contactPoint": {
						"@type": "ContactPoint",
						"contactType": "Customer Service",
						"email": "commerza.ahmer@gmail.com",
						"telephone": "+923148396293"
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
					}
				},
				{
					"@type": "BreadcrumbList",
					"@id": "https://commerza.ahmershah.dev/terms-of-service.php#breadcrumb",
					"itemListElement": [{
							"@type": "ListItem",
							"position": 1,
							"name": "Home",
							"item": "https://commerza.ahmershah.dev/"
						},
						{
							"@type": "ListItem",
							"position": 2,
							"name": "Terms of Service",
							"item": "https://commerza.ahmershah.dev/terms-of-service.php"
						}
					]
				},
				{
					"@type": "WebPage",
					"@id": "https://commerza.ahmershah.dev/terms-of-service.php#webpage",
					"url": "https://commerza.ahmershah.dev/terms-of-service.php",
					"name": "Terms of Service | Commerza",
					"description": "Commerza terms and conditions for account use, order placement, payments, shipping, returns, and legal responsibilities.",
					"inLanguage": "en",
					"isPartOf": {
						"@id": "https://commerza.ahmershah.dev/#website"
					},
					"breadcrumb": {
						"@id": "https://commerza.ahmershah.dev/terms-of-service.php#breadcrumb"
					}
				},
				{
					"@type": "TermsOfService",
					"@id": "https://commerza.ahmershah.dev/terms-of-service.php#terms",
					"name": "Commerza Terms of Service",
					"url": "https://commerza.ahmershah.dev/terms-of-service.php",
					"dateModified": "2026-01-01",
					"publisher": {
						"@id": "https://commerza.ahmershah.dev/#organization"
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
						<li class="nav-item"><a class="nav-link" href="terms-of-service.php" aria-current="page">Terms</a></li>
						<li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
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
					<li class="nav-item"><a class="nav-link" href="terms-of-service.php" aria-current="page">Terms</a></li>
					<li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
				</ul>
			</div>
		</div>
	</header>

	<main class="container my-5">
		<?php commerza_render_page_breadcrumb('Terms of Service'); ?>
		<section class="page-hero mb-5">
			<div class="hero-content">
				<span class="hero-badge"><i class="bi bi-file-earmark-text"></i> Legal</span>
				<h1 class="mt-3" style="color: #ff6600">Terms of Service</h1>
				<p class="product-desc mt-2">Effective date: January 1, 2026. These terms govern your use of Commerza, including account activity, purchases, and post-sale support.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">1. Agreement to These Terms</h2>
				<p class="product-desc mb-0">By accessing Commerza, creating an account, or placing an order, you agree to these Terms of Service and our Privacy Policy. If you disagree with any part, please discontinue use of the website and services.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">2. Eligibility and Account Responsibilities</h2>
				<p class="product-desc mb-0">You must provide accurate and complete profile information. You are responsible for maintaining the confidentiality of your login credentials and for all actions performed under your account, including orders and profile updates.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">3. Product Listings and Pricing</h2>
				<p class="product-desc mb-0">We work to keep product content accurate, including images, movement type, stock, and pricing. Occasional errors may occur. Commerza may correct listing details, adjust pricing mistakes, or cancel affected orders before dispatch.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">4. Order Acceptance and Fraud Controls</h2>
				<p class="product-desc mb-0">Order submission is a purchase request and does not constitute final acceptance. We may hold, reject, or cancel orders due to failed verification, suspected fraud, abuse of promotions, payment anomalies, duplicate orders, or unavailable inventory.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">5. Payment Terms</h2>
				<p class="product-desc mb-0">Supported payment method is currently Cash on Delivery. You must provide valid checkout details and any required verification references. Incorrect or unverifiable information may delay fulfillment or result in cancellation.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">6. Shipping, Delivery, and Risk</h2>
				<p class="product-desc mb-0">Shipping timelines are estimates and can vary by location or courier constraints. Delivery obligations are considered completed when the package is handed to the shipping partner or delivered according to the selected method.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">7. Returns, Refunds, and Warranty</h2>
				<p class="product-desc mb-2">Return, refund, and warranty handling follows the published policy pages. Eligibility depends on order status, item condition, and policy windows.</p>
				<ul class="product-desc mb-2">
					<li>Refund requests are available for delivered orders for up to <strong>7 days</strong> after delivery confirmation.</li>
					<li>Customers can submit requests from <a href="account.php" class="policy-mail">My Account</a> using the Refund Me option.</li>
					<li>Unauthorized, damaged, incomplete, or out-of-window claims may be declined.</li>
				</ul>
				<p class="product-desc mb-0">If you need review support, contact <a class="policy-mail" href="mailto:commerza.ahmer@gmail.com?subject=Commerza%20Refund%20Support">commerza.ahmer@gmail.com</a>.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">8. Acceptable Use</h2>
				<p class="product-desc mb-0">You may not attempt unauthorized access, bypass security controls, scrape protected content, disrupt checkout processes, reverse engineer APIs, or upload malicious code. Abuse can result in account suspension and legal action where applicable.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">9. Intellectual Property</h2>
				<p class="product-desc mb-0">All trademarks, logos, designs, text, product media, and platform code shown on Commerza are protected by intellectual property law. You may not copy, republish, or commercially reuse content without prior written authorization.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">10. Limitation of Liability</h2>
				<p class="product-desc mb-0">To the fullest extent permitted by law, Commerza is not liable for indirect, incidental, or consequential damages. Our aggregate liability for any claim related to an order is limited to the amount paid for that order.</p>
			</div>
		</section>

		<section class="card product-card mb-4">
			<div class="card-body">
				<h2 class="product-name mb-3">11. Governing Law and Policy Updates</h2>
				<p class="product-desc mb-0">These terms are governed by applicable laws of Pakistan. We may revise these terms to reflect legal, operational, or security changes. Updated versions become effective when posted with a revised effective date.</p>
			</div>
		</section>

		<section class="card product-card">
			<div class="card-body">
				<h2 class="product-name mb-3">12. Contact</h2>
				<p class="product-desc mb-0">For legal requests, policy clarifications, or dispute support, email <a class="policy-mail" href="mailto:commerza.ahmer@gmail.com?subject=Commerza%20Terms%20Support">commerza.ahmer@gmail.com</a> with your account email and order number (if applicable).</p>
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
						<li><a href="terms-of-service.php" aria-current="page">Terms of Service</a></li>
						<li><a href="privacy-policy.php">Privacy Policy</a></li>
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
	<script src="frontend/assets/js/global-protection.js"></script>
	<script src="frontend/assets/js/script.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>