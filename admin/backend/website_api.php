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

if ($action === 'save-brand') {
    $name = website_api_clean_text((string)($body['name'] ?? ''), 100);
    $logo = trim((string)($body['logo'] ?? ''));
    $favicon = trim((string)($body['favicon'] ?? ''));

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

    website_api_json([
        'ok' => true,
        'message' => 'Contact details saved.',
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

    website_api_json([
        'ok' => true,
        'message' => 'Slide added.',
        'payload' => website_api_payload($con),
    ]);
}

if ($action === 'save-feature-videos') {
    $homeVideo = trim((string)($body['home_video'] ?? ''));
    $categoryAVideo = trim((string)($body['category_a_video'] ?? ''));

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
