<?php

function commerza_collect_preload_assets(string $buffer): array
{
    $assets = [];
    $seen = [];

    $styleTags = [];
    if (preg_match_all('/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*\bhref\s*=\s*(["\'])([^"\']+)\2[^>]*>/i', $buffer, $styleTags, PREG_SET_ORDER) === 1 || !empty($styleTags)) {
        foreach ($styleTags as $tag) {
            $href = trim((string)($tag[3] ?? ''));
            if ($href === '') {
                continue;
            }

            $key = 'style|' . strtolower($href);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $assets[] = [
                'as' => 'style',
                'href' => $href,
            ];

            if (count($assets) >= 3) {
                break;
            }
        }
    }

    // Keep preload scoped to styles only; generic script preloads can trigger
    // low-value warnings when scripts execute after window load.

    return $assets;
}

function commerza_inject_preload_links(string $buffer): string
{
    if ($buffer === '' || stripos($buffer, '</head>') === false) {
        return $buffer;
    }

    if (stripos($buffer, 'rel="preload"') !== false) {
        return $buffer;
    }

    $assets = commerza_collect_preload_assets($buffer);
    if (empty($assets)) {
        return $buffer;
    }

    $tags = [];
    foreach ($assets as $asset) {
        $href = trim((string)($asset['href'] ?? ''));
        $as = strtolower(trim((string)($asset['as'] ?? '')));

        if ($href === '' || !in_array($as, ['style', 'script', 'image', 'font'], true)) {
            continue;
        }

        $isExternal = preg_match('#^(https?:)?//#i', $href) === 1;
        $needsCrossOrigin = $isExternal && in_array($as, ['font', 'fetch'], true);
        $tag = '<link rel="preload" as="' . htmlspecialchars($as, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
        if ($needsCrossOrigin) {
            $tag .= ' crossorigin="anonymous"';
        }
        $tag .= '>';
        $tags[] = $tag;
    }

    if (empty($tags)) {
        return $buffer;
    }

    $html = "\n  " . implode("\n  ", $tags) . "\n";
    return preg_replace('/<\/head>/i', $html . '</head>', $buffer, 1) ?? $buffer;
}

function commerza_optimize_stylesheet_links(string $buffer): string
{
    return preg_replace_callback(
        '/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $href = '';
            if (preg_match('/\bhref\s*=\s*(["\'])([^"\']+)\1/i', $tag, $hrefMatch) === 1) {
                $href = (string)($hrefMatch[2] ?? '');
            }

            $fallbackHref = $href !== '' ? commerza_cdn_fallback_asset_for_url($href) : '';
            if ($fallbackHref !== '' && preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $safeFallback = htmlspecialchars($fallbackHref, ENT_QUOTES, 'UTF-8');
                $tag = commerza_insert_tag_attribute(
                    $tag,
                    'onerror="this.onerror=null;this.href=\'' . $safeFallback . '\'"'
                );
            }

            if (
                preg_match('/\bmedia\s*=\s*(["\'])print\1/i', $tag) === 1
                || preg_match('/\bonload\s*=\s*(["\'])[^"\']*this\.media\s*=\s*["\']all["\'][^"\']*\1/i', $tag) === 1
            ) {
                return $tag;
            }

            if (preg_match('/\bmedia\s*=\s*(["\']).*?\1/i', $tag) === 1) {
                return $tag;
            }

            $tag = commerza_insert_tag_attribute($tag, 'media="print"');
            $tag = commerza_insert_tag_attribute($tag, 'onload="this.media=\'all\'"');

            return $tag;
        },
        $buffer
    ) ?? $buffer;
}

function commerza_optimize_head_script_defer(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    $headMatch = [];
    if (preg_match('/<head\b[^>]*>.*<\/head>/is', $buffer, $headMatch, PREG_OFFSET_CAPTURE) !== 1) {
        return $buffer;
    }

    $headHtml = (string)($headMatch[0][0] ?? '');
    $headOffset = (int)($headMatch[0][1] ?? 0);
    if ($headHtml === '') {
        return $buffer;
    }

    $optimizedHead = preg_replace_callback(
        '/<script\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1[^>]*>\s*<\/script>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $srcRaw = (string)($matches[2] ?? '');
            $fallbackSrc = commerza_cdn_fallback_asset_for_url($srcRaw);
            if ($fallbackSrc !== '' && preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $safeFallback = htmlspecialchars($fallbackSrc, ENT_QUOTES, 'UTF-8');
                $tag = commerza_insert_tag_attribute(
                    $tag,
                    'onerror="this.onerror=null;this.src=\'' . $safeFallback . '\'"'
                );
            }

            if (
                preg_match('/\b(?:defer|async)\b/i', $tag) === 1
                || preg_match('/\btype\s*=\s*(["\'])module\1/i', $tag) === 1
            ) {
                return $tag;
            }

            $src = strtolower(trim(html_entity_decode($srcRaw, ENT_QUOTES, 'UTF-8')));
            if (
                $src === ''
                || str_contains($src, 'jquery')
                || str_contains($src, 'recaptcha')
                || str_contains($src, 'captcha')
                || str_contains($src, 'googletagmanager.com')
                || str_contains($src, 'google-analytics.com')
            ) {
                return $tag;
            }

            $tagEnd = strpos($tag, '>');
            if ($tagEnd === false) {
                return $tag;
            }

            return substr($tag, 0, $tagEnd) . ' defer' . substr($tag, $tagEnd);
        },
        $headHtml
    ) ?? $headHtml;

    if ($optimizedHead === $headHtml) {
        return $buffer;
    }

    return substr($buffer, 0, $headOffset)
        . $optimizedHead
        . substr($buffer, $headOffset + strlen($headHtml));
}

function commerza_optimize_script_tag_fallbacks(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    return preg_replace_callback(
        '/<script\b[^>]*\bsrc\s*=\s*(["\'])([^"\']+)\1[^>]*>\s*<\/script>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '' || preg_match('/\bonerror\s*=\s*(["\']).*?\1/i', $tag) === 1) {
                return $tag;
            }

            $srcRaw = (string)($matches[2] ?? '');
            if ($srcRaw === '') {
                return $tag;
            }

            $fallbackSrc = commerza_cdn_fallback_asset_for_url($srcRaw);
            if ($fallbackSrc === '') {
                return $tag;
            }

            $safeFallback = htmlspecialchars($fallbackSrc, ENT_QUOTES, 'UTF-8');
            return commerza_insert_tag_attribute(
                $tag,
                'onerror="this.onerror=null;this.src=\'' . $safeFallback . '\'"'
            );
        },
        $buffer
    ) ?? $buffer;
}

function commerza_guess_image_alt_from_src(string $src): string
{
    $path = (string)(parse_url($src, PHP_URL_PATH) ?? $src);
    $filename = basename($path);
    $name = preg_replace('/\.[a-z0-9]+$/i', '', $filename);
    $name = is_string($name) ? $name : '';
    $name = trim(str_replace(['-', '_'], ' ', $name));
    $name = preg_replace('/\s+/', ' ', $name) ?: '';

    if ($name === '') {
        return 'Image';
    }

    $name = ucwords(strtolower($name));
    return $name !== '' ? $name : 'Image';
}

function commerza_optimize_image_loading(string $buffer): string
{
    return preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function (array $matches): string {
            $tag = (string)($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $insert = '';
            if (preg_match('/\bloading\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $insert .= ' loading="lazy"';
            }
            if (preg_match('/\bdecoding\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $insert .= ' decoding="async"';
            }

            if (preg_match('/\balt\s*=\s*(["\']).*?\1/i', $tag) !== 1) {
                $src = '';
                if (preg_match('/\bsrc\s*=\s*(["\'])([^"\']+)\1/i', $tag, $srcMatch) === 1) {
                    $src = (string)($srcMatch[2] ?? '');
                }
                $alt = commerza_guess_image_alt_from_src($src);
                $insert .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
            }

            if ($insert === '') {
                return $tag;
            }

            if (preg_match('/\s*\/>$/', $tag) === 1) {
                return preg_replace('/\s*\/>$/', $insert . ' />', $tag, 1) ?? $tag;
            }

            return preg_replace('/\s*>$/', $insert . '>', $tag, 1) ?? $tag;
        },
        $buffer
    ) ?? $buffer;
}

function commerza_placeholder_enhancer_style_tag(): string
{
    $href = commerza_local_vendor_prefix() . 'frontend/assets/css/modules/placeholder-enhancer.css';
    return '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" id="commerzaPlaceholderStyle">';
}

function commerza_placeholder_enhancer_script_tag(): string
{
    $script = <<<'JS'
(function () {
    function normalizeText(value) {
        return (value || "")
            .toString()
            .replace(/\*/g, "")
            .replace(/\s+/g, " ")
            .replace(/[\s:]+$/g, "")
            .trim();
    }

    function findLabelText(field) {
        var fieldId = (field.getAttribute("id") || "").trim();
        if (!fieldId) {
            return "";
        }

        if (window.CSS && typeof window.CSS.escape === "function") {
            var escapedId = window.CSS.escape(fieldId);
            var escapedLabel = document.querySelector('label[for="' + escapedId + '"]');
            if (escapedLabel) {
                return normalizeText(escapedLabel.textContent);
            }
        }

        var labels = document.querySelectorAll("label[for]");
        for (var index = 0; index < labels.length; index += 1) {
            var current = labels[index];
            if ((current.getAttribute("for") || "") === fieldId) {
                return normalizeText(current.textContent);
            }
        }

        return "";
    }

    function placeholderByType(type) {
        var presets = {
            email: "Enter your email",
            password: "Enter your password",
            tel: "Enter your phone number",
            search: "Search here",
            url: "Enter a valid URL",
            number: "Enter a value"
        };
        return presets[type] || "";
    }

    function placeholderForField(field) {
        var type = (field.getAttribute("type") || "text").toLowerCase();
        var byType = placeholderByType(type);
        if (byType) {
            return byType;
        }

        var byLabel = findLabelText(field);
        if (byLabel) {
            return "Enter " + byLabel.toLowerCase();
        }

        var ariaLabel = normalizeText(field.getAttribute("aria-label") || "");
        if (ariaLabel) {
            return "Enter " + ariaLabel.toLowerCase();
        }

        var fieldName = normalizeText(field.getAttribute("name") || "").replace(/[_-]+/g, " ");
        if (fieldName) {
            return "Enter " + fieldName.toLowerCase();
        }

        return "Enter value";
    }

    function applyPlaceholders() {
        var skipped = {
            hidden: true,
            submit: true,
            button: true,
            checkbox: true,
            radio: true,
            file: true,
            image: true,
            reset: true,
            color: true,
            range: true,
            date: true,
            time: true,
            "datetime-local": true,
            month: true,
            week: true
        };

        document.querySelectorAll("input,textarea").forEach(function (field) {
            if (!field || field.hasAttribute("placeholder") || field.disabled) {
                return;
            }

            if (field.tagName === "INPUT") {
                var type = (field.getAttribute("type") || "text").toLowerCase();
                if (skipped[type]) {
                    return;
                }
            }

            field.setAttribute("placeholder", placeholderForField(field));
            field.dataset.commerzaPlaceholderAuto = "1";
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", applyPlaceholders, { once: true });
    } else {
        applyPlaceholders();
    }
})();
JS;

    return '<script ' . commerza_csp_nonce_attr() . ' id="commerzaPlaceholderEnhancer">'
        . $script
        . '</script>';
}

function commerza_frontend_manual_stylesheet_links_html(): string
{
    static $html = null;

    if (is_string($html) && $html !== '') {
        return $html;
    }

    $paths = [
        'frontend/assets/css/modules/base.css',
        'frontend/assets/css/modules/navigation.css',
        'frontend/assets/css/modules/search.css',
        'frontend/assets/css/modules/carousel.css',
        'frontend/assets/css/modules/products.css',
        'frontend/assets/css/modules/footer.css',
        'frontend/assets/css/modules/layout-sections.css',
        'frontend/assets/css/modules/newsletter.css',
        'frontend/assets/css/modules/wishlist-tracking.css',
        'frontend/assets/css/modules/search-suggestions.css',
        'frontend/assets/css/modules/offcanvas.css',
        'frontend/assets/css/modules/breadcrumbs.css',
        'frontend/assets/css/modules/page-hero-wishlist.css',
    ];

    $tags = [];
    foreach ($paths as $path) {
        $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '">';
    }

    $html = implode("\n  ", $tags);
    return $html;
}

function commerza_site_settings_inline_json_tag(): string
{
    $payload = $GLOBALS['commerza_public_site_settings_payload'] ?? null;
    if (!is_array($payload) || empty($payload)) {
        return '';
    }

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
    );

    if (!is_string($json) || $json === '') {
        return '';
    }

    return '<script ' . commerza_csp_nonce_attr() . ' id="commerzaSiteSettingsData" type="application/json">'
        . $json
        . '</script>';
}

function commerza_head_upsert_title(string $buffer, string $title): string
{
    $value = trim($title);
    if ($value === '') {
        return $buffer;
    }

    $safe = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    if (preg_match('/<title>.*?<\/title>/is', $buffer) === 1) {
        return preg_replace('/<title>.*?<\/title>/is', '<title>' . $safe . '</title>', $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  <title>{$safe}</title>\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_meta_name(string $buffer, string $name, string $content): string
{
    $metaName = strtolower(trim($name));
    $value = trim($content);
    if ($metaName === '' || $value === '') {
        return $buffer;
    }

    $safeName = preg_replace('/[^a-z0-9:_-]/i', '', $metaName) ?: '';
    if ($safeName === '') {
        return $buffer;
    }

    $tag = '<meta name="' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<meta\b[^>]*\bname\s*=\s*(["\'])' . preg_quote($safeName, '/') . '\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_meta_property(string $buffer, string $property, string $content): string
{
    $metaProperty = strtolower(trim($property));
    $value = trim($content);
    if ($metaProperty === '' || $value === '') {
        return $buffer;
    }

    $safeProperty = preg_replace('/[^a-z0-9:_-]/i', '', $metaProperty) ?: '';
    if ($safeProperty === '') {
        return $buffer;
    }

    $tag = '<meta property="' . htmlspecialchars($safeProperty, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<meta\b[^>]*\bproperty\s*=\s*(["\'])' . preg_quote($safeProperty, '/') . '\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_head_upsert_canonical(string $buffer, string $canonical): string
{
    $value = trim($canonical);
    if ($value === '') {
        return $buffer;
    }

    $tag = '<link rel="canonical" href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    $pattern = '/<link\b[^>]*\brel\s*=\s*(["\'])canonical\1[^>]*>/i';

    if (preg_match($pattern, $buffer) === 1) {
        return preg_replace($pattern, $tag, $buffer, 1) ?? $buffer;
    }

    return preg_replace('/<\/head>/i', "\n  {$tag}\n</head>", $buffer, 1) ?? $buffer;
}

function commerza_current_page_meta_key(): string
{
    $script = strtolower(trim((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''))));
    return $script !== '' ? $script : 'index.php';
}

function commerza_page_meta_overrides(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $cached = [];
    $con = $GLOBALS['con'] ?? null;
    if (!($con instanceof mysqli)) {
        return $cached;
    }

    $page = commerza_current_page_meta_key();

    $stmt = $con->prepare(
        'SELECT page, meta_title, meta_description, canonical_url, og_title, og_description, og_image, json_ld
         FROM page_meta
         WHERE page = ?
         LIMIT 1'
    );

    if (!$stmt) {
        $stmt = $con->prepare(
            'SELECT page, meta_title, meta_description
             FROM page_meta
             WHERE page = ?
             LIMIT 1'
        );
    }

    if (!$stmt) {
        return $cached;
    }

    $stmt->bind_param('s', $page);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return $cached;
    }

    $cached = [
        'meta_title' => trim((string)($row['meta_title'] ?? '')),
        'meta_description' => trim((string)($row['meta_description'] ?? '')),
        'canonical_url' => trim((string)($row['canonical_url'] ?? '')),
        'og_title' => trim((string)($row['og_title'] ?? '')),
        'og_description' => trim((string)($row['og_description'] ?? '')),
        'og_image' => trim((string)($row['og_image'] ?? '')),
        'json_ld' => trim((string)($row['json_ld'] ?? '')),
    ];

    return $cached;
}

function commerza_html_meta_normalize(string $buffer): string
{
    if ($buffer === '') {
        return $buffer;
    }

    $base = commerza_public_base_url();
    $buffer = str_replace(
        ['https://commerza.ahmershah.dev', 'http://commerza.ahmershah.dev'],
        $base,
        $buffer
    );

    $buffer = preg_replace(
        '/frontend\/assets\/js\/global-protection\.js(?!\?v=)/i',
        'frontend/assets/js/modules/core/global-protection.js?v=20260408',
        $buffer
    ) ?? $buffer;

    if (stripos($buffer, '</head>') === false) {
        return $buffer;
    }

    if (stripos($buffer, '<base ') === false) {
        $baseHref = htmlspecialchars(rtrim(commerza_public_base_url(), '/') . '/', ENT_QUOTES, 'UTF-8');
        $baseTag = "\n  <base href=\"{$baseHref}\">\n";
        $buffer = preg_replace_callback('/<head(\\s[^>]*)?>/i', static function (array $matches) use ($baseTag): string {
            return $matches[0] . $baseTag;
        }, $buffer, 1) ?? $buffer;
    }

    $manualStyles = commerza_frontend_manual_stylesheet_links_html();
    $buffer = preg_replace_callback(
        '/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*\bhref\s*=\s*(["\'])([^"\']+)\2[^>]*>/i',
        static function (array $matches) use ($manualStyles): string {
            $tag = (string)($matches[0] ?? '');
            $href = html_entity_decode(strtolower(trim((string)($matches[3] ?? ''))), ENT_QUOTES, 'UTF-8');

            if ($href === '' || !str_contains($href, 'frontend/assets/css/style.css')) {
                return $tag;
            }

            if (str_contains($href, 'admin/frontend/assets/css/style.css')) {
                return $tag;
            }

            return $manualStyles;
        },
        $buffer,
        1
    ) ?? $buffer;

    $buffer = commerza_optimize_stylesheet_links($buffer);
    $buffer = commerza_optimize_head_script_defer($buffer);
    $buffer = commerza_optimize_script_tag_fallbacks($buffer);
    $buffer = commerza_optimize_image_loading($buffer);
    $buffer = commerza_inject_preload_links($buffer);

    if (stripos($buffer, 'id="commerzaPlaceholderStyle"') === false) {
        $placeholderStyle = commerza_placeholder_enhancer_style_tag();
        $buffer = preg_replace('/<\/head>/i', "\n  {$placeholderStyle}\n</head>", $buffer, 1) ?? $buffer;
    }

    if (stripos($buffer, 'id="commerzaPlaceholderEnhancer"') === false) {
        $placeholderScript = commerza_placeholder_enhancer_script_tag();
        if (stripos($buffer, '</body>') !== false) {
            $buffer = preg_replace('/<\/body>/i', "\n  {$placeholderScript}\n</body>", $buffer, 1) ?? $buffer;
        } else {
            $buffer = preg_replace('/<\/head>/i', "\n  {$placeholderScript}\n</head>", $buffer, 1) ?? $buffer;
        }
    }

    $metaOverrides = commerza_page_meta_overrides();
    if (!empty($metaOverrides)) {
        $titleOverride = trim((string)($metaOverrides['meta_title'] ?? ''));
        $descriptionOverride = trim((string)($metaOverrides['meta_description'] ?? ''));
        $canonicalOverride = trim((string)($metaOverrides['canonical_url'] ?? ''));
        $ogTitleOverride = trim((string)($metaOverrides['og_title'] ?? ''));
        $ogDescriptionOverride = trim((string)($metaOverrides['og_description'] ?? ''));
        $ogImageOverride = trim((string)($metaOverrides['og_image'] ?? ''));
        $jsonLdOverride = trim((string)($metaOverrides['json_ld'] ?? ''));

        if ($canonicalOverride !== '' && preg_match('#^https?://#i', $canonicalOverride) !== 1) {
            $canonicalOverride = commerza_absolute_url('/' . ltrim($canonicalOverride, '/'));
        }

        if ($ogImageOverride !== '' && preg_match('#^https?://#i', $ogImageOverride) !== 1) {
            $ogImageOverride = commerza_absolute_url('/' . ltrim($ogImageOverride, '/'));
        }

        if ($titleOverride !== '') {
            $buffer = commerza_head_upsert_title($buffer, $titleOverride);
        }

        if ($descriptionOverride !== '') {
            $buffer = commerza_head_upsert_meta_name($buffer, 'description', $descriptionOverride);
        }

        if ($canonicalOverride !== '') {
            $buffer = commerza_head_upsert_canonical($buffer, $canonicalOverride);
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:url', $canonicalOverride);
        }

        if ($ogTitleOverride === '' && $titleOverride !== '') {
            $ogTitleOverride = $titleOverride;
        }
        if ($ogDescriptionOverride === '' && $descriptionOverride !== '') {
            $ogDescriptionOverride = $descriptionOverride;
        }

        if ($ogTitleOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:title', $ogTitleOverride);
        }

        if ($ogDescriptionOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:description', $ogDescriptionOverride);
        }

        if ($ogImageOverride !== '') {
            $buffer = commerza_head_upsert_meta_property($buffer, 'og:image', $ogImageOverride);
        }

        if ($jsonLdOverride !== '') {
            $decodedJsonLd = json_decode($jsonLdOverride, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJsonLd)) {
                $jsonLd = json_encode(
                    $decodedJsonLd,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                );

                if (is_string($jsonLd) && $jsonLd !== '') {
                    $buffer = preg_replace(
                        '/<script\b[^>]*\bid="commerzaSeoJsonLd"[^>]*>.*?<\/script>/is',
                        '',
                        $buffer
                    ) ?? $buffer;

                    $customJsonLdTag = "\n  <script " . commerza_csp_nonce_attr() . " id=\"commerzaSeoJsonLd\" type=\"application/ld+json\">\n"
                        . $jsonLd
                        . "\n  </script>\n";

                    $buffer = preg_replace('/<\/head>/i', $customJsonLdTag . '</head>', $buffer, 1) ?? $buffer;
                }
            }
        }
    }

    if (
        stripos($buffer, 'name="twitter:card"') === false
        && preg_match('/property="og:title"\s+content="([^"]*)"/i', $buffer, $ogTitle)
        && preg_match('/property="og:description"\s+content="([^"]*)"/i', $buffer, $ogDesc)
        && preg_match('/property="og:image"\s+content="([^"]*)"/i', $buffer, $ogImage)
    ) {
        $twitter = "\n  <meta name=\"twitter:card\" content=\"summary_large_image\">"
            . "\n  <meta name=\"twitter:title\" content=\"{$ogTitle[1]}\">"
            . "\n  <meta name=\"twitter:description\" content=\"{$ogDesc[1]}\">"
            . "\n  <meta name=\"twitter:image\" content=\"{$ogImage[1]}\">\n";
        $buffer = preg_replace('/<\/head>/i', $twitter . '</head>', $buffer, 1) ?? $buffer;
    }

    if (
        stripos($buffer, 'rel="canonical"') === false
        && preg_match('/property="og:url"\s+content="([^"]*)"/i', $buffer, $ogUrl)
    ) {
        $canonical = "\n  <link rel=\"canonical\" href=\"{$ogUrl[1]}\" />\n";
        $buffer = preg_replace('/<\/head>/i', $canonical . '</head>', $buffer, 1) ?? $buffer;
    }

    if (stripos($buffer, 'id="commerzaSiteSettingsData"') === false) {
        $settingsScript = commerza_site_settings_inline_json_tag();
        if ($settingsScript !== '') {
            $buffer = preg_replace('/<\/head>/i', "\n  {$settingsScript}\n</head>", $buffer, 1) ?? $buffer;
        }
    }

    $hasOrganizationSchema = preg_match('/"@type"\s*:\s*"Organization"/i', $buffer) === 1;
    $hasWebsiteSchema = preg_match('/"@type"\s*:\s*"WebSite"/i', $buffer) === 1;
    $hasPageSchema = preg_match('/"@type"\s*:\s*"(WebPage|FAQPage|Product|CollectionPage|ItemList|Article|ContactPage|AboutPage|CheckoutPage|ProfilePage|SearchResultsPage)"/i', $buffer) === 1;

    $title = 'Commerza';
    if (preg_match('/<title>(.*?)<\/title>/is', $buffer, $titleMatch)) {
        $titleText = trim(strip_tags(html_entity_decode((string)$titleMatch[1], ENT_QUOTES, 'UTF-8')));
        if ($titleText !== '') {
            $title = $titleText;
        }
    }

    $description = 'Commerza premium watches and ecommerce experience.';
    if (preg_match('/<meta\s+name="description"\s+content="([^"]*)"/i', $buffer, $descriptionMatch)) {
        $descriptionText = trim(html_entity_decode((string)$descriptionMatch[1], ENT_QUOTES, 'UTF-8'));
        if ($descriptionText !== '') {
            $description = $descriptionText;
        }
    }

    $pageUrl = '';
    if (preg_match('/<link\s+rel="canonical"\s+href="([^"]*)"/i', $buffer, $canonicalMatch)) {
        $pageUrl = trim((string)$canonicalMatch[1]);
    }
    if ($pageUrl === '' && preg_match('/property="og:url"\s+content="([^"]*)"/i', $buffer, $ogUrlMatch)) {
        $pageUrl = trim((string)$ogUrlMatch[1]);
    }
    if ($pageUrl === '') {
        $scriptName = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $pageUrl = rtrim(commerza_public_base_url(), '/') . '/';
        if ($scriptName !== '') {
            $pageUrl .= $scriptName;
        }
    }

    $pageImage = rtrim(commerza_public_base_url(), '/') . '/frontend/assets/images/logo/commerza-logo.webp';
    if (preg_match('/property="og:image"\s+content="([^"]*)"/i', $buffer, $ogImageMatch)) {
        $candidateImage = trim((string)$ogImageMatch[1]);
        if ($candidateImage !== '') {
            $pageImage = $candidateImage;
        }
    }

    $graph = [];

    if (!$hasOrganizationSchema) {
        $graph[] = [
            '@type' => 'Organization',
            '@id' => rtrim(commerza_public_base_url(), '/') . '/#organization',
            'name' => 'Commerza',
            'url' => rtrim(commerza_public_base_url(), '/') . '/',
            'logo' => $pageImage,
            'sameAs' => [
                'https://www.facebook.com/commerza.ahmer',
                'https://www.instagram.com/commerza.ahmer',
                'https://x.com/commerza_ahmer',
            ],
        ];
    }

    if (!$hasWebsiteSchema) {
        $graph[] = [
            '@type' => 'WebSite',
            '@id' => rtrim(commerza_public_base_url(), '/') . '/#website',
            'name' => 'Commerza',
            'url' => rtrim(commerza_public_base_url(), '/') . '/',
            'publisher' => [
                '@id' => rtrim(commerza_public_base_url(), '/') . '/#organization',
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => rtrim(commerza_public_base_url(), '/') . '/products.php?name={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    if (!$hasPageSchema) {
        $graph[] = [
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $pageUrl,
            'description' => $description,
            'isPartOf' => [
                '@id' => rtrim(commerza_public_base_url(), '/') . '/#website',
            ],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $pageImage,
            ],
        ];
    }

    if (!empty($graph)) {
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (is_string($jsonLd) && $jsonLd !== '') {
            $script = "\n  <script " . commerza_csp_nonce_attr() . " type=\"application/ld+json\">\n"
                . $jsonLd
                . "\n  </script>\n";
            $buffer = preg_replace('/<\/head>/i', $script . '</head>', $buffer, 1) ?? $buffer;
        }
    }

    return $buffer;
}

function commerza_enable_meta_normalizer(): void
{
    static $enabled = false;

    if ($enabled || PHP_SAPI === 'cli' || commerza_is_backend_request()) {
        return;
    }

    $enabled = true;
    ob_start(static fn(string $buffer): string => commerza_html_meta_normalize($buffer));
}

