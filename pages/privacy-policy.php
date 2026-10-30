<?php
require_once dirname(__DIR__) . '/backend/core/data.php';

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
$logoUrl = $siteBaseUrl . '/frontend/assets/images/logo/commerza_logo.svg';

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

$dataCorrectionMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode('Commerza Data Correction Request');
$privacyConcernMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode('Commerza Privacy Security Concern');
$privacySupportMailto = 'mailto:' . $supportEmail . '?subject=' . rawurlencode('Commerza Privacy Support');
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
						"email": "<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>",
						"telephone": "<?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?>"
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
  <?php commerza_partial('site-header', ['variant' => 'simple', 'activePage' => 'privacy-policy']); ?>

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
							<a class="policy-mail" href="<?= htmlspecialchars($dataCorrectionMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>.
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
							<a class="policy-mail" href="<?= htmlspecialchars($privacyConcernMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>.
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
				<p class="product-desc mb-0">For privacy questions, data requests, or security concerns, contact <a class="policy-mail" href="<?= htmlspecialchars($privacySupportMailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a> and include your registered email for faster verification.</p>
			</div>
		</section>
	</main>

  <?php commerza_partial('site-footer', ['activePage' => 'privacy-policy']); ?>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
	<script src="frontend/assets/js/modules/core/global-protection.js"></script>
	<script src="frontend/assets/js/modules/bootstrap/loader/module-loader.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
		integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
		crossorigin="anonymous"></script>
</body>

</html>