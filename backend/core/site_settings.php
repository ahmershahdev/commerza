<?php

function commerza_site_setting_cache_ttl(): int
{
    static $ttl = null;

    if (is_int($ttl) && $ttl > 0) {
        return $ttl;
    }

    $raw = getenv('COMMERZA_SITE_SETTINGS_CACHE_TTL');
    $candidate = $raw === false ? 300 : (int)$raw;
    if ($candidate < 30) {
        $candidate = 30;
    }

    if ($candidate > 3600) {
        $candidate = 3600;
    }

    $ttl = $candidate;
    return $ttl;
}

function commerza_site_setting_query_value(mysqli $con, string $normalizedKey): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('s', $normalizedKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return '';
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value;
}

function commerza_site_setting_value(mysqli $con, string $key, string $fallback = ''): string
{
    $normalizedKey = strtolower(trim($key));
    if ($normalizedKey === '') {
        return $fallback;
    }

    $value = '';
    if (function_exists('commerza_cache_remember')) {
        $cached = commerza_cache_remember(
            'site-setting:' . $normalizedKey,
            commerza_site_setting_cache_ttl(),
            static function () use ($con, $normalizedKey): string {
                return commerza_site_setting_query_value($con, $normalizedKey);
            }
        );

        $value = trim((string)$cached);
    } else {
        $value = commerza_site_setting_query_value($con, $normalizedKey);
    }

    return $value !== '' ? $value : $fallback;
}

function commerza_public_table_exists(mysqli $con, string $table): bool
{
    static $cache = [];

    $normalized = strtolower(trim($table));
    if ($normalized === '' || preg_match('/^[a-z0-9_]+$/', $normalized) !== 1) {
        return false;
    }

    if (array_key_exists($normalized, $cache)) {
        return (bool)$cache[$normalized];
    }

    $safeTable = $con->real_escape_string($normalized);
    $result = $con->query("SHOW TABLES LIKE '{$safeTable}'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $cache[$normalized] = $exists;

    return $exists;
}

function commerza_public_ticker_messages(mysqli $con): array
{
    if (!commerza_public_table_exists($con, 'ticker')) {
        return [];
    }

    $messages = [];
    $result = $con->query(
        'SELECT message
         FROM ticker
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 20'
    );

    if (!$result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $message = trim((string)($row['message'] ?? ''));
        if ($message !== '') {
            $messages[] = $message;
        }
    }

    return $messages;
}

function commerza_public_collectors_speak(mysqli $con): array
{
    if (!commerza_public_table_exists($con, 'collectors_speak')) {
        return [];
    }

    $rows = [];
    $result = $con->query(
        'SELECT name, tagline, quote
         FROM collectors_speak
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 20'
    );

    if (!$result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $name = trim((string)($row['name'] ?? ''));
        $tagline = trim((string)($row['tagline'] ?? ''));
        $quote = trim((string)($row['quote'] ?? ''));

        if ($name === '' || $quote === '') {
            continue;
        }

        $rows[] = [
            'name' => $name,
            'tagline' => $tagline,
            'quote' => $quote,
        ];
    }

    return $rows;
}

function commerza_build_public_site_settings_payload(mysqli $con): array
{
    return [
        'brand' => [
            'name' => commerza_site_setting_value($con, 'site_name', 'COMMERZA'),
            'logo' => commerza_site_setting_value(
                $con,
                'logo_url',
                'frontend/assets/images/logo/commerza_logo.svg'
            ),
            'favicon' => commerza_site_setting_value(
                $con,
                'favicon_url',
                'frontend/assets/images/favicon/commerza-watches-icon.ico'
            ),
        ],
        'contact' => [
            'address' => commerza_site_setting_value($con, 'site_address', ''),
            'email' => commerza_site_setting_value($con, 'site_email', ''),
            'phone' => commerza_site_setting_value($con, 'site_phone', ''),
        ],
        'ticker' => [
            'enabled' => commerza_site_setting_value($con, 'ticker_enabled', '1') !== '0',
            'messages' => commerza_public_ticker_messages($con),
        ],
        'collectorsSpeak' => commerza_public_collectors_speak($con),
        'socialLinks' => [],
        'sliderImages' => [],
        'featuredVideos' => [
            'home' => commerza_site_setting_value(
                $con,
                'home_feature_video',
                'frontend/assets/videos/slider/steel_watch_1.mp4'
            ),
            'categoryA' => commerza_site_setting_value(
                $con,
                'category_a_feature_video',
                'frontend/assets/videos/products/smart/automatic_watches_carousel.mp4'
            ),
        ],
    ];
}
