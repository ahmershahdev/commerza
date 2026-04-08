<?php
include "backend/data.php";
require_once __DIR__ . '/backend/notifications.php';
require_once __DIR__ . '/backend/mailer.php';
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
$showAccountForgotPasswordPanel = false;

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
  $stmt = $con->prepare("SELECT id, full_name, username, username_slug, profile_visibility, email, phone, address, profile_picture, password_hash, username_changed_at FROM users WHERE id = ? LIMIT 1");

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

const ACCOUNT_USERNAME_CHANGE_LOCK_DAYS = 90;
const ACCOUNT_USERNAME_CHANGE_LOCK_SECONDS = ACCOUNT_USERNAME_CHANGE_LOCK_DAYS * 86400;

function account_username_change_lock_state(?string $usernameChangedAt): array
{
  $raw = trim((string)$usernameChangedAt);
  if ($raw === '') {
    return [
      'locked' => false,
      'remaining_seconds' => 0,
      'remaining_days' => 0,
      'unlock_timestamp' => 0,
    ];
  }

  $changedAtTimestamp = strtotime($raw);
  if ($changedAtTimestamp === false) {
    return [
      'locked' => false,
      'remaining_seconds' => 0,
      'remaining_days' => 0,
      'unlock_timestamp' => 0,
    ];
  }

  $unlockTimestamp = $changedAtTimestamp + ACCOUNT_USERNAME_CHANGE_LOCK_SECONDS;
  $remainingSeconds = $unlockTimestamp - time();
  if ($remainingSeconds <= 0) {
    return [
      'locked' => false,
      'remaining_seconds' => 0,
      'remaining_days' => 0,
      'unlock_timestamp' => 0,
    ];
  }

  return [
    'locked' => true,
    'remaining_seconds' => $remainingSeconds,
    'remaining_days' => (int)ceil($remainingSeconds / 86400),
    'unlock_timestamp' => $unlockTimestamp,
  ];
}

function account_send_reset_code_email(string $recipientEmail, string $recipientName, string $code, ?string &$errorMessage = null): bool
{
  $subject = "Commerza Password Reset Code";
  $resetUrl = commerza_absolute_url('/reset-password.php') . '?email=' . urlencode($recipientEmail);
  $supportEmail = trim((string)(getenv('COMMERZA_SUPPORT_EMAIL') ?: 'support@ahmershah.dev'));
  if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    $supportEmail = 'support@ahmershah.dev';
  }

  $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Customer', ENT_QUOTES, 'UTF-8');
  $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

  $message = "<!DOCTYPE html>
<html>
  <body style=\"margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;\">
    <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#080808;padding:24px 0;\">
      <tr>
        <td align=\"center\">
          <table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;\">
            <tr>
              <td align=\"center\" style=\"padding:28px 20px 10px 20px;\">
                <h1 style=\"margin:0;color:#ff6600;font-size:24px;letter-spacing:1px;\">Password Reset</h1>
              </td>
            </tr>
            <tr>
              <td style=\"padding:20px 30px 30px 30px;\">
                <p style=\"margin:0 0 12px 0;line-height:1.6;color:#d7d7d7;\">Hello {$safeName},</p>
                <p style=\"margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;\">Use the code below to reset your Commerza account password. This code expires in <strong>15 minutes</strong>.</p>
                <div style=\"margin:18px 0 20px 0;padding:14px 18px;background:#0b0b0b;border:1px dashed #ff6600;border-radius:8px;text-align:center;\">
                  <span style=\"font-size:28px;font-weight:700;letter-spacing:6px;color:#ffcc00;\">{$safeCode}</span>
                </div>
                <p style=\"margin:0 0 18px 0;line-height:1.6;color:#bfbfbf;\">Open this page to complete reset:</p>
                <p style=\"margin:0 0 10px 0;word-break:break-all;\"><a href=\"{$resetUrl}\" style=\"color:#ff6600;text-decoration:none;\">{$resetUrl}</a></p>
                <p style=\"margin:18px 0 0 0;line-height:1.6;color:#8f8f8f;font-size:13px;\">If you did not request this, you can ignore this email.</p>
                <p style=\"margin:16px 0 0 0;line-height:1.6;color:#9f9f9f;font-size:12px;\">Support: <a href=\"mailto:{$supportEmail}\" style=\"color:#ffb066;text-decoration:none;\">{$supportEmail}</a></p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>";

  return commerza_send_html_mail(
    $recipientEmail,
    $subject,
    $message,
    $supportEmail,
    'Commerza Security',
    $errorMessage
  );
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

  $scanReason = null;
  if (!commerza_upload_scan_file($tmpPath, $scanReason)) {
    $errors[] = $scanReason !== null ? $scanReason : 'Refund evidence failed security scan.';
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
  $showAccountForgotPasswordPanel = in_array($action, ['request_password_reset_code', 'reset_password_with_code'], true);

  $captchaContexts = [
    'update_profile' => 'user_account_profile',
    'update_password' => 'user_account_password',
    'request_password_reset_code' => 'user_account_forgot_password',
    'reset_password_with_code' => 'user_account_reset_password',
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
    'request_password_reset_code' => ['scope' => 'user_account_forgot_password', 'max' => 4, 'window' => 2700, 'block' => 2700],
    'reset_password_with_code' => ['scope' => 'user_account_reset_password', 'max' => 6, 'window' => 1800, 'block' => 1800],
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
    $usernameChangedFlag = 0;

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
      $freshUsernameStmt = $con->prepare('SELECT username, username_slug, username_changed_at FROM users WHERE id = ? LIMIT 1');
      if (!$freshUsernameStmt) {
        $errors[] = 'Something went wrong. Please try again.';
      } else {
        $freshUsernameStmt->bind_param('i', $user_id);
        $freshUsernameStmt->execute();
        $freshUsernameResult = $freshUsernameStmt->get_result();
        $freshUsernameRow = $freshUsernameResult ? $freshUsernameResult->fetch_assoc() : null;
        $freshUsernameStmt->close();

        if (!is_array($freshUsernameRow)) {
          $errors[] = 'Something went wrong. Please try again.';
        } else {
          $currentUsername = commerza_username_slug((string)($freshUsernameRow['username_slug'] ?? ''));
          if (!commerza_username_is_valid($currentUsername)) {
            $currentUsername = commerza_username_slug((string)($freshUsernameRow['username'] ?? ''));
          }

          $usernameChangedFlag = strcasecmp($currentUsername, $username) !== 0 ? 1 : 0;

          if ($usernameChangedFlag === 1) {
            $usernameLock = account_username_change_lock_state((string)($freshUsernameRow['username_changed_at'] ?? ''));
            if ((bool)($usernameLock['locked'] ?? false)) {
              $remainingDays = max(1, (int)($usernameLock['remaining_days'] ?? 1));
              $unlockTimestamp = max(0, (int)($usernameLock['unlock_timestamp'] ?? 0));
              $unlockLabel = $unlockTimestamp > 0 ? date('M d, Y h:i A', $unlockTimestamp) : '';
              $errors[] = 'Username is locked for ' . ACCOUNT_USERNAME_CHANGE_LOCK_DAYS . ' days after each change. Try again in ' . $remainingDays . ' day(s)' . ($unlockLabel !== '' ? ' (after ' . $unlockLabel . ').' : '.');
            }
          }
        }
      }
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

    if (empty($errors)) {
      $blockedContact = commerza_customer_blacklist_lookup($con, $email, $phone);
      if (is_array($blockedContact)) {
        $errors[] = commerza_customer_blacklist_feedback_message($blockedContact);
      }
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
  } elseif ($action === 'request_password_reset_code') {
    $freshUser = fetchUser($con, $user_id);
    $accountEmail = strtolower(trim((string)($freshUser['email'] ?? '')));
    $emailFromRequest = strtolower(trim((string)($_POST['forgot_password_email'] ?? '')));

    if (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL) || strlen($accountEmail) > 150) {
      $errors[] = 'Your account email is invalid. Please update profile email first.';
    }

    if ($emailFromRequest !== '' && strcasecmp($emailFromRequest, $accountEmail) !== 0) {
      $errors[] = 'Reset code can only be sent to your current account email.';
    }

    $lastEmailSentAt = (int)($_SESSION['account_forgot_password_last_sent_at'] ?? 0);
    $lastEmailTarget = (string)($_SESSION['account_forgot_password_last_sent_email'] ?? '');
    if (empty($errors) && $lastEmailTarget === $accountEmail && (time() - $lastEmailSentAt) < 60) {
      $errors[] = 'Please wait 60 seconds before requesting another code.';
    }

    if (empty($errors) && !$freshUser) {
      $errors[] = 'User not found.';
    }

    if (empty($errors) && $freshUser) {
      $resetCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $tokenHash = commerza_password_hash($resetCode);
      $expiry = date('Y-m-d H:i:s', time() + (15 * 60));

      $updateStmt = $con->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ? LIMIT 1');
      if (!$updateStmt) {
        $errors[] = 'Unable to process request right now.';
      } else {
        $updateStmt->bind_param('ssi', $tokenHash, $expiry, $user_id);
        $updated = $updateStmt->execute();
        $updateStmt->close();

        if (!$updated) {
          $errors[] = 'Unable to generate reset code. Please try again.';
        } else {
          $mailError = null;
          $mailSent = account_send_reset_code_email(
            $accountEmail,
            (string)($freshUser['full_name'] ?? ''),
            $resetCode,
            $mailError
          );

          if ($mailSent) {
            commerza_security_log_event($con, [
              'event_type' => 'password_reset_code_sent_from_account',
              'severity' => 'info',
              'actor_type' => 'user',
              'actor_identifier' => $accountEmail,
              'user_id' => $user_id,
              'ip_address' => $clientIp,
            ]);

            $_SESSION['account_forgot_password_last_sent_at'] = time();
            $_SESSION['account_forgot_password_last_sent_email'] = $accountEmail;
            $success[] = 'Reset code sent to your account email. Use it below to set a new password.';
          } else {
            commerza_security_log_event($con, [
              'event_type' => 'password_reset_email_send_failed_from_account',
              'severity' => 'warning',
              'actor_type' => 'user',
              'actor_identifier' => $accountEmail,
              'user_id' => $user_id,
              'ip_address' => $clientIp,
              'details' => [
                'mail_error' => $mailError ?? '',
              ],
            ]);

            $errors[] = $mailError ?: 'Unable to send reset code email right now.';
          }
        }
      }
    }
  } elseif ($action === 'reset_password_with_code') {
    $resetCode = trim((string)($_POST['reset_code'] ?? ''));
    $newPassword = (string)($_POST['recovery_new_password'] ?? '');
    $confirmPassword = (string)($_POST['recovery_confirm_password'] ?? '');

    if (!preg_match('/^\d{6}$/', $resetCode)) {
      $errors[] = 'Reset code must be 6 digits.';
    }

    $passwordPolicyError = null;
    if (!commerza_password_validate($newPassword, $passwordPolicyError)) {
      $errors[] = $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description();
    }

    if ($newPassword !== $confirmPassword) {
      $errors[] = 'Passwords do not match.';
    }

    $resetUserStmt = $con->prepare('SELECT id, email, password_hash, reset_token, reset_token_expiry FROM users WHERE id = ? LIMIT 1');
    if (!$resetUserStmt) {
      $errors[] = 'Something went wrong. Please try again.';
    } else {
      $resetUserStmt->bind_param('i', $user_id);
      $resetUserStmt->execute();
      $resetUserResult = $resetUserStmt->get_result();
      $resetUserRow = $resetUserResult ? $resetUserResult->fetch_assoc() : null;
      $resetUserStmt->close();

      if (!is_array($resetUserRow)) {
        $errors[] = 'User not found.';
      } elseif (commerza_password_verify($newPassword, (string)($resetUserRow['password_hash'] ?? ''))) {
        $errors[] = 'New password must be different from current password.';
      }
    }

    if (empty($errors) && is_array($resetUserRow ?? null)) {
      $isValidCode = false;
      $storedResetToken = (string)($resetUserRow['reset_token'] ?? '');
      $expiryRaw = (string)($resetUserRow['reset_token_expiry'] ?? '');
      $expiryTs = strtotime($expiryRaw);

      if ($storedResetToken !== '' && $expiryTs !== false && $expiryTs >= time()) {
        $isValidCode = commerza_password_verify($resetCode, $storedResetToken);
      }

      if (!$isValidCode) {
        $errors[] = 'Invalid or expired reset code.';
      } else {
        $newHash = commerza_password_hash($newPassword);
        $updateStmt = $con->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ? AND reset_token = ? AND reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW() LIMIT 1');

        if (!$updateStmt) {
          $errors[] = 'Something went wrong. Please try again.';
        } else {
          $updateStmt->bind_param('sis', $newHash, $user_id, $storedResetToken);
          $updated = $updateStmt->execute();
          $affectedRows = $updated ? (int)$updateStmt->affected_rows : 0;
          $updateStmt->close();

          if (!$updated) {
            $errors[] = 'Unable to reset password right now.';
          } elseif ($affectedRows !== 1) {
            $errors[] = 'Reset code was already used or expired. Request a new code.';
          } else {
            commerza_security_log_event($con, [
              'event_type' => 'password_reset_success_from_account',
              'severity' => 'info',
              'actor_type' => 'user',
              'actor_identifier' => (string)($resetUserRow['email'] ?? ''),
              'user_id' => $user_id,
              'ip_address' => $clientIp,
            ]);

            $success[] = 'Password reset successful. Your new password is now active.';
          }
        }
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
          $scanReason = null;
          if (!commerza_upload_scan_file($tmp_path, $scanReason)) {
            $errors[] = $scanReason !== null ? $scanReason : "Upload failed security scan.";
          }

          if (empty($errors)) {
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
                $conversion = commerza_media_convert_upload_to_webp($tmp_path, $mime, 220, 1400, true);
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
                      if (is_file($destination)) {
                        @unlink($destination);
                      }
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
                        if (is_file($destination)) {
                          @unlink($destination);
                        }
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
          $insertErrno = (int)$insertRefund->errno;
          $insertRefund->close();

          if (!$persisted) {
            if ($insertErrno === 1062) {
              $errors[] = 'A refund request for this order was just submitted. Please refresh and check status.';
            } else {
              $errors[] = 'Unable to submit refund request right now.';
            }
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

  $rateResetAllowedActions = [
    'update_profile',
    'update_password',
    'update_profile_picture',
    'request_refund',
    'request_delete_account_code',
    'delete_account_permanently',
  ];

  if (empty($errors) && $rateScopeUsed !== '' && in_array($action, $rateResetAllowedActions, true)) {
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

$usernameChangeLockState = account_username_change_lock_state((string)($user['username_changed_at'] ?? ''));
$usernameChangeLockActive = (bool)($usernameChangeLockState['locked'] ?? false);
$usernameChangeLockRemainingDays = max(0, (int)($usernameChangeLockState['remaining_days'] ?? 0));
$usernameChangeLockUnlockTs = max(0, (int)($usernameChangeLockState['unlock_timestamp'] ?? 0));
$usernameChangeLockUnlockLabel = $usernameChangeLockUnlockTs > 0
  ? date('M d, Y h:i A', $usernameChangeLockUnlockTs)
  : '';
$usernameChangeLockMessage = $usernameChangeLockActive
  ? 'Username is locked for ' . ACCOUNT_USERNAME_CHANGE_LOCK_DAYS . ' days after each change. You can update it again in ' . $usernameChangeLockRemainingDays . ' day(s)' . ($usernameChangeLockUnlockLabel !== '' ? ' (after ' . $usernameChangeLockUnlockLabel . ').' : '.')
  : '';

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

    .upload-progress-shell {
      border: 1px solid rgba(255, 140, 64, 0.34);
      border-radius: 12px;
      padding: 10px 12px;
      background:
        radial-gradient(circle at 0% 0%, rgba(255, 153, 51, 0.15), transparent 42%),
        linear-gradient(140deg, rgba(17, 17, 17, 0.94), rgba(8, 8, 8, 0.96));
      box-shadow:
        0 12px 24px rgba(0, 0, 0, 0.34),
        inset 0 0 0 1px rgba(255, 255, 255, 0.03);
      position: relative;
      overflow: hidden;
    }

    .upload-progress-shell::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(255, 255, 255, 0.08), transparent 40%);
      opacity: 0;
      transition: opacity 0.2s ease;
      pointer-events: none;
    }

    .upload-progress-shell.is-active::after {
      opacity: 1;
    }

    .upload-progress-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
    }

    .upload-progress-percent {
      font-family: 'JetBrains Mono', monospace;
      font-size: 0.74rem;
      color: #ffd6ad;
      border: 1px solid rgba(255, 170, 122, 0.4);
      border-radius: 999px;
      padding: 2px 7px;
      letter-spacing: 0.05em;
      white-space: nowrap;
    }

    .upload-progress-track {
      height: 8px !important;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 999px;
      overflow: hidden;
    }

    .upload-progress-bar {
      transition: width 0.2s ease;
      font-size: 0;
      background: linear-gradient(90deg, #ff8f36, #ffd35c) !important;
    }

    .upload-progress-shell.upload-state-processing .upload-progress-bar {
      background: linear-gradient(90deg, #ff9f1a, #ffe27a) !important;
    }

    .upload-progress-shell.upload-state-success .upload-progress-bar {
      background: linear-gradient(90deg, #25c96b, #82efad) !important;
    }

    .upload-progress-shell.upload-state-error .upload-progress-bar {
      background: linear-gradient(90deg, #ff516d, #ff9bb0) !important;
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

    .username-lock-label {
      margin-top: 8px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      border: 1px solid rgba(255, 171, 99, 0.45);
      border-radius: 10px;
      padding: 8px 10px;
      background: rgba(62, 27, 15, 0.55);
      color: #ffd7b5;
      font-size: 0.74rem;
      line-height: 1.45;
    }

    .username-lock-label i {
      color: #ffca8f;
      margin-top: 1px;
    }

    .account-recovery-shell {
      margin-top: 12px;
      border: 1px solid rgba(255, 145, 81, 0.3);
      border-radius: 12px;
      padding: 12px;
      background:
        radial-gradient(circle at 100% 0%, rgba(255, 180, 128, 0.1), transparent 40%),
        linear-gradient(160deg, rgba(14, 14, 14, 0.86), rgba(10, 10, 10, 0.9));
    }

    .account-recovery-shell .recovery-divider {
      border-color: rgba(255, 173, 109, 0.24);
      margin: 12px 0;
    }

    .account-recovery-shell .recovery-title {
      color: #ffe2c5;
      font-size: 0.84rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 8px;
      font-family: 'JetBrains Mono', monospace;
    }

    .account-recovery-shell .recovery-help {
      color: #c9b39d;
      font-size: 0.78rem;
      line-height: 1.45;
      margin-bottom: 10px;
    }

    .account-recovery-toggle {
      border: 1px solid rgba(255, 161, 95, 0.44);
      background: linear-gradient(132deg,
          rgba(255, 130, 40, 0.2),
          rgba(255, 84, 64, 0.16)) !important;
      color: #ffe0bf !important;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 0.76rem;
    }

    .account-recovery-toggle:hover,
    .account-recovery-toggle:focus {
      border-color: rgba(255, 205, 153, 0.72);
      color: #fff2df !important;
      box-shadow: 0 0 0 0.15rem rgba(255, 131, 45, 0.18);
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
                <div class="upload-progress-head">
                  <small class="text-secondary d-block mb-0" data-upload-stage>Waiting to upload...</small>
                  <span class="upload-progress-percent" data-upload-percent>0%</span>
                </div>
                <div class="progress mt-1 upload-progress-track">
                  <div class="progress-bar upload-progress-bar bg-warning" role="progressbar" data-upload-bar style="width: 0%"></div>
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

            <button
              type="button"
              class="btn account-recovery-toggle w-100 mt-3"
              data-bs-toggle="collapse"
              data-bs-target="#accountForgotPasswordPanel"
              aria-expanded="<?= $showAccountForgotPasswordPanel ? 'true' : 'false' ?>"
              aria-controls="accountForgotPasswordPanel">
              Forgot Current Password?
            </button>

            <div class="collapse<?= $showAccountForgotPasswordPanel ? ' show' : '' ?>" id="accountForgotPasswordPanel">
              <div class="account-recovery-shell">
                <p class="recovery-help mb-2">If you do not remember your current password, request a secure reset code for your account email, then set a new password here.</p>

                <p class="recovery-title mb-1">Step 1: Send Reset Code</p>
                <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="mb-2">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="request_password_reset_code">

                  <div class="mb-2">
                    <label for="forgot-password-email" class="form-label">Account Email</label>
                    <input
                      type="email"
                      id="forgot-password-email"
                      name="forgot_password_email"
                      class="form-control search-input"
                      value="<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>"
                      maxlength="150"
                      required>
                    <small class="text-secondary d-block mt-1">Code will only be sent to your current account email.</small>
                  </div>

                  <?= commerza_captcha_widget_html($con, 'user_account_forgot_password') ?>

                  <button type="submit" class="btn product-btn-buy w-100 mt-2" data-loading-text="Sending Code...">
                    Send Reset Code
                  </button>
                </form>

                <hr class="recovery-divider">

                <p class="recovery-title mb-1">Step 2: Set New Password</p>
                <form action="<?= htmlspecialchars($accountCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="mb-2">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="action" value="reset_password_with_code">

                  <div class="mb-2">
                    <label for="account-reset-code" class="form-label">6-digit Reset Code</label>
                    <input
                      type="text"
                      id="account-reset-code"
                      name="reset_code"
                      class="form-control search-input"
                      inputmode="numeric"
                      pattern="[0-9]{6}"
                      minlength="6"
                      maxlength="6"
                      placeholder="Enter 6-digit code"
                      required>
                  </div>

                  <div class="mb-2">
                    <label for="account-recovery-new-password" class="form-label">New Password</label>
                    <input
                      type="password"
                      id="account-recovery-new-password"
                      name="recovery_new_password"
                      class="form-control search-input"
                      minlength="6"
                      maxlength="20"
                      pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,}"
                      title="Minimum 6 chars, 1 number, 1 capital, 1 special char"
                      autocomplete="new-password"
                      required>
                  </div>

                  <div class="mb-2">
                    <label for="account-recovery-confirm-password" class="form-label">Confirm New Password</label>
                    <input
                      type="password"
                      id="account-recovery-confirm-password"
                      name="recovery_confirm_password"
                      class="form-control search-input"
                      minlength="6"
                      maxlength="20"
                      pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,}"
                      title="Minimum 6 chars, 1 number, 1 capital, 1 special char"
                      autocomplete="new-password"
                      required>
                  </div>

                  <?= commerza_captcha_widget_html($con, 'user_account_reset_password') ?>

                  <button type="submit" class="btn product-btn-buy w-100 mt-2" data-loading-text="Resetting Password...">
                    Reset Password With Code
                  </button>
                </form>

                <a href="reset-password.php?email=<?= urlencode((string)$user['email']) ?>" class="btn btn-outline-secondary w-100 mt-1">Open Full Reset Page</a>
              </div>
            </div>
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
                      pattern="[A-Za-z][A-Za-z .'-]{2,39}"
                      title="Use 3-40 letters with spaces, dots, apostrophes, or hyphens." />
                    <small class="account-help text-secondary">Use your real name for invoices and delivery records.</small>
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="account-field h-100">
                    <label for="username" class="form-label"><i class="bi bi-at"></i><span>Username</span></label>
                    <input type="text" id="username" name="username" class="form-control search-input"
                      value="<?= htmlspecialchars($username_value) ?>" required
                      data-current-username="<?= htmlspecialchars($username_value, ENT_QUOTES, 'UTF-8') ?>"
                      data-username-lock-active="<?= $usernameChangeLockActive ? '1' : '0' ?>"
                      data-username-lock-message="<?= htmlspecialchars($usernameChangeLockMessage, ENT_QUOTES, 'UTF-8') ?>"
                      autocomplete="username" minlength="3" maxlength="24"
                      pattern="[a-zA-Z][a-zA-Z0-9_]{2,23}"
                      title="Use 3-24 characters: letters, numbers, underscore." />
                    <div id="usernameLiveFeedback" class="account-live-feedback" aria-live="polite"></div>
                    <?php if ($usernameChangeLockActive): ?>
                      <div class="username-lock-label" role="note" aria-label="Username change cooldown information">
                        <i class="bi bi-lock-fill"></i>
                        <span><?= htmlspecialchars($usernameChangeLockMessage, ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                    <?php endif; ?>
                    <small class="account-help text-secondary">
                      This username may appear publicly in reviews when profile is public. Username updates are limited to once every <?= (int)ACCOUNT_USERNAME_CHANGE_LOCK_DAYS ?> days.
                    </small>
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
                                <div class="upload-progress-head">
                                  <small class="text-secondary d-block mb-0" data-upload-stage>Waiting to upload...</small>
                                  <span class="upload-progress-percent" data-upload-percent>0%</span>
                                </div>
                                <div class="progress mt-1 upload-progress-track">
                                  <div class="progress-bar upload-progress-bar bg-warning" role="progressbar" data-upload-bar style="width: 0%"></div>
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
  <script src="frontend/assets/js/global-protection.js" defer></script>
  <script src="frontend/assets/js/script.js" defer></script>
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
      let usernameLocked = false;
      let usernameLockFromServer = false;
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

      const lockActiveFlag = (usernameInput.attr("data-username-lock-active") || "") === "1";
      const usernameLockMessage = (usernameInput.attr("data-username-lock-message") || "").toString().trim();
      const currentUsername = normalizeUsername(
        (usernameInput.attr("data-current-username") || usernameInput.val() || "").toString()
      );

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

      const updateUsernameLockState = function(value) {
        const normalized = normalizeUsername(value);
        const isChangeAttempt = normalized !== "" && normalized !== currentUsername;
        usernameLocked = (lockActiveFlag || usernameLockFromServer) && isChangeAttempt;

        if (usernameLocked) {
          setFieldState(
            usernameInput,
            usernameFeedback,
            usernameLockMessage || "Username can only be changed once every 90 days.",
            "#ef4444",
            "#ef4444"
          );
          return true;
        }

        return false;
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
          usernameLocked = false;
          usernameLockFromServer = false;
          clearTimeout(usernameTimer);
          setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");

          if (updateUsernameLockState(normalized)) {
            return;
          }

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
                usernameLockFromServer = !!res?.lock_active;
                usernameLocked = usernameLockFromServer;
                if (usernameLocked) {
                  usernameTaken = true;
                  const lockMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    (usernameLockMessage || "Username can only be changed once every 90 days.");
                  setFieldState(usernameInput, usernameFeedback, lockMessage, "#ef4444", "#ef4444");
                  return;
                }

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
                usernameLocked = false;
                usernameLockFromServer = false;
                setFieldState(usernameInput, usernameFeedback, "", "#9ca3af", "");
              }
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
                const blocked = !!res?.blocked;
                emailTaken = !!res?.exists;
                if (emailTaken) {
                  const emailMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This email is blocked by admin." :
                    "Email already in use.";
                  setFieldState(emailInput, emailFeedback, emailMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(emailInput, emailFeedback, "Email available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                emailTaken = false;
                setFieldState(emailInput, emailFeedback, "", "#9ca3af", "");
              }
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
                const blocked = !!res?.blocked;
                phoneTaken = !!res?.exists;
                if (phoneTaken) {
                  const phoneMessage =
                    typeof res?.message === "string" && res.message.trim() !== "" ?
                    res.message :
                    blocked ?
                    "This phone number is blocked by admin." :
                    "Phone already in use.";
                  setFieldState(phoneInput, phoneFeedback, phoneMessage, "#ef4444", "#ef4444");
                } else {
                  setFieldState(phoneInput, phoneFeedback, "Phone available.", "#22c55e", "#22c55e");
                }
              },
              function() {
                phoneTaken = false;
                setFieldState(phoneInput, phoneFeedback, "", "#9ca3af", "");
              }
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

          const lockTriggered = updateUsernameLockState(normalized);
          if (lockTriggered || usernameLocked) {
            event.preventDefault();
            event.stopImmediatePropagation();
            usernameInput.focus();
            return false;
          }

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
        const percentChip = shell.find("[data-upload-percent]").first();

        shell.removeClass("upload-state-upload upload-state-processing upload-state-success upload-state-error");
        shell.addClass("is-active");

        if (tone === "bg-success") {
          shell.addClass("upload-state-success");
        } else if (tone === "bg-danger") {
          shell.addClass("upload-state-error");
        } else if (safePercent >= 95) {
          shell.addClass("upload-state-processing");
        } else {
          shell.addClass("upload-state-upload");
        }

        shell.removeClass("d-none");
        if (stage.length) {
          stage.text((stageText || "Processing upload...").toString());
        }

        if (percentChip.length) {
          percentChip.text(`${safePercent}%`);
        }

        if (bar.length) {
          bar.removeClass("bg-warning bg-danger bg-success");
          if (tone) {
            bar.addClass(tone);
          }
          bar.css("width", `${safePercent}%`);
        }
      };

      const animateUploadShell = function(shell, stageText, targetPercent, tone, options = {}) {
        if (!shell || !shell.length) {
          return;
        }

        const safeTarget = Math.max(0, Math.min(100, Math.round(targetPercent || 0)));
        const duration = Math.max(120, parseInt(options?.duration, 10) || 360);
        const current = parseInt(shell.attr("data-progress-current") || "0", 10) || 0;

        if (safeTarget <= current) {
          updateUploadShell(shell, stageText, safeTarget, tone);
          shell.attr("data-progress-current", String(safeTarget));
          return;
        }

        const startedAt = Date.now();

        const tick = function() {
          const elapsed = Date.now() - startedAt;
          const progressRatio = Math.max(0, Math.min(1, elapsed / duration));
          const eased = 1 - Math.pow(1 - progressRatio, 3);
          const next = Math.max(current, Math.round(current + (safeTarget - current) * eased));

          updateUploadShell(shell, stageText, next, tone);
          shell.attr("data-progress-current", String(next));

          if (next < safeTarget && progressRatio < 1) {
            window.requestAnimationFrame(tick);
          }
        };

        window.requestAnimationFrame(tick);
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
          if (shell && shell.length) {
            shell.attr("data-progress-current", "0");
          }
          animateUploadShell(shell, "Uploading file...", 4, "bg-warning", {
            duration: 180,
          });

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
            true
          );
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

          xhr.upload.addEventListener("progress", function(progressEvent) {
            if (!progressEvent.lengthComputable) {
              animateUploadShell(shell, "Uploading file...", 60, "bg-warning", {
                duration: 260,
              });
              return;
            }

            const pct = (progressEvent.loaded / progressEvent.total) * 78;
            const rounded = Math.max(4, Math.round(pct));
            animateUploadShell(shell, `Uploading file... ${rounded}%`, rounded, "bg-warning", {
              duration: 180,
            });
          });

          xhr.upload.addEventListener("load", function() {
            animateUploadShell(
              shell,
              "Upload complete. Parsing and compressing image...",
              90,
              "bg-warning", {
                duration: 420,
              }
            );
          });

          xhr.onerror = function() {
            animateUploadShell(
              shell,
              "Upload failed due to a network error.",
              100,
              "bg-danger", {
                duration: 220,
              }
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
                  animateUploadShell(shell, jsonError, 100, "bg-danger", {
                    duration: 240,
                  });
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
                animateUploadShell(shell, "Finalizing profile update...", 98, "bg-warning", {
                  duration: 240,
                });
                animateUploadShell(shell, successMessage, 100, "bg-success", {
                  duration: 280,
                });
                showServerBanner("success", successMessage);
                restoreButton();

                window.setTimeout(() => {
                  shell.removeClass("is-active");
                  shell.addClass("d-none");
                }, 1400);
                return;
              }

              animateUploadShell(shell, "Upload completed. Refreshing...", 100, "bg-success", {
                duration: 220,
              });
              document.open();
              document.write(xhr.responseText || "");
              document.close();
              return;
            }

            animateUploadShell(
              shell,
              "Upload failed. Please try again.",
              100,
              "bg-danger", {
                duration: 240,
              }
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
        }
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