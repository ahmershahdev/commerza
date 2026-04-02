<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

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
    if (isset($_REQUEST['action'])) {
        return strtolower(trim((string)$_REQUEST['action']));
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
            admin_note VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_review_user_product (user_id, product_id),
            KEY idx_review_product_visible (product_id, is_visible),
            KEY idx_review_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

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

    $where = '';
    if ($visibility === 'visible') {
        $where = 'WHERE r.is_visible = 1';
    } elseif ($visibility === 'hidden') {
        $where = 'WHERE r.is_visible = 0';
    }

    $sql =
        'SELECT
            r.id,
            r.user_id,
            r.product_id,
            r.order_id,
            r.rating,
            r.review_text,
            r.is_verified_purchase,
            r.is_visible,
            r.admin_note,
            r.created_at,
            r.updated_at,
            u.full_name AS user_name,
            u.email AS user_email,
            p.name AS product_name,
            o.order_number
         FROM product_reviews r
         INNER JOIN users u ON u.id = r.user_id
         INNER JOIN products p ON p.id = r.product_id
         LEFT JOIN orders o ON o.id = r.order_id
         ' . $where . '
         ORDER BY r.updated_at DESC, r.id DESC
         LIMIT 500';

    $result = $con->query($sql);
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
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
            'adminNote' => (string)($row['admin_note'] ?? ''),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'updatedAt' => (string)($row['updated_at'] ?? ''),
            'images' => admin_reviews_review_images($con, (int)($row['id'] ?? 0)),
        ];
    }

    return $rows;
}

function admin_reviews_stats(mysqli $con): array
{
    $result = $con->query(
        'SELECT
            COUNT(*) AS total_reviews,
            COALESCE(SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END), 0) AS visible_reviews,
            COALESCE(SUM(CASE WHEN is_visible = 0 THEN 1 ELSE 0 END), 0) AS hidden_reviews,
            COALESCE(AVG(rating), 0) AS average_rating
         FROM product_reviews'
    );

    $row = $result ? $result->fetch_assoc() : null;

    return [
        'total' => (int)($row['total_reviews'] ?? 0),
        'visible' => (int)($row['visible_reviews'] ?? 0),
        'hidden' => (int)($row['hidden_reviews'] ?? 0),
        'averageRating' => round((float)($row['average_rating'] ?? 0), 2),
    ];
}

function admin_reviews_exists(mysqli $con, int $reviewId): bool
{
    if ($reviewId <= 0) {
        return false;
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

admin_require_login_api($con);
admin_reviews_ensure_table($con);
admin_reviews_ensure_images_table($con);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = admin_reviews_action();

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

    if ($reviewId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    $stmt = $con->prepare(
        'UPDATE product_reviews
         SET is_visible = ?, updated_at = NOW()
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to update review visibility.',
        ], 500);
    }

    $stmt->bind_param('ii', $isVisible, $reviewId);
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

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review visibility updated.',
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

    if ($reviewId <= 0) {
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

    $stmt = $con->prepare(
        'UPDATE product_reviews
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

    $stmt->bind_param('issi', $rating, $reviewText, $adminNote, $reviewId);
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
    if ($reviewId <= 0) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Invalid review id.',
        ], 422);
    }

    $imagePaths = [];
    $imageStmt = $con->prepare('SELECT image_path FROM product_review_images WHERE review_id = ?');
    if ($imageStmt) {
        $imageStmt->bind_param('i', $reviewId);
        $imageStmt->execute();
        $imageResult = $imageStmt->get_result();
        while ($imageResult && ($imageRow = $imageResult->fetch_assoc())) {
            $imagePaths[] = (string)($imageRow['image_path'] ?? '');
        }
        $imageStmt->close();
    }

    $stmt = $con->prepare('DELETE FROM product_reviews WHERE id = ? LIMIT 1');
    if (!$stmt) {
        admin_reviews_json([
            'ok' => false,
            'message' => 'Unable to delete review.',
        ], 500);
    }

    $stmt->bind_param('i', $reviewId);
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

    admin_reviews_json([
        'ok' => true,
        'message' => 'Review deleted successfully.',
        'payload' => [
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
