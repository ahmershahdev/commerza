<?php
include "backend/data.php";
require_once "backend/notifications.php";

if (!empty($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: account.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$email_value = '';
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

    $email_value = strtolower(trim((string)($_POST['user_login_email'] ?? '')));
    $password = (string)($_POST['user_login_password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);

    if (!filter_var($email_value, FILTER_VALIDATE_EMAIL) || strlen($email_value) > 150) {
        $errors[] = "Invalid email or password.";
    }

    if ($password === '' || strlen($password) > 255) {
        $errors[] = "Invalid email or password.";
    }

    $clientIp = commerza_client_ip();

    if (empty($errors)) {
      $rate = commerza_rate_limit_check(
        $con,
        'user_login',
        $email_value !== '' ? $email_value : 'anonymous',
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
        $errors[] = "Too many login attempts. Try again in " . $retryMinutes . " minute(s) (" . $retrySeconds . " seconds).";
      }
    }

    if (empty($errors)) {
      $stmt = $con->prepare("SELECT id, full_name, email, password_hash FROM users WHERE email = ? LIMIT 1");

        if (!$stmt) {
            $errors[] = "Something went wrong. Please try again.";
        } else {
            $stmt->bind_param("s", $email_value);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;

            if ($user && password_verify($password, (string)$user['password_hash'])) {
                $stmt->close();
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
              if ($rememberMe) {
                commerza_issue_remember_token($con, (int)$user['id']);
              } else {
                commerza_forget_current_remember_token($con);
              }
              commerza_rate_limit_reset($con, 'user_login', $email_value, $clientIp);
              commerza_notify_user_login(
                $con,
                (int)$user['id'],
                (string)($user['email'] ?? $email_value),
                (string)($user['full_name'] ?? ''),
                $clientIp
              );
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: account.php");
                exit;
            }

            $errors[] = "Invalid email or password.";
            $stmt->close();
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
  <meta name="description" content="Login securely to your Commerza account.">
  <meta property="og:title" content="Login | Commerza">
  <meta property="og:description" content="Secure login page for Commerza customers.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/login.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta http-equiv="Content-Security-Policy"
    content="default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self'">
  <title>Login | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/login.php" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script type="application/ld+json">
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
  <style>
    ::selection {
      background-color: #ff4500;
      color: #ffffff;
    }

    body.dark-theme {
      background-color: #050505;
      color: #d1d1d1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      margin: 0;
      overflow-x: hidden;
      animation: bodyFadeIn 1.5s ease;
    }

    @keyframes bodyFadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .login-card {
      background: linear-gradient(145deg, #1a1a1a 0%, #0a0a0a 100%);
      border: 1px solid rgba(255, 69, 0, 0.2);
      border-radius: 12px;
      padding: 35px;
      width: 100%;
      max-width: 450px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 69, 0, 0.1);
      position: relative;
      animation: reveal-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    @keyframes reveal-up {
      0% {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
        filter: blur(10px);
      }

      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
      }
    }

    .login-title {
      font-family: 'Montserrat', sans-serif;
      font-size: 32px;
      font-weight: 900;
      text-align: center;
      letter-spacing: 4px;
      text-transform: uppercase;
      white-space: nowrap;
      background: linear-gradient(-45deg,
          #000000 0%,
          #ff0000 15%,
          #ff4500 30%,
          #ffffff 45%,
          #ffcc00 60%,
          #ff4500 85%,
          #000000 100%);
      background-size: 200% auto;
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      animation: h1-shine 4s linear infinite;
      margin-bottom: 5px;
      user-select: none;
    }

    @keyframes h1-shine {
      to {
        background-position: 200% center;
      }
    }

    .login-subtitle {
      font-family: 'JetBrains Mono', monospace;
      color: #888;
      text-align: center;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 30px;
    }

    .form-label {
      font-family: 'Montserrat', sans-serif;
      color: #ff4500;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .form-control {
      font-family: 'JetBrains Mono', monospace;
      background-color: #000 !important;
      border: 1px solid rgba(255, 69, 0, 0.3);
      color: #ffffff !important;
      padding: 12px;
      border-radius: 4px;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: #ffcc00;
      box-shadow: 0 0 10px rgba(255, 69, 0, 0.2);
      outline: none;
    }

    .password-wrapper {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;
    }

    #user-login-password {
      padding-right: 40px !important;
    }

    #togglePassword {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #ff4500;
      z-index: 10;
      font-size: 18px;
      line-height: 1;
      transition: all 0.3s ease;
    }

    #togglePassword:hover {
      color: #ffcc00;
    }

    .login-btn {
      background: #000 !important;
      border: 1px solid #ff4500 !important;
      color: #fff !important;
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      padding: 12px !important;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .login-btn:hover:not(:disabled) {
      background: linear-gradient(90deg, #ff4500, #ffcc00) !important;
      color: #000 !important;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(255, 69, 0, 0.4);
    }

    .login-btn:active:not(:disabled) {
      transform: scale(0.95);
    }

    .login-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .oauth-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 18px 0 14px;
      color: #666;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .oauth-divider::before,
    .oauth-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: rgba(255, 69, 0, 0.25);
    }

    .oauth-btn {
      border: 1px solid rgba(255, 69, 0, 0.4) !important;
      background: #101010 !important;
      color: #f0f0f0 !important;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 10px 12px !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .oauth-btn:hover {
      background: #1a1a1a !important;
      color: #fff !important;
      border-color: #ffcc00 !important;
    }

    .oauth-google i {
      color: #ffcc00;
    }

    .oauth-facebook i {
      color: #4a7dff;
    }

    .login-links {
      text-align: center;
      margin-top: 25px;
      width: 100%;
    }

    .login-links p {
      margin-bottom: 8px;
      display: block;
      color: #888;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
    }

    .login-links a {
      color: #ff4500;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      text-decoration: none;
      text-transform: uppercase;
      transition: all 0.3s ease;
      display: inline-block;
      margin-left: 2px;
    }

    .login-links a:hover {
      color: #ffcc00;
      text-decoration: underline;
      text-shadow: 0 0 10px rgba(255, 69, 0, 0.5);
    }

    .form-check-input {
      background-color: #000;
      border-color: rgba(255, 69, 0, 0.4);
    }

    .form-check-input:checked {
      background-color: #ff4500;
      border-color: #ff4500;
    }

    .form-check-label {
      color: #a5a5a5;
      font-size: 13px;
    }

    input::placeholder {
      font-family: 'Inter', sans-serif;
      color: #555 !important;
    }

    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }

    #serverAlert,
    #successAlert {
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
      width: 340px;
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }

    #serverAlert {
      background-color: #ff0000 !important;
    }

    #successAlert {
      background-color: #198754 !important;
    }
  </style>
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
        <label for="user-login-email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="user-login-email" name="user_login_email"
          placeholder="Enter your email" required autocomplete="email" autofocus maxlength="150"
          value="<?= htmlspecialchars($email_value) ?>" />
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

      <div class="d-grid">
        <button type="submit" class="btn login-btn" id="loginSubmitBtn">Login</button>
      </div>
    </form>

    <div class="oauth-divider"><span>or continue with</span></div>
    <div class="d-grid gap-2">
      <a class="btn oauth-btn oauth-google" href="oauth.php?provider=google&amp;mode=login">
        <i class="bi bi-google"></i>
        <span>Google</span>
      </a>
      <a class="btn oauth-btn oauth-facebook" href="oauth.php?provider=facebook&amp;mode=login">
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
  <script src="frontend/assets/js/global-protection.js"></script>
  <script>
    $(function () {
      let submitted = false;

      $("#togglePassword").on("click", function () {
        const input = $("#user-login-password");
        input.attr("type", input.attr("type") === "password" ? "text" : "password");
        $(this).toggleClass("bi-eye bi-eye-slash");
      });

      $("#serverAlert, #successAlert").each(function () {
        const element = $(this);
        setTimeout(function () {
          element.fadeOut(400);
        }, 3500);
      });

      $("#loginForm").on("submit", function () {
        if (submitted) {
          return false;
        }

        submitted = true;
        $("#loginSubmitBtn").prop("disabled", true).text("Signing In...");
      });
    });
  </script>
</body>

</html>
