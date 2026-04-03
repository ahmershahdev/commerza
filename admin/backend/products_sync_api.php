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
admin_require_permission_api($admin, 'products.manage');

function admin_normalize_page_value(string $page): string
{
    $page = trim($page);
    if ($page === '') {
        return 'index.php';
    }

    if (str_ends_with($page, '.html')) {
        return substr($page, 0, -5) . '.php';
    }

    return $page;
}

function admin_build_products_payload(mysqli $con): array
{
    $sectionsResult = $con->query(
        'SELECT sectionId, sectionName, category, subcategory, page
         FROM sections
         ORDER BY id ASC'
    );

    $productsResult = $con->query(
        'SELECT id, sectionId, name, description, image, video_url, price, salePrice, stock, movement, created_at
         FROM products
         ORDER BY id ASC'
    );

    if (!$sectionsResult || !$productsResult) {
        throw new RuntimeException('Unable to read products data.');
    }

    $productsBySection = [];

    while ($row = $productsResult->fetch_assoc()) {
        $sectionId = (string)($row['sectionId'] ?? '');
        if ($sectionId === '') {
            continue;
        }

        if (!isset($productsBySection[$sectionId])) {
            $productsBySection[$sectionId] = [];
        }

        $productsBySection[$sectionId][] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'description' => (string)($row['description'] ?? ''),
            'image' => (string)($row['image'] ?? ''),
            'video' => (string)($row['video_url'] ?? ''),
            'price' => (float)$row['price'],
            'salePrice' => $row['salePrice'] !== null ? (float)$row['salePrice'] : null,
            'stock' => (int)($row['stock'] ?? 0),
            'movement' => (string)($row['movement'] ?? 'quartz'),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'sectionId' => $sectionId,
        ];
    }

    $sections = [];
    $total = 0;

    while ($row = $sectionsResult->fetch_assoc()) {
        $sectionId = (string)$row['sectionId'];
        $sectionProducts = $productsBySection[$sectionId] ?? [];
        $total += count($sectionProducts);

        $sections[] = [
            'sectionId' => $sectionId,
            'sectionName' => (string)$row['sectionName'],
            'category' => (string)($row['category'] ?? 'Uncategorized'),
            'subcategory' => (string)($row['subcategory'] ?? 'General'),
            'page' => admin_normalize_page_value((string)($row['page'] ?? 'index.php')),
            'products' => $sectionProducts,
        ];
    }

    return [
        'meta' => [
            'total' => $total,
            'currency' => 'PKR',
            'lastUpdated' => gmdate('Y-m-d'),
        ],
        'sections' => $sections,
    ];
}

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody ?: '{}', true);
if (!is_array($body)) {
    $body = [];
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? ($body['action'] ?? 'get-products'))));

if ($action === 'get-products') {
    try {
        echo json_encode([
            'ok' => true,
            'payload' => admin_build_products_payload($con),
        ]);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Could not load products.',
        ]);
    }
    exit;
}

if ($action !== 'save-products') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported action.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
}

if (!admin_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
    ]);
    exit;
}

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid request body.',
    ]);
    exit;
}

$sections = $body['sections'] ?? null;
if (!is_array($sections)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Sections payload is required.',
    ]);
    exit;
}

$allowedMovements = ['auto', 'manual', 'quartz', 'smart'];

$con->begin_transaction();

try {
    if (!$con->query('DELETE FROM products')) {
        throw new RuntimeException('Unable to clear products.');
    }

    if (!$con->query('DELETE FROM sections')) {
        throw new RuntimeException('Unable to clear sections.');
    }

    $insertSectionStmt = $con->prepare(
        'INSERT INTO sections (sectionId, sectionName, category, subcategory, page)
         VALUES (?, ?, ?, ?, ?)'
    );

    $insertProductWithIdStmt = $con->prepare(
           'INSERT INTO products (id, sectionId, name, description, image, video_url, price, salePrice, stock, movement)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""))'
    );

    $insertProductStmt = $con->prepare(
           'INSERT INTO products (sectionId, name, description, image, video_url, price, salePrice, stock, movement)
            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""))'
    );

    if (!$insertSectionStmt || !$insertProductWithIdStmt || !$insertProductStmt) {
        throw new RuntimeException('Unable to prepare sync statements.');
    }

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $sectionId = trim((string)($section['sectionId'] ?? ''));
        $sectionName = trim((string)($section['sectionName'] ?? ''));
        $category = trim((string)($section['category'] ?? 'Uncategorized'));
        $subcategory = trim((string)($section['subcategory'] ?? 'General'));
        $page = admin_normalize_page_value((string)($section['page'] ?? 'index.php'));

        if ($sectionId === '' || $sectionName === '') {
            continue;
        }

        if (strlen($sectionId) > 64 || strlen($sectionName) > 128 || strlen($page) > 64) {
            continue;
        }

        $insertSectionStmt->bind_param('sssss', $sectionId, $sectionName, $category, $subcategory, $page);
        if (!$insertSectionStmt->execute()) {
            throw new RuntimeException('Unable to save section.');
        }

        $products = $section['products'] ?? [];
        if (!is_array($products)) {
            continue;
        }

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $name = trim((string)($product['name'] ?? ''));
            $description = trim((string)($product['description'] ?? ''));
            $image = trim((string)($product['image'] ?? ''));
            $video = trim((string)($product['video'] ?? ''));
            $price = (float)($product['price'] ?? 0);
            $salePriceRaw = $product['salePrice'] ?? null;
            $salePrice = $salePriceRaw === null || $salePriceRaw === '' ? '' : (string)(float)$salePriceRaw;
            $stock = (int)($product['stock'] ?? 0);
            $movement = strtolower(trim((string)($product['movement'] ?? 'quartz')));
            $movement = in_array($movement, $allowedMovements, true) ? $movement : '';
            $productId = (int)($product['id'] ?? 0);

            if ($name === '' || $price <= 0) {
                continue;
            }

            if (strlen($name) > 255 || strlen($image) > 255 || strlen($video) > 255) {
                continue;
            }

            $priceString = (string)$price;

            if ($productId > 0) {
                $insertProductWithIdStmt->bind_param(
                    'isssssssis',
                    $productId,
                    $sectionId,
                    $name,
                    $description,
                    $image,
                    $video,
                    $priceString,
                    $salePrice,
                    $stock,
                    $movement
                );

                if (!$insertProductWithIdStmt->execute()) {
                    throw new RuntimeException('Unable to save product.');
                }

                continue;
            }

            $insertProductStmt->bind_param(
                'sssssssis',
                $sectionId,
                $name,
                $description,
                $image,
                $video,
                $priceString,
                $salePrice,
                $stock,
                $movement
            );

            if (!$insertProductStmt->execute()) {
                throw new RuntimeException('Unable to save product.');
            }
        }
    }

    $insertSectionStmt->close();
    $insertProductWithIdStmt->close();
    $insertProductStmt->close();

    $con->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Products synced successfully.',
        'payload' => admin_build_products_payload($con),
    ]);
} catch (Throwable $exception) {
    $con->rollback();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not sync products.',
    ]);
}
