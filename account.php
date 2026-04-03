<?php
include "backend/data.php";
require_once __DIR__ . '/backend/notifications.php';

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

function fetchUser(mysqli $con, int $user_id): ?array
{
    $stmt = $con->prepare("SELECT id, full_name, email, phone, address, profile_picture, password_hash FROM users WHERE id = ? LIMIT 1");

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

  $filename = 'refund_' . $userId . '_' . $orderId . '_' . bin2hex(random_bytes(10)) . '.' . $extension;
  $absolutePath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
  $relativePath = 'frontend/assets/uploads/refunds/' . $filename;

  if (!move_uploaded_file($tmpPath, $absolutePath)) {
    $errors[] = 'Unable to save refund evidence file.';
    return null;
  }

  return [
    'path' => $relativePath,
    'name' => $originalName,
    'size' => $size,
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

    if ($action === 'logout') {
      commerza_forget_current_remember_token($con);
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    if ($action === 'update_profile') {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
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

            if (!$emailCheck || !$phoneCheck) {
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

                $emailCheck->close();
                $phoneCheck->close();

                if ($emailExists) {
                    $errors[] = "Email is already in use.";
                }

                if ($phoneExists) {
                    $errors[] = "Phone is already in use.";
                }
            }
        }

        if (empty($errors)) {
            $address_value = $address !== '' ? $address : null;
            $stmt = $con->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ? LIMIT 1");

            if (!$stmt) {
                $errors[] = "Something went wrong. Please try again.";
            } else {
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $address_value, $user_id);

                if ($stmt->execute()) {
                    $success[] = "Profile updated successfully.";
                } else {
                    if ((int)$stmt->errno === 1062 || (int)$con->errno === 1062) {
                        $errors[] = "Email or phone is already in use.";
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

        if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&]).{6,20}$/', $new_password)) {
            $errors[] = "Password must be 6-20 chars with uppercase, lowercase, number, and special char.";
        }

        $freshUser = fetchUser($con, $user_id);

        if (!$freshUser) {
            $errors[] = "User not found.";
        } elseif (!password_verify($current_password, (string)$freshUser['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        } elseif (password_verify($new_password, (string)$freshUser['password_hash'])) {
            $errors[] = "New password must be different from current password.";
        }

        if (empty($errors)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
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
                    $mime = $finfo ? finfo_file($finfo, $tmp_path) : '';

                    if ($finfo) {
                        finfo_close($finfo);
                    }

                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif'
                    ];

                    if (!isset($allowed[$mime])) {
                        $errors[] = "Only JPG, PNG, WEBP, and GIF are allowed.";
                    } else {
                        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'users';

                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                            $errors[] = "Failed to create upload directory.";
                        } else {
                            $ext = $allowed[$mime];
                            $filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
                            $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                            $publicPath = 'frontend/assets/images/users/' . $filename;

                            if (!move_uploaded_file($tmp_path, $destination)) {
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
    } else {
        $errors[] = "Invalid request.";
    }

    $user = fetchUser($con, $user_id);

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

$orders = [];
$orderStmt = $con->prepare("SELECT o.id, o.order_number, o.grand_total, o.status, o.created_at, o.updated_at, COALESCE(GROUP_CONCAT(CONCAT(oi.product_name, ' x', oi.quantity) SEPARATOR '||'), '') AS order_items FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? GROUP BY o.id, o.order_number, o.grand_total, o.status, o.created_at, o.updated_at ORDER BY o.created_at DESC");

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Manage your Commerza account, update profile details, and review your order history securely.">
  <meta property="og:title" content="My Account | Commerza">
  <meta property="og:description" content="Manage your Commerza account, update profile details, and review your orders.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/account.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <title>My Account | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/account.php" />
  <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "My Account | Commerza",
      "url": "https://commerza.ahmershah.dev/account.php",
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

    label {
      color: rgb(255, 255, 255);
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
  </style>
</head>

<body class="dark-theme">
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
              <a class="nav-link nav-icon-link" aria-current="page" href="account.php" aria-label="Account"><i
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
          <a href="account.php" class="offcanvas-action-btn" aria-current="page">
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
    <h1 class="mb-4" style="color: #ff6600; user-select: none">My Account</h1>

    <div class="row">
      <div class="col-lg-4 mb-4">
        <div class="card product-card mb-4">
          <div class="card-body text-center">
            <img src="<?= htmlspecialchars($profile_picture) ?>" class="profile-img mb-3" alt="Profile picture"
              loading="lazy" style="user-select: none" id="accountProfileImage" />
            <h3 class="product-name" id="accountProfileName"><?= htmlspecialchars((string)$user['full_name']) ?></h3>
            <p class="product-desc" id="accountProfileEmail"><?= htmlspecialchars((string)$user['email']) ?></p>

            <form action="account.php" method="POST" enctype="multipart/form-data" id="updateProfilePictureForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="update_profile_picture">
              <input type="file" name="profile_picture" id="profilePictureInput" class="form-control search-input mt-3"
                accept="image/jpeg,image/png,image/webp,image/gif" />
              <button type="submit" class="btn product-btn-buy w-100 mt-2" data-loading-text="Updating Picture...">
                Update Picture
              </button>
            </form>

            <form action="account.php" method="POST" id="logoutForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="logout">
              <button type="submit" class="btn product-btn-cart w-100 mt-2" id="logoutBtn" data-loading-text="Logging Out...">Logout</button>
            </form>
          </div>
        </div>

        <div class="card product-card">
          <div class="card-body">
            <h3 class="product-name mb-3">Change Password</h3>

            <form action="account.php" method="POST" id="updatePasswordForm">
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
              </div>

              <button type="submit" class="btn product-btn-buy w-100" data-loading-text="Updating Password...">
                Update Password
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card product-card mb-4">
          <div class="card-body">
            <h4 class="product-name mb-3">Personal Information</h4>

            <form action="account.php" method="POST" id="updateProfileForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="update_profile">

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="full-name" class="form-label">Full Name</label>
                  <input type="text" id="full-name" name="full_name" class="form-control search-input"
                    value="<?= htmlspecialchars((string)$user['full_name']) ?>" required
                    autocomplete="name" minlength="3" maxlength="40"
                    pattern="[A-Za-z][A-Za-z\s\.\'\-]{2,39}"
                    title="Use 3-40 letters with spaces, dots, apostrophes, or hyphens." />
                </div>

                <div class="col-md-6 mb-3">
                  <label for="email" class="form-label">Email Address</label>
                  <input type="email" id="email" name="email" class="form-control search-input"
                    value="<?= htmlspecialchars((string)$user['email']) ?>" required
                    autocomplete="email" maxlength="150" />
                </div>

                <div class="col-md-6 mb-3">
                  <label for="phone" class="form-label">Phone Number</label>
                  <input type="tel" id="phone" name="phone" class="form-control search-input"
                    value="<?= htmlspecialchars((string)$user['phone']) ?>" required
                    autocomplete="tel" minlength="11" maxlength="15"
                    pattern="\d{11,15}" title="Enter 11 to 15 digits only." />
                </div>

                <div class="col-md-12 mb-3">
                  <label for="address" class="form-label">Address</label>
                  <textarea id="address" name="address" class="form-control search-input" rows="3" maxlength="255"
                    minlength="8" placeholder="Enter your address"><?= htmlspecialchars((string)($user['address'] ?? '')) ?></textarea>
                </div>

                <div class="col-md-12 mb-3">
                  <label class="form-label">Address Map Preview</label>
                  <div class="ratio ratio-16x9 border border-secondary rounded overflow-hidden">
                    <iframe
                      id="address-map-frame"
                      src="https://www.google.com/maps?q=<?= rawurlencode((string)($user['address'] ?: 'Pakistan')) ?>&output=embed"
                      title="Address Map Preview"
                      loading="lazy"
                      referrerpolicy="no-referrer-when-downgrade"
                      style="border:0;"></iframe>
                  </div>
                  <small class="text-secondary">Map refreshes automatically as you edit your address.</small>
                </div>
              </div>

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
                          <form action="account.php" method="POST" enctype="multipart/form-data" class="mt-2">
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
                                class="form-control search-input"
                                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script>
    $(function () {
      $("#serverError, #serverSuccess").each(function () {
        const element = $(this);
        setTimeout(function () {
          element.fadeOut(400);
        }, 3500);
      });

      $(document).on("click keydown", ".account-toggle-password", function (event) {
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
      const refreshAddressMap = function () {
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

      $("form").on("submit", function () {
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
