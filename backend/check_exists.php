<?php
header('Content-Type: application/json');

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

$field = (string)($_POST['field'] ?? '');
$value = trim((string)($_POST['value'] ?? ''));

if (!in_array($field, ['email', 'phone'], true) || $value === '') {
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
    $column = 'email';
} else {
    if (!preg_match('/^\d{11,15}$/', $value)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid phone']);
        exit;
    }
    $column = 'phone';
}

$stmt = $con->prepare("SELECT 1 FROM users WHERE {$column} = ? LIMIT 1");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}

$stmt->bind_param('s', $value);
$stmt->execute();
$stmt->store_result();

echo json_encode(['exists' => $stmt->num_rows > 0]);

$stmt->close();