<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/auth.php';

/** @var mysqli|null $con */
$con = (isset($con) && $con instanceof mysqli)
    ? $con
    : (($GLOBALS['con'] ?? null) instanceof mysqli ? $GLOBALS['con'] : null);

if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
    exit;
}

function admin_reviews_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_reviews_request_body(): array
{
    static $body = null;

    if (is_array($body)) {
        return $body;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    $body = is_array($decoded) ? $decoded : [];

    return $body;
}

function admin_reviews_action(): string
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET' && isset($_GET['action'])) {
        return strtolower(trim((string)$_GET['action']));
    }

    if ($method === 'POST' && isset($_POST['action'])) {
        return strtolower(trim((string)$_POST['action']));
    }

    $body = admin_reviews_request_body();
    return strtolower(trim((string)($body['action'] ?? 'list')));
}

function admin_reviews_csrf_from_request(): string
{
    $headerToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    if (isset($_POST['csrf_token'])) {
        return trim((string)$_POST['csrf_token']);
    }

    $body = admin_reviews_request_body();
    return trim((string)($body['csrf_token'] ?? ''));
}

function admin_reviews_require_csrf(): void
{
    if (!admin_validate_csrf_token(admin_reviews_csrf_from_request())) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Forbidden.',
        ], 403);
    }
}

function admin_reviews_column_exists(mysqli $con, string $table, string $column): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $columnEscaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM {$tableEscaped} LIKE '{$columnEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function admin_reviews_table_exists(mysqli $con, string $table): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $result = $con->query("SHOW TABLES LIKE '{$tableEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function admin_reviews_index_exists(mysqli $con, string $table, string $indexName): bool
{
    $tableEscaped = $con->real_escape_string($table);
    $indexEscaped = $con->real_escape_string($indexName);
    $result = $con->query("SHOW INDEX FROM {$tableEscaped} WHERE Key_name = '{$indexEscaped}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function admin_reviews_foreign_key_exists(mysqli $con, string $table, string $constraintName): bool
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

function admin_reviews_ensure_table(mysqli $con): void
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
        if (admin_reviews_column_exists($con, 'product_reviews', $column)) {
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

function admin_reviews_ensure_images_table(mysqli $con): void
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

function admin_reviews_ensure_fake_table(mysqli $con): void
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

    if (!admin_reviews_table_exists($con, 'product_fake_reviews')) {
        return;
    }

    $legacyForeignKeys = [
        'fk_fake_reviews_review',
        'fk_fake_reviews_user',
        'fk_pfr_review',
        'fk_pfr_user',
    ];

    foreach ($legacyForeignKeys as $constraintName) {
        if (!admin_reviews_foreign_key_exists($con, 'product_fake_reviews', $constraintName)) {
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
        if (admin_reviews_column_exists($con, 'product_fake_reviews', $column)) {
            continue;
        }

        $con->query($sql);
    }

    if (admin_reviews_column_exists($con, 'product_fake_reviews', 'review_id')) {
        $con->query('ALTER TABLE product_fake_reviews MODIFY COLUMN review_id INT DEFAULT NULL');
    }

    if (admin_reviews_column_exists($con, 'product_fake_reviews', 'fake_user_id')) {
        $con->query('ALTER TABLE product_fake_reviews MODIFY COLUMN fake_user_id INT DEFAULT NULL');
    }

    if (!admin_reviews_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_product_visibility')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_product_visibility (product_id, is_visible)');
    }

    if (!admin_reviews_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_visibility_updated')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_visibility_updated (is_visible, updated_at)');
    }

    if (!admin_reviews_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_locked_updated')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_locked_updated (is_locked, updated_at)');
    }

    if (!admin_reviews_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_product_created')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_product_created (product_id, created_at)');
    }

    if (!admin_reviews_index_exists($con, 'product_fake_reviews', 'idx_fake_reviews_admin_created')) {
        $con->query('ALTER TABLE product_fake_reviews ADD KEY idx_fake_reviews_admin_created (generated_by_admin_id, created_at)');
    }

    $hasUserVisibility = admin_reviews_column_exists($con, 'users', 'profile_visibility');
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

function admin_reviews_review_images(mysqli $con, int $reviewId): array
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

function admin_reviews_delete_files(array $paths): void
{
    foreach ($paths as $relativePath) {
        $relativePath = trim(str_replace('\\', '/', (string)$relativePath));
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

function admin_reviews_fetch_all(mysqli $con, string $visibility = 'all'): array
{
    $rows = [];
    $whereReal = '';
    $whereFake = '';

    if ($visibility === 'visible') {
        $whereReal = ' WHERE r.is_visible = 1';
        $whereFake = ' WHERE f.is_visible = 1';
    } elseif ($visibility === 'hidden') {
        $whereReal = ' WHERE r.is_visible = 0';
        $whereFake = ' WHERE f.is_visible = 0';
    }

    $realSql =
        'SELECT
            r.id,
            r.user_id,
            r.product_id,
            r.order_id,
            r.rating,
            r.review_text,
            r.is_verified_purchase,
            r.is_visible,
            r.is_locked,
            r.locked_at,
            r.locked_by_admin_id,
            r.admin_note,
            r.created_at,
            r.updated_at,
            u.full_name AS user_name,
            u.email AS user_email,
            p.name AS product_name,
            o.order_number
         FROM product_reviews r
         LEFT JOIN users u ON u.id = r.user_id
         INNER JOIN products p ON p.id = r.product_id
         LEFT JOIN orders o ON o.id = r.order_id'
        . $whereReal
        . ' ORDER BY r.updated_at DESC, r.id DESC LIMIT 500';

    $realResult = $con->query($realSql);
    while ($realResult && ($row = $realResult->fetch_assoc())) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'userId' => (int)($row['user_id'] ?? 0),
            'productId' => (int)($row['product_id'] ?? 0),
            'orderId' => (int)($row['order_id'] ?? 0),
            'orderNumber' => (string)($row['order_number'] ?? ''),
            'userName' => (string)($row['user_name'] ?? 'Customer'),
            'userEmail' => (string)($row['user_email'] ?? ''),
            'productName' => (string)($row['product_name'] ?? 'Product'),
            'rating' => max(1, min(5, (int)($row['rating'] ?? 0))),
            'reviewText' => (string)($row['review_text'] ?? ''),
            'isVerifiedPurchase' => (int)($row['is_verified_purchase'] ?? 0) === 1,
            'isVisible' => (int)($row['is_visible'] ?? 0) === 1,
            'isLocked' => (int)($row['is_locked'] ?? 0) === 1,
            'lockedAt' => (string)($row['locked_at'] ?? ''),
            'lockedByAdminId' => (int)($row['locked_by_admin_id'] ?? 0),
            'adminNote' => (string)($row['admin_note'] ?? ''),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'updatedAt' => (string)($row['updated_at'] ?? ''),
            'isFake' => false,
            'images' => admin_reviews_review_images($con, (int)($row['id'] ?? 0)),
        ];
    }

    if (admin_reviews_table_exists($con, 'product_fake_reviews')) {
        $fakeSql =
            'SELECT
                f.id,
                f.product_id,
                f.rating,
                f.review_text,
                f.reviewer_name,
                f.reviewer_handle,
                f.reviewer_visibility,
                f.is_visible,
                f.is_locked,
                f.locked_at,
                f.locked_by_admin_id,
                f.admin_note,
                f.created_at,
                f.updated_at,
                p.name AS product_name
             FROM product_fake_reviews f
             INNER JOIN products p ON p.id = f.product_id'
            . $whereFake
            . ' ORDER BY f.updated_at DESC, f.id DESC LIMIT 500';

        $fakeResult = $con->query($fakeSql);
        while ($fakeResult && ($row = $fakeResult->fetch_assoc())) {
            $reviewerName = trim((string)($row['reviewer_name'] ?? ''));
            $reviewerHandle = trim((string)($row['reviewer_handle'] ?? ''));
            $displayName = $reviewerName !== '' ? $reviewerName : 'Customer';

            if ($reviewerHandle !== '' && strtolower(trim((string)($row['reviewer_visibility'] ?? 'public'))) === 'public') {
                $displayName = $reviewerHandle;
            }

            $rows[] = [
                'id' => -1 * max(1, (int)($row['id'] ?? 0)),
                'userId' => 0,
                'productId' => (int)($row['product_id'] ?? 0),
                'orderId' => 0,
                'orderNumber' => '',
                'userName' => $displayName,
                'userEmail' => '',
                'productName' => (string)($row['product_name'] ?? 'Product'),
                'rating' => max(1, min(5, (int)($row['rating'] ?? 0))),
                'reviewText' => (string)($row['review_text'] ?? ''),
                'isVerifiedPurchase' => false,
                'isVisible' => (int)($row['is_visible'] ?? 0) === 1,
                'isLocked' => (int)($row['is_locked'] ?? 0) === 1,
                'lockedAt' => (string)($row['locked_at'] ?? ''),
                'lockedByAdminId' => (int)($row['locked_by_admin_id'] ?? 0),
                'adminNote' => (string)($row['admin_note'] ?? ''),
                'createdAt' => (string)($row['created_at'] ?? ''),
                'updatedAt' => (string)($row['updated_at'] ?? ''),
                'isFake' => true,
                'images' => [],
            ];
        }
    }

    usort($rows, static function (array $a, array $b): int {
        $aTs = strtotime((string)($a['updatedAt'] ?? '')) ?: 0;
        $bTs = strtotime((string)($b['updatedAt'] ?? '')) ?: 0;

        if ($aTs === $bTs) {
            return abs((int)($b['id'] ?? 0)) <=> abs((int)($a['id'] ?? 0));
        }

        return $bTs <=> $aTs;
    });

    if (count($rows) > 500) {
        $rows = array_slice($rows, 0, 500);
    }

    return $rows;
}

function admin_reviews_stats(mysqli $con): array
{
    $statsSql = admin_reviews_table_exists($con, 'product_fake_reviews')
        ? 'SELECT
                     COUNT(*) AS total_reviews,
                     COALESCE(SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END), 0) AS visible_reviews,
                     COALESCE(SUM(CASE WHEN is_visible = 0 THEN 1 ELSE 0 END), 0) AS hidden_reviews,
                     COALESCE(SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END), 0) AS locked_reviews,
                     COALESCE(AVG(rating), 0) AS average_rating
                 FROM (
                     SELECT rating, is_visible, is_locked FROM product_reviews
                     UNION ALL
                     SELECT rating, is_visible, is_locked FROM product_fake_reviews
                 ) review_union'
        : 'SELECT
                     COUNT(*) AS total_reviews,
                     COALESCE(SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END), 0) AS visible_reviews,
                     COALESCE(SUM(CASE WHEN is_visible = 0 THEN 1 ELSE 0 END), 0) AS hidden_reviews,
                     COALESCE(SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END), 0) AS locked_reviews,
                     COALESCE(AVG(rating), 0) AS average_rating
                 FROM product_reviews';

    $result = $con->query($statsSql);

    $row = $result ? $result->fetch_assoc() : null;

    return [
        'total' => (int)($row['total_reviews'] ?? 0),
        'visible' => (int)($row['visible_reviews'] ?? 0),
        'hidden' => (int)($row['hidden_reviews'] ?? 0),
        'locked' => (int)($row['locked_reviews'] ?? 0),
        'averageRating' => round((float)($row['average_rating'] ?? 0), 2),
    ];
}

function admin_reviews_exists(mysqli $con, int $reviewId): bool
{
    if ($reviewId === 0) {
        return false;
    }

    if ($reviewId < 0) {
        $fakeId = abs($reviewId);
        $stmt = $con->prepare('SELECT id FROM product_fake_reviews WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $fakeId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    $stmt = $con->prepare('SELECT id FROM product_reviews WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function admin_reviews_fake_profile(): array
{
    $firstNames = [
        'Aiden',
        'Noah',
        'Liam',
        'Ethan',
        'Mason',
        'Lucas',
        'Aria',
        'Maya',
        'Ayla',
        'Zara',
        'Hina',
        'Areeba',
        'Sara',
        'Daniyal',
        'Hamza',
        'Talha',
        'Usman',
        'Ibrahim',
        'Rayyan',
        'Zayan',
    ];
    $lastNames = [
        'Khan',
        'Raza',
        'Ali',
        'Malik',
        'Shah',
        'Sheikh',
        'Butt',
        'Qureshi',
        'Hussain',
        'Mirza',
    ];

    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];

    return [
        'full_name' => trim($first . ' ' . $last),
        'username_base' => strtolower(preg_replace('/[^a-z0-9_]+/i', '', $first . $last) ?? 'reviewer'),
    ];
}

function admin_reviews_fake_identity(): array
{
    $profile = admin_reviews_fake_profile();
    $base = trim((string)($profile['username_base'] ?? 'reviewer'));
    if ($base === '') {
        $base = 'reviewer';
    }

    $suffix = (string)random_int(10, 9999);
    $handle = substr($base . $suffix, 0, 28);
    $visibility = random_int(1, 100) <= 82 ? 'public' : 'private';

    return [
        'reviewer_name' => (string)($profile['full_name'] ?? 'Customer'),
        'reviewer_handle' => $handle,
        'reviewer_visibility' => $visibility,
    ];
}

function admin_reviews_random_review_text(string $productName, int $rating): string
{
    $safeProductName = trim($productName) !== '' ? trim($productName) : 'this product';

    $positive = [
        'This watch is awesome, movement is smooth, and the finishing feels premium.',
        'Looks even better in person and sits very comfortably on the wrist for daily use.',
        'Dial clarity is excellent and the strap quality feels solid for long wear.',
        'Packaging was clean, delivery was on time, and the watch quality matched expectations.',
        'Great everyday piece with accurate timekeeping and a classy overall look.',
        'Very balanced design, not too flashy, and the build quality feels reliable.',
    ];

    $neutral = [
        'Overall good watch with a smooth movement, but the price is a bit high for this segment.',
        'Design is clean and performance is stable, though the strap could be a little softer.',
        'Good value for regular use, but finishing around the case could be improved slightly.',
        'Nice watch for daily wear, works well, just expected a bit more premium weight.',
        'Timekeeping is accurate and comfort is fine, but the dial color appears slightly different in low light.',
    ];

    $critical = [
        'The watch is usable and movement is acceptable, but overall finishing needs improvement.',
        'Looks decent from a distance, but I expected better quality control at this price.',
        'Comfort is average and the strap quality feels basic compared to the listing.',
        'Performance is okay for now, though value for money feels only moderate.',
    ];

    $openers = [
        "Bought {$safeProductName} recently",
        "Using {$safeProductName} for a few days",
        "After trying {$safeProductName}",
        "My experience with {$safeProductName}",
    ];

    $closers = [
        'Happy with the purchase overall.',
        'Would still recommend it for regular wear.',
        'Decent pick if you like this style.',
        'Works fine for daily routine.',
        'Good option with a few trade-offs.',
    ];

    if ($rating >= 5) {
        $pool = $positive;
    } elseif ($rating >= 3) {
        $pool = $neutral;
    } else {
        $pool = $critical;
    }

    $opener = $openers[array_rand($openers)];
    $line = $pool[array_rand($pool)];
    $closer = $closers[array_rand($closers)];

    return $opener . '. ' . $line . ' ' . $closer;
}

$admin = admin_require_login_api($con);
admin_require_permission_api($admin, 'reviews.manage');
admin_reviews_ensure_table($con);
admin_reviews_ensure_images_table($con);
admin_reviews_ensure_fake_table($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = admin_reviews_action();

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_reviews_api', $action),
    120,
    60,
    120,
    300
);

if ($method === 'GET') {
    if ($action !== 'list') {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid action.',
        ], 400);
    }

    $visibility = strtolower(trim((string)($_GET['visibility'] ?? 'all')));
    if (!in_array($visibility, ['all', 'visible', 'hidden'], true)) {
        $visibility = 'all';
    }

    admin_reviews_json([
        'ok' => true,
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con, $visibility),
            'stats' => admin_reviews_stats($con),
            'visibility' => $visibility,
        ],
    ]);
}

if ($method !== 'POST') {
    admin_reviews_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

admin_reviews_require_csrf();
$body = admin_reviews_request_body();

if ($action === 'set-visibility') {
    $reviewId = (int)($body['id'] ?? 0);
    $isVisible = (int)($body['is_visible'] ?? 0) === 1 ? 1 : 0;
    $isFake = $reviewId < 0;
    $targetId = abs($reviewId);

    if ($targetId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    $table = $isFake ? 'product_fake_reviews' : 'product_reviews';
    $stmt = $con->prepare('UPDATE ' . $table . ' SET is_visible = ?, updated_at = NOW() WHERE id = ? LIMIT 1');

    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review visibility.',
        ], 500);
    }

    $stmt->bind_param('ii', $isVisible, $targetId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review visibility.',
        ], 500);
    }

    if ($affected < 1) {
        if (!admin_reviews_exists($con, $reviewId)) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        admin_reviews_json([
            'ok' => true,
            'message' => 'Review visibility already set.',
            'payload' => [
                'reviews' => admin_reviews_fetch_all($con),
                'stats' => admin_reviews_stats($con),
            ],
        ]);
    }

    admin_api_log_security_event($con, $admin, 'review.visibility_changed', 'info', [
        'review_id' => $reviewId,
        'is_visible' => $isVisible,
        'source' => $isFake ? 'fake' : 'real',
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review visibility updated.',
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'set-lock') {
    $reviewId = (int)($body['id'] ?? 0);
    $isLocked = (int)($body['is_locked'] ?? 0) === 1 ? 1 : 0;
    $adminId = (int)($admin['id'] ?? 0);
    $isFake = $reviewId < 0;
    $targetId = abs($reviewId);

    if ($targetId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    $table = $isFake ? 'product_fake_reviews' : 'product_reviews';
    $stmt = $con->prepare(
        'UPDATE ' . $table . '
         SET is_locked = ?,
             locked_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END,
             locked_by_admin_id = CASE WHEN ? = 1 THEN ? ELSE NULL END,
             updated_at = NOW()
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review lock state.',
        ], 500);
    }

    $stmt->bind_param('iiiii', $isLocked, $isLocked, $isLocked, $adminId, $targetId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review lock state.',
        ], 500);
    }

    if ($affected < 1) {
        if (!admin_reviews_exists($con, $reviewId)) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        admin_reviews_json([
            'ok' => true,
            'message' => 'Review lock state already set.',
            'payload' => [
                'reviews' => admin_reviews_fetch_all($con),
                'stats' => admin_reviews_stats($con),
            ],
        ]);
    }

    admin_api_log_security_event($con, $admin, 'review.lock_state_changed', 'info', [
        'review_id' => $reviewId,
        'is_locked' => $isLocked,
        'admin_id' => $adminId,
        'source' => $isFake ? 'fake' : 'real',
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => $isLocked === 1 ? 'Review locked successfully.' : 'Review unlocked successfully.',
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'update-review') {
    $reviewId = (int)($body['id'] ?? 0);
    $rating = (int)($body['rating'] ?? 0);
    $reviewText = trim((string)($body['review_text'] ?? ''));
    $adminNote = trim((string)($body['admin_note'] ?? ''));
    $isFake = $reviewId < 0;
    $targetId = abs($reviewId);

    if ($targetId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    if ($rating < 1 || $rating > 5) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Rating must be between 1 and 5.',
        ], 422);
    }

    if (strlen($reviewText) < 10 || strlen($reviewText) > 500) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Review text must be 10 to 500 characters.',
        ], 422);
    }

    if (strlen($adminNote) > 500) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Admin note can be up to 500 characters.',
        ], 422);
    }

    $table = $isFake ? 'product_fake_reviews' : 'product_reviews';
    $lockStmt = $con->prepare('SELECT is_locked FROM ' . $table . ' WHERE id = ? LIMIT 1');
    if (!$lockStmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to verify review lock state.',
        ], 500);
    }

    $lockStmt->bind_param('i', $targetId);
    $lockStmt->execute();
    $lockRow = $lockStmt->get_result()?->fetch_assoc();
    $lockStmt->close();

    if (!is_array($lockRow)) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Review not found.',
        ], 404);
    }

    if ((int)($lockRow['is_locked'] ?? 0) === 1) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Review is locked. Unlock it before editing.',
        ], 423);
    }

    $stmt = $con->prepare(
        'UPDATE ' . $table . '
         SET rating = ?, review_text = ?, admin_note = ?, updated_at = NOW()
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review.',
        ], 500);
    }

    $stmt->bind_param('issi', $rating, $reviewText, $adminNote, $targetId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review.',
        ], 500);
    }

    if ($affected < 1) {
        if (!admin_reviews_exists($con, $reviewId)) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        admin_reviews_json([
            'ok' => true,
            'message' => 'Review already up to date.',
            'payload' => [
                'reviews' => admin_reviews_fetch_all($con),
                'stats' => admin_reviews_stats($con),
            ],
        ]);
    }

    admin_api_log_security_event($con, $admin, 'review.updated', 'info', [
        'review_id' => $reviewId,
        'rating' => $rating,
        'admin_note_length' => strlen($adminNote),
        'source' => $isFake ? 'fake' : 'real',
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review updated successfully.',
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'delete-review') {
    $reviewId = (int)($body['id'] ?? 0);
    $isFake = $reviewId < 0;
    $targetId = abs($reviewId);

    if ($targetId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    $imagePaths = [];
    if (!$isFake) {
        $imageStmt = $con->prepare('SELECT image_path FROM product_review_images WHERE review_id = ?');
        if ($imageStmt) {
            $imageStmt->bind_param('i', $targetId);
            $imageStmt->execute();
            $imageResult = $imageStmt->get_result();
            while ($imageResult && ($imageRow = $imageResult->fetch_assoc())) {
                $imagePaths[] = (string)($imageRow['image_path'] ?? '');
            }
            $imageStmt->close();
        }
    }

    $table = $isFake ? 'product_fake_reviews' : 'product_reviews';
    $stmt = $con->prepare('DELETE FROM ' . $table . ' WHERE id = ? LIMIT 1');
    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to delete review.',
        ], 500);
    }

    $stmt->bind_param('i', $targetId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok || $affected < 1) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Review not found.',
        ], 404);
    }

    admin_reviews_delete_files($imagePaths);

    admin_api_log_security_event($con, $admin, 'review.deleted', 'warning', [
        'review_id' => $reviewId,
        'deleted_images' => count($imagePaths),
        'source' => $isFake ? 'fake' : 'real',
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review deleted successfully.',
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'add-fake-bulk-reviews') {
    $productId = (int)($body['product_id'] ?? 0);
    $count = (int)($body['count'] ?? 1);
    $count = max(1, min(80, $count));
    $ratingMin = (int)($body['rating_min'] ?? 3);
    $ratingMax = (int)($body['rating_max'] ?? 5);
    $isVisible = (int)($body['is_visible'] ?? 1) === 1 ? 1 : 0;

    $ratingMin = max(1, min(5, $ratingMin));
    $ratingMax = max(1, min(5, $ratingMax));
    if ($ratingMin > $ratingMax) {
        [$ratingMin, $ratingMax] = [$ratingMax, $ratingMin];
    }

    if ($productId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Product ID is required for fake reviews.',
        ], 422);
    }

    $productStmt = $con->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
    if (!$productStmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to validate fake review target.',
        ], 500);
    }

    $productStmt->bind_param('i', $productId);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    $productRow = $productResult ? $productResult->fetch_assoc() : null;
    $productStmt->close();

    if (!is_array($productRow)) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Product not found.',
        ], 404);
    }

    $productName = trim((string)($productRow['name'] ?? 'Product'));
    if ($productName === '') {
        $productName = 'Product';
    }

    $insertStmt = $con->prepare(
        'INSERT INTO product_fake_reviews (
            product_id,
            rating,
            review_text,
            reviewer_name,
            reviewer_handle,
            reviewer_visibility,
            is_visible,
            admin_note,
            generated_by_admin_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$insertStmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to prepare fake review insertion.',
        ], 500);
    }

    $inserted = 0;
    $failed = 0;
    $adminNote = 'Admin generated fake review';
    $generatedByAdminId = (int)($admin['id'] ?? 0);

    for ($index = 0; $index < $count; $index++) {
        $identity = admin_reviews_fake_identity();
        $rating = random_int($ratingMin, $ratingMax);
        $reviewText = admin_reviews_random_review_text($productName, $rating);
        $reviewerName = trim((string)($identity['reviewer_name'] ?? 'Customer'));
        if ($reviewerName === '') {
            $reviewerName = 'Customer';
        }

        $reviewerHandle = trim((string)($identity['reviewer_handle'] ?? ''));
        $reviewerVisibility = strtolower(trim((string)($identity['reviewer_visibility'] ?? 'public')));
        if (!in_array($reviewerVisibility, ['public', 'private'], true)) {
            $reviewerVisibility = 'public';
        }

        $insertStmt->bind_param(
            'iissssisi',
            $productId,
            $rating,
            $reviewText,
            $reviewerName,
            $reviewerHandle,
            $reviewerVisibility,
            $isVisible,
            $adminNote,
            $generatedByAdminId
        );

        if (!$insertStmt->execute()) {
            $failed++;
            continue;
        }

        $inserted++;
    }

    $insertStmt->close();

    if ($inserted <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to generate fake reviews. Please retry.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'review.fake_bulk_created', 'warning', [
        'product_id' => $productId,
        'requested_count' => $count,
        'inserted_count' => $inserted,
        'failed_count' => $failed,
        'rating_min' => $ratingMin,
        'rating_max' => $ratingMax,
        'is_visible' => $isVisible,
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => $failed > 0
            ? "Generated {$inserted} fake review(s). {$failed} could not be created."
            : "Generated {$inserted} fake review(s) successfully.",
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'delete-all-fake-reviews') {
    $legacyReviewIds = [];
    $fakeIds = [];

    $fakeRows = $con->query('SELECT id, review_id FROM product_fake_reviews');
    while ($fakeRows && ($row = $fakeRows->fetch_assoc())) {
        $fakeId = (int)($row['id'] ?? 0);
        if ($fakeId > 0) {
            $fakeIds[] = $fakeId;
        }

        $legacyReviewId = (int)($row['review_id'] ?? 0);
        if ($legacyReviewId > 0) {
            $legacyReviewIds[] = $legacyReviewId;
        }
    }

    $legacyMarkerResult = $con->query(
        'SELECT id
         FROM product_reviews
         WHERE is_verified_purchase = 0
           AND order_id IS NULL
           AND admin_note = "Admin generated fake review"'
    );

    while ($legacyMarkerResult && ($row = $legacyMarkerResult->fetch_assoc())) {
        $legacyReviewId = (int)($row['id'] ?? 0);
        if ($legacyReviewId > 0) {
            $legacyReviewIds[] = $legacyReviewId;
        }
    }

    $legacyReviewIds = array_values(array_unique($legacyReviewIds));

    if (count($fakeIds) === 0 && count($legacyReviewIds) === 0) {
        admin_reviews_json([
            'ok' => true,
            'message' => 'No fake reviews found to delete.',
            'payload' => [
                'deletedCount' => 0,
                'reviews' => admin_reviews_fetch_all($con),
                'stats' => admin_reviews_stats($con),
            ],
        ]);
    }

    $imagePaths = [];
    if (count($legacyReviewIds) > 0) {
        $idList = implode(',', array_map(static fn(int $id): string => (string)$id, $legacyReviewIds));
        $imageResult = $con->query(
            'SELECT image_path
             FROM product_review_images
             WHERE review_id IN (' . $idList . ')'
        );

        while ($imageResult && ($imageRow = $imageResult->fetch_assoc())) {
            $imagePaths[] = (string)($imageRow['image_path'] ?? '');
        }
    }

    $deleteFakeOk = $con->query('DELETE FROM product_fake_reviews');
    if (!$deleteFakeOk) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to delete fake reviews. Please retry.',
        ], 500);
    }

    $deletedFakeCount = (int)$con->affected_rows;

    $deletedLegacyCount = 0;
    if (count($legacyReviewIds) > 0) {
        $idList = implode(',', array_map(static fn(int $id): string => (string)$id, $legacyReviewIds));
        $deleteLegacyOk = $con->query('DELETE FROM product_reviews WHERE id IN (' . $idList . ')');
        if (!$deleteLegacyOk) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Unable to delete legacy fake reviews. Please retry.',
            ], 500);
        }

        $deletedLegacyCount = (int)$con->affected_rows;
    }

    $deletedCount = $deletedFakeCount + $deletedLegacyCount;
    admin_reviews_delete_files($imagePaths);

    admin_api_log_security_event($con, $admin, 'review.fake_bulk_deleted', 'warning', [
        'deleted_count' => $deletedCount,
        'deleted_fake_table_count' => $deletedFakeCount,
        'deleted_legacy_review_count' => $deletedLegacyCount,
        'deleted_images' => count($imagePaths),
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => $deletedCount > 0
            ? "Deleted {$deletedCount} fake review(s) successfully."
            : 'No fake reviews were deleted.',
        'payload' => [
            'deletedCount' => $deletedCount,
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

if ($action === 'add-review') {
    $userId = (int)($body['user_id'] ?? 0);
    $productId = (int)($body['product_id'] ?? 0);
    $orderId = (int)($body['order_id'] ?? 0);
    $rating = (int)($body['rating'] ?? 0);
    $reviewText = trim((string)($body['review_text'] ?? ''));
    $adminNote = trim((string)($body['admin_note'] ?? ''));
    $isVisible = (int)($body['is_visible'] ?? 1) === 1 ? 1 : 0;

    if ($userId <= 0 || $productId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'User ID and Product ID are required.',
        ], 422);
    }

    if ($rating < 1 || $rating > 5) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Rating must be between 1 and 5.',
        ], 422);
    }

    if (strlen($reviewText) < 10 || strlen($reviewText) > 500) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Review text must be 10 to 500 characters.',
        ], 422);
    }

    if (strlen($adminNote) > 500) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Admin note can be up to 500 characters.',
        ], 422);
    }

    $userCheckStmt = $con->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $productCheckStmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');

    if (!$userCheckStmt || !$productCheckStmt) {
        if ($userCheckStmt) {
            $userCheckStmt->close();
        }
        if ($productCheckStmt) {
            $productCheckStmt->close();
        }

        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to validate review payload.',
        ], 500);
    }

    $userCheckStmt->bind_param('i', $userId);
    $userCheckStmt->execute();
    $userCheckStmt->store_result();
    $userExists = $userCheckStmt->num_rows > 0;
    $userCheckStmt->close();

    if (!$userExists) {
        $productCheckStmt->close();
        admin_reviews_json([
            'ok' => false,
            'message' => 'User not found.',
        ], 404);
    }

    $productCheckStmt->bind_param('i', $productId);
    $productCheckStmt->execute();
    $productCheckStmt->store_result();
    $productExists = $productCheckStmt->num_rows > 0;
    $productCheckStmt->close();

    if (!$productExists) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Product not found.',
        ], 404);
    }

    $effectiveOrderId = $orderId > 0 ? $orderId : null;
    if ($effectiveOrderId !== null) {
        $orderCheckStmt = $con->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
        if (!$orderCheckStmt) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Unable to validate order id.',
            ], 500);
        }

        $orderCheckStmt->bind_param('i', $effectiveOrderId);
        $orderCheckStmt->execute();
        $orderCheckStmt->store_result();
        $orderExists = $orderCheckStmt->num_rows > 0;
        $orderCheckStmt->close();

        if (!$orderExists) {
            admin_reviews_json([
                'ok' => false,
                'message' => 'Order not found.',
            ], 404);
        }
    }

    $orderIdValue = $effectiveOrderId ?? 0;

    $upsertStmt = $con->prepare(
        'INSERT INTO product_reviews (user_id, product_id, order_id, rating, review_text, is_verified_purchase, is_visible, admin_note)
         VALUES (?, ?, NULLIF(?, 0), ?, ?, 1, ?, ?)
         ON DUPLICATE KEY UPDATE
            order_id = VALUES(order_id),
            rating = VALUES(rating),
            review_text = VALUES(review_text),
            is_visible = VALUES(is_visible),
            admin_note = VALUES(admin_note),
            updated_at = NOW()'
    );

    if (!$upsertStmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to add review.',
        ], 500);
    }

    $upsertStmt->bind_param('iiiisis', $userId, $productId, $orderIdValue, $rating, $reviewText, $isVisible, $adminNote);
    $ok = $upsertStmt->execute();
    $upsertStmt->close();

    if (!$ok) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to add review.',
        ], 500);
    }

    admin_api_log_security_event($con, $admin, 'review.upserted', 'info', [
        'user_id' => $userId,
        'product_id' => $productId,
        'order_id' => $orderIdValue,
        'rating' => $rating,
        'is_visible' => $isVisible,
    ]);

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review added successfully.',
        'payload' => [
            'reviews' => admin_reviews_fetch_all($con),
            'stats' => admin_reviews_stats($con),
        ],
    ]);
}

admin_reviews_json([
    'ok' => false,
    'message' => 'Invalid action.',
], 400);
