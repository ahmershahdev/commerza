<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
    exit;
}

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'website.manage');

function website_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function website_api_column_exists(mysqli $con, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function website_api_index_exists(mysqli $con, string $table, string $indexName): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $indexName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function website_api_page_meta_has_unique_page_index(mysqli $con): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND NON_UNIQUE = 0';

    $table = 'page_meta';
    $column = 'page';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function website_api_ensure_slider_compatibility(mysqli $con): void
{
    if (!website_api_column_exists($con, 'slider', 'subtitle')) {
        $con->query('ALTER TABLE slider ADD COLUMN subtitle VARCHAR(255) DEFAULT NULL AFTER title');
    }

    if (!website_api_column_exists($con, 'slider', 'cta_text_2')) {
        $con->query('ALTER TABLE slider ADD COLUMN cta_text_2 VARCHAR(80) DEFAULT NULL AFTER cta_url');
    }

    if (!website_api_column_exists($con, 'slider', 'cta_url_2')) {
        $con->query('ALTER TABLE slider ADD COLUMN cta_url_2 VARCHAR(255) DEFAULT NULL AFTER cta_text_2');
    }

    if (!website_api_column_exists($con, 'slider', 'overlay_opacity')) {
        $con->query('ALTER TABLE slider ADD COLUMN overlay_opacity DECIMAL(3,2) DEFAULT 0.40 AFTER cta_url_2');
    }

    if (website_api_column_exists($con, 'slider', 'page')) {
        $con->query('ALTER TABLE slider MODIFY page VARCHAR(100) NULL');
    }
}

function website_api_ensure_page_meta_schema(mysqli $con): void
{
    $con->query(
        'CREATE TABLE IF NOT EXISTS page_meta (
            id INT NOT NULL AUTO_INCREMENT,
            page VARCHAR(100) NOT NULL,
            meta_title VARCHAR(150) DEFAULT NULL,
            meta_description VARCHAR(255) DEFAULT NULL,
            canonical_url VARCHAR(255) DEFAULT NULL,
            og_title VARCHAR(150) DEFAULT NULL,
            og_description VARCHAR(255) DEFAULT NULL,
            og_image VARCHAR(255) DEFAULT NULL,
            json_ld MEDIUMTEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_page_meta_page (page),
            KEY idx_page_meta_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    if (!website_api_column_exists($con, 'page_meta', 'canonical_url')) {
        $con->query('ALTER TABLE page_meta ADD COLUMN canonical_url VARCHAR(255) DEFAULT NULL AFTER meta_description');
    }

    if (!website_api_column_exists($con, 'page_meta', 'og_title')) {
        $con->query('ALTER TABLE page_meta ADD COLUMN og_title VARCHAR(150) DEFAULT NULL AFTER canonical_url');
    }

    if (!website_api_column_exists($con, 'page_meta', 'og_description')) {
        $con->query('ALTER TABLE page_meta ADD COLUMN og_description VARCHAR(255) DEFAULT NULL AFTER og_title');
    }

    if (!website_api_column_exists($con, 'page_meta', 'og_image')) {
        $con->query('ALTER TABLE page_meta ADD COLUMN og_image VARCHAR(255) DEFAULT NULL AFTER og_description');
    }

    if (!website_api_column_exists($con, 'page_meta', 'json_ld')) {
        $con->query('ALTER TABLE page_meta ADD COLUMN json_ld MEDIUMTEXT DEFAULT NULL AFTER og_image');
    }

    if (!website_api_page_meta_has_unique_page_index($con)) {
        $con->query('ALTER TABLE page_meta ADD UNIQUE KEY uq_page_meta_page (page)');
    }

    if (!website_api_index_exists($con, 'page_meta', 'idx_page_meta_updated')) {
        $con->query('ALTER TABLE page_meta ADD KEY idx_page_meta_updated (updated_at)');
    }
}

function website_api_ensure_schema(mysqli $con): void
{
    $con->query(
        'CREATE TABLE IF NOT EXISTS social_links (
            id INT NOT NULL AUTO_INCREMENT,
            label VARCHAR(50) NOT NULL,
            url VARCHAR(255) NOT NULL,
            icon VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $con->query(
        'CREATE TABLE IF NOT EXISTS ticker (
            id INT NOT NULL AUTO_INCREMENT,
            message VARCHAR(255) NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            link_text VARCHAR(100) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $con->query(
        'CREATE TABLE IF NOT EXISTS slider (
            id INT NOT NULL AUTO_INCREMENT,
            title VARCHAR(150) DEFAULT NULL,
            subtitle VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            image_url VARCHAR(255) NOT NULL,
            alt_text VARCHAR(255) DEFAULT NULL,
            video_url VARCHAR(255) DEFAULT NULL,
            cta_text VARCHAR(80) DEFAULT NULL,
            cta_url VARCHAR(255) DEFAULT NULL,
            cta_text_2 VARCHAR(80) DEFAULT NULL,
            cta_url_2 VARCHAR(255) DEFAULT NULL,
            overlay_opacity DECIMAL(3,2) DEFAULT 0.40,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    website_api_ensure_slider_compatibility($con);
    website_api_ensure_page_meta_schema($con);
}

function website_api_get_setting(mysqli $con, string $key, string $fallback = ''): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function website_api_upsert_setting(mysqli $con, string $key, string $value, string $label, string $group): bool
{
    $stmt = $con->prepare(
        'INSERT INTO site_settings (setting_key, setting_val, label, setting_group)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            setting_val = VALUES(setting_val),
            label = VALUES(label),
            setting_group = VALUES(setting_group)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssss', $key, $value, $label, $group);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function website_api_clean_text(string $value, int $maxLen): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }

    return $value;
}

function website_api_valid_path(string $value): bool
{
    if ($value === '') {
        return false;
    }

    if (preg_match('#^https?://#i', $value) === 1) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    if (strpos($value, '..') !== false || strpos($value, '\\') !== false) {
        return false;
    }

    return preg_match('#^[a-zA-Z0-9/_\-.]+$#', $value) === 1;
}

function website_api_valid_meta_url(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }

    if (preg_match('#^https?://#i', $value) === 1) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    return website_api_valid_path($value);
}

function website_api_valid_page_key(string $value): bool
{
    return preg_match('/^[a-z0-9][a-z0-9\-]*\.php$/i', $value) === 1;
}

function website_api_project_root(): string
{
    return dirname(__DIR__, 2);
}

function website_api_is_generated_media_path(string $path): bool
{
    $normalized = trim($path);
    if ($normalized === '' || !website_api_valid_path($normalized)) {
        return false;
    }

    if (preg_match('#^https?://#i', $normalized) === 1) {
        return false;
    }

    $allowedPrefixes = [
        'frontend/assets/images/slider/',
        'frontend/assets/images/logo/',
        'frontend/assets/images/favicon/',
        'frontend/assets/images/social/',
        'frontend/assets/videos/slider/',
        'frontend/assets/videos/products/',
    ];

    $prefixAllowed = false;
    foreach ($allowedPrefixes as $prefix) {
        if (strpos($normalized, $prefix) === 0) {
            $prefixAllowed = true;
            break;
        }
    }

    if (!$prefixAllowed) {
        return false;
    }

    $filename = pathinfo($normalized, PATHINFO_FILENAME);
    return preg_match('/-[a-f0-9]{16}$/i', (string)$filename) === 1;
}

function website_api_path_referenced_elsewhere(mysqli $con, string $path): bool
{
    $siteStmt = $con->prepare('SELECT COUNT(*) AS total FROM site_settings WHERE setting_val = ?');
    if (!$siteStmt) {
        return true;
    }

    $siteStmt->bind_param('s', $path);
    $siteStmt->execute();
    $siteRow = $siteStmt->get_result()?->fetch_assoc();
    $siteStmt->close();
    if ((int)($siteRow['total'] ?? 0) > 0) {
        return true;
    }

    $sliderStmt = $con->prepare('SELECT COUNT(*) AS total FROM slider WHERE image_url = ? OR video_url = ?');
    if (!$sliderStmt) {
        return true;
    }

    $sliderStmt->bind_param('ss', $path, $path);
    $sliderStmt->execute();
    $sliderRow = $sliderStmt->get_result()?->fetch_assoc();
    $sliderStmt->close();
    if ((int)($sliderRow['total'] ?? 0) > 0) {
        return true;
    }

    $productStmt = $con->prepare('SELECT COUNT(*) AS total FROM products WHERE image = ? OR video_url = ?');
    if (!$productStmt) {
        return true;
    }

    $productStmt->bind_param('ss', $path, $path);
    $productStmt->execute();
    $productRow = $productStmt->get_result()?->fetch_assoc();
    $productStmt->close();
    if ((int)($productRow['total'] ?? 0) > 0) {
        return true;
    }

    $socialStmt = $con->prepare('SELECT COUNT(*) AS total FROM social_links WHERE icon = ?');
    if (!$socialStmt) {
        return true;
    }

    $socialStmt->bind_param('s', $path);
    $socialStmt->execute();
    $socialRow = $socialStmt->get_result()?->fetch_assoc();
    $socialStmt->close();

    return (int)($socialRow['total'] ?? 0) > 0;
}

function website_api_cleanup_replaced_media(mysqli $con, string $oldPath): void
{
    $normalized = trim($oldPath);
    if (!website_api_is_generated_media_path($normalized)) {
        return;
    }

    if (website_api_path_referenced_elsewhere($con, $normalized)) {
        return;
    }

    $absolutePath = website_api_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function website_api_social_rows(mysqli $con): array
{
    $rows = [];
    $result = $con->query(
        'SELECT id, label, url, icon, sort_order
         FROM social_links
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['label'] ?? ''),
            'url' => (string)($row['url'] ?? ''),
            'icon' => (string)($row['icon'] ?? ''),
            'sort_order' => (int)($row['sort_order'] ?? 0),
        ];
    }

    return $rows;
}

function website_api_slider_rows(mysqli $con): array
{
    $rows = [];
    $result = $con->query(
        'SELECT id, title, subtitle, description, image_url, alt_text, video_url, cta_text, cta_url, sort_order
         FROM slider
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['subtitle'] ?? ''),
            'heading' => (string)($row['title'] ?? ''),
            'text' => (string)($row['description'] ?? ''),
            'image' => (string)($row['image_url'] ?? ''),
            'alt' => (string)($row['alt_text'] ?? ''),
            'video' => (string)($row['video_url'] ?? ''),
            'buttonText' => (string)($row['cta_text'] ?? ''),
            'buttonLink' => (string)($row['cta_url'] ?? ''),
            'sort_order' => (int)($row['sort_order'] ?? 0),
        ];
    }

    return $rows;
}

function website_api_ticker_rows(mysqli $con): array
{
    $rows = [];
    $result = $con->query(
        'SELECT id, message
         FROM ticker
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'message' => (string)($row['message'] ?? ''),
        ];
    }

    return $rows;
}

function website_api_page_meta_rows(mysqli $con): array
{
    $rows = [];
    $result = $con->query(
        'SELECT page, meta_title, meta_description, canonical_url, og_title, og_description, og_image, json_ld, updated_at
         FROM page_meta
         ORDER BY page ASC'
    );

    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'page' => (string)($row['page'] ?? ''),
            'meta_title' => (string)($row['meta_title'] ?? ''),
            'meta_description' => (string)($row['meta_description'] ?? ''),
            'canonical_url' => (string)($row['canonical_url'] ?? ''),
            'og_title' => (string)($row['og_title'] ?? ''),
            'og_description' => (string)($row['og_description'] ?? ''),
            'og_image' => (string)($row['og_image'] ?? ''),
            'json_ld' => (string)($row['json_ld'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function website_api_payload(mysqli $con): array
{
    return [
        'brand' => [
            'name' => website_api_get_setting($con, 'site_name', 'COMMERZA'),
            'logo' => website_api_get_setting($con, 'logo_url', 'frontend/assets/images/logo/commerza-logo.webp'),
            'favicon' => website_api_get_setting($con, 'favicon_url', 'frontend/assets/images/favicon/commerza-watches-icon.ico'),
        ],
        'contact' => [
            'address' => website_api_get_setting($con, 'site_address', ''),
            'email' => website_api_get_setting($con, 'site_email', ''),
            'phone' => website_api_get_setting($con, 'site_phone', ''),
        ],
        'ticker' => [
            'enabled' => website_api_get_setting($con, 'ticker_enabled', '1') !== '0',
            'messages' => array_map(
                static fn(array $item): string => (string)$item['message'],
                website_api_ticker_rows($con)
            ),
        ],
        'socialLinks' => website_api_social_rows($con),
        'pageMeta' => website_api_page_meta_rows($con),
        'sliderImages' => website_api_slider_rows($con),
        'featuredVideos' => [
            'home' => website_api_get_setting(
                $con,
                'home_feature_video',
                'frontend/assets/videos/slider/steel_watch_1.mp4'
            ),
            'categoryA' => website_api_get_setting(
                $con,
                'category_a_feature_video',
                'frontend/assets/videos/products/smart/automatic_watches_carousel.mp4'
            ),
        ],
    ];
}

website_api_ensure_schema($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = 'get';

if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'get')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? 'get')));
}

if ($method === 'GET' && $action === 'get') {
    admin_api_rate_limit_guard(
        $con,
        $admin,
        admin_api_scope('admin_website_api', $action),
        90,
        60,
        120,
        300
    );

    website_api_json([
        'ok' => true,
        'payload' => website_api_payload($con),
    ]);
}

if ($method !== 'POST') {
    website_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!admin_validate_csrf_token($csrfToken)) {
    website_api_json([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
    ], 403);
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '{}', true);
if (!is_array($body) || !$body) {
    $body = $_POST;
}

$action = strtolower(trim((string)($body['action'] ?? $action)));

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_website_api', $action),
    90,
    60,
    120,
    300
);

if ($action === 'save-brand') {
    $name = website_api_clean_text((string)($body['name'] ?? ''), 100);
    $logo = trim((string)($body['logo'] ?? ''));
    $favicon = trim((string)($body['favicon'] ?? ''));
    $previousLogo = website_api_get_setting($con, 'logo_url', '');
    $previousFavicon = website_api_get_setting($con, 'favicon_url', '');

    if ($name === '' || !website_api_valid_path($logo) || !website_api_valid_path($favicon)) {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide valid name, logo, and favicon paths.',
        ], 422);
    }

    $ok = true;
    $ok = $ok && website_api_upsert_setting($con, 'site_name', $name, 'Website Name', 'general');
    $ok = $ok && website_api_upsert_setting($con, 'logo_url', $logo, 'Logo Image URL', 'general');
    $ok = $ok && website_api_upsert_setting($con, 'favicon_url', $favicon, 'Favicon URL', 'general');

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save branding.',
        ], 500);
    }

    if ($previousLogo !== '' && $previousLogo !== $logo) {
        website_api_cleanup_replaced_media($con, $previousLogo);
    }
    if ($previousFavicon !== '' && $previousFavicon !== $favicon) {
        website_api_cleanup_replaced_media($con, $previousFavicon);
    }

    admin_api_log_security_event($con, $admin, 'website.brand_updated', 'info', [
        'name' => $name,
        'logo' => $logo,
        'favicon' => $favicon,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Branding saved.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-contact') {
    $address = website_api_clean_text((string)($body['address'] ?? ''), 255);
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $phone = website_api_clean_text((string)($body['phone'] ?? ''), 40);

    if ($address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide valid contact details.',
        ], 422);
    }

    $ok = true;
    $ok = $ok && website_api_upsert_setting($con, 'site_address', $address, 'Business Address', 'contact');
    $ok = $ok && website_api_upsert_setting($con, 'site_email', $email, 'Contact Email', 'contact');
    $ok = $ok && website_api_upsert_setting($con, 'site_phone', $phone, 'Contact Phone', 'contact');

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save contact details.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'website.contact_updated', 'info', [
        'email' => $email,
        'phone' => $phone,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Contact details saved.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-page-meta') {
    $page = strtolower(trim((string)($body['page'] ?? '')));
    $metaTitle = website_api_clean_text((string)($body['meta_title'] ?? ''), 150);
    $metaDescription = website_api_clean_text((string)($body['meta_description'] ?? ''), 255);
    $canonicalUrl = trim((string)($body['canonical_url'] ?? ''));
    $ogTitle = website_api_clean_text((string)($body['og_title'] ?? ''), 150);
    $ogDescription = website_api_clean_text((string)($body['og_description'] ?? ''), 255);
    $ogImage = trim((string)($body['og_image'] ?? ''));
    $jsonLd = trim((string)($body['json_ld'] ?? ''));

    if (!website_api_valid_page_key($page)) {
        website_api_json([
            'ok' => false,
            'message' => 'Invalid page key. Use a page filename like about.php.',
        ], 422);
    }

    if (!website_api_valid_meta_url($canonicalUrl)) {
        website_api_json([
            'ok' => false,
            'message' => 'Canonical URL must be a valid absolute URL or local path.',
        ], 422);
    }

    if (!website_api_valid_meta_url($ogImage)) {
        website_api_json([
            'ok' => false,
            'message' => 'OG image must be a valid absolute URL or local path.',
        ], 422);
    }

    if ($jsonLd !== '') {
        if (strlen($jsonLd) > 20000) {
            website_api_json([
                'ok' => false,
                'message' => 'JSON-LD content is too long.',
            ], 422);
        }

        $decodedJsonLd = json_decode($jsonLd, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedJsonLd)) {
            website_api_json([
                'ok' => false,
                'message' => 'JSON-LD must be valid JSON object or array.',
            ], 422);
        }

        $jsonLd = (string)json_encode($decodedJsonLd, JSON_UNESCAPED_SLASHES);
    }

    $metaTitleValue = $metaTitle !== '' ? $metaTitle : null;
    $metaDescriptionValue = $metaDescription !== '' ? $metaDescription : null;
    $canonicalValue = $canonicalUrl !== '' ? $canonicalUrl : null;
    $ogTitleValue = $ogTitle !== '' ? $ogTitle : null;
    $ogDescriptionValue = $ogDescription !== '' ? $ogDescription : null;
    $ogImageValue = $ogImage !== '' ? $ogImage : null;
    $jsonLdValue = $jsonLd !== '' ? $jsonLd : null;

    $stmt = $con->prepare(
        'INSERT INTO page_meta (page, meta_title, meta_description, canonical_url, og_title, og_description, og_image, json_ld)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            meta_title = VALUES(meta_title),
            meta_description = VALUES(meta_description),
            canonical_url = VALUES(canonical_url),
            og_title = VALUES(og_title),
            og_description = VALUES(og_description),
            og_image = VALUES(og_image),
            json_ld = VALUES(json_ld)'
    );

    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save page SEO settings.',
        ], 500);
    }

    $stmt->bind_param(
        'ssssssss',
        $page,
        $metaTitleValue,
        $metaDescriptionValue,
        $canonicalValue,
        $ogTitleValue,
        $ogDescriptionValue,
        $ogImageValue,
        $jsonLdValue
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save page SEO settings.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'website.page_meta_saved', 'info', [
        'page' => $page,
        'canonical_url' => $canonicalUrl,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Page SEO settings saved.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'delete-page-meta') {
    $page = strtolower(trim((string)($body['page'] ?? '')));
    if (!website_api_valid_page_key($page)) {
        website_api_json([
            'ok' => false,
            'message' => 'Invalid page key.',
        ], 422);
    }

    $stmt = $con->prepare('DELETE FROM page_meta WHERE page = ? LIMIT 1');
    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete page SEO settings.',
        ], 500);
    }

    $stmt->bind_param('s', $page);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete page SEO settings.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'website.page_meta_deleted', 'warning', [
        'page' => $page,
        'affected_rows' => $affected,
    ]);

    website_api_json([
        'ok' => true,
        'message' => $affected > 0 ? 'Page SEO settings deleted.' : 'No SEO settings found for this page.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-social') {
    $id = (int)($body['id'] ?? 0);
    $label = website_api_clean_text((string)($body['label'] ?? ''), 50);
    $url = trim((string)($body['url'] ?? ''));
    $icon = trim((string)($body['icon'] ?? ''));

    if ($label === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide valid social label and URL.',
        ], 422);
    }

    if ($icon !== '' && !(website_api_valid_path($icon) || preg_match('/^bi\s+bi-[a-z0-9-]+$/i', $icon) === 1)) {
        website_api_json([
            'ok' => false,
            'message' => 'Icon must be a bootstrap icon class or uploaded path.',
        ], 422);
    }

    if ($id > 0) {
        $previousIcon = '';
        $existingStmt = $con->prepare('SELECT icon FROM social_links WHERE id = ? LIMIT 1');
        if ($existingStmt) {
            $existingStmt->bind_param('i', $id);
            $existingStmt->execute();
            $existingRow = $existingStmt->get_result()?->fetch_assoc();
            $existingStmt->close();
            $previousIcon = trim((string)($existingRow['icon'] ?? ''));
        }

        $stmt = $con->prepare(
            'UPDATE social_links
             SET label = ?, url = ?, icon = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$stmt) {
            website_api_json([
                'ok' => false,
                'message' => 'Unable to update social link.',
            ], 500);
        }

        $stmt->bind_param('sssi', $label, $url, $icon, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            website_api_json([
                'ok' => false,
                'message' => 'Unable to update social link.',
            ], 500);
        }

        if ($previousIcon !== '' && $previousIcon !== $icon) {
            website_api_cleanup_replaced_media($con, $previousIcon);
        }

        admin_api_log_security_event($con, $admin, 'website.social_updated', 'info', [
            'social_id' => $id,
            'label' => $label,
            'url' => $url,
        ]);

        website_api_json([
            'ok' => true,
            'message' => 'Social link updated.',
            'payload' => website_api_payload($con),
        ]);
    }

    $result = $con->query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM social_links');
    $maxSort = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $maxSort = (int)($row['max_sort'] ?? 0);
    }

    $sortOrder = $maxSort + 1;

    $stmt = $con->prepare(
        'INSERT INTO social_links (label, url, icon, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)'
    );

    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to add social link.',
        ], 500);
    }

    $stmt->bind_param('sssi', $label, $url, $icon, $sortOrder);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to add social link.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'website.social_added', 'info', [
        'label' => $label,
        'url' => $url,
        'sort_order' => $sortOrder,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Social link added.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'delete-social') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        website_api_json([
            'ok' => false,
            'message' => 'Invalid social link id.',
        ], 422);
    }

    $existingIcon = '';
    $iconStmt = $con->prepare('SELECT icon FROM social_links WHERE id = ? LIMIT 1');
    if ($iconStmt) {
        $iconStmt->bind_param('i', $id);
        $iconStmt->execute();
        $iconRow = $iconStmt->get_result()?->fetch_assoc();
        $iconStmt->close();
        $existingIcon = trim((string)($iconRow['icon'] ?? ''));
    }

    $stmt = $con->prepare('DELETE FROM social_links WHERE id = ? LIMIT 1');
    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete social link.',
        ], 500);
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete social link.',
        ], 500);
    }

    if ($existingIcon !== '') {
        website_api_cleanup_replaced_media($con, $existingIcon);
    }

    admin_api_log_security_event($con, $admin, 'website.social_deleted', 'warning', [
        'social_id' => $id,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Social link deleted.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-ticker') {
    $enabled = !empty($body['enabled']) && (string)$body['enabled'] !== '0';
    $messages = $body['messages'] ?? [];

    if (!is_array($messages)) {
        website_api_json([
            'ok' => false,
            'message' => 'Ticker messages must be an array.',
        ], 422);
    }

    $cleaned = [];
    foreach ($messages as $message) {
        $value = website_api_clean_text((string)$message, 255);
        if ($value !== '') {
            $cleaned[] = $value;
        }
    }

    if (empty($cleaned)) {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide at least one ticker message.',
        ], 422);
    }

    $ok = website_api_upsert_setting($con, 'ticker_enabled', $enabled ? '1' : '0', 'Enable Ticker (0/1)', 'general');
    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save ticker settings.',
        ], 500);
    }

    $con->query('DELETE FROM ticker');

    $stmt = $con->prepare(
        'INSERT INTO ticker (message, sort_order, is_active)
         VALUES (?, ?, 1)'
    );

    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save ticker messages.',
        ], 500);
    }

    foreach ($cleaned as $index => $message) {
        $sortOrder = $index + 1;
        $stmt->bind_param('si', $message, $sortOrder);
        $stmt->execute();
    }

    $stmt->close();

    admin_api_log_security_event($con, $admin, 'website.ticker_saved', 'info', [
        'enabled' => $enabled,
        'messages' => count($cleaned),
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Ticker saved.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-slider') {
    $id = (int)($body['id'] ?? 0);
    $image = trim((string)($body['image'] ?? ''));
    $alt = website_api_clean_text((string)($body['alt'] ?? ''), 255);
    $label = website_api_clean_text((string)($body['label'] ?? ''), 255);
    $heading = website_api_clean_text((string)($body['heading'] ?? ''), 150);
    $text = website_api_clean_text((string)($body['text'] ?? ''), 500);
    $buttonText = website_api_clean_text((string)($body['buttonText'] ?? ''), 80);
    $buttonLink = trim((string)($body['buttonLink'] ?? ''));
    $video = trim((string)($body['video'] ?? ''));

    if (!website_api_valid_path($image) || $heading === '') {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide valid slide image and heading.',
        ], 422);
    }

    if ($buttonLink !== '' && !website_api_valid_path($buttonLink) && filter_var($buttonLink, FILTER_VALIDATE_URL) === false) {
        website_api_json([
            'ok' => false,
            'message' => 'Button link must be a valid URL or local path.',
        ], 422);
    }

    if ($video !== '' && !website_api_valid_path($video)) {
        website_api_json([
            'ok' => false,
            'message' => 'Video path is invalid.',
        ], 422);
    }

    if ($id > 0) {
        $previousImage = '';
        $previousVideo = '';
        $existingStmt = $con->prepare('SELECT image_url, video_url FROM slider WHERE id = ? LIMIT 1');
        if ($existingStmt) {
            $existingStmt->bind_param('i', $id);
            $existingStmt->execute();
            $existingRow = $existingStmt->get_result()?->fetch_assoc();
            $existingStmt->close();
            $previousImage = trim((string)($existingRow['image_url'] ?? ''));
            $previousVideo = trim((string)($existingRow['video_url'] ?? ''));
        }

        $stmt = $con->prepare(
            'UPDATE slider
             SET title = ?, subtitle = ?, description = ?, image_url = ?, alt_text = ?, video_url = ?, cta_text = ?, cta_url = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$stmt) {
            website_api_json([
                'ok' => false,
                'message' => 'Unable to update slide.',
            ], 500);
        }

        $stmt->bind_param('ssssssssi', $heading, $label, $text, $image, $alt, $video, $buttonText, $buttonLink, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            website_api_json([
                'ok' => false,
                'message' => 'Unable to update slide.',
            ], 500);
        }

        if ($previousImage !== '' && $previousImage !== $image) {
            website_api_cleanup_replaced_media($con, $previousImage);
        }
        if ($previousVideo !== '' && $previousVideo !== $video) {
            website_api_cleanup_replaced_media($con, $previousVideo);
        }

        admin_api_log_security_event($con, $admin, 'website.slider_updated', 'info', [
            'slider_id' => $id,
            'heading' => $heading,
            'image' => $image,
        ]);

        website_api_json([
            'ok' => true,
            'message' => 'Slide updated.',
            'payload' => website_api_payload($con),
        ]);
    }

    $result = $con->query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM slider');
    $maxSort = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $maxSort = (int)($row['max_sort'] ?? 0);
    }

    $sortOrder = $maxSort + 1;

    $stmt = $con->prepare(
        'INSERT INTO slider (title, subtitle, description, image_url, alt_text, video_url, cta_text, cta_url, sort_order, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );

    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to add slide.',
        ], 500);
    }

    $stmt->bind_param('ssssssssi', $heading, $label, $text, $image, $alt, $video, $buttonText, $buttonLink, $sortOrder);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to add slide.',
        ], 500);
    }

    $createdSliderId = (int)$con->insert_id;
    admin_api_log_security_event($con, $admin, 'website.slider_added', 'info', [
        'slider_id' => $createdSliderId,
        'heading' => $heading,
        'image' => $image,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Slide added.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-feature-videos') {
    $homeVideo = trim((string)($body['home_video'] ?? ''));
    $categoryAVideo = trim((string)($body['category_a_video'] ?? ''));
    $previousHomeVideo = website_api_get_setting($con, 'home_feature_video', '');
    $previousCategoryVideo = website_api_get_setting($con, 'category_a_feature_video', '');

    if (!website_api_valid_path($homeVideo) || !website_api_valid_path($categoryAVideo)) {
        website_api_json([
            'ok' => false,
            'message' => 'Please provide valid video paths for homepage and category A.',
        ], 422);
    }

    $ok = true;
    $ok = $ok && website_api_upsert_setting(
        $con,
        'home_feature_video',
        $homeVideo,
        'Homepage Feature Video',
        'general'
    );
    $ok = $ok && website_api_upsert_setting(
        $con,
        'category_a_feature_video',
        $categoryAVideo,
        'Category A Feature Video',
        'general'
    );

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to save featured videos.',
        ], 500);
    }

    if ($previousHomeVideo !== '' && $previousHomeVideo !== $homeVideo) {
        website_api_cleanup_replaced_media($con, $previousHomeVideo);
    }
    if ($previousCategoryVideo !== '' && $previousCategoryVideo !== $categoryAVideo) {
        website_api_cleanup_replaced_media($con, $previousCategoryVideo);
    }

    admin_api_log_security_event($con, $admin, 'website.featured_videos_saved', 'info', [
        'home_video' => $homeVideo,
        'category_a_video' => $categoryAVideo,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Featured videos saved.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'delete-slider') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        website_api_json([
            'ok' => false,
            'message' => 'Invalid slide id.',
        ], 422);
    }

    $existingImage = '';
    $existingVideo = '';
    $existingStmt = $con->prepare('SELECT image_url, video_url FROM slider WHERE id = ? LIMIT 1');
    if ($existingStmt) {
        $existingStmt->bind_param('i', $id);
        $existingStmt->execute();
        $existingRow = $existingStmt->get_result()?->fetch_assoc();
        $existingStmt->close();
        $existingImage = trim((string)($existingRow['image_url'] ?? ''));
        $existingVideo = trim((string)($existingRow['video_url'] ?? ''));
    }

    $stmt = $con->prepare('DELETE FROM slider WHERE id = ? LIMIT 1');
    if (!$stmt) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete slide.',
        ], 500);
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        website_api_json([
            'ok' => false,
            'message' => 'Unable to delete slide.',
        ], 500);
    }

    if ($existingImage !== '') {
        website_api_cleanup_replaced_media($con, $existingImage);
    }
    if ($existingVideo !== '') {
        website_api_cleanup_replaced_media($con, $existingVideo);
    }

    admin_api_log_security_event($con, $admin, 'website.slider_deleted', 'warning', [
        'slider_id' => $id,
    ]);

    website_api_json([
        'ok' => true,
        'message' => 'Slide deleted.',
        'payload' => website_api_payload($con),
    ]);
}

website_api_json([
    'ok' => false,
    'message' => 'Unsupported action.',
], 400);
