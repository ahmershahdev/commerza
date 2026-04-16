<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

include __DIR__ . '/data.php';

const CHECK_EXISTS_USERNAME_CHANGE_LOCK_DAYS = 90;
const CHECK_EXISTS_USERNAME_CHANGE_LOCK_SECONDS = CHECK_EXISTS_USERNAME_CHANGE_LOCK_DAYS * 86400;

function check_exists_username_lock_state(?string $usernameChangedAt): array
{
    $raw = trim((string)$usernameChangedAt);
    if ($raw === '') {
        return [
            'locked' => false,
            'remaining_seconds' => 0,
            'remaining_days' => 0,
        ];
    }

    $changedAtTimestamp = strtotime($raw);
    if ($changedAtTimestamp === false) {
        return [
            'locked' => false,
            'remaining_seconds' => 0,
            'remaining_days' => 0,
        ];
    }

    $unlockTimestamp = $changedAtTimestamp + CHECK_EXISTS_USERNAME_CHANGE_LOCK_SECONDS;
    $remainingSeconds = $unlockTimestamp - time();
    if ($remainingSeconds <= 0) {
        return [
            'locked' => false,
            'remaining_seconds' => 0,
            'remaining_days' => 0,
        ];
    }

    return [
        'locked' => true,
        'remaining_seconds' => $remainingSeconds,
        'remaining_days' => (int)ceil($remainingSeconds / 86400),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$isAuthenticatedUser = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
$userId = $isAuthenticatedUser ? (int)$_SESSION['user_id'] : 0;
$clientIp = commerza_client_ip();
$rateScope = $isAuthenticatedUser ? 'user_account_exists_lookup' : 'public_signup_exists_lookup';
$rateIdentifier = $isAuthenticatedUser
    ? (string)$userId
    : 'guest:' . substr(hash('sha256', ($clientIp !== '' ? $clientIp : '0.0.0.0') . '|' . session_id()), 0, 24);

$rate = commerza_rate_limit_check(
    $con,
    $rateScope,
    $rateIdentifier,
    $clientIp,
    $isAuthenticatedUser ? 90 : 45,
    600,
    $isAuthenticatedUser ? 900 : 1200,
    $isAuthenticatedUser ? 1800 : 2400,
    86400
);

if (!(bool)($rate['allowed'] ?? false)) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many requests.',
        'retry_after' => max(1, (int)($rate['retry_after'] ?? 1)),
    ]);
    exit;
}

$field = (string)($_POST['field'] ?? '');
$value = trim((string)($_POST['value'] ?? ''));
$excludeCurrent = !empty($_POST['exclude_current']) && $isAuthenticatedUser;
$excludeUserId = 0;

if ($excludeCurrent && $isAuthenticatedUser) {
    $excludeUserId = (int)$_SESSION['user_id'];
}

if (!in_array($field, ['email', 'phone', 'username'], true) || $value === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if ($field === 'email') {
    $value = strtolower($value);
    if (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 150) {
        echo json_encode([
            'exists' => false,
            'invalid' => true,
            'message' => 'Invalid email',
        ]);
        exit;
    }

    $blocked = commerza_customer_blacklist_lookup($con, $value, '');
    if (is_array($blocked)) {
        echo json_encode([
            'exists' => true,
            'blocked' => true,
            'block_type' => 'email',
            'message' => commerza_customer_blacklist_feedback_message($blocked),
        ]);
        exit;
    }

    $sql = 'SELECT 1 FROM users WHERE email = ?';
} elseif ($field === 'phone') {
    if (!preg_match('/^\d{11,15}$/', $value)) {
        echo json_encode([
            'exists' => false,
            'invalid' => true,
            'message' => 'Invalid phone',
        ]);
        exit;
    }

    $blocked = commerza_customer_blacklist_lookup($con, '', $value);
    if (is_array($blocked)) {
        echo json_encode([
            'exists' => true,
            'blocked' => true,
            'block_type' => 'phone',
            'message' => commerza_customer_blacklist_feedback_message($blocked),
        ]);
        exit;
    }

    $sql = 'SELECT 1 FROM users WHERE phone = ?';
} else {
    $value = commerza_username_slug($value);
    if (!commerza_username_is_valid($value)) {
        echo json_encode([
            'exists' => false,
            'invalid' => true,
            'message' => 'Invalid username',
        ]);
        exit;
    }

    if ($excludeCurrent && $excludeUserId > 0) {
        $lockStmt = $con->prepare('SELECT username, username_slug, username_changed_at FROM users WHERE id = ? LIMIT 1');
        if (!$lockStmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }

        $lockStmt->bind_param('i', $excludeUserId);
        $lockStmt->execute();
        $lockResult = $lockStmt->get_result();
        $lockRow = $lockResult ? $lockResult->fetch_assoc() : null;
        $lockStmt->close();

        if (!is_array($lockRow)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $currentUsername = commerza_username_slug((string)($lockRow['username_slug'] ?? ''));
        if (!commerza_username_is_valid($currentUsername)) {
            $currentUsername = commerza_username_slug((string)($lockRow['username'] ?? ''));
        }
        $isUsernameChangeAttempt = strcasecmp($currentUsername, $value) !== 0;

        if ($isUsernameChangeAttempt) {
            $lockState = check_exists_username_lock_state((string)($lockRow['username_changed_at'] ?? ''));
            if ((bool)($lockState['locked'] ?? false)) {
                $remainingDays = max(1, (int)($lockState['remaining_days'] ?? 1));
                $remainingSeconds = max(1, (int)($lockState['remaining_seconds'] ?? 1));
                echo json_encode([
                    'exists' => true,
                    'blocked' => true,
                    'lock_active' => true,
                    'retry_after' => $remainingSeconds,
                    'retry_after_days' => $remainingDays,
                    'message' => 'Username can only be changed once every ' . CHECK_EXISTS_USERNAME_CHANGE_LOCK_DAYS . ' days. Try again in ' . $remainingDays . ' day(s).',
                ]);
                exit;
            }
        }
    }

    $blocked = commerza_username_blacklist_lookup($con, $value);
    if (is_array($blocked)) {
        echo json_encode([
            'exists' => true,
            'blocked' => true,
            'block_type' => (string)($blocked['type'] ?? 'harmful'),
            'message' => commerza_username_blacklist_feedback_message($blocked),
        ]);
        exit;
    }

    $sql = 'SELECT 1 FROM users WHERE username_slug = ?';
}

if ($excludeUserId > 0) {
    $sql .= ' AND id <> ?';
}

$sql .= ' LIMIT 1';

$stmt = $con->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}

if ($excludeUserId > 0) {
    $stmt->bind_param('si', $value, $excludeUserId);
} else {
    $stmt->bind_param('s', $value);
}

$stmt->execute();
$stmt->store_result();

echo json_encode(['exists' => $stmt->num_rows > 0]);

$stmt->close();
