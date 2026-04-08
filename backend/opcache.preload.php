<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);

$preloadFiles = [
    $baseDir . '/backend/data.php',
    $baseDir . '/backend/security_helpers.php',
    $baseDir . '/backend/rate_limit.php',
    $baseDir . '/backend/security_events.php',
    $baseDir . '/backend/mailer.php',
    $baseDir . '/backend/notifications.php',
    $baseDir . '/backend/nav_state.php',
    $baseDir . '/backend/cart_helpers.php',
    $baseDir . '/backend/coupon_helpers.php',
    $baseDir . '/backend/products_schema_helpers.php',
    $baseDir . '/backend/media_image_helpers.php',
    $baseDir . '/admin/backend/auth.php',
    $baseDir . '/admin/backend/orders_api.php',
    $baseDir . '/admin/backend/website_api.php',
    $baseDir . '/backend/viewers_api.php',
];

if (!function_exists('opcache_compile_file')) {
    return;
}

foreach ($preloadFiles as $filePath) {
    if (!is_string($filePath) || $filePath === '' || !is_file($filePath)) {
        continue;
    }

    @opcache_compile_file($filePath);
}
