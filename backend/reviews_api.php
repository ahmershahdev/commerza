<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/data.php';

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

function reviews_api_existing_user_review(mysqli $con, int $userId, int $productId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, rating, review_text, is_visible, created_at, updated_at
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

    $existingReview = reviews_api_existing_user_review($con, $userId, $productId);

    $sqlBase =
        'SELECT o.id
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ?
           AND oi.product_id = ?
           AND o.status = "Delivered"
           AND o.updated_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)';

    if (reviews_api_refund_table_exists($con)) {
        $sqlBase .=
            ' AND NOT EXISTS (
                SELECT 1
                FROM refund_requests rr
                WHERE rr.order_id = o.id
                  AND rr.status = "accepted"
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

    $stmt->bind_param('ii', $userId, $productId);
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

    return [
        'can_review' => false,
        'message' => 'Only delivered orders older than 7 days can be reviewed. Refunded orders are not eligible.',
        'eligible_order_id' => 0,
        'existing_review' => null,
    ];
}

function reviews_api_public_reviews(mysqli $con, int $productId): array
{
    $rows = [];

    $stmt = $con->prepare(
        'SELECT
            r.id,
            r.rating,
            r.review_text,
            r.created_at,
            r.updated_at,
            u.full_name
         FROM product_reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ?
           AND r.is_visible = 1
         ORDER BY r.updated_at DESC, r.id DESC
         LIMIT 30'
    );

    if ($stmt) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $name = trim((string)($row['full_name'] ?? 'Customer'));
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
            ];
        }

        $stmt->close();
    }

    $summary = [
        'count' => 0,
        'average' => 0,
    ];

    $summaryStmt = $con->prepare(
        'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average_rating
         FROM product_reviews
         WHERE product_id = ?
           AND is_visible = 1'
    );

    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $productId);
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
            'updated_at' => (string)($eligibility['existing_review']['updated_at'] ?? ''),
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

$rating = (int)($_POST['rating'] ?? 0);
$reviewText = trim((string)($_POST['review_text'] ?? ''));

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

$eligibility = reviews_api_check_eligibility($con, $userId, $productId);
$existingReview = is_array($eligibility['existing_review'] ?? null) ? $eligibility['existing_review'] : null;

if (!$existingReview && !(bool)($eligibility['can_review'] ?? false)) {
    reviews_api_json([
        'ok' => false,
        'message' => (string)($eligibility['message'] ?? 'You are not eligible to review this product.'),
        'csrf_token' => $_SESSION['csrf_token'],
    ], 403);
}

if ($existingReview) {
    $reviewId = (int)($existingReview['id'] ?? 0);

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

    $stmt->bind_param('isii', $rating, $reviewText, $reviewId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        reviews_api_json([
            'ok' => false,
            'message' => 'Unable to update your review right now.',
            'csrf_token' => $_SESSION['csrf_token'],
        ], 500);
    }

    reviews_api_json([
        'ok' => true,
        'message' => 'Your review has been updated.',
        'logged_in' => true,
        'payload' => reviews_api_payload($con, $productId, $userId),
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

$orderId = (int)($eligibility['eligible_order_id'] ?? 0);

$stmt = $con->prepare(
    'INSERT INTO product_reviews (user_id, product_id, order_id, rating, review_text, is_verified_purchase, is_visible)
     VALUES (?, ?, ?, ?, ?, 1, 1)'
);

if (!$stmt) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

$stmt->bind_param('iiiis', $userId, $productId, $orderId, $rating, $reviewText);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    reviews_api_json([
        'ok' => false,
        'message' => 'Unable to save your review right now.',
        'csrf_token' => $_SESSION['csrf_token'],
    ], 500);
}

reviews_api_json([
    'ok' => true,
    'message' => 'Thanks! Your review was submitted successfully.',
    'logged_in' => true,
    'payload' => reviews_api_payload($con, $productId, $userId),
    'csrf_token' => $_SESSION['csrf_token'],
]);
