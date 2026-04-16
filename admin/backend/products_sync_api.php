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

function admin_products_sync_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_products_sync_table_exists(mysqli $con, string $table): bool
{
    $escapedTable = $con->real_escape_string($table);
    $result = $con->query("SHOW TABLES LIKE '{$escapedTable}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function admin_products_sync_is_generated_media_path(string $path): bool
{
    $normalized = trim(str_replace('\\', '/', $path));
    if ($normalized === '' || str_contains($normalized, '..')) {
        return false;
    }

    $allowedPrefixes = [
        'frontend/assets/images/products/uploads/',
        'frontend/assets/videos/products/uploads/',
        'frontend/assets/images/slider/',
        'frontend/assets/videos/slider/',
        'frontend/assets/images/logo/',
        'frontend/assets/images/favicon/',
        'frontend/assets/images/social/',
    ];

    $hasAllowedPrefix = false;
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            $hasAllowedPrefix = true;
            break;
        }
    }

    if (!$hasAllowedPrefix) {
        return false;
    }

    $basename = basename($normalized);
    return preg_match('/-[a-f0-9]{16}\.[a-z0-9]+$/i', $basename) === 1;
}

function admin_products_sync_delete_local_file(string $relativePath): void
{
    $normalized = trim(str_replace('\\', '/', $relativePath));
    if (!admin_products_sync_is_generated_media_path($normalized)) {
        return;
    }

    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function admin_products_sync_path_referenced(mysqli $con, string $path): bool
{
    $normalized = trim(str_replace('\\', '/', $path));
    if ($normalized === '') {
        return false;
    }

    $productsStmt = $con->prepare(
        'SELECT id
         FROM products
         WHERE image = ? OR video_url = ?
         LIMIT 1'
    );

    if ($productsStmt) {
        $productsStmt->bind_param('ss', $normalized, $normalized);
        $productsStmt->execute();
        $productsStmt->store_result();
        $inProducts = $productsStmt->num_rows > 0;
        $productsStmt->close();

        if ($inProducts) {
            return true;
        }
    }

    if (admin_products_sync_table_exists($con, 'slider')) {
        $sliderStmt = $con->prepare(
            'SELECT id
             FROM slider
             WHERE image_url = ? OR video_url = ?
             LIMIT 1'
        );

        if ($sliderStmt) {
            $sliderStmt->bind_param('ss', $normalized, $normalized);
            $sliderStmt->execute();
            $sliderStmt->store_result();
            $inSlider = $sliderStmt->num_rows > 0;
            $sliderStmt->close();

            if ($inSlider) {
                return true;
            }
        }
    }

    if (admin_products_sync_table_exists($con, 'social_links')) {
        $socialStmt = $con->prepare(
            'SELECT id
             FROM social_links
             WHERE icon = ?
             LIMIT 1'
        );

        if ($socialStmt) {
            $socialStmt->bind_param('s', $normalized);
            $socialStmt->execute();
            $socialStmt->store_result();
            $inSocial = $socialStmt->num_rows > 0;
            $socialStmt->close();

            if ($inSocial) {
                return true;
            }
        }
    }

    if (admin_products_sync_table_exists($con, 'site_settings')) {
        $settingsStmt = $con->prepare(
            'SELECT id
             FROM site_settings
             WHERE setting_val = ?
             LIMIT 1'
        );

        if ($settingsStmt) {
            $settingsStmt->bind_param('s', $normalized);
            $settingsStmt->execute();
            $settingsStmt->store_result();
            $inSettings = $settingsStmt->num_rows > 0;
            $settingsStmt->close();

            if ($inSettings) {
                return true;
            }
        }
    }

    if (admin_products_sync_table_exists($con, 'product_trash')) {
        $trashStmt = $con->prepare(
            'SELECT id
             FROM product_trash
             WHERE image = ? OR video_url = ?
             LIMIT 1'
        );

        if ($trashStmt) {
            $trashStmt->bind_param('ss', $normalized, $normalized);
            $trashStmt->execute();
            $trashStmt->store_result();
            $inTrash = $trashStmt->num_rows > 0;
            $trashStmt->close();

            if ($inTrash) {
                return true;
            }
        }
    }

    return false;
}

function admin_products_sync_cleanup_paths(mysqli $con, array $paths): void
{
    $seen = [];
    foreach ($paths as $path) {
        $normalized = trim(str_replace('\\', '/', (string)$path));
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;

        if (!admin_products_sync_is_generated_media_path($normalized)) {
            continue;
        }

        if (admin_products_sync_path_referenced($con, $normalized)) {
            continue;
        }

        admin_products_sync_delete_local_file($normalized);
    }
}

function admin_products_sync_collect_existing_sections(mysqli $con): array
{
    $sections = [];
    $result = $con->query(
        'SELECT sectionId, sectionName, category, subcategory, page
         FROM sections'
    );

    if (!($result instanceof mysqli_result)) {
        return $sections;
    }

    while ($row = $result->fetch_assoc()) {
        $sectionId = (string)($row['sectionId'] ?? '');
        if ($sectionId === '') {
            continue;
        }

        $sections[$sectionId] = [
            'sectionId' => $sectionId,
            'sectionName' => (string)($row['sectionName'] ?? ''),
            'category' => (string)($row['category'] ?? 'Uncategorized'),
            'subcategory' => (string)($row['subcategory'] ?? 'General'),
            'page' => admin_normalize_page_value((string)($row['page'] ?? 'index.php')),
        ];
    }

    $result->free();
    return $sections;
}

function admin_products_sync_collect_existing_products(mysqli $con): array
{
    $products = [];
    $result = $con->query(
        'SELECT
            id,
            sectionId,
            name,
            slug,
            description,
            image,
            video_url,
            product_code,
            warranty_info,
            dispatch_info,
            returns_info,
            price,
            salePrice,
            stock,
            movement,
            created_at,
            updated_at
         FROM products'
    );

    if (!($result instanceof mysqli_result)) {
        return $products;
    }

    while ($row = $result->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $products[$id] = $row;
    }

    $result->free();
    return $products;
}

function admin_products_sync_collect_incoming_products(array $sections): array
{
    $incoming = [];

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $products = $section['products'] ?? [];
        if (!is_array($products)) {
            continue;
        }

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $productId = (int)($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $incoming[$productId] = [
                'image' => trim((string)($product['image'] ?? '')),
                'video_url' => trim((string)($product['video'] ?? '')),
            ];
        }
    }

    return $incoming;
}

function admin_products_sync_archive_deleted_products(
    mysqli $con,
    array $products,
    array $sectionsById,
    int $adminId
): int {
    if (empty($products)) {
        return 0;
    }

    $stmt = $con->prepare(
        'INSERT INTO product_trash (
            product_id,
            section_id,
            section_name,
            section_page,
            section_category,
            section_subcategory,
            name,
            slug,
            description,
            image,
            video_url,
            product_code,
            warranty_info,
            dispatch_info,
            returns_info,
            price,
            sale_price,
            stock,
            movement,
            original_created_at,
            original_updated_at,
            deleted_at,
            purge_after,
            deleted_by_admin_id,
            delete_reason
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""), ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?
        )
        ON DUPLICATE KEY UPDATE
            section_id = VALUES(section_id),
            section_name = VALUES(section_name),
            section_page = VALUES(section_page),
            section_category = VALUES(section_category),
            section_subcategory = VALUES(section_subcategory),
            name = VALUES(name),
            slug = VALUES(slug),
            description = VALUES(description),
            image = VALUES(image),
            video_url = VALUES(video_url),
            product_code = VALUES(product_code),
            warranty_info = VALUES(warranty_info),
            dispatch_info = VALUES(dispatch_info),
            returns_info = VALUES(returns_info),
            price = VALUES(price),
            sale_price = VALUES(sale_price),
            stock = VALUES(stock),
            movement = VALUES(movement),
            original_created_at = VALUES(original_created_at),
            original_updated_at = VALUES(original_updated_at),
            deleted_at = NOW(),
            purge_after = DATE_ADD(NOW(), INTERVAL 7 DAY),
            deleted_by_admin_id = VALUES(deleted_by_admin_id),
            delete_reason = VALUES(delete_reason)'
    );

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare product trash archive statement.');
    }

    $archived = 0;
    foreach ($products as $product) {
        $productId = (int)($product['id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }

        $sectionId = trim((string)($product['sectionId'] ?? ''));
        $sectionMeta = $sectionsById[$sectionId] ?? [];
        $sectionName = trim((string)($sectionMeta['sectionName'] ?? 'Archived Section'));
        $sectionPage = admin_normalize_page_value((string)($sectionMeta['page'] ?? 'index.php'));
        $sectionCategory = trim((string)($sectionMeta['category'] ?? 'Uncategorized'));
        $sectionSubcategory = trim((string)($sectionMeta['subcategory'] ?? 'General'));

        if ($sectionName === '') {
            $sectionName = 'Archived Section';
        }

        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $slug = trim((string)($product['slug'] ?? ''));
        $description = (string)($product['description'] ?? '');
        $image = trim((string)($product['image'] ?? ''));
        $videoUrl = trim((string)($product['video_url'] ?? ''));
        $productCode = trim((string)($product['product_code'] ?? ''));
        $warrantyInfo = trim((string)($product['warranty_info'] ?? '12-month seller warranty'));
        $dispatchInfo = trim((string)($product['dispatch_info'] ?? 'Dispatch in 24-48 hours'));
        $returnsInfo = trim((string)($product['returns_info'] ?? '7-day return policy (unused items)'));
        $price = (string)(float)($product['price'] ?? 0);
        $salePriceRaw = $product['salePrice'] ?? null;
        $salePrice = $salePriceRaw === null ? '' : (string)(float)$salePriceRaw;
        $stock = (int)($product['stock'] ?? 0);
        $movement = strtolower(trim((string)($product['movement'] ?? '')));
        $createdAt = trim((string)($product['created_at'] ?? ''));
        $updatedAt = trim((string)($product['updated_at'] ?? ''));
        $deleteReason = 'Removed from catalog sync';

        $stmt->bind_param(
            'issssssssssssssssisssis',
            $productId,
            $sectionId,
            $sectionName,
            $sectionPage,
            $sectionCategory,
            $sectionSubcategory,
            $name,
            $slug,
            $description,
            $image,
            $videoUrl,
            $productCode,
            $warrantyInfo,
            $dispatchInfo,
            $returnsInfo,
            $price,
            $salePrice,
            $stock,
            $movement,
            $createdAt,
            $updatedAt,
            $adminId,
            $deleteReason
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to archive deleted product into trash.');
        }

        $archived++;
    }

    $stmt->close();
    return $archived;
}

function admin_products_sync_fetch_trash(mysqli $con): array
{
    if (!admin_products_sync_table_exists($con, 'product_trash')) {
        return [];
    }

    $rows = [];
    $result = $con->query(
        'SELECT
            id,
            product_id,
            section_id,
            section_name,
            name,
            image,
            video_url,
            product_code,
            deleted_at,
            purge_after,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), purge_after)) AS expires_in_seconds
         FROM product_trash
         ORDER BY deleted_at DESC, id DESC
         LIMIT 500'
    );

    if (!($result instanceof mysqli_result)) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'productId' => (int)($row['product_id'] ?? 0),
            'sectionId' => (string)($row['section_id'] ?? ''),
            'sectionName' => (string)($row['section_name'] ?? 'Section'),
            'name' => (string)($row['name'] ?? 'Product'),
            'image' => (string)($row['image'] ?? ''),
            'video' => (string)($row['video_url'] ?? ''),
            'productCode' => (string)($row['product_code'] ?? ''),
            'deletedAt' => (string)($row['deleted_at'] ?? ''),
            'purgeAfter' => (string)($row['purge_after'] ?? ''),
            'expiresInSeconds' => max(0, (int)($row['expires_in_seconds'] ?? 0)),
        ];
    }

    $result->free();
    return $rows;
}

function admin_products_sync_purge_expired_trash(mysqli $con): int
{
    if (!admin_products_sync_table_exists($con, 'product_trash')) {
        return 0;
    }

    $paths = [];
    $result = $con->query(
        'SELECT id, image, video_url
         FROM product_trash
         WHERE purge_after <= NOW()
         LIMIT 500'
    );

    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        return 0;
    }

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)($row['id'] ?? 0);
        $paths[] = (string)($row['image'] ?? '');
        $paths[] = (string)($row['video_url'] ?? '');
    }
    $result->free();

    $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    if (empty($ids)) {
        return 0;
    }

    $idList = implode(',', array_map('intval', $ids));
    $deleted = 0;

    if ($con->query("DELETE FROM product_trash WHERE id IN ({$idList})")) {
        $deleted = (int)$con->affected_rows;
    }

    if ($deleted > 0) {
        admin_products_sync_cleanup_paths($con, $paths);
    }

    return $deleted;
}

function admin_products_sync_require_csrf(): void
{
    $csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($csrfToken === '') {
        $csrfToken = (string)($_POST['csrf_token'] ?? '');
    }

    if (!admin_validate_csrf_token($csrfToken)) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Invalid CSRF token.',
        ], 403);
    }
}

function admin_products_sync_unique_product_code(mysqli $con, string $candidate, int $productId): string
{
    $base = commerza_normalize_product_code_value($candidate, $productId);
    $resolved = $base;
    $counter = 2;

    $existsStmt = $con->prepare(
        'SELECT id
         FROM products
         WHERE product_code = ?
         LIMIT 1'
    );

    if (!$existsStmt) {
        return $resolved;
    }

    while (true) {
        $existsStmt->bind_param('s', $resolved);
        $existsStmt->execute();
        $existsStmt->store_result();
        $exists = $existsStmt->num_rows > 0;

        if (!$exists) {
            break;
        }

        $suffix = '-' . ($productId > 0 ? str_pad((string)$productId, 5, '0', STR_PAD_LEFT) : (string)$counter);
        $maxBaseLength = max(1, 40 - strlen($suffix));
        $resolved = substr($base, 0, $maxBaseLength) . $suffix;
        $counter++;
    }

    $existsStmt->close();
    return $resolved;
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

try {
    admin_products_sync_purge_expired_trash($con);
} catch (Throwable $exception) {
    // Trash purge should never block product operations.
}

if ($action === 'get-products') {
    try {
        admin_products_sync_json([
            'ok' => true,
            'payload' => admin_build_products_payload($con),
        ]);
    } catch (Throwable $exception) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Could not load products.',
        ], 500);
    }
}

if ($action === 'get-trash') {
    if ($method !== 'GET') {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    admin_products_sync_json([
        'ok' => true,
        'payload' => [
            'items' => admin_products_sync_fetch_trash($con),
        ],
    ]);
}

if ($action === 'restore-trash-product') {
    if ($method !== 'POST') {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    admin_products_sync_require_csrf();

    $trashId = (int)($body['id'] ?? ($_POST['id'] ?? 0));
    if ($trashId <= 0) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Invalid trash id.',
        ], 422);
    }

    if (!admin_products_sync_table_exists($con, 'product_trash')) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Product trash is empty.',
        ], 404);
    }

    if (!$con->begin_transaction()) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Unable to restore trashed product right now.',
        ], 500);
    }

    try {
        $trashStmt = $con->prepare(
            'SELECT *
             FROM product_trash
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        if (!$trashStmt) {
            throw new RuntimeException('Unable to load trash row.');
        }

        $trashStmt->bind_param('i', $trashId);
        $trashStmt->execute();
        $trashResult = $trashStmt->get_result();
        $trashRow = $trashResult ? $trashResult->fetch_assoc() : null;
        $trashStmt->close();

        if (!is_array($trashRow)) {
            throw new UnexpectedValueException('Trash item not found.');
        }

        $sectionId = trim((string)($trashRow['section_id'] ?? ''));
        $sectionName = trim((string)($trashRow['section_name'] ?? 'Archived Section'));
        $sectionPage = admin_normalize_page_value((string)($trashRow['section_page'] ?? 'index.php'));
        $sectionCategory = trim((string)($trashRow['section_category'] ?? 'Uncategorized'));
        $sectionSubcategory = trim((string)($trashRow['section_subcategory'] ?? 'General'));

        if ($sectionId === '') {
            $sectionId = 'restored-' . strtolower(bin2hex(random_bytes(3)));
        }

        if ($sectionName === '') {
            $sectionName = 'Restored Section';
        }

        $sectionCheckStmt = $con->prepare('SELECT sectionId FROM sections WHERE sectionId = ? LIMIT 1');
        if (!$sectionCheckStmt) {
            throw new RuntimeException('Unable to verify section while restoring product.');
        }

        $sectionCheckStmt->bind_param('s', $sectionId);
        $sectionCheckStmt->execute();
        $sectionCheckStmt->store_result();
        $sectionExists = $sectionCheckStmt->num_rows > 0;
        $sectionCheckStmt->close();

        if (!$sectionExists) {
            $insertSectionStmt = $con->prepare(
                'INSERT INTO sections (sectionId, sectionName, category, subcategory, page)
                 VALUES (?, ?, ?, ?, ?)'
            );

            if (!$insertSectionStmt) {
                throw new RuntimeException('Unable to restore section for trashed product.');
            }

            $insertSectionStmt->bind_param('sssss', $sectionId, $sectionName, $sectionCategory, $sectionSubcategory, $sectionPage);
            if (!$insertSectionStmt->execute()) {
                $insertSectionStmt->close();
                throw new RuntimeException('Unable to restore section for trashed product.');
            }

            $insertSectionStmt->close();
        }

        $desiredProductId = (int)($trashRow['product_id'] ?? 0);
        if ($desiredProductId <= 0) {
            $maxProductIdResult = $con->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM products');
            $maxProductId = 0;
            if ($maxProductIdResult instanceof mysqli_result) {
                $maxRow = $maxProductIdResult->fetch_assoc();
                $maxProductId = (int)($maxRow['max_id'] ?? 0);
            }

            $desiredProductId = $maxProductId + 1;
        }

        $productIdCheckStmt = $con->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
        if (!$productIdCheckStmt) {
            throw new RuntimeException('Unable to verify product id while restoring.');
        }

        $productIdCheckStmt->bind_param('i', $desiredProductId);
        $productIdCheckStmt->execute();
        $productIdCheckStmt->store_result();
        $idExists = $productIdCheckStmt->num_rows > 0;
        $productIdCheckStmt->close();

        if ($idExists) {
            $maxProductIdResult = $con->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM products');
            $maxProductId = 0;
            if ($maxProductIdResult instanceof mysqli_result) {
                $maxRow = $maxProductIdResult->fetch_assoc();
                $maxProductId = (int)($maxRow['max_id'] ?? 0);
            }
            $desiredProductId = $maxProductId + 1;
        }

        $name = trim((string)($trashRow['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Trashed product is missing required fields.');
        }

        $slug = trim((string)($trashRow['slug'] ?? ''));
        $description = (string)($trashRow['description'] ?? '');
        $image = trim((string)($trashRow['image'] ?? ''));
        $videoUrl = trim((string)($trashRow['video_url'] ?? ''));
        $productCode = admin_products_sync_unique_product_code(
            $con,
            (string)($trashRow['product_code'] ?? ''),
            $desiredProductId
        );
        $warrantyInfo = trim((string)($trashRow['warranty_info'] ?? '12-month seller warranty'));
        $dispatchInfo = trim((string)($trashRow['dispatch_info'] ?? 'Dispatch in 24-48 hours'));
        $returnsInfo = trim((string)($trashRow['returns_info'] ?? '7-day return policy (unused items)'));
        $price = (string)(float)($trashRow['price'] ?? 0);
        $salePriceRaw = $trashRow['sale_price'] ?? null;
        $salePrice = $salePriceRaw === null ? '' : (string)(float)$salePriceRaw;
        $stock = (int)($trashRow['stock'] ?? 0);
        $movement = strtolower(trim((string)($trashRow['movement'] ?? '')));

        $insertProductStmt = $con->prepare(
            'INSERT INTO products (
                id,
                sectionId,
                name,
                slug,
                description,
                image,
                video_url,
                product_code,
                warranty_info,
                dispatch_info,
                returns_info,
                price,
                salePrice,
                stock,
                movement,
                deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""), NULL)'
        );

        if (!$insertProductStmt) {
            throw new RuntimeException('Unable to restore product.');
        }

        $insertProductStmt->bind_param(
            'issssssssssssis',
            $desiredProductId,
            $sectionId,
            $name,
            $slug,
            $description,
            $image,
            $videoUrl,
            $productCode,
            $warrantyInfo,
            $dispatchInfo,
            $returnsInfo,
            $price,
            $salePrice,
            $stock,
            $movement
        );

        if (!$insertProductStmt->execute()) {
            $insertProductStmt->close();
            throw new RuntimeException('Unable to restore product.');
        }

        $insertProductStmt->close();

        $deleteTrashStmt = $con->prepare('DELETE FROM product_trash WHERE id = ? LIMIT 1');
        if (!$deleteTrashStmt) {
            throw new RuntimeException('Unable to clear restored trash item.');
        }

        $deleteTrashStmt->bind_param('i', $trashId);
        if (!$deleteTrashStmt->execute()) {
            $deleteTrashStmt->close();
            throw new RuntimeException('Unable to clear restored trash item.');
        }
        $deleteTrashStmt->close();

        $con->commit();

        admin_api_log_security_event($con, $admin, 'products.trash_restored', 'info', [
            'trash_id' => $trashId,
            'product_id' => $desiredProductId,
            'section_id' => $sectionId,
        ]);

        admin_products_sync_json([
            'ok' => true,
            'message' => 'Product restored from trash.',
            'payload' => [
                'products' => admin_build_products_payload($con),
                'trash' => [
                    'items' => admin_products_sync_fetch_trash($con),
                ],
            ],
        ]);
    } catch (UnexpectedValueException $exception) {
        $con->rollback();
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Trash item not found.',
        ], 404);
    } catch (Throwable $exception) {
        $con->rollback();
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Unable to restore trashed product right now.',
        ], 500);
    }
}

if ($action === 'delete-trash-item') {
    if ($method !== 'POST') {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    admin_products_sync_require_csrf();

    if (!admin_products_sync_table_exists($con, 'product_trash')) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Product trash is empty.',
        ], 404);
    }

    $trashId = (int)($body['id'] ?? ($_POST['id'] ?? 0));
    if ($trashId <= 0) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Invalid trash id.',
        ], 422);
    }

    if (!$con->begin_transaction()) {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Unable to delete trash item right now.',
        ], 500);
    }

    try {
        $selectStmt = $con->prepare(
            'SELECT image, video_url
             FROM product_trash
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        if (!$selectStmt) {
            throw new RuntimeException('Unable to load trash item.');
        }

        $selectStmt->bind_param('i', $trashId);
        $selectStmt->execute();
        $selectResult = $selectStmt->get_result();
        $trashRow = $selectResult ? $selectResult->fetch_assoc() : null;
        $selectStmt->close();

        if (!is_array($trashRow)) {
            throw new UnexpectedValueException('Trash item not found.');
        }

        $deleteStmt = $con->prepare('DELETE FROM product_trash WHERE id = ? LIMIT 1');
        if (!$deleteStmt) {
            throw new RuntimeException('Unable to delete trash item.');
        }

        $deleteStmt->bind_param('i', $trashId);
        if (!$deleteStmt->execute()) {
            $deleteStmt->close();
            throw new RuntimeException('Unable to delete trash item.');
        }
        $deleteStmt->close();

        $con->commit();

        admin_products_sync_cleanup_paths($con, [
            (string)($trashRow['image'] ?? ''),
            (string)($trashRow['video_url'] ?? ''),
        ]);

        admin_api_log_security_event($con, $admin, 'products.trash_deleted', 'warning', [
            'trash_id' => $trashId,
        ]);

        admin_products_sync_json([
            'ok' => true,
            'message' => 'Trash item permanently deleted.',
            'payload' => [
                'trash' => [
                    'items' => admin_products_sync_fetch_trash($con),
                ],
            ],
        ]);
    } catch (UnexpectedValueException $exception) {
        $con->rollback();
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Trash item not found.',
        ], 404);
    } catch (Throwable $exception) {
        $con->rollback();
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Unable to delete trash item right now.',
        ], 500);
    }
}

if ($action === 'empty-trash') {
    if ($method !== 'POST') {
        admin_products_sync_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    admin_products_sync_require_csrf();

    if (!admin_products_sync_table_exists($con, 'product_trash')) {
        admin_products_sync_json([
            'ok' => true,
            'message' => 'Trash is already empty.',
            'payload' => [
                'deleted' => 0,
                'trash' => [
                    'items' => [],
                ],
            ],
        ]);
    }

    $mode = strtolower(trim((string)($body['mode'] ?? ($_POST['mode'] ?? 'all'))));
    if (!in_array($mode, ['all', 'expired'], true)) {
        $mode = 'all';
    }

    $whereSql = $mode === 'expired' ? 'WHERE purge_after <= NOW()' : '';
    $paths = [];

    $selectResult = $con->query(
        'SELECT id, image, video_url
         FROM product_trash ' . $whereSql
    );

    if ($selectResult instanceof mysqli_result) {
        while ($row = $selectResult->fetch_assoc()) {
            $paths[] = (string)($row['image'] ?? '');
            $paths[] = (string)($row['video_url'] ?? '');
        }
        $selectResult->free();
    }

    $deleted = 0;
    if ($con->query('DELETE FROM product_trash ' . $whereSql)) {
        $deleted = (int)$con->affected_rows;
    }

    if ($deleted > 0) {
        admin_products_sync_cleanup_paths($con, $paths);
    }

    admin_api_log_security_event($con, $admin, 'products.trash_emptied', 'warning', [
        'mode' => $mode,
        'deleted_items' => $deleted,
    ]);

    admin_products_sync_json([
        'ok' => true,
        'message' => $deleted > 0
            ? 'Trash cleaned successfully.'
            : 'Trash is already empty.',
        'payload' => [
            'deleted' => $deleted,
            'trash' => [
                'items' => admin_products_sync_fetch_trash($con),
            ],
        ],
    ]);
}

if ($action !== 'save-products') {
    admin_products_sync_json([
        'ok' => false,
        'message' => 'Unsupported action.',
    ], 400);
}

if ($method !== 'POST') {
    admin_products_sync_json([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

admin_products_sync_require_csrf();

if (!is_array($body)) {
    admin_products_sync_json([
        'ok' => false,
        'message' => 'Invalid request body.',
    ], 400);
}

$sections = $body['sections'] ?? null;
if (!is_array($sections)) {
    admin_products_sync_json([
        'ok' => false,
        'message' => 'Sections payload is required.',
    ], 422);
}

$allowedMovements = ['auto', 'manual', 'quartz', 'smart'];

$existingSections = admin_products_sync_collect_existing_sections($con);
$existingProducts = admin_products_sync_collect_existing_products($con);
$incomingProducts = admin_products_sync_collect_incoming_products($sections);

$deletedProducts = [];
$staleMediaPaths = [];

foreach ($existingProducts as $existingId => $existingProduct) {
    if (!isset($incomingProducts[$existingId])) {
        $deletedProducts[] = $existingProduct;
        $staleMediaPaths[] = (string)($existingProduct['image'] ?? '');
        $staleMediaPaths[] = (string)($existingProduct['video_url'] ?? '');
        continue;
    }

    $incoming = $incomingProducts[$existingId];
    $oldImage = trim((string)($existingProduct['image'] ?? ''));
    $newImage = trim((string)($incoming['image'] ?? ''));
    $oldVideo = trim((string)($existingProduct['video_url'] ?? ''));
    $newVideo = trim((string)($incoming['video_url'] ?? ''));

    if ($oldImage !== '' && $oldImage !== $newImage) {
        $staleMediaPaths[] = $oldImage;
    }

    if ($oldVideo !== '' && $oldVideo !== $newVideo) {
        $staleMediaPaths[] = $oldVideo;
    }
}

if (!$con->begin_transaction()) {
    admin_products_sync_json([
        'ok' => false,
        'message' => 'Could not sync products.',
    ], 500);
}

try {
    admin_products_sync_archive_deleted_products(
        $con,
        $deletedProducts,
        $existingSections,
        (int)($admin['id'] ?? 0)
    );

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

    admin_products_sync_cleanup_paths($con, $staleMediaPaths);

    $totalSections = is_array($sections) ? count($sections) : 0;
    $syncedPayload = admin_build_products_payload($con);
    $syncedTotalProducts = (int)($syncedPayload['meta']['total'] ?? 0);
    $trashItems = admin_products_sync_fetch_trash($con);

    admin_api_log_security_event($con, $admin, 'products.synced', 'info', [
        'sections' => $totalSections,
        'products' => $syncedTotalProducts,
        'archived_products' => count($deletedProducts),
    ]);

    admin_products_sync_json([
        'ok' => true,
        'message' => 'Products synced successfully.',
        'payload' => [
            ...$syncedPayload,
            'trash' => [
                'items' => $trashItems,
            ],
        ],
    ]);
} catch (Throwable $exception) {
    $con->rollback();

    admin_products_sync_json([
        'ok' => false,
        'message' => 'Could not sync products.',
    ], 500);
}
