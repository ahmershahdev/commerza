<?php
require_once __DIR__ . '/../backend/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
  http_response_code(500);
  exit('Service unavailable.');
}

$errors = [];
$success = '';
$resetComplete = false;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $action = strtolower(trim((string)($_POST['action'] ?? '')));
  $emailValue = strtolower(trim((string)($_POST['admin_email'] ?? '')));

  $captchaContexts = [
    'send_reset_code' => 'admin_forgot_password_send',
  ];

  if (isset($captchaContexts[$action])) {
    $captchaCheck = commerza_captcha_verify_submission($con, $_POST, (string)$captchaContexts[$action]);
    if (!(bool)$captchaCheck['ok']) {
      $errors[] = (string)$captchaCheck['message'];
    }
  }

  $clientIp = admin_get_client_ip();

  $admin = null;
  if (!in_array($action, ['send_reset_code', 'reset_password'], true)) {
    $errors[] = 'Invalid action.';
  }

  if (empty($errors)) {
    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || strlen($emailValue) > 150) {
      $errors[] = 'Enter a valid admin email address.';
    }
  }

  if (empty($errors)) {
    $admin = admin_get_by_email($con, $emailValue);

    if (!$admin) {
      $errors[] = 'No active admin account found for this email.';
    } else {
      $blockReason = admin_account_block_reason($admin);
      if ($blockReason !== null) {
        $errors[] = $blockReason;
      }
    }
  }

  if (empty($errors) && $action === 'send_reset_code') {
    $rate = commerza_rate_limit_check(
      $con,
      'admin_forgot_password_send',
      $emailValue,
      $clientIp,
      5,
      3600,
      1800
    );

    if (!$rate['allowed']) {
      commerza_security_log_rate_limit_block(
        $con,
        'admin_forgot_password_send',
        'admin',
        $emailValue,
        $clientIp,
        max(1, (int)$rate['retry_after'])
      );
      $errors[] = 'Too many reset requests. Try again in ' . (int)$rate['retry_after'] . ' seconds.';
    }
  }

  if (empty($errors) && $action === 'send_reset_code') {
    $code = (string)random_int(100000, 999999);

    if (!admin_store_reset_code($con, (int)$admin['id'], $code)) {
      $errors[] = 'Could not generate reset code. Please try again.';
    } else {
      $mailError = null;
      $mailSent = admin_send_password_reset_code_email(
        (string)$admin['email'],
        (string)$admin['full_name'],
        $code,
        $mailError
      );

      if (!$mailSent) {
        admin_clear_reset_code($con, (int)$admin['id']);
        $errors[] = $mailError ?: 'Could not send reset email. Please verify server mail settings.';
      } else {
        $success = 'A password reset code has been sent to the specified admin email.';
      }
    }
  }

  if (empty($errors) && $action === 'reset_password') {
    $rate = commerza_rate_limit_check(
      $con,
      'admin_forgot_password_reset',
      $emailValue,
      $clientIp,
      8,
      1800,
      1800
    );

    if (!$rate['allowed']) {
      commerza_security_log_rate_limit_block(
        $con,
        'admin_forgot_password_reset',
        'admin',
        $emailValue,
        $clientIp,
        max(1, (int)$rate['retry_after'])
      );
      $errors[] = 'Too many reset attempts. Try again in ' . (int)$rate['retry_after'] . ' seconds.';
    }
  }

  if (empty($errors) && $action === 'reset_password') {
    $code = admin_normalize_numeric_code((string)($_POST['reset_code'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!preg_match('/^\d{6}$/', $code)) {
      $errors[] = 'Enter a valid 6-digit reset code.';
    }

    if ($newPassword !== $confirmPassword) {
      $errors[] = 'Passwords do not match.';
    }

    $passwordPolicyError = null;
    if (!commerza_password_validate($newPassword, $passwordPolicyError)) {
      $errors[] = $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description();
    }

    if (empty($errors)) {
      $adminId = (int)($admin['id'] ?? 0);
      $verification = admin_verify_reset_code_status($con, $adminId, $code);

      if (!(bool)($verification['ok'] ?? false)) {
        $status = (string)($verification['status'] ?? 'invalid_code');
        $securityReason = 'invalid_or_expired_reset_code';
        $message = 'Incorrect reset code. Please try again.';

        if ($status === 'expired' || $status === 'missing_code') {
          admin_clear_reset_code($con, $adminId);
          $securityReason = 'expired_reset_code';
          $message = 'Reset code expired. Request a new code.';
        } elseif ($status === 'invalid_code_format') {
          $securityReason = 'invalid_code_format';
          $message = 'Enter a valid 6-digit reset code.';
        } elseif ($status === 'server_error') {
          $securityReason = 'verification_server_error';
          $message = 'Unable to verify reset code right now. Please retry.';
        }

        commerza_security_log_event($con, [
          'event_type' => 'admin_password_reset_failed',
          'severity' => 'warning',
          'actor_type' => 'admin',
          'actor_identifier' => (string)($admin['email'] ?? $emailValue),
          'admin_id' => $adminId,
          'ip_address' => $clientIp,
          'details' => [
            'reason' => $securityReason,
            'status' => $status,
          ],
        ]);

        $errors[] = $message;
      }
    }

    if (empty($errors)) {
      $hash = commerza_password_hash($newPassword);
      $updateStmt = $con->prepare(
        'UPDATE admin_users
         SET password_hash = ?
         WHERE id = ?
         LIMIT 1'
      );

      if (!$updateStmt) {
        $errors[] = 'Could not update password. Please try again.';
      } else {
        $adminId = (int)$admin['id'];
        $updateStmt->bind_param('si', $hash, $adminId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
          $errors[] = 'Could not update password. Please try again.';
        } else {
          commerza_security_log_event($con, [
            'event_type' => 'admin_password_reset_success',
            'severity' => 'info',
            'actor_type' => 'admin',
            'actor_identifier' => (string)($admin['email'] ?? $emailValue),
            'admin_id' => $adminId,
            'ip_address' => $clientIp,
          ]);
          admin_clear_reset_code($con, $adminId);
          commerza_rate_limit_reset(
            $con,
            'admin_forgot_password_reset',
            $emailValue,
            $clientIp
          );
          admin_logout_user($con);
          $success = 'Password reset successful. Redirecting to login...';
          $resetComplete = true;
        }
      }
    }
  }
}

$csrfToken = admin_generate_csrf_token();
$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$forgotPasswordCanonicalUrl = admin_public_url('/admin-forgot-password');
$adminOgImageUrl = admin_public_url('/frontend/assets/images/logo/commerza-logo.webp');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <base href="<?= htmlspecialchars($adminFrontendBaseHref, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com; frame-src 'self' https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com; base-uri 'self'; form-action 'self'">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($forgotPasswordCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="Admin Forgot Password | Commerza">
  <meta property="og:description" content="Reset the Commerza admin password using secure verification.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($forgotPasswordCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>Admin Forgot Password | Commerza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/pages/admin-forgot-password-inline.css">
</head>

<body class="dark-theme">
  <main class="forgot-card">
    <h1 class="forgot-title">COMMERZA</h1>
    <p class="forgot-subtitle">Reset your admin password securely</p>

    <div class="alert alert-danger py-2 px-3 small <?= empty($errors) ? 'd-none' : '' ?>" role="alert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <div class="alert alert-success py-2 px-3 small <?= $success === '' ? 'd-none' : '' ?>" role="alert" id="resetSuccessMessage"><?= htmlspecialchars($success) ?></div>

    <form action="admin-forgot-password.php" method="POST" id="sendResetForm" class="mb-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" value="send_reset_code">
      <div class="mb-3">
        <label for="admin-email-send" class="form-label">Admin Email</label>
        <input type="email" class="form-control" id="admin-email-send" name="admin_email" placeholder="Enter admin email" required maxlength="150" autocomplete="email" value="<?= htmlspecialchars($emailValue) ?>">
      </div>
      <?= commerza_captcha_widget_html($con, 'admin_forgot_password_send') ?>
      <div class="d-grid">
        <button type="submit" class="btn reset-btn" id="sendResetCodeBtn">Send Reset Code</button>
      </div>
    </form>

    <hr class="border-secondary my-4">

    <form action="admin-forgot-password.php" method="POST" id="resetPasswordForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="action" value="reset_password">

      <div class="mb-3">
        <label for="admin-email-reset" class="form-label">Admin Email</label>
        <input type="email" class="form-control" id="admin-email-reset" name="admin_email" placeholder="Enter admin email" required maxlength="150" autocomplete="email" value="<?= htmlspecialchars($emailValue) ?>">
      </div>

      <div class="mb-3">
        <label for="reset-code" class="form-label">6-Digit Code</label>
        <input type="text" class="form-control" id="reset-code" name="reset_code" placeholder="Enter reset code" required pattern="\d{6}" maxlength="6" autocomplete="one-time-code">
      </div>

      <div class="mb-3">
        <label for="new-password" class="form-label">New Password</label>
        <div class="password-wrapper">
          <input type="password" class="form-control" id="new-password" name="new_password" placeholder="Enter new password" required minlength="8" maxlength="64" autocomplete="new-password">
          <i class="bi bi-eye password-toggle" data-target="#new-password"></i>
        </div>
      </div>

      <div class="mb-3">
        <label for="confirm-password" class="form-label">Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" class="form-control" id="confirm-password" name="confirm_password" placeholder="Confirm new password" required minlength="8" maxlength="64" autocomplete="new-password">
          <i class="bi bi-eye password-toggle" data-target="#confirm-password"></i>
        </div>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn reset-btn" id="resetPasswordBtn">Update Password</button>
      </div>
    </form>

    <div class="forgot-links">
      <p class="mt-3 mb-1">
        <a href="admin-login.php">Back to Login</a>
      </p>
      <p class="mb-0">
        <a href="admin-forgot-email.php">Forgot Email?</a>
      </p>
    </div>
  </main>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="assets/js/pages/admin-auth-common.js"></script>
  <script src="assets/js/pages/admin-forgot-password.js" data-reset-complete="<?= $resetComplete ? '1' : '0' ?>"></script>
</body>

</html>