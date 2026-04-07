<?php

declare(strict_types=1);

function commerza_media_allowed_image_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
}

function commerza_media_normalize_upload_name(string $name, string $fallback = 'image.webp'): string
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return $fallback;
    }

    $cleaned = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $trimmed);
    if (!is_string($cleaned)) {
        return $fallback;
    }

    $cleaned = trim($cleaned, '-. ');
    if ($cleaned === '') {
        return $fallback;
    }

    return $cleaned;
}

function commerza_media_image_from_upload(string $tmpPath, string $mime)
{
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        return @imagecreatefromjpeg($tmpPath);
    }

    if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($tmpPath);
    }

    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($tmpPath);
    }

    if ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
        return @imagecreatefromgif($tmpPath);
    }

    return false;
}

function commerza_media_prepare_canvas(int $width, int $height)
{
    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas) {
        return false;
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);

    return $canvas;
}

function commerza_media_resize_for_limit($image, int $maxDimension): array
{
    $width = imagesx($image);
    $height = imagesy($image);

    if ($width <= 0 || $height <= 0 || $maxDimension <= 0) {
        return [$image, max(1, $width), max(1, $height)];
    }

    $largestSide = max($width, $height);
    if ($largestSide <= $maxDimension) {
        return [$image, $width, $height];
    }

    $scale = $maxDimension / $largestSide;
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));

    $resized = commerza_media_prepare_canvas($newWidth, $newHeight);
    if (!$resized) {
        return [$image, $width, $height];
    }

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    return [$resized, $newWidth, $newHeight];
}

function commerza_media_encode_webp_binary($image, int $targetBytes): ?string
{
    $qualities = [84, 80, 76, 72, 68, 64, 60, 56, 52, 48];
    $bestBinary = '';
    $bestSize = 0;

    foreach ($qualities as $quality) {
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $binary = ob_get_clean();

        if (!$ok || !is_string($binary) || $binary === '') {
            continue;
        }

        $binarySize = strlen($binary);
        if ($binarySize <= $targetBytes) {
            return $binary;
        }

        if ($bestBinary === '' || $binarySize < $bestSize) {
            $bestBinary = $binary;
            $bestSize = $binarySize;
        }
    }

    return $bestBinary !== '' ? $bestBinary : null;
}

function commerza_media_convert_upload_to_webp(
    string $tmpPath,
    string $mime,
    int $targetKb = 280,
    int $maxDimension = 2200
): array {
    $allowedMimes = commerza_media_allowed_image_mimes();

    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        $fallbackBinary = @file_get_contents($tmpPath);
        if (!is_string($fallbackBinary) || $fallbackBinary === '') {
            return [
                'ok' => false,
                'message' => 'Unable to parse uploaded image.',
            ];
        }

        return [
            'ok' => true,
            'binary' => $fallbackBinary,
            'bytes' => strlen($fallbackBinary),
            'width' => 0,
            'height' => 0,
            'mime' => $mime,
            'extension' => $allowedMimes[$mime] ?? 'bin',
            'parser' => 'image-pass-through',
            'compressed' => false,
        ];
    }

    $image = commerza_media_image_from_upload($tmpPath, $mime);
    if (!$image) {
        $fallbackBinary = @file_get_contents($tmpPath);
        if (is_string($fallbackBinary) && $fallbackBinary !== '') {
            return [
                'ok' => true,
                'binary' => $fallbackBinary,
                'bytes' => strlen($fallbackBinary),
                'width' => 0,
                'height' => 0,
                'mime' => $mime,
                'extension' => $allowedMimes[$mime] ?? 'bin',
                'parser' => 'image-pass-through',
                'compressed' => false,
            ];
        }

        return [
            'ok' => false,
            'message' => 'Unable to parse uploaded image.',
        ];
    }

    [$workingImage, $width, $height] = commerza_media_resize_for_limit(
        $image,
        max(256, $maxDimension)
    );

    $targetBytes = max(60, $targetKb) * 1024;
    $binary = commerza_media_encode_webp_binary($workingImage, $targetBytes);
    imagedestroy($workingImage);

    if (!is_string($binary) || $binary === '') {
        return [
            'ok' => false,
            'message' => 'Unable to encode image as WebP.',
        ];
    }

    return [
        'ok' => true,
        'binary' => $binary,
        'bytes' => strlen($binary),
        'width' => $width,
        'height' => $height,
        'mime' => 'image/webp',
        'extension' => 'webp',
        'parser' => 'image-webp-parser-compressor',
        'compressed' => true,
    ];
}
