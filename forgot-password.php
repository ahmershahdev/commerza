<?php
include "backend/core/data.php";
require_once "backend/mailer/mailer.php";

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$email_value = '';

function send_reset_code_email(string $recipientEmail, string $recipientName, string $code, ?string &$errorMessage = null): bool
{
  $subject = "Commerza Password Reset Code";
  $resetUrl = commerza_absolute_url('/reset-password.php');
  $supportEmail = trim((string)(getenv('COMMERZA_SUPPORT_EMAIL') ?: 'support@ahmershah.dev'));
  if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    $supportEmail = 'support@ahmershah.dev';
  }

  $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Customer', ENT_QUOTES, 'UTF-8');
  $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

  $message = "<!DOCTYPE html>
<html>
  <body style=\"margin:0;padding:0;background:#080808;font-family:Arial,sans-serif;color:#f5f5f5;\">
    <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#080808;padding:24px 0;\">
      <tr>
        <td align=\"center\">
          <table role=\"presentation\" width=\"600\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:600px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;\">
            <tr>
              <td align=\"center\" style=\"padding:28px 20px 10px 20px;\">
                <h1 style=\"margin:0;color:#ff6600;font-size:24px;letter-spacing:1px;\">Password Reset</h1>
              </td>
            </tr>
            <tr>
              <td style=\"padding:20px 30px 30px 30px;\">
                <p style=\"margin:0 0 12px 0;line-height:1.6;color:#d7d7d7;\">Hello {$safeName},</p>
                <p style=\"margin:0 0 14px 0;line-height:1.6;color:#d7d7d7;\">Use the code below to reset your Commerza account password. This code expires in <strong>15 minutes</strong> and can be used only once.</p>
                <div style=\"margin:18px 0 20px 0;padding:14px 18px;background:#0b0b0b;border:1px dashed #ff6600;border-radius:8px;text-align:center;\">
                  <span style=\"font-size:28px;font-weight:700;letter-spacing:6px;color:#ffcc00;\">{$safeCode}</span>
                </div>
                <p style=\"margin:0 0 18px 0;line-height:1.6;color:#bfbfbf;\">Then open this page to complete reset:</p>
                <p style=\"margin:0 0 10px 0;word-break:break-all;\"><a href=\"{$resetUrl}\" style=\"color:#ff6600;text-decoration:none;\">{$resetUrl}</a></p>
                <p style=\"margin:18px 0 0 0;line-height:1.6;color:#8f8f8f;font-size:13px;\">If you did not request this, you can ignore this email.</p>
                <p style=\"margin:16px 0 0 0;line-height:1.6;color:#9f9f9f;font-size:12px;\">Support: <a href=\"mailto:{$supportEmail}\" style=\"color:#ffb066;text-decoration:none;\">{$supportEmail}</a></p>
                <p style=\"margin:8px 0 0 0;line-height:1.6;color:#9f9f9f;font-size:12px;\">Connect: <a href=\"https://instagram.com/commerza\" style=\"color:#ffb066;text-decoration:none;\">Instagram</a> <span style=\"color:#666;\">|</span> <a href=\"https://facebook.com/commerza\" style=\"color:#ffb066;text-decoration:none;\">Facebook</a> <span style=\"color:#666;\">|</span> <a href=\"https://www.linkedin.com/in/syedahmershah\" style=\"color:#ffb066;text-decoration:none;\">LinkedIn</a> <span style=\"color:#666;\">|</span> <a href=\"https://github.com/ahmershahdev\" style=\"color:#ffb066;text-decoration:none;\">GitHub</a></p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>";

  return commerza_send_html_mail(
    $recipientEmail,
    $subject,
    $message,
    $supportEmail,
    'Commerza Security',
    $errorMessage
  );
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

  $captchaCheck = commerza_captcha_verify_submission($con, $_POST, 'user_forgot_password');
  if (!(bool)$captchaCheck['ok']) {
    $errors[] = (string)$captchaCheck['message'];
  }

  $email_value = strtolower(trim((string)($_POST['forgot_password_email'] ?? '')));

  if (!filter_var($email_value, FILTER_VALIDATE_EMAIL) || strlen($email_value) > 150) {
    $errors[] = "Please enter a valid email address.";
  }

  $clientIp = commerza_client_ip();

  if (empty($errors)) {
    $rate = commerza_rate_limit_check(
      $con,
      'user_forgot_password',
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
      commerza_security_log_rate_limit_block(
        $con,
        'user_forgot_password',
        'user',
        $email_value !== '' ? $email_value : 'anonymous',
        $clientIp,
        $retrySeconds
      );
      $errors[] = "Too many reset requests. Try again in " . $retryMinutes . " minute(s) (" . $retrySeconds . " seconds).";
    }
  }

  if (empty($errors)) {
    $stmt = $con->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");

    if (!$stmt) {
      $errors[] = "Something went wrong. Please try again.";
    } else {
      $stmt->bind_param("s", $email_value);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result ? $result->fetch_assoc() : null;
      $stmt->close();

      if (!$user) {
        commerza_security_log_event($con, [
          'event_type' => 'password_reset_requested_unknown_email',
          'severity' => 'warning',
          'actor_type' => 'user',
          'actor_identifier' => $email_value,
          'ip_address' => $clientIp,
        ]);

        $success = "If an account exists for this email, a reset code has been sent.";
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $canSend = false;
      } else {
        $canSend = empty($errors);
      }

      $lastEmailSentAt = (int)($_SESSION['forgot_password_last_sent_at'] ?? 0);
      $lastEmailTarget = (string)($_SESSION['forgot_password_last_sent_email'] ?? '');

      if ($canSend && $lastEmailTarget === $email_value && (time() - $lastEmailSentAt) < 60) {
        $errors[] = "Please wait 60 seconds before requesting another code.";
        $canSend = false;
      }

      if ($canSend && $user) {
        $resetCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $tokenHash = commerza_password_hash($resetCode);
        if ($tokenHash === '') {
          $errors[] = "Unable to generate reset code. Please try again.";
          $canSend = false;
        }

        if ($canSend) {
          $updateStmt = $con->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ? LIMIT 1");
          if (!$updateStmt) {
            $errors[] = "Unable to process request right now.";
          } else {
            $userId = (int)$user['id'];
            $updateStmt->bind_param("si", $tokenHash, $userId);
            $updated = $updateStmt->execute();
            $updateStmt->close();

            if (!$updated) {
              $errors[] = "Unable to generate reset code. Please try again.";
            } else {
              $mailError = null;
              $mailSent = send_reset_code_email(
                (string)$user['email'],
                (string)$user['full_name'],
                $resetCode,
                $mailError
              );

              if ($mailSent) {
                commerza_security_log_event($con, [
                  'event_type' => 'password_reset_code_sent',
                  'severity' => 'info',
                  'actor_type' => 'user',
                  'actor_identifier' => $email_value,
                  'user_id' => (int)$user['id'],
                  'ip_address' => $clientIp,
                ]);
                $_SESSION['forgot_password_last_sent_at'] = time();
                $_SESSION['forgot_password_last_sent_email'] = $email_value;
                $success = "Reset code sent successfully. Please check your inbox.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
              } else {
                $clearStmt = $con->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ? LIMIT 1");
                if ($clearStmt) {
                  $userId = (int)$user['id'];
                  $clearStmt->bind_param('i', $userId);
                  $clearStmt->execute();
                  $clearStmt->close();
                }

                commerza_security_log_event($con, [
                  'event_type' => 'password_reset_email_send_failed',
                  'severity' => 'warning',
                  'actor_type' => 'user',
                  'actor_identifier' => $email_value,
                  'user_id' => (int)$user['id'],
                  'ip_address' => $clientIp,
                  'details' => [
                    'mail_error' => $mailError ?? '',
                  ],
                ]);
                $errors[] = $mailError ?: "Unable to send reset email right now.";
              }
            }
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
  <meta name="description" content="Request a secure password reset code for your Commerza account.">
  <meta property="og:title" content="Forgot Password | Commerza">
  <meta property="og:description" content="Request a password reset code for your Commerza account.">
  <meta property="og:url" content="https://commerza.ahmershah.dev/forgot-password.php">
  <meta property="og:type" content="website">
  <meta property="og:image" content="https://commerza.ahmershah.dev/frontend/assets/images/logo/commerza-logo.webp">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <title>Forgot Password | Commerza</title>
  <link rel="canonical" href="https://commerza.ahmershah.dev/forgot-password.php" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script <?= commerza_csp_nonce_attr() ?> type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Forgot Password | Commerza",
      "url": "https://commerza.ahmershah.dev/forgot-password.php",
      "description": "Request a secure Commerza password reset code."
    }
  </script>
  <link rel="icon" href="frontend/assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="frontend/assets/css/pages/forgot-password-inline.css">
</head>

<body class="dark-theme">
  <?php if (!empty($errors)): ?>
    <div id="serverAlert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div id="successAlert"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="forgot-card">
    <h1 class="forgot-title">COMMERZA</h1>
    <p class="forgot-subtitle">
      Enter your email and we will send you a secure reset code
    </p>

    <form action="forgot-password.php" method="POST" id="forgotPasswordForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="mb-3">
        <label for="forgot-password-email" class="form-label">Email Address</label>
        <input type="email" class="form-control" id="forgot-password-email" name="forgot_password_email"
          placeholder="Enter your registered email" required autocomplete="email" autofocus maxlength="150"
          value="<?= htmlspecialchars($email_value) ?>" />
      </div>

      <?= commerza_captcha_widget_html($con, 'user_forgot_password') ?>
      <p class="forgot-subtitle" style="margin-top:10px;margin-bottom:18px;letter-spacing:.5px;">
        Complete one verification method: Google check or backup question.
      </p>

      <div class="d-grid">
        <button type="submit" class="btn reset-btn" id="forgotPasswordSubmitBtn">Send Reset Code</button>
      </div>
    </form>

    <div class="forgot-links">
      <p class="mt-3 mb-1">
        <a href="login.php">Back to Login</a>
      </p>
      <p style="user-select: none">
        Do not have an account?
        <a href="signup.php">Create Account</a>
      </p>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/modules/core/global-protection.js"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="frontend/assets/js/pages/forgot-password.js"></script>
</body>

</html>
