<?php
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../../backend/notifications.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    exit('Service unavailable.');
}

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin-panel.php');
    exit;
}

if (admin_get_two_factor_pending_session()) {
    header('Location: admin-verify-2fa.php');
    exit;
}

$pending = admin_get_email_verification_pending_session();
if (!$pending) {
    header('Location: admin-login.php');
    exit;
}

$errors = [];
$success = '';
$codeValue = '';
$clientIp = admin_get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_validate_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Forbidden.');
    }

    $pending = admin_get_email_verification_pending_session();
    if (!$pending) {
        header('Location: admin-login.php');
        exit;
    }

    $action = strtolower(trim((string)($_POST['action'] ?? 'verify')));

    if ($action === 'resend') {
        $rate = commerza_rate_limit_check(
            $con,
            'admin_email_verify_resend',
            (string)$pending['email'],
            $clientIp,
            4,
            600,
            900
        );

        if (!(bool)($rate['allowed'] ?? false)) {
            $retry = max(1, (int)($rate['retry_after'] ?? 1));
            commerza_security_log_rate_limit_block(
                $con,
                'admin_email_verify_resend',
                'admin',
                (string)$pending['email'],
                $clientIp,
                $retry
            );
            $errors[] = 'Too many resend requests. Try again in ' . $retry . ' seconds.';
        } else {
            $admin = admin_get_by_id($con, (int)$pending['admin_id']);
            if (!$admin) {
                admin_clear_email_verification_pending_session();
                $_SESSION['admin_login_error'] = 'Verification session expired. Please login again.';
                header('Location: admin-login.php');
                exit;
            }

            $blockReason = admin_account_block_reason($admin);
            if ($blockReason !== null) {
                admin_clear_email_verification_pending_session();
                $_SESSION['admin_login_error'] = $blockReason;
                header('Location: admin-login.php');
                exit;
            }

            if (admin_is_email_verified($admin)) {
                admin_clear_email_verification_pending_session();
                $issueError = null;
                if (!admin_issue_two_factor_challenge($con, $admin, (string)$pending['next'], $issueError)) {
                    $_SESSION['admin_login_error'] = $issueError ?: 'Email already verified. Please login again.';
                    header('Location: admin-login.php');
                    exit;
                }

                header('Location: admin-verify-2fa.php');
                exit;
            }

            $issueError = null;
            if (!admin_issue_email_verification_challenge($con, $admin, $issueError)) {
                $errors[] = $issueError ?: 'Unable to resend verification code.';
            } else {
                commerza_security_log_event($con, [
                    'event_type' => 'admin_email_verification_code_resent',
                    'severity' => 'info',
                    'actor_type' => 'admin',
                    'actor_identifier' => (string)$pending['email'],
                    'admin_id' => (int)$pending['admin_id'],
                    'ip_address' => $clientIp,
                ]);
                $success = 'A new email verification code has been sent.';
            }
        }
    } else {
        $codeValue = admin_normalize_numeric_code((string)($_POST['verification_code'] ?? ''));

        $rate = commerza_rate_limit_check(
            $con,
            'admin_email_verify_attempt',
            (string)$pending['email'],
            $clientIp,
            8,
            600,
            900
        );

        if (!(bool)($rate['allowed'] ?? false)) {
            $retry = max(1, (int)($rate['retry_after'] ?? 1));
            commerza_security_log_rate_limit_block(
                $con,
                'admin_email_verify_attempt',
                'admin',
                (string)$pending['email'],
                $clientIp,
                $retry
            );
            $errors[] = 'Too many verification attempts. Try again in ' . $retry . ' seconds.';
        } else {
            $verify = admin_verify_email_verification_code($con, (int)$pending['admin_id'], $codeValue);
            if (!(bool)($verify['ok'] ?? false)) {
                $status = (string)($verify['status'] ?? 'failed');

                commerza_security_log_event($con, [
                    'event_type' => 'admin_email_verification_failed',
                    'severity' => 'warning',
                    'actor_type' => 'admin',
                    'actor_identifier' => (string)$pending['email'],
                    'admin_id' => (int)$pending['admin_id'],
                    'ip_address' => $clientIp,
                    'details' => [
                        'status' => $status,
                    ],
                ]);

                if (in_array($status, ['invalid_admin', 'deleted'], true)) {
                    admin_clear_email_verification_pending_session();
                    $_SESSION['admin_login_error'] = (string)($verify['message'] ?? 'Verification session expired. Please login again.');
                    header('Location: admin-login.php');
                    exit;
                }

                $errors[] = (string)($verify['message'] ?? 'Invalid verification code.');
            } else {
                $verifiedAdmin = is_array($verify['admin'] ?? null) ? $verify['admin'] : null;
                if (!$verifiedAdmin) {
                    $verifiedAdmin = admin_get_by_id($con, (int)$pending['admin_id']);
                }

                if (!$verifiedAdmin) {
                    admin_clear_email_verification_pending_session();
                    $_SESSION['admin_login_error'] = 'Verification session expired. Please login again.';
                    header('Location: admin-login.php');
                    exit;
                }

                $blockReason = admin_account_block_reason($verifiedAdmin);
                if ($blockReason !== null) {
                    admin_clear_email_verification_pending_session();
                    $_SESSION['admin_login_error'] = $blockReason;
                    header('Location: admin-login.php');
                    exit;
                }

                $issueError = null;
                if (!admin_issue_two_factor_challenge($con, $verifiedAdmin, (string)$pending['next'], $issueError)) {
                    $errors[] = $issueError ?: 'Email verified, but unable to start two-factor verification.';
                } else {
                    admin_clear_email_verification_pending_session();
                    commerza_rate_limit_reset($con, 'admin_email_verify_attempt', (string)$pending['email'], $clientIp);
                    commerza_security_log_event($con, [
                        'event_type' => 'admin_email_verified',
                        'severity' => 'info',
                        'actor_type' => 'admin',
                        'actor_identifier' => (string)$pending['email'],
                        'admin_id' => (int)$pending['admin_id'],
                        'ip_address' => $clientIp,
                    ]);
                    header('Location: admin-verify-2fa.php');
                    exit;
                }
            }
        }
    }
}

$csrfToken = admin_generate_csrf_token();
$pending = admin_get_email_verification_pending_session();
if (!$pending) {
    header('Location: admin-login.php');
    exit;
}

$adminFrontendBaseHref = rtrim(admin_public_url('/admin/frontend/'), '/') . '/';
$verifyEmailCanonicalUrl = admin_public_url('/admin-verify-email');
$adminOgImageUrl = admin_public_url('/frontend/assets/images/logo/commerza-logo.webp');

function admin_mask_email_for_verify(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'your email';
    }

    [$local, $domain] = explode('@', $email, 2);
    $length = strlen($local);
    if ($length <= 2) {
        $maskedLocal = str_repeat('*', max(1, $length));
    } else {
        $maskedLocal = $local[0] . str_repeat('*', max(1, $length - 2)) . $local[$length - 1];
    }

    return $maskedLocal . '@' . $domain;
}
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
    <link rel="canonical" href="<?= htmlspecialchars($verifyEmailCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="Admin Email Verification | Commerza">
    <meta property="og:description" content="Verify admin email before secure panel access.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($verifyEmailCanonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($adminOgImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <title>Admin Email Verification | Commerza</title>
    <link rel="icon" href="assets/images/favicon/commerza-watches-icon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/pages/admin-verify-email-inline.css">
</head>

<body>
    <main class="verify-card">
        <h1 class="title">Verify Email</h1>
        <p class="subtitle">Enter the 6-digit code sent to <?= htmlspecialchars(admin_mask_email_for_verify((string)$pending['email'])) ?>.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small" role="alert"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success py-2 px-3 small" role="alert"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="admin-verify-email.php" method="POST" class="mb-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="mb-3">
                <label for="verification-code" class="form-label">Verification Code</label>
                <input
                    type="text"
                    id="verification-code"
                    name="verification_code"
                    class="form-control"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    minlength="6"
                    autocomplete="one-time-code"
                    required
                    value="<?= htmlspecialchars($codeValue) ?>">
            </div>

            <div class="d-grid mb-2">
                <button type="submit" name="action" value="verify" class="btn btn-primary">Verify Email</button>
            </div>
            <button type="submit" name="action" value="resend" formnovalidate class="btn btn-link p-0">Resend code</button>
            <span class="text-secondary px-2">|</span>
            <a href="admin-login.php" class="btn-link">Back to login</a>
        </form>
    </main>
</body>

</html>