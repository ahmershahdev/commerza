<?php

declare(strict_types=1);

function commerza_cloudinary_env(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    if ($value === false) {
        return $fallback;
    }

    return trim((string)$value);
}

function commerza_cloudinary_env_flag(string $key, bool $default = false): bool
{
    $raw = commerza_cloudinary_env($key, $default ? '1' : '0');
    $normalized = strtolower(trim($raw));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function commerza_cloudinary_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $cloudName = commerza_cloudinary_env('COMMERZA_CLOUDINARY_CLOUD_NAME');
    if (preg_match('/^replace_with_/i', $cloudName) === 1) {
        $cloudName = '';
    }

    $apiKey = commerza_cloudinary_env('COMMERZA_CLOUDINARY_API_KEY');
    $apiSecret = commerza_cloudinary_env('COMMERZA_CLOUDINARY_API_SECRET');

    $folder = trim(str_replace('\\', '/', commerza_cloudinary_env('COMMERZA_CLOUDINARY_FOLDER', 'commerza')), '/');
    $uploadPresetImage = commerza_cloudinary_env('COMMERZA_CLOUDINARY_UPLOAD_PRESET_IMAGE', 'commerza_image_optimized');
    $uploadPresetVideo = commerza_cloudinary_env('COMMERZA_CLOUDINARY_UPLOAD_PRESET_VIDEO', 'commerza_video_optimized');
    $imageTransformation = commerza_cloudinary_env(
        'COMMERZA_CLOUDINARY_IMAGE_TRANSFORMATION',
        'f_auto,q_auto,c_limit,w_2200,h_2200'
    );
    $videoTransformation = commerza_cloudinary_env(
        'COMMERZA_CLOUDINARY_VIDEO_TRANSFORMATION',
        'f_auto,q_auto:good,vc_auto'
    );

    $timeout = (int)commerza_cloudinary_env('COMMERZA_CLOUDINARY_TIMEOUT', '45');
    if ($timeout < 10) {
        $timeout = 10;
    }
    if ($timeout > 180) {
        $timeout = 180;
    }

    $enabled = commerza_cloudinary_env_flag('COMMERZA_CLOUDINARY_ENABLED', false)
        && $cloudName !== ''
        && $apiKey !== ''
        && $apiSecret !== '';

    $config = [
        'enabled' => $enabled,
        'cloud_name' => $cloudName,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'folder' => $folder,
        'upload_preset_image' => $uploadPresetImage,
        'upload_preset_video' => $uploadPresetVideo,
        'image_transformation' => $imageTransformation,
        'video_transformation' => $videoTransformation,
        'timeout' => $timeout,
    ];

    return $config;
}

function commerza_cloudinary_is_enabled(): bool
{
    $config = commerza_cloudinary_config();

    return (bool)($config['enabled'] ?? false);
}

function commerza_cloudinary_target_folder(string $suffix = ''): string
{
    $config = commerza_cloudinary_config();
    $prefix = trim((string)($config['folder'] ?? ''), '/');
    $tail = trim(str_replace('\\', '/', $suffix), '/');

    if ($prefix === '') {
        return $tail;
    }

    if ($tail === '') {
        return $prefix;
    }

    return $prefix . '/' . $tail;
}

function commerza_cloudinary_sign_params(array $params, string $apiSecret): string
{
    $normalized = [];

    foreach ($params as $key => $value) {
        $key = trim((string)$key);
        if ($key === '' || $key === 'file' || $key === 'api_key' || $key === 'resource_type' || $value === null) {
            continue;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = implode(',', array_map(static function ($entry): string {
                return trim((string)$entry);
            }, $value));
        } else {
            $value = trim((string)$value);
        }

        if ($value === '') {
            continue;
        }

        $normalized[$key] = $value;
    }

    ksort($normalized, SORT_STRING);

    $segments = [];
    foreach ($normalized as $key => $value) {
        $segments[] = $key . '=' . $value;
    }

    $toSign = implode('&', $segments);

    return sha1($toSign . $apiSecret);
}

function commerza_cloudinary_http_form_request(
    string $method,
    string $url,
    array $fields,
    int $timeout,
    string $basicAuth = ''
): array {
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'json' => null,
            'error' => 'cURL extension is not available on this server.',
        ];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'json' => null,
            'error' => 'Unable to initialize cURL request.',
        ];
    }

    $method = strtoupper(trim($method));
    if ($method === '') {
        $method = 'POST';
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => max(10, $timeout),
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $fields,
    ];

    if ($basicAuth !== '') {
        $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $options[CURLOPT_USERPWD] = $basicAuth;
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $bodyText = is_string($body) ? $body : '';
    $json = null;
    if ($bodyText !== '') {
        $decoded = json_decode($bodyText, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    if ($curlError !== '') {
        return [
            'ok' => false,
            'status' => $status,
            'body' => $bodyText,
            'json' => $json,
            'error' => $curlError,
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $bodyText,
        'json' => $json,
        'error' => '',
    ];
}

function commerza_cloudinary_upload_file(string $filePath, array $options = []): array
{
    $config = commerza_cloudinary_config();

    if (!(bool)($config['enabled'] ?? false)) {
        return [
            'ok' => false,
            'message' => 'Cloudinary is not fully configured.',
        ];
    }

    $absolutePath = realpath($filePath);
    if (!is_string($absolutePath) || $absolutePath === '' || !is_file($absolutePath)) {
        return [
            'ok' => false,
            'message' => 'Upload file does not exist.',
        ];
    }

    if (!class_exists('CURLFile') && !function_exists('curl_file_create')) {
        return [
            'ok' => false,
            'message' => 'CURLFile support is not available on this server.',
        ];
    }

    $resourceType = strtolower(trim((string)($options['resource_type'] ?? 'auto')));
    if (!in_array($resourceType, ['image', 'video', 'raw', 'auto'], true)) {
        $resourceType = 'auto';
    }

    $folder = trim(str_replace('\\', '/', (string)($options['folder'] ?? '')), '/');
    $publicId = trim(str_replace('\\', '/', (string)($options['public_id'] ?? '')), '/');
    $uploadPreset = trim((string)($options['upload_preset'] ?? ''));
    $tags = $options['tags'] ?? '';
    $context = trim((string)($options['context'] ?? ''));
    $overwrite = (bool)($options['overwrite'] ?? false);
    $invalidate = (bool)($options['invalidate'] ?? true);

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = trim((string)finfo_file($finfo, $absolutePath));
            finfo_close($finfo);
        }
    }

    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = trim((string)mime_content_type($absolutePath));
    }

    $fileName = basename($absolutePath);
    $curlFile = class_exists('CURLFile')
        ? new CURLFile($absolutePath, $mime !== '' ? $mime : null, $fileName)
        : curl_file_create($absolutePath, $mime !== '' ? $mime : null, $fileName);

    $timestamp = (string)time();

    $params = [
        'timestamp' => $timestamp,
        'overwrite' => $overwrite ? 'true' : 'false',
        'invalidate' => $invalidate ? 'true' : 'false',
    ];

    if ($folder !== '') {
        $params['folder'] = $folder;
    }

    if ($publicId !== '') {
        $params['public_id'] = $publicId;
    }

    if ($uploadPreset !== '') {
        $params['upload_preset'] = $uploadPreset;
    }

    if (is_array($tags)) {
        $tags = implode(',', array_map(static function ($entry): string {
            return trim((string)$entry);
        }, $tags));
    }
    $tags = trim((string)$tags);
    if ($tags !== '') {
        $params['tags'] = $tags;
    }

    if ($context !== '') {
        $params['context'] = $context;
    }

    $signature = commerza_cloudinary_sign_params($params, (string)$config['api_secret']);

    $payload = $params;
    $payload['api_key'] = (string)$config['api_key'];
    $payload['signature'] = $signature;
    $payload['file'] = $curlFile;

    $endpoint = sprintf(
        'https://api.cloudinary.com/v1_1/%s/%s/upload',
        rawurlencode((string)$config['cloud_name']),
        $resourceType
    );

    $response = commerza_cloudinary_http_form_request(
        'POST',
        $endpoint,
        $payload,
        (int)$config['timeout']
    );

    $json = is_array($response['json'] ?? null) ? $response['json'] : [];
    if (!(bool)($response['ok'] ?? false)) {
        $message = trim((string)($json['error']['message'] ?? ''));
        if ($message === '') {
            $message = trim((string)($response['error'] ?? ''));
        }
        if ($message === '') {
            $message = 'Cloudinary upload request failed.';
        }

        return [
            'ok' => false,
            'message' => $message,
            'status' => (int)($response['status'] ?? 0),
            'response' => $json,
        ];
    }

    $secureUrl = trim((string)($json['secure_url'] ?? ''));
    $fallbackUrl = trim((string)($json['url'] ?? ''));
    if ($secureUrl === '' && $fallbackUrl === '') {
        return [
            'ok' => false,
            'message' => 'Cloudinary response did not include a URL.',
            'status' => (int)($response['status'] ?? 0),
            'response' => $json,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Uploaded to Cloudinary.',
        'status' => (int)($response['status'] ?? 0),
        'asset_id' => (string)($json['asset_id'] ?? ''),
        'public_id' => (string)($json['public_id'] ?? ''),
        'resource_type' => (string)($json['resource_type'] ?? $resourceType),
        'format' => (string)($json['format'] ?? ''),
        'bytes' => (int)($json['bytes'] ?? 0),
        'width' => (int)($json['width'] ?? 0),
        'height' => (int)($json['height'] ?? 0),
        'duration' => (float)($json['duration'] ?? 0),
        'url' => $fallbackUrl,
        'secure_url' => $secureUrl !== '' ? $secureUrl : $fallbackUrl,
        'response' => $json,
    ];
}

function commerza_cloudinary_upsert_upload_preset(string $name, array $definition): array
{
    $config = commerza_cloudinary_config();

    if (!(bool)($config['enabled'] ?? false)) {
        return [
            'ok' => false,
            'message' => 'Cloudinary is not fully configured.',
        ];
    }

    $presetName = trim($name);
    if ($presetName === '') {
        return [
            'ok' => false,
            'message' => 'Preset name is required.',
        ];
    }

    $payload = [];
    foreach ($definition as $key => $value) {
        $payloadKey = trim((string)$key);
        if ($payloadKey === '' || $value === null) {
            continue;
        }

        if (is_bool($value)) {
            $payload[$payloadKey] = $value ? 'true' : 'false';
            continue;
        }

        if (is_array($value)) {
            $payload[$payloadKey] = implode(',', array_map(static function ($entry): string {
                return trim((string)$entry);
            }, $value));
            continue;
        }

        $payload[$payloadKey] = trim((string)$value);
    }

    $auth = (string)$config['api_key'] . ':' . (string)$config['api_secret'];
    $base = 'https://api.cloudinary.com/v1_1/' . rawurlencode((string)$config['cloud_name']) . '/upload_presets';

    $update = commerza_cloudinary_http_form_request(
        'PUT',
        $base . '/' . rawurlencode($presetName),
        $payload,
        (int)$config['timeout'],
        $auth
    );

    if ((bool)($update['ok'] ?? false)) {
        return [
            'ok' => true,
            'message' => 'Preset updated.',
            'action' => 'updated',
            'status' => (int)($update['status'] ?? 0),
            'response' => (array)($update['json'] ?? []),
        ];
    }

    $createPayload = $payload;
    $createPayload['name'] = $presetName;

    $create = commerza_cloudinary_http_form_request(
        'POST',
        $base,
        $createPayload,
        (int)$config['timeout'],
        $auth
    );

    if ((bool)($create['ok'] ?? false)) {
        return [
            'ok' => true,
            'message' => 'Preset created.',
            'action' => 'created',
            'status' => (int)($create['status'] ?? 0),
            'response' => (array)($create['json'] ?? []),
        ];
    }

    $updateJson = is_array($update['json'] ?? null) ? $update['json'] : [];
    $createJson = is_array($create['json'] ?? null) ? $create['json'] : [];
    $message = trim((string)($createJson['error']['message'] ?? ''));
    if ($message === '') {
        $message = trim((string)($updateJson['error']['message'] ?? ''));
    }
    if ($message === '') {
        $message = trim((string)($create['error'] ?? ''));
    }
    if ($message === '') {
        $message = 'Unable to create or update Cloudinary preset.';
    }

    return [
        'ok' => false,
        'message' => $message,
        'status' => (int)($create['status'] ?? ($update['status'] ?? 0)),
        'response' => [
            'update' => $updateJson,
            'create' => $createJson,
        ],
    ];
}

function commerza_cloudinary_ensure_default_presets(): array
{
    $config = commerza_cloudinary_config();

    if (!(bool)($config['enabled'] ?? false)) {
        return [
            'ok' => false,
            'message' => 'Cloudinary is not fully configured.',
            'presets' => [],
        ];
    }

    $imagePresetName = trim((string)($config['upload_preset_image'] ?? ''));
    $videoPresetName = trim((string)($config['upload_preset_video'] ?? ''));

    if ($imagePresetName === '' || $videoPresetName === '') {
        return [
            'ok' => false,
            'message' => 'Cloudinary preset names are missing from environment settings.',
            'presets' => [],
        ];
    }

    $imageDefinition = [
        'unsigned' => false,
        'use_filename' => false,
        'unique_filename' => true,
        'overwrite' => false,
        'invalidate' => true,
        'allowed_formats' => 'jpg,jpeg,png,webp,gif,avif,svg,ico,bmp,tif,tiff',
    ];

    $videoDefinition = [
        'unsigned' => false,
        'use_filename' => false,
        'unique_filename' => true,
        'overwrite' => false,
        'invalidate' => true,
        'allowed_formats' => 'mp4,m4v,mov,webm,ogv',
    ];

    $imageTransformation = trim((string)($config['image_transformation'] ?? ''));
    if ($imageTransformation !== '') {
        $imageDefinition['transformation'] = $imageTransformation;
    }

    $videoTransformation = trim((string)($config['video_transformation'] ?? ''));
    if ($videoTransformation !== '') {
        $videoDefinition['transformation'] = $videoTransformation;
    }

    $imageResult = commerza_cloudinary_upsert_upload_preset($imagePresetName, $imageDefinition);
    $videoResult = commerza_cloudinary_upsert_upload_preset($videoPresetName, $videoDefinition);

    $ok = (bool)($imageResult['ok'] ?? false) && (bool)($videoResult['ok'] ?? false);

    return [
        'ok' => $ok,
        'message' => $ok
            ? 'Cloudinary upload presets are ready.'
            : 'Cloudinary upload presets could not be fully provisioned.',
        'presets' => [
            'image' => [
                'name' => $imagePresetName,
                'result' => $imageResult,
            ],
            'video' => [
                'name' => $videoPresetName,
                'result' => $videoResult,
            ],
        ],
    ];
}
