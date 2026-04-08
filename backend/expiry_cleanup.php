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

function commerza_expiry_public_url(string $path = ''): string
{
    if (function_exists('commerza_public_base_url')) {
        $base = rtrim((string)commerza_public_base_url(), '/');
    } else {
        $configuredBase = trim((string)(getenv('COMMERZA_APP_URL') ?: getenv('COMMERZA_PUBLIC_URL') ?: getenv('APP_URL') ?: ''));
        if ($configuredBase !== '' && filter_var($configuredBase, FILTER_VALIDATE_URL)) {
            $base = rtrim($configuredBase, '/');
        } else {
            $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
            $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            $cfVisitor = strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));
            $isHttps = ($https !== '' && $https !== 'off')
                || ($forwardedProto !== '' && str_contains($forwardedProto, 'https'))
                || ($cfVisitor !== '' && str_contains($cfVisitor, '"https"'))
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
            $scheme = $isHttps ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost')));
            if ($host === '') {
                $host = 'localhost';
            }

            $base = $scheme . '://' . $host;
        }
    }

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function commerza_should_run_expiry_cleanup(): bool
{
    $intervalSeconds = 900;
    $lockPath = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'commerza_expiry_cleanup_last_run.txt';
    $now = time();

    $handle = @fopen($lockPath, 'c+');
    if (!is_resource($handle)) {
        $lastRun = isset($_SESSION['commerza_last_expiry_cleanup'])
            ? (int)$_SESSION['commerza_last_expiry_cleanup']
            : 0;

        if ($lastRun > 0 && ($now - $lastRun) < $intervalSeconds) {
            return false;
        }

        $_SESSION['commerza_last_expiry_cleanup'] = $now;
        return true;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }

    rewind($handle);
    $raw = stream_get_contents($handle);
    $lastRun = is_string($raw) && trim($raw) !== '' ? (int)trim($raw) : 0;

    if ($lastRun > 0 && ($now - $lastRun) < $intervalSeconds) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, (string)$now);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $_SESSION['commerza_last_expiry_cleanup'] = $now;
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
    $continueShoppingUrl = htmlspecialchars(commerza_expiry_public_url('/index.php'), ENT_QUOTES, 'UTF-8');

    $sender = commerza_mail_default_sender();
    $fromEmail = filter_var((string)($sender['email'] ?? ''), FILTER_VALIDATE_EMAIL)
        ? (string)$sender['email']
        : 'support@ahmershah.dev';
    $fromName = trim((string)($sender['name'] ?? ''));
    if ($fromName === '') {
        $fromName = 'Commerza Notifications';
    }

    $body = '<!DOCTYPE html>
<html>
  <body style="margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
      <tr>
        <td align="center">
          <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
            <tr>
                            <td align="center" style="padding:24px 28px 8px 28px;">
                                <h1 style="margin:0;color:#ff6600;font-size:22px;">Commerza ' . $safeType . ' Update</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:10px 28px 24px 28px;">
                <p style="margin:0 0 10px 0;line-height:1.6;color:#d7d7d7;">Hello ' . $safeName . ',</p>
                <p style="margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;">We removed ' . (int)$removedCount . ' item(s) from your ' . strtolower($typeLabel) . ' because they were inactive for more than ' . (int)$hours . ' hours.</p>
                <p style="margin:0 0 16px 0;line-height:1.6;color:#bfbfbf;">You can add them again anytime from our catalog.</p>
                <a href="' . $continueShoppingUrl . '" style="display:inline-block;padding:10px 16px;background:#ff6600;color:#111;text-decoration:none;border-radius:8px;font-weight:700;">Continue Shopping</a>
                                <p style="margin:16px 0 0 0;line-height:1.6;color:#9f9f9f;font-size:12px;">Support: <a href="mailto:' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '" style="color:#ffb066;text-decoration:none;">' . htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8') . '</a></p>
                                <p style="margin:8px 0 0 0;line-height:1.6;color:#9f9f9f;font-size:12px;">Connect: <a href="https://instagram.com/commerza" style="color:#ffb066;text-decoration:none;">Instagram</a> <span style="color:#666;">|</span> <a href="https://facebook.com/commerza" style="color:#ffb066;text-decoration:none;">Facebook</a> <span style="color:#666;">|</span> <a href="https://www.linkedin.com/in/syedahmershah" style="color:#ffb066;text-decoration:none;">LinkedIn</a> <span style="color:#666;">|</span> <a href="https://github.com/ahmershahdev" style="color:#ffb066;text-decoration:none;">GitHub</a></p>
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
        $fromEmail,
        $fromName,
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

function commerza_cleanup_generated_media_path(string $path): bool
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

    return preg_match('/-[a-f0-9]{16}\.[a-z0-9]+$/i', basename($normalized)) === 1;
}

function commerza_product_media_path_referenced(mysqli $con, string $path): bool
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

    return false;
}

function commerza_cleanup_product_media_file(mysqli $con, string $relativePath): void
{
    $normalized = trim(str_replace('\\', '/', $relativePath));
    if (!commerza_cleanup_generated_media_path($normalized)) {
        return;
    }

    if (commerza_product_media_path_referenced($con, $normalized)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function commerza_cleanup_expired_product_trash(mysqli $con): void
{
    $result = $con->query("SHOW TABLES LIKE 'product_trash'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        return;
    }

    $expiredResult = $con->query(
        'SELECT id, image, video_url
         FROM product_trash
         WHERE purge_after <= NOW()
         LIMIT 500'
    );

    if (!($expiredResult instanceof mysqli_result) || $expiredResult->num_rows === 0) {
        return;
    }

    $ids = [];
    $paths = [];
    while ($row = $expiredResult->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
        $paths[] = (string)($row['image'] ?? '');
        $paths[] = (string)($row['video_url'] ?? '');
    }

    if (empty($ids)) {
        return;
    }

    $idList = implode(',', array_map('intval', $ids));
    if (!$con->query("DELETE FROM product_trash WHERE id IN ({$idList})")) {
        return;
    }

    foreach ($paths as $path) {
        commerza_cleanup_product_media_file($con, (string)$path);
    }
}

function commerza_run_expiry_cleanup(mysqli $con): void
{
    $hours = commerza_get_expiry_hours();

    try {
        commerza_cleanup_expired_cart_items($con, $hours);
        commerza_cleanup_expired_wishlist_items($con, $hours);
        commerza_cleanup_expired_product_trash($con);
    } catch (Throwable $error) {
        error_log('Commerza expiry cleanup failed: ' . $error->getMessage());
    }
}
