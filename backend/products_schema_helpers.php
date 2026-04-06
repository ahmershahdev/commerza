<?php

declare(strict_types=1);

function commerza_products_has_column(mysqli $con, string $column): bool
{
    $escaped = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM products LIKE '{$escaped}'");
    if (!($result instanceof mysqli_result)) {
        return false;
    }

    $hasColumn = $result->num_rows > 0;
    $result->free();

    return $hasColumn;
}

function commerza_products_has_index(mysqli $con, string $indexName): bool
{
    $escaped = $con->real_escape_string($indexName);
    $result = $con->query("SHOW INDEX FROM products WHERE Key_name = '{$escaped}'");
    if (!($result instanceof mysqli_result)) {
        return false;
    }

    $hasIndex = $result->num_rows > 0;
    $result->free();

    return $hasIndex;
}

function commerza_normalize_product_code_value(string $value, int $fallbackId = 0): string
{
    $normalized = strtoupper(trim($value));
    $normalized = preg_replace('/[^A-Z0-9-]+/', '-', $normalized) ?? '';
    $normalized = preg_replace('/-+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    if ($normalized === '' && $fallbackId > 0) {
        $normalized = 'CMRZ-' . str_pad((string)$fallbackId, 5, '0', STR_PAD_LEFT);
    }

    if ($normalized === '') {
        $normalized = 'CMRZ-' . strtoupper(substr(sha1(uniqid('', true)), 0, 8));
    }

    if (strlen($normalized) > 40) {
        $normalized = substr($normalized, 0, 40);
        $normalized = rtrim($normalized, '-');
    }

    return $normalized;
}

function commerza_products_normalize_existing_codes(mysqli $con): void
{
    $result = $con->query('SELECT id, product_code FROM products WHERE product_code IS NOT NULL AND TRIM(product_code) <> ""');
    if (!($result instanceof mysqli_result)) {
        return;
    }

    $updateStmt = $con->prepare('UPDATE products SET product_code = ? WHERE id = ? LIMIT 1');
    if (!$updateStmt) {
        $result->free();
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $currentCode = (string)($row['product_code'] ?? '');
        $normalizedCode = commerza_normalize_product_code_value($currentCode, $id);

        if ($normalizedCode === $currentCode) {
            continue;
        }

        $updateStmt->bind_param('si', $normalizedCode, $id);
        $updateStmt->execute();
    }

    $updateStmt->close();
    $result->free();
}

function commerza_products_fix_duplicate_codes(mysqli $con): void
{
    $duplicates = $con->query(
        'SELECT product_code
         FROM products
         WHERE product_code IS NOT NULL AND TRIM(product_code) <> ""
         GROUP BY product_code
         HAVING COUNT(*) > 1'
    );

    if (!($duplicates instanceof mysqli_result)) {
        return;
    }

    $loadStmt = $con->prepare('SELECT id FROM products WHERE product_code = ? ORDER BY id ASC');
    $updateStmt = $con->prepare('UPDATE products SET product_code = ? WHERE id = ? LIMIT 1');

    if (!$loadStmt || !$updateStmt) {
        $duplicates->free();
        if ($loadStmt) {
            $loadStmt->close();
        }
        if ($updateStmt) {
            $updateStmt->close();
        }
        return;
    }

    while ($dup = $duplicates->fetch_assoc()) {
        $code = (string)($dup['product_code'] ?? '');
        if ($code === '') {
            continue;
        }

        $loadStmt->bind_param('s', $code);
        $loadStmt->execute();
        $idResult = $loadStmt->get_result();

        if (!($idResult instanceof mysqli_result)) {
            continue;
        }

        $ids = [];
        while ($idRow = $idResult->fetch_assoc()) {
            $ids[] = (int)($idRow['id'] ?? 0);
        }

        $idResult->free();

        if (count($ids) <= 1) {
            continue;
        }

        $baseCode = commerza_normalize_product_code_value($code, $ids[0]);
        foreach ($ids as $offset => $id) {
            if ($offset === 0 || $id <= 0) {
                continue;
            }

            $suffix = '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            $maxBaseLength = max(1, 40 - strlen($suffix));
            $candidate = substr($baseCode, 0, $maxBaseLength) . $suffix;

            $updateStmt->bind_param('si', $candidate, $id);
            $updateStmt->execute();
        }
    }

    $loadStmt->close();
    $updateStmt->close();
    $duplicates->free();
}

function commerza_products_ensure_schema(mysqli $con): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $requiredColumns = [
        'product_code' => 'ALTER TABLE products ADD COLUMN product_code VARCHAR(40) DEFAULT NULL AFTER video_url',
        'warranty_info' => "ALTER TABLE products ADD COLUMN warranty_info VARCHAR(120) NOT NULL DEFAULT '12-month seller warranty' AFTER product_code",
        'dispatch_info' => "ALTER TABLE products ADD COLUMN dispatch_info VARCHAR(120) NOT NULL DEFAULT 'Dispatch in 24-48 hours' AFTER warranty_info",
    ];

    $addedColumns = false;
    foreach ($requiredColumns as $columnName => $sql) {
        if (commerza_products_has_column($con, $columnName)) {
            continue;
        }

        $con->query($sql);
        $addedColumns = true;
    }

    if ($addedColumns) {
        $con->query("UPDATE products SET warranty_info = '12-month seller warranty' WHERE warranty_info IS NULL OR TRIM(warranty_info) = ''");
        $con->query("UPDATE products SET dispatch_info = CASE WHEN COALESCE(stock, 0) > 0 THEN 'Dispatch in 24-48 hours' ELSE 'Pre-order availability' END WHERE dispatch_info IS NULL OR TRIM(dispatch_info) = ''");
    }

    if (commerza_products_has_column($con, 'product_code') && !commerza_products_has_index($con, 'uq_products_product_code')) {
        $con->query("UPDATE products SET product_code = CONCAT('CMRZ-', LPAD(id, 5, '0')) WHERE product_code IS NULL OR TRIM(product_code) = ''");
        commerza_products_normalize_existing_codes($con);
        commerza_products_fix_duplicate_codes($con);
    }

    $requiredIndexes = [
        'sectionId' => 'ALTER TABLE products ADD INDEX sectionId (sectionId)',
        'idx_products_section_updated' => 'ALTER TABLE products ADD INDEX idx_products_section_updated (sectionId, updated_at, id)',
        'idx_products_movement' => 'ALTER TABLE products ADD INDEX idx_products_movement (movement)',
        'idx_products_price' => 'ALTER TABLE products ADD INDEX idx_products_price (price)',
        'idx_products_sale_price' => 'ALTER TABLE products ADD INDEX idx_products_sale_price (salePrice)',
        'idx_products_stock' => 'ALTER TABLE products ADD INDEX idx_products_stock (stock)',
        'uq_products_product_code' => 'ALTER TABLE products ADD UNIQUE INDEX uq_products_product_code (product_code)',
    ];

    foreach ($requiredIndexes as $indexName => $sql) {
        if (commerza_products_has_index($con, $indexName)) {
            continue;
        }

        $con->query($sql);
    }

    $checked = true;
}
