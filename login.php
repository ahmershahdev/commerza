<?php
include "backend/core/data.php";
require_once "backend/helpers/notifications.php";

if (!empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: account.php");
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$login_identifier = '';
$flash_success = '';

if (!empty($_SESSION['flash_success'])) {
  $flash_success = (string)$_SESSION['flash_success'];
  unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['oauth_error'])) {
  $errors[] = (string)$_SESSION['oauth_error'];
  unset($_SESSION['oauth_error']);
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

  $login_identifier = trim((string)($_POST['user_login_identifier'] ?? ''));
  $normalizedIdentifier = strtolower($login_identifier);
  $usernameCandidate = commerza_username_slug($normalizedIdentifier);
  $isEmailLogin = filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL) && strlen($normalizedIdentifier) <= 150;
  $isUsernameLogin = commerza_username_is_valid($usernameCandidate);
  $password = (string)($_POST['user_login_password'] ?? '');
  $rememberMe = !empty($_POST['remember_me']);

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'user_login');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  if (!$isEmailLogin && !$isUsernameLogin) {
    $errors[] = "Invalid email/username or password.";
  }

  if ($password === '' || strlen($password) > 255) {
    $errors[] = "Invalid email/username or password.";
  }

  $clientIp = commerza_client_ip();

  if (empty($errors)) {
    $rate = commerza_rate_limit_check(
      $con,
      'user_login',
      $normalizedIdentifier !== '' ? $normalizedIdentifier : 'anonymous',
      $clientIp,
      4,
      2700,
      2700,
      14400,
      86400
    );

    if (!$rate['allowed']) {
      $retrySeconds = max(1, (int)$rate['retry_after']);
      $retryMinutes = (int)ceil($retrySeconds / 60);
      commerza_security_log_rate_limit_block(
        $con,
        'user_login',
        'user',
        $normalizedIdentifier !== '' ? $normalizedIdentifier : 'anonymous',
        $clientIp,
        $retrySeconds
      );
      $errors[] = "Too many login attempts. Try again in " . $retryMinutes . " minute(s) (" . $retrySeconds . " seconds).";
    }
  }

  if (empty($errors)) {
    $stmt = null;

    if ($isEmailLogin) {
      $stmt = $con->prepare("SELECT id, full_name, email, phone, password_hash FROM users WHERE email = ? LIMIT 1");
      if ($stmt) {
        $stmt->bind_param("s", $normalizedIdentifier);
      }
    } else {
      $stmt = $con->prepare("SELECT id, full_name, email, phone, password_hash FROM users WHERE username_slug = ? OR username = ? LIMIT 1");
      if ($stmt) {
        $stmt->bind_param("ss", $usernameCandidate, $usernameCandidate);
      }
    }

    if (!$stmt) {
      $errors[] = "Something went wrong. Please try again.";
    } else {
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result ? $result->fetch_assoc() : null;

      if ($user && commerza_password_verify($password, (string)$user['password_hash'])) {
        $blockedContact = commerza_customer_blacklist_lookup(
          $con,
          (string)($user['email'] ?? ''),
          (string)($user['phone'] ?? '')
        );

        if (is_array($blockedContact)) {
          commerza_security_log_event($con, [
            'event_type' => 'login_blocked_blacklist',
            'severity' => 'warning',
            'actor_type' => 'user',
            'actor_identifier' => (string)($user['email'] ?? $normalizedIdentifier),
            'ip_address' => $clientIp,
            'details' => [
              'reason' => 'blacklisted_contact',
              'match' => (string)($blockedContact['match'] ?? ''),
              'blacklist_id' => (int)($blockedContact['id'] ?? 0),
            ],
          ]);

          $errors[] = commerza_customer_blacklist_feedback_message($blockedContact);
          $stmt->close();
          goto login_done;
        }

        $stmt->close();

        $clearResetStmt = $con->prepare('UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ? LIMIT 1');
        if ($clearResetStmt) {
          $userId = (int)$user['id'];
          $clearResetStmt->bind_param('i', $userId);
          $clearResetStmt->execute();
          $clearResetStmt->close();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        if ($rememberMe) {
          commerza_issue_remember_token($con, (int)$user['id']);
        } else {
          commerza_forget_current_remember_token($con);
        }
        commerza_rate_limit_reset($con, 'user_login', $normalizedIdentifier, $clientIp);
        commerza_notify_user_login(
          $con,
          (int)$user['id'],
          (string)($user['email'] ?? $normalizedIdentifier),
          (string)($user['full_name'] ?? ''),
          $clientIp
        );
        commerza_security_log_auth_attempt(
          $con,
          'user',
          (string)($user['email'] ?? $normalizedIdentifier),
          $clientIp,
          true,
          'login_success',
          (int)$user['id'],
          0
        );

        if (commerza_password_needs_rehash((string)$user['password_hash'])) {
          $rehash = commerza_password_hash($password);
          if ($rehash !== '') {
            $rehashStmt = $con->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
            if ($rehashStmt) {
              $userId = (int)$user['id'];
              $rehashStmt->bind_param('si', $rehash, $userId);
              $rehashStmt->execute();
              $rehashStmt->close();
            }
          }
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: account.php");
        exit;
      }

      commerza_security_log_auth_attempt(
        $con,
        'user',
        $normalizedIdentifier !== '' ? $normalizedIdentifier : 'anonymous',
        $clientIp,
        false,
        'invalid_credentials',
        0,
        0
      );
      $errors[] = "Invalid email/username or password.";
      $stmt->close();
    }
  }
}

login_done:
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Login securely to your Commerza account.">
  <meta property="og:title" content="Login | Commerza">
  <meta property="og:description" content="Secure login page for Commerza customers.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/login.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <title>Login | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/login.php" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Login | Commerza",
      "url": "https://commerza.ahmershah.dev/login.php",
      "description": "Secure login page for Commerza users."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/login-inline.css">
</head>

<body class="dark-theme">
  <?php if (!empty($flash_success)): ?>
    <div id="successAlert"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="login-card">
    <h1 class="login-title">COMMERZA</h1>
    <p class="login-subtitle">Login to your account</p>

    <form action="login.php" method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="mb-3">
        <label for="user-login-identifier" class="form-label">Email or Username</label>
        <input type="text" class="form-control" id="user-login-identifier" name="user_login_identifier"
          placeholder="Enter email or username" required autocomplete="username" autofocus maxlength="150"
          value="<?= htmlspecialchars($login_identifier) ?>" />
      </div>

      <div class="mb-3">
        <label for="user-login-password" class="form-label">Password</label>
        <div class="password-wrapper">
          <input type="password" class="form-control" id="user-login-password" name="user_login_password"
            placeholder="Enter your password" required autocomplete="current-password" minlength="6" maxlength="20"
            autocorrect="off" autocapitalize="off" spellcheck="false" />
          <i class="bi bi-eye" id="togglePassword"></i>
        </div>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me" value="1" <?= !empty($_POST['remember_me']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="rememberMe">Remember me for 30 days</label>
      </div>

      <?= commerza_captcha_widget_html($con, 'user_login') ?>

      <div class="d-grid">
        <button type="submit" class="btn login-btn" id="loginSubmitBtn">Login</button>
      </div>
    </form>

    <div class="oauth-divider"><span>or continue with</span></div>
    <div class="d-grid gap-2">
      <a class="btn oauth-btn oauth-google" href="oauth?provider=google&amp;mode=login">
        <i class="bi bi-google"></i>
        <span>Google</span>
      </a>
      <a class="btn oauth-btn oauth-facebook" href="oauth?provider=facebook&amp;mode=login">
        <i class="bi bi-facebook"></i>
        <span>Facebook</span>
      </a>
    </div>

    <div class="login-links">
      <p class="mt-3 mb-1">
        <a href="forgot-password.php">Forgot Password?</a>
      </p>
      <p style="user-select: none">
        Don&apos;t have an account?
        <a href="signup.php">Sign Up</a>
      </p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/modules/core/global-protection.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="frontend/assets/js/pages/login.js"></script>
</body>

</html>
