<?php
include __DIR__ . '/backend/data.php';

http_response_code(404);

$publicSettings = $GLOBALS['commerza_public_site_settings_payload'] ?? [];
$siteBrandName = trim((string)($publicSettings['brand']['name'] ?? 'Commerza'));
if ($siteBrandName === '') {
    $siteBrandName = 'Commerza';
}

$logoPath = trim((string)($publicSettings['brand']['logo'] ?? 'frontend/assets/images/logo/commerza-logo.webp'));
if ($logoPath === '') {
    $logoPath = 'frontend/assets/images/logo/commerza-logo.webp';
}

$faviconPath = trim((string)($publicSettings['brand']['favicon'] ?? 'frontend/assets/images/favicon/commerza-watches-icon.ico'));
if ($faviconPath === '') {
    $faviconPath = 'frontend/assets/images/favicon/commerza-watches-icon.ico';
}

$notFoundUrl = commerza_absolute_url('/error');
$homeUrl = commerza_absolute_url('/home');
$aboutUrl = commerza_absolute_url('/about');
$shopUrl = commerza_absolute_url('/products');
$logoUrl = commerza_absolute_url('/' . ltrim($logoPath, '/'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, follow" />
    <meta name="description" content="The page you requested could not be found. Explore <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> premium shopping from here." />
    <meta property="og:title" content="404 Not Found | <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:description" content="That link is broken, but your next premium find is one click away." />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?= htmlspecialchars($notFoundUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <title>404 Not Found | <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="canonical" href="<?= htmlspecialchars($notFoundUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="icon" href="<?= htmlspecialchars($faviconPath, ENT_QUOTES, 'UTF-8') ?>" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/assets/css/pages/404-inline.css">
</head>

<body>
    <div class="noise" aria-hidden="true"></div>

    <main class="scene">
        <section class="card-404" aria-labelledby="notFoundTitle">
            <span class="corner-flare" aria-hidden="true"></span>
            <div class="grid">
                <div class="left-pane">
                    <div class="logo-badge" aria-hidden="true">
                        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" loading="lazy" style="user-select: none; pointer-events:none" />
                    </div>

                    <p class="code" aria-label="404" style="user-select: none;">4<span class="code-glow">0</span>4</p>
                    <h1 class="lead-title" id="notFoundTitle">The link is broken, not your shopping mood.</h1>
                    <p class="lead-copy">
                        The page you requested moved, expired, or never existed. Jump back into <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?> and continue browsing premium pieces without losing momentum.
                    </p>
                    <div class="status-row">
                        <span class="status-dot" aria-hidden="true"></span>
                        Broken link detected
                    </div>
                </div>

                <div class="right-pane">
                    <p class="route-title">Get Back To Shopping</p>

                    <a class="route-card" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="route-label">
                            <span class="route-icon"><i class="bi bi-house-door"></i></span>
                            Main Shopping Page
                        </span>
                        <i class="bi bi-arrow-right route-arrow" aria-hidden="true"></i>
                    </a>

                    <a class="route-card" href="<?= htmlspecialchars($aboutUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="route-label">
                            <span class="route-icon"><i class="bi bi-info-circle"></i></span>
                            About <?= htmlspecialchars($siteBrandName, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <i class="bi bi-arrow-right route-arrow" aria-hidden="true"></i>
                    </a>

                    <a class="route-card" href="<?= htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="route-label">
                            <span class="route-icon"><i class="bi bi-bag"></i></span>
                            Explore Products
                        </span>
                        <i class="bi bi-arrow-right route-arrow" aria-hidden="true"></i>
                    </a>

                    <p class="footer-note">
                        If you typed the address manually, check for typos and try again.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <script src="frontend/assets/js/pages/404.js"></script>
</body>

</html>