<?php

require_once __DIR__ . '/../core/data.php';
require_once __DIR__ . '/../helpers/products_schema_helpers.php';

commerza_products_ensure_schema($con);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

function commerza_products_api_send(array $payload, int $maxAgeSeconds = 120): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        http_response_code(500);
        echo '{"ok":false,"message":"Unable to encode response."}';
        exit;
    }

    $etag = '"' . sha1($json) . '"';
    $maxAge = max(0, $maxAgeSeconds);

    header('Cache-Control: public, max-age=' . $maxAge . ', stale-while-revalidate=' . max(0, $maxAge * 2));
    header('ETag: ' . $etag);
    header('Vary: Accept-Encoding');

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
        http_response_code(304);
        exit;
    }

    echo $json;
    exit;
}

function commerza_products_rate_limit_guard(
    mysqli $con,
    string $scope,
    string $identifier,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $escalatedBlockSeconds = 600
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
        'guest',
        $identifier,
        $clientIp,
        $retrySeconds
    );

    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'message' => 'Too many product requests. Please retry shortly.',
        'retry_after' => $retrySeconds,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function commerza_normalize_page_name(string $page): string
{
    $page = trim(str_replace('\\', '/', $page));
    if ($page === '') {
        return '';
    }

    $file = basename($page);
    if (substr($file, -5) === '.html') {
        return substr($file, 0, -5) . '.php';
    }

    return $file;
}

function commerza_products_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    if (!is_string($slug)) {
        return 'product';
    }

    $slug = trim($slug, '-');
    $slug = preg_replace('/-+/', '-', $slug);
    if (!is_string($slug) || $slug === '') {
        return 'product';
    }

    return $slug;
}

function commerza_products_has_table(mysqli $con, string $table): bool
{
    $safeTable = $con->real_escape_string($table);
    $result = $con->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_products_table_has_column(mysqli $con, string $table, string $column): bool
{
    $safeTable = $con->real_escape_string($table);
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$action = strtolower(trim((string)($_GET['action'] ?? 'sections')));
if ($action === '') {
    $action = 'sections';
}

$hasProductTrashTable = commerza_products_has_table($con, 'product_trash');
$hasProductsDeletedAt = commerza_products_table_has_column($con, 'products', 'deleted_at');

$productsActiveClause = static function (string $alias = '') use ($hasProductTrashTable, $hasProductsDeletedAt): string {
    $prefix = $alias !== '' ? ($alias . '.') : '';
    $clauses = [];

    if ($hasProductsDeletedAt) {
        $clauses[] = $prefix . 'deleted_at IS NULL';
    }

    if ($hasProductTrashTable) {
        $clauses[] = 'NOT EXISTS (SELECT 1 FROM product_trash pt WHERE pt.product_id = ' . $prefix . 'id)';
    }

    if (empty($clauses)) {
        return '';
    }

    return ' AND ' . implode(' AND ', $clauses);
};

if ($action === 'suggest') {
    commerza_products_rate_limit_guard($con, 'products_suggest', 'suggest_bucket', 90, 60, 120, 600);

    $query = preg_replace('/\s+/', ' ', trim((string)($_GET['q'] ?? '')));
    $query = is_string($query) ? $query : '';
    $limit = (int)($_GET['limit'] ?? 6);
    $limit = max(1, min(10, $limit));

    if (strlen($query) < 2) {
        commerza_products_api_send([
            'ok' => true,
            'suggestions' => [],
        ], 15);
    }

    $likeQuery = '%' . $query . '%';
    $prefixQuery = $query . '%';
    $suggestions = [];

    $sql =
        'SELECT p.id, p.name, p.image, p.price, p.salePrice
         FROM products p
         WHERE (p.name LIKE ? OR p.description LIKE ?)' . $productsActiveClause('p') . '
         ORDER BY
            CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END,
            p.updated_at DESC,
            p.id DESC
         LIMIT ' . $limit;

    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $likeQuery, $likeQuery, $prefixQuery);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $suggestions[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => trim((string)($row['name'] ?? '')),
                'image' => trim((string)($row['image'] ?? '')),
                'price' => isset($row['price']) ? (float)$row['price'] : 0.0,
                'salePrice' => isset($row['salePrice']) && $row['salePrice'] !== null
                    ? (float)$row['salePrice']
                    : (isset($row['price']) ? (float)$row['price'] : 0.0),
            ];
        }

        $stmt->close();
    }

    commerza_products_api_send([
        'ok' => true,
        'suggestions' => $suggestions,
    ], 30);
}

commerza_products_rate_limit_guard($con, 'products_sections', 'sections_payload', 75, 60, 120, 600);

$sections = [];

$sectionResult = $con->query(
    'SELECT sectionId, sectionName, category, subcategory, page
     FROM sections
     ORDER BY id ASC'
);

if ($sectionResult) {
    while ($row = $sectionResult->fetch_assoc()) {
        $sectionId = (string)$row['sectionId'];
        $sections[$sectionId] = [
            'sectionId' => $sectionId,
            'sectionName' => (string)($row['sectionName'] ?? ''),
            'category' => (string)($row['category'] ?? ''),
            'subcategory' => (string)($row['subcategory'] ?? ''),
            'page' => commerza_normalize_page_name((string)($row['page'] ?? '')),
            'products' => [],
        ];
    }
}

$hasReviewsTable = commerza_products_has_table($con, 'product_reviews');
$reviewVisibilityClause = '1 = 1';
if ($hasReviewsTable && commerza_products_table_has_column($con, 'product_reviews', 'is_visible')) {
    $reviewVisibilityClause = 'is_visible = 1';
}

$productSql =
    'SELECT
        p.id,
        p.sectionId,
        p.name,
        p.description,
        p.image,
        p.price,
        p.salePrice,
        p.stock,
        p.movement,
        p.product_code,
        p.warranty_info,
        p.dispatch_info';

if ($hasReviewsTable) {
    $productSql .=
        ',
        COALESCE(rv.review_count, 0) AS rating_count,
        COALESCE(rv.average_rating, 0) AS rating_average
     FROM products p
     LEFT JOIN (
        SELECT product_id, COUNT(*) AS review_count, AVG(rating) AS average_rating
        FROM product_reviews
        WHERE ' . $reviewVisibilityClause . '
        GROUP BY product_id
     ) rv ON rv.product_id = p.id';
} else {
    $productSql .=
        ',
        0 AS rating_count,
        0 AS rating_average
     FROM products p';
}

$productSql .= ' WHERE 1 = 1' . $productsActiveClause('p');
$productSql .= ' ORDER BY p.id ASC';

$productResult = $con->query($productSql);

if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $sectionId = (string)($row['sectionId'] ?? '');

        if ($sectionId === '') {
            continue;
        }

        if (!isset($sections[$sectionId])) {
            $sections[$sectionId] = [
                'sectionId' => $sectionId,
                'sectionName' => $sectionId,
                'category' => '',
                'subcategory' => '',
                'page' => '',
                'products' => [],
            ];
        }

        $sections[$sectionId]['products'][] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'slug' => commerza_products_slugify((string)($row['name'] ?? '')),
            'description' => (string)($row['description'] ?? ''),
            'image' => (string)($row['image'] ?? ''),
            'price' => (float)$row['price'],
            'salePrice' => $row['salePrice'] !== null ? (float)$row['salePrice'] : null,
            'stock' => (int)($row['stock'] ?? 0),
            'movement' => (string)($row['movement'] ?? 'quartz'),
            'productCode' => (string)($row['product_code'] ?? ''),
            'warrantyInfo' => (string)($row['warranty_info'] ?? ''),
            'dispatchInfo' => (string)($row['dispatch_info'] ?? ''),
            'ratingCount' => max(0, (int)($row['rating_count'] ?? 0)),
            'ratingAverage' => round((float)($row['rating_average'] ?? 0), 2),
        ];
    }
}

$response = [
    'ok' => true,
    'sections' => array_values($sections),
];
commerza_products_api_send($response, 120);
