<?php
require_once __DIR__ . '/../backend/auth.php';

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
          admin_logout_user();
          $success = 'Admin email updated. Please log in again.';
          $newEmailValue = '';
          $confirmEmailValue = '';
        }
      }
    }
  }
}

$csrfToken = admin_generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self'">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <title>Admin Forgot Email | Commerza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="assets/js/admin-config.js"></script>
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

    .forgot-card {
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

    .forgot-title {
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
    }

    @keyframes h1-shine {
      to {
        background-position: 200% center;
      }
    }

    .forgot-subtitle {
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

    .reset-btn {
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

    .reset-btn:hover {
      background: linear-gradient(90deg, #ff4500, #ffcc00) !important;
      color: #000 !important;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(255, 69, 0, 0.4);
    }

    .reset-btn:active {
      transform: scale(0.95);
    }

    .forgot-links {
      text-align: center;
      margin-top: 25px;
      width: 100%;
    }

    .forgot-links p {
      margin-bottom: 8px;
      display: block;
      color: #888;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
    }

    .forgot-links a {
      color: #ff4500;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      text-decoration: none;
      text-transform: uppercase;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      min-width: 44px;
      padding: 8px 12px;
      margin-left: 2px;
    }

    .forgot-links a:hover {
      color: #ffcc00;
      text-decoration: underline;
      text-shadow: 0 0 10px rgba(255, 69, 0, 0.5);
    }
  </style>
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
    <script src="assets/js/pages/admin-auth-common.js"></script>
    <script src="assets/js/pages/admin-forgot-email.js"></script>

  </main>
</body>
</html>
