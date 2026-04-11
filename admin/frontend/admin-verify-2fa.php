<?php
require_once __DIR__ . '/../backend/auth/auth.php';
require_once __DIR__ . '/../../backend/helpers/notifications.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
  http_response_code(500);
  exit('Service unavailable.');
}

if (!empty($_SESSION['admin_user_id'])) {
  header('Location: admin-panel.php');
  exit;
}

$pending = admin_get_two_factor_pending_session();
if (!$pending) {
  if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin-panel.php');
    exit;
  }

  header('Location: admin-login.php');
  exit;
}

$errors = [];
$success = '';
$codeValue = '';
$clientIp = admin_get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $pending = admin_get_two_factor_pending_session();
  if (!$pending) {
    if (!empty($_SESSION['admin_user_id'])) {
      header('Location: admin-panel.php');
      exit;
    }

    header('Location: admin-login.php');
    exit;
  }

  $action = strtolower(trim((string)($_POST['action'] ?? 'verify')));

  if (empty($errors) && $action === 'resend') {
    $rate = commerza_rate_limit_check(
      $con,
      'admin_2fa_resend',
      (string)$pending['email'],
      $clientIp,
      3,
      600,
      600
    );

    if (!$rate['allowed']) {
      $retry = max(1, (int)$rate['retry_after']);
      commerza_security_log_rate_limit_block(
        $con,
        'admin_2fa_resend',
        'admin',
        (string)$pending['email'],
        $clientIp,
        $retry
      );
      $errors[] = 'Too many resend requests. Try again in ' . $retry . ' seconds.';
    } else {
      $admin = admin_get_by_id($con, (int)$pending['admin_id']);
      $blockReason = is_array($admin) ? admin_account_block_reason($admin) : 'Verification session expired. Please login again.';
      if (!$admin || $blockReason !== null) {
        admin_clear_two_factor_pending_session();
        $_SESSION['admin_login_error'] = $blockReason !== null ? $blockReason : 'Verification session expired. Please login again.';
        header('Location: admin-login.php');
        exit;
      } else {
        $issueError = null;
        if (!admin_issue_two_factor_challenge($con, $admin, (string)$pending['next'], $issueError)) {
          $errors[] = $issueError ?: 'Unable to resend verification code.';
        } else {
          commerza_rate_limit_reset($con, 'admin_2fa_resend', (string)$pending['email'], $clientIp);
          commerza_security_log_event($con, [
            'event_type' => 'admin_2fa_challenge_resent',
            'severity' => 'info',
            'actor_type' => 'admin',
            'actor_identifier' => (string)$pending['email'],
            'admin_id' => (int)$pending['admin_id'],
            'ip_address' => $clientIp,
          ]);
          $success = 'A new verification code has been sent to your email.';
        }
      }
    }
  } elseif (empty($errors)) {
    $codeValue = admin_normalize_numeric_code((string)($_POST['verification_code'] ?? ''));

    $rate = commerza_rate_limit_check(
      $con,
      'admin_2fa_verify',
      (string)$pending['email'],
      $clientIp,
      8,
      600,
      900
    );

    if (!$rate['allowed']) {
      $retry = max(1, (int)$rate['retry_after']);
      commerza_security_log_rate_limit_block(
        $con,
        'admin_2fa_verify',
        'admin',
        (string)$pending['email'],
        $clientIp,
        $retry
      );
      $errors[] = 'Too many verification attempts. Try again in ' . $retry . ' seconds.';
    } else {
      $verify = admin_verify_two_factor_code($con, (int)$pending['admin_id'], $codeValue);
      if (!(bool)($verify['ok'] ?? false)) {
        commerza_security_log_event($con, [
          'event_type' => 'admin_2fa_verify_failed',
          'severity' => 'warning',
          'actor_type' => 'admin',
          'actor_identifier' => (string)$pending['email'],
          'admin_id' => (int)$pending['admin_id'],
          'ip_address' => $clientIp,
          'details' => [
            'status' => (string)($verify['status'] ?? 'failed'),
          ],
        ]);

        $status = (string)($verify['status'] ?? 'failed');
        if (in_array($status, ['invalid_session', 'expired', 'locked'], true)) {
          admin_clear_two_factor_pending_session();
          $_SESSION['admin_login_error'] = (string)($verify['message'] ?? 'Verification expired. Please login again.');
          header('Location: admin-login.php');
          exit;
        }

        $errors[] = (string)($verify['message'] ?? 'Invalid verification code.');
      } else {
        $adminRow = is_array($verify['admin'] ?? null) ? $verify['admin'] : null;
        if (!$adminRow) {
          $adminRow = admin_get_by_id($con, (int)$pending['admin_id']);
        }

        $adminBlockReason = is_array($adminRow) ? admin_account_block_reason($adminRow) : 'Verification session expired. Please login again.';
        if (!$adminRow || $adminBlockReason !== null) {
          admin_clear_two_factor_pending_session();
          $_SESSION['admin_login_error'] = $adminBlockReason !== null ? $adminBlockReason : 'Verification session expired. Please login again.';
          header('Location: admin-login.php');
          exit;
        } else {
          admin_login_user($con, $adminRow);
          admin_clear_two_factor_pending_session();
          commerza_rate_limit_reset($con, 'admin_2fa_verify', (string)$pending['email'], $clientIp);
          commerza_notify_admin_login(
            $con,
            (string)($adminRow['email'] ?? ''),
            (string)($adminRow['full_name'] ?? ''),
            $clientIp
          );
          commerza_security_log_auth_attempt(
            $con,
            'admin',
            (string)($adminRow['email'] ?? $pending['email']),
            $clientIp,
            true,
            'admin_2fa_verified',
            0,
            (int)$adminRow['id']
          );

          header('Location: ' . admin_safe_redirect_target((string)$pending['next'], 'admin-panel.php'));
          exit;
        }
      }
    }
  }
}

if (!empty($_SESSION['admin_login_error'])) {
  $errors[] = (string)$_SESSION['admin_login_error'];
  unset($_SESSION['admin_login_error']);
}

$csrfToken = admin_generate_csrf_token();
$pending = admin_get_two_factor_pending_session();
if (!$pending) {
  if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin-panel.php');
    exit;
  }

  header('Location: admin-login.php');
  exit;
}

$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$verify2faCanonicalUrl = admin_public_url('/admin-verify-2fa');
$adminOgImageUrl = admin_public_url('/frontend/assets/images/logo/commerza-logo.webp');

function admin_mask_email(string $email): string
{
  $email = strtolower(trim($email));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'your email';
  }

  [$local, $domain] = explode('@', $email, 2);
  $localLength = strlen($local);
  if ($localLength <= 2) {
    $maskedLocal = str_repeat('*', max(1, $localLength));
  } else {
    $maskedLocal = $local[0] . str_repeat('*', max(1, $localLength - 2)) . $local[$localLength - 1];
  }

  return $maskedLocal . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <base href="<?= htmlspecialchars($adminFrontendBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="referrer" content="no-referrer">
  <link rel="canonical" href="<?= htmlspecialchars($verify2faCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="Admin 2FA Verification | Commerza">
  <meta property="og:description" content="Second-factor verification for secure Commerza admin login.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($verify2faCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>Admin 2FA Verification | Commerza</title>
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/pages/admin-verify-2fa-inline.css">
</head>

<body>
  <main class="verify-card">
    <h1 class="title">Admin Verification</h1>
    <p class="subtitle">Enter the 6-digit code sent to <?= htmlspecialchars(admin_mask_email((string)$pending['email'])) ?>.</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger py-2 px-3 small" role="alert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <div class="alert alert-success py-2 px-3 small" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="admin-verify-2fa.php" method="POST" class="mb-2" id="adminVerify2faForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div class="mb-3">
        <label for="verification-code" class="form-label">Verification Code</label>
        <input
          type="text"
          id="verification-code"
          name="verification_code"
          class="form-control"
          inputmode="numeric"
          pattern="[0-9]{6}"
          maxlength="6"
          minlength="6"
          autocomplete="one-time-code"
          required
          value="<?= htmlspecialchars($codeValue) ?>">
      </div>

      <div class="d-grid mb-2">
        <button type="submit" name="action" value="verify" class="btn btn-primary" id="adminVerify2faSubmitBtn">Verify & Login</button>
      </div>
      <button type="submit" name="action" value="resend" formnovalidate class="btn btn-link p-0" id="adminVerify2faResendBtn">Resend code</button>
      <span class="text-secondary px-2">|</span>
      <a href="admin-login.php" class="btn-link">Back to login</a>
    </form>
  </main>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="assets/js/pages/auth/admin-auth-common.js"></script>
  <script src="assets/js/pages/auth/admin-verify-2fa.js"></script>
</body>

</html>