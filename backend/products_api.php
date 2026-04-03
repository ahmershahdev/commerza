<?php

require_once __DIR__ . '/data.php';

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

$action = strtolower(trim((string)($_GET['action'] ?? 'sections')));
if ($action === '') {
    $action = 'sections';
}

if ($action === 'suggest') {
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
        'SELECT id, name, image, price, salePrice
         FROM products
         WHERE name LIKE ? OR description LIKE ?
         ORDER BY
            CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
            updated_at DESC,
            id DESC
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

$productResult = $con->query(
    'SELECT id, sectionId, name, description, image, price, salePrice, stock, movement
     FROM products
     ORDER BY id ASC'
);

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
            'description' => (string)($row['description'] ?? ''),
            'image' => (string)($row['image'] ?? ''),
            'price' => (float)$row['price'],
            'salePrice' => $row['salePrice'] !== null ? (float)$row['salePrice'] : null,
            'stock' => (int)($row['stock'] ?? 0),
            'movement' => (string)($row['movement'] ?? 'quartz'),
        ];
    }
}

$response = [
    'ok' => true,
    'sections' => array_values($sections),
];
commerza_products_api_send($response, 120);
