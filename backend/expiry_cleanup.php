<?php

require_once __DIR__ . '/mailer.php';

function commerza_get_expiry_hours(): int
{
    $envValue = getenv('COMMERZA_CART_WISHLIST_EXPIRY_HOURS');
    if ($envValue !== false && is_numeric($envValue)) {
        $hours = (int)$envValue;
        if ($hours >= 8 && $hours <= 12) {
            return $hours;
        }
    }

    return 10;
}

function commerza_should_run_expiry_cleanup(): bool
{
    $intervalSeconds = 900;
    $lastRun = isset($_SESSION['commerza_last_expiry_cleanup'])
        ? (int)$_SESSION['commerza_last_expiry_cleanup']
        : 0;

    if ($lastRun > 0 && (time() - $lastRun) < $intervalSeconds) {
        return false;
    }

    $_SESSION['commerza_last_expiry_cleanup'] = time();
    return true;
}

function commerza_send_expiry_notice(string $email, string $name, string $typeLabel, int $removedCount, int $hours): void
{
    if ($removedCount <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $safeName = htmlspecialchars($name !== '' ? $name : 'Customer', ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8');
    $subject = 'Commerza ' . $typeLabel . ' Expiry Notice';

    $body = '<!DOCTYPE html>
<html>
  <body style="margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
            <tr>
              <td style="padding:24px 28px;">
                <h1 style="margin:0 0 14px 0;color:#ff6600;font-size:22px;">Commerza ' . $safeType . ' Update</h1>
                <p style="margin:0 0 10px 0;line-height:1.6;color:#d7d7d7;">Hello ' . $safeName . ',</p>
                <p style="margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;">We removed ' . (int)$removedCount . ' item(s) from your ' . strtolower($typeLabel) . ' because they were inactive for more than ' . (int)$hours . ' hours.</p>
                <p style="margin:0 0 16px 0;line-height:1.6;color:#bfbfbf;">You can add them again anytime from our catalog.</p>
                <a href="https://commerza.ahmershah.dev/index.php" style="display:inline-block;padding:10px 16px;background:#ff6600;color:#111;text-decoration:none;border-radius:8px;font-weight:700;">Continue Shopping</a>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>';

    $errorMessage = null;
    commerza_send_html_mail(
        $email,
        $subject,
        $body,
        'no-reply@commerza.ahmershah.dev',
        'Commerza Notifications',
        $errorMessage
    );
}

function commerza_cleanup_expired_cart_items(mysqli $con, int $hours): void
{
    $hours = max(8, min(12, $hours));

    $selectSql =
        'SELECT c.id AS cart_id, u.email, u.full_name, COUNT(ci.id) AS stale_count
         FROM cart c
         INNER JOIN cart_items ci ON ci.cart_id = c.id
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.user_id IS NOT NULL
           AND ci.added_at < DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)
         GROUP BY c.id, u.email, u.full_name';

    $result = $con->query($selectSql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cartId = (int)$row['cart_id'];

            $deleteSql =
                'DELETE FROM cart_items
                 WHERE cart_id = ?
                   AND added_at < DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)';
            $deleteStmt = $con->prepare($deleteSql);
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $cartId);
                $deleteStmt->execute();
                $removed = $deleteStmt->affected_rows;
                $deleteStmt->close();

                if ($removed > 0) {
                    commerza_send_expiry_notice(
                        (string)$row['email'],
                        (string)$row['full_name'],
                        'Cart',
                        $removed,
                        $hours
                    );
                }
            }
        }
    }

    $guestDeleteSql =
        'DELETE ci
         FROM cart_items ci
         INNER JOIN cart c ON c.id = ci.cart_id
         WHERE c.user_id IS NULL
           AND ci.added_at < DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)';
    $con->query($guestDeleteSql);

    $cleanupCartsSql =
        'DELETE c
         FROM cart c
         LEFT JOIN cart_items ci ON ci.cart_id = c.id
         WHERE ci.id IS NULL';
    $con->query($cleanupCartsSql);
}

function commerza_cleanup_expired_wishlist_items(mysqli $con, int $hours): void
{
    $hours = max(8, min(12, $hours));

    $selectSql =
        'SELECT w.id AS wishlist_id, u.email, u.full_name, COUNT(wi.id) AS stale_count
         FROM wishlist w
         INNER JOIN wishlist_items wi ON wi.wishlist_id = w.id
         INNER JOIN users u ON u.id = w.user_id
         WHERE wi.added_at < DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)
         GROUP BY w.id, u.email, u.full_name';

    $result = $con->query($selectSql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $wishlistId = (int)$row['wishlist_id'];

            $deleteSql =
                'DELETE FROM wishlist_items
                 WHERE wishlist_id = ?
                   AND added_at < DATE_SUB(NOW(), INTERVAL ' . $hours . ' HOUR)';
            $deleteStmt = $con->prepare($deleteSql);
            if ($deleteStmt) {
                $deleteStmt->bind_param('i', $wishlistId);
                $deleteStmt->execute();
                $removed = $deleteStmt->affected_rows;
                $deleteStmt->close();

                if ($removed > 0) {
                    commerza_send_expiry_notice(
                        (string)$row['email'],
                        (string)$row['full_name'],
                        'Wishlist',
                        $removed,
                        $hours
                    );
                }
            }
        }
    }
}

function commerza_run_expiry_cleanup(mysqli $con): void
{
    $hours = commerza_get_expiry_hours();

    try {
        commerza_cleanup_expired_cart_items($con, $hours);
        commerza_cleanup_expired_wishlist_items($con, $hours);
    } catch (Throwable $error) {
        error_log('Commerza expiry cleanup failed: ' . $error->getMessage());
    }
}
