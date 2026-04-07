<?php
include "backend/data.php";
require_once __DIR__ . '/backend/notifications.php';
require_once __DIR__ . '/backend/media_image_helpers.php';

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = [];

function account_is_ajax_request(): bool
{
  $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
  if ($requestedWith === 'xmlhttprequest') {
    return true;
  }

  $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
  if (strpos($accept, 'application/json') !== false) {
    return true;
  }

  $ajaxFlag = strtolower(trim((string)($_POST['ajax'] ?? '')));
  return in_array($ajaxFlag, ['1', 'true', 'yes', 'on'], true);
}

function fetchUser(mysqli $con, int $user_id): ?array
{
  $stmt = $con->prepare("SELECT id, full_name, username, username_slug, profile_visibility, email, phone, address, profile_picture, password_hash FROM users WHERE id = ? LIMIT 1");

  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result ? $result->fetch_assoc() : null;
  $stmt->close();

  return $user ?: null;
}

function account_delete_session_key(): string
{
  return 'account_delete_pending';
}

function account_delete_pending_clear(): void
{
  unset($_SESSION[account_delete_session_key()]);
}

function account_delete_pending_get(): ?array
{
  $pending = $_SESSION[account_delete_session_key()] ?? null;
  return is_array($pending) ? $pending : null;
}

function account_delete_pending_set(array $pending): void
{
  $_SESSION[account_delete_session_key()] = $pending;
}

function account_delete_profile_picture_file(string $profilePicture): void
{
  $relative = trim($profilePicture);
  if ($relative === '' || strpos($relative, 'frontend/assets/images/users/') !== 0) {
    return;
  }

  $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
  if (is_file($absolutePath)) {
    @unlink($absolutePath);
  }
}

function account_delete_user_permanently(mysqli $con, int $userId): bool
{
  if ($userId <= 0 || !$con->begin_transaction()) {
    return false;
  }

  $statements = [
    'DELETE pri FROM product_review_images pri INNER JOIN product_reviews pr ON pr.id = pri.review_id WHERE pr.user_id = ?',
    'DELETE FROM product_reviews WHERE user_id = ?',
    'DELETE FROM coupon_redemptions WHERE user_id = ?',
    'DELETE FROM engagement_reminders WHERE user_id = ?',
    'DELETE FROM live_product_viewers WHERE user_id = ?',
    'DELETE FROM refund_requests WHERE user_id = ?',
    'DELETE FROM security_events WHERE user_id = ?',
    'DELETE FROM user_sessions WHERE user_id = ?',
    'DELETE oi FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.user_id = ?',
    'DELETE FROM orders WHERE user_id = ?',
    'DELETE cmpi FROM compare_items cmpi INNER JOIN compare_list cmpl ON cmpl.id = cmpi.compare_id WHERE cmpl.user_id = ?',
    'DELETE FROM compare_list WHERE user_id = ?',
    'DELETE ci FROM cart_items ci INNER JOIN cart c ON c.id = ci.cart_id WHERE c.user_id = ?',
    'DELETE FROM cart WHERE user_id = ?',
    'DELETE wi FROM wishlist_items wi INNER JOIN wishlist w ON w.id = wi.wishlist_id WHERE w.user_id = ?',
    'DELETE FROM wishlist WHERE user_id = ?',
  ];

  try {
    foreach ($statements as $sql) {
      $stmt = $con->prepare($sql);
      if (!$stmt) {
        throw new RuntimeException('Failed to prepare account cleanup statement.');
      }

      $stmt->bind_param('i', $userId);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Failed to execute account cleanup statement.');
      }

      $stmt->close();
    }

    $deleteStmt = $con->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    if (!$deleteStmt) {
      throw new RuntimeException('Failed to prepare user deletion statement.');
    }

    $deleteStmt->bind_param('i', $userId);
    if (!$deleteStmt->execute()) {
      $deleteStmt->close();
      throw new RuntimeException('Failed to delete account row.');
    }

    $affected = (int)$deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affected !== 1) {
      throw new RuntimeException('Account row was not deleted.');
    }

    $con->commit();
    return true;
  } catch (Throwable $exception) {
    $con->rollback();
    return false;
  }
}

function account_ensure_refund_table(mysqli $con): void
{
  static $initialized = false;

  if ($initialized) {
    return;
  }

  $con->query(
    'CREATE TABLE IF NOT EXISTS refund_requests (
      id INT NOT NULL AUTO_INCREMENT,
      order_id INT NOT NULL,
      user_id INT NOT NULL,
      reason VARCHAR(500) DEFAULT NULL,
      status ENUM("pending", "accepted", "rejected") NOT NULL DEFAULT "pending",
      admin_note VARCHAR(500) DEFAULT NULL,
      evidence_path VARCHAR(255) DEFAULT NULL,
      evidence_name VARCHAR(255) DEFAULT NULL,
      evidence_size INT NOT NULL DEFAULT 0,
      requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_refund_order (order_id),
      KEY idx_refund_user_status (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
  );

  $requiredColumns = [
    'evidence_path' => 'VARCHAR(255) DEFAULT NULL',
    'evidence_name' => 'VARCHAR(255) DEFAULT NULL',
    'evidence_size' => 'INT NOT NULL DEFAULT 0',
  ];

  foreach ($requiredColumns as $column => $definition) {
    $escapedColumn = $con->real_escape_string($column);
    $columnResult = $con->query("SHOW COLUMNS FROM refund_requests LIKE '{$escapedColumn}'");
    $exists = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;

    if (!$exists) {
      $con->query("ALTER TABLE refund_requests ADD COLUMN {$column} {$definition}");
    }
  }

  $initialized = true;
}

function account_ensure_orders_logistics_columns(mysqli $con): void
{
  static $initialized = false;

  if ($initialized) {
    return;
  }

  $requiredColumns = [
    'notes' => 'TEXT DEFAULT NULL',
    'delivery_estimate' => 'DATETIME DEFAULT NULL',
    'admin_note' => 'VARCHAR(500) DEFAULT NULL',
  ];

  foreach ($requiredColumns as $column => $definition) {
    $escapedColumn = $con->real_escape_string($column);
    $columnResult = $con->query("SHOW COLUMNS FROM orders LIKE '{$escapedColumn}'");
    $exists = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;

    if (!$exists) {
      $con->query("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
    }
  }

  $initialized = true;
}

function account_refund_upload_relative_path(string $path): bool
{
  return strpos($path, 'frontend/assets/uploads/refunds/') === 0;
}

function account_delete_refund_evidence_file(string $relativePath): void
{
  if (!account_refund_upload_relative_path($relativePath)) {
    return;
  }

  $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
  if (is_file($absolutePath)) {
    @unlink($absolutePath);
  }
}

function account_store_refund_evidence($file, int $userId, int $orderId, array &$errors): ?array
{
  if (!is_array($file)) {
    return null;
  }

  $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($errorCode === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  if ($errorCode !== UPLOAD_ERR_OK) {
    $errors[] = 'Unable to upload refund evidence right now.';
    return null;
  }

  $size = (int)($file['size'] ?? 0);
  if ($size <= 0 || $size > 6 * 1024 * 1024) {
    $errors[] = 'Refund evidence must be less than 6MB.';
    return null;
  }

  $tmpPath = (string)($file['tmp_name'] ?? '');
  if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    $errors[] = 'Invalid refund evidence upload.';
    return null;
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
  if ($finfo) {
    finfo_close($finfo);
  }

  $allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
  ];

  $extension = $allowed[$mime] ?? '';
  if ($extension === '') {
    $errors[] = 'Evidence must be JPG, PNG, WEBP, GIF, or PDF.';
    return null;
  }

  $isImageEvidence = strpos($mime, 'image/') === 0;
  $imageBlob = '';
  $outputExtension = $extension;

  if ($isImageEvidence) {
    $conversion = commerza_media_convert_upload_to_webp($tmpPath, $mime, 420, 2200);
    if (!(bool)($conversion['ok'] ?? false)) {
      $errors[] = (string)($conversion['message'] ?? 'Unable to parse and compress evidence image.');
      return null;
    }

    $imageBlob = (string)($conversion['binary'] ?? ($conversion['blob'] ?? ''));
    if ($imageBlob === '') {
      $errors[] = 'Unable to save parsed evidence image.';
      return null;
    }

    $candidateExtension = strtolower(trim((string)($conversion['extension'] ?? ($conversion['output_extension'] ?? 'webp'))));
    if (!preg_match('/^[a-z0-9]{2,6}$/', $candidateExtension)) {
      $candidateExtension = 'webp';
    }

    $outputExtension = $candidateExtension;
  }

  $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'refunds';
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    $errors[] = 'Unable to prepare refund upload directory.';
    return null;
  }

  $originalName = trim((string)($file['name'] ?? 'refund-evidence'));
  $originalName = preg_replace('/[^a-zA-Z0-9._ -]/', '-', $originalName) ?? 'refund-evidence';
  $originalName = trim($originalName);
  if ($originalName === '') {
    $originalName = 'refund-evidence.' . $extension;
  }

  $filename = 'refund_' . $userId . '_' . $orderId . '_' . bin2hex(random_bytes(10)) . '.' . $outputExtension;
  $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
  $relativePath = 'frontend/assets/uploads/refunds/' . $filename;
  $storedSize = $size;

  if ($isImageEvidence) {
    if (@file_put_contents($absolutePath, $imageBlob) === false) {
      $errors[] = 'Unable to save parsed refund evidence file.';
      return null;
    }

    $storedSize = max(0, strlen($imageBlob));
  } else {
    if (!move_uploaded_file($tmpPath, $absolutePath)) {
      $errors[] = 'Unable to save refund evidence file.';
      return null;
    }

    $storedSize = (int)(filesize($absolutePath) ?: $size);
  }

  return [
    'path' => $relativePath,
    'name' => $originalName,
    'size' => $storedSize,
  ];
}

$user = fetchUser($con, $user_id);

if (!$user) {
  session_unset();
  session_destroy();
  header("Location: login.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    http_response_code(403);
    exit("Forbidden.");
  }

  $action = (string)($_POST['action'] ?? '');
  $isAjaxRequest = account_is_ajax_request();

  $captchaContexts = [
    'update_profile' => 'user_account_profile',
    'update_password' => 'user_account_password',
    'request_delete_account_code' => 'user_account_delete_request',
    'delete_account_permanently' => 'user_account_delete_confirm',
  ];

  if (isset($captchaContexts[$action])) {
    $captchaCheck = commerza_captcha_verify_submission($con, $_POST, (string)$captchaContexts[$action]);
    if (!(bool)$captchaCheck['ok']) {
      $errors[] = (string)$captchaCheck['message'];
    }
  }

  $clientIp = commerza_client_ip();
  $rateIdentifier = (string)$user_id;
  $rateScopeUsed = '';
  $ratePolicies = [
    'update_profile' => ['scope' => 'user_account_update_profile', 'max' => 8, 'window' => 3600, 'block' => 1800],
    'update_password' => ['scope' => 'user_account_update_password', 'max' => 6, 'window' => 3600, 'block' => 2400],
    'update_profile_picture' => ['scope' => 'user_account_update_picture', 'max' => 8, 'window' => 3600, 'block' => 1800],
    'request_refund' => ['scope' => 'user_account_request_refund', 'max' => 4, 'window' => 3600, 'block' => 3600],
    'request_delete_account_code' => ['scope' => 'user_account_delete_code', 'max' => 3, 'window' => 3600, 'block' => 3600],
    'delete_account_permanently' => ['scope' => 'user_account_delete_confirm', 'max' => 6, 'window' => 3600, 'block' => 3600],
  ];

  if (isset($ratePolicies[$action]) && empty($errors)) {
    $policy = $ratePolicies[$action];
    $rateScopeUsed = (string)$policy['scope'];

    $rate = commerza_rate_limit_check(
      $con,
      $rateScopeUsed,
      $rateIdentifier,
      $clientIp,
      (int)$policy['max'],
      (int)$policy['window'],
      (int)$policy['block'],
      max((int)$policy['block'], 7200),
      86400
    );

    if (!(bool)($rate['allowed'] ?? false)) {
      $retrySeconds = max(1, (int)($rate['retry_after'] ?? 1));
      $retryMinutes = (int)ceil($retrySeconds / 60);
      commerza_security_log_rate_limit_block(
        $con,
        $rateScopeUsed,
        'user',
        $rateIdentifier,
        $clientIp,
        $retrySeconds
      );
      $errors[] = 'Too many requests for this action. Try again in ' . $retryMinutes . ' minute(s) (' . $retrySeconds . ' seconds).';
    }
  }

  if ($action === 'logout') {
    commerza_forget_current_remember_token($con);
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
  }

  if ($action === 'update_profile') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $username = commerza_username_slug((string)($_POST['username'] ?? ''));
    $profile_visibility = strtolower(trim((string)($_POST['profile_visibility'] ?? 'private')));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = preg_replace('/\s+/', '', trim((string)($_POST['phone'] ?? '')));
    $phone = $phone ?? '';
    $address = trim((string)($_POST['address'] ?? ''));

    if (
      strlen($full_name) < 3 ||
      strlen($full_name) > 40 ||
      !preg_match("/^[A-Za-z][A-Za-z\\s\\.\\'\\-]{2,39}$/", $full_name)
    ) {
      $errors[] = "Full name must be 3-40 valid letters.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
      $errors[] = "Invalid email address.";
    }

    if (!commerza_username_is_valid($username)) {
      $errors[] = "Username must be 3-24 chars and use lowercase letters, numbers, or underscores.";
    }

    if (empty($errors)) {
      $blockedUsername = commerza_username_blacklist_lookup($con, $username);
      if (is_array($blockedUsername)) {
        $errors[] = commerza_username_blacklist_feedback_message($blockedUsername);
      }
    }

    if (!in_array($profile_visibility, ['private', 'public'], true)) {
      $errors[] = "Invalid profile visibility option.";
    }

    if (!preg_match('/^\d{11,15}$/', $phone)) {
      $errors[] = "Invalid phone number.";
    }

    if ($address !== '' && strlen($address) < 8) {
      $errors[] = "Address must be at least 8 characters.";
    }

    if (strlen($address) > 255) {
      $errors[] = "Address is too long.";
    }

    if (empty($errors)) {
      $emailCheck = $con->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
      $phoneCheck = $con->prepare("SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1");
      $usernameCheck = $con->prepare("SELECT id FROM users WHERE username_slug = ? AND id <> ? LIMIT 1");

      if (!$emailCheck || !$phoneCheck || !$usernameCheck) {
        $errors[] = "Something went wrong. Please try again.";
      } else {
        $emailCheck->bind_param("si", $email, $user_id);
        $emailCheck->execute();
        $emailResult = $emailCheck->get_result();
        $emailExists = ($emailResult && $emailResult->num_rows > 0);

        $phoneCheck->bind_param("si", $phone, $user_id);
        $phoneCheck->execute();
        $phoneResult = $phoneCheck->get_result();
        $phoneExists = ($phoneResult && $phoneResult->num_rows > 0);

        $usernameCheck->bind_param("si", $username, $user_id);
        $usernameCheck->execute();
        $usernameResult = $usernameCheck->get_result();
        $usernameExists = ($usernameResult && $usernameResult->num_rows > 0);

        $emailCheck->close();
        $phoneCheck->close();
        $usernameCheck->close();

        if ($emailExists) {
          $errors[] = "Email is already in use.";
        }

        if ($phoneExists) {
          $errors[] = "Phone is already in use.";
        }

        if ($usernameExists) {
          $errors[] = "Username is already in use.";
        }
      }
    }

    if (empty($errors)) {
      $address_value = $address !== '' ? $address : null;
      $usernameChangedFlag = strcasecmp((string)($user['username_slug'] ?? ''), $username) !== 0 ? 1 : 0;
      $stmt = $con->prepare("UPDATE users SET full_name = ?, username = ?, username_slug = ?, profile_visibility = ?, email = ?, phone = ?, address = ?, username_changed_at = CASE WHEN ? = 1 THEN NOW() ELSE username_changed_at END WHERE id = ? LIMIT 1");

      if (!$stmt) {
        $errors[] = "Something went wrong. Please try again.";
      } else {
        $stmt->bind_param("sssssssii", $full_name, $username, $username, $profile_visibility, $email, $phone, $address_value, $usernameChangedFlag, $user_id);

        if ($stmt->execute()) {
          $success[] = "Profile updated successfully.";
        } else {
          if ((int)$stmt->errno === 1062 || (int)$con->errno === 1062) {
            $errors[] = "Email, phone, or username is already in use.";
          } else {
            $errors[] = "Something went wrong. Please try again.";
          }
        }

        $stmt->close();
      }
    }
  } elseif ($action === 'update_password') {
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($new_password !== $confirm_password) {
      $errors[] = "New passwords do not match.";
    }

    $passwordPolicyError = null;
    if (!commerza_password_validate($new_password, $passwordPolicyError)) {
      $errors[] = $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description();
    }

    $freshUser = fetchUser($con, $user_id);

    if (!$freshUser) {
      $errors[] = "User not found.";
    } elseif (!commerza_password_verify($current_password, (string)$freshUser['password_hash'])) {
      $errors[] = "Current password is incorrect.";
    } elseif (commerza_password_verify($new_password, (string)$freshUser['password_hash'])) {
      $errors[] = "New password must be different from current password.";
    }

    if (empty($errors)) {
      $new_hash = commerza_password_hash($new_password);
      $stmt = $con->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");

      if (!$stmt) {
        $errors[] = "Something went wrong. Please try again.";
      } else {
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
          $success[] = "Password updated successfully.";
        } else {
          $errors[] = "Something went wrong. Please try again.";
        }

        $stmt->close();
      }
    }
  } elseif ($action === 'update_profile_picture') {
    if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
      $errors[] = "Invalid upload request.";
    } else {
      $file = $_FILES['profile_picture'];

      if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please choose an image first.";
      } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Failed to upload image.";
      } elseif ((int)$file['size'] > 2 * 1024 * 1024) {
        $errors[] = "Image must be 2MB or smaller.";
      } else {
        $tmp_path = (string)$file['tmp_name'];

        if (!is_uploaded_file($tmp_path)) {
          $errors[] = "Invalid upload.";
        } else {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime = $finfo ? (string)finfo_file($finfo, $tmp_path) : '';

          if ($finfo) {
            finfo_close($finfo);
          }

          $allowed = commerza_media_allowed_image_mimes();

          if (!isset($allowed[$mime])) {
            $errors[] = "Only JPG, PNG, WEBP, and GIF are allowed.";
          } else {
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users';

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
              $errors[] = "Failed to create upload directory.";
            } else {
              $conversion = commerza_media_convert_upload_to_webp($tmp_path, $mime, 220, 1400);
              if (!(bool)($conversion['ok'] ?? false)) {
                $errors[] = (string)($conversion['message'] ?? 'Failed to parse and compress profile picture.');
              } else {
                $outputExtension = strtolower(trim((string)($conversion['extension'] ?? '')));
                if ($outputExtension === '') {
                  $outputExtension = 'webp';
                }

                $filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $outputExtension;
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                $publicPath = 'frontend/assets/images/users/' . $filename;
                $binary = (string)($conversion['binary'] ?? '');

                if ($binary === '' || file_put_contents($destination, $binary) === false) {
                  $errors[] = "Failed to save uploaded image.";
                } else {
                  $updateStmt = $con->prepare("UPDATE users SET profile_picture = ? WHERE id = ? LIMIT 1");

                  if (!$updateStmt) {
                    $errors[] = "Something went wrong. Please try again.";
                  } else {
                    $updateStmt->bind_param("si", $publicPath, $user_id);

                    if ($updateStmt->execute()) {
                      if (!empty($user['profile_picture']) && strpos((string)$user['profile_picture'], 'frontend/assets/images/users/') === 0) {
                        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$user['profile_picture']);

                        if (is_file($oldPath)) {
                          @unlink($oldPath);
                        }
                      }

                      $success[] = "Profile picture updated successfully.";
                    } else {
                      $errors[] = "Something went wrong. Please try again.";
                    }

                    $updateStmt->close();
                  }
                }
              }
            }
          }
        }
      }
    }
  } elseif ($action === 'request_refund') {
    account_ensure_refund_table($con);

    $orderId = (int)($_POST['order_id'] ?? 0);
    $refundReason = trim((string)($_POST['refund_reason'] ?? ''));

    if ($orderId <= 0) {
      $errors[] = 'Invalid refund request.';
    }

    if ($refundReason === '' || strlen($refundReason) < 8) {
      $errors[] = 'Please provide a brief reason (at least 8 characters).';
    }

    if (strlen($refundReason) > 500) {
      $refundReason = substr($refundReason, 0, 500);
    }

    $orderRow = null;
    if (empty($errors)) {
      $orderStmt = $con->prepare(
        'SELECT id, order_number, customer_name, customer_email, status, created_at, updated_at
           FROM orders
           WHERE id = ? AND user_id = ?
           LIMIT 1'
      );

      if (!$orderStmt) {
        $errors[] = 'Unable to validate refund request right now.';
      } else {
        $orderStmt->bind_param('ii', $orderId, $user_id);
        $orderStmt->execute();
        $result = $orderStmt->get_result();
        $orderRow = $result ? $result->fetch_assoc() : null;
        $orderStmt->close();
      }
    }

    if (empty($errors) && !$orderRow) {
      $errors[] = 'Order not found for this account.';
    }

    if (empty($errors)) {
      $orderStatus = strtolower((string)($orderRow['status'] ?? ''));
      if ($orderStatus !== 'delivered') {
        $errors[] = 'Only delivered orders can be refunded.';
      }
    }

    $deadlineTimestamp = null;
    if (empty($errors) && $orderRow) {
      $anchor = trim((string)($orderRow['updated_at'] ?? ''));
      if ($anchor === '') {
        $anchor = trim((string)($orderRow['created_at'] ?? ''));
      }

      $anchorTs = strtotime($anchor);
      if ($anchorTs === false) {
        $errors[] = 'Could not verify refund window for this order.';
      } else {
        $deadlineTimestamp = strtotime('+7 days', $anchorTs);
        if ($deadlineTimestamp === false || $deadlineTimestamp < time()) {
          $errors[] = 'Refund window has expired for this order.';
        }
      }
    }

    $existingRefund = null;
    $uploadedEvidence = null;
    if (empty($errors)) {
      $existingStmt = $con->prepare(
        'SELECT id, status, evidence_path, evidence_name, evidence_size
           FROM refund_requests
           WHERE order_id = ?
           LIMIT 1'
      );

      if (!$existingStmt) {
        $errors[] = 'Unable to verify previous refund requests.';
      } else {
        $existingStmt->bind_param('i', $orderId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingRefund = $existingResult ? $existingResult->fetch_assoc() : null;
        $existingStmt->close();
      }
    }

    if (empty($errors) && $existingRefund) {
      $existingStatus = strtolower((string)($existingRefund['status'] ?? 'pending'));
      if ($existingStatus === 'pending') {
        $errors[] = 'Refund request already submitted and pending review.';
      } elseif ($existingStatus === 'accepted') {
        $errors[] = 'Refund has already been accepted for this order.';
      } elseif ($existingStatus === 'rejected') {
        $errors[] = 'A refund request was already reviewed for this order. You cannot submit another request.';
      } else {
        $errors[] = 'A refund request already exists for this order.';
      }
    }

    if (empty($errors)) {
      $uploadedEvidence = account_store_refund_evidence(
        $_FILES['refund_evidence'] ?? null,
        $user_id,
        $orderId,
        $errors
      );
    }

    if (empty($errors)) {
      $persisted = false;
      $previousEvidencePath = trim((string)($existingRefund['evidence_path'] ?? ''));
      $previousEvidenceName = trim((string)($existingRefund['evidence_name'] ?? ''));
      $previousEvidenceSize = (int)($existingRefund['evidence_size'] ?? 0);

      $evidencePath = $uploadedEvidence['path'] ?? ($previousEvidencePath !== '' ? $previousEvidencePath : '');
      $evidenceName = $uploadedEvidence['name'] ?? ($previousEvidenceName !== '' ? $previousEvidenceName : '');
      $evidenceSize = (int)($uploadedEvidence['size'] ?? $previousEvidenceSize);

      if ($existingRefund) {
        $refundId = (int)($existingRefund['id'] ?? 0);
        $updateRefund = $con->prepare(
          'UPDATE refund_requests
             SET reason = ?,
                 status = "pending",
                 admin_note = NULL,
                 evidence_path = NULLIF(?, ""),
                 evidence_name = NULLIF(?, ""),
                 evidence_size = ?,
                 requested_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateRefund) {
          $errors[] = 'Unable to submit refund request right now.';
        } else {
          $updateRefund->bind_param('sssii', $refundReason, $evidencePath, $evidenceName, $evidenceSize, $refundId);
          $persisted = $updateRefund->execute();
          $updateRefund->close();

          if (!$persisted) {
            $errors[] = 'Unable to submit refund request right now.';
          }
        }
      } else {
        $insertRefund = $con->prepare(
          'INSERT INTO refund_requests (order_id, user_id, reason, status, evidence_path, evidence_name, evidence_size)
             VALUES (?, ?, ?, "pending", NULLIF(?, ""), NULLIF(?, ""), ?)'
        );

        if (!$insertRefund) {
          $errors[] = 'Unable to submit refund request right now.';
        } else {
          $insertRefund->bind_param('iisssi', $orderId, $user_id, $refundReason, $evidencePath, $evidenceName, $evidenceSize);
          $persisted = $insertRefund->execute();
          $insertRefund->close();

          if (!$persisted) {
            $errors[] = 'Unable to submit refund request right now.';
          }
        }
      }

      if (!$persisted && $uploadedEvidence && !empty($uploadedEvidence['path'])) {
        account_delete_refund_evidence_file((string)$uploadedEvidence['path']);
      }

      if (
        $persisted &&
        $uploadedEvidence &&
        !empty($uploadedEvidence['path']) &&
        $previousEvidencePath !== '' &&
        $previousEvidencePath !== (string)$uploadedEvidence['path']
      ) {
        account_delete_refund_evidence_file($previousEvidencePath);
      }
    }

    if (empty($errors) && $orderRow) {
      commerza_notify_admin_refund_request(
        $con,
        [
          'order_number' => (string)($orderRow['order_number'] ?? ''),
          'customer_name' => (string)($orderRow['customer_name'] ?? ''),
          'customer_email' => (string)($orderRow['customer_email'] ?? ''),
          'refund_reason' => $refundReason,
          'refund_deadline' => $deadlineTimestamp ? date('Y-m-d H:i:s', $deadlineTimestamp) : '',
        ],
        $refundReason
      );

      $success[] = 'Refund request sent. Our team will review and email you updates.';
    }
  } elseif ($action === 'request_delete_account_code') {
    if (empty($errors)) {
      $freshUser = fetchUser($con, $user_id);
      if (!$freshUser) {
        $errors[] = 'Unable to verify your account right now.';
      } else {
        $pending = account_delete_pending_get();
        $lastSentAt = (int)($pending['last_sent_at'] ?? 0);
        if ($lastSentAt > 0 && (time() - $lastSentAt) < 60) {
          $waitSeconds = max(1, 60 - (time() - $lastSentAt));
          $errors[] = 'Please wait ' . $waitSeconds . ' second(s) before requesting another delete code.';
        } else {
          $email = strtolower(trim((string)($freshUser['email'] ?? '')));
          if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Your account email is invalid. Update your profile email first.';
          } else {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $mailError = null;
            $mailSent = commerza_notify_account_deletion_code(
              $con,
              $email,
              (string)($freshUser['full_name'] ?? 'Customer'),
              $code,
              $mailError
            );

            if (!$mailSent) {
              $errors[] = $mailError ?: 'Unable to send deletion code. Please try another method or retry later.';
            } else {
              account_delete_pending_set([
                'user_id' => $user_id,
                'email' => $email,
                'code_hash' => hash('sha256', $code),
                'attempts' => 0,
                'expires_at' => time() + 600,
                'last_sent_at' => time(),
              ]);

              $success[] = 'Deletion code sent to your email. Enter the code to permanently delete your account.';
            }
          }
        }
      }
    }
  } elseif ($action === 'delete_account_permanently') {
    if (empty($errors)) {
      $pending = account_delete_pending_get();
      $freshUser = fetchUser($con, $user_id);
      $currentPasswordInput = (string)($_POST['delete_account_password'] ?? '');

      if (!$freshUser) {
        $errors[] = 'Unable to verify your account right now.';
      } elseif (!is_array($pending)) {
        $errors[] = 'Request an account deletion code first.';
      } elseif (!commerza_password_verify($currentPasswordInput, (string)($freshUser['password_hash'] ?? ''))) {
        $errors[] = 'Enter your current password to confirm account deletion.';
      } else {
        $pendingUserId = (int)($pending['user_id'] ?? 0);
        $pendingEmail = strtolower(trim((string)($pending['email'] ?? '')));
        $currentEmail = strtolower(trim((string)($freshUser['email'] ?? '')));
        $expiresAt = (int)($pending['expires_at'] ?? 0);

        if ($pendingUserId !== $user_id || $pendingEmail === '' || $currentEmail !== $pendingEmail) {
          account_delete_pending_clear();
          $errors[] = 'Deletion code session is invalid. Request a new code.';
        } elseif ($expiresAt <= 0 || $expiresAt < time()) {
          account_delete_pending_clear();
          $errors[] = 'Deletion code expired. Request a new code.';
        } else {
          $enteredCode = trim((string)($_POST['delete_account_code'] ?? ''));
          if (!preg_match('/^\d{6}$/', $enteredCode)) {
            $errors[] = 'Enter the 6-digit account deletion code.';
          } else {
            $expectedHash = (string)($pending['code_hash'] ?? '');
            $enteredHash = hash('sha256', $enteredCode);

            if ($expectedHash === '' || !hash_equals($expectedHash, $enteredHash)) {
              $attempts = max(0, (int)($pending['attempts'] ?? 0)) + 1;
              $pending['attempts'] = $attempts;

              if ($attempts >= 6) {
                account_delete_pending_clear();
                $errors[] = 'Too many invalid deletion code attempts. Request a new code.';
              } else {
                account_delete_pending_set($pending);
                $remaining = max(0, 6 - $attempts);
                $errors[] = 'Invalid deletion code. Remaining attempts: ' . $remaining . '.';
              }
            } else {
              $profilePicture = (string)($freshUser['profile_picture'] ?? '');
              $deleted = account_delete_user_permanently($con, $user_id);

              if (!$deleted) {
                $errors[] = 'Unable to delete your account right now. Please contact support.';
              } else {
                account_delete_pending_clear();
                account_delete_profile_picture_file($profilePicture);
                commerza_forget_current_remember_token($con);
                session_unset();
                session_destroy();
                header('Location: login.php?account_deleted=1');
                exit;
              }
            }
          }
        }
      }
    }
  } else {
    $errors[] = "Invalid request.";
  }

  if (empty($errors) && $rateScopeUsed !== '') {
    commerza_rate_limit_reset($con, $rateScopeUsed, $rateIdentifier, $clientIp);
  }

  if (empty($errors) && $action !== 'logout') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }

  $user = fetchUser($con, $user_id);

  if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
  }

  if ($isAjaxRequest && $action === 'update_profile_picture') {
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8');
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $responseVisibility = strtolower(trim((string)($user['profile_visibility'] ?? 'private')));
    if (!in_array($responseVisibility, ['private', 'public'], true)) {
      $responseVisibility = 'private';
    }

    $responseUsername = commerza_username_slug((string)($user['username'] ?? ''));
    if (!commerza_username_is_valid($responseUsername)) {
      $responseUsername = commerza_username_base_from_identity(
        (string)($user['full_name'] ?? ''),
        (string)($user['email'] ?? ''),
        $user_id
      );
      if (!commerza_username_is_valid($responseUsername)) {
        $responseUsername = 'user' . max(1, $user_id);
      }
    }

    $profilePicture = !empty($user['profile_picture'])
      ? (string)$user['profile_picture']
      : 'frontend/assets/images/logo/commerza-logo.webp';

    $primaryMessage = '';
    if (!empty($success)) {
      $primaryMessage = (string)$success[0];
    } elseif (!empty($errors)) {
      $primaryMessage = (string)$errors[0];
    }

    echo json_encode([
      'ok' => empty($errors),
      'message' => $primaryMessage,
      'errors' => array_values($errors),
      'success' => array_values($success),
      'csrf_token' => (string)($_SESSION['csrf_token'] ?? ''),
      'profile_picture' => $profilePicture,
      'full_name' => (string)($user['full_name'] ?? ''),
      'username' => $responseUsername,
      'email' => (string)($user['email'] ?? ''),
      'profile_visibility' => $responseVisibility,
      'profile_visibility_label' => $responseVisibility === 'public' ? 'Public Profile' : 'Private Profile',
      'profile_visibility_icon' => $responseVisibility === 'public' ? 'bi-globe2' : 'bi-shield-lock',
    ], JSON_UNESCAPED_SLASHES);
    exit;
  }
}

$orders = [];
account_ensure_orders_logistics_columns($con);
$orderStmt = $con->prepare("SELECT o.id, o.order_number, o.grand_total, o.status, o.created_at, o.updated_at, o.notes, o.delivery_estimate, o.admin_note, COALESCE(GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR '||'), '') AS order_items FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? GROUP BY o.id, o.order_number, o.grand_total, o.status, o.created_at, o.updated_at, o.notes, o.delivery_estimate, o.admin_note ORDER BY o.created_at DESC");

if ($orderStmt) {
  $orderStmt->bind_param("i", $user_id);
  $orderStmt->execute();
  $orderResult = $orderStmt->get_result();

  if ($orderResult) {
    while ($row = $orderResult->fetch_assoc()) {
      $orders[] = $row;
    }
  }

  $orderStmt->close();
}

account_ensure_refund_table($con);

$refundRequestsByOrder = [];
$refundStmt = $con->prepare(
  'SELECT order_id, status, reason, admin_note, evidence_path, evidence_name, evidence_size, requested_at, updated_at
     FROM refund_requests
     WHERE user_id = ?
     ORDER BY updated_at DESC, id DESC'
);

if ($refundStmt) {
  $refundStmt->bind_param('i', $user_id);
  $refundStmt->execute();
  $refundResult = $refundStmt->get_result();

  if ($refundResult) {
    while ($row = $refundResult->fetch_assoc()) {
      $orderId = (int)($row['order_id'] ?? 0);
      if ($orderId <= 0 || isset($refundRequestsByOrder[$orderId])) {
        continue;
      }

      $refundRequestsByOrder[$orderId] = [
        'status' => strtolower((string)($row['status'] ?? 'pending')),
        'reason' => (string)($row['reason'] ?? ''),
        'admin_note' => (string)($row['admin_note'] ?? ''),
        'evidence_path' => (string)($row['evidence_path'] ?? ''),
        'evidence_name' => (string)($row['evidence_name'] ?? ''),
        'evidence_size' => (int)($row['evidence_size'] ?? 0),
        'requested_at' => (string)($row['requested_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
      ];
    }
  }

  $refundStmt->close();
}

$profile_picture = !empty($user['profile_picture']) ? (string)$user['profile_picture'] : 'frontend/assets/images/logo/commerza-logo.webp';
$username_value = commerza_username_slug((string)($user['username'] ?? ''));
if (!commerza_username_is_valid($username_value)) {
  $username_value = commerza_username_base_from_identity(
    (string)($user['full_name'] ?? ''),
    (string)($user['email'] ?? ''),
    $user_id
  );

  if (!commerza_username_is_valid($username_value)) {
    $username_value = 'user' . max(1, $user_id);
  }
}

$profile_visibility_value = strtolower(trim((string)($user['profile_visibility'] ?? 'private')));
if (!in_array($profile_visibility_value, ['private', 'public'], true)) {
  $profile_visibility_value = 'private';
}

$profile_visibility_label = $profile_visibility_value === 'public' ? 'Public Profile' : 'Private Profile';

$appBaseHref = rtrim(commerza_public_base_url(), '/') . '/';
$accountCleanPath = '/account/' . rawurlencode($username_value);
$accountCanonicalUrl = commerza_absolute_url($accountCleanPath);
$accountImageUrl = commerza_absolute_url('/frontend/assets/images/logo/commerza-logo.webp');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
  $targetPath = (string)(parse_url($accountCanonicalUrl, PHP_URL_PATH) ?? '');

  $normalizePath = static function (string $path): string {
    $normalized = '/' . trim(str_replace('\\', '/', $path), '/');
    $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;
    return strtolower(rtrim($normalized, '/'));
  };

  if ($normalizePath($requestPath) !== $normalizePath($targetPath)) {
    $queryString = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?? '');
    $redirectUrl = $accountCanonicalUrl . ($queryString !== '' ? '?' . $queryString : '');
    header('Location: ' . $redirectUrl, true, 302);
    exit;
  }
}

$accountDeletePending = account_delete_pending_get();
$accountDeleteCodeExpiresIn = 0;
if (is_array($accountDeletePending)) {
  $expiresAt = (int)($accountDeletePending['expires_at'] ?? 0);
  if ($expiresAt <= 0 || $expiresAt < time()) {
    account_delete_pending_clear();
    $accountDeletePending = null;
  } else {
    $accountDeleteCodeExpiresIn = max(1, $expiresAt - time());
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <base href="<?= htmlspecialchars($appBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Manage your Commerza account, update profile details, and review your order history securely.">
  <meta property="og:title" content="My Account | Commerza">
  <meta property="og:description" content="Manage your Commerza account, update profile details, and review your orders.">
  <meta property="og:url" content="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($accountImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>My Account | Commerza</title>
  <link rel="canonical" href="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "My Account | Commerza",
      "url": "<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>",
      "description": "Manage your Commerza account profile and order history."
    }
  </script>

  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link rel="stylesheet" href="frontend/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    .profile-img {
      width: 120px;
      height: 120px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid #ff6600;
      display: block;
      margin: 0 auto;
    }

    .account-page label {
      color: rgb(255, 255, 255);
    }

    .account-breadcrumb .breadcrumb {
      background: rgba(16, 16, 16, 0.65);
      border: 1px solid rgba(255, 102, 0, 0.24);
      border-radius: 999px;
      display: inline-flex;
      margin: 0;
      padding: 8px 14px;
    }

    .account-breadcrumb .breadcrumb-item,
    .account-breadcrumb .breadcrumb-item a {
      color: #ffc89d;
      font-size: 0.78rem;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      text-decoration: none;
    }

    .account-breadcrumb .breadcrumb-item.active {
      color: #fff3e5;
    }

    .account-breadcrumb .breadcrumb-item+.breadcrumb-item::before {
      color: rgba(255, 204, 163, 0.7);
    }

    .password-wrapper {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;
    }

    .password-wrapper .form-control {
      padding-right: 40px;
    }

    .account-toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #ffcc00;
      z-index: 3;
      font-size: 18px;
      line-height: 1;
      pointer-events: auto;
    }

    #serverError,
    #serverSuccess {
      border: none;
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      position: fixed;
      top: 20px;
      right: 0;
      left: 0;
      margin: auto;
      width: 380px;
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }

    #serverError {
      background-color: #dc3545;
    }

    #serverSuccess {
      background-color: #198754;
    }

    .account-live-feedback {
      min-height: 16px;
      margin-top: 6px;
      font-size: 11px;
      font-family: 'JetBrains Mono', monospace;
    }

    .account-visibility-dropdown .dropdown-toggle {
      border: 1px solid rgba(255, 102, 0, 0.35);
      color: #f4f4f4;
      background: rgba(22, 22, 22, 0.95);
      min-height: 42px;
    }

    .account-visibility-dropdown .dropdown-toggle:hover,
    .account-visibility-dropdown .dropdown-toggle:focus {
      border-color: #ff8a2a;
      box-shadow: 0 0 0 0.15rem rgba(255, 102, 0, 0.18);
    }

    .account-visibility-dropdown .dropdown-menu {
      background: #141414;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 10px;
      overflow: hidden;
    }

    .account-visibility-dropdown .dropdown-item {
      color: #f0f0f0;
      font-size: 0.88rem;
      padding: 9px 12px;
    }

    .account-visibility-dropdown .dropdown-item:hover,
    .account-visibility-dropdown .dropdown-item:focus,
    .account-visibility-dropdown .dropdown-item.active {
      background: rgba(255, 102, 0, 0.18);
      color: #fff;
    }

    .profile-visibility-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 1px solid rgba(255, 102, 0, 0.45);
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 11px;
      letter-spacing: .4px;
      text-transform: uppercase;
      color: #ffd8b8;
      background: rgba(255, 102, 0, 0.1);
    }

    .account-section-subtitle {
      color: #b9b9b9;
      font-size: 0.92rem;
      margin: -2px 0 16px;
      line-height: 1.5;
    }

    .account-personal-form .account-field {
      border: 1px solid rgba(255, 102, 0, 0.2);
      border-radius: 12px;
      padding: 14px;
      background: rgba(14, 14, 14, 0.62);
      height: 100%;
    }

    .account-personal-form .form-label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      font-size: 0.74rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #ffd7b0;
      font-family: 'JetBrains Mono', monospace;
    }

    .account-personal-form .form-label i {
      color: #ff9c53;
      font-size: 0.85rem;
    }

    .account-personal-form .account-help {
      min-height: 34px;
      margin-top: 8px;
      font-size: 0.78rem;
      line-height: 1.45;
      display: block;
    }

    .account-personal-form .account-live-feedback {
      margin-top: 6px;
      min-height: 16px;
    }

    @media (max-width: 767.98px) {
      .account-personal-form .account-field {
        padding: 12px;
      }

      .account-personal-form .account-help {
        min-height: 0;
      }
    }
  </style>
</head>

<body class="dark-theme account-page">
  <?php if (!empty($errors)): ?>
    <div id="serverError"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="serverSuccess"><?= htmlspecialchars(implode(' ', $success)) ?></div>
  <?php endif; ?>

  <header>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
      <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="navbar-logo me-2" />
          <span class="brand-text">COMMERZA</span>
        </a>
        <div class="d-flex align-items-center order-lg-2">
          <ul class="navbar-nav ms-3 d-none d-lg-flex flex-row align-items-center me-3">
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" href="cart.php" aria-label="View cart">
                <i class="bi bi-cart3" id="cart-icon"></i>
                <span class="nav-badge" id="cart-count">0</span>
              </a>
            </li>
            <li class="nav-item position-relative me-3">
              <a class="nav-link nav-icon-link" href="wishlist.php" aria-label="View wishlist">
                <i class="bi bi-heart"></i>
                <span class="nav-badge" id="wishlist-count">0</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link nav-icon-link" aria-current="page" href="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Account"><i
                  class="bi bi-person"></i></a>
            </li>
          </ul>
          <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas"
            aria-controls="navbarOffcanvas" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
        </div>
        <div class="collapse navbar-collapse order-lg-1" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="about.php">About</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="contact.php">Contact</a>
            </li>
            <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
            <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="navbarOffcanvas" aria-labelledby="offcanvasNavbarLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasNavbarLabel">
          <img src="frontend/assets/images/logo/commerza-logo.webp" alt="Commerza Logo" loading="lazy"
            class="offcanvas-logo me-2" />
          <span class="brand-text">COMMERZA</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="offcanvas-user-actions">
          <a href="cart.php" class="offcanvas-action-btn">
            <i class="bi bi-cart3"></i>
            <span>Cart</span>
            <span class="offcanvas-badge" id="cart-count-mobile">0</span>
          </a>
          <a href="wishlist.php" class="offcanvas-action-btn">
            <i class="bi bi-heart"></i>
            <span>Wishlist</span>
            <span class="offcanvas-badge" id="wishlist-count-mobile">0</span>
          </a>
          <a href="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" class="offcanvas-action-btn" aria-current="page">
            <i class="bi bi-person"></i>
            <span>Account</span>
          </a>
        </div>
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="about.php">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="contact.php">Contact</a>
          </li>
          <li class="nav-item"><a class="nav-link" href="terms-of-service.php">Terms</a></li>
          <li class="nav-item"><a class="nav-link" href="privacy-policy.php">Privacy</a></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="container my-5">
    <nav class="account-breadcrumb mb-3" aria-label="Breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Account</li>
      </ol>
    </nav>
    <h1 class="mb-4" style="color: #ff6600; user-select: none">My Account</h1>

    <div class="row">
      <div class="col-lg-4 mb-4">
        <div class="card product-card mb-4">
          <div class="card-body text-center">
            <img src="<?= htmlspecialchars($profile_picture) ?>" class="profile-img mb-3" alt="Profile picture"
              loading="lazy" style="user-select: none" id="accountProfileImage" />
            <h3 class="product-name" id="accountProfileName"><?= htmlspecialchars((string)$user['full_name']) ?></h3>
            <p class="product-desc mb-1" id="accountProfileUsername">@<?= htmlspecialchars($username_value) ?></p>
            <p class="product-desc" id="accountProfileEmail"><?= htmlspecialchars((string)$user['email']) ?></p>
            <p class="mb-2">
              <span class="profile-visibility-pill" id="accountProfileVisibility">
                <i class="bi <?= $profile_visibility_value === 'public' ? 'bi-globe2' : 'bi-shield-lock' ?>"></i>
                <?= htmlspecialchars($profile_visibility_label) ?>
              </span>
            </p>

            <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" enctype="multipart/form-data" id="updateProfilePictureForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="update_profile_picture">
              <input type="file" name="profile_picture" id="profilePictureInput" class="form-control search-input mt-3"
                accept="image/jpeg,image/png,image/webp,image/gif" />
              <small class="text-secondary d-block mt-2">Upload a clear square photo. Supported formats: JPG, PNG, WebP, GIF. Max 2 MB. Image is parsed/compressed before save.</small>
              <div class="upload-progress-shell mt-2 d-none" id="profileUploadProgress">
                <small class="text-secondary d-block" data-upload-stage>Waiting to upload...</small>
                <div class="progress mt-1" style="height: 6px;">
                  <div class="progress-bar bg-warning" role="progressbar" data-upload-bar style="width: 0%">0%</div>
                </div>
              </div>
              <button type="submit" class="btn product-btn-buy w-100 mt-2" data-loading-text="Updating Picture...">
                Update Picture
              </button>
            </form>

            <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" id="logoutForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="logout">
              <button type="submit" class="btn product-btn-cart w-100 mt-2" id="logoutBtn" data-loading-text="Logging Out...">Logout</button>
            </form>
          </div>
        </div>

        <div class="card product-card">
          <div class="card-body">
            <h3 class="product-name mb-3">Change Password</h3>

            <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" id="updatePasswordForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="update_password">

              <div class="mb-2">
                <label for="current-password" class="form-label">Current Password</label>
                <div class="password-wrapper">
                  <input type="password" id="current-password" name="current_password" class="form-control search-input"
                    placeholder="Current Password" required autocomplete="current-password" minlength="6"
                    maxlength="20" />
                  <i class="bi bi-eye account-toggle-password" data-target="#current-password" role="button" tabindex="0" aria-label="Toggle current password visibility"></i>
                </div>
                <small class="text-secondary d-block mt-1">Enter the password you currently use to sign in.</small>
              </div>

              <div class="mb-2">
                <label for="new-password" class="form-label">New Password</label>
                <div class="password-wrapper">
                  <input type="password" id="new-password" name="new_password" class="form-control search-input"
                    placeholder="New Password" required autocomplete="new-password" minlength="6" maxlength="20"
                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,}"
                    title="Minimum 6 chars, 1 number, 1 capital, 1 special char" autocapitalize="off" autocorrect="off"
                    spellcheck="false" />
                  <i class="bi bi-eye account-toggle-password" data-target="#new-password" role="button" tabindex="0" aria-label="Toggle new password visibility"></i>
                </div>
                <small class="text-secondary d-block mt-1">Use a stronger password than your previous one.</small>
              </div>

              <div class="mb-3">
                <label for="confirm-password" class="form-label">Confirm New Password</label>
                <div class="password-wrapper">
                  <input type="password" id="confirm-password" name="confirm_password" class="form-control search-input"
                    placeholder="Confirm New Password" required autocomplete="new-password" minlength="6" maxlength="20"
                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,}"
                    title="Minimum 6 chars, 1 number, 1 capital, 1 special char" autocapitalize="off" autocorrect="off"
                    spellcheck="false" />
                  <i class="bi bi-eye account-toggle-password" data-target="#confirm-password" role="button" tabindex="0" aria-label="Toggle confirm password visibility"></i>
                </div>
                <small class="text-secondary d-block mt-1">Re-type your new password exactly to confirm.</small>
              </div>

              <?= commerza_captcha_widget_html($con, 'user_account_password') ?>

              <button type="submit" class="btn product-btn-buy w-100" data-loading-text="Updating Password...">
                Update Password
              </button>
            </form>
          </div>
        </div>

        <div class="card product-card mt-4">
          <div class="card-body">
            <h3 class="product-name mb-2 text-danger">Delete Account</h3>
            <p class="product-desc mb-3">This action permanently deletes your profile, saved lists, sessions, and linked account data. This cannot be undone.</p>

            <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="mb-3">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="request_delete_account_code">
              <?= commerza_captcha_widget_html($con, 'user_account_delete_request') ?>
              <button type="submit" class="btn product-btn-cart w-100 mt-3" data-loading-text="Sending Code...">
                Send Deletion Code
              </button>
            </form>

            <?php if (is_array($accountDeletePending)): ?>
              <?php if ($accountDeleteCodeExpiresIn > 0): ?>
                <p class="text-warning small mb-2">Current deletion code expires in <?= (int)ceil($accountDeleteCodeExpiresIn / 60) ?> minute(s).</p>
              <?php endif; ?>
              <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="delete_account_permanently">

                <div class="mb-2">
                  <label for="delete-account-password" class="form-label">Current Password</label>
                  <input
                    type="password"
                    id="delete-account-password"
                    name="delete_account_password"
                    class="form-control search-input"
                    autocomplete="current-password"
                    required>
                  <small class="text-secondary d-block mt-1">For security, enter your current account password.</small>
                </div>

                <div class="mb-2">
                  <label for="delete-account-code" class="form-label">Deletion Code</label>
                  <input
                    type="text"
                    id="delete-account-code"
                    name="delete_account_code"
                    class="form-control search-input"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    minlength="6"
                    autocomplete="one-time-code"
                    required>
                  <small class="text-secondary d-block mt-1">Enter the 6-digit deletion code sent to your email.</small>
                </div>

                <?= commerza_captcha_widget_html($con, 'user_account_delete_confirm') ?>

                <button
                  type="submit"
                  class="btn w-100 mt-3"
                  style="background:#9e1f1f;color:#fff;border:1px solid #d24b4b;"
                  data-loading-text="Deleting Account...">
                  Permanently Delete My Account
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card product-card mb-4">
          <div class="card-body">
            <h4 class="product-name mb-3">Personal Information</h4>
            <p class="account-section-subtitle">Keep your profile details accurate so checkout, delivery, and account security work smoothly.</p>

            <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" id="updateProfileForm" class="account-personal-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="update_profile">

              <div class="row g-3">
                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="full-name" class="form-label"><i class="bi bi-person-badge"></i><span>Full Name</span></label>
                    <input type="text" id="full-name" name="full_name" class="form-control search-input"
                      value="<?= htmlspecialchars((string)$user['full_name']) ?>" required
                      autocomplete="name" minlength="3" maxlength="40"
                      pattern="[A-Za-z][A-Za-z\s\.\'\-]{2,39}"
                      title="Use 3-40 letters with spaces, dots, apostrophes, or hyphens." />
                    <small class="account-help text-secondary">Use your real name for invoices and delivery records.</small>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="username" class="form-label"><i class="bi bi-at"></i><span>Username</span></label>
                    <input type="text" id="username" name="username" class="form-control search-input"
                      value="<?= htmlspecialchars($username_value) ?>" required
                      autocomplete="username" minlength="3" maxlength="24"
                      pattern="[a-zA-Z][a-zA-Z0-9_]{2,23}"
                      title="Use 3-24 characters: letters, numbers, underscore." />
                    <div id="usernameLiveFeedback" class="account-live-feedback" aria-live="polite"></div>
                    <small class="account-help text-secondary">This username may appear publicly in reviews when profile is public.</small>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="email" class="form-label"><i class="bi bi-envelope"></i><span>Email Address</span></label>
                    <input type="email" id="email" name="email" class="form-control search-input"
                      value="<?= htmlspecialchars((string)$user['email']) ?>" required
                      autocomplete="email" maxlength="150" />
                    <div id="emailLiveFeedback" class="account-live-feedback" aria-live="polite"></div>
                    <small class="account-help text-secondary">Use an active email to receive security and order updates.</small>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="profile-visibility" class="form-label"><i class="bi bi-shield-check"></i><span>Profile Visibility</span></label>
                    <input type="hidden" id="profile-visibility" name="profile_visibility" value="<?= htmlspecialchars($profile_visibility_value) ?>" required>
                    <div class="dropdown account-visibility-dropdown">
                      <button class="btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between"
                        type="button" id="profileVisibilityMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-inline-flex align-items-center gap-2">
                          <i class="bi <?= $profile_visibility_value === 'public' ? 'bi-globe2' : 'bi-shield-lock' ?>" id="profileVisibilityIcon"></i>
                          <span id="profileVisibilityLabel"><?= htmlspecialchars($profile_visibility_label) ?></span>
                        </span>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-dark w-100 border-secondary" aria-labelledby="profileVisibilityMenu">
                        <li>
                          <button type="button" class="dropdown-item profile-visibility-option <?= $profile_visibility_value === 'private' ? 'active' : '' ?>" data-value="private" data-label="Private Profile" data-icon="bi-shield-lock">
                            <i class="bi bi-shield-lock me-2"></i>Private Profile
                          </button>
                        </li>
                        <li>
                          <button type="button" class="dropdown-item profile-visibility-option <?= $profile_visibility_value === 'public' ? 'active' : '' ?>" data-value="public" data-label="Public Profile" data-icon="bi-globe2">
                            <i class="bi bi-globe2 me-2"></i>Public Profile
                          </button>
                        </li>
                      </ul>
                    </div>
                    <small class="account-help text-secondary">Public profile shows your username in public-facing areas (like reviews). Private keeps identity generic.</small>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="phone" class="form-label"><i class="bi bi-telephone"></i><span>Phone Number</span></label>
                    <input type="tel" id="phone" name="phone" class="form-control search-input"
                      value="<?= htmlspecialchars((string)$user['phone']) ?>" required
                      autocomplete="tel" minlength="11" maxlength="15"
                      pattern="\d{11,15}" title="Enter 11 to 15 digits only." />
                    <div id="phoneLiveFeedback" class="account-live-feedback" aria-live="polite"></div>
                    <small class="account-help text-secondary">Digits only. Include your area/mobile code without symbols.</small>
                  </div>
                </div>

                <div class="col-md-12">
                  <div class="account-field">
                    <label for="address" class="form-label"><i class="bi bi-geo-alt"></i><span>Address</span></label>
                    <textarea id="address" name="address" class="form-control search-input" rows="3" maxlength="255"
                      minlength="8" placeholder="Enter your address"><?= htmlspecialchars((string)($user['address'] ?? '')) ?></textarea>
                    <small class="account-help text-secondary">Add complete house, street, city details for accurate delivery.</small>
                  </div>
                </div>

                <div class="col-md-12">
                  <div class="account-field">
                    <label class="form-label"><i class="bi bi-map"></i><span>Address Map Preview</span></label>
                    <div class="ratio ratio-16x9 border border-secondary rounded overflow-hidden">
                      <iframe
                        id="address-map-frame"
                        src="https://www.google.com/maps?q=<?= rawurlencode((string)($user['address'] ?: 'Pakistan')) ?>&output=embed"
                        title="Address Map Preview"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        style="border:0;"></iframe>
                    </div>
                    <small class="account-help text-secondary">Map refreshes automatically as you edit your address.</small>
                  </div>
                </div>
              </div>

              <?= commerza_captcha_widget_html($con, 'user_account_profile') ?>

              <button type="submit" class="btn product-btn-buy px-4 mt-2" data-loading-text="Saving Changes...">
                Save Changes
              </button>
            </form>
          </div>
        </div>

        <div class="card product-card">
          <div class="card-body">
            <h4 class="product-name mb-3">My Orders</h4>
            <div id="my-orders-container">
              <?php if (empty($orders)): ?>
                <div class="text-center py-5">
                  <i class="bi bi-bag-x" style="font-size: 3rem; color: #ff6600;"></i>
                  <h3 class="text-white mt-3">No Orders Yet</h3>
                  <p class="text-secondary">You haven't placed any orders. Start shopping now!</p>
                  <a href="index.php" class="btn product-btn-buy mt-3">Start Shopping</a>
                </div>
              <?php else: ?>
                <?php foreach ($orders as $order): ?>
                  <?php
                  $orderId = (int)($order['id'] ?? 0);
                  $status = (string)$order['status'];
                  $orderUserNote = trim((string)($order['notes'] ?? ''));
                  $orderAdminNote = trim((string)($order['admin_note'] ?? ''));
                  $deliveryEstimateRaw = trim((string)($order['delivery_estimate'] ?? ''));
                  $deliveryEstimateTs = $deliveryEstimateRaw !== '' ? strtotime($deliveryEstimateRaw) : false;
                  $deliveryEstimateLabel = $deliveryEstimateTs !== false
                    ? date('d M Y, h:i A', $deliveryEstimateTs)
                    : '';
                  $statusClass = 'warning';

                  if ($status === 'Delivered') {
                    $statusClass = 'success';
                  } elseif ($status === 'Cancelled' || $status === 'Refunded') {
                    $statusClass = 'danger';
                  } elseif ($status === 'Confirmed' || $status === 'Processing' || $status === 'Shipped') {
                    $statusClass = 'info';
                  }

                  $items = [];
                  if (!empty($order['order_items'])) {
                    $items = array_filter(array_map('trim', explode('||', (string)$order['order_items'])));
                  }

                  $refundState = $refundRequestsByOrder[$orderId] ?? null;
                  $refundStatus = strtolower((string)($refundState['status'] ?? ''));
                  $refundAnchor = trim((string)($order['updated_at'] ?? ''));
                  if ($refundAnchor === '') {
                    $refundAnchor = trim((string)($order['created_at'] ?? ''));
                  }

                  $refundAnchorTs = strtotime($refundAnchor);
                  $refundDeadlineTs = $refundAnchorTs !== false ? strtotime('+7 days', $refundAnchorTs) : false;
                  $isWithinRefundWindow = $refundDeadlineTs !== false && $refundDeadlineTs >= time();
                  $isDelivered = strtolower($status) === 'delivered';
                  $canRequestRefund = $isDelivered && $isWithinRefundWindow && !$refundState;

                  $refundBadgeClass = 'warning';
                  if ($refundStatus === 'accepted') {
                    $refundBadgeClass = 'success';
                  } elseif ($refundStatus === 'rejected') {
                    $refundBadgeClass = 'danger';
                  }

                  $refundEvidencePath = trim((string)($refundState['evidence_path'] ?? ''));
                  $refundEvidenceName = trim((string)($refundState['evidence_name'] ?? ''));
                  if ($refundEvidenceName === '') {
                    $refundEvidenceName = 'View refund evidence';
                  }
                  ?>
                  <div class="card product-card mb-3">
                    <div class="card-body">
                      <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div>
                          <h3 class="product-name mb-1"><?= htmlspecialchars((string)$order['order_number']) ?></h3>
                          <p class="product-desc mb-1"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string)$order['created_at']))) ?></p>
                        </div>
                        <div class="text-end">
                          <span class="badge bg-<?= $statusClass ?> rounded-pill"><?= htmlspecialchars($status) ?></span>
                          <p class="text-white fw-bold mt-2"><?= number_format((float)$order['grand_total'], 2) ?> PKR</p>
                        </div>
                      </div>
                      <?php if (!empty($items)): ?>
                        <div class="mt-3">
                          <?php foreach ($items as $item): ?>
                            <div class="text-secondary small mb-1"><?= htmlspecialchars($item) ?></div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                      <?php if ($deliveryEstimateLabel !== '' || $orderUserNote !== '' || $orderAdminNote !== ''): ?>
                        <div class="mt-3 p-3 rounded border border-secondary">
                          <?php if ($deliveryEstimateLabel !== ''): ?>
                            <p class="text-secondary small mb-1"><strong class="text-light">Delivery estimate:</strong> <?= htmlspecialchars($deliveryEstimateLabel) ?></p>
                          <?php endif; ?>
                          <?php if ($orderUserNote !== ''): ?>
                            <p class="text-secondary small mb-1"><strong class="text-light">Your note:</strong> <?= nl2br(htmlspecialchars($orderUserNote), false) ?></p>
                          <?php endif; ?>
                          <?php if ($orderAdminNote !== ''): ?>
                            <p class="text-secondary small mb-0"><strong class="text-light">Admin logistics note:</strong> <?= nl2br(htmlspecialchars($orderAdminNote), false) ?></p>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>

                      <div class="mt-3 pt-3 border-top border-secondary">
                        <p class="text-secondary small mb-2">7-day refund policy applies to delivered orders only.</p>

                        <?php if ($refundState): ?>
                          <p class="mb-2">
                            <span class="badge bg-<?= htmlspecialchars($refundBadgeClass) ?> rounded-pill">
                              Refund <?= htmlspecialchars(ucfirst($refundStatus)) ?>
                            </span>
                          </p>
                          <?php if (!empty($refundState['admin_note'])): ?>
                            <p class="text-secondary small mb-2">Admin note: <?= htmlspecialchars((string)$refundState['admin_note']) ?></p>
                          <?php endif; ?>
                          <?php if ($refundEvidencePath !== ''): ?>
                            <p class="text-secondary small mb-2">
                              Evidence:
                              <a href="<?= htmlspecialchars($refundEvidencePath) ?>" target="_blank" rel="noopener" class="text-warning text-decoration-underline">
                                <?= htmlspecialchars($refundEvidenceName) ?>
                              </a>
                            </p>
                          <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($canRequestRefund): ?>
                          <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" enctype="multipart/form-data" class="mt-2 refund-request-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="request_refund">
                            <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                            <div class="mb-2">
                              <label class="form-label text-light" for="refund-reason-<?= (int)$orderId ?>">Reason for refund</label>
                              <textarea
                                id="refund-reason-<?= (int)$orderId ?>"
                                name="refund_reason"
                                class="form-control search-input"
                                rows="2"
                                minlength="8"
                                maxlength="500"
                                placeholder="Describe the issue with your order"
                                required></textarea>
                            </div>
                            <div class="mb-2">
                              <label class="form-label text-light" for="refund-evidence-<?= (int)$orderId ?>">Upload evidence (optional, max 6MB)</label>
                              <input
                                id="refund-evidence-<?= (int)$orderId ?>"
                                type="file"
                                name="refund_evidence"
                                class="form-control search-input refund-evidence-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
                              <small class="text-secondary d-block mt-1">Allowed: JPG, PNG, WebP, GIF, PDF. Max 6 MB. Images are parsed/compressed before upload.</small>
                              <div class="upload-progress-shell mt-2 d-none" data-upload-progress-shell>
                                <small class="text-secondary d-block" data-upload-stage>Waiting to upload...</small>
                                <div class="progress mt-1" style="height: 6px;">
                                  <div class="progress-bar bg-warning" role="progressbar" data-upload-bar style="width: 0%">0%</div>
                                </div>
                              </div>
                            </div>
                            <button type="submit" class="btn product-btn-cart" data-loading-text="Submitting Refund...">
                              Refund Me
                            </button>
                            <?php if ($refundDeadlineTs !== false): ?>
                              <p class="text-secondary small mt-2 mb-0">Request before <?= htmlspecialchars(date('d M Y, h:i A', $refundDeadlineTs)) ?></p>
                            <?php endif; ?>
                          </form>
                        <?php elseif (!$isDelivered): ?>
                          <p class="text-secondary small mb-0">Refund option will appear after this order is delivered.</p>
                        <?php elseif ($refundState && $refundStatus === 'rejected'): ?>
                          <p class="text-secondary small mb-0">Your previous refund request was reviewed and rejected. New requests are disabled for this order.</p>
                        <?php elseif (!$isWithinRefundWindow && !$refundState): ?>
                          <p class="text-secondary small mb-0">Refund window closed for this order.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="container-fluid">
      <div class="row py-5">
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Commerza</h3>
          <p class="footer-text">
            Premium watches and accessories for the modern lifestyle. Quality
            craftsmanship meets contemporary design.
          </p>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Quick Links</h3>
          <ul class="footer-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="contact.php">Contact</a></li>
            <li><a href="wishlist.php">Wishlist</a></li>
            <li><a href="order-tracking.php">Order Tracking</a></li>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Customer Service</h3>
          <ul class="footer-links">
            <li><a href="shipping.php">Shipping Info</a></li>
            <li><a href="returns.php">Returns</a></li>
            <li><a href="faq.php">FAQ</a></li>
            <li><a href="warranty.php">Warranty</a></li>
            <li><a href="terms-of-service.php">Terms of Service</a></li>
            <li><a href="privacy-policy.php">Privacy Policy</a></li>
          </ul>
        </div>
        <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-4">
          <h3 class="footer-heading">Connect</h3>
          <div class="social-links">
            <a href="https://www.facebook.com/commerza.ahmer" target="_blank" aria-label="Commerza on Facebook"><i
                class="bi bi-facebook"></i></a>
            <a href="https://x.com/commerza_ahmer" target="_blank" aria-label="Commerza on X"><i
                class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/commerza.ahmer" target="_blank" aria-label="Commerza on Instagram"><i
                class="bi bi-instagram"></i></a>
          </div>
          <p class="footer-text mt-3">Email: commerza.ahmer@gmail.com</p>
          <p class="footer-text">Phone: +92 314 8396293</p>
        </div>
      </div>
      <div class="row">
        <div class="col-12 text-center py-3 border-top">
          <p class="footer-copyright">
            &copy; 2026 Commerza. All rights reserved.
          </p>
        </div>
      </div>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js"></script>
  <script src="frontend/assets/js/script.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function() {
      $("#serverError, #serverSuccess").each(function() {
        const element = $(this);
        setTimeout(function() {
          element.fadeOut(400);
        }, 3500);
      });

      $(document).on("click keydown", ".account-toggle-password", function(event) {
        if (event.type === "keydown" && event.key !== "Enter" && event.key !== " ") {
          return;
        }

        event.preventDefault();

        const icon = $(this);
        const target = (icon.attr("data-target") || "").toString().trim();
        if (!target) {
          return;
        }

        const input = $(target).first();
        if (!input.length) {
          return;
        }

        const reveal = input.attr("type") === "password";
        input.attr("type", reveal ? "text" : "password");
        icon.toggleClass("bi-eye", !reveal).toggleClass("bi-eye-slash", reveal);
      });

      const addressInput = $("#address");
      const mapFrame = $("#address-map-frame");
      const refreshAddressMap = function() {
        if (!addressInput.length || !mapFrame.length) {
          return;
        }

        const rawAddress = (addressInput.val() || "").toString().trim();
        const query = rawAddress !== "" ? rawAddress : "Pakistan";
        const mapUrl =
          "https://www.google.com/maps?q=" +
          encodeURIComponent(query) +
          "&output=embed";
        mapFrame.attr("src", mapUrl);
      };

      addressInput.on("input", refreshAddressMap);
      refreshAddressMap();

      const profileForm = $("#updateProfileForm");
      const usernameInput = $("#username");
      const emailInput = $("#email");
      const phoneInput = $("#phone");
      const usernameFeedback = $("#usernameLiveFeedback");
      const emailFeedback = $("#emailLiveFeedback");
      const phoneFeedback = $("#phoneLiveFeedback");
      const visibilityInput = $("#profile-visibility");
      const visibilityLabel = $("#profileVisibilityLabel");
      const visibilityIcon = $("#profileVisibilityIcon");
      const visibilityOptions = $(".profile-visibility-option");
      const profileCsrf = profileForm.find('input[name="csrf_token"]').val() || "";

      let usernameTaken = false;
      let emailTaken = false;
      let phoneTaken = false;
      let usernameTimer = null;
      let emailTimer = null;
      let phoneTimer = null;

      const normalizeUsername = function(value) {
        return (value || "")
          .toString()
          .toLowerCase()
          .replace(/\s+/g, "_")
          .replace(/[^a-z0-9_]/g, "")
          .replace(/_+/g, "_")
          .replace(/^_+|_+$/g, "");
      };

      const normalizeEmail = function(value) {
        return (value || "").toString().trim().toLowerCase();
      };

      const normalizePhone = function(value) {
        return (value || "")
          .toString()
          .replace(/\D+/g, "")
          .slice(0, 15);
      };

      const isValidEmail = function(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) && value.length <= 150;
      };

      const isValidPhone = function(value) {
        return /^\d{11,15}$/.test(value);
      };

      const setLiveFeedback = function(target, message, color) {
        if (!target.length) {
          return;
        }

        target.text(message).css("color", color || "#9ca3af");
      };

      const setFieldState = function(input, feedback, message, color, borderColor) {
        if (input.length) {
          input.css("border-color", borderColor || "");
        }

        setLiveFeedback(feedback, message, color);
      };

      const runExistsCheck = function(field, value, onDone, onFail) {
        $.post("backend/check_exists.php", {
            csrf_token: profileCsrf,
            field,
            value,
            exclude_current: 1,
          })
          .done(function(res) {
            onDone(res || {});
          })
          .fail(function() {
            if (typeof onFail === "function") {
              onFail();
            }
          });
      };

      const applyVisibilityOption = function(rawValue) {
        const value = (rawValue || "").toString().toLowerCase() === "public" ? "public" : "private";
        const option = visibilityOptions.filter(`[data-value="${value}"]`).first();
        const label = (option.attr("data-label") || (value === "public" ? "Public Profile" : "Private Profile")).toString();
        const iconClass = (option.attr("data-icon") || (value === "public" ? "bi-globe2" : "bi-shield-lock")).toString();

        visibilityInput.val(value);
        visibilityLabel.text(label);
        visibilityIcon.attr("class", `bi ${iconClass}`);
        visibilityOptions.removeClass("active");
        option.addClass("active");
      };

      if (visibilityOptions.length && visibilityInput.length) {
        visibilityOptions.on("click", function() {
          applyVisibilityOption($(this).attr("data-value") || "private");
        });

        applyVisibilityOption(visibilityInput.val() || "private");
      }

      if (usernameInput.length && profileForm.length && profileCsrf !== "") {
        usernameInput.on("input", function() {
          const normalized = normalizeUsername(usernameInput.val());
          usernameInput.val(normalized);
          usernameTaken = false;
          clearTimeout(usernameTimer);
          setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");

          if (normalized.length < 3) {
            setLiveFeedback(usernameFeedback, "Use at least 3 characters.", "#9ca3af");
            return;
          }

          usernameTimer = setTimeout(() => {
            runExistsCheck(
              "username",
              normalized,
              function(res) {
                const blocked = !!res?.blocked;
                usernameTaken = !!res?.exists;
                if (usernameTaken) {
                  const blockedMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This username is not allowed." :
                    "Username already taken.";
                  setFieldState(usernameInput, usernameFeedback, blockedMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(usernameInput, usernameFeedback, "Username available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                usernameTaken = false;
                setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");
              },
            );
          }, 420);
        });
      }

      if (emailInput.length && profileForm.length && profileCsrf !== "") {
        emailInput.on("input", function() {
          const normalized = normalizeEmail(emailInput.val());
          emailInput.val(normalized);
          emailTaken = false;
          clearTimeout(emailTimer);
          setFieldState(emailInput, emailFeedback, "", "#9ca3af", "");

          if (normalized === "") {
            setLiveFeedback(emailFeedback, "Email is required.", "#9ca3af");
            return;
          }

          if (!isValidEmail(normalized)) {
            setFieldState(emailInput, emailFeedback, "Enter a valid email address.", "#9ca3af", "");
            return;
          }

          emailTimer = setTimeout(() => {
            runExistsCheck(
              "email",
              normalized,
              function(res) {
                emailTaken = !!res?.exists;
                if (emailTaken) {
                  setFieldState(emailInput, emailFeedback, "Email already in use.", "#ef4444", "#ef4444");
                } else {
                  setFieldState(emailInput, emailFeedback, "Email available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                emailTaken = false;
                setFieldState(emailInput, emailFeedback, "", "#9ca3af", "");
              },
            );
          }, 420);
        });
      }

      if (phoneInput.length && profileForm.length && profileCsrf !== "") {
        phoneInput.on("input", function() {
          const normalized = normalizePhone(phoneInput.val());
          phoneInput.val(normalized);
          phoneTaken = false;
          clearTimeout(phoneTimer);
          setFieldState(phoneInput, phoneFeedback, "", "#9ca3af", "");

          if (normalized === "") {
            setLiveFeedback(phoneFeedback, "Phone number is required.", "#9ca3af");
            return;
          }

          if (!isValidPhone(normalized)) {
            setFieldState(phoneInput, phoneFeedback, "Use 11 to 15 digits.", "#9ca3af", "");
            return;
          }

          phoneTimer = setTimeout(() => {
            runExistsCheck(
              "phone",
              normalized,
              function(res) {
                phoneTaken = !!res?.exists;
                if (phoneTaken) {
                  setFieldState(phoneInput, phoneFeedback, "Phone already in use.", "#ef4444", "#ef4444");
                } else {
                  setFieldState(phoneInput, phoneFeedback, "Phone available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                phoneTaken = false;
                setFieldState(phoneInput, phoneFeedback, "", "#9ca3af", "");
              },
            );
          }, 420);
        });
      }

      if (profileForm.length) {
        profileForm.on("submit", function(event) {
          const normalized = normalizeUsername(usernameInput.val());
          const normalizedEmail = normalizeEmail(emailInput.val());
          const normalizedPhone = normalizePhone(phoneInput.val());

          usernameInput.val(normalized);
          emailInput.val(normalizedEmail);
          phoneInput.val(normalizedPhone);

          if (usernameTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            setFieldState(usernameInput, usernameFeedback, "Choose another username before saving.", "#ef4444", "#ef4444");
            return false;
          }

          if (normalized.length < 3) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            setFieldState(usernameInput, usernameFeedback, "Username must be 3-24 characters.", "#ef4444", "#ef4444");
            return false;
          }

          if (!isValidEmail(normalizedEmail)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            emailInput.focus();
            setFieldState(emailInput, emailFeedback, "Enter a valid email address.", "#ef4444", "#ef4444");
            return false;
          }

          if (emailTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            emailInput.focus();
            setFieldState(emailInput, emailFeedback, "Choose another email before saving.", "#ef4444", "#ef4444");
            return false;
          }

          if (!isValidPhone(normalizedPhone)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            phoneInput.focus();
            setFieldState(phoneInput, phoneFeedback, "Use 11 to 15 digits.", "#ef4444", "#ef4444");
            return false;
          }

          if (phoneTaken) {
            event.preventDefault();
            event.stopImmediatePropagation();
            phoneInput.focus();
            setFieldState(phoneInput, phoneFeedback, "Choose another phone before saving.", "#ef4444", "#ef4444");
            return false;
          }

          return true;
        });
      }

      const updateUploadShell = function(shell, stageText, percent, tone) {
        if (!shell || !shell.length) {
          return;
        }

        const safePercent = Math.max(0, Math.min(100, Math.round(percent || 0)));
        const stage = shell.find("[data-upload-stage]").first();
        const bar = shell.find("[data-upload-bar]").first();

        shell.removeClass("d-none");
        if (stage.length) {
          stage.text((stageText || "Processing upload...").toString());
        }

        if (bar.length) {
          bar.removeClass("bg-warning bg-danger bg-success");
          bar.addClass(tone || "bg-warning");
          bar.css("width", `${safePercent}%`);
          bar.text(`${safePercent}%`);
        }
      };

      const showServerBanner = function(type, message) {
        const safeType = type === "error" ? "error" : "success";
        const safeMessage = (message || "").toString().trim();
        if (!safeMessage) {
          return;
        }

        const targetId = safeType === "error" ? "serverError" : "serverSuccess";
        const fallbackId = safeType === "error" ? "serverSuccess" : "serverError";

        $(`#${fallbackId}`).remove();

        let banner = $(`#${targetId}`);
        if (!banner.length) {
          banner = $("<div>")
            .attr("id", targetId)
            .appendTo("body");
        }

        banner.stop(true, true).text(safeMessage).show();

        window.setTimeout(() => {
          banner.fadeOut(400);
        }, 3500);
      };

      const applyProfilePictureResponse = function(payload) {
        const picturePath = (payload?.profile_picture || "").toString().trim();
        if (picturePath !== "") {
          const cacheBustedPath = `${picturePath}${picturePath.includes("?") ? "&" : "?"}v=${Date.now()}`;
          $("#accountProfileImage").attr("src", cacheBustedPath);
        }

        const fullName = (payload?.full_name || "").toString().trim();
        if (fullName !== "") {
          $("#accountProfileName").text(fullName);
        }

        const username = (payload?.username || "").toString().trim();
        if (username !== "") {
          $("#accountProfileUsername").text(`@${username}`);
        }

        const email = (payload?.email || "").toString().trim();
        if (email !== "") {
          $("#accountProfileEmail").text(email);
        }

        const visibilityLabel = (payload?.profile_visibility_label || "").toString().trim();
        const visibilityIcon = (payload?.profile_visibility_icon || "bi-shield-lock").toString().trim();
        const visibilityPill = $("#accountProfileVisibility");
        if (visibilityPill.length && visibilityLabel !== "") {
          visibilityPill.empty();
          $("<i>")
            .addClass(`bi ${visibilityIcon || "bi-shield-lock"}`)
            .appendTo(visibilityPill);
          visibilityPill.append(document.createTextNode(` ${visibilityLabel}`));
        }
      };

      const bindUploadProgressForm = function(form, fileSelector, resolveShell, options = {}) {
        if (!form || !form.length) {
          return;
        }

        const expectJsonResponse = !!options?.expectJsonResponse;
        const onJsonSuccess =
          typeof options?.onJsonSuccess === "function" ?
          options.onJsonSuccess :
          null;

        form.off("submit.uploadProgress").on("submit.uploadProgress", function(event) {
          const activeForm = $(this);
          const fileInput = activeForm.find(fileSelector).first();
          if (!fileInput.length) {
            return true;
          }

          const file = fileInput[0]?.files?.[0] || null;
          if (!file) {
            const shell = resolveShell(activeForm);
            if (shell && shell.length) {
              shell.addClass("d-none");
            }
            return true;
          }

          event.preventDefault();
          event.stopImmediatePropagation();

          const shell = resolveShell(activeForm);
          updateUploadShell(shell, "Uploading file...", 0, "bg-warning");

          const submitBtn = activeForm.find("button[type='submit']").first();
          const originalText = submitBtn.text();
          const loadingText = (submitBtn.data("loading-text") || "Uploading...").toString();
          submitBtn.prop("disabled", true).text(loadingText);

          const restoreButton = function() {
            submitBtn.prop("disabled", false).text(originalText);
          };

          const xhr = new XMLHttpRequest();
          xhr.open(
            (activeForm.attr("method") || "POST").toString().toUpperCase(),
            (activeForm.attr("action") || window.location.href).toString(),
            true,
          );
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

          xhr.upload.addEventListener("progress", function(progressEvent) {
            if (!progressEvent.lengthComputable) {
              updateUploadShell(shell, "Uploading file...", 0, "bg-warning");
              return;
            }

            const pct = (progressEvent.loaded / progressEvent.total) * 100;
            updateUploadShell(shell, `Uploading file... ${Math.round(pct)}%`, pct, "bg-warning");
          });

          xhr.upload.addEventListener("load", function() {
            updateUploadShell(
              shell,
              "Upload complete. Server is parsing/compressing...",
              100,
              "bg-warning",
            );
          });

          xhr.onerror = function() {
            updateUploadShell(
              shell,
              "Upload failed due to a network error.",
              100,
              "bg-danger",
            );
            restoreButton();
          };

          xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
              if (expectJsonResponse) {
                let payload = null;
                try {
                  payload = JSON.parse(xhr.responseText || "{}");
                } catch (_error) {
                  payload = null;
                }

                if (!payload || payload.ok !== true) {
                  const jsonError =
                    payload?.message ||
                    (Array.isArray(payload?.errors) && payload.errors.length ?
                      payload.errors[0] :
                      "Upload failed. Please try again.");
                  updateUploadShell(shell, jsonError, 100, "bg-danger");
                  showServerBanner("error", jsonError);
                  restoreButton();
                  return;
                }

                const nextToken = (payload?.csrf_token || "").toString().trim();
                if (nextToken !== "") {
                  $("input[name='csrf_token']").val(nextToken);
                }

                if (onJsonSuccess) {
                  onJsonSuccess(payload, activeForm);
                }

                const successMessage =
                  (payload?.message || "Profile picture updated successfully.")
                  .toString()
                  .trim();
                updateUploadShell(shell, successMessage, 100, "bg-success");
                showServerBanner("success", successMessage);
                restoreButton();

                window.setTimeout(() => {
                  shell.addClass("d-none");
                }, 1400);
                return;
              }

              updateUploadShell(shell, "Upload completed. Refreshing...", 100, "bg-success");
              document.open();
              document.write(xhr.responseText || "");
              document.close();
              return;
            }

            updateUploadShell(
              shell,
              "Upload failed. Please try again.",
              100,
              "bg-danger",
            );
            restoreButton();
          };

          const formData = new FormData(activeForm[0]);
          if (expectJsonResponse) {
            formData.set("ajax", "1");
          }
          xhr.send(formData);
          return false;
        });
      };

      bindUploadProgressForm(
        $("#updateProfilePictureForm"),
        "#profilePictureInput",
        function() {
          return $("#profileUploadProgress");
        }, {
          expectJsonResponse: true,
          onJsonSuccess: applyProfilePictureResponse,
        },
      );

      $(".refund-request-form").each(function() {
        const form = $(this);
        bindUploadProgressForm(form, ".refund-evidence-input", function(activeForm) {
          return activeForm.find("[data-upload-progress-shell]").first();
        });
      });

      $("form").on("submit", function() {
        const btn = $(this).find("button[type='submit']").first();
        if (!btn.length || btn.prop("disabled")) {
          return;
        }
        const loadingText = btn.data("loading-text");
        btn.prop("disabled", true);
        if (loadingText) {
          btn.text(loadingText);
        }
      });
    });
  </script>
</body>

</html>