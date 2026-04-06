<?php
include __DIR__ . '/backend/data.php';

http_response_code(404);

$notFoundUrl = commerza_absolute_url('/404.php');
$homeUrl = commerza_absolute_url('/index.php');
$aboutUrl = commerza_absolute_url('/about.php');
$shopUrl = commerza_absolute_url('/products.php');
$logoUrl = commerza_absolute_url('/frontend/assets/images/logo/commerza-logo.webp');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, follow" />
    <meta name="description" content="The page you requested could not be found. Explore Commerza's premium shopping experience from here." />
    <meta property="og:title" content="404 Not Found | Commerza" />
    <meta property="og:description" content="That link is broken, but your next premium find is one click away." />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?= htmlspecialchars($notFoundUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:image" content="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <title>404 Not Found | Commerza</title>
    <link rel="canonical" href="<?= htmlspecialchars($notFoundUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0b10;
            --panel: #121522;
            --accent: #ff5a1f;
            --accent-soft: #ffbf47;
            --text: #f5f5f7;
            --muted: #b8bdd1;
            --stroke: rgba(255, 255, 255, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--text);
            background:
                radial-gradient(80rem 50rem at -10% 20%, rgba(255, 90, 31, 0.22), transparent 60%),
                radial-gradient(65rem 42rem at 110% 75%, rgba(255, 191, 71, 0.14), transparent 58%),
                linear-gradient(160deg, #0b0f1a 0%, #08090d 55%, #050506 100%);
            overflow-x: hidden;
        }

        .noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 0.6px, transparent 0.6px);
            background-size: 3px 3px;
            opacity: 0.18;
            mix-blend-mode: soft-light;
            z-index: 0;
        }

        .scene {
            position: relative;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            z-index: 1;
        }

        .card-404 {
            width: min(980px, 100%);
            background: linear-gradient(145deg, rgba(23, 29, 49, 0.86), rgba(12, 14, 23, 0.92));
            border: 1px solid var(--stroke);
            border-radius: 26px;
            overflow: hidden;
            box-shadow:
                0 24px 90px rgba(0, 0, 0, 0.55),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(6px);
            animation: cardIn 700ms ease-out;
        }

        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .left-pane {
            position: relative;
            padding: 44px 36px;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            background:
                linear-gradient(170deg, rgba(255, 90, 31, 0.18), rgba(255, 191, 71, 0.02) 48%, rgba(0, 0, 0, 0) 100%);
        }

        .logo-badge {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(0, 0, 0, 0.28);
            display: grid;
            place-items: center;
            margin-bottom: 18px;
        }

        .logo-badge img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, 0.4));
        }

        .code {
            font-family: 'Montserrat', sans-serif;
            font-size: clamp(62px, 10vw, 122px);
            line-height: 0.9;
            font-weight: 900;
            letter-spacing: 2px;
            margin: 0;
            color: transparent;
            -webkit-text-stroke: 2px rgba(255, 255, 255, 0.9);
            text-shadow: 0 0 20px rgba(255, 90, 31, 0.24);
        }

        .code-glow {
            color: var(--accent);
            margin-left: 5px;
            text-shadow:
                0 0 16px rgba(255, 90, 31, 0.52),
                0 0 32px rgba(255, 90, 31, 0.24);
        }

        .lead-title {
            margin: 14px 0 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: clamp(22px, 4vw, 32px);
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: 0.6px;
        }

        .lead-copy {
            color: var(--muted);
            margin: 0;
            font-size: 15px;
            line-height: 1.7;
            max-width: 420px;
        }

        .status-row {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffd3a4;
            font-size: 12px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            border: 1px solid rgba(255, 191, 71, 0.45);
            border-radius: 999px;
            padding: 6px 12px;
            background: rgba(255, 191, 71, 0.08);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent-soft);
            box-shadow: 0 0 0 0 rgba(255, 191, 71, 0.55);
            animation: pulse 1.6s ease-out infinite;
        }

        @keyframes pulse {
            to {
                box-shadow: 0 0 0 10px rgba(255, 191, 71, 0);
            }
        }

        .right-pane {
            padding: 38px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 14px;
        }

        .route-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: #f9f2dd;
            margin: 0 0 4px;
        }

        .route-card {
            border: 1px solid rgba(255, 255, 255, 0.11);
            border-radius: 15px;
            padding: 16px 14px;
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            color: var(--text);
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
            animation: staggerIn 420ms ease both;
        }

        .route-card:nth-child(2) {
            animation-delay: 100ms;
        }

        .route-card:nth-child(3) {
            animation-delay: 170ms;
        }

        .route-card:nth-child(4) {
            animation-delay: 240ms;
        }

        @keyframes staggerIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .route-card:hover,
        .route-card:focus-visible {
            transform: translateY(-2px);
            border-color: rgba(255, 90, 31, 0.7);
            background: rgba(255, 90, 31, 0.08);
            color: #fff;
            outline: none;
        }

        .route-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 600;
        }

        .route-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.06);
            color: #ffdcb9;
            font-size: 14px;
        }

        .route-arrow {
            color: #ffae70;
            font-size: 16px;
        }

        .footer-note {
            margin-top: 8px;
            color: #9da4bd;
            font-size: 12px;
            line-height: 1.5;
        }

        .footer-note code {
            color: #fff;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 6px;
            padding: 1px 6px;
        }

        @media (max-width: 920px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .left-pane {
                border-right: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }
        }

        @media (max-width: 540px) {
            .scene {
                padding: 14px;
            }

            .left-pane,
            .right-pane {
                padding: 22px 18px;
            }

            .lead-copy {
                font-size: 14px;
            }

            .route-label {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="noise" aria-hidden="true"></div>

    <main class="scene">
        <section class="card-404" aria-labelledby="notFoundTitle">
            <div class="grid">
                <div class="left-pane">
                    <div class="logo-badge" aria-hidden="true">
                        <img src="frontend/assets/images/logo/commerza-logo.webp" alt="" loading="lazy" />
                    </div>

                    <p class="code" aria-label="404">4<span class="code-glow">0</span>4</p>
                    <h1 class="lead-title" id="notFoundTitle">The link is broken, not your shopping mood.</h1>
                    <p class="lead-copy">
                        The page you requested moved, expired, or never existed. Jump back into Commerza and continue browsing premium pieces without losing momentum.
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
                            About Commerza
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
</body>

</html>