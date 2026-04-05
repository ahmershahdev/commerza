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
$success = [];
$full_name = '';
$email = '';
$phone = '';
$pendingSignup = $_SESSION['signup_pending'] ?? null;

if (isset($_GET['reset_pending']) && $_GET['reset_pending'] === '1') {
  unset($_SESSION['signup_pending']);
  $pendingSignup = null;
}

if (is_array($pendingSignup)) {
  $full_name = (string)($pendingSignup['full_name'] ?? '');
  $email = (string)($pendingSignup['email'] ?? '');
  $phone = (string)($pendingSignup['phone'] ?? '');
}

function signup_generate_verification_code(): string
{
  return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit("Forbidden.");
    }

    $flowAction = (string)($_POST['flow_action'] ?? 'start_signup');

    $captchaContext = '';
    if ($flowAction === 'start_signup') {
      $captchaContext = 'user_signup_start';
    } elseif ($flowAction === 'resend_code') {
      $captchaContext = 'user_signup_resend';
    }

    if ($captchaContext !== '') {
      $captchaCheck = commerza_captcha_verify_submission($con, $_POST, $captchaContext);
      if (!(bool)$captchaCheck['ok']) {
        $errors[] = (string)$captchaCheck['message'];
      }
    }

    if (empty($errors) && ($flowAction === 'verify_signup' || $flowAction === 'resend_code')) {
      $pending = $_SESSION['signup_pending'] ?? null;
      if (!is_array($pending)) {
        $errors[] = 'Signup session expired. Please start again.';
      } else {
        $full_name = (string)($pending['full_name'] ?? '');
        $email = strtolower(trim((string)($pending['email'] ?? '')));
        $phone = (string)($pending['phone'] ?? '');
        $clientIp = commerza_client_ip();

        $verifyScope = $flowAction === 'resend_code' ? 'user_signup_resend' : 'user_signup_verify';
        $verifyIdentifier = $email !== '' ? $email : 'anonymous';
        $verifyMax = $flowAction === 'resend_code' ? 4 : 10;
        $verifyWindow = $flowAction === 'resend_code' ? 3600 : 1800;
        $verifyRate = commerza_rate_limit_check(
          $con,
          $verifyScope,
          $verifyIdentifier,
          $clientIp,
          $verifyMax,
          $verifyWindow,
          $verifyWindow,
          7200,
          86400
        );

        if (!$verifyRate['allowed']) {
          $retrySeconds = max(1, (int)$verifyRate['retry_after']);
          $retryMinutes = (int)ceil($retrySeconds / 60);
          commerza_security_log_rate_limit_block(
            $con,
            $verifyScope,
            'user',
            $verifyIdentifier,
            $clientIp,
            $retrySeconds
          );
          $errors[] = 'Too many verification attempts. Try again in ' . $retryMinutes . ' minute(s) (' . $retrySeconds . ' seconds).';
        }

        if (!empty($errors)) {
          $pendingSignup = $_SESSION['signup_pending'] ?? null;
          goto signup_end;
        }

        $expiresAt = (int)($pending['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
          unset($_SESSION['signup_pending']);
          $errors[] = 'Verification code expired. Please sign up again.';
        } elseif ($flowAction === 'resend_code') {
          $lastResendAt = (int)($pending['last_resend_at'] ?? 0);
          if ($lastResendAt > 0 && (time() - $lastResendAt) < 45) {
            $wait = max(1, 45 - (time() - $lastResendAt));
            $errors[] = 'Please wait ' . $wait . ' second(s) before requesting a new code.';
          }

          if (empty($errors)) {
          $newCode = signup_generate_verification_code();
          $pending['code_hash'] = hash('sha256', $newCode);
          $pending['expires_at'] = time() + 600;
          $pending['last_resend_at'] = time();
          $_SESSION['signup_pending'] = $pending;
                commerza_notify_signup_verification_code($con, $email, $full_name, $newCode);
          $success[] = 'A new verification code was sent to your email.';
          }
        } else {
          $verificationCode = trim((string)($_POST['verification_code'] ?? ''));
          if (!preg_match('/^\d{6}$/', $verificationCode)) {
            $errors[] = 'Enter the 6-digit verification code.';
          } else {
            $codeHash = hash('sha256', $verificationCode);
            if (!hash_equals((string)($pending['code_hash'] ?? ''), $codeHash)) {
              $pending['attempts'] = (int)($pending['attempts'] ?? 0) + 1;
              if ((int)$pending['attempts'] >= 6) {
                unset($_SESSION['signup_pending']);
                $errors[] = 'Too many invalid attempts. Please sign up again.';
              } else {
                $_SESSION['signup_pending'] = $pending;
                $errors[] = 'Invalid verification code.';
              }
            } else {
              $password_hash = (string)($pending['password_hash'] ?? '');
              if ($password_hash === '') {
                $errors[] = 'Signup session invalid. Please start again.';
                unset($_SESSION['signup_pending']);
              }

              if (empty($errors)) {
                $stmt = $con->prepare("INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");

                if (!$stmt) {
                  $errors[] = 'Something went wrong. Please try again.';
                } else {
                  $stmt->bind_param("ssss", $full_name, $email, $phone, $password_hash);

                  if ($stmt->execute()) {
                    $stmt->close();
                    unset($_SESSION['signup_pending']);
                    $rateIdentifier = $email !== '' ? $email : 'anonymous';
                    $signupClientIp = commerza_client_ip();
                    commerza_rate_limit_reset($con, 'user_signup', $rateIdentifier, $signupClientIp);
                    commerza_rate_limit_reset($con, 'user_signup_verify', $rateIdentifier, $signupClientIp);
                    commerza_rate_limit_reset($con, 'user_signup_resend', $rateIdentifier, $signupClientIp);
                    commerza_notify_signup_success($con, $email, $full_name);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['flash_success'] = 'Email verified. Account created successfully. Please login.';
                    header('Location: login.php');
                    exit;
                  }

                  if ((int)$stmt->errno === 1062 || (int)$con->errno === 1062) {
                    commerza_security_log_event($con, [
                      'event_type' => 'signup_duplicate_conflict',
                      'severity' => 'warning',
                      'actor_type' => 'user',
                      'actor_identifier' => $email !== '' ? $email : $phone,
                      'ip_address' => commerza_client_ip(),
                      'details' => [
                        'reason' => 'duplicate_email_or_phone_during_verify',
                      ],
                    ]);
                    $errors[] = 'Email or phone already exists.';
                    unset($_SESSION['signup_pending']);
                  } else {
                    $errors[] = 'Something went wrong. Please try again.';
                  }

                  $stmt->close();
                }
              }
            }
          }
        }
      }
    } elseif (empty($errors)) {
      $full_name = trim((string)($_POST['user_full_name'] ?? ''));
      $email = strtolower(trim((string)($_POST['user_signup_email'] ?? '')));
      $phone = preg_replace('/\s+/', '', trim((string)($_POST['user_signup_phone'] ?? '')));
      $phone = $phone ?? '';
      $password = (string)($_POST['signup_create_password'] ?? '');
      $confirm = (string)($_POST['signup_confirm_password'] ?? '');

      if (
        strlen($full_name) < 3 ||
        strlen($full_name) > 40 ||
        !preg_match("/^[A-Za-z][A-Za-z\\s\\.\\'\\-]{2,39}$/", $full_name)
      ) {
        $errors[] = 'Full name must be 3-40 valid letters.';
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Invalid email address.';
      }

      if (!preg_match('/^\d{11,15}$/', $phone)) {
        $errors[] = 'Invalid phone number.';
      }

      if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
      }

      $passwordPolicyError = null;
      if (!commerza_password_validate($password, $passwordPolicyError)) {
        $errors[] = $passwordPolicyError !== null ? $passwordPolicyError : commerza_password_policy_description();
      }

      $clientIp = commerza_client_ip();
      if (empty($errors)) {
        $rateIdentifier = $email !== '' ? $email : ($phone !== '' ? $phone : 'anonymous');
        $rate = commerza_rate_limit_check(
        $con,
        'user_signup',
        $rateIdentifier,
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
          'user_signup',
          'user',
          $rateIdentifier,
          $clientIp,
          $retrySeconds
        );
        $errors[] = 'Too many signup attempts. Try again in ' . $retryMinutes . ' minute(s) (' . $retrySeconds . ' seconds).';
        }
      }

      if (empty($errors)) {
        $dupStmt = $con->prepare("SELECT email, phone FROM users WHERE email = ? OR phone = ? LIMIT 2");

        if (!$dupStmt) {
          $errors[] = 'Something went wrong. Please try again.';
        } else {
          $dupStmt->bind_param("ss", $email, $phone);
          $dupStmt->execute();
          $dupResult = $dupStmt->get_result();

          $emailTaken = false;
          $phoneTaken = false;

          if ($dupResult) {
            while ($row = $dupResult->fetch_assoc()) {
              if (isset($row['email']) && strcasecmp((string)$row['email'], $email) === 0) {
                $emailTaken = true;
              }

              if (isset($row['phone']) && (string)$row['phone'] === $phone) {
                $phoneTaken = true;
              }
            }
          }

          $dupStmt->close();

          if ($emailTaken) {
            $errors[] = 'Email already registered.';
          }

          if ($phoneTaken) {
            $errors[] = 'Phone already registered.';
          }
        }
      }

      if (empty($errors)) {
        $verificationCode = signup_generate_verification_code();
        $_SESSION['signup_pending'] = [
          'full_name' => $full_name,
          'email' => $email,
          'phone' => $phone,
          'password_hash' => commerza_password_hash($password),
          'code_hash' => hash('sha256', $verificationCode),
          'expires_at' => time() + 600,
          'last_resend_at' => time(),
          'attempts' => 0,
        ];

            commerza_notify_signup_verification_code($con, $email, $full_name, $verificationCode);
        $success[] = 'Verification code sent. Enter it below to complete signup.';
      }
    }

    $pendingSignup = $_SESSION['signup_pending'] ?? null;
}

  signup_end:

if (!empty($_SESSION['oauth_error'])) {
  $errors[] = (string)$_SESSION['oauth_error'];
  unset($_SESSION['oauth_error']);
}

$signupUrl = commerza_absolute_url('/signup.php');
$signupImageUrl = commerza_absolute_url('/frontend/assets/images/logo/commerza-logo.webp');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="description" content="Create your Commerza account securely to shop, track orders, and manage your wishlist.">
  <meta property="og:title" content="Sign Up | Commerza">
  <meta property="og:description" content="Create your Commerza profile to save favorites, checkout faster, and track every order.">
  <meta property="og:url" content="<?= htmlspecialchars($signupUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= htmlspecialchars($signupImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Sign Up | Commerza">
  <meta name="twitter:description" content="Create your Commerza profile to save favorites, checkout faster, and track every order.">
  <meta name="twitter:image" content="<?= htmlspecialchars($signupImageUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sign Up | Commerza</title>
  <link rel="canonical" href="<?= htmlspecialchars($signupUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Sign Up | Commerza",
      "url": "<?= htmlspecialchars($signupUrl, ENT_QUOTES, 'UTF-8') ?>",
      "description": "Create a Commerza user account securely."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    ::selection {
      background-color: #ff4500;
      color: #fff;
    }

    body.dark-theme {
      background: radial-gradient(circle at top right, rgba(255, 69, 0, 0.16), transparent 34%),
        radial-gradient(circle at bottom left, rgba(255, 204, 0, 0.08), transparent 30%),
        #050505;
      color: #d1d1d1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      margin: 0;
      overflow-x: hidden;
      padding: 28px 16px;
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

    .signup-shell {
      width: 100%;
      max-width: 1120px;
      margin: 0 auto;
    }

    .signup-card {
      background: linear-gradient(145deg, #1a1a1a 0%, #0a0a0a 100%);
      border: 1px solid rgba(255, 69, 0, 0.2);
      border-radius: 12px;
      width: 100%;
      max-width: 1120px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 69, 0, 0.1);
      position: relative;
      overflow: hidden;
      display: grid;
      grid-template-columns: minmax(320px, 0.9fr) minmax(420px, 1.1fr);
      animation: reveal-up 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }

    .signup-panel {
      padding: 42px 36px;
      border-right: 1px solid rgba(255, 69, 0, 0.2);
      background: linear-gradient(160deg, rgba(255, 69, 0, 0.15), rgba(0, 0, 0, 0.2));
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .signup-form-wrap {
      padding: 34px 34px 30px;
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

    .signup-title {
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(2rem, 3.6vw, 32px);
      font-weight: 900;
      text-align: center;
      letter-spacing: clamp(2px, 0.4vw, 4px);
      text-transform: uppercase;
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

    .signup-subtitle {
      font-family: 'JetBrains Mono', monospace;
      color: #888;
      text-align: center;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 24px;
    }

    .signup-points {
      margin: 20px 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 10px;
    }

    .signup-points li {
      border: 0;
      background: linear-gradient(135deg, rgba(255, 69, 0, 0.12), rgba(0, 0, 0, 0.38));
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 13px;
      color: #d7d7d7;
      line-height: 1.45;
      display: flex;
      align-items: flex-start;
      gap: 6px;
    }

    .signup-panel-copy {
      color: #f1f1f1;
      font-size: 14px;
      line-height: 1.6;
    }

    .signup-points i {
      color: #ffcc00;
      margin-top: 1px;
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
      color: #fff !important;
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

    .form-control[type="password"],
    .form-control[type="text"] {
      padding-right: 40px !important;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #ff4500;
      z-index: 10;
      font-size: 18px;
      line-height: 1;
      transition: color 0.3s ease;
    }

    .toggle-password:hover {
      color: #ffcc00;
    }

    .progress {
      background-color: #000 !important;
      border: 1px solid #222;
      overflow: hidden;
    }

    .bg-orange {
      background-color: #ff8533 !important;
    }

    .signup-btn {
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

    .signup-btn:hover:not(:disabled) {
      background: linear-gradient(90deg, #ff4500, #ffcc00) !important;
      color: #000 !important;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(255, 69, 0, 0.4);
    }

    .signup-btn:active:not(:disabled) {
      transform: scale(0.95);
    }

    .signup-btn:disabled {
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

    .signup-links {
      text-align: center;
      margin-top: 18px;
      width: 100%;
    }

    .signup-links p {
      margin-bottom: 8px;
      color: #888;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      user-select: none;
    }

    .signup-links a {
      color: #ff4500;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      text-decoration: none;
      text-transform: uppercase;
      transition: 0.3s;
      display: inline-block;
      margin-left: 2px;
    }

    .signup-links a:hover {
      color: #ffcc00;
      text-decoration: underline;
      text-shadow: 0 0 10px rgba(255, 69, 0, 0.5);
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
    #clientAlert,
    #successAlert {
      background-color: #ff0000 !important;
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
      width: 320px;
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }

    #successAlert {
      background-color: #198754 !important;
    }

    @media (max-width: 991px) {
      .signup-card {
        grid-template-columns: 1fr;
      }

      .signup-panel {
        border-right: 0;
        border-bottom: 1px solid rgba(255, 69, 0, 0.2);
        padding: 28px 24px;
      }

      .signup-form-wrap {
        padding: 24px 20px;
      }

      .signup-title,
      .signup-subtitle,
      .signup-links {
        text-align: center;
      }

      .signup-panel-copy {
        text-align: center;
      }
    }

    @media (max-width: 575px) {
      body.dark-theme {
        padding: 14px 10px;
      }

      .signup-panel {
        padding: 22px 16px;
      }

      .signup-form-wrap {
        padding: 20px 14px;
      }

      .signup-subtitle {
        font-size: 11px;
        margin-bottom: 16px;
      }

      .signup-points {
        gap: 8px;
      }

      .signup-points li {
        font-size: 12px;
        padding: 9px 10px;
      }
    }
  </style>
</head>

<body class="dark-theme">

  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="successAlert"><?= htmlspecialchars(implode(' ', $success)) ?></div>
  <?php endif; ?>

  <div id="clientAlert" style="display:none;"></div>

  <div class="signup-shell">
    <div class="signup-card">
      <div class="signup-panel">
        <h1 class="signup-title">COMMERZA</h1>
        <p class="signup-subtitle">Create your account</p>
        <p class="signup-panel-copy mb-0 text-center">Create your Commerza profile to save favorites and glide through checkout.</p>
        <ul class="signup-points">
          <li><i class="bi bi-stars"></i><span>Build your personal watch rack in one tap.</span></li>
          <li><i class="bi bi-speedometer2"></i><span>Checkout faster with your saved profile details.</span></li>
          <li><i class="bi bi-truck"></i><span>Track each order from confirmation to doorstep.</span></li>
        </ul>
        <div class="signup-links">
          <p class="mt-3 mb-0">Already have an account? <a href="login.php">Login</a></p>
        </div>
      </div>

      <div class="signup-form-wrap">
        <?php if (is_array($pendingSignup)): ?>
          <form id="verifySignupForm" action="signup.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="flow_action" value="verify_signup">

            <div class="mb-3">
              <label class="form-label">Verification Email</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars((string)($pendingSignup['email'] ?? '')) ?>" readonly>
            </div>

            <div class="mb-3">
              <label for="verification-code" class="form-label">6-Digit Code</label>
              <input type="text" class="form-control" id="verification-code" name="verification_code"
                placeholder="Enter verification code" required minlength="6" maxlength="6" pattern="\d{6}" autocomplete="one-time-code">
            </div>

            <div class="d-grid gap-2">
              <button type="submit" id="verifyBtn" class="btn signup-btn">Verify &amp; Create Account</button>
            </div>
          </form>

          <form action="signup.php" method="POST" class="mt-2 d-grid gap-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="flow_action" value="resend_code">
            <?= commerza_captcha_widget_html($con, 'user_signup_resend') ?>
            <button type="submit" class="btn oauth-btn">Resend Code</button>
            <a href="signup.php?reset_pending=1" class="btn btn-outline-secondary">Start Over</a>
          </form>
        <?php else: ?>
          <form id="signupForm" action="signup.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="flow_action" value="start_signup">

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="fullname" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="fullname" name="user_full_name"
                  placeholder="Enter your full name" required autofocus autocomplete="name"
                  minlength="3" maxlength="40"
                  pattern="[A-Za-z][A-Za-z\s\.\'\-]{2,39}"
                  title="Use 3-40 letters with spaces, dots, apostrophes, or hyphens."
                  value="<?= htmlspecialchars($full_name) ?>" />
              </div>

              <div class="col-md-6 mb-3">
                <label for="signup-email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="signup-email" name="user_signup_email"
                  placeholder="Enter your email" required autocomplete="email" maxlength="150"
                  value="<?= htmlspecialchars($email) ?>" />
              </div>
            </div>

            <div class="mb-3">
              <label for="signup-phone" class="form-label">Phone Number</label>
              <input type="tel" class="form-control" id="signup-phone" name="user_signup_phone"
                placeholder="03XXXXXXXXX" required autocomplete="tel"
                minlength="11" maxlength="15" pattern="\d{11,15}" title="Enter 11 to 15 digits only."
                value="<?= htmlspecialchars($phone) ?>" />
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="signup-password" class="form-label">Create Password</label>
                <div class="password-wrapper">
                  <input type="password" class="form-control" id="signup-password" name="signup_create_password"
                    placeholder="Create password" required minlength="10" maxlength="64"
                    autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false"
                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{10,64}"
                    title="10-64 chars with uppercase, lowercase, number, and special character" />
                  <i class="bi bi-eye toggle-password" data-target="signup-password"></i>
                </div>
              </div>

              <div class="col-md-6 mb-3">
                <label for="signup-confirm-password" class="form-label">Confirm Password</label>
                <div class="password-wrapper">
                  <input type="password" class="form-control" id="signup-confirm-password" name="signup_confirm_password"
                    placeholder="Confirm password" required minlength="10" maxlength="64"
                    autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" />
                  <i class="bi bi-eye toggle-password" data-target="signup-confirm-password"></i>
                </div>
              </div>
            </div>

            <div class="progress mt-1 mb-3" id="strengthBar" style="height:5px; display:none;">
              <div id="passwordStrength" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>

            <?= commerza_captcha_widget_html($con, 'user_signup_start') ?>

            <div class="d-grid">
              <button type="submit" id="submitBtn" class="btn signup-btn">Create Account</button>
            </div>
          </form>
        <?php endif; ?>

        <div class="oauth-divider"><span>or sign up with</span></div>
        <div class="d-grid gap-2">
          <a class="btn oauth-btn oauth-google" href="oauth.php?provider=google&amp;mode=signup">
            <i class="bi bi-google"></i>
            <span>Google</span>
          </a>
          <a class="btn oauth-btn oauth-facebook" href="oauth.php?provider=facebook&amp;mode=signup">
            <i class="bi bi-facebook"></i>
            <span>Facebook</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function () {
      let submitted = false;
      const csrf = $('input[name="csrf_token"]').val();
      const fieldState = { email: false, phone: false };

      <?php if (!empty($errors)): ?>
        setTimeout(() => $("#serverAlert").fadeOut(400), 3500);
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        setTimeout(() => $("#successAlert").fadeOut(400), 3500);
      <?php endif; ?>

      function showAlert(msg) {
        $("#clientAlert").text(msg).fadeIn(200);
        setTimeout(() => $("#clientAlert").fadeOut(400), 2800);
      }

      function setFieldStatus(input, taken, message) {
        const wrapper = input.closest(".mb-3");
        wrapper.find(".live-feedback").remove();

        const color = taken ? "#ff4444" : "#22c55e";
        const icon = taken ? "bi-x-circle-fill" : "bi-check-circle-fill";
        const text = taken ? message : "Available";

        input.css("border-color", color);
        wrapper.append(
          `<div class="live-feedback" style="font-family:'JetBrains Mono',monospace;font-size:11px;margin-top:5px;color:${color};">
            <i class="bi ${icon}"></i> ${text}
          </div>`
        );
      }

      function clearFieldStatus(input) {
        input.closest(".mb-3").find(".live-feedback").remove();
        input.css("border-color", "");
      }

      function checkField(input, field, value, message) {
        $.post("backend/check_exists.php", { csrf_token: csrf, field, value })
          .done(function (res) {
            fieldState[field] = !!res.exists;
            setFieldStatus(input, !!res.exists, message);
          })
          .fail(function () {
            clearFieldStatus(input);
            fieldState[field] = false;
          });
      }

      let emailTimer;
      let phoneTimer;

      $("#signup-email").on("input", function () {
        const val = $(this).val().trim().toLowerCase();
        clearTimeout(emailTimer);
        clearFieldStatus($(this));
        fieldState.email = false;
        if (!val) return;
        emailTimer = setTimeout(() => {
          checkField($(this), "email", val, "Email already registered");
        }, 500);
      });

      $("#signup-phone").on("input", function () {
        const val = $(this).val().trim();
        clearTimeout(phoneTimer);
        clearFieldStatus($(this));
        fieldState.phone = false;
        if (!val) return;
        phoneTimer = setTimeout(() => {
          checkField($(this), "phone", val, "Phone already registered");
        }, 500);
      });

      $(".toggle-password").on("click", function () {
        const id = $(this).data("target");
        const input = $("#" + id);
        input.attr("type", input.attr("type") === "password" ? "text" : "password");
        $(this).toggleClass("bi-eye bi-eye-slash");
      });

      $("#signup-password").on("input", function () {
        const pw = $(this).val();
        let strength = 0;
        if (pw.length >= 10) strength++;
        if (/[A-Z]/.test(pw)) strength++;
        if (/[a-z]/.test(pw)) strength++;
        if (/[0-9]/.test(pw)) strength++;
        if (/[@$!%*?&]/.test(pw)) strength++;

        $("#strengthBar").toggle(pw.length > 0);
        $("#passwordStrength")
          .css("width", ((strength / 5) * 100) + "%")
          .removeClass()
          .addClass("progress-bar " + (
            strength <= 1 ? "bg-warning" :
            strength === 2 ? "bg-success" :
            strength === 3 ? "bg-info" :
            strength === 4 ? "bg-orange" : "bg-danger"
          ));
      });

      $("#signupForm").on("submit", function () {
        if (submitted) {
          return false;
        }

        if (fieldState.email || fieldState.phone) {
          showAlert(
            fieldState.email && fieldState.phone
              ? "Email and phone already registered."
              : fieldState.email
              ? "Email already registered."
              : "Phone already registered."
          );
          return false;
        }

        const pw = $("#signup-password").val();
        const cpw = $("#signup-confirm-password").val();

        if (pw !== cpw) {
          showAlert("Passwords do not match.");
          return false;
        }

        submitted = true;
        $("#submitBtn").prop("disabled", true).text("Creating Account...");
      });

      $("#verifySignupForm").on("submit", function () {
        $("#verifyBtn").prop("disabled", true).text("Verifying...");
      });
    });
  </script>
</body>

</html>
