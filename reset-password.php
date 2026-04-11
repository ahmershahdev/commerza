<?php
include "backend/data.php";

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$email_value = strtolower(trim((string)($_GET['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
  ) {
    http_response_code(403);
    exit("Forbidden.");
  }

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'user_reset_password');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  $email_value = strtolower(trim((string)($_POST['reset_email'] ?? '')));
  $reset_code = trim((string)($_POST['reset_code'] ?? ''));
  $new_password = (string)($_POST['new_password'] ?? '');
  $confirm_password = (string)($_POST['confirm_password'] ?? '');
  $clientIp = commerza_client_ip();

  if (!filter_var($email_value, FILTER_VALIDATE_EMAIL) || strlen($email_value) > 150) {
    $errors[] = "Please enter a valid email address.";
  }

  if (!preg_match('/^\d{6}$/', $reset_code)) {
    $errors[] = "Reset code must be 6 digits.";
  }

  $passwordPolicyError = null;
  if (!commerza_password_validate($new_password, $passwordPolicyError)) {
    $errors[] = $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description();
  }

  if ($new_password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
  }

  if (empty($errors)) {
    $rate = commerza_rate_limit_check(
      $con,
      'user_reset_password',
      $email_value !== '' ? $email_value : 'anonymous',
      $clientIp,
      6,
      1800,
      1800,
      7200,
      86400
    );

    if (!$rate['allowed']) {
      $retrySeconds = max(1, (int)$rate['retry_after']);
      $retryMinutes = (int)ceil($retrySeconds / 60);
      commerza_security_log_rate_limit_block(
        $con,
        'user_reset_password',
        'user',
        $email_value !== '' ? $email_value : 'anonymous',
        $clientIp,
        $retrySeconds
      );
      $errors[] = "Too many reset attempts. Try again in " . $retryMinutes . " minute(s) (" . $retrySeconds . " seconds).";
    }
  }

  if (empty($errors)) {
    $stmt = $con->prepare("SELECT id, reset_token, reset_token_expiry FROM users WHERE email = ? LIMIT 1");

    if (!$stmt) {
      $errors[] = "Something went wrong. Please try again.";
    } else {
      $stmt->bind_param("s", $email_value);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      $isValid = false;
      $storedResetToken = '';
      if ($user && !empty($user['reset_token']) && !empty($user['reset_token_expiry'])) {
        $expiryTs = strtotime((string)$user['reset_token_expiry']);
        if ($expiryTs !== false && $expiryTs >= time()) {
          $storedResetToken = (string)$user['reset_token'];
          $isValid = commerza_password_verify($reset_code, $storedResetToken);
        }
      }

      if (!$isValid) {
        commerza_security_log_event($con, [
          'event_type' => 'password_reset_failed',
          'severity' => 'warning',
          'actor_type' => 'user',
          'actor_identifier' => $email_value,
          'ip_address' => $clientIp,
          'details' => [
            'reason' => 'invalid_or_expired_reset_code',
          ],
        ]);
        $errors[] = "Invalid or expired reset code.";
      } else {
        $password_hash = commerza_password_hash($new_password);
        $updateStmt = $con->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ? AND reset_token = ? AND reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW() LIMIT 1");

        if (!$updateStmt) {
          $errors[] = "Something went wrong. Please try again.";
        } else {
          $userId = (int)$user['id'];
          $updateStmt->bind_param("sis", $password_hash, $userId, $storedResetToken);
          $updated = $updateStmt->execute();
          $affectedRows = $updated ? (int)$updateStmt->affected_rows : 0;
          $updateStmt->close();

          if (!$updated) {
            $errors[] = "Unable to reset password right now.";
          } elseif ($affectedRows !== 1) {
            $errors[] = "Reset code was already used or expired. Request a new code.";
          } else {
            commerza_security_log_event($con, [
              'event_type' => 'password_reset_success',
              'severity' => 'info',
              'actor_type' => 'user',
              'actor_identifier' => $email_value,
              'user_id' => (int)$user['id'],
              'ip_address' => $clientIp,
            ]);
            commerza_rate_limit_reset(
              $con,
              'user_reset_password',
              $email_value !== '' ? $email_value : 'anonymous',
              $clientIp
            );
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['flash_success'] = "Password reset successful. Please login.";
            header("Location: login.php");
            exit;
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Set a new secure password for your Commerza account.">
  <meta property="og:title" content="Reset Password | Commerza">
  <meta property="og:description" content="Securely reset your Commerza account password.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/reset-password.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/reset-password.php" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Reset Password | Commerza",
      "url": "https://commerza.ahmershah.dev/reset-password.php",
      "description": "Set a new password for your Commerza account."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/reset-password-inline.css">
</head>

<body class="dark-theme">
  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <main class="reset-card">
    <h1 class="reset-title">COMMERZA</h1>
    <p class="reset-subtitle">Reset your account password</p>

    <form action="reset-password.php" method="POST" id="resetForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="mb-3">
        <label for="reset-email" class="form-label">Email Address</label>
        <input type="email" id="reset-email" name="reset_email" class="form-control" required maxlength="150"
          placeholder="Enter your email" value="<?= htmlspecialchars($email_value) ?>" autocomplete="email" />
      </div>

      <div class="mb-3">
        <label for="reset-code" class="form-label">Reset Code</label>
        <input type="text" id="reset-code" name="reset_code" class="form-control" required maxlength="6" minlength="6"
          placeholder="6-digit code" />
      </div>

      <div class="mb-3">
        <label for="new-password" class="form-label">New Password</label>
        <div class="password-wrapper">
          <input type="password" id="new-password" name="new_password" class="form-control" required minlength="6"
            maxlength="20" autocomplete="new-password" />
          <i class="bi bi-eye toggle-password" data-target="#new-password" aria-label="Show password" role="button"></i>
        </div>
      </div>

      <div class="mb-3">
        <label for="confirm-password" class="form-label">Confirm Password</label>
        <div class="password-wrapper">
          <input type="password" id="confirm-password" name="confirm_password" class="form-control" required
            minlength="6" maxlength="20" autocomplete="new-password" />
          <i class="bi bi-eye toggle-password" data-target="#confirm-password" aria-label="Show password" role="button"></i>
        </div>
      </div>

      <?= commerza_captcha_widget_html($con, 'user_reset_password') ?>

      <div class="d-grid">
        <button type="submit" class="btn reset-btn" id="resetSubmitBtn">Update Password</button>
      </div>
      <div class="reset-links">
        <p>Remembered it?<a href="login.php">Login</a></p>
        <p>Need a new code?<a href="forgot-password.php">Request Reset Code</a></p>
      </div>
    </form>
  </main>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="frontend/assets/js/pages/reset-password.js"></script>
</body>

</html>