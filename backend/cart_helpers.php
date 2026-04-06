<?php

function commerza_is_logged_in_user(): bool
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function commerza_current_session_id(): string
{
    $sessionId = session_id();
    if (!is_string($sessionId) || $sessionId === '') {
        return '';
    }

    return $sessionId;
}

function commerza_get_cart_id(mysqli $con, bool $createIfMissing = false): ?int
{
    $userId = commerza_is_logged_in_user() ? (int)$_SESSION['user_id'] : null;
    $sessionId = commerza_current_session_id();

    if ($userId !== null) {
        $stmt = $con->prepare('SELECT id FROM cart WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return (int)$row['id'];
            }
        }

        if ($sessionId !== '') {
            $guestStmt = $con->prepare('SELECT id FROM cart WHERE user_id IS NULL AND session_id = ? ORDER BY id DESC LIMIT 1');
            if ($guestStmt) {
                $guestStmt->bind_param('s', $sessionId);
                $guestStmt->execute();
                $guestResult = $guestStmt->get_result();
                $guestRow = $guestResult ? $guestResult->fetch_assoc() : null;
                $guestStmt->close();

                if ($guestRow) {
                    $cartId = (int)$guestRow['id'];
                    $attachStmt = $con->prepare('UPDATE cart SET user_id = ?, session_id = NULL WHERE id = ? LIMIT 1');
                    if ($attachStmt) {
                        $attachStmt->bind_param('ii', $userId, $cartId);
                        $attachStmt->execute();
                        $attachStmt->close();
                    }
                    return $cartId;
                }
            }
        }

        if (!$createIfMissing) {
            return null;
        }

        $insertStmt = $con->prepare('INSERT INTO cart (session_id, user_id) VALUES (?, ?)');
        if (!$insertStmt) {
            return null;
        }

        $insertSessionId = $sessionId !== '' ? $sessionId : null;
        $insertStmt->bind_param('si', $insertSessionId, $userId);
        $ok = $insertStmt->execute();
        $newId = $ok ? (int)$con->insert_id : null;
        $insertStmt->close();

        return $newId;
    }

    if ($sessionId === '') {
        return null;
    }

    $stmt = $con->prepare('SELECT id FROM cart WHERE user_id IS NULL AND session_id = ? ORDER BY id DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            return (int)$row['id'];
        }
    }

    if (!$createIfMissing) {
        return null;
    }

    $insertStmt = $con->prepare('INSERT INTO cart (session_id, user_id) VALUES (?, NULL)');
    if (!$insertStmt) {
        return null;
    }

    $insertStmt->bind_param('s', $sessionId);
    $ok = $insertStmt->execute();
    $newId = $ok ? (int)$con->insert_id : null;
    $insertStmt->close();

    return $newId;
}

function commerza_touch_cart(mysqli $con, int $cartId): void
{
    $stmt = $con->prepare('UPDATE cart SET updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $stmt->close();
}

function commerza_get_cart_total_qty(mysqli $con, int $cartId): int
{
    $stmt = $con->prepare('SELECT COALESCE(SUM(quantity), 0) AS qty FROM cart_items WHERE cart_id = ?');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int)$row['qty'] : 0;
}

function commerza_get_cart_count(mysqli $con): int
{
    $cartId = commerza_get_cart_id($con, false);
    if (!$cartId) {
        return 0;
    }

    return commerza_get_cart_total_qty($con, $cartId);
}

function commerza_remove_cart_if_empty(mysqli $con, int $cartId): void
{
    $qty = commerza_get_cart_total_qty($con, $cartId);
    if ($qty > 0) {
        return;
    }

    $stmt = $con->prepare('DELETE FROM cart WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $stmt->close();
}

function commerza_fetch_cart_snapshot(mysqli $con, ?int $cartId = null): array
{
    if ($cartId === null) {
        $cartId = commerza_get_cart_id($con, false);
    }

    if (!$cartId) {
        return [
            'cart_id' => null,
            'count' => 0,
            'subtotal' => 0,
            'items' => [],
        ];
    }

    $items = [];
    $subtotal = 0.0;
    $count = 0;

    $stmt = $con->prepare(
        'SELECT ci.product_id,
                ci.quantity,
                ci.added_at,
                p.name,
                p.image,
                p.price,
                p.salePrice
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         WHERE ci.cart_id = ?
            ORDER BY ci.added_at DESC, ci.product_id DESC'
    );

    if ($stmt) {
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && ($row = $result->fetch_assoc())) {
            $quantity = max(1, (int)$row['quantity']);
            $price = (float)$row['price'];
            $salePrice = isset($row['salePrice']) ? (float)$row['salePrice'] : 0.0;
            $effective = $salePrice > 0 ? $salePrice : $price;

            $lineTotal = round($effective * $quantity, 2);
            $subtotal += $lineTotal;
            $count += $quantity;

            $items[] = [
                'id' => (int)$row['product_id'],
                'name' => (string)$row['name'],
                'image' => (string)$row['image'],
                'price' => $price,
                'salePrice' => $salePrice,
                'effective_price' => $effective,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'added_at' => (string)$row['added_at'],
            ];
        }

        $stmt->close();
    }

    return [
        'cart_id' => $cartId,
        'count' => $count,
        'subtotal' => round($subtotal, 2),
        'items' => $items,
    ];
}
