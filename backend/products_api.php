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

echo json_encode([
    'ok' => true,
    'sections' => array_values($sections),
], JSON_UNESCAPED_SLASHES);
