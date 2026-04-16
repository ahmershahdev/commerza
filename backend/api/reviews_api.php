<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../data.php';
require_once __DIR__ . '/../helpers/media_image_helpers.php';
require_once __DIR__ . '/../services/cloudinary_service.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function reviews_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function reviews_api_request_value(string $key, string $fallback = ''): string
{
    if (isset($_REQUEST[$key])) {
        return trim((string)$_REQUEST[$key]);
    }

    return $fallback;
}

function reviews_api_rate_limit_guard(
    mysqli $con,
    string $scope,
    string $identifier,
    string $actorType,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $escalatedBlockSeconds = 300
): void {
    $clientIp = commerza_client_ip();
    $rate = commerza_rate_limit_check(
        $con,
        $scope,
        $identifier,
        $clientIp,
        max(1, $maxAttempts),
        max(60, $windowSeconds),
        max(60, $blockSeconds),
        max($blockSeconds, $escalatedBlockSeconds)
    );

    if ((bool)($rate['allowed'] ?? true)) {
        return;
    }

    $retrySeconds = max(1, (int)($rate['retry_after'] ?? $blockSeconds));
    commerza_security_log_rate_limit_block(
        $con,
        $scope,
        $actorType,
        $identifier,
        $clientIp,
        $retrySeconds
    );

    reviews_api_json([
        'ok' => false,
        'message' => 'Too many review requests. Please wait and retry.',
        'retry_after' => $retrySeconds,
        'csrf_token' => $_SESSION['csrf_token'],
    ], 429);
}

function reviews_api_user_blacklist_entry(mysqli $con, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $con->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    $blocked = commerza_customer_blacklist_lookup(
        $con,
        (string)($row['email'] ?? ''),
        (string)($row['phone'] ?? '')
    );

    return is_array($blocked) ? $blocked : null;
}

function reviews_api_upload_limit_bytes(): int
{
    return 6 * 1024 * 1024;
}

function reviews_api_max_images_per_review(): int
{
    return 2;
}

function reviews_api_refund_table_exists(mysqli $con): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $result = $con->query("SHOW TABLES LIKE 'refund_requests'");
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;

    return $exists;
}

function reviews_api_column_exists(mysqli $con, string $table, string $column): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $columnEscaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM {$tableEscaped} LIKE '{$columnEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function reviews_api_table_exists(mysqli $con, string $table): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $result = $con->query("SHOW TABLES LIKE '{$tableEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function reviews_api_index_exists(mysqli $con, string $table, string $indexName): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $indexEscaped = $con->real_escape_string($indexName);
    $result = $con->query("SHOW INDEX FROM {$tableEscaped} WHERE Key_name = '{$indexEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function reviews_api_foreign_key_exists(mysqli $con, string $table, string $constraintName): bool
{
    $stmt = $con->prepare(
        'SELECT 1
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?
           AND CONSTRAINT_TYPE = "FOREIGN KEY"
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $constraintName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function reviews_api_ensure_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS product_reviews (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            order_id INT DEFAULT NULL,
            rating TINYINT NOT NULL,
            review_text VARCHAR(500) NOT NULL,
            is_verified_purchase TINYINT(1) NOT NULL DEFAULT 1,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            locked_at DATETIME DEFAULT NULL,
            locked_by_admin_id INT DEFAULT NULL,
            admin_note VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_review_user_product (user_id, product_id),
            KEY idx_review_product_visible (product_id, is_visible),
            KEY idx_review_locked (is_locked, updated_at),
            KEY idx_review_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $missingColumns = [
        'is_locked' => 'ALTER TABLE product_reviews ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_visible',
        'locked_at' => 'ALTER TABLE product_reviews ADD COLUMN locked_at DATETIME DEFAULT NULL AFTER is_locked',
        'locked_by_admin_id' => 'ALTER TABLE product_reviews ADD COLUMN locked_by_admin_id INT DEFAULT NULL AFTER locked_at',
    ];

    foreach ($missingColumns as $column => $sql) {
        if (reviews_api_column_exists($con, 'product_reviews', $column)) {
            continue;
        }

        $con->query($sql);
    }

    $indexCheck = $con->query("SHOW INDEX FROM product_reviews WHERE Key_name = 'idx_review_locked'");
    if (!($indexCheck instanceof mysqli_result) || $indexCheck->num_rows === 0) {
        $con->query('ALTER TABLE product_reviews ADD KEY idx_review_locked (is_locked, updated_at)');
    }

    $initialized = true;
}

function reviews_api_ensure_images_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS product_review_images (
            id INT NOT NULL AUTO_INCREMENT,
            review_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            image_size INT NOT NULL DEFAULT 0,
            sort_order TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_review_images_review (review_id),
            CONSTRAINT fk_review_images_review FOREIGN KEY (review_id)
                REFERENCES product_reviews (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function reviews_api_ensure_fake_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS product_fake_reviews (
            id INT NOT NULL AUTO_INCREMENT,
            review_id INT DEFAULT NULL,
            product_id INT NOT NULL,
            fake_user_id INT DEFAULT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            review_text VARCHAR(500) NOT NULL,
            reviewer_name VARCHAR(120) NOT NULL DEFAULT "Customer",
            reviewer_handle VARCHAR(80) DEFAULT NULL,
            reviewer_visibility ENUM("public", "private") NOT NULL DEFAULT "public",
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            locked_at DATETIME DEFAULT NULL,
            locked_by_admin_id INT DEFAULT NULL,
            admin_note VARCHAR(500) DEFAULT NULL,
            generated_by_admin_id INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_fake_reviews_product_visibility (product_id, is_visible),
            KEY idx_fake_reviews_visibility_updated (is_visible, updated_at),
            KEY idx_fake_reviews_locked_updated (is_locked, updated_at),
            KEY idx_fake_reviews_product_created (product_id, created_at),
            KEY idx_fake_reviews_admin_created (generated_by_admin_id, created_at),
            CONSTRAINT fk_fake_reviews_product FOREIGN KEY (product_id)
                REFERENCES products (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_fake_reviews_admin FOREIGN KEY (generated_by_admin_id)
                REFERENCES admin_users (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    if (!reviews_api_table_exists($con, 'product_fake_reviews')) {
        return;
    }

    $legacyForeignKeys = [
        'fk_fake_reviews_review',
        'fk_fake_reviews_user',
        'fk_pfr_review',
        'fk_pfr_user',
    ];

    foreach ($legacyForeignKeys as $constraintName) {
        if (!reviews_api_foreign_key_exists($con, 'product_fake_reviews', $constraintName)) {
            continue;
        }

        $con->query('ALTER TABLE product_fake_reviews DROP FOREIGN KEY ' . $constraintName);
    }

    $missingColumns = [
        'rating' => 'ALTER TABLE product_fake_reviews ADD COLUMN rating TINYINT NOT NULL DEFAULT 5 AFTER fake_user_id',
        'review_text' => 'ALTER TABLE product_fake_reviews ADD COLUMN review_text VARCHAR(500) NOT NULL DEFAULT "" AFTER rating',
        'reviewer_name' => 'ALTER TABLE product_fake_reviews ADD COLUMN reviewer_name VARCHAR(120) NOT NULL DEFAULT "Customer" AFTER review_text',
        'reviewer_handle' => 'ALTER TABLE product_fake_reviews ADD COLUMN reviewer_handle VARCHAR(80) DEFAULT NULL AFTER reviewer_name',
        'reviewer_visibility' => 'ALTER TABLE product_fake_reviews ADD COLUMN reviewer_visibility ENUM("public", "private") NOT NULL DEFAULT "public" AFTER reviewer_handle',
        'is_visible' => 'ALTER TABLE product_fake_reviews ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER reviewer_visibility',
        'is_locked' => 'ALTER TABLE product_fake_reviews ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_visible',
        'locked_at' => 'ALTER TABLE product_fake_reviews ADD COLUMN locked_at DATETIME DEFAULT NULL AFTER is_locked',
        'locked_by_admin_id' => 'ALTER TABLE product_fake_reviews ADD COLUMN locked_by_admin_id INT DEFAULT NULL AFTER locked_at',
        'admin_note' => 'ALTER TABLE product_fake_reviews ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL AFTER locked_by_admin_id',
        'updated_at' => 'ALTER TABLE product_fake_reviews ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($missingColumns as $column => $sql) {
        if (reviews_api_column_exists($con, 'product_fake_reviews', $column)) {
            continue;
        }

        $con->query($sql);
    }

    if (reviews_api_column_exists($con, 'product_fake_reviews', 'review_id')) {
        $con->query('ALTER TABLE product_fake_reviews MODIFY COLUMN review_id INT DEFAULT NULL');
    }

    if (reviews_api_column_exists($con, 'product_fake_reviews', 'fake_user_id')) {
        $con->query('ALTER TABLE product_fake_reviews MODIFY COLUMN fake_user_id INT DEFAULT NULL');
    }

    if (!reviews_api_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_product_visibility')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_product_visibility (product_id, is_visible)');
    }

    if (!reviews_api_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_visibility_updated')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_visibility_updated (is_visible, updated_at)');
    }

    if (!reviews_api_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_locked_updated')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_locked_updated (is_locked, updated_at)');
    }

    if (!reviews_api_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_product_created')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_product_created (product_id, created_at)');
    }

    if (!reviews_api_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_admin_created')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_admin_created (generated_by_admin_id, created_at)');
    }

    $hasUserVisibility = reviews_api_column_exists($con, 'users', 'profile_visibility');
    $visibilityFallbackSql = $hasUserVisibility
        ? 'CASE WHEN LOWER(TRIM(COALESCE(u.profile_visibility, "public"))) = "private" THEN "private" ELSE "public" END'
        : '"public"';

    $con->query(
        'UPDATE product_fake_reviews f
         LEFT JOIN product_reviews r ON r.id = f.review_id
         LEFT JOIN users u ON u.id = f.fake_user_id
         SET
            f.rating = CASE WHEN f.rating BETWEEN 1 AND 5 THEN f.rating ELSE COALESCE(r.rating, 5) END,
            f.review_text = CASE
                WHEN CHAR_LENGTH(TRIM(COALESCE(f.review_text, ""))) > 0 THEN f.review_text
                ELSE COALESCE(r.review_text, "Great quality and value for daily use.")
            END,
            f.reviewer_name = CASE
                WHEN CHAR_LENGTH(TRIM(COALESCE(f.reviewer_name, ""))) > 0 THEN f.reviewer_name
                ELSE COALESCE(NULLIF(TRIM(COALESCE(u.full_name, "")), ""), "Customer")
            END,
            f.reviewer_handle = CASE
                WHEN CHAR_LENGTH(TRIM(COALESCE(f.reviewer_handle, ""))) > 0 THEN f.reviewer_handle
                ELSE NULLIF(TRIM(COALESCE(u.username, "")), "")
            END,
            f.reviewer_visibility = CASE
                WHEN LOWER(TRIM(COALESCE(f.reviewer_visibility, ""))) IN ("public", "private")
                    THEN LOWER(TRIM(f.reviewer_visibility))
                ELSE ' . $visibilityFallbackSql . '
            END,
            f.is_visible = CASE WHEN f.is_visible IN (0, 1) THEN f.is_visible ELSE COALESCE(r.is_visible, 1) END,
            f.is_locked = CASE WHEN f.is_locked IN (0, 1) THEN f.is_locked ELSE COALESCE(r.is_locked, 0) END,
            f.locked_at = COALESCE(f.locked_at, r.locked_at),
            f.locked_by_admin_id = COALESCE(f.locked_by_admin_id, r.locked_by_admin_id),
            f.admin_note = CASE
                WHEN CHAR_LENGTH(TRIM(COALESCE(f.admin_note, ""))) > 0 THEN f.admin_note
                ELSE COALESCE(r.admin_note, "Admin generated fake review")
            END,
            f.generated_by_admin_id = COALESCE(f.generated_by_admin_id, r.locked_by_admin_id)'
    );

    $initialized = true;
}

function reviews_api_review_images(mysqli $con, int $reviewId): array
{
    if ($reviewId <= 0) {
        return [];
    }

    $rows = [];
    $stmt = $con->prepare(
        'SELECT image_path, image_name, image_size
         FROM product_review_images
         WHERE review_id = ?
         ORDER BY sort_order ASC, id ASC
         LIMIT 2'
    );

    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = [
            'path' => (string)($row['image_path'] ?? ''),
            'name' => (string)($row['image_name'] ?? ''),
            'size' => (int)($row['image_size'] ?? 0),
        ];
    }

    $stmt->close();
    return $rows;
}

function reviews_api_uploaded_files(): array
{
    if (!isset($_FILES['review_images']) || !is_array($_FILES['review_images'])) {
        return [];
    }

    $raw = $_FILES['review_images'];
    $names = $raw['name'] ?? [];
    $types = $raw['type'] ?? [];
    $tmpNames = $raw['tmp_name'] ?? [];
    $errors = $raw['error'] ?? [];
    $sizes = $raw['size'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $types = [is_array($types) ? '' : $types];
        $tmpNames = [is_array($tmpNames) ? '' : $tmpNames];
        $errors = [is_array($errors) ? UPLOAD_ERR_NO_FILE : $errors];
        $sizes = [is_array($sizes) ? 0 : $sizes];
    }

    $files = [];
    foreach ($names as $index => $name) {
        $error = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files[] = [
            'name' => (string)$name,
            'type' => (string)($types[$index] ?? ''),
            'tmp_name' => (string)($tmpNames[$index] ?? ''),
            'error' => $error,
            'size' => (int)($sizes[$index] ?? 0),
        ];
    }

    return $files;
}

function reviews_api_validate_uploaded_images(array $files): array
{
    if (count($files) > reviews_api_max_images_per_review()) {
        return [false, [], 'You can upload up to 2 images only.'];
    }

    $validated = [];
    $maxBytes = reviews_api_upload_limit_bytes();
    $allowedMimes = commerza_media_allowed_image_mimes();
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    foreach ($files as $file) {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return [false, [], 'One or more review images failed to upload.'];
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $originalName = trim((string)($file['name'] ?? 'image'));

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [false, [], 'Invalid uploaded image file.'];
        }

        $scanReason = null;
        if (!commerza_upload_scan_file($tmpName, $scanReason)) {
            return [false, [], $scanReason !== null ? $scanReason : 'Uploaded image failed security scan.'];
        }

        if ($size <= 0 || $size >= $maxBytes) {
            return [false, [], 'Each image must be less than 6 MB.'];
        }

        $mime = '';
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpName);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmpName);
        }

        if (!isset($allowedMimes[$mime])) {
            return [false, [], 'Only JPG, PNG, WEBP, and GIF images are allowed.'];
        }

        $validated[] = [
            'tmp_name' => $tmpName,
            'size' => $size,
            'mime' => $mime,
            'name' => commerza_media_normalize_upload_name($originalName, 'review-image.webp'),
        ];
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    return [true, $validated, ''];
}

function reviews_api_store_images(array $files, int $reviewId, int $userId): array
{
    $stored = [];
    $cloudinaryEnabled = function_exists('commerza_cloudinary_is_enabled') && commerza_cloudinary_is_enabled();
    $storageDirRelative = 'frontend/assets/images/reviews';
    $storageDirAbsolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'reviews';

    if (!$cloudinaryEnabled && !is_dir($storageDirAbsolute) && !mkdir($storageDirAbsolute, 0755, true) && !is_dir($storageDirAbsolute)) {
        return [false, [], 'Unable to prepare review image storage directory.'];
    }

    foreach ($files as $index => $file) {
        $tmpName = (string)($file['tmp_name'] ?? '');
        $mime = (string)($file['mime'] ?? '');
        $displayName = (string)($file['name'] ?? 'review-image');

        $conversion = commerza_media_convert_upload_to_webp($tmpName, $mime, 260, 2200);
        if (!(bool)($conversion['ok'] ?? false)) {
            return [
                false,
                $stored,
                (string)($conversion['message'] ?? 'Unable to parse and compress uploaded review image.'),
            ];
        }

        $outputExtension = strtolower(trim((string)($conversion['extension'] ?? '')));
        if ($outputExtension === '') {
            $outputExtension = 'webp';
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            $token = uniqid('rvw', true);
        }

        $fileName = 'review_' . $reviewId . '_' . $userId . '_' . $token . '.' . $outputExtension;
        $relativePath = $storageDirRelative . '/' . $fileName;
        $absolutePath = $storageDirAbsolute . DIRECTORY_SEPARATOR . $fileName;

        $displayStem = pathinfo($displayName, PATHINFO_FILENAME);
        $displayStem = commerza_media_normalize_upload_name($displayStem, 'review-image');
        $storedDisplayName = $displayStem . '.' . $outputExtension;

        $binary = (string)($conversion['binary'] ?? '');
        if ($binary === '') {
            return [false, $stored, 'Unable to save uploaded review image.'];
        }

        if ($cloudinaryEnabled) {
            $tempPath = tempnam(sys_get_temp_dir(), 'cmzrvw_');
            if (!is_string($tempPath) || $tempPath === '') {
                return [false, $stored, 'Unable to prepare Cloudinary review image buffer.'];
            }

            if (file_put_contents($tempPath, $binary) === false) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
                return [false, $stored, 'Unable to save uploaded review image.'];
            }

            $cloudinaryResult = commerza_cloudinary_upload_file($tempPath, [
                'resource_type' => 'image',
                'folder' => commerza_cloudinary_target_folder('reviews/images'),
                'public_id' => pathinfo($fileName, PATHINFO_FILENAME),
                'upload_preset' => (string)(commerza_cloudinary_config()['upload_preset_image'] ?? ''),
                'overwrite' => false,
                'invalidate' => true,
                'tags' => ['commerza', 'reviews', 'user-review'],
            ]);

            if (is_file($tempPath)) {
                @unlink($tempPath);
            }

            if (!(bool)($cloudinaryResult['ok'] ?? false)) {
                return [
                    false,
                    $stored,
                    (string)($cloudinaryResult['message'] ?? 'Unable to upload review image to Cloudinary.'),
                ];
            }

            $relativePath = trim((string)($cloudinaryResult['secure_url'] ?? ''));
            if ($relativePath === '') {
                $relativePath = trim((string)($cloudinaryResult['url'] ?? ''));
            }

            if ($relativePath === '') {
                return [false, $stored, 'Cloudinary review upload completed without a URL.'];
            }

            $storedSize = (int)($cloudinaryResult['bytes'] ?? 0);
            if ($storedSize <= 0) {
                $storedSize = (int)($conversion['bytes'] ?? 0);
            }
        } else {
            if (file_put_contents($absolutePath, $binary) === false) {
                return [false, $stored, 'Unable to save uploaded review image.'];
            }

            $storedSize = (int)($conversion['bytes'] ?? 0);
        }

        $stored[] = [
            'path' => $relativePath,
            'name' => $storedDisplayName,
            'size' => $storedSize,
            'sort_order' => $index,
        ];
    }

    return [true, $stored, ''];
}

function reviews_api_insert_image_rows(mysqli $con, int $reviewId, array $storedImages): bool
{
    if (empty($storedImages)) {
        return true;
    }

    $stmt = $con->prepare(
        'INSERT INTO product_review_images (review_id, image_path, image_name, image_size, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        return false;
    }

    foreach ($storedImages as $image) {
        $path = (string)($image['path'] ?? '');
        $name = (string)($image['name'] ?? 'review-image');
        $size = (int)($image['size'] ?? 0);
        $sortOrder = (int)($image['sort_order'] ?? 0);

        $stmt->bind_param('issii', $reviewId, $path, $name, $size, $sortOrder);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }

    $stmt->close();
    return true;
}

function reviews_api_delete_image_rows(mysqli $con, int $reviewId): array
{
    $paths = [];

    $fetchStmt = $con->prepare(
        'SELECT image_path
         FROM product_review_images
         WHERE review_id = ?'
    );

    if ($fetchStmt) {
        $fetchStmt->bind_param('i', $reviewId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $paths[] = (string)($row['image_path'] ?? '');
        }
        $fetchStmt->close();
    }

    $deleteStmt = $con->prepare('DELETE FROM product_review_images WHERE review_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $reviewId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    return array_values(array_filter(array_unique($paths)));
}

function reviews_api_delete_files(array $paths): void
{
    foreach ($paths as $relativePath) {
        $value = trim((string)$relativePath);
        if ($value === '') {
            continue;
        }

        if (
            function_exists('commerza_cloudinary_is_managed_url')
            && function_exists('commerza_cloudinary_delete_asset_by_url')
            && commerza_cloudinary_is_managed_url($value)
        ) {
            commerza_cloudinary_delete_asset_by_url($value);
            continue;
        }

        $relativePath = trim(str_replace('\\', '/', $value));
        if ($relativePath === '' || strpos($relativePath, 'frontend/assets/images/reviews/') !== 0) {
            continue;
        }

        if (strpos($relativePath, '..') !== false) {
            continue;
        }

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

function reviews_api_product_exists(mysqli $con, int $productId): bool
{
    $stmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function reviews_api_product_name(mysqli $con, int $productId): string
{
    if ($productId <= 0) {
        return '';
    }

    $stmt = $con->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return trim((string)($row['name'] ?? ''));
}

function reviews_api_existing_user_review(mysqli $con, int $userId, int $productId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, rating, review_text, is_visible, is_locked, locked_at, created_at, updated_at
         FROM product_reviews
         WHERE user_id = ? AND product_id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row) ? $row : null;
}

function reviews_api_check_eligibility(mysqli $con, int $userId, int $productId): array
{
    if ($userId <= 0) {
        return [
            'can_review' => false,
            'message' => 'Please login to add a review.',
            'eligible_order_id' => 0,
            'existing_review' => null,
        ];
    }

    $blockedContact = reviews_api_user_blacklist_entry($con, $userId);
    if (is_array($blockedContact)) {
        return [
            'can_review' => false,
            'message' => commerza_customer_blacklist_feedback_message($blockedContact),
            'eligible_order_id' => 0,
            'existing_review' => null,
        ];
    }

    $existingReview = reviews_api_existing_user_review($con, $userId, $productId);
    $existingReviewLocked = is_array($existingReview)
        && (int)($existingReview['is_locked'] ?? 0) === 1;

    if ($existingReviewLocked) {
        return [
            'can_review' => false,
            'message' => 'Your review is locked by admin and can no longer be edited.',
            'eligible_order_id' => 0,
            'existing_review' => $existingReview,
        ];
    }

    $hasRefundTable = reviews_api_refund_table_exists($con);
    $hasOrderItemsProductNameColumn = reviews_api_column_exists($con, 'order_items', 'product_name');
    $productName = strtolower(trim(reviews_api_product_name($con, $productId)));

    $productMatchSql = 'oi.product_id = ?';
    $bindTypes = 'ii';
    $bindProductName = false;

    if ($hasOrderItemsProductNameColumn && $productName !== '') {
        $productMatchSql = '(oi.product_id = ? OR LOWER(TRIM(oi.product_name)) = ?)';
        $bindTypes = 'iis';
        $bindProductName = true;
    }

    $sqlBase =
        'SELECT o.id
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ?
           AND ' . $productMatchSql . '
           AND LOWER(TRIM(o.status)) IN ("delivered", "completed", "received")';

    if ($hasRefundTable) {
        $sqlBase .=
            ' AND NOT EXISTS (
                SELECT 1
                FROM refund_requests rr
                WHERE rr.order_id = o.id
                  AND LOWER(TRIM(rr.status)) IN ("pending", "accepted")
            )';
    }

    $sqlBase .= ' ORDER BY o.updated_at DESC, o.id DESC LIMIT 1';

    $stmt = $con->prepare($sqlBase);
    if (!$stmt) {
        return [
            'can_review' => false,
            'message' => 'Unable to verify review eligibility.',
            'eligible_order_id' => 0,
            'existing_review' => $existingReview,
        ];
    }

    if ($bindProductName) {
        $stmt->bind_param($bindTypes, $userId, $productId, $productName);
    } else {
        $stmt->bind_param($bindTypes, $userId, $productId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        return [
            'can_review' => true,
            'message' => $existingReview ? 'You can update your review anytime.' : 'You are eligible to review this product.',
            'eligible_order_id' => (int)($row['id'] ?? 0),
            'existing_review' => $existingReview,
        ];
    }

    if ($existingReview) {
        return [
            'can_review' => true,
            'message' => 'You can update your existing review.',
            'eligible_order_id' => 0,
            'existing_review' => $existingReview,
        ];
    }

    if ($hasRefundTable) {
        $refundStmt = $con->prepare(
            'SELECT rr.id
             FROM refund_requests rr
             INNER JOIN orders o ON o.id = rr.order_id
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ?
               AND oi.product_id = ?
               AND LOWER(TRIM(rr.status)) IN ("pending", "accepted")
             LIMIT 1'
        );

        if ($refundStmt) {
            $refundStmt->bind_param('ii', $userId, $productId);
            $refundStmt->execute();
            $refundStmt->store_result();
            $hasBlockingRefund = $refundStmt->num_rows > 0;
            $refundStmt->close();

            if ($hasBlockingRefund) {
                return [
                    'can_review' => false,
                    'message' => 'Review access is disabled while a refund request is pending or accepted for this product order.',
                    'eligible_order_id' => 0,
                    'existing_review' => null,
                ];
            }
        }
    }

    return [
        'can_review' => false,
        'message' => 'Only purchased products from delivered orders can be reviewed. Orders with pending or accepted refunds are not eligible.',
        'eligible_order_id' => 0,
        'existing_review' => null,
    ];
}

function reviews_api_public_reviews(mysqli $con, int $productId): array
{
    $rows = [];

    $realStmt = $con->prepare(
        'SELECT
            r.id,
            r.rating,
            r.review_text,
            r.created_at,
            r.updated_at,
            u.full_name,
            u.username,
            u.profile_visibility
         FROM product_reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
           AND r.is_visible = 1
         ORDER BY r.updated_at DESC, r.id DESC
         LIMIT 30'
    );

    if ($realStmt) {
        $realStmt->bind_param('i', $productId);
        $realStmt->execute();
        $result = $realStmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $visibility = strtolower(trim((string)($row['profile_visibility'] ?? 'private')));
            if ($visibility === 'public') {
                $name = trim((string)($row['username'] ?? ''));
                if ($name === '') {
                    $name = trim((string)($row['full_name'] ?? 'Customer'));
                }
            } else {
                $name = 'Customer';
            }

            if ($name === '') {
                $name = 'Customer';
            }

            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => $name,
                'rating' => max(1, min(5, (int)($row['rating'] ?? 0))),
                'text' => (string)($row['review_text'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'images' => reviews_api_review_images($con, (int)($row['id'] ?? 0)),
            ];
        }

        $realStmt->close();
    }

    if (reviews_api_table_exists($con, 'product_fake_reviews')) {
        $fakeStmt = $con->prepare(
            'SELECT
                id,
                rating,
                review_text,
                reviewer_name,
                reviewer_handle,
                reviewer_visibility,
                created_at,
                updated_at
             FROM product_fake_reviews
             WHERE product_id = ?
               AND is_visible = 1
             ORDER BY updated_at DESC, id DESC
             LIMIT 30'
        );

        if ($fakeStmt) {
            $fakeStmt->bind_param('i', $productId);
            $fakeStmt->execute();
            $fakeResult = $fakeStmt->get_result();

            while ($fakeResult && ($row = $fakeResult->fetch_assoc())) {
                $visibility = strtolower(trim((string)($row['reviewer_visibility'] ?? 'public')));
                $name = trim((string)($row['reviewer_name'] ?? ''));
                $handle = trim((string)($row['reviewer_handle'] ?? ''));

                if ($visibility === 'public' && $handle !== '') {
                    $name = $handle;
                }

                if ($name === '') {
                    $name = 'Customer';
                }

                $rows[] = [
                    'id' => -1 * max(1, (int)($row['id'] ?? 0)),
                    'name' => $name,
                    'rating' => max(1, min(5, (int)($row['rating'] ?? 0))),
                    'text' => (string)($row['review_text'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                    'images' => [],
                ];
            }

            $fakeStmt->close();
        }
    }

    usort($rows, static function (array $a, array $b): int {
        $aTs = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
        $bTs = strtotime((string)($b['updated_at'] ?? '')) ?: 0;

        if ($aTs === $bTs) {
            return abs((int)($b['id'] ?? 0)) <=> abs((int)($a['id'] ?? 0));
        }

        return $bTs <=> $aTs;
    });

    if (count($rows) > 30) {
        $rows = array_slice($rows, 0, 30);
    }

    $summary = [
        'count' => 0,
        'average' => 0,
    ];

    $summarySql = reviews_api_table_exists($con, 'product_fake_reviews')
        ? 'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average_rating
           FROM (
               SELECT rating
               FROM product_reviews
               WHERE product_id = ?
                 AND is_visible = 1
               UNION ALL
               SELECT rating
               FROM product_fake_reviews
               WHERE product_id = ?
                 AND is_visible = 1
           ) review_union'
        : 'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average_rating
           FROM product_reviews
           WHERE product_id = ?
             AND is_visible = 1';

    $summaryStmt = $con->prepare($summarySql);

    if ($summaryStmt) {
        if (reviews_api_table_exists($con, 'product_fake_reviews')) {
            $summaryStmt->bind_param('ii', $productId, $productId);
        } else {
            $summaryStmt->bind_param('i', $productId);
        }

        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        $summaryRow = $summaryResult ? $summaryResult->fetch_assoc() : null;
        $summaryStmt->close();

        if ($summaryRow) {
            $summary['count'] = (int)($summaryRow['total'] ?? 0);
            $summary['average'] = round((float)($summaryRow['average_rating'] ?? 0), 2);
        }
    }

    return [
        'reviews' => $rows,
        'summary' => $summary,
    ];
}

function reviews_api_payload(mysqli $con, int $productId, int $userId): array
{
    $public = reviews_api_public_reviews($con, $productId);
    $eligibility = reviews_api_check_eligibility($con, $userId, $productId);

    $existing = is_array($eligibility['existing_review'] ?? null)
        ? [
            'id' => (int)($eligibility['existing_review']['id'] ?? 0),
            'rating' => (int)($eligibility['existing_review']['rating'] ?? 0),
            'text' => (string)($eligibility['existing_review']['review_text'] ?? ''),
            'is_visible' => (int)($eligibility['existing_review']['is_visible'] ?? 0) === 1,
            'is_locked' => (int)($eligibility['existing_review']['is_locked'] ?? 0) === 1,
            'locked_at' => (string)($eligibility['existing_review']['locked_at'] ?? ''),
            'updated_at' => (string)($eligibility['existing_review']['updated_at'] ?? ''),
            'images' => reviews_api_review_images($con, (int)($eligibility['existing_review']['id'] ?? 0)),
        ]
        : null;

    return [
        'product_id' => $productId,
        'reviews' => $public['reviews'],
        'summary' => $public['summary'],
        'eligibility' => [
            'can_review' => (bool)($eligibility['can_review'] ?? false),
            'message' => (string)($eligibility['message'] ?? ''),
            'existing_review' => $existing,
        ],
    ];
}

reviews_api_ensure_table($con);
reviews_api_ensure_images_table($con);
reviews_api_ensure_fake_table($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(reviews_api_request_value('action', 'list'));
$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$productId = (int)reviews_api_request_value('product_id', '0');

if ($productId <= 0) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Invalid product id.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

if (!reviews_api_product_exists($con, $productId)) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Product does not exist.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 404);
}

if ($action === 'list' && $method === 'GET') {
    $listIdentifier = 'product_' . $productId;
    $listActorType = $userId > 0 ? 'user' : 'guest';
    reviews_api_rate_limit_guard($con, 'reviews_list', $listIdentifier, $listActorType, 120, 60, 120, 300);

    reviews_api_json([
        'ok' => true,
        'logged_in' => $userId > 0,
        'payload' => reviews_api_payload($con, $productId, $userId),
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

if ($action !== 'submit') {
    reviews_api_json([
        'ok' => false,
        'message' => 'Invalid action.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 400);
}

if ($method !== 'POST') {
    reviews_api_json([
        'ok' => false,
        'message' => 'Method not allowed.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 405);
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Forbidden.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

if ($userId <= 0) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Please login to submit a review.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 401);
}

$blockedContact = reviews_api_user_blacklist_entry($con, $userId);
if (is_array($blockedContact)) {
    reviews_api_json([
        'ok' => false,
        'message' => commerza_customer_blacklist_feedback_message($blockedContact),
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

reviews_api_rate_limit_guard($con, 'reviews_submit', 'user_' . $userId, 'user', 6, 600, 900, 3600);

$rating = (int)($_POST['rating'] ?? 0);
$reviewText = trim((string)($_POST['review_text'] ?? ''));
$removeExistingImagesRaw = strtolower(trim((string)($_POST['remove_existing_images'] ?? '')));
$removeExistingImages = in_array($removeExistingImagesRaw, ['1', 'true', 'yes', 'on'], true);

if ($rating < 1 || $rating > 5) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Rating must be between 1 and 5.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

if (strlen($reviewText) < 10 || strlen($reviewText) > 500) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Review text must be 10 to 500 characters.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

$rawUploadedImages = reviews_api_uploaded_files();
[$imagesValid, $validatedImages, $imageValidationError] = reviews_api_validate_uploaded_images($rawUploadedImages);

if (!$imagesValid) {
    reviews_api_json([
        'ok' => false,
        'message' => $imageValidationError !== '' ? $imageValidationError : 'Invalid review images.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 422);
}

$eligibility = reviews_api_check_eligibility($con, $userId, $productId);
$existingReview = is_array($eligibility['existing_review'] ?? null) ? $eligibility['existing_review'] : null;

if (!$existingReview && !(bool)($eligibility['can_review'] ?? false)) {
    reviews_api_json([
        'ok' => false,
        'message' => (string)($eligibility['message'] ?? 'You are not eligible to review this product.'),
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

if ($existingReview && (int)($existingReview['is_locked'] ?? 0) === 1) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Your review is locked by admin and cannot be updated.',
        'logged_in' => true,
        'payload' => reviews_api_payload($con, $productId, $userId),
        'csrf_token' => $_SESSION['csrf_token'],
    ], 423);
}

if ($existingReview) {
    $reviewId = (int)($existingReview['id'] ?? 0);
    $replaceImages = !empty($validatedImages) || $removeExistingImages;
    $oldImagePaths = [];
    $storedImages = [];

    $stmt = $con->prepare(
        'UPDATE product_reviews
         SET rating = ?, review_text = ?, updated_at = NOW()
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to update your review right now.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    if (!$con->begin_transaction()) {
        $stmt->close();
        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to update your review right now.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    $stmt->bind_param('isii', $rating, $reviewText, $reviewId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $con->rollback();
        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to update your review right now.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    if ($replaceImages) {
        $oldImagePaths = reviews_api_delete_image_rows($con, $reviewId);

        if (!empty($validatedImages)) {
            [$storeOk, $storedImages, $storeError] = reviews_api_store_images($validatedImages, $reviewId, $userId);
            if (!$storeOk) {
                $con->rollback();
                $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
                reviews_api_delete_files($newPaths);

                reviews_api_json([
                    'ok' => false,
                    'message' => $storeError !== '' ? $storeError : 'Unable to save review images.',
                    'csrf_token' => $_SESSION['csrf_token'],
                ], 500);
            }

            if (!reviews_api_insert_image_rows($con, $reviewId, $storedImages)) {
                $con->rollback();
                $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
                reviews_api_delete_files($newPaths);

                reviews_api_json([
                    'ok' => false,
                    'message' => 'Unable to save review images.',
                    'csrf_token' => $_SESSION['csrf_token'],
                ], 500);
            }
        }
    }

    if (!$con->commit()) {
        $con->rollback();
        if (!empty($storedImages)) {
            $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
            reviews_api_delete_files($newPaths);
        }

        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to update your review right now.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    if ($replaceImages && !empty($oldImagePaths)) {
        reviews_api_delete_files($oldImagePaths);
    }

    reviews_api_json([
        'ok' => true,
        'message' => $replaceImages
            ? (!empty($validatedImages)
                ? 'Your review has been updated and images replaced.'
                : 'Your review has been updated and existing images were removed.')
            : 'Your review has been updated.',
        'logged_in' => true,
        'payload' => reviews_api_payload($con, $productId, $userId),
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

$orderId = (int)($eligibility['eligible_order_id'] ?? 0);

if (!$con->begin_transaction()) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

$stmt = $con->prepare(
    'INSERT INTO product_reviews (user_id, product_id, order_id, rating, review_text, is_verified_purchase, is_visible)
    VALUES (?, ?, ?, ?, ?, 1, 1)
    ON DUPLICATE KEY UPDATE
       id = LAST_INSERT_ID(id),
    order_id = IF(is_locked = 1, order_id, VALUES(order_id)),
    rating = IF(is_locked = 1, rating, VALUES(rating)),
    review_text = IF(is_locked = 1, review_text, VALUES(review_text)),
    updated_at = IF(is_locked = 1, updated_at, NOW())'
);

if (!$stmt) {
    $con->rollback();
    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

$stmt->bind_param('iiiis', $userId, $productId, $orderId, $rating, $reviewText);
$ok = $stmt->execute();
$affectedRows = (int)$stmt->affected_rows;
$reviewId = (int)$stmt->insert_id;
$stmt->close();

if (!$ok || $reviewId <= 0) {
    $con->rollback();

    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

$didConcurrentUpsert = $affectedRows > 1;
$oldImagePaths = [];
$removedExistingImages = false;

// Re-check lock state after upsert to guard against admin locks applied mid-request.
$lockStateStmt = $con->prepare(
    'SELECT is_locked
     FROM product_reviews
     WHERE id = ?
     LIMIT 1'
);

if ($lockStateStmt) {
    $lockStateStmt->bind_param('i', $reviewId);
    $lockStateStmt->execute();
    $lockStateResult = $lockStateStmt->get_result();
    $lockStateRow = $lockStateResult ? $lockStateResult->fetch_assoc() : null;
    $lockStateStmt->close();

    if (is_array($lockStateRow) && (int)($lockStateRow['is_locked'] ?? 0) === 1) {
        $con->rollback();
        reviews_api_json([
            'ok' => false,
            'message' => 'Your review is locked by admin and cannot be updated.',
            'logged_in' => true,
            'payload' => reviews_api_payload($con, $productId, $userId),
            'csrf_token' => $_SESSION['csrf_token'],
        ], 423);
    }
}

$storedImages = [];
if ($didConcurrentUpsert && ($removeExistingImages || !empty($validatedImages))) {
    $oldImagePaths = reviews_api_delete_image_rows($con, $reviewId);
    $removedExistingImages = !empty($oldImagePaths);
}

if (!empty($validatedImages)) {
    [$storeOk, $storedImages, $storeError] = reviews_api_store_images($validatedImages, $reviewId, $userId);
    if (!$storeOk) {
        $con->rollback();
        $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
        reviews_api_delete_files($newPaths);

        reviews_api_json([
            'ok' => false,
            'message' => $storeError !== '' ? $storeError : 'Unable to save review images.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    if (!reviews_api_insert_image_rows($con, $reviewId, $storedImages)) {
        $con->rollback();
        $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
        reviews_api_delete_files($newPaths);

        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to save review images.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }
}

if (!$con->commit()) {
    $con->rollback();
    if (!empty($storedImages)) {
        $newPaths = array_map(static fn(array $image): string => (string)($image['path'] ?? ''), $storedImages);
        reviews_api_delete_files($newPaths);
    }

    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

if (!empty($oldImagePaths)) {
    reviews_api_delete_files($oldImagePaths);
}

reviews_api_json([
    'ok' => true,
    'message' => $didConcurrentUpsert
        ? (!empty($storedImages)
            ? 'Your existing review was updated and images were replaced.'
            : ($removedExistingImages
                ? 'Your existing review was updated and previous images were removed.'
                : 'Your existing review was updated successfully.'))
        : (!empty($storedImages)
            ? 'Thanks! Your review with images was submitted successfully.'
            : 'Thanks! Your review was submitted successfully.'),
    'logged_in' => true,
    'payload' => reviews_api_payload($con, $productId, $userId),
    'csrf_token' => $_SESSION['csrf_token'],
]);
