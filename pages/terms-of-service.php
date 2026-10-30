<?php
require_once dirname(__DIR__) . '/backend/core/data.php';

$publicSettings = $GLOBALS['commerza_public_site_settings_payload'] ?? [];
$contactSettings = [];
if (is_array($publicSettings) && isset($publicSettings['contact']) && is_array($publicSettings['contact'])) {
	$contactSettings = $publicSettings['contact'];
}

$supportEmail = trim((string)($contactSettings['email'] ?? 'support@ahmershah.dev'));
if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
	$supportEmail = 'support@ahmershah.dev';
}

$supportPhone = trim((string)($contactSettings['phone'] ?? '+92 370 4831994'));
if ($supportPhone === '') {
	$supportPhone = '+92 370 4831994';
}

$refundSupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode('Commerza Refund Support');
$termsSupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode('Commerza Terms Support');
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
	<meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza_logo.svg" />
	<title>Terms of Service | Commerza</title>
	<link rel="canonical" href="https://commerza.ahmershah.dev/terms-of-service.php" />

	<link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
	<link rel="stylesheet" href="frontend/assets/css/modules/core/style.css" />
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
	<link rel="stylesheet" href="frontend/assets/css/pages/terms-of-service-inline.css">
	<script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
		{
			"@context": "https://schema.org",
			"@graph": [{
					"@type": "Organization",
					"@id": "https://commerza.ahmershah.dev/#organization",
					"name": "Commerza",
					"url": "https://commerza.ahmershah.dev/",
					"logo": "https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza_logo.svg",
					"contactPoint": {
						"@type": "ContactPoint",
						"contactType": "Customer Service",
						"email": "<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>",
						"telephone": "<?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?>"
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
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'terms-of-service']); ?>

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
				<p class="product-desc mb-0">If you need review support, contact <a class="policy-mail" href="<?= htmlspecialchars($refundSupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>.</p>
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
				<p class="product-desc mb-0">For legal requests, policy clarifications, or dispute support, email <a class="policy-mail" href="<?= htmlspecialchars($termsSupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a> with your account email and order number (if applicable).</p>
			</div>
		</section>
	</main>

  <?php commerza_partial('site-footer', ['activePage' => 'terms-of-service']); ?>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="frontend/assets/js/modules/core/global-protection.js"></script>
	<script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>