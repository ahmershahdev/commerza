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
$username = '';
$email = '';
$phone = '';
$pendingSignup = $_SESSION['signup_pending'] ?? null;

if (isset($_GET['reset_pending']) && $_GET['reset_pending'] === '1') {
  unset($_SESSION['signup_pending']);
  $pendingSignup = null;
}

if (is_array($pendingSignup)) {
  $full_name = (string)($pendingSignup['full_name'] ?? '');
  $username = (string)($pendingSignup['username'] ?? '');
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
      $username = (string)($pending['username'] ?? '');
      $email = strtolower(trim((string)($pending['email'] ?? '')));
      $phone = (string)($pending['phone'] ?? '');
      $profile_visibility = 'private';
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
          $pending['expires_at'] = time() + (15 * 60);
          $pending['last_resend_at'] = time();
          $_SESSION['signup_pending'] = $pending;
          commerza_notify_signup_verification_code($con, $email, $full_name, $newCode);
          $success[] = 'A new verification code was sent to your email. This code expires in 15 minutes.';
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
            $resolvedUsername = commerza_username_resolve_unique(
              $con,
              (string)($pending['username'] ?? ''),
              $full_name,
              $email
            );
            $username = (string)($resolvedUsername['username'] ?? '');
            $username_slug = (string)($resolvedUsername['slug'] ?? $username);

            if (!commerza_username_is_valid($username) || !commerza_username_is_valid($username_slug)) {
              $errors[] = 'Username is invalid. Please restart signup.';
            }

            if (empty($errors)) {
              $blockedUsername = commerza_username_blacklist_lookup($con, $username_slug);
              if (is_array($blockedUsername)) {
                $errors[] = commerza_username_blacklist_feedback_message($blockedUsername);
              }

              $blockedContact = commerza_customer_blacklist_lookup($con, $email, $phone);
              if (is_array($blockedContact)) {
                $errors[] = commerza_customer_blacklist_feedback_message($blockedContact);
              }
            }

            if ($password_hash === '') {
              $errors[] = 'Signup session invalid. Please start again.';
              unset($_SESSION['signup_pending']);
            }

            if (empty($errors)) {
              $stmt = $con->prepare(
                "INSERT INTO users (full_name, username, username_slug, email, phone, password_hash, profile_visibility) VALUES (?, ?, ?, ?, ?, ?, ?)"
              );

              if (!$stmt) {
                $errors[] = 'Something went wrong. Please try again.';
              } else {
                $stmt->bind_param("sssssss", $full_name, $username, $username_slug, $email, $phone, $password_hash, $profile_visibility);

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
                  $errors[] = 'Email, phone, or username already exists.';
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
    $username = commerza_username_slug((string)($_POST['user_username'] ?? ''));
    $email = strtolower(trim((string)($_POST['user_signup_email'] ?? '')));
    $phone = preg_replace('/\s+/', '', trim((string)($_POST['user_signup_phone'] ?? '')));
    $phone = $phone ?? '';
    $profile_visibility = 'private';
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

    if (!commerza_username_is_valid($username)) {
      $errors[] = 'Username must be 3-24 chars and use lowercase letters, numbers, or underscores.';
    }

    if (empty($errors)) {
      $blockedUsername = commerza_username_blacklist_lookup($con, $username);
      if (is_array($blockedUsername)) {
        $errors[] = commerza_username_blacklist_feedback_message($blockedUsername);
      }

      $blockedContact = commerza_customer_blacklist_lookup($con, $email, $phone);
      if (is_array($blockedContact)) {
        $errors[] = commerza_customer_blacklist_feedback_message($blockedContact);
      }
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
      $dupStmt = $con->prepare("SELECT email, phone, username_slug FROM users WHERE email = ? OR phone = ? OR username_slug = ? LIMIT 3");

      if (!$dupStmt) {
        $errors[] = 'Something went wrong. Please try again.';
      } else {
        $dupStmt->bind_param("sss", $email, $phone, $username);
        $dupStmt->execute();
        $dupResult = $dupStmt->get_result();

        $emailTaken = false;
        $phoneTaken = false;
        $usernameTaken = false;

        if ($dupResult) {
          while ($row = $dupResult->fetch_assoc()) {
            if (isset($row['email']) && strcasecmp((string)$row['email'], $email) === 0) {
              $emailTaken = true;
            }

            if (isset($row['phone']) && (string)$row['phone'] === $phone) {
              $phoneTaken = true;
            }

            if (isset($row['username_slug']) && strcasecmp((string)$row['username_slug'], $username) === 0) {
              $usernameTaken = true;
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

        if ($usernameTaken) {
          $errors[] = 'Username already taken.';
        }
      }
    }

    if (empty($errors)) {
      $verificationCode = signup_generate_verification_code();
      $_SESSION['signup_pending'] = [
        'full_name' => $full_name,
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => commerza_password_hash($password),
        'code_hash' => hash('sha256', $verificationCode),
        'expires_at' => time() + (15 * 60),
        'last_resend_at' => time(),
        'attempts' => 0,
      ];

      commerza_notify_signup_verification_code($con, $email, $full_name, $verificationCode);
      $success[] = 'Verification code sent. Enter it below to complete signup within 15 minutes.';
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
  <link rel="stylesheet" href="frontend/assets/css/pages/signup-inline.css">
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
                  pattern="[A-Za-z][A-Za-z .'\-]{2,39}"
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

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="signup-username" class="form-label">Username</label>
                <input type="text" class="form-control" id="signup-username" name="user_username"
                  placeholder="your_username" required autocomplete="username"
                  minlength="3" maxlength="24" pattern="[a-zA-Z][a-zA-Z0-9_]{2,23}"
                  title="Use 3-24 characters: letters, numbers, underscore."
                  value="<?= htmlspecialchars($username) ?>" />
              </div>

              <div class="col-md-6 mb-3">
                <label for="signup-phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="signup-phone" name="user_signup_phone"
                  placeholder="03XXXXXXXXX" required autocomplete="tel"
                  minlength="11" maxlength="15" pattern="\d{11,15}" title="Enter 11 to 15 digits only."
                  value="<?= htmlspecialchars($phone) ?>" />
              </div>
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
          <a class="btn oauth-btn oauth-google" href="oauth?provider=google&amp;mode=signup">
            <i class="bi bi-google"></i>
            <span>Google</span>
          </a>
          <a class="btn oauth-btn oauth-facebook" href="oauth?provider=facebook&amp;mode=signup">
            <i class="bi bi-facebook"></i>
            <span>Facebook</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="frontend/assets/js/global-protection.js?v=20260408"></script>
  <?= commerza_captcha_script_tag($con) ?>
  <script src="frontend/assets/js/pages/signup.js"></script>
</body>

</html>