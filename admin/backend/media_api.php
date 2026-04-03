<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

$con = $con ?? null;
if (!($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
    exit;
}

admin_require_login_api($con);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!admin_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid CSRF token.',
    ]);
    exit;
}

$target = strtolower(trim((string)($_POST['target'] ?? '')));

$targets = [
    'product-image' => [
        'dir' => 'frontend/assets/images/products/uploads',
        'kind' => 'image',
        'max_size' => 3 * 1024 * 1024,
    ],
    'slider-image' => [
        'dir' => 'frontend/assets/images/slider',
        'kind' => 'image',
        'max_size' => 4 * 1024 * 1024,
    ],
    'logo' => [
        'dir' => 'frontend/assets/images/logo',
        'kind' => 'image',
        'max_size' => 2 * 1024 * 1024,
    ],
    'favicon' => [
        'dir' => 'frontend/assets/images/favicon',
        'kind' => 'image_or_icon',
        'max_size' => 1024 * 1024,
    ],
    'social-icon' => [
        'dir' => 'frontend/assets/images/social',
        'kind' => 'image_or_icon',
        'max_size' => 2 * 1024 * 1024,
    ],
    'product-video' => [
        'dir' => 'frontend/assets/videos/products/uploads',
        'kind' => 'video',
        'max_size' => 40 * 1024 * 1024,
    ],
    'slider-video' => [
        'dir' => 'frontend/assets/videos/slider',
        'kind' => 'video',
        'max_size' => 50 * 1024 * 1024,
    ],
];

if (!isset($targets[$target])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid upload target.',
    ]);
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'No file uploaded.',
    ]);
    exit;
}

$file = $_FILES['file'];
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Upload failed. Please retry.',
    ]);
    exit;
}

$tmpPath = (string)($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid uploaded file.',
    ]);
    exit;
}

$size = (int)($file['size'] ?? 0);
$meta = $targets[$target];
if ($size <= 0 || $size > (int)$meta['max_size']) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'File size exceeds allowed limit for this target.',
    ]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
if ($finfo) {
    finfo_close($finfo);
}

$allowed = [];
if ($meta['kind'] === 'image') {
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
} elseif ($meta['kind'] === 'image_or_icon') {
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
} else {
    $allowed = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
    ];
}

$extension = $allowed[$mime] ?? '';
if ($extension === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported file type for this target.',
    ]);
    exit;
}

$originalName = trim((string)($file['name'] ?? 'upload'));
$originalName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $originalName) ?? 'upload';
$originalName = trim($originalName, '-.');
if ($originalName === '') {
    $originalName = 'upload';
}

$basename = pathinfo($originalName, PATHINFO_FILENAME);
$basename = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$basename) ?? 'file';
$basename = trim($basename, '-_');
if ($basename === '') {
    $basename = 'file';
}

$filename = $basename . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
$relativeDir = str_replace('\\', '/', (string)$meta['dir']);
$absoluteDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to create upload directory.',
    ]);
    exit;
}

$absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
$relativePath = $relativeDir . '/' . $filename;

if (!move_uploaded_file($tmpPath, $absolutePath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to save uploaded file.',
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'message' => 'File uploaded successfully.',
    'payload' => [
        'path' => $relativePath,
        'target' => $target,
        'size' => $size,
        'mime' => $mime,
    ],
], JSON_UNESCAPED_SLASHES);
