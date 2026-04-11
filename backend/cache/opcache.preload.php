<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);

$preloadFiles = [
    $baseDir . '/backend/core/data.php',
    $baseDir . '/backend/core/bootstrap_helpers.php',
    $baseDir . '/backend/core/meta_normalizer.php',
    $baseDir . '/backend/core/site_settings.php',
    $baseDir . '/backend/core/identity_schema.php',
    $baseDir . '/backend/core/remember_sessions.php',
    $baseDir . '/backend/cache/cache_helpers.php',
    $baseDir . '/backend/security/security_helpers.php',
    $baseDir . '/backend/security/rate_limit.php',
    $baseDir . '/backend/security/security_events.php',
    $baseDir . '/backend/mailer/mailer.php',
    $baseDir . '/backend/helpers/notifications.php',
    $baseDir . '/backend/helpers/nav_state.php',
    $baseDir . '/backend/helpers/cart_helpers.php',
    $baseDir . '/backend/helpers/coupon_helpers.php',
    $baseDir . '/backend/helpers/products_schema_helpers.php',
    $baseDir . '/backend/helpers/media_image_helpers.php',
    $baseDir . '/admin/backend/auth.php',
    $baseDir . '/admin/backend/orders_api.php',
    $baseDir . '/admin/backend/website_api.php',
    $baseDir . '/backend/api/viewers_api.php',
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
