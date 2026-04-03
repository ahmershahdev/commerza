<?php

require_once __DIR__ . '/cart_helpers.php';

function commerza_get_wishlist_count(mysqli $con): int
{
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        return 0;
    }

    $userId = (int)$_SESSION['user_id'];

    $stmt = $con->prepare(
        'SELECT COALESCE(COUNT(wi.id), 0) AS cnt
         FROM wishlist w
         LEFT JOIN wishlist_items wi ON wi.wishlist_id = w.id
         WHERE w.user_id = ?'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['cnt'] : 0;
}

function commerza_get_nav_counts(mysqli $con): array
{
    return [
        'cart_count' => commerza_get_cart_count($con),
        'wishlist_count' => commerza_get_wishlist_count($con),
    ];
}
