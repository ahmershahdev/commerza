<?php
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../../backend/notifications.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
  http_response_code(500);
  exit('Service unavailable.');
}

if (!empty($_SESSION['admin_user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: admin-panel.php');
  exit;
}

$errors = [];
$emailValue = '';
$nextTarget = admin_safe_redirect_target(
  (string)($_GET['next'] ?? ($_POST['next'] ?? '')),
  'admin-panel.php'
);

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
      $errors[] = 'Too many login attempts. Try again in ' . (int)$rate['retry_after'] . ' seconds.';
    }
  }

  if (empty($errors)) {
    $admin = admin_get_by_email($con, $emailValue);

    if ($admin && (int)($admin['is_active'] ?? 0) === 1 && password_verify($password, (string)$admin['password_hash'])) {
      admin_login_user($con, $admin);
      commerza_rate_limit_reset($con, 'admin_login', $emailValue, $clientIp);
      commerza_notify_admin_login(
        $con,
        (string)($admin['email'] ?? ''),
        (string)($admin['full_name'] ?? ''),
        $clientIp
      );
      header('Location: ' . $nextTarget);
      exit;
    }

    $errors[] = 'Invalid email or password.';
  }
}

$csrfToken = admin_generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow">
  <meta name="author" content="Syed Ahmer Shah">
  <meta name="referrer" content="no-referrer">
  <meta http-equiv="X-Content-Type-Options" content="nosniff">
  <meta http-equiv="Permissions-Policy" content="geolocation=(), microphone=(), camera=()">
  <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; connect-src 'self' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self'">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <title>Admin Login | Commerza</title>
  <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
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

    .toggle-password-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #ff4500;
      z-index: 10;
      background: transparent;
      border: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      min-width: 44px;
      padding: 0;
      transition: all 0.3s ease;
    }

    .toggle-password-btn .bi {
      font-size: 18px;
      line-height: 1;
    }

    .toggle-password-btn:hover {
      color: #ffcc00;
    }

    .toggle-password-btn:focus-visible {
      outline: 2px solid #ffcc00;
      outline-offset: 2px;
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

    .login-btn:hover {
      background: linear-gradient(90deg, #ff4500, #ffcc00) !important;
      color: #000 !important;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(255, 69, 0, 0.4);
    }

    .login-btn:active {
      transform: scale(0.95);
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
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      min-width: 44px;
      padding: 8px 12px;
      margin-left: 2px;
    }

    .login-links a:hover {
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
  </style>
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
    <script src="assets/js/pages/admin-auth-common.js"></script>
    <script src="assets/js/pages/admin-login.js"></script>

</body>
</html>