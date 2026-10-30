<?php

/**
 * Shared presentation helpers for storefront pages and template partials.
 */

if (!function_exists('commerza_e')) {
    function commerza_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Brand, contact, and social data resolved once per request from public site settings.
 */
function commerza_site_view_context(): array
{
    static $context = null;
    if (is_array($context)) {
        return $context;
    }

    $settings = $GLOBALS['commerza_public_site_settings_payload'] ?? [];
    $pick = static function ($value, string $fallback): string {
        $value = trim((string)$value);
        return $value !== '' ? $value : $fallback;
    };

    $socialLinks = [];
    foreach ((array)($settings['socialLinks'] ?? []) as $link) {
        $url = trim((string)($link['url'] ?? ''));
        if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
            continue;
        }
        $socialLinks[] = [
            'label' => $pick($link['label'] ?? '', 'Social'),
            'url' => $url,
            'icon' => preg_match('/^[a-z0-9 _-]+$/i', (string)($link['icon'] ?? '')) === 1 ? (string)$link['icon'] : 'bi bi-link-45deg',
        ];
    }

    if ($socialLinks === []) {
        $socialLinks = [
            ['label' => 'Facebook', 'url' => 'https://www.facebook.com/commerza.ahmer', 'icon' => 'bi bi-facebook'],
            ['label' => 'X', 'url' => 'https://x.com/commerza_ahmer', 'icon' => 'bi bi-twitter'],
            ['label' => 'Instagram', 'url' => 'https://www.instagram.com/commerza.ahmer', 'icon' => 'bi bi-instagram'],
        ];
    }

    $context = [
        'brand_name' => $pick($settings['brand']['name'] ?? '', 'Commerza'),
        'logo' => $pick($settings['brand']['logo'] ?? '', 'frontend/assets/images/logo/commerza_logo.svg'),
        'favicon' => $pick($settings['brand']['favicon'] ?? '', 'frontend/assets/images/favicon/commerza-watches-icon.ico'),
        'support_email' => $pick($settings['contact']['email'] ?? '', 'support@ahmershah.dev'),
        'support_phone' => $pick($settings['contact']['phone'] ?? '', '+92 370 4831994'),
        'support_address' => $pick($settings['contact']['address'] ?? '', ''),
        'social_links' => $socialLinks,
    ];

    return $context;
}

/**
 * Render a template partial from templates/partials with an isolated variable scope.
 */
function commerza_partial(string $name, array $vars = []): void
{
    if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
        return;
    }

    $file = COMMERZA_ROOT . '/templates/partials/' . $name . '.php';
    if (!is_file($file)) {
        return;
    }

    (static function (string $__file, array $__vars): void {
        extract($__vars, EXTR_SKIP);
        require $__file;
    })($file, $vars);
}

/**
 * aria-current attribute for navigation links.
 */
function commerza_nav_current(string $activePage, string ...$pages): string
{
    return in_array($activePage, $pages, true) ? ' aria-current="page"' : '';
}
