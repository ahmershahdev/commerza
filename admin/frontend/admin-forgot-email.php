<?php
require_once __DIR__ . '/../backend/auth/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
  http_response_code(500);
  exit('Service unavailable.');
}

$errors = [];
$success = '';
$newEmailValue = '';
$confirmEmailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'admin_forgot_email');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  $clientIp = admin_get_client_ip();

  $resetKey = trim((string)($_POST['reset_key'] ?? ''));
  $newEmailValue = strtolower(trim((string)($_POST['new_admin_email'] ?? '')));
  $confirmEmailValue = strtolower(trim((string)($_POST['confirm_admin_email'] ?? '')));

  if ($resetKey === '' || !hash_equals(admin_get_reset_key($con), $resetKey)) {
    $errors[] = 'Invalid reset key.';
  }

  if (!filter_var($newEmailValue, FILTER_VALIDATE_EMAIL) || strlen($newEmailValue) > 150) {
    $errors[] = 'Enter a valid email address.';
  }

  if ($newEmailValue !== $confirmEmailValue) {
    $errors[] = 'Emails do not match.';
  }

  $targetAdmin = null;
  if (empty($errors)) {
    if (!empty($_SESSION['admin_user_id'])) {
      $targetAdmin = admin_get_by_id($con, (int)$_SESSION['admin_user_id']);
    }

    if (!$targetAdmin) {
      $targetAdmin = admin_get_primary_admin($con);
    }

    if (!$targetAdmin || (int)($targetAdmin['is_active'] ?? 0) !== 1) {
      $errors[] = 'No active admin account found.';
    }
  }

  if (empty($errors) && $targetAdmin) {
    $rate = commerza_rate_limit_check(
      $con,
      'admin_forgot_email_change',
      (string)($targetAdmin['email'] ?? 'admin'),
      $clientIp,
      5,
      3600,
      1800
    );

    if (!$rate['allowed']) {
      commerza_security_log_rate_limit_block(
        $con,
        'admin_forgot_email_change',
        'admin',
        (string)($targetAdmin['email'] ?? 'admin'),
        $clientIp,
        max(1, (int)$rate['retry_after'])
      );
      $errors[] = 'Too many attempts. Try again in ' . (int)$rate['retry_after'] . ' seconds.';
    }
  }

  if (empty($errors) && $targetAdmin) {
    $adminId = (int)$targetAdmin['id'];

    $duplicateStmt = $con->prepare(
      'SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1'
    );

    if (!$duplicateStmt) {
      $errors[] = 'Something went wrong. Please try again.';
    } else {
      $duplicateStmt->bind_param('si', $newEmailValue, $adminId);
      $duplicateStmt->execute();
      $duplicateResult = $duplicateStmt->get_result();
      $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
      $duplicateStmt->close();

      if ($duplicateRow) {
        $errors[] = 'Email is already in use.';
      }
    }

    if (empty($errors)) {
      $updateStmt = $con->prepare(
        'UPDATE admin_users SET email = ? WHERE id = ? LIMIT 1'
      );

      if (!$updateStmt) {
        $errors[] = 'Something went wrong. Please try again.';
      } else {
        $updateStmt->bind_param('si', $newEmailValue, $adminId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
          $errors[] = 'Something went wrong. Please try again.';
        } else {
          commerza_rate_limit_reset(
            $con,
            'admin_forgot_email_change',
            (string)($targetAdmin['email'] ?? 'admin'),
            $clientIp
          );
          admin_logout_user($con);
          $success = 'Admin email updated. Please log in again.';
          $newEmailValue = '';
          $confirmEmailValue = '';
        }
      }
    }
  }
}

$csrfToken = admin_generate_csrf_token();
$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$forgotEmailCanonicalUrl = admin_public_url('/admin-forgot-email');
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
  <link rel="canonical" href="<?= htmlspecialchars($forgotEmailCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="Admin Forgot Email | Commerza">
  <meta property="og:description" content="Secure recovery flow for Commerza admin account email updates.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($forgotEmailCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>Admin Forgot Email | Commerza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="assets/js/core/admin-config.js"></script>
  <link rel="stylesheet" href="assets/css/pages/admin-forgot-email-inline.css">
</head>

<body class="dark-theme">
  <main class="forgot-card">
    <h1 class="forgot-title">COMMERZA</h1>
    <p class="forgot-subtitle">Admin Email Recovery</p>
    <div class="alert alert-danger py-2 px-3 small <?= empty($errors) ? 'd-none' : '' ?>" id="resetError" role="alert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <div class="alert alert-success py-2 px-3 small <?= $success === '' ? 'd-none' : '' ?>" id="resetSuccess" role="alert"><?= htmlspecialchars($success) ?></div>

    <form action="admin-forgot-email.php" method="POST" id="forgotEmailForm">
      <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
      <div class="mb-3">
        <label for="reset-key" class="form-label">Reset Key</label>
        <input type="password" class="form-control" id="reset-key" name="reset_key" placeholder="Enter secret key"
          required />
      </div>

      <div class="mb-3">
        <label for="new-admin-email" class="form-label">New Admin Email</label>
        <input type="email" class="form-control" id="new-admin-email" name="new_admin_email"
          placeholder="Enter new email" required autocomplete="email" maxlength="150" value="<?= htmlspecialchars($newEmailValue) ?>" />
      </div>

      <div class="mb-3">
        <label for="confirm-admin-email" class="form-label">Confirm Email</label>
        <input type="email" class="form-control" id="confirm-admin-email" name="confirm_admin_email"
          placeholder="Re-enter new email" required autocomplete="email" maxlength="150" value="<?= htmlspecialchars($confirmEmailValue) ?>" />
      </div>

      <?= commerza_captcha_widget_html($con, 'admin_forgot_email') ?>

      <div class="d-grid">
        <button type="submit" class="btn reset-btn" id="forgotEmailSubmitBtn">Reset Email</button>
      </div>

      <div class="forgot-links">
        <p class="mt-3 mb-1">
          <a href="admin-login.php">Back to Login</a>
        </p>
        <p class="mb-0">
          <a href="admin-forgot-password.php">Forgot Password?</a>
        </p>
      </div>
    </form>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?= commerza_captcha_script_tag($con) ?>
    <script src="assets/js/pages/auth/admin-auth-common.js"></script>
    <script src="assets/js/pages/auth/admin-forgot-email.js"></script>

  </main>
</body>

</html>