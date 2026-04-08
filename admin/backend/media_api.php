<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../backend/media_image_helpers.php';

function admin_media_fail(int $status, string $message, array $payload = []): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'message' => $message,
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_media_require_db_connection($candidate): mysqli
{
    if ($candidate instanceof mysqli) {
        return $candidate;
    }

    admin_media_fail(500, 'Database connection unavailable.');
    throw new RuntimeException('Database connection unavailable.');
}

function admin_media_detect_mime(string $tmpPath): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return '';
    }

    $mime = (string)finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    return trim($mime);
}

function admin_media_extract_uploads(array $files): array
{
    $uploads = [];

    foreach (['file', 'files'] as $field) {
        if (!isset($files[$field]) || !is_array($files[$field])) {
            continue;
        }

        $entry = $files[$field];
        $names = $entry['name'] ?? null;

        if (is_array($names)) {
            foreach ($names as $index => $name) {
                $uploads[] = [
                    'name' => (string)$name,
                    'type' => (string)($entry['type'][$index] ?? ''),
                    'tmp_name' => (string)($entry['tmp_name'][$index] ?? ''),
                    'error' => (int)($entry['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($entry['size'][$index] ?? 0),
                ];
            }
            continue;
        }

        $uploads[] = [
            'name' => (string)($entry['name'] ?? ''),
            'type' => (string)($entry['type'] ?? ''),
            'tmp_name' => (string)($entry['tmp_name'] ?? ''),
            'error' => (int)($entry['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int)($entry['size'] ?? 0),
        ];
    }

    return $uploads;
}

function admin_media_normalize_name(string $raw): string
{
    $name = trim($raw);
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name) ?? 'upload';
    $name = trim($name, '-.');

    return $name !== '' ? $name : 'upload';
}

function admin_media_normalize_basename(string $name): string
{
    $basename = pathinfo($name, PATHINFO_FILENAME);
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$basename) ?? 'file';
    $basename = trim($basename, '-_');

    return $basename !== '' ? $basename : 'file';
}

function admin_media_random_suffix(int $bytes = 8): string
{
    try {
        return bin2hex(random_bytes($bytes));
    } catch (Throwable $exception) {
        return substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, $bytes * 2);
    }
}

function admin_media_image_from_upload(string $tmpPath, string $mime)
{
    $image = null;

    if ($mime === 'image/jpeg') {
        $image = @imagecreatefromjpeg($tmpPath);
    } elseif ($mime === 'image/png') {
        $image = @imagecreatefrompng($tmpPath);
    } elseif ($mime === 'image/gif') {
        $image = @imagecreatefromgif($tmpPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($tmpPath);
    }

    if ($image) {
        return $image;
    }

    $blob = @file_get_contents($tmpPath);
    if (!is_string($blob) || $blob === '') {
        return null;
    }

    return @imagecreatefromstring($blob) ?: null;
}

function admin_media_resize_image($image, int $maxDimension): array
{
    $width = max(1, (int)imagesx($image));
    $height = max(1, (int)imagesy($image));

    $ratio = min($maxDimension / $width, $maxDimension / $height, 1.0);
    if ($ratio >= 1.0) {
        return [
            'image' => $image,
            'width' => $width,
            'height' => $height,
            'resized' => false,
        ];
    }

    $newWidth = max(1, (int)floor($width * $ratio));
    $newHeight = max(1, (int)floor($height * $ratio));

    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if (!$canvas) {
        return [
            'image' => $image,
            'width' => $width,
            'height' => $height,
            'resized' => false,
        ];
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);

    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    return [
        'image' => $canvas,
        'width' => $newWidth,
        'height' => $newHeight,
        'resized' => true,
    ];
}

function admin_media_encode_webp($image, int $targetKb): array
{
    $qualities = [86, 82, 78, 74, 70, 66, 62, 58, 54, 50, 46, 42, 38, 34];
    $targetBytes = max(24 * 1024, $targetKb * 1024);

    $bestBlob = '';
    $bestBytes = 0;
    $bestQuality = 0;

    foreach ($qualities as $quality) {
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $blob = ob_get_clean();

        if (!$ok || !is_string($blob) || $blob === '') {
            continue;
        }

        $bytes = strlen($blob);
        if ($bestBytes === 0 || $bytes < $bestBytes) {
            $bestBytes = $bytes;
            $bestBlob = $blob;
            $bestQuality = $quality;
        }

        if ($bytes <= $targetBytes) {
            break;
        }
    }

    if ($bestBlob === '') {
        throw new RuntimeException('Unable to encode image as WebP.');
    }

    return [
        'blob' => $bestBlob,
        'bytes' => $bestBytes,
        'quality' => $bestQuality,
    ];
}

function admin_media_convert_to_webp(
    string $tmpPath,
    string $mime,
    string $destination,
    int $targetKb,
    int $maxDimension
): array {
    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        throw new RuntimeException('Image parser/compressor is not available on this server.');
    }

    $image = admin_media_image_from_upload($tmpPath, $mime);
    if (!$image) {
        throw new RuntimeException('Unable to parse this image format.');
    }

    $width = 0;
    $height = 0;
    $resized = false;

    try {
        $resizedMeta = admin_media_resize_image($image, max(512, $maxDimension));
        $image = $resizedMeta['image'];
        $width = (int)$resizedMeta['width'];
        $height = (int)$resizedMeta['height'];
        $resized = (bool)$resizedMeta['resized'];

        $encoded = admin_media_encode_webp($image, max(60, $targetKb));
        $bytes = file_put_contents($destination, (string)$encoded['blob'], LOCK_EX);
        if ($bytes === false || $bytes <= 0) {
            throw new RuntimeException('Unable to write compressed WebP file.');
        }

        return [
            'size' => (int)$bytes,
            'quality' => (int)$encoded['quality'],
            'width' => $width,
            'height' => $height,
            'resized' => $resized,
        ];
    } finally {
        imagedestroy($image);
    }
}

function admin_media_process_one_upload(
    array $upload,
    string $target,
    array $meta,
    string $relativeDir,
    string $absoluteDir
): array {
    $errorCode = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please retry.');
    }

    $tmpPath = (string)($upload['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $scanReason = null;
    if (!commerza_upload_scan_file($tmpPath, $scanReason)) {
        throw new RuntimeException($scanReason !== null ? $scanReason : 'Upload blocked by malware scanner.');
    }

    $size = (int)($upload['size'] ?? 0);
    $maxSize = (int)($meta['max_size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        throw new RuntimeException('File size exceeds allowed limit for this target.');
    }

    $mime = admin_media_detect_mime($tmpPath);
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
        throw new RuntimeException('Unsupported file type for this target.');
    }

    $originalName = admin_media_normalize_name((string)($upload['name'] ?? 'upload'));
    $basename = admin_media_normalize_basename($originalName);

    $isCompressibleImage = (
        in_array((string)$meta['kind'], ['image', 'image_or_icon'], true)
        && $extension !== 'ico'
    );

    $outputExtension = $isCompressibleImage ? 'webp' : $extension;
    $filename = $basename . '-' . admin_media_random_suffix(8) . '.' . $outputExtension;
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
    $relativePath = $relativeDir . '/' . $filename;

    $finalSize = 0;
    $outputMime = $mime;
    $parser = $meta['kind'] === 'video' ? 'video-validated' : 'file-validated';
    $qualityUsed = 0;
    $width = 0;
    $height = 0;
    $resized = false;

    if ($isCompressibleImage) {
        $parser = 'image-webp-parser-compressor';
        $outputMime = 'image/webp';

        $conversion = admin_media_convert_to_webp(
            $tmpPath,
            $mime,
            $absolutePath,
            (int)($meta['image_target_kb'] ?? 350),
            (int)($meta['image_max_dimension'] ?? 2200)
        );

        $finalSize = (int)$conversion['size'];
        $qualityUsed = (int)$conversion['quality'];
        $width = (int)$conversion['width'];
        $height = (int)$conversion['height'];
        $resized = (bool)$conversion['resized'];
    } else {
        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        $finalSize = (int)(filesize($absolutePath) ?: 0);
        if ($finalSize <= 0) {
            throw new RuntimeException('Uploaded file could not be verified after save.');
        }

        if ($extension === 'ico') {
            $parser = 'icon-pass-through';
        }
    }

    return [
        'path' => $relativePath,
        'target' => $target,
        'size' => $finalSize,
        'size_kb' => round($finalSize / 1024, 2),
        'mime' => $outputMime,
        'original_name' => $originalName,
        'original_size' => $size,
        'original_size_kb' => round($size / 1024, 2),
        'parser' => $parser,
        'compressed' => $isCompressibleImage,
        'quality' => $qualityUsed,
        'width' => $width,
        'height' => $height,
        'resized' => $resized,
    ];
}

$db = admin_media_require_db_connection($con ?? null);

$admin = admin_require_login_api($db);
admin_require_permission_api($admin, 'media.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_media_fail(405, 'Method not allowed.');
}

$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!admin_validate_csrf_token($csrfToken)) {
    admin_media_fail(403, 'Invalid CSRF token.');
}

$target = strtolower(trim((string)($_POST['target'] ?? '')));

admin_api_rate_limit_guard(
    $db,
    $admin,
    admin_api_scope('admin_media_api', $target !== '' ? $target : 'upload'),
    180,
    60,
    420,
    300
);

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
    admin_media_fail(422, 'Invalid upload target.');
}

$meta = $targets[$target];
$meta['image_target_kb'] = (int)($meta['image_target_kb'] ?? match ($target) {
    'logo' => 180,
    'favicon' => 96,
    'social-icon' => 160,
    'slider-image' => 520,
    default => 380,
});
$meta['image_max_dimension'] = (int)($meta['image_max_dimension'] ?? match ($target) {
    'logo' => 1800,
    'favicon' => 1024,
    'social-icon' => 1200,
    'slider-image' => 2600,
    default => 2200,
});

$uploads = admin_media_extract_uploads($_FILES);
if ($uploads === []) {
    admin_media_fail(422, 'No file uploaded.');
}

$relativeDir = str_replace('\\', '/', (string)$meta['dir']);
$absoluteDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
    admin_media_fail(500, 'Unable to create upload directory.');
}

$items = [];
$successCount = 0;
$failedCount = 0;

foreach ($uploads as $upload) {
    $safeName = admin_media_normalize_name((string)($upload['name'] ?? 'upload'));

    try {
        $item = admin_media_process_one_upload($upload, $target, $meta, $relativeDir, $absoluteDir);
        $item['status'] = 'ok';
        $items[] = $item;
        $successCount++;

        admin_api_log_security_event($db, $admin, 'media.upload', 'info', [
            'target' => $target,
            'path' => (string)$item['path'],
            'size' => (int)$item['size'],
            'mime' => (string)$item['mime'],
            'parser' => (string)$item['parser'],
            'compressed' => (bool)$item['compressed'],
        ]);
    } catch (Throwable $exception) {
        $items[] = [
            'status' => 'error',
            'target' => $target,
            'original_name' => $safeName,
            'message' => $exception->getMessage(),
        ];
        $failedCount++;
    }
}

$total = count($uploads);

if ($successCount === 0) {
    admin_media_fail(422, 'Unable to process uploaded files.', [
        'target' => $target,
        'total' => $total,
        'success' => 0,
        'failed' => $failedCount,
        'items' => $items,
    ]);
}

$firstSuccess = null;
foreach ($items as $item) {
    if (($item['status'] ?? '') === 'ok') {
        $firstSuccess = $item;
        break;
    }
}

$message = $total > 1
    ? "Processed {$successCount}/{$total} file(s)."
    : 'File uploaded successfully.';

http_response_code($failedCount > 0 ? 207 : 200);
echo json_encode([
    'ok' => true,
    'message' => $message,
    'payload' => [
        'path' => (string)($firstSuccess['path'] ?? ''),
        'target' => $target,
        'size' => (int)($firstSuccess['size'] ?? 0),
        'mime' => (string)($firstSuccess['mime'] ?? ''),
        'total' => $total,
        'success' => $successCount,
        'failed' => $failedCount,
        'items' => $items,
    ],
], JSON_UNESCAPED_SLASHES);
