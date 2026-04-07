<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

include __DIR__ . '/data.php';

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

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$clientIp = commerza_client_ip();
$rate = commerza_rate_limit_check(
    $con,
    'user_account_exists_lookup',
    (string)$userId,
    $clientIp,
    90,
    600,
    900,
    1800,
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
$excludeCurrent = !empty($_POST['exclude_current']);
$excludeUserId = 0;

if ($excludeCurrent && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
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
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email']);
        exit;
    }
    $sql = 'SELECT 1 FROM users WHERE email = ?';
} elseif ($field === 'phone') {
    if (!preg_match('/^\d{11,15}$/', $value)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid phone']);
        exit;
    }
    $sql = 'SELECT 1 FROM users WHERE phone = ?';
} else {
    $value = commerza_username_slug($value);
    if (!commerza_username_is_valid($value)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid username']);
        exit;
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
