<?php

function commerza_wishlist_table_has_index(mysqli $con, string $table, string $indexName): bool
{
    $safeTable = $con->real_escape_string($table);
    $safeIndex = $con->real_escape_string($indexName);

    $result = $con->query("SHOW INDEX FROM {$safeTable} WHERE Key_name = '{$safeIndex}'");
    if (!($result instanceof mysqli_result)) {
        return false;
    }

    $hasIndex = $result->num_rows > 0;
    $result->free();

    return $hasIndex;
}

function commerza_wishlist_ensure_schema(mysqli $con): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $ensured = true;

    $requiredIndexes = [
        'uq_wishlist_product' => 'ALTER TABLE wishlist_items ADD UNIQUE KEY uq_wishlist_product (wishlist_id, product_id)',
        'product_id' => 'ALTER TABLE wishlist_items ADD KEY product_id (product_id)',
        'idx_wishlist_items_wishlist_added_at' => 'ALTER TABLE wishlist_items ADD KEY idx_wishlist_items_wishlist_added_at (wishlist_id, added_at)',
    ];

    foreach ($requiredIndexes as $indexName => $sql) {
        if (commerza_wishlist_table_has_index($con, 'wishlist_items', $indexName)) {
            continue;
        }

        $con->query($sql);
    }
}
