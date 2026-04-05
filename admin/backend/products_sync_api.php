<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../backend/products_schema_helpers.php';

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
commerza_products_ensure_schema($con);

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
        'SELECT id, sectionId, name, description, image, video_url, product_code, warranty_info, dispatch_info, price, salePrice, stock, movement, created_at
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
            'productCode' => (string)($row['product_code'] ?? ''),
            'warrantyInfo' => (string)($row['warranty_info'] ?? ''),
            'dispatchInfo' => (string)($row['dispatch_info'] ?? ''),
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

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = 'get-products';
if ($method === 'GET') {
    $action = strtolower(trim((string)($_GET['action'] ?? 'get-products')));
} elseif ($method === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? ($body['action'] ?? 'get-products'))));
}

admin_api_rate_limit_guard(
    $con,
    $admin,
    admin_api_scope('admin_products_sync_api', $action),
    90,
    60,
    120,
    300
);

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
           'INSERT INTO products (id, sectionId, name, description, image, video_url, product_code, warranty_info, dispatch_info, price, salePrice, stock, movement)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""))'
    );

    $insertProductStmt = $con->prepare(
           'INSERT INTO products (sectionId, name, description, image, video_url, product_code, warranty_info, dispatch_info, price, salePrice, stock, movement)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""))'
    );

    if (!$insertSectionStmt || !$insertProductWithIdStmt || !$insertProductStmt) {
        throw new RuntimeException('Unable to prepare sync statements.');
    }

    $seenProductCodes = [];

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
            $productCodeRaw = trim((string)($product['productCode'] ?? ''));
            $warrantyInfo = trim((string)($product['warrantyInfo'] ?? ''));
            $dispatchInfo = trim((string)($product['dispatchInfo'] ?? ''));

            $productCode = commerza_normalize_product_code_value($productCodeRaw, $productId);
            if ($warrantyInfo === '') {
                $warrantyInfo = '12-month seller warranty';
            }
            if ($dispatchInfo === '') {
                $dispatchInfo = $stock > 0 ? 'Dispatch in 24-48 hours' : 'Pre-order availability';
            }

            $dedupeCounter = 2;
            $baseCode = $productCode;
            while (isset($seenProductCodes[$productCode])) {
                $suffix = '-' . ($productId > 0 ? str_pad((string)$productId, 5, '0', STR_PAD_LEFT) : (string)$dedupeCounter);
                $maxBaseLength = max(1, 40 - strlen($suffix));
                $productCode = substr($baseCode, 0, $maxBaseLength) . $suffix;
                $dedupeCounter++;
            }
            $seenProductCodes[$productCode] = true;

            if ($name === '' || $price <= 0) {
                continue;
            }

            if (
                strlen($name) > 255 ||
                strlen($image) > 255 ||
                strlen($video) > 255 ||
                strlen($productCode) > 40 ||
                strlen($warrantyInfo) > 120 ||
                strlen($dispatchInfo) > 120
            ) {
                continue;
            }

            $priceString = (string)$price;

            if ($productId > 0) {
                $insertProductWithIdStmt->bind_param(
                    'issssssssssis',
                    $productId,
                    $sectionId,
                    $name,
                    $description,
                    $image,
                    $video,
                    $productCode,
                    $warrantyInfo,
                    $dispatchInfo,
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
                'ssssssssssis',
                $sectionId,
                $name,
                $description,
                $image,
                $video,
                $productCode,
                $warrantyInfo,
                $dispatchInfo,
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

    $totalSections = is_array($sections) ? count($sections) : 0;
    $syncedPayload = admin_build_products_payload($con);
    $syncedTotalProducts = (int)($syncedPayload['meta']['total'] ?? 0);

    admin_api_log_security_event($con, $admin, 'products.synced', 'info', [
        'sections' => $totalSections,
        'products' => $syncedTotalProducts,
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Products synced successfully.',
        'payload' => $syncedPayload,
    ]);
} catch (Throwable $exception) {
    $con->rollback();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not sync products.',
    ]);
}
