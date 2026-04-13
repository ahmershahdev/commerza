<?php
require_once __DIR__ . '/../backend/auth/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    exit('Service unavailable.');
}

$adminUser = admin_require_login($con);
$adminCsrfToken = admin_generate_csrf_token();
$adminCssVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();
$adminJsVersion = @filemtime(__DIR__ . '/assets/js/modules/panel/init/document-ready.js') ?: time();
$adminSubAdminsJsVersion = @filemtime(__DIR__ . '/assets/js/modules/panel/sub-admins.js') ?: $adminJsVersion;
$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$adminPanelCanonicalUrl = admin_public_url('/admin-panel');
$adminOgImageUrl = admin_public_url('/frontend/assets/images/logo/commerza_logo.svg');
$adminDisplayNameRaw = trim((string)($adminUser['full_name'] ?? 'Admin User'));
if ($adminDisplayNameRaw === '') {
    $adminDisplayNameRaw = 'Admin User';
}
$adminDisplayName = $adminDisplayNameRaw;
if ($adminDisplayNameRaw !== '') {
    $nameParts = preg_split('/\s+/', $adminDisplayNameRaw) ?: [];
    $normalizedNameParts = [];
    foreach ($nameParts as $part) {
        $token = (string)$part;
        if (preg_match('/^[A-Z]{3,}$/', $token) === 1) {
            $normalizedNameParts[] = ucfirst(strtolower($token));
            continue;
        }

        $normalizedNameParts[] = $token;
    }

    $adminDisplayName = trim(implode(' ', $normalizedNameParts));
    if ($adminDisplayName === '') {
        $adminDisplayName = 'Admin User';
    }
}
$adminDisplayEmail = strtolower(trim((string)($adminUser['email'] ?? '')));
$adminRoleKey = admin_normalize_role((string)($adminUser['role'] ?? 'admin'));
$adminRoleLabel = admin_role_label($adminRoleKey);
$adminPermissions = admin_effective_permissions($adminUser);
$adminHiddenTabs = admin_hidden_tabs($adminUser);
$adminPermissionCatalog = admin_permissions_payload();
$adminTabCatalog = admin_tabs_payload();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($adminFrontendBaseHref, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="admin-csrf-token" content="<?= htmlspecialchars($adminCsrfToken) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($adminPanelCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="Admin Panel | Commerza">
    <meta property="og:description" content="Commerza admin dashboard for operations, analytics, and security monitoring.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($adminPanelCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <title>Admin Panel | Commerza</title>
    <link rel="icon" href="../../frontend/assets/images/favicon/commerza-watches-icon.ico">
    <link id="bootstrapCssCdn" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"
        onerror="this.onerror=null;this.href='../../frontend/assets/vendor/bootstrap/bootstrap.min.css'">
    <link id="bootstrapIconsCdn" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        onerror="this.onerror=null;this.href='../../frontend/assets/vendor/bootstrap-icons/bootstrap-icons.min.css'">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)$adminCssVersion ?>">
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/core/admin-config.js"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/core/admin-theme-manager.js"></script>
</head>

<body class="dark-theme">
    <div class="admin-backdrop" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-hidden="true"></div>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark admin-sidebar collapse">
                <div class="d-md-none text-end pe-3 pt-3">
                    <button type="button" class="btn-close btn-close-white" data-bs-toggle="collapse"
                        data-bs-target="#sidebarMenu" aria-label="Close menu"></button>
                </div>
                <div class="d-flex flex-column h-100">
                    <div class="pt-4">
                        <div class="text-center mb-4 pb-3 border-bottom border-secondary">
                            <a class="navbar-brand d-inline-flex flex-column align-items-center text-decoration-none">
                                <img src="../../frontend/assets/images/logo/commerza_logo.svg" alt="Commerza Logo" loading="lazy"
                                    class="navbar-logo mb-2">
                                <span class="brand-text d-block">COMMERZA</span>
                            </a>
                            <p class="text-orange mt-3 mb-0 fw-bold">Admin Panel</p>
                        </div>


                        <ul class="nav nav-pills flex-column px-2" id="sidebarNav">
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link active rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="dashboard-tab" data-bs-toggle="pill" data-bs-target="#dashboardSection"
                                    type="button" aria-controls="dashboardSection" aria-selected="true">
                                    <i class="bi bi-speedometer2 me-2 fs-5"></i>
                                    <span>Dashboard</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="products-tab" data-bs-toggle="pill" data-bs-target="#productsSection"
                                    type="button" aria-controls="productsSection" aria-selected="false">
                                    <i class="bi bi-box-seam me-2 fs-5"></i>
                                    <span>Products</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="product-trash-tab" data-bs-toggle="pill" data-bs-target="#productTrashSection"
                                    type="button" aria-controls="productTrashSection" aria-selected="false">
                                    <i class="bi bi-trash3 me-2 fs-5"></i>
                                    <span>Product Trash</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center justify-content-between py-2 px-3 w-100 text-start border-0"
                                    id="orders-tab" data-bs-toggle="pill" data-bs-target="#ordersSection" type="button"
                                    aria-controls="ordersSection" aria-selected="false">
                                    <span>
                                        <i class="bi bi-cart me-2 fs-5"></i>
                                        <span>Orders</span>
                                    </span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="customers-tab" data-bs-toggle="pill" data-bs-target="#customersSection"
                                    type="button" aria-controls="customersSection" aria-selected="false">
                                    <i class="bi bi-people me-2 fs-5"></i>
                                    <span>Customers</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="sub-admins-tab" data-bs-toggle="pill" data-bs-target="#subAdminsSection"
                                    type="button" aria-controls="subAdminsSection" aria-selected="false">
                                    <i class="bi bi-person-gear me-2 fs-5"></i>
                                    <span>Sub Admins</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="coupons-tab" data-bs-toggle="pill" data-bs-target="#couponsSection"
                                    type="button" aria-controls="couponsSection" aria-selected="false">
                                    <i class="bi bi-ticket-perforated me-2 fs-5"></i>
                                    <span>Coupons</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="reviews-tab" data-bs-toggle="pill" data-bs-target="#reviewsSection"
                                    type="button" aria-controls="reviewsSection" aria-selected="false">
                                    <i class="bi bi-chat-left-text me-2 fs-5"></i>
                                    <span>Reviews</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="email-tab" data-bs-toggle="pill" data-bs-target="#emailSection"
                                    type="button" aria-controls="emailSection" aria-selected="false">
                                    <i class="bi bi-envelope-paper me-2 fs-5"></i>
                                    <span>Email Center</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="analytics-tab" data-bs-toggle="pill" data-bs-target="#analyticsSection"
                                    type="button" aria-controls="analyticsSection" aria-selected="false">
                                    <i class="bi bi-bar-chart me-2 fs-5"></i>
                                    <span>Analytics</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="website-tab" data-bs-toggle="pill" data-bs-target="#websiteSection"
                                    type="button" aria-controls="websiteSection" aria-selected="false">
                                    <i class="bi bi-globe2 me-2 fs-5"></i>
                                    <span>Website</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="security-events-tab" data-bs-toggle="pill" data-bs-target="#securityEventsSection"
                                    type="button" aria-controls="securityEventsSection" aria-selected="false">
                                    <i class="bi bi-shield-check me-2 fs-5"></i>
                                    <span>Security Events</span>
                                </button>
                            </li>
                            <li class="nav-item mb-1">
                                <button
                                    class="nav-link rounded-2 d-flex align-items-center py-2 px-3 w-100 text-start border-0"
                                    id="homepage-tab" data-bs-toggle="pill" data-bs-target="#homepageSection"
                                    type="button" aria-controls="homepageSection" aria-selected="false">
                                    <i class="bi bi-house-door me-2 fs-5"></i>
                                    <span>Homepage</span>
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-auto pb-4">
                        <div class="border-top border-secondary pt-4 px-3">
                            <div class="text-center mb-2">
                                <img src="https://ui-avatars.com/api/?name=Admin+User&background=ff6600&color=000"
                                    alt="Admin" class="rounded-circle border border-3 border-orange mb-3" width="60"
                                    height="60">
                                <p class="mb-1 fw-bold text-light" id="adminSidebarName"><?= htmlspecialchars($adminDisplayName, ENT_QUOTES, 'UTF-8') ?></p>
                                <small class="text-secondary d-block mb-2" id="adminSidebarEmail"><?= htmlspecialchars($adminDisplayEmail, ENT_QUOTES, 'UTF-8') ?></small>
                                <small class="text-white d-block" id="adminSidebarRole"><?= htmlspecialchars($adminRoleLabel, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-0 d-flex flex-column min-vh-100">
                <div
                    class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4 px-4 border-bottom border-orange border-2 admin-topbar">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-orange d-md-none me-3 rounded-2" type="button" data-bs-toggle="collapse"
                            data-bs-target=".admin-sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu">
                            <i class="bi bi-list fs-5"></i>
                        </button>
                        <div class="d-flex flex-column">
                            <h1 class="h2 mb-1 fw-bold text-light" id="pageTitle">Dashboard</h1>
                            <nav aria-label="Breadcrumb" id="adminBreadcrumbNav">
                                <ol class="breadcrumb admin-breadcrumb mb-0">
                                    <li class="breadcrumb-item"><span>Admin Panel</span></li>
                                    <li class="breadcrumb-item active" id="adminBreadcrumbCurrent" aria-current="page">Dashboard</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0 gap-2 admin-action-toolbar" id="actionButtons">

                        <div class="btn-group admin-export-group">
                            <button type="button" class="btn btn-sm btn-outline-orange dropdown-toggle" id="exportBtn" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Export options">
                                <i class="bi bi-download me-1"></i>
                                <span class="d-none d-sm-inline">Export</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary admin-header-dropdown">
                                <li><a class="dropdown-item text-light" href="#" onclick="exportProductsData(); return false;"><i class="bi bi-filetype-json me-2"></i>Export as JSON</a></li>
                                <li><a class="dropdown-item text-light" href="#" onclick="exportProductsAsCSV(); return false;"><i class="bi bi-filetype-csv me-2"></i>Export as CSV</a></li>
                            </ul>
                            <button type="button" class="btn btn-sm btn-orange" id="addBtn" style="display:none;">
                                <i class="bi bi-plus-circle me-1"></i>
                                <span class="d-none d-sm-inline">Add Product</span>
                            </button>
                        </div>
                        <div class="dropdown admin-notification-group">
                            <button class="btn btn-sm btn-outline-orange dropdown-toggle position-relative"
                                type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Notifications">
                                <i class="bi bi-bell"></i>
                                <span id="notificationCount"
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
                                    0
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary admin-header-dropdown admin-notification-menu" id="notificationList">
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="tab-content px-4 flex-grow-1" id="contentSections">
                    <div class="tab-pane fade show active" id="dashboardSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h4 mb-2 text-light">Welcome back!</h2>
                                <p class="mb-0 text-secondary">Quick start: create a section, add products, then check orders daily.</p>
                            </div>
                            <div class="helper-actions">
                                <button class="btn btn-orange" type="button" onclick="document.getElementById('products-tab')?.click();">
                                    <i class="bi bi-box-seam me-2"></i>Manage Products
                                </button>
                                <button class="btn btn-outline-orange" type="button" onclick="document.getElementById('orders-tab')?.click();">
                                    <i class="bi bi-cart me-2"></i>View Orders
                                </button>
                                <button class="btn btn-outline-orange" type="button" onclick="document.getElementById('website-tab')?.click();">
                                    <i class="bi bi-globe2 me-2"></i>Website Settings
                                </button>
                            </div>
                        </div>
                        <section class="dashboard-spotlight mb-4" aria-label="Dashboard mission pulse">
                            <div class="dashboard-spotlight-grid">
                                <div class="dashboard-spotlight-copy">
                                    <p class="dashboard-spotlight-kicker">Mission Pulse</p>
                                    <h2 class="dashboard-spotlight-title">Command Center Is Live</h2>
                                    <p class="dashboard-spotlight-text">Track revenue, monitor fulfillment, and move from alerts to action without switching context.</p>
                                    <div class="dashboard-spotlight-strips" aria-hidden="true">
                                        <span>Realtime Order Insights</span>
                                        <span>Security Event Monitoring</span>
                                        <span>Faster Catalog Decisions</span>
                                    </div>
                                </div>
                                <div class="dashboard-spotlight-pulse" aria-label="Operational status indicators">
                                    <div class="dashboard-pulse-chip">
                                        <span class="pulse-dot pulse-dot-live"></span>
                                        <div>
                                            <strong>Live Queue</strong>
                                            <small>Orders and events update in place</small>
                                        </div>
                                    </div>
                                    <div class="dashboard-pulse-chip">
                                        <span class="pulse-dot pulse-dot-guard"></span>
                                        <div>
                                            <strong>Security Guard</strong>
                                            <small>Rate-limit and incident triage active</small>
                                        </div>
                                    </div>
                                    <div class="dashboard-pulse-chip">
                                        <span class="pulse-dot pulse-dot-growth"></span>
                                        <div>
                                            <strong>Growth Radar</strong>
                                            <small>Revenue and conversion visibility</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-4">
                                <div class="action-tile">
                                    <div class="action-icon"><i class="bi bi-plus-circle"></i></div>
                                    <div>
                                        <p class="h6 mb-1 fw-semibold text-light">Add a product</p>
                                        <p class="mb-0 text-secondary">Pick a section and fill in the form.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="action-tile">
                                    <div class="action-icon"><i class="bi bi-layers"></i></div>
                                    <div>
                                        <p class="h6 mb-1 fw-semibold text-light">Create a section</p>
                                        <p class="mb-0 text-secondary">Use simple names like "Featured" or "New".</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="action-tile">
                                    <div class="action-icon"><i class="bi bi-megaphone"></i></div>
                                    <div>
                                        <p class="h6 mb-1 fw-semibold text-light">Update homepage</p>
                                        <p class="mb-0 text-secondary">Change slider images and text anytime.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Total
                                                    Revenue</p>
                                                <h3 class="fw-bold mb-0 text-light" id="totalRevenueValue">PKR 0</h3>
                                            </div>
                                            <div class="icon-bg bg-orange rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-currency-dollar text-dark fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-success">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="totalRevenueInfo">Delivered Orders (Last 30 Days)</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Total
                                                    Orders
                                                </p>
                                                <h3 class="fw-bold mb-0 text-light" id="totalOrdersValue">0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-cart text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-success">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="totalOrdersInfo">Last 30 Days</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Products
                                                </p>
                                                <h3 class="fw-bold mb-0 text-light" id="totalProductsValue">0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-box-seam text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-warning" style="display: none;">
                                            <i class="bi bi-exclamation-triangle"></i> <span
                                                class="fw-semibold" id="lowStockCount">0</span>
                                            low stock
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">
                                                    Customers
                                                </p>
                                                <h3 class="fw-bold mb-0 text-light" id="totalCustomersValue">0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-people text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-success">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="totalCustomersInfo">Last 30 Days</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Refund Status</p>
                                                <h3 class="fw-bold mb-0 text-light" id="dashboardRefundSummaryValue">0 / 0 / 0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-cash-coin text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-info">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="dashboardRefundSummaryInfo">Pending / Accepted / Rejected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Avg Order Value</p>
                                                <h3 class="fw-bold mb-0 text-light" id="avgOrderValueValue">PKR 0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-graph-up-arrow text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-success">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="avgOrderValueInfo">Delivered Revenue / Last 30 Days Orders</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Returning Customers</p>
                                                <h3 class="fw-bold mb-0 text-light" id="returningCustomerRateValue">0%</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-arrow-repeat text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-info">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="returningCustomerRateInfo">Placed more than one order in 30 days</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <p class="text-secondary small mb-2 text-uppercase fw-semibold">Pending Fulfillment</p>
                                                <h3 class="fw-bold mb-0 text-light" id="pendingFulfillmentValue">0</h3>
                                            </div>
                                            <div class="icon-bg bg-secondary rounded-3 d-flex align-items-center justify-content-center"
                                                style="width: 48px; height: 48px;">
                                                <i class="bi bi-hourglass-split text-light fs-4"></i>
                                            </div>
                                        </div>
                                        <p class="mb-0 small text-warning">
                                            <i class="bi bi-info-circle"></i> <span class="fw-semibold" id="pendingFulfillmentInfo">Pending + Confirmed + Processing (Last 30 Days)</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Recent Orders</h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">ID</th>
                                                        <th class="py-3 text-orange fw-semibold">Customer</th>
                                                        <th class="py-3 text-orange fw-semibold">Date</th>
                                                        <th class="py-3 text-orange fw-semibold">Amount</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-bottom border-secondary">
                                                        <td class="ps-4 py-3 fw-semibold text-light"></td>
                                                        <td class="py-3 text-light"></td>
                                                        <td class="py-3 text-secondary small"></td>
                                                        <td class="py-3 text-light fw-semibold"></td>
                                                        <td class="pe-4 py-3">
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>

                    <div class="tab-pane fade" id="productsSection">
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="helper-banner mb-4">
                                    <div>
                                        <h2 class="h5 mb-2 text-light">Products setup checklist</h2>
                                        <p class="mb-0 text-secondary">Step 1: Create a section. Step 2: Add products. Step 3: Set Product Code, Warranty, and Dispatch info for each product.</p>
                                    </div>
                                    <span class="step-chip">Tip: Use unique product codes and clear service details so support teams can resolve issues faster.</span>
                                </div>
                                <div class="row g-3 mb-4" id="productsWorkspaceSummary">
                                    <div class="col-12 col-md-4">
                                        <div class="product-workspace-card">
                                            <p class="product-workspace-kicker mb-1">Active Products</p>
                                            <h3 class="product-workspace-value mb-1" id="productWorkspaceProducts">0</h3>
                                            <small class="field-hint">Total products currently visible in the catalog.</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <div class="product-workspace-card">
                                            <p class="product-workspace-kicker mb-1">Sections</p>
                                            <h3 class="product-workspace-value mb-1" id="productWorkspaceSections">0</h3>
                                            <small class="field-hint">Categories/sections where products are grouped.</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <div class="product-workspace-card product-workspace-trash">
                                            <p class="product-workspace-kicker mb-1">Trash Queue</p>
                                            <h3 class="product-workspace-value mb-1" id="productWorkspaceTrash">0</h3>
                                            <small class="field-hint">Deleted items can be restored for 7 days.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card admin-card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Bulk Product Upload (CSV / JSON)</h3>
                                        <span class="badge bg-secondary">Optional Step A</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <p class="text-secondary mb-3">Simple steps for non-technical admins: download sample CSV, fill each row (one product), then click import. Import replaces current catalog data.</p>
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-7">
                                                <label for="bulkProductsFile" class="form-label text-light">Upload Product File</label>
                                                <input type="file" class="form-control bg-secondary border-0 text-light" id="bulkProductsFile" accept=".csv,.json,text/csv,application/json">
                                                <small class="field-hint d-block mt-2">CSV columns: section_id, section_name, page, category, subcategory, name, description, image, video, product_code, warranty_info, dispatch_info, price, sale_price, stock, movement.</small>
                                            </div>
                                            <div class="col-12 col-lg-5 d-flex align-items-end gap-2 flex-wrap">
                                                <button class="btn btn-orange" id="bulkProductsImportBtn" type="button">
                                                    <i class="bi bi-upload me-1"></i>Import Products
                                                </button>
                                                <button class="btn btn-outline-orange" id="downloadSampleProductsCsvBtn" type="button">
                                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download Sample CSV
                                                </button>
                                                <div class="w-100 small text-secondary" id="bulkImportProgress">No import in progress.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3 d-flex gap-2 align-items-start">
                                    <div style="flex: 1;">
                                        <label class="form-label text-light fw-semibold mb-2 d-block">Filter by Section</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="sectionFilterBtn">
                                                All Sections
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="sectionFilterMenu"></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card admin-card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Manage Sections</h3>
                                        <span class="text-secondary small">Step 1 of 3: Create a section first, then add products</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <input type="hidden" id="sectionFormId">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4">
                                                <label for="sectionName" class="form-label text-light">Section Name</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sectionName" placeholder="Featured Collection">
                                                <small class="field-hint">Shown on the website.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="sectionId" class="form-label text-light">Section ID</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sectionId" placeholder="featured-collection">
                                                <small class="field-hint">Use lowercase and hyphens only.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="sectionPage" class="form-label text-light">Page</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sectionPage" placeholder="index.php">
                                                <small class="field-hint">Where the section appears.</small>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="sectionCategory" class="form-label text-light">Category</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sectionCategory" placeholder="Premium Watches & Accessories">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="sectionSubcategory" class="form-label text-light">Subcategory</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sectionSubcategory" placeholder="Luxury Timepieces">
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveSectionBtn">
                                                <i class="bi bi-plus-circle me-1"></i>Add Section
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetSectionBtn" type="button">Reset</button>
                                        </div>
                                        <div class="table-responsive mt-4">
                                            <table class="table table-dark table-hover align-middle mb-0" id="sectionsTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Section</th>
                                                        <th class="py-3 text-orange fw-semibold">Page</th>
                                                        <th class="py-3 text-orange fw-semibold">Category</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="card admin-card border-0 shadow-sm">
                                    <div
                                        class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Step 2 of 3: All Products (<span id="productCount">0</span>)</h3>
                                        <button class="btn btn-sm btn-orange" id="addNewProductBtn">
                                            <i class="bi bi-plus-circle me-1"></i>Add New Product
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="productsTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Image</th>
                                                        <th class="py-3 text-orange fw-semibold">Product Name</th>
                                                        <th class="py-3 text-orange fw-semibold">Section / Category</th>
                                                        <th class="py-3 text-orange fw-semibold">Price / Sale</th>
                                                        <th class="py-3 text-orange fw-semibold">Stock</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="productTrashSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Product Trash recovery</h2>
                                <p class="mb-0 text-secondary">Restore recently deleted products or permanently remove expired trash entries.</p>
                            </div>
                            <span class="step-chip">Tip: Restore first if unsure. Empty actions are permanent.</span>
                        </div>
                        <div class="card admin-card border-0 shadow-sm" id="productTrashCard">
                            <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="product-trash-title-wrap">
                                    <h3 class="h5 mb-0 fw-bold text-orange product-trash-title d-flex align-items-center gap-2 flex-wrap">
                                        <span>Product Trash</span>
                                        <span class="product-trash-count-chip" id="productTrashCountChip">
                                            <i class="bi bi-trash3-fill"></i>
                                            <span id="productTrashCount">0</span>
                                        </span>
                                    </h3>
                                    <small class="text-secondary fw-normal product-trash-subline">Items auto-purge after 7 days unless restored.</small>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-sm btn-outline-orange" id="refreshProductTrashBtn" type="button">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" id="emptyExpiredProductTrashBtn" type="button">
                                        <i class="bi bi-hourglass-split me-1"></i>Empty Expired
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" id="emptyAllProductTrashBtn" type="button">
                                        <i class="bi bi-trash3 me-1"></i>Empty All
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="product-trash-toolbar border-bottom border-secondary px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="product-trash-meta d-flex align-items-center gap-2 flex-wrap" aria-live="polite">
                                        <span class="badge rounded-pill text-bg-secondary" id="productTrashTotalBadge">Total: 0</span>
                                        <span class="badge rounded-pill text-bg-warning text-dark" id="productTrashExpiringBadge">Expiring &lt; 24h: 0</span>
                                        <span class="badge rounded-pill text-bg-danger" id="productTrashExpiredBadge">Expired: 0</span>
                                    </div>
                                    <small class="text-secondary">Restore keeps product data and media paths intact.</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0" id="productTrashTable">
                                        <thead class="border-bottom border-secondary">
                                            <tr>
                                                <th class="py-3 ps-4 text-orange fw-semibold">Product</th>
                                                <th class="py-3 text-orange fw-semibold">Section</th>
                                                <th class="py-3 text-orange fw-semibold">Deleted At</th>
                                                <th class="py-3 text-orange fw-semibold">Auto Purge</th>
                                                <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-secondary">Trash is empty.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="ordersSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Orders that need your attention</h2>
                                <p class="mb-0 text-secondary">Pending and Processing orders should be packed or shipped.</p>
                            </div>
                            <span class="step-chip">Tip: Mark Delivered only after the order reaches the customer.</span>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">All Orders</h3>
                                        <button class="btn btn-sm btn-outline-danger" id="bulkDeleteOrdersBtn" type="button">
                                            <i class="bi bi-trash me-1"></i>Delete Selected
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="ordersTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold"><input class="form-check-input" type="checkbox" id="ordersSelectAll"></th>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Order ID</th>
                                                        <th class="py-3 text-orange fw-semibold">Customer</th>
                                                        <th class="py-3 text-orange fw-semibold">Date</th>
                                                        <th class="py-3 text-orange fw-semibold">Amount</th>
                                                        <th class="py-3 text-orange fw-semibold">Payment</th>
                                                        <th class="py-3 text-orange fw-semibold">Status</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-bottom border-secondary">
                                                        <td colspan="8" class="text-center py-4 text-secondary">No orders found</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm" id="shippingRulesCard">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Shipping Rules</h3>
                                        <span class="text-secondary small">Used at checkout for all orders</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-12 col-md-5 col-lg-4">
                                                <label for="shippingFlatFeeInput" class="form-label text-light">Flat Shipping Fee (PKR)</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="shippingFlatFeeInput" min="0" step="0.01" placeholder="1000">
                                                <small class="field-hint d-block mt-1">Applied when free-shipping condition is not met.</small>
                                            </div>
                                            <div class="col-12 col-md-5 col-lg-4">
                                                <label for="freeShippingOverInput" class="form-label text-light">Free Shipping Over (PKR)</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="freeShippingOverInput" min="0" step="0.01" placeholder="500">
                                                <small class="field-hint d-block mt-1">Set 0 to disable threshold-based free shipping.</small>
                                            </div>
                                            <div class="col-12 col-md-2 col-lg-4 d-grid">
                                                <button class="btn btn-orange" id="saveShippingConfigBtn" type="button">
                                                    <i class="bi bi-truck me-1"></i>Save Shipping Rules
                                                </button>
                                                <small class="field-hint d-block mt-1">Apply Shipping Rules</small>
                                            </div>
                                        </div>
                                        <div class="text-secondary small mt-3" id="shippingRulesPreview">Current checkout rules will appear here.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Refund Requests</h3>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="refundTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Order</th>
                                                        <th class="py-3 text-orange fw-semibold">Customer</th>
                                                        <th class="py-3 text-orange fw-semibold">Requested</th>
                                                        <th class="py-3 text-orange fw-semibold">Status</th>
                                                        <th class="py-3 text-orange fw-semibold">Order Payment</th>
                                                        <th class="py-3 text-orange fw-semibold">Reason</th>
                                                        <th class="py-3 text-orange fw-semibold">Evidence</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-4 text-secondary">No refund requests yet.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="customersSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Customer list</h2>
                                <p class="mb-0 text-secondary">These are customers who placed orders. Search by name, email, or username and remove full profiles when required.</p>
                            </div>
                            <span class="step-chip">Tip: If an email looks wrong, update the order details.</span>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">All Customers</h3>
                                        <button class="btn btn-sm btn-outline-danger" id="bulkDeleteCustomersBtn" type="button">
                                            <i class="bi bi-person-x me-1"></i>Delete Selected
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="p-3 border-bottom border-secondary">
                                            <label for="customersSearchInput" class="form-label text-light mb-2">Search Customers</label>
                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="customersSearchInput" list="customersSearchSuggestions" placeholder="Type name, email, or username">
                                            <datalist id="customersSearchSuggestions"></datalist>
                                            <small class="field-hint d-block mt-2">Suggestions appear as you type. You can search by full name, email, username, or phone.</small>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="customersTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold"><input class="form-check-input" type="checkbox" id="customersSelectAll"></th>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Avatar</th>
                                                        <th class="py-3 text-orange fw-semibold">Name</th>
                                                        <th class="py-3 text-orange fw-semibold">Username</th>
                                                        <th class="py-3 text-orange fw-semibold">Email</th>
                                                        <th class="py-3 text-orange fw-semibold">Phone</th>
                                                        <th class="py-3 text-orange fw-semibold">Orders</th>
                                                        <th class="py-3 text-orange fw-semibold">Total Spent</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-bottom border-secondary">
                                                        <td colspan="9" class="text-center py-4 text-secondary">No customers found</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm" id="customerBlacklistCard">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Customer Blacklist</h3>
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <span class="badge bg-warning text-dark text-uppercase">Premium Risk Labels</span>
                                            <span class="text-secondary small">Blocked email/phone cannot be used for signup or profile updates</span>
                                        </div>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-12 col-md-4">
                                                <label for="blacklistEmailInput" class="form-label text-light">Email</label>
                                                <input type="email" class="form-control bg-secondary border-0 text-light" id="blacklistEmailInput" placeholder="user@example.com">
                                                <small class="field-hint d-block mt-1">Optional if phone is provided.</small>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label for="blacklistPhoneInput" class="form-label text-light">Phone</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="blacklistPhoneInput" placeholder="03123456789">
                                                <small class="field-hint d-block mt-1">11 to 15 digits.</small>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label for="blacklistReasonInput" class="form-label text-light">Reason</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="blacklistReasonInput" maxlength="255" placeholder="Fraud, abuse, chargeback, etc.">
                                            </div>
                                            <div class="col-12 col-md-2 d-grid">
                                                <button class="btn btn-outline-danger" id="addBlacklistBtn" type="button">
                                                    <i class="bi bi-slash-circle me-1"></i>Blacklist
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-items-end mt-1">
                                            <div class="col-12 col-md-4">
                                                <label for="whitelistEmailInput" class="form-label text-light">Whitelist by Email</label>
                                                <input type="email" class="form-control bg-secondary border-0 text-light" id="whitelistEmailInput" placeholder="user@example.com">
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label for="whitelistPhoneInput" class="form-label text-light">Whitelist by Phone</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="whitelistPhoneInput" placeholder="03123456789">
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <small class="field-hint d-block">Whitelist immediately removes active blacklist entries for this email/phone and restores signup, cart, wishlist, review, and checkout access.</small>
                                            </div>
                                            <div class="col-12 col-md-2 d-grid">
                                                <button class="btn btn-outline-success" id="whitelistContactBtn" type="button">
                                                    <i class="bi bi-shield-check me-1"></i>Whitelist
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row g-3 mt-2">
                                            <div class="col-12">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 rounded-3 border border-secondary-subtle">
                                                    <div>
                                                        <p class="mb-1 text-light fw-semibold">Blacklist Notice On Customer Account Page</p>
                                                        <small class="field-hint d-block">If hidden, blacklisted users are still blocked from ordering/review/refund/cart/wishlist, but the account banner is not shown to them.</small>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="form-check form-switch m-0">
                                                            <input class="form-check-input" type="checkbox" role="switch" id="blacklistUserNoticeToggle">
                                                            <label class="form-check-label text-light" for="blacklistUserNoticeToggle">Visible to user</label>
                                                        </div>
                                                        <button class="btn btn-sm btn-outline-orange" id="saveBlacklistNoticeToggleBtn" type="button">
                                                            <i class="bi bi-save2 me-1"></i>Save
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="field-hint d-block mt-3">Tip: Use customer row actions for quick Blacklist + Delete on abusive accounts.</small>
                                        <div class="table-responsive mt-3">
                                            <table class="table table-dark table-hover align-middle mb-0" id="blacklistTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Email</th>
                                                        <th class="py-3 text-orange fw-semibold">Phone</th>
                                                        <th class="py-3 text-orange fw-semibold">Reason / Label</th>
                                                        <th class="py-3 text-orange fw-semibold">Added</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4 text-secondary">No blacklisted contacts yet.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="subAdminsSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Sub Admin Access Studio</h2>
                                <p class="mb-0 text-secondary">Create mini admin accounts with role profiles, custom permissions, and selective tab visibility. Verification codes expire in 15 minutes and are one-time use.</p>
                            </div>
                            <span class="step-chip">Premium control: assign access by capability, not by guesswork.</span>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12 col-xl-7">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange" id="subAdminFormTitle">Create Sub Admin</h3>
                                        <span class="badge bg-warning text-dark">Role + Custom Matrix</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <form id="subAdminForm" novalidate>
                                            <input type="hidden" id="subAdminEditId" value="0">
                                            <div class="row g-3">
                                                <div class="col-12 col-md-6">
                                                    <label for="subAdminFullName" class="form-label text-light">Full Name</label>
                                                    <input type="text" class="form-control bg-secondary border-0 text-light" id="subAdminFullName" maxlength="100" placeholder="Ahmer Shah" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label for="subAdminEmail" class="form-label text-light">Email</label>
                                                    <input type="email" class="form-control bg-secondary border-0 text-light" id="subAdminEmail" maxlength="150" placeholder="subadmin@commerza.com" required>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label for="subAdminPhone" class="form-label text-light">Phone (Optional)</label>
                                                    <input type="text" class="form-control bg-secondary border-0 text-light" id="subAdminPhone" maxlength="15" placeholder="03123456789">
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label for="subAdminPassword" class="form-label text-light">Password</label>
                                                    <input type="password" class="form-control bg-secondary border-0 text-light" id="subAdminPassword" maxlength="64" placeholder="Set secure password" autocomplete="new-password" required>
                                                    <small class="field-hint d-block mt-1">Use a strong password that meets policy requirements.</small>
                                                </div>
                                            </div>

                                            <hr class="border-secondary my-4">

                                            <label class="form-label text-light mb-2">Role Profile</label>
                                            <div class="sub-admin-role-grid" id="subAdminRoleCards"></div>
                                            <input type="hidden" id="subAdminRole" value="operations_manager">
                                            <small class="field-hint d-block mt-2" id="subAdminRoleHelp">Choose a role to auto-fill recommended permissions.</small>

                                            <div class="sub-admin-custom-access mt-4" id="subAdminCustomAccessWrap">
                                                <div class="row g-4">
                                                    <div class="col-12 col-lg-7">
                                                        <h4 class="h6 text-light mb-2">Custom Permissions</h4>
                                                        <p class="field-hint mb-2">Select exactly what this sub admin can do.</p>
                                                        <div class="sub-admin-permission-grid" id="subAdminPermissionGrid"></div>
                                                    </div>
                                                    <div class="col-12 col-lg-5">
                                                        <h4 class="h6 text-light mb-2">Hide Tab Panes</h4>
                                                        <p class="field-hint mb-2">Hide selected tabs from this sub admin UI.</p>
                                                        <div class="sub-admin-hidden-tabs" id="subAdminHiddenTabsGrid"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="sub-admin-custom-access mt-4" id="subAdminVerificationWrap" hidden>
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                    <h4 class="h6 text-light mb-0">Email Verification</h4>
                                                    <button type="button" class="btn btn-sm btn-outline-info" id="subAdminResendVerifyFromFormBtn">
                                                        <i class="bi bi-envelope-arrow-up me-1"></i>Resend Code
                                                    </button>
                                                </div>
                                                <p class="field-hint mb-2" id="subAdminVerificationHint">Enter the 6-digit code sent to this sub-admin email.</p>
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-12 col-md-8">
                                                        <label for="subAdminVerificationCode" class="form-label text-light mb-1">Verification Code</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="subAdminVerificationCode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6" placeholder="123456" autocomplete="one-time-code">
                                                    </div>
                                                    <div class="col-12 col-md-4 d-grid">
                                                        <button type="button" class="btn btn-outline-orange" id="verifySubAdminCodeBtn">
                                                            <i class="bi bi-shield-check me-1"></i>Verify Code
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2 mt-4">
                                                <button type="submit" class="btn btn-orange" id="saveSubAdminBtn">
                                                    <i class="bi bi-person-plus me-1"></i>Create Sub Admin
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" id="resetSubAdminBtn">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-xl-5">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Sub Admin Accounts</h3>
                                        <button class="btn btn-sm btn-outline-orange" id="refreshSubAdminsBtn" type="button">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="subAdminsTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Admin</th>
                                                        <th class="py-3 text-orange fw-semibold">Role</th>
                                                        <th class="py-3 text-orange fw-semibold">Status</th>
                                                        <th class="py-3 text-orange fw-semibold">Verification</th>
                                                        <th class="py-3 text-orange fw-semibold">Last Login</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4 text-secondary">No sub admin accounts found.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="couponsSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Coupon Campaign Studio</h2>
                                <p class="mb-0 text-secondary">Plan, launch, and monitor coupon campaigns with clearer guardrails and one-click actions.</p>
                            </div>
                            <span class="step-chip">Premium tip: Pair short codes with strict limits to reduce support friction.</span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="coupon-overview-card">
                                    <p class="coupon-overview-kicker mb-1">Total Coupons</p>
                                    <h3 class="coupon-overview-value mb-0" id="couponStatsTotal">0</h3>
                                    <small class="field-hint">All created codes</small>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="coupon-overview-card coupon-overview-active">
                                    <p class="coupon-overview-kicker mb-1">Currently Active</p>
                                    <h3 class="coupon-overview-value mb-0" id="couponStatsActive">0</h3>
                                    <small class="field-hint">Live and not expired</small>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="coupon-overview-card coupon-overview-used">
                                    <p class="coupon-overview-kicker mb-1">Total Redemptions</p>
                                    <h3 class="coupon-overview-value mb-0" id="couponStatsUsed">0</h3>
                                    <small class="field-hint">Times customers used offers</small>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="coupon-overview-card coupon-overview-focus">
                                    <p class="coupon-overview-kicker mb-1">Showing In Table</p>
                                    <h3 class="coupon-overview-value mb-0" id="couponStatsShowing">0</h3>
                                    <small class="field-hint">After search and filters</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12 col-xl-8">
                                <div class="card admin-card border-0 shadow-sm coupon-studio-card h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Campaign Quick Actions</h3>
                                        <span class="badge bg-warning text-dark">Preset builder + live guidance</span>
                                    </div>
                                    <div class="card-body p-4 coupon-studio-panel">
                                        <p class="field-hint mb-3">Use a preset, generate a code, then tune limits before saving.</p>
                                        <div class="coupon-preset-grid mb-3" id="couponPresetQuickActions">
                                            <button class="btn btn-outline-orange coupon-preset-btn" type="button" data-coupon-preset="flash10">Flash 10% (24h)</button>
                                            <button class="btn btn-outline-orange coupon-preset-btn" type="button" data-coupon-preset="welcome250">Welcome PKR 250</button>
                                            <button class="btn btn-outline-orange coupon-preset-btn" type="button" data-coupon-preset="vip15">VIP 15% Capped</button>
                                        </div>
                                        <div class="row g-3 align-items-end">
                                            <div class="col-12 col-md-6">
                                                <label for="couponCodeSeed" class="form-label text-light">Code Seed</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="couponCodeSeed" placeholder="Weekend Drop, Eid Sale, New User">
                                                <small class="field-hint d-block mt-1">Optional helper text used for clean code generation.</small>
                                            </div>
                                            <div class="col-12 col-md-6 d-flex flex-wrap gap-2">
                                                <button class="btn btn-outline-light" id="couponGenerateCodeBtn" type="button">
                                                    <i class="bi bi-magic me-1"></i>Generate Code
                                                </button>
                                                <button class="btn btn-outline-secondary" id="couponCopyCodeBtn" type="button">
                                                    <i class="bi bi-clipboard me-1"></i>Copy Code
                                                </button>
                                                <button class="btn btn-outline-orange" id="couponPrefillEmailBtn" type="button">
                                                    <i class="bi bi-envelope-check me-1"></i>Prefill Email Copy
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <div class="card admin-card border-0 shadow-sm coupon-preview-card h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Live Coupon Preview</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="coupon-preview-kicker mb-1">Current Build</div>
                                        <h4 class="coupon-preview-code mb-2" id="couponPreviewCode">NO-CODE</h4>
                                        <div class="coupon-preview-meta mb-3" id="couponPreviewType">Fixed discount</div>
                                        <ul class="coupon-preview-list mb-3">
                                            <li><span>Value</span><strong id="couponPreviewValue">PKR 0.00</strong></li>
                                            <li><span>Min Order</span><strong id="couponPreviewMinOrder">PKR 0.00</strong></li>
                                            <li><span>Limits</span><strong id="couponPreviewLimits">Unlimited</strong></li>
                                            <li><span>Expiry</span><strong id="couponPreviewExpiry">No expiry</strong></li>
                                            <li><span>Status</span><strong id="couponPreviewStatus">Active</strong></li>
                                        </ul>
                                        <div class="coupon-preview-sim" id="couponPreviewSimulation">Sample: On PKR 5,000 cart, discount is PKR 0.00.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12 col-xl-6">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Create / Update Coupon</h3>
                                    </div>
                                    <div class="card-body p-4 coupon-form-panel">
                                        <input type="hidden" id="couponId">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label for="couponCode" class="form-label text-light">Coupon Code</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="couponCode" placeholder="SAVE10" maxlength="50">
                                                <small class="field-hint d-block mt-1">Unique code customers enter at checkout.</small>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="couponTitle" class="form-label text-light">Title</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="couponTitle" placeholder="Weekend Offer" maxlength="120">
                                                <small class="field-hint d-block mt-1">Internal display name shown in admin only.</small>
                                            </div>
                                            <div class="col-12">
                                                <label for="couponDescription" class="form-label text-light">Description</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="couponDescription" placeholder="Audience or campaign note" maxlength="255">
                                                <small class="field-hint d-block mt-1">Optional note for your team (campaign goal, audience, etc).</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponDiscountType" class="form-label text-light">Discount Type</label>
                                                <div class="dropdown">
                                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="couponDiscountTypeBtn">
                                                        Fixed PKR
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="couponDiscountTypeMenu">
                                                        <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="couponDiscountType" data-value="fixed" data-label="Fixed PKR">Fixed PKR</a></li>
                                                        <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="couponDiscountType" data-value="percent" data-label="Percent %">Percent %</a></li>
                                                    </ul>
                                                </div>
                                                <input type="hidden" id="couponDiscountType" value="fixed">
                                                <small class="field-hint d-block mt-1">Choose fixed amount or percentage-based discount.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponDiscountValue" class="form-label text-light">Discount Value</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="couponDiscountValue" min="0.01" step="0.01" placeholder="250">
                                                <small class="field-hint d-block mt-1">For percent type, enter number like 10 for 10%.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponMinOrder" class="form-label text-light">Min Order (PKR)</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="couponMinOrder" min="0" step="0.01" placeholder="0">
                                                <small class="field-hint d-block mt-1">Minimum cart amount required before coupon applies.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponMaxDiscount" class="form-label text-light">Max Discount (PKR)</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="couponMaxDiscount" min="0" step="0.01" placeholder="Percent coupons only">
                                                <small class="field-hint d-block mt-1">Optional safety cap for percentage discounts.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponUsageLimit" class="form-label text-light">Usage Limit</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="couponUsageLimit" min="0" step="1" placeholder="0 for unlimited">
                                                <small class="field-hint d-block mt-1">Total times this coupon can be redeemed.</small>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label for="couponPerUserLimit" class="form-label text-light">Per-User Limit</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="couponPerUserLimit" min="0" step="1" placeholder="0 for unlimited">
                                                <small class="field-hint d-block mt-1">How many times one customer can use this code.</small>
                                            </div>
                                            <div class="col-12 col-md-8">
                                                <label for="couponExpiresAt" class="form-label text-light">Expires At</label>
                                                <input type="datetime-local" class="form-control bg-secondary border-0 text-light" id="couponExpiresAt">
                                                <small class="field-hint d-block mt-1">Leave empty if the coupon should stay available until disabled.</small>
                                            </div>
                                            <div class="col-12 col-md-4 d-flex align-items-end">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="couponIsActive" checked>
                                                    <label class="form-check-label text-light" for="couponIsActive">Active</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveCouponBtn" type="button">
                                                <i class="bi bi-save2 me-1"></i>Save Coupon
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetCouponBtn" type="button">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-6">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Send Coupon Email</h3>
                                    </div>
                                    <div class="card-body p-4 coupon-form-panel">
                                        <div class="mb-3">
                                            <label for="couponEmailCouponId" class="form-label text-light">Select Coupon</label>
                                            <div class="dropdown">
                                                <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="couponEmailCouponIdBtn">
                                                    Select a coupon
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="couponEmailCouponIdMenu">
                                                    <li><span class="dropdown-item text-secondary">No coupons available</span></li>
                                                </ul>
                                            </div>
                                            <input type="hidden" id="couponEmailCouponId" value="">
                                            <small class="field-hint d-block mt-1">Pick which coupon details to insert into the message.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="couponEmailRecipients" class="form-label text-light">Recipients</label>
                                            <textarea class="form-control bg-secondary border-0 text-light" id="couponEmailRecipients" rows="3" placeholder="Add emails separated by comma, space, or new line"></textarea>
                                            <small class="field-hint d-block mt-1">You can paste multiple emails separated by comma, spaces, or new lines.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="couponEmailSubject" class="form-label text-light">Subject</label>
                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="couponEmailSubject" placeholder="Your Commerza coupon is here">
                                            <small class="field-hint d-block mt-1">Keep it short and offer-focused to improve open rates.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="couponEmailMessage" class="form-label text-light">Message Template</label>
                                            <textarea class="form-control bg-secondary border-0 text-light" id="couponEmailMessage" rows="5" placeholder="Use placeholders: {{code}}, {{discount}}, {{min_order}}, {{expires_at}}"></textarea>
                                            <small class="field-hint d-block mt-1">Use placeholders so each email auto-fills coupon details.</small>
                                        </div>
                                        <button class="btn btn-orange" id="sendCouponEmailBtn" type="button">
                                            <i class="bi bi-send me-1"></i>Send Coupon Email
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">All Coupons</h3>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-secondary text-light">Live dashboard</span>
                                            <span class="badge bg-warning text-dark">Sort by status + expiry</span>
                                            <div class="input-group input-group-sm coupon-search-group">
                                                <span class="input-group-text bg-secondary border-0 text-light"><i class="bi bi-search"></i></span>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="couponTableSearch" placeholder="Search code/title">
                                            </div>
                                            <button class="btn btn-sm btn-outline-orange" id="refreshCouponsBtn" type="button">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="couponsTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Code</th>
                                                        <th class="py-3 text-orange fw-semibold">Offer</th>
                                                        <th class="py-3 text-orange fw-semibold">Limits</th>
                                                        <th class="py-3 text-orange fw-semibold">Expiry</th>
                                                        <th class="py-3 text-orange fw-semibold">Status</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4 text-secondary">No coupons created yet.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="reviewsSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Review moderation</h2>
                                <p class="mb-0 text-secondary">Review customer feedback, hide inappropriate reviews, and correct rating mistakes.</p>
                            </div>
                            <span class="step-chip">Tip: Hide abusive content instead of deleting it when possible.</span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="rounded-3 border border-secondary p-3">
                                    <p class="text-secondary small mb-1 text-uppercase">Total Reviews</p>
                                    <h4 class="text-light fw-bold mb-0" id="reviewStatTotal">0</h4>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="rounded-3 border border-secondary p-3">
                                    <p class="text-secondary small mb-1 text-uppercase">Visible</p>
                                    <h4 class="text-success fw-bold mb-0" id="reviewStatVisible">0</h4>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="rounded-3 border border-secondary p-3">
                                    <p class="text-secondary small mb-1 text-uppercase">Hidden</p>
                                    <h4 class="text-warning fw-bold mb-0" id="reviewStatHidden">0</h4>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="rounded-3 border border-secondary p-3">
                                    <p class="text-secondary small mb-1 text-uppercase">Average Rating</p>
                                    <h4 class="text-light fw-bold mb-0" id="reviewStatAverage">0.0</h4>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <div class="rounded-3 border border-secondary p-3">
                                    <p class="text-secondary small mb-1 text-uppercase">Locked</p>
                                    <h4 class="text-info fw-bold mb-0" id="reviewStatLocked">0</h4>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-card border-0 shadow-sm mb-4">
                            <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h3 class="h5 mb-0 fw-bold text-orange">All Product Reviews</h3>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-dark border border-secondary text-light dropdown-toggle"
                                            type="button"
                                            id="reviewVisibilityFilterBtn"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            style="min-width: 132px;">
                                            All Reviews
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary" id="reviewVisibilityFilterMenu">
                                            <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="reviewVisibilityFilter" data-value="all" data-label="All Reviews">All Reviews</a></li>
                                            <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewVisibilityFilter" data-value="visible" data-label="Visible">Visible</a></li>
                                            <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewVisibilityFilter" data-value="hidden" data-label="Hidden">Hidden</a></li>
                                        </ul>
                                    </div>
                                    <input type="hidden" id="reviewVisibilityFilter" value="all">
                                    <button class="btn btn-sm btn-orange" id="addReviewBtn" type="button">
                                        <i class="bi bi-layout-text-window-reverse me-1"></i>Use Quick Form
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" id="addFakeReviewBtn" type="button">
                                        <i class="bi bi-magic me-1"></i>Quick Fake x1
                                    </button>
                                    <button class="btn btn-sm btn-outline-orange" id="refreshReviewsBtn" type="button">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body border-bottom border-secondary px-4 py-3">
                                <small class="field-hint mb-0 d-block" id="reviewQuickHint">Use the quick form below to add reviews without popup prompts.</small>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0" id="reviewsTable">
                                        <thead class="border-bottom border-secondary">
                                            <tr>
                                                <th class="py-3 ps-4 text-orange fw-semibold">Product</th>
                                                <th class="py-3 text-orange fw-semibold">Customer</th>
                                                <th class="py-3 text-orange fw-semibold">Rating</th>
                                                <th class="py-3 text-orange fw-semibold">Review</th>
                                                <th class="py-3 text-orange fw-semibold">Status</th>
                                                <th class="py-3 text-orange fw-semibold">Updated</th>
                                                <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-secondary">No reviews found.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-card border-0 shadow-sm mb-4 review-workbench-card" id="reviewQuickAddCard">
                            <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h3 class="h5 mb-0 fw-bold text-orange">Quick Add Review (Manual)</h3>
                                <span class="badge bg-dark border border-secondary text-light">Clear form workflow</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-12 col-md-3">
                                        <label for="reviewAddUserId" class="form-label text-light">User ID</label>
                                        <input type="number" class="form-control bg-secondary border-0 text-light" id="reviewAddUserId" min="1" placeholder="15">
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label for="reviewAddProductId" class="form-label text-light">Product (ID or ID - Name)</label>
                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="reviewAddProductId" list="reviewProductSuggestions" placeholder="12 or 12 - Chrono Steel">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="reviewAddOrderId" class="form-label text-light">Order ID (optional)</label>
                                        <input type="number" class="form-control bg-secondary border-0 text-light" id="reviewAddOrderId" min="1" placeholder="1024">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label for="reviewAddRating" class="form-label text-light">Rating</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="reviewAddRatingBtn">
                                                5 - Excellent
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="reviewAddRatingMenu">
                                                <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="reviewAddRating" data-value="5" data-label="5 - Excellent">5 - Excellent</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewAddRating" data-value="4" data-label="4 - Good">4 - Good</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewAddRating" data-value="3" data-label="3 - Average">3 - Average</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewAddRating" data-value="2" data-label="2 - Poor">2 - Poor</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewAddRating" data-value="1" data-label="1 - Bad">1 - Bad</a></li>
                                            </ul>
                                        </div>
                                        <input type="hidden" id="reviewAddRating" value="5">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label for="reviewAddVisible" class="form-label text-light">Visibility</label>
                                        <div class="dropdown w-100">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="reviewAddVisibleBtn">
                                                Visible
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="reviewAddVisibleMenu">
                                                <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="reviewAddVisible" data-value="1" data-label="Visible">Visible</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="reviewAddVisible" data-value="0" data-label="Hidden">Hidden</a></li>
                                            </ul>
                                        </div>
                                        <input type="hidden" id="reviewAddVisible" value="1">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="reviewAddNote" class="form-label text-light">Admin Note (optional)</label>
                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="reviewAddNote" maxlength="500" placeholder="Internal moderation note">
                                    </div>
                                    <div class="col-12">
                                        <label for="reviewAddText" class="form-label text-light">Review Text</label>
                                        <textarea class="form-control bg-secondary border-0 text-light" id="reviewAddText" rows="3" maxlength="500" placeholder="Write 10 to 500 characters."></textarea>
                                    </div>
                                </div>
                                <datalist id="reviewProductSuggestions"></datalist>
                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                    <button class="btn btn-orange" id="submitAddReviewBtn" type="button">
                                        <i class="bi bi-plus-circle me-1"></i>Add Review Now
                                    </button>
                                    <button class="btn btn-outline-secondary" id="clearAddReviewBtn" type="button">
                                        <i class="bi bi-eraser me-1"></i>Clear Fields
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-card border-0 shadow-sm">
                            <div class="card-header bg-dark border-bottom border-secondary py-3">
                                <h3 class="h5 mb-0 fw-bold text-orange">Generate Fake Reviews</h3>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-3">
                                        <label for="fakeReviewProductId" class="form-label text-light">Product ID</label>
                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="fakeReviewProductId" list="reviewProductSuggestions" placeholder="10 or 10 - Product Name">
                                    </div>
                                    <div class="col-12 col-md-2">
                                        <label for="fakeReviewCount" class="form-label text-light">Count</label>
                                        <input type="number" class="form-control bg-secondary border-0 text-light" id="fakeReviewCount" min="1" max="100" value="10">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label for="fakeReviewRatingMin" class="form-label text-light">Rating Min</label>
                                        <input type="number" class="form-control bg-secondary border-0 text-light" id="fakeReviewRatingMin" min="1" max="5" value="3">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label for="fakeReviewRatingMax" class="form-label text-light">Rating Max</label>
                                        <input type="number" class="form-control bg-secondary border-0 text-light" id="fakeReviewRatingMax" min="1" max="5" value="5">
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label for="fakeReviewVisibility" class="form-label text-light">Visibility</label>
                                        <div class="dropdown">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="fakeReviewVisibilityBtn">
                                                Visible
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="fakeReviewVisibilityMenu">
                                                <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="fakeReviewVisibility" data-value="1" data-label="Visible">Visible</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="fakeReviewVisibility" data-value="0" data-label="Hidden">Hidden</a></li>
                                            </ul>
                                        </div>
                                        <input type="hidden" id="fakeReviewVisibility" value="1">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                    <button class="btn btn-outline-info" id="addSingleFakeReviewBtn" type="button">
                                        <i class="bi bi-magic me-1"></i>Generate 1 Fake Review
                                    </button>
                                    <button class="btn btn-orange" id="addFakeBulkReviewsBtn" type="button">
                                        <i class="bi bi-lightning-charge me-1"></i>Generate Bulk Fake Reviews
                                    </button>
                                </div>
                                <small class="field-hint d-block mt-2">Use this only for controlled seeding/testing scenarios.</small>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="analyticsSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Business snapshot</h2>
                                <p class="mb-0 text-secondary">Simple daily summary: money in, orders to handle, and what to improve next.</p>
                            </div>
                            <span class="step-chip">Tip: Check this tab once in the morning and once in the evening.</span>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <p class="text-secondary small mb-2 text-uppercase fw-semibold">Sales Received</p>
                                        <h3 class="fw-bold text-light mb-2" id="analyticsRevenueValue">PKR 0</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary small">Last 30 Days</span>
                                            <span class="text-success fw-semibold" id="analyticsRevenueHint"><i class="bi bi-info-circle"></i> Live</span>
                                        </div>
                                        <div class="progress mt-3" style="height: 6px;">
                                            <div class="progress-bar bg-orange" style="width: 75%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <p class="text-secondary small mb-2 text-uppercase fw-semibold">Orders Placed</p>
                                        <h3 class="fw-bold text-light mb-2" id="analyticsOrdersValue">0</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary small">Last 30 Days</span>
                                            <span class="text-success fw-semibold" id="analyticsOrdersHint"><i class="bi bi-info-circle"></i> Live</span>
                                        </div>
                                        <div class="progress mt-3" style="height: 6px;">
                                            <div class="progress-bar bg-info" style="width: 62%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <p class="text-secondary small mb-2 text-uppercase fw-semibold">Average Cart</p>
                                        <h3 class="fw-bold text-light mb-2" id="analyticsAovValue">PKR 0</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary small">Based on last 30 days</span>
                                            <span class="text-warning fw-semibold" id="analyticsAovHint"><i class="bi bi-info-circle"></i> Live</span>
                                        </div>
                                        <div class="progress mt-3" style="height: 6px;">
                                            <div class="progress-bar bg-warning" style="width: 48%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-body p-4">
                                        <p class="text-secondary small mb-2 text-uppercase fw-semibold">Repeat Customers</p>
                                        <h3 class="fw-bold text-light mb-2" id="analyticsReturningValue">0%</h3>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary small">Bought 2 or more times</span>
                                            <span class="text-success fw-semibold" id="analyticsReturningHint"><i class="bi bi-info-circle"></i> Live</span>
                                        </div>
                                        <div class="progress mt-3" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: 38%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Profit vs Loss Trend (7 Days)</h3>
                                        <span class="text-secondary small">Revenue, refunds, and net progress</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="analytics-chart-shell">
                                            <canvas id="analyticsProfitLossChart" height="120" aria-label="Profit and loss chart"></canvas>
                                        </div>
                                        <small class="field-hint mt-3">Loss is estimated from accepted refunds distributed across the week.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12 col-lg-7">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Weekly Performance</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="weeklyPerformanceRows">
                                            <div class="text-secondary small">No weekly performance data yet.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-5">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Top Products</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="topProductsList">
                                            <div class="text-secondary small">No top-product data yet.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-lg-6">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Store Health Summary</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="rounded-3 border border-secondary p-3 d-flex justify-content-between align-items-center">
                                                    <span class="text-light">Open orders (Pending + Processing)</span>
                                                    <span class="text-orange fw-bold" id="storeHealthOpenOrders">0</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="rounded-3 border border-secondary p-3 d-flex justify-content-between align-items-center">
                                                    <span class="text-light">Low stock products</span>
                                                    <span class="text-warning fw-bold" id="storeHealthLowStock">0</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="rounded-3 border border-secondary p-3 d-flex justify-content-between align-items-center">
                                                    <span class="text-light">Pending refunds</span>
                                                    <span class="text-info fw-bold" id="storeHealthPendingRefunds">0</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <small class="field-hint">Use this quick checklist to decide what needs action first.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">What To Do Next</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <ul class="list-group list-group-flush" id="analyticsActionList">
                                            <li class="list-group-item bg-transparent border-secondary text-secondary">No recommendations yet.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mt-1">
                            <div class="col-12 col-xl-5">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Live Product Viewers</h3>
                                        <span class="badge bg-dark border border-secondary text-light" id="liveViewersModeBadge">Mode: real</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="mb-3">
                                            <label for="liveViewersMode" class="form-label text-light">Tracking Mode</label>
                                            <input type="hidden" id="liveViewersMode" value="real">
                                            <div class="dropdown admin-mode-dropdown">
                                                <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" id="liveViewersModeBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Real (active sessions)
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="liveViewersModeMenu">
                                                    <li><button class="dropdown-item" type="button" data-mode="real" data-label="Real (active sessions)">Real (active sessions)</button></li>
                                                    <li><button class="dropdown-item" type="button" data-mode="fake" data-label="Fake (marketing demo)">Fake (marketing demo)</button></li>
                                                </ul>
                                            </div>
                                            <small class="field-hint">Real mode reads active sessions. Fake mode uses a configurable range.</small>
                                        </div>

                                        <div id="liveViewerFakeConfig" class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label for="liveViewersFakeMin" class="form-label text-light">Fake Min</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="liveViewersFakeMin" min="1" max="5000" value="120">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="liveViewersFakeMax" class="form-label text-light">Fake Max</label>
                                                <input type="number" class="form-control bg-secondary border-0 text-light" id="liveViewersFakeMax" min="1" max="5000" value="165">
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <label for="liveViewersWindow" class="form-label text-light">Active Window (seconds)</label>
                                            <input type="number" class="form-control bg-secondary border-0 text-light" id="liveViewersWindow" min="30" max="3600" value="180">
                                            <small class="field-hint">Visitors active within this window are counted as live viewers.</small>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button class="btn btn-orange" type="button" id="saveLiveViewersBtn">
                                                <i class="bi bi-save2 me-1"></i>Save Viewer Settings
                                            </button>
                                            <button class="btn btn-outline-orange" type="button" id="refreshLiveViewersBtn">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Snapshot
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-7">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Live Viewer Snapshot</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-md-6">
                                                <div class="rounded-3 border border-secondary p-3 h-100">
                                                    <p class="text-secondary small text-uppercase fw-semibold mb-1">Active Viewers</p>
                                                    <h4 class="text-light fw-bold mb-0" id="liveViewersActiveNow">0</h4>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="rounded-3 border border-secondary p-3 h-100">
                                                    <p class="text-secondary small text-uppercase fw-semibold mb-1">Tracked Products</p>
                                                    <h4 class="text-light fw-bold mb-0" id="liveViewersTrackedProducts">0</h4>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-3 text-orange fw-semibold">Product</th>
                                                        <th class="py-3 pe-3 text-orange fw-semibold text-end">Live Viewers</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="liveViewersTopProducts">
                                                    <tr>
                                                        <td class="ps-3 py-3 text-secondary" colspan="2">No live viewer data yet.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="emailSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Email Center</h2>
                                <p class="mb-0 text-secondary">Send announcements to subscribers and customers.</p>
                            </div>
                            <span class="step-chip">Tip: Build your list first, then apply a template.</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3 class="h5 mb-0 fw-bold text-orange">Recipients</h3>
                                            <p class="text-secondary small mb-0"><span id="emailRecipientCount">0</span> total</p>
                                        </div>
                                        <span class="badge bg-secondary text-light">Selected: <span id="emailSelectedCount">0</span></span>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-2 align-items-end mb-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label text-light">Source</label>
                                                <div class="dropdown w-100">
                                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="emailSourceBtn">
                                                        All sources
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="emailSourceMenu">
                                                        <li><a class="dropdown-item text-light" href="#" data-source="all">All sources</a></li>
                                                        <li>
                                                            <hr class="dropdown-divider border-secondary">
                                                        </li>
                                                        <li><a class="dropdown-item text-light" href="#" data-source="newsletter">Newsletter</a></li>
                                                        <li><a class="dropdown-item text-light" href="#" data-source="customers">Customers</a></li>
                                                    </ul>
                                                </div>
                                                <input type="hidden" id="emailSourceFilter" value="all">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="emailSearchInput" class="form-label text-light">Search</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="emailSearchInput" placeholder="Search by email or name">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            <button class="btn btn-outline-orange btn-sm" type="button" id="emailSelectAllBtn">
                                                Select All
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" id="emailClearBtn">
                                                Clear
                                            </button>
                                            <button class="btn btn-outline-orange btn-sm" type="button" id="emailCopyBtn">
                                                Copy Emails
                                            </button>
                                        </div>
                                        <div class="input-group mb-3">
                                            <input type="email" class="form-control bg-secondary border-0 text-light" id="emailAddRecipientInput" placeholder="Add manual recipient">
                                            <button class="btn btn-orange" type="button" id="emailAddRecipientBtn">Add</button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="emailRecipientsTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold" style="width: 50px;">Pick</th>
                                                        <th class="py-3 text-orange fw-semibold">Email</th>
                                                        <th class="py-3 text-orange fw-semibold">Source</th>
                                                        <th class="py-3 text-orange fw-semibold">Last Seen</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Remove</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Compose Email</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label text-light">Template</label>
                                                <div class="dropdown w-100">
                                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="emailTemplateBtn">
                                                        Custom
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100 email-template-menu" id="emailTemplateMenu"></ul>
                                                </div>
                                                <input type="hidden" id="emailTemplateId" value="">
                                            </div>
                                            <div class="col-12">
                                                <label for="emailTemplateName" class="form-label text-light">Template Name</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="emailTemplateName" placeholder="Summer launch update">
                                            </div>
                                            <div class="col-12">
                                                <label for="emailSubjectInput" class="form-label text-light">Subject</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="emailSubjectInput" placeholder="New arrivals are live">
                                            </div>
                                            <div class="col-12">
                                                <label for="emailBodyInput" class="form-label text-light">Message</label>
                                                <textarea class="form-control bg-secondary border-0 text-light" id="emailBodyInput" rows="8" placeholder="Write your message here..."></textarea>
                                                <small class="field-hint">Tip: Keep the subject short and the message clear.</small>
                                            </div>
                                            <div class="col-12">
                                                <label for="emailAttachmentInput" class="form-label text-light">Attachment</label>
                                                <input type="file" class="form-control bg-secondary border-0 text-light" id="emailAttachmentInput" multiple>
                                                <small class="field-hint">Attachments open in your email client after clicking Send. This panel cannot upload files.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button class="btn btn-orange" type="button" id="emailSendBtn">
                                                <i class="bi bi-send me-1"></i>Send Email
                                            </button>
                                            <button class="btn btn-outline-orange" type="button" id="emailSaveTemplateBtn">
                                                <i class="bi bi-save2 me-1"></i>Save Template
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" id="emailNewTemplateBtn">
                                                <i class="bi bi-plus-circle me-1"></i>New Template
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Preview</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="p-3 rounded-2 bg-dark border border-secondary" style="min-height: 140px; white-space: pre-wrap;" id="emailPreview"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="websiteSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Website settings</h2>
                                <p class="mb-0 text-secondary">Manage security, contact info, and social links.</p>
                            </div>
                            <span class="step-chip">Tip: Save after every change so the website updates.</span>
                        </div>
                        <div class="row g-4 mt-1">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Security Controls</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-4">
                                            <div class="col-12 col-lg-4">
                                                <h4 class="h6 text-light fw-bold mb-3">Change Admin Email</h4>
                                                <div class="mb-3">
                                                    <label for="securityEmailPassword" class="form-label text-light">Current Password</label>
                                                    <div class="password-wrapper">
                                                        <input type="password" class="form-control bg-secondary border-0 text-light" id="securityEmailPassword" placeholder="Enter current password" autocomplete="new-password">
                                                        <i class="bi bi-eye password-toggle" data-target="#securityEmailPassword" aria-label="Show/hide password"></i>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityEmailResetKey" class="form-label text-light">Reset Key</label>
                                                    <input type="password" class="form-control bg-secondary border-0 text-light" id="securityEmailResetKey" placeholder="Enter reset key" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityEmailNew" class="form-label text-light">New Email</label>
                                                    <input type="email" class="form-control bg-secondary border-0 text-light" id="securityEmailNew" placeholder="name@example.com" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityEmailConfirm" class="form-label text-light">Confirm Email</label>
                                                    <input type="email" class="form-control bg-secondary border-0 text-light" id="securityEmailConfirm" placeholder="name@example.com" autocomplete="off">
                                                </div>
                                                <button class="btn btn-orange" id="saveAdminEmailBtn">
                                                    <i class="bi bi-save2 me-1"></i>Update Email
                                                </button>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <h4 class="h6 text-light fw-bold mb-3">Change Admin Password</h4>
                                                <div class="mb-3">
                                                    <label for="securityPasswordEmail" class="form-label text-light">Current Email</label>
                                                    <input type="email" class="form-control bg-secondary border-0 text-light" id="securityPasswordEmail" placeholder="admin email" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityPasswordResetKey" class="form-label text-light">Reset Key</label>
                                                    <input type="password" class="form-control bg-secondary border-0 text-light" id="securityPasswordResetKey" placeholder="Enter reset key" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityPasswordNew" class="form-label text-light">New Password</label>
                                                    <div class="password-wrapper">
                                                        <input type="password" class="form-control bg-secondary border-0 text-light" id="securityPasswordNew" placeholder="New password" autocomplete="new-password">
                                                        <i class="bi bi-eye password-toggle" data-target="#securityPasswordNew" aria-label="Show/hide password"></i>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityPasswordConfirm" class="form-label text-light">Confirm Password</label>
                                                    <div class="password-wrapper">
                                                        <input type="password" class="form-control bg-secondary border-0 text-light" id="securityPasswordConfirm" placeholder="Confirm password" autocomplete="new-password">
                                                        <i class="bi bi-eye password-toggle" data-target="#securityPasswordConfirm" aria-label="Show/hide password"></i>
                                                    </div>
                                                </div>
                                                <button class="btn btn-orange" id="saveAdminPasswordBtn">
                                                    <i class="bi bi-save2 me-1"></i>Update Password
                                                </button>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <h4 class="h6 text-light fw-bold mb-3">Rotate Reset Key</h4>
                                                <div class="mb-3">
                                                    <label for="securityKeyEmail" class="form-label text-light">Current Email</label>
                                                    <input type="email" class="form-control bg-secondary border-0 text-light" id="securityKeyEmail" placeholder="admin email" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityKeyPassword" class="form-label text-light">Current Password</label>
                                                    <div class="password-wrapper">
                                                        <input type="password" class="form-control bg-secondary border-0 text-light" id="securityKeyPassword" placeholder="Enter current password" autocomplete="new-password">
                                                        <i class="bi bi-eye password-toggle" data-target="#securityKeyPassword" aria-label="Show/hide password"></i>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityKeyNew" class="form-label text-light">New Reset Key</label>
                                                    <input type="text" class="form-control bg-secondary border-0 text-light" id="securityKeyNew" placeholder="New reset key" autocomplete="off">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="securityKeyConfirm" class="form-label text-light">Confirm Reset Key</label>
                                                    <input type="text" class="form-control bg-secondary border-0 text-light" id="securityKeyConfirm" placeholder="Confirm reset key" autocomplete="off">
                                                </div>
                                                <button class="btn btn-orange" id="saveAdminResetKeyBtn">
                                                    <i class="bi bi-save2 me-1"></i>Update Reset Key
                                                </button>
                                            </div>
                                        </div>
                                        <p class="field-hint mt-3">Security updates are now validated on the backend with session and CSRF protection.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4 mt-3">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Branding</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-4">
                                                <label for="siteName" class="form-label text-light">Website Name</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="siteName" placeholder="COMMERZA">
                                                <small class="field-hint">Shown in the navbar and logo text.</small>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="siteLogo" class="form-label text-light">Logo Path</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="siteLogo" placeholder="frontend/assets/images/logo/commerza_logo.svg">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="siteLogoFile" accept="image/*">
                                                    <button class="btn btn-outline-orange" id="uploadSiteLogoBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">Use a full URL or a local path.</small>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="siteFavicon" class="form-label text-light">Favicon Path</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="siteFavicon" placeholder="frontend/assets/images/favicon/commerza-watches-icon.ico">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="siteFaviconFile" accept="image/*,.ico">
                                                    <button class="btn btn-outline-orange" id="uploadSiteFaviconBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">ICO/PNG recommended.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveBrandBtn">
                                                <i class="bi bi-save2 me-1"></i>Save Branding
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4 mt-3" id="seoMetaManagerRow">
                            <div class="col-12 col-xl-7">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">SEO Meta Manager</h3>
                                        <span class="badge bg-warning text-dark text-uppercase">Premium Control</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label for="seoPageSelect" class="form-label text-light">Page</label>
                                                <div class="dropdown w-100">
                                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="seoPageSelectBtn">
                                                        Select Page
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="seoPageSelectMenu"></ul>
                                                </div>
                                                <input type="hidden" id="seoPageSelect" value="">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="seoMetaTitleInput" class="form-label text-light">Meta Title</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="seoMetaTitleInput" maxlength="150" placeholder="Page title for search results">
                                            </div>
                                            <div class="col-12">
                                                <label for="seoMetaDescriptionInput" class="form-label text-light">Meta Description</label>
                                                <textarea class="form-control bg-secondary border-0 text-light" id="seoMetaDescriptionInput" rows="2" maxlength="255" placeholder="Page description for search and social previews"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label for="seoCanonicalInput" class="form-label text-light">Canonical URL</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="seoCanonicalInput" maxlength="255" placeholder="https://example.com/page or /page">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="seoOgTitleInput" class="form-label text-light">OG Title</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="seoOgTitleInput" maxlength="150" placeholder="Open Graph title">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label for="seoOgImageInput" class="form-label text-light">OG Image</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="seoOgImageInput" maxlength="255" placeholder="https://example.com/image.webp or frontend/assets/...">
                                            </div>
                                            <div class="col-12">
                                                <label for="seoOgDescriptionInput" class="form-label text-light">OG Description</label>
                                                <textarea class="form-control bg-secondary border-0 text-light" id="seoOgDescriptionInput" rows="2" maxlength="255" placeholder="Open Graph description"></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label for="seoJsonLdInput" class="form-label text-light">JSON-LD</label>
                                                <textarea class="form-control bg-secondary border-0 text-light" id="seoJsonLdInput" rows="5" placeholder='{"@context":"https://schema.org"}'></textarea>
                                                <small class="field-hint d-block mt-1">Add valid JSON object or array only.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveSeoMetaBtn" type="button">
                                                <i class="bi bi-save2 me-1"></i>Save SEO Meta
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetSeoMetaBtn" type="button">Reset Fields</button>
                                            <button class="btn btn-outline-danger" id="deleteSeoMetaBtn" type="button">
                                                <i class="bi bi-trash me-1"></i>Delete Page Meta
                                            </button>
                                        </div>
                                        <small class="field-hint d-block mt-2" id="seoMetaPreview">Select a page to edit search and social metadata.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-xl-5">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Configured SEO Pages</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="table-responsive">
                                            <table class="table table-dark table-hover align-middle mb-0" id="seoMetaTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Page</th>
                                                        <th class="py-3 text-orange fw-semibold">Meta Title</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-4 text-secondary">No page metadata configured.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4 mt-3">
                            <div class="col-12 col-lg-6">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Contact Details</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="mb-3">
                                            <label for="siteAddress" class="form-label text-light">Address</label>
                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="siteAddress" placeholder="Barrage Colony, HYD, PK">
                                        </div>
                                        <div class="mb-3">
                                            <label for="siteEmail" class="form-label text-light">Email</label>
                                            <input type="email" class="form-control bg-secondary border-0 text-light" id="siteEmail" placeholder="admin@commerza.com">
                                        </div>
                                        <div class="mb-3">
                                            <label for="sitePhone" class="form-label text-light">Phone</label>
                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="sitePhone" placeholder="+92 300 1234567">
                                        </div>
                                        <button class="btn btn-orange" id="saveContactBtn">
                                            <i class="bi bi-save2 me-1"></i>Save Contact
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div class="card admin-card border-0 shadow-sm h-100">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Social Media Links</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <input type="hidden" id="socialId">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4">
                                                <label for="socialLabel" class="form-label text-light">Label</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="socialLabel" placeholder="Facebook">
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <label for="socialUrl" class="form-label text-light">URL</label>
                                                <input type="url" class="form-control bg-secondary border-0 text-light" id="socialUrl" placeholder="https://facebook.com/...">
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label for="socialIcon" class="form-label text-light">Icon</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="socialIcon" placeholder="bi bi-facebook or frontend/assets/images/social/icon.png">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="socialIconFile" accept="image/*,.ico" multiple>
                                                    <button class="btn btn-outline-orange" id="uploadSocialIconBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">Select one or more icons. Images are parsed and compressed to WebP; ICO files stay ICO.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveSocialBtn">
                                                <i class="bi bi-plus-circle me-1"></i>Add Social
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetSocialBtn" type="button">Reset</button>
                                        </div>
                                        <div class="table-responsive mt-4">
                                            <table class="table table-dark table-hover align-middle mb-0" id="socialLinksTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Label</th>
                                                        <th class="py-3 text-orange fw-semibold">URL</th>
                                                        <th class="py-3 text-orange fw-semibold">Icon</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade security-events-shell" id="securityEventsSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Security Event Monitoring</h2>
                                <p class="mb-0 text-secondary">Review login failures, suspicious activity, and rate-limit blocks in one place.</p>
                            </div>
                            <span class="step-chip">Tip: Filter by severity and date range for faster incident triage.</span>
                        </div>

                        <div class="card admin-card border-0 shadow-sm mb-4 security-events-panel security-events-filters">
                            <div class="card-header bg-dark border-bottom border-secondary py-3">
                                <h3 class="h5 mb-0 fw-bold text-orange">Filters</h3>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-6 col-xl-2">
                                        <label class="form-label text-light" for="securityEventTypeFilter">Event Type</label>
                                        <input type="text" id="securityEventTypeFilter" class="form-control bg-secondary border-0 text-light" placeholder="login_failed">
                                    </div>
                                    <div class="col-6 col-md-3 col-xl-2">
                                        <label class="form-label text-light" for="securitySeverityFilter">Severity</label>
                                        <div class="dropdown">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" id="securitySeverityFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                All
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="securitySeverityFilterMenu">
                                                <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="securitySeverityFilter" data-value="" data-label="All">All</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="securitySeverityFilter" data-value="info" data-label="Info">Info</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="securitySeverityFilter" data-value="warning" data-label="Warning">Warning</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="securitySeverityFilter" data-value="critical" data-label="Critical">Critical</a></li>
                                            </ul>
                                        </div>
                                        <input type="hidden" id="securitySeverityFilter" value="">
                                    </div>
                                    <div class="col-6 col-md-3 col-xl-2">
                                        <label class="form-label text-light" for="securityActorTypeFilter">Actor</label>
                                        <div class="dropdown">
                                            <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" id="securityActorTypeFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                All
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="securityActorTypeFilterMenu">
                                                <li><a class="dropdown-item text-light admin-dropdown-item active" href="#" data-target="securityActorTypeFilter" data-value="" data-label="All">All</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="securityActorTypeFilter" data-value="user" data-label="User">User</a></li>
                                                <li><a class="dropdown-item text-light admin-dropdown-item" href="#" data-target="securityActorTypeFilter" data-value="admin" data-label="Admin">Admin</a></li>
                                            </ul>
                                        </div>
                                        <input type="hidden" id="securityActorTypeFilter" value="">
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <label class="form-label text-light" for="securityEventSearchFilter">Search</label>
                                        <input type="text" id="securityEventSearchFilter" class="form-control bg-secondary border-0 text-light" placeholder="identifier, IP, user-agent">
                                    </div>
                                    <div class="col-6 col-md-3 col-xl-1">
                                        <label class="form-label text-light" for="securityFromFilter">From</label>
                                        <input type="date" id="securityFromFilter" class="form-control bg-secondary border-0 text-light">
                                    </div>
                                    <div class="col-6 col-md-3 col-xl-1">
                                        <label class="form-label text-light" for="securityToFilter">To</label>
                                        <input type="date" id="securityToFilter" class="form-control bg-secondary border-0 text-light">
                                    </div>
                                    <div class="col-12 col-xl-1 d-grid gap-2">
                                        <button class="btn btn-orange" id="securityEventsApplyBtn" type="button">Apply</button>
                                        <button class="btn btn-outline-secondary" id="securityEventsClearBtn" type="button">Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-card border-0 shadow-sm mb-4 security-events-panel security-events-log">
                            <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                                <h3 class="h5 mb-0 fw-bold text-orange">Security Events Log</h3>
                                <button class="btn btn-sm btn-outline-orange" id="securityEventsRefreshBtn" type="button">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive security-events-table-wrap">
                                    <table class="table table-dark table-hover align-middle mb-0 security-events-table" id="securityEventsTable">
                                        <thead class="border-bottom border-secondary">
                                            <tr>
                                                <th class="ps-4 py-3 text-orange fw-semibold">Time</th>
                                                <th class="py-3 text-orange fw-semibold">Event</th>
                                                <th class="py-3 text-orange fw-semibold">Severity</th>
                                                <th class="py-3 text-orange fw-semibold">Actor</th>
                                                <th class="py-3 text-orange fw-semibold">Identifier / IP</th>
                                                <th class="pe-4 py-3 text-orange fw-semibold">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-secondary">No events loaded yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer bg-dark border-top border-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 security-events-footer">
                                <small class="text-secondary" id="securityEventsMeta">Page 1</small>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" id="securityEventsPrevBtn" type="button">Previous</button>
                                    <button class="btn btn-sm btn-outline-secondary" id="securityEventsNextBtn" type="button">Next</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="homepageSection">
                        <div class="helper-banner mb-4">
                            <div>
                                <h2 class="h5 mb-2 text-light">Homepage content</h2>
                                <p class="mb-0 text-secondary">Update the ticker, Collectors Speak, and homepage slider.</p>
                            </div>
                            <span class="step-chip">Tip: Keep messages short for smooth scrolling.</span>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Homepage Storybook</h3>
                                        <span class="badge bg-warning text-dark text-uppercase">Admin Editable</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <p class="text-secondary mb-3">Edit each page subtitle, title, and body text for the homepage interactive book.</p>
                                        <div class="row g-3">
                                            <div class="col-12 col-xl-6">
                                                <div class="border border-secondary rounded-3 p-3 h-100">
                                                    <h4 class="h6 text-light fw-bold mb-3">Page 1</h4>
                                                    <div class="mb-2">
                                                        <label for="storybookPage1Subtitle" class="form-label text-light">Subtitle</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage1Subtitle" maxlength="120" placeholder="Design Language">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage1Title" class="form-label text-light">Title</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage1Title" maxlength="150" placeholder="Built For Modern Legacy">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage1BodyPrimary" class="form-label text-light">Primary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage1BodyPrimary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage1BodySecondary" class="form-label text-light">Secondary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage1BodySecondary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div>
                                                        <label for="storybookPage1Footnote" class="form-label text-light">Footnote</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage1Footnote" maxlength="180" placeholder="Chapter note...">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-xl-6">
                                                <div class="border border-secondary rounded-3 p-3 h-100">
                                                    <h4 class="h6 text-light fw-bold mb-3">Page 2</h4>
                                                    <div class="mb-2">
                                                        <label for="storybookPage2Subtitle" class="form-label text-light">Subtitle</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage2Subtitle" maxlength="120" placeholder="Material and Movement">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage2Title" class="form-label text-light">Title</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage2Title" maxlength="150" placeholder="Casework, Crystal, and Caliber Harmony">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage2BodyPrimary" class="form-label text-light">Primary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage2BodyPrimary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage2BodySecondary" class="form-label text-light">Secondary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage2BodySecondary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div>
                                                        <label for="storybookPage2Footnote" class="form-label text-light">Footnote</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage2Footnote" maxlength="180" placeholder="Every layer must earn its place.">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-xl-6">
                                                <div class="border border-secondary rounded-3 p-3 h-100">
                                                    <h4 class="h6 text-light fw-bold mb-3">Page 3</h4>
                                                    <div class="mb-2">
                                                        <label for="storybookPage3Subtitle" class="form-label text-light">Subtitle</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage3Subtitle" maxlength="120" placeholder="Wrist Presence">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage3Title" class="form-label text-light">Title</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage3Title" maxlength="150" placeholder="Designed To Transition Across Moments">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage3BodyPrimary" class="form-label text-light">Primary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage3BodyPrimary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage3BodySecondary" class="form-label text-light">Secondary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage3BodySecondary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div>
                                                        <label for="storybookPage3Footnote" class="form-label text-light">Footnote</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage3Footnote" maxlength="180" placeholder="Form and confidence in one profile.">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-xl-6">
                                                <div class="border border-secondary rounded-3 p-3 h-100">
                                                    <h4 class="h6 text-light fw-bold mb-3">Page 4</h4>
                                                    <div class="mb-2">
                                                        <label for="storybookPage4Subtitle" class="form-label text-light">Subtitle</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage4Subtitle" maxlength="120" placeholder="Service and Trust">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage4Title" class="form-label text-light">Title</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage4Title" maxlength="150" placeholder="Refined Through Real Customer Signals">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage4BodyPrimary" class="form-label text-light">Primary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage4BodyPrimary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label for="storybookPage4BodySecondary" class="form-label text-light">Secondary Paragraph</label>
                                                        <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage4BodySecondary" rows="3" maxlength="700"></textarea>
                                                    </div>
                                                    <div>
                                                        <label for="storybookPage4Footnote" class="form-label text-light">Footnote</label>
                                                        <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage4Footnote" maxlength="180" placeholder="Experience matters beyond the watch itself.">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="border border-secondary rounded-3 p-3 h-100">
                                                    <h4 class="h6 text-light fw-bold mb-3">Page 5</h4>
                                                    <div class="row g-2">
                                                        <div class="col-12 col-xl-6">
                                                            <label for="storybookPage5Subtitle" class="form-label text-light">Subtitle</label>
                                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage5Subtitle" maxlength="120" placeholder="Final Note">
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <label for="storybookPage5Title" class="form-label text-light">Title</label>
                                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage5Title" maxlength="150" placeholder="The Next Chapter Starts On Your Wrist">
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <label for="storybookPage5BodyPrimary" class="form-label text-light">Primary Paragraph</label>
                                                            <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage5BodyPrimary" rows="3" maxlength="700"></textarea>
                                                        </div>
                                                        <div class="col-12 col-xl-6">
                                                            <label for="storybookPage5BodySecondary" class="form-label text-light">Secondary Paragraph</label>
                                                            <textarea class="form-control bg-secondary border-0 text-light" id="storybookPage5BodySecondary" rows="3" maxlength="700"></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="storybookPage5Footnote" class="form-label text-light">Footnote</label>
                                                            <input type="text" class="form-control bg-secondary border-0 text-light" id="storybookPage5Footnote" maxlength="180" placeholder="End of lookbook.">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3 flex-wrap">
                                            <button class="btn btn-orange" id="saveStorybookBtn" type="button">
                                                <i class="bi bi-save2 me-1"></i>Save Storybook
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetStorybookBtn" type="button">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Homepage Ticker</h3>
                                    </div>
                                    <div class="card-body p-4 homepage-ticker-shell">
                                        <div class="row g-3 align-items-start">
                                            <div class="col-12 col-xl-7">
                                                <div class="form-check form-switch mb-3">
                                                    <input class="form-check-input" type="checkbox" id="tickerEnabled" checked>
                                                    <label class="form-check-label text-light" for="tickerEnabled">Enable ticker</label>
                                                </div>
                                                <label for="tickerMessages" class="form-label text-light">Ticker Messages</label>
                                                <textarea class="form-control bg-secondary border-0 text-light" id="tickerMessages" rows="6" placeholder="Add one message per line"></textarea>
                                                <small class="field-hint d-block mt-2">Each line becomes one ticker item. Keep messages concise for smooth marquee speed.</small>
                                                <div class="ticker-composer-meta mt-2" id="tickerComposerMeta">0 message(s) ready.</div>
                                            </div>
                                            <div class="col-12 col-xl-5">
                                                <div class="ticker-live-preview">
                                                    <p class="ticker-preview-title mb-2">Live Preview</p>
                                                    <div id="tickerPreviewList" class="ticker-preview-list">
                                                        <span class="ticker-preview-pill">Add a message to preview</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3 flex-wrap">
                                            <button class="btn btn-orange" id="saveTickerBtn">
                                                <i class="bi bi-save2 me-1"></i>Save Ticker
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetTickerBtn" type="button">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm collectors-editor-shell">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Collectors Speak</h3>
                                        <span class="badge bg-info text-dark text-uppercase">Homepage Marquee</span>
                                    </div>
                                    <div class="card-body p-4">
                                        <label for="collectorsSpeakInput" class="form-label text-light">Entries (one per line)</label>
                                        <textarea class="form-control bg-secondary border-0 text-light" id="collectorsSpeakInput" rows="7" placeholder="Name | City / Role | Quote"></textarea>
                                        <small class="field-hint d-block mt-2">Format: Name | Tagline | Quote. Example: A. Khan | Lahore Collector | The dial finishing is excellent.</small>
                                        <small class="field-hint d-block">Add by creating a new line, edit by changing a line, and delete by removing the line before saving.</small>
                                        <div class="collectors-preview-list mt-3" id="collectorsSpeakPreview">
                                            <div class="text-secondary small">Preview will appear here as you type.</div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3 flex-wrap">
                                            <button class="btn btn-orange" id="saveCollectorsSpeakBtn" type="button">
                                                <i class="bi bi-save2 me-1"></i>Save Collectors Speak
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetCollectorsSpeakBtn" type="button">Reset</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Featured Videos</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-6">
                                                <label for="homeFeatureVideo" class="form-label text-light">Homepage Feature Video</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="homeFeatureVideo" placeholder="frontend/assets/videos/slider/...mp4">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="homeFeatureVideoFile" accept="video/mp4,video/webm,video/ogg" multiple>
                                                    <button class="btn btn-outline-orange" id="uploadHomeFeatureVideoBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">This video appears on the homepage showcase section.</small>
                                            </div>
                                            <div class="col-12 col-lg-6">
                                                <label for="categoryAFeatureVideo" class="form-label text-light">Shop Category A Feature Video</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="categoryAFeatureVideo" placeholder="frontend/assets/videos/products/...mp4">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="categoryAFeatureVideoFile" accept="video/mp4,video/webm,video/ogg" multiple>
                                                    <button class="btn btn-outline-orange" id="uploadCategoryAFeatureVideoBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">This video appears in Shop Category A above products.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveFeaturedVideosBtn">
                                                <i class="bi bi-save2 me-1"></i>Save Featured Videos
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mt-1">
                            <div class="col-12">
                                <div class="card admin-card border-0 shadow-sm">
                                    <div class="card-header bg-dark border-bottom border-secondary py-3">
                                        <h3 class="h5 mb-0 fw-bold text-orange">Homepage Slider Images</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <input type="hidden" id="sliderId">
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderImage" class="form-label text-light">Image Path</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderImage" placeholder="frontend/assets/images/slider/...">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="sliderImageFile" accept="image/*" multiple>
                                                    <button class="btn btn-outline-orange" id="uploadSliderImageBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">Bulk select images to parse/compress to WebP and upload in a queue.</small>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderAlt" class="form-label text-light">Alt Text</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderAlt" placeholder="Luxury watch banner">
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderLabel" class="form-label text-light">Label</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderLabel" placeholder="Premium Collection">
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderHeading" class="form-label text-light">Heading</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderHeading" placeholder="Chronograph Precision">
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderText" class="form-label text-light">Description</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderText" placeholder="Engineered movements with dual finish cases">
                                            </div>
                                            <div class="col-12 col-lg-2">
                                                <label for="sliderButtonText" class="form-label text-light">Button Text</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderButtonText" placeholder="Explore Now" autocomplete="off">
                                            </div>
                                            <div class="col-12 col-lg-2">
                                                <label for="sliderButtonLink" class="form-label text-light">Button Link</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderButtonLink" placeholder="shop-category-a.php" autocomplete="off">
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <label for="sliderVideo" class="form-label text-light">Optional Video Path</label>
                                                <input type="text" class="form-control bg-secondary border-0 text-light" id="sliderVideo" placeholder="frontend/assets/videos/slider/...mp4">
                                                <div class="d-flex gap-2 mt-2">
                                                    <input type="file" class="form-control bg-secondary border-0 text-light" id="sliderVideoFile" accept="video/mp4,video/webm,video/ogg" multiple>
                                                    <button class="btn btn-outline-orange" id="uploadSliderVideoBtn" type="button"><i class="bi bi-upload"></i></button>
                                                </div>
                                                <small class="field-hint">Bulk video parsing is queued to avoid stuck uploads.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-orange" id="saveSliderBtn">
                                                <i class="bi bi-plus-circle me-1"></i>Add Slide
                                            </button>
                                            <button class="btn btn-outline-secondary" id="resetSliderBtn" type="button">Reset</button>
                                        </div>
                                        <div class="table-responsive mt-4">
                                            <table class="table table-dark table-hover align-middle mb-0" id="sliderTable">
                                                <thead class="border-bottom border-secondary">
                                                    <tr>
                                                        <th class="py-3 ps-4 text-orange fw-semibold">Image</th>
                                                        <th class="py-3 text-orange fw-semibold">Heading</th>
                                                        <th class="py-3 text-orange fw-semibold">CTA</th>
                                                        <th class="py-3 pe-4 text-orange fw-semibold">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="mt-auto pt-5">
                    <div class="border-top border-orange border-2 py-4 px-4 mt-5">
                        <div class="row">
                            <div class="col-12 text-center">
                                <p class="footer-copyright mb-0 text-secondary small">
                                    &copy; 2026 Commerza Admin Panel. All rights reserved.
                                </p>
                            </div>
                        </div>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark border border-secondary">
                <div class="modal-header border-bottom border-secondary">
                    <h3 class="h5 modal-title text-orange fw-bold" id="productModalLabel">Add New Product</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="productId">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="productName" class="form-label text-light">Product Name</label>
                                <input type="text" class="form-control bg-secondary border-0 text-light" id="productName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-light mb-2">Section</label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="productSectionBtn">
                                        Select Section
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100" id="productSectionMenu"></ul>
                                </div>
                                <input type="hidden" id="productSection" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="productPrice" class="form-label text-light">Price (PKR)</label>
                                <input type="number" class="form-control bg-secondary border-0 text-light" id="productPrice" required>
                            </div>
                            <div class="col-md-6">
                                <label for="productSalePrice" class="form-label text-light">Sale Price (PKR)</label>
                                <input type="number" class="form-control bg-secondary border-0 text-light" id="productSalePrice" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="productStock" class="form-label text-light">Stock</label>
                                <input type="number" class="form-control bg-secondary border-0 text-light" id="productStock" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-light mb-2">Movement Type</label>
                                <div class="dropdown w-100">
                                    <button class="btn btn-dark border border-secondary text-light w-100 text-start rounded-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="productMovementBtn">
                                        Quartz
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start bg-dark border-secondary w-100">
                                        <li><a class="dropdown-item text-light" href="#" onclick="selectProductMovement('quartz', 'Quartz'); return false;">Quartz</a></li>
                                        <li><a class="dropdown-item text-light" href="#" onclick="selectProductMovement('auto', 'Automatic'); return false;">Automatic</a></li>
                                        <li><a class="dropdown-item text-light" href="#" onclick="selectProductMovement('smart', 'Smart'); return false;">Smart</a></li>
                                    </ul>
                                </div>
                                <input type="hidden" id="productMovement" value="quartz">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="productCode" class="form-label text-light">Product Code</label>
                                <input type="text" class="form-control bg-secondary border-0 text-light" id="productCode" maxlength="40" placeholder="CMRZ-00001">
                                <small class="field-hint">Unique code for support, warranty, and dispatch tracking.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="productWarrantyInfo" class="form-label text-light">Warranty</label>
                                <input type="text" class="form-control bg-secondary border-0 text-light" id="productWarrantyInfo" maxlength="120" value="12-month seller warranty" placeholder="12-month seller warranty">
                            </div>
                            <div class="col-md-4">
                                <label for="productDispatchInfo" class="form-label text-light">Dispatch</label>
                                <input type="text" class="form-control bg-secondary border-0 text-light" id="productDispatchInfo" maxlength="120" value="Dispatch in 24-48 hours" placeholder="Dispatch in 24-48 hours">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="productImage" class="form-label text-light">Image Path</label>
                            <input type="text" class="form-control bg-secondary border-0 text-light" id="productImage" placeholder="frontend/assets/images/products/..." required>
                            <div class="d-flex gap-2 mt-2">
                                <input type="file" class="form-control bg-secondary border-0 text-light" id="productImageFile" accept="image/*" multiple>
                                <button class="btn btn-outline-orange" id="uploadProductImageBtn" type="button"><i class="bi bi-upload"></i></button>
                            </div>
                            <small class="field-hint">Supports bulk image uploads with visual parser/compressor progress.</small>
                        </div>
                        <div class="mb-3">
                            <label for="productVideo" class="form-label text-light">Optional Video Path</label>
                            <input type="text" class="form-control bg-secondary border-0 text-light" id="productVideo" placeholder="frontend/assets/videos/products/...mp4">
                            <div class="d-flex gap-2 mt-2">
                                <input type="file" class="form-control bg-secondary border-0 text-light" id="productVideoFile" accept="video/mp4,video/webm,video/ogg" multiple>
                                <button class="btn btn-outline-orange" id="uploadProductVideoBtn" type="button"><i class="bi bi-upload"></i></button>
                            </div>
                            <small class="field-hint">Supports bulk video uploads with queued processing status.</small>
                        </div>
                        <div class="mb-3">
                            <label for="productDescription" class="form-label text-light">Description</label>
                            <textarea class="form-control bg-secondary border-0 text-light" id="productDescription" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-orange" id="saveProductBtn">Save Product</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminDialogModal" tabindex="-1" aria-labelledby="adminDialogTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border border-secondary">
                <div class="modal-header border-bottom border-secondary">
                    <h3 class="h5 modal-title text-orange fw-bold" id="adminDialogTitle">Confirm Action</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-light mb-2" id="adminDialogMessage"></p>
                    <div id="adminDialogInputWrap" class="d-none mt-3">
                        <label for="adminDialogInput" class="form-label text-light">Add a note</label>
                        <textarea id="adminDialogInput" class="form-control bg-secondary border-0 text-light" rows="3" maxlength="500" placeholder="Type here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary" id="adminDialogCancelBtn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-orange" id="adminDialogOkBtn">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <script <?= commerza_csp_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous" onerror="this.onerror=null;this.src='../../frontend/assets/vendor/bootstrap/bootstrap.bundle.min.js'"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="https://code.jquery.com/jquery-3.7.1.min.js"
        onerror="this.onerror=null;this.src='../../frontend/assets/vendor/jquery/jquery-3.7.1.min.js'"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
        onerror="this.onerror=null;this.src='../../frontend/assets/vendor/chart/chart.umd.min.js'"></script>
    <script id="commerzaAdminRuntimeData" type="application/json">
        <?= json_encode([
            'csrfToken' => $adminCsrfToken,
            'permissions' => $adminPermissions,
            'hiddenTabs' => $adminHiddenTabs,
            'permissionCatalog' => $adminPermissionCatalog,
            'tabCatalog' => $adminTabCatalog,
            'admin' => [
                'id' => (int)($adminUser['id'] ?? 0),
                'name' => $adminDisplayName,
                'email' => $adminDisplayEmail,
                'role' => $adminRoleKey,
                'roleLabel' => $adminRoleLabel,
                'permissions' => $adminPermissions,
                'hiddenTabs' => $adminHiddenTabs,
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    </script>
    <script src="assets/js/pages/panel/admin-panel.js"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/core/runtime-state.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/core/common-utils.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/core/upload-queue.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/live-viewers/settings.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/email/directory-templates.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/email/init.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/coupons/workflows.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/reviews/management.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/security/events-init.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/analytics/dashboard-metrics.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/products/catalog-storage.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/products/catalog-management.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/products/import-export.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/ui/dialogs-notifications.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/ui/tab-guidance.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/analytics/summary-render.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/analytics/charts-orders-customers.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/operations/orders-customers-blacklist.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/website/content-seo-social.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/init/document-ready.js?v=<?= (int)$adminJsVersion ?>"></script>
    <script <?= commerza_csp_nonce_attr() ?> src="assets/js/modules/panel/sub-admins.js?v=<?= (int)$adminSubAdminsJsVersion ?>"></script>
</body>

</html>