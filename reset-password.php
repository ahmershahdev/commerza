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

    .reset-card {
      background: linear-gradient(145deg, #1a1a1a 0%, #0a0a0a 100%);
      border: 1px solid rgba(255, 69, 0, 0.2);
      border-radius: 12px;
      padding: 35px;
      width: 100%;
      max-width: 450px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(255, 69, 0, 0.1);
      position: relative;
      animation: reveal-up 1s ease-out forwards;
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

    .reset-title {
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

    .reset-subtitle {
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
      color: #ff6600;
      font-weight: 700;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .form-control {
      font-family: 'JetBrains Mono', monospace;
      background-color: #000 !important;
      border: 1px solid rgba(255, 69, 0, 0.3);
      color: #fff !important;
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

    #new-password,
    #confirm-password {
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

    .reset-btn {
      background: #000 !important;
      border: 1px solid #ff4500 !important;
      color: #fff !important;
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      padding: 12px !important;
      transition: all 0.22s ease-out;
      position: relative;
      overflow: hidden;
    }

    .reset-btn:hover:not(:disabled) {
      background: linear-gradient(90deg, #ff4500, #ffcc00) !important;
      color: #000 !important;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(255, 69, 0, 0.4);
    }

    .reset-btn:active:not(:disabled) {
      transform: scale(0.95);
    }

    .reset-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .reset-links {
      text-align: center;
      margin-top: 25px;
      width: 100%;
    }

    .reset-links p {
      margin-bottom: 8px;
      color: #888;
      font-size: 13px;
    }

    .reset-links a {
      color: #ff4500;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      text-decoration: none;
      text-transform: uppercase;
      transition: all 0.3s ease;
      display: inline-block;
      margin-left: 2px;
    }

    .reset-links a:hover {
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

    #serverAlert {
      background-color: #dc3545;
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
      max-width: calc(100% - 24px);
      z-index: 9999;
      border-radius: 6px;
      padding: 14px 20px;
      text-align: center;
    }
  </style>
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
  <script <?= commerza_csp_nonce_attr() ?>>
    $(function () {
      $("#serverAlert").each(function () {
        const element = $(this);
        setTimeout(function () {
          element.fadeOut(400);
        }, 3500);
      });

      $(".toggle-password").on("click", function () {
        const target = $(this).data("target");
        if (!target) return;
        const input = $(target);
        const isPassword = input.attr("type") === "password";
        input.attr("type", isPassword ? "text" : "password");
        $(this).toggleClass("bi-eye bi-eye-slash");
      });

      let submitted = false;
      $("#resetForm").on("submit", function () {
        if (submitted) {
          return false;
        }
        submitted = true;
        $("#resetSubmitBtn").prop("disabled", true).text("Updating...");
      });
    });
  </script>
</body>

</html>
