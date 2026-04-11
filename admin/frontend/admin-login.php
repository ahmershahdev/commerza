<?php
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../../backend/helpers/notifications.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
  http_response_code(500);
  exit('Service unavailable.');
}

if (!empty($_SESSION['admin_user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: admin-panel.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && admin_get_email_verification_pending_session()) {
  header('Location: admin-verify-email.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && admin_get_two_factor_pending_session()) {
  header('Location: admin-verify-2fa.php');
  exit;
}

$errors = [];
$emailValue = '';
$nextTarget = admin_safe_redirect_target(
  (string)($_GET['next'] ?? ($_POST['next'] ?? '')),
  'admin-panel.php'
);

if (!empty($_SESSION['admin_login_error'])) {
  $errors[] = (string)$_SESSION['admin_login_error'];
  unset($_SESSION['admin_login_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!admin_validate_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Forbidden.');
  }

  $emailValue = strtolower(trim((string)($_POST['admin_email'] ?? '')));
  $password = (string)($_POST['admin_password'] ?? '');

  if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || strlen($emailValue) > 150) {
    $errors[] = 'Invalid email or password.';
  }

  if ($password === '' || strlen($password) > 255) {
    $errors[] = 'Invalid email or password.';
  }

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'admin_login');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  $clientIp = admin_get_client_ip();

  if (empty($errors)) {
    $rate = commerza_rate_limit_check(
      $con,
      'admin_login',
      $emailValue !== '' ? $emailValue : 'admin',
      $clientIp,
      8,
      900,
      900
    );

    if (!$rate['allowed']) {
      commerza_security_log_rate_limit_block(
        $con,
        'admin_login',
        'admin',
        $emailValue !== '' ? $emailValue : 'admin',
        $clientIp,
        max(1, (int)$rate['retry_after'])
      );
      $errors[] = 'Too many login attempts. Try again in ' . (int)$rate['retry_after'] . ' seconds.';
    }
  }

  if (empty($errors)) {
    $admin = admin_get_by_email($con, $emailValue);

    if ($admin && commerza_password_verify($password, (string)$admin['password_hash'])) {
      $blockReason = admin_account_block_reason($admin);
      if ($blockReason !== null) {
        commerza_security_log_auth_attempt(
          $con,
          'admin',
          (string)($admin['email'] ?? $emailValue),
          $clientIp,
          false,
          'blocked_account_state',
          0,
          (int)($admin['id'] ?? 0)
        );
        $errors[] = $blockReason;
      } elseif (!admin_is_email_verified($admin)) {
        $issueError = null;
        if (!admin_issue_email_verification_challenge($con, $admin, $issueError)) {
          $errors[] = $issueError ?: 'Unable to send account verification code. Please try again.';
        } else {
          admin_set_email_verification_pending_session($admin, $nextTarget);
          commerza_rate_limit_reset($con, 'admin_login', $emailValue, $clientIp);
          commerza_security_log_event($con, [
            'event_type' => 'admin_email_verification_challenge_sent',
            'severity' => 'info',
            'actor_type' => 'admin',
            'actor_identifier' => (string)($admin['email'] ?? $emailValue),
            'admin_id' => (int)($admin['id'] ?? 0),
            'ip_address' => $clientIp,
          ]);
          header('Location: admin-verify-email.php');
          exit;
        }
      } else {
        if (commerza_password_needs_rehash((string)$admin['password_hash'])) {
          $rehash = commerza_password_hash($password);
          if ($rehash !== '') {
            $rehashStmt = $con->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ? LIMIT 1');
            if ($rehashStmt) {
              $adminId = (int)$admin['id'];
              $rehashStmt->bind_param('si', $rehash, $adminId);
              $rehashStmt->execute();
              $rehashStmt->close();
            }
          }
        }

        $issueError = null;
        if (admin_issue_two_factor_challenge($con, $admin, $nextTarget, $issueError)) {
          commerza_rate_limit_reset($con, 'admin_login', $emailValue, $clientIp);
          commerza_security_log_event($con, [
            'event_type' => 'admin_2fa_challenge_sent',
            'severity' => 'info',
            'actor_type' => 'admin',
            'actor_identifier' => (string)($admin['email'] ?? $emailValue),
            'admin_id' => (int)$admin['id'],
            'ip_address' => $clientIp,
          ]);
          header('Location: admin-verify-2fa.php');
          exit;
        }

        commerza_security_log_event($con, [
          'event_type' => 'admin_2fa_challenge_failed',
          'severity' => 'warning',
          'actor_type' => 'admin',
          'actor_identifier' => (string)($admin['email'] ?? $emailValue),
          'admin_id' => (int)$admin['id'],
          'ip_address' => $clientIp,
          'details' => [
            'reason' => $issueError ?? 'challenge_issue',
          ],
        ]);

        $errors[] = $issueError ?: 'Unable to send verification code. Please try again.';
      }
    } else {
      commerza_security_log_auth_attempt(
        $con,
        'admin',
        $emailValue !== '' ? $emailValue : 'admin',
        $clientIp,
        false,
        'invalid_credentials',
        0,
        0
      );
      $errors[] = 'Invalid email or password.';
    }
  }
}

$csrfToken = admin_generate_csrf_token();
$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$adminLoginCanonicalUrl = admin_public_url('/admin-login');
$adminOgImageUrl = admin_public_url('/frontend/assets/images/logo/commerza-logo.webp');
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
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://www.recaptcha.net https://challenges.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com; frame-src 'self' https://www.google.com https://www.recaptcha.net https://challenges.cloudflare.com; base-uri 'self'; form-action 'self'">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($adminLoginCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="Admin Login | Commerza">
  <meta property="og:description" content="Secure login portal for the Commerza administration panel.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($adminLoginCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <title>Admin Login | Commerza</title>
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="assets/js/core/admin-config.js"></script>
  <link rel="stylesheet" href="assets/css/pages/admin-login-inline.css">
</head>

<body class="dark-theme">
  <main class="login-card">
    <h1 class="login-title">COMMERZA</h1>
    <p class="login-subtitle">Commerza Admin Portal</p>
    <div class="alert alert-danger py-2 px-3 small <?= empty($errors) ? 'd-none' : '' ?>" id="loginError" role="alert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>

    <form action="admin-login.php" method="POST" id="adminLoginForm">
      <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="next" value="<?= htmlspecialchars($nextTarget) ?>">
      <div class="mb-3">
        <label for="admin-email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="admin-email" name="admin_email" placeholder="Enter your email"
          required autocomplete="email" autofocus maxlength="150" value="<?= htmlspecialchars($emailValue) ?>" />
      </div>

      <div class="mb-3">
        <label for="admin-password" class="form-label">Password</label>
        <div class="password-wrapper">
          <input type="password" class="form-control" id="admin-password" name="admin_password"
            placeholder="Enter your password" required autocomplete="current-password" autocorrect="off"
            autocapitalize="off" spellcheck="false" />

          <button type="button" id="togglePassword" class="toggle-password-btn" aria-label="Show password"
            aria-pressed="false">
            <i class="bi bi-eye" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <?= commerza_captcha_widget_html($con, 'admin_login') ?>

      <div class="d-grid">
        <button type="submit" class="btn login-btn" id="adminLoginSubmitBtn">Login</button>
      </div>

      <div class="login-links">
        <p class="mt-3 mb-1">
          <a href="admin-forgot-password.php">Forgot Password?</a>
        </p>
        <p class="mb-0">
          <a href="admin-forgot-email.php">Forgot Email?</a>
        </p>
      </div>
    </form>
  </main>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="assets/js/pages/auth/admin-auth-common.js"></script>
  <script src="assets/js/pages/auth/admin-login.js"></script>

</body>

</html>