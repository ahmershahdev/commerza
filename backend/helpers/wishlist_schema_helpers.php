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

if (!function_exists('get_or_create_wishlist_id')) {
    function get_or_create_wishlist_id(mysqli $con, int $userId): ?int
    {
        $selectStmt = $con->prepare('SELECT id FROM wishlist WHERE user_id = ? LIMIT 1');
        if (!$selectStmt) {
            return null;
        }
    
        $selectStmt->bind_param('i', $userId);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
    
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $selectStmt->close();
            return (int)$row['id'];
        }
    
        $selectStmt->close();
    
        $insertStmt = $con->prepare(
            'INSERT INTO wishlist (user_id)
             VALUES (?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        if (!$insertStmt) {
            return null;
        }
    
        $insertStmt->bind_param('i', $userId);
        $ok = $insertStmt->execute();
        $newId = $ok ? (int)$con->insert_id : null;
        $insertStmt->close();
    
        return $newId;
    }
}
