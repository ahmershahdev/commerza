<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
if (!is_string($projectRoot) || $projectRoot === '') {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'bootstrap_helpers.php';
commerza_bootstrap_env();
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'cloudinary_service.php';

function cloudinary_sync_parse_options(array $argv): array
{
    $options = [
        'dry_run' => false,
        'skip_presets' => false,
        'skip_upload' => false,
        'skip_db' => false,
        'skip_sql' => false,
    ];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        $normalized = strtolower(trim((string)$arg));
        if ($normalized === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($normalized === '--skip-presets') {
            $options['skip_presets'] = true;
        } elseif ($normalized === '--skip-upload') {
            $options['skip_upload'] = true;
        } elseif ($normalized === '--skip-db') {
            $options['skip_db'] = true;
        } elseif ($normalized === '--skip-sql') {
            $options['skip_sql'] = true;
        } elseif ($normalized === '--help' || $normalized === '-h') {
            echo "Usage: php scripts/maintenance/cloudinary_sync.php [--dry-run] [--skip-presets] [--skip-upload] [--skip-db] [--skip-sql]\n";
            echo "  --dry-run      Show what would happen without making changes\n";
            echo "  --skip-presets Skip Cloudinary preset provisioning\n";
            echo "  --skip-upload  Skip frontend asset uploads\n";
            echo "  --skip-db      Skip DB reference rewrites\n";
            echo "  --skip-sql     Skip SQL dump URL rewrites\n";
            exit(0);
        }
    }

    return $options;
}

function cloudinary_sync_print(string $message): void
{
    echo $message . PHP_EOL;
}

function cloudinary_sync_folder_suffix_for_relative_path(string $relativePath, string $resourceType): string
{
    $normalized = trim(str_replace('\\', '/', $relativePath), '/');
    if ($normalized === '') {
        return $resourceType === 'video' ? 'library/videos' : 'library/images';
    }

    $assetsPrefix = 'frontend/assets/';
    if (!str_starts_with($normalized, $assetsPrefix)) {
        return $resourceType === 'video' ? 'library/videos' : 'library/images';
    }

    $fromAssets = substr($normalized, strlen($assetsPrefix));
    $fromAssets = trim(str_replace('\\', '/', (string)$fromAssets), '/');
    if ($fromAssets === '') {
        return $resourceType === 'video' ? 'library/videos' : 'library/images';
    }

    if (str_starts_with($fromAssets, 'images/users/')) {
        return 'users/profile-pictures';
    }

    if (str_starts_with($fromAssets, 'images/reviews/')) {
        return 'reviews/images';
    }

    if (str_starts_with($fromAssets, 'images/products/')) {
        $tail = trim((string)pathinfo(substr($fromAssets, strlen('images/products/')), PATHINFO_DIRNAME), '/.');
        return $tail !== '' ? 'products/images/' . $tail : 'products/images';
    }

    if (str_starts_with($fromAssets, 'videos/products/')) {
        $tail = trim((string)pathinfo(substr($fromAssets, strlen('videos/products/')), PATHINFO_DIRNAME), '/.');
        return $tail !== '' ? 'products/videos/' . $tail : 'products/videos';
    }

    if (str_starts_with($fromAssets, 'images/slider/')) {
        return 'slider/images';
    }

    if (str_starts_with($fromAssets, 'videos/slider/')) {
        return 'slider/videos';
    }

    if (str_starts_with($fromAssets, 'images/logo/')) {
        return 'brand/logo';
    }

    if (str_starts_with($fromAssets, 'images/favicon/')) {
        return 'brand/favicon';
    }

    if (str_starts_with($fromAssets, 'images/social/')) {
        return 'brand/social-icons';
    }

    if ($resourceType === 'video') {
        return 'library/videos';
    }

    return 'library/images';
}

function cloudinary_sync_collect_media_files(string $projectRoot): array
{
    $root = $projectRoot . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets';
    if (!is_dir($root)) {
        return [];
    }

    $imageExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'avif',
        'svg',
        'ico',
        'bmp',
        'tif',
        'tiff',
    ];
    $videoExtensions = [
        'mp4',
        'webm',
        'ogv',
        'mov',
        'm4v',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $items = [];

    foreach ($iterator as $fileInfo) {
        if (!($fileInfo instanceof SplFileInfo) || !$fileInfo->isFile()) {
            continue;
        }

        $absolutePath = $fileInfo->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($projectRoot) + 1));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            continue;
        }

        $extension = strtolower((string)pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            continue;
        }

        $resourceType = '';
        if (in_array($extension, $imageExtensions, true)) {
            $resourceType = 'image';
        } elseif (in_array($extension, $videoExtensions, true)) {
            $resourceType = 'video';
        }

        if ($resourceType === '') {
            continue;
        }

        $relativeFromAssets = str_replace('\\', '/', (string)substr($relativePath, strlen('frontend/assets/')));
        $folderSuffix = cloudinary_sync_folder_suffix_for_relative_path($relativePath, $resourceType);

        $stem = (string)pathinfo($relativeFromAssets, PATHINFO_FILENAME);
        $stem = preg_replace('/[^a-z0-9_-]+/i', '-', $stem) ?? 'asset';
        $stem = trim($stem, '-_');
        if ($stem === '') {
            $stem = 'asset';
        }

        $items[] = [
            'absolute_path' => $absolutePath,
            'relative_path' => $relativePath,
            'resource_type' => $resourceType,
            'folder_suffix' => $folderSuffix,
            'public_id' => $stem,
            'extension' => $extension,
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string)$a['relative_path'], (string)$b['relative_path']);
    });

    return $items;
}

function cloudinary_sync_db_connection(): ?mysqli
{
    $host = trim((string)(getenv('COMMERZA_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost'));
    $user = trim((string)(getenv('COMMERZA_DB_USER') ?: getenv('DB_USER') ?: 'root'));
    $db = trim((string)(getenv('COMMERZA_DB_NAME') ?: getenv('DB_NAME') ?: 'commerza'));

    $passEnv = getenv('COMMERZA_DB_PASS');
    if ($passEnv === false) {
        $passEnv = getenv('DB_PASS');
    }
    $pass = $passEnv === false ? '' : (string)$passEnv;

    $connection = @mysqli_connect($host, $user, $pass, $db);
    if (!($connection instanceof mysqli)) {
        return null;
    }

    mysqli_set_charset($connection, 'utf8mb4');

    return $connection;
}

function cloudinary_sync_identifier_safe(string $value): bool
{
    return preg_match('/^[a-z0-9_]+$/i', $value) === 1;
}

function cloudinary_sync_table_has_column(mysqli $con, string $table, string $column): bool
{
    static $cache = [];

    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
        return (bool)$cache[$key];
    }

    if (!cloudinary_sync_identifier_safe($table) || !cloudinary_sync_identifier_safe($column)) {
        $cache[$key] = false;
        return false;
    }

    $tableEscaped = $con->real_escape_string($table);
    $columnEscaped = $con->real_escape_string($column);

    $result = $con->query(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEscaped}' AND COLUMN_NAME = '{$columnEscaped}' LIMIT 1"
    );

    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $cache[$key] = $exists;

    return $exists;
}

function cloudinary_sync_rewrite_db_paths(mysqli $con, array $map): array
{
    $targets = [
        ['table' => 'site_settings', 'column' => 'setting_val'],
        ['table' => 'products', 'column' => 'image'],
        ['table' => 'products', 'column' => 'video_url'],
        ['table' => 'slider', 'column' => 'image_url'],
        ['table' => 'slider', 'column' => 'video_url'],
        ['table' => 'social_links', 'column' => 'icon'],
        ['table' => 'page_meta', 'column' => 'og_image'],
        ['table' => 'product_trash', 'column' => 'image'],
        ['table' => 'product_trash', 'column' => 'video_url'],
        ['table' => 'product_review_images', 'column' => 'image_path'],
        ['table' => 'users', 'column' => 'profile_picture'],
        ['table' => 'refund_requests', 'column' => 'evidence_path'],
        ['table' => 'order_items', 'column' => 'product_img'],
    ];

    $totalUpdates = 0;
    $details = [];

    foreach ($targets as $target) {
        $table = (string)$target['table'];
        $column = (string)$target['column'];

        if (!cloudinary_sync_table_has_column($con, $table, $column)) {
            continue;
        }

        if (!cloudinary_sync_identifier_safe($table) || !cloudinary_sync_identifier_safe($column)) {
            continue;
        }

        $sql = "UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            continue;
        }

        $updatedRows = 0;

        foreach ($map as $oldPath => $newUrl) {
            $oldPath = trim((string)$oldPath);
            $newUrl = trim((string)$newUrl);
            if ($oldPath === '' || $newUrl === '') {
                continue;
            }

            $stmt->bind_param('ss', $newUrl, $oldPath);
            if ($stmt->execute()) {
                $updatedRows += max(0, (int)$stmt->affected_rows);
            }

            if (!str_starts_with($oldPath, '/')) {
                $prefixed = '/' . ltrim($oldPath, '/');
                $stmt->bind_param('ss', $newUrl, $prefixed);
                if ($stmt->execute()) {
                    $updatedRows += max(0, (int)$stmt->affected_rows);
                }
            }
        }

        $stmt->close();

        if ($updatedRows > 0) {
            $totalUpdates += $updatedRows;
            $details[] = [
                'table' => $table,
                'column' => $column,
                'updated' => $updatedRows,
            ];
        }
    }

    return [
        'total_updates' => $totalUpdates,
        'details' => $details,
    ];
}

function cloudinary_sync_write_map_files(string $projectRoot, array $map): array
{
    $cacheDir = $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'cache';
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
        return [
            'ok' => false,
            'message' => 'Unable to create backend/cache directory for map files.',
        ];
    }

    ksort($map, SORT_STRING);

    $phpPath = $cacheDir . DIRECTORY_SEPARATOR . 'cloudinary_asset_map.php';
    $jsonPath = $cacheDir . DIRECTORY_SEPARATOR . 'cloudinary_asset_map.json';

    $phpBody = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($map, true) . ";\n";
    $jsonBody = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!is_string($jsonBody)) {
        $jsonBody = '{}';
    }

    $phpOk = file_put_contents($phpPath, $phpBody, LOCK_EX) !== false;
    $jsonOk = file_put_contents($jsonPath, $jsonBody . "\n", LOCK_EX) !== false;

    if (!$phpOk || !$jsonOk) {
        return [
            'ok' => false,
            'message' => 'Unable to write one or more map files.',
            'php_path' => $phpPath,
            'json_path' => $jsonPath,
        ];
    }

    return [
        'ok' => true,
        'php_path' => $phpPath,
        'json_path' => $jsonPath,
    ];
}

function cloudinary_sync_load_existing_map(string $projectRoot): array
{
    $mapPath = $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'cloudinary_asset_map.php';
    if (!is_file($mapPath)) {
        return [];
    }

    $loaded = include $mapPath;
    if (!is_array($loaded)) {
        return [];
    }

    $normalized = [];
    foreach ($loaded as $oldPath => $url) {
        $oldPath = trim((string)$oldPath);
        $url = trim((string)$url);
        if ($oldPath === '' || $url === '') {
            continue;
        }

        $normalized[$oldPath] = $url;
    }

    return $normalized;
}

function cloudinary_sync_rewrite_sql_dump(string $projectRoot, array $replacementMap): array
{
    $sqlPath = $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'commerza.sql';
    if (!is_file($sqlPath)) {
        return [
            'ok' => false,
            'message' => 'SQL dump file was not found.',
            'path' => $sqlPath,
            'replacements' => 0,
        ];
    }

    $content = file_get_contents($sqlPath);
    if (!is_string($content)) {
        return [
            'ok' => false,
            'message' => 'Unable to read SQL dump file.',
            'path' => $sqlPath,
            'replacements' => 0,
        ];
    }

    $pairs = [];
    foreach ($replacementMap as $old => $new) {
        $old = trim((string)$old);
        $new = trim((string)$new);
        if ($old === '' || $new === '') {
            continue;
        }

        $pairs[$old] = $new;
    }

    if (empty($pairs)) {
        return [
            'ok' => true,
            'message' => 'No SQL URL replacements were required.',
            'path' => $sqlPath,
            'replacements' => 0,
        ];
    }

    $keys = array_keys($pairs);
    usort($keys, static function (string $a, string $b): int {
        return strlen($b) <=> strlen($a);
    });

    $updated = $content;
    $replacementCount = 0;

    foreach ($keys as $oldValue) {
        $newValue = (string)$pairs[$oldValue];
        if ($oldValue === $newValue) {
            continue;
        }

        $count = 0;
        $updated = str_replace($oldValue, $newValue, $updated, $count);
        $replacementCount += max(0, $count);
    }

    if ($replacementCount === 0 || $updated === $content) {
        return [
            'ok' => true,
            'message' => 'SQL dump already matched current Cloudinary URLs.',
            'path' => $sqlPath,
            'replacements' => 0,
        ];
    }

    $writeOk = file_put_contents($sqlPath, $updated, LOCK_EX) !== false;
    if (!$writeOk) {
        return [
            'ok' => false,
            'message' => 'Unable to write SQL dump file with updated URLs.',
            'path' => $sqlPath,
            'replacements' => 0,
        ];
    }

    return [
        'ok' => true,
        'message' => 'SQL dump URL references updated successfully.',
        'path' => $sqlPath,
        'replacements' => $replacementCount,
    ];
}

$options = cloudinary_sync_parse_options($argv);
$config = commerza_cloudinary_config();

cloudinary_sync_print('Cloudinary sync started.');
cloudinary_sync_print('Project root: ' . $projectRoot);

if (!(bool)($config['enabled'] ?? false)) {
    cloudinary_sync_print('Cloudinary is not fully configured.');
    cloudinary_sync_print('Required: COMMERZA_CLOUDINARY_ENABLED=1 plus cloud name, API key, and API secret.');
    exit(1);
}

if (!(bool)$options['skip_presets']) {
    cloudinary_sync_print('Step 1/4: Ensuring Cloudinary upload presets...');
    if ((bool)$options['dry_run']) {
        cloudinary_sync_print('Dry-run: preset provisioning skipped.');
    } else {
        $presetResult = commerza_cloudinary_ensure_default_presets();
        if (!(bool)($presetResult['ok'] ?? false)) {
            cloudinary_sync_print('Preset provisioning failed: ' . (string)($presetResult['message'] ?? 'Unknown error'));
            exit(2);
        }
        cloudinary_sync_print((string)($presetResult['message'] ?? 'Cloudinary presets ready.'));
    }
} else {
    cloudinary_sync_print('Step 1/4: Preset provisioning skipped by flag.');
}

$existingMap = cloudinary_sync_load_existing_map($projectRoot);
$assetMap = [];
$dbRewriteMap = [];
$uploadStats = [
    'total' => 0,
    'uploaded' => 0,
    'failed' => 0,
    'errors' => [],
];

if (!(bool)$options['skip_upload']) {
    cloudinary_sync_print('Step 2/4: Uploading frontend image/video assets...');
    $mediaFiles = cloudinary_sync_collect_media_files($projectRoot);
    $uploadStats['total'] = count($mediaFiles);

    cloudinary_sync_print('Discovered media files: ' . $uploadStats['total']);

    if ((bool)$options['dry_run']) {
        foreach ($mediaFiles as $item) {
            cloudinary_sync_print('[dry-run] upload ' . (string)$item['relative_path']);
        }
        $uploadStats['uploaded'] = $uploadStats['total'];
    } else {
        foreach ($mediaFiles as $index => $item) {
            $relativePath = (string)$item['relative_path'];
            $resourceType = (string)$item['resource_type'];
            $folderSuffix = (string)$item['folder_suffix'];
            $publicId = (string)$item['public_id'];
            $absolutePath = (string)$item['absolute_path'];

            $preset = $resourceType === 'video'
                ? (string)($config['upload_preset_video'] ?? '')
                : (string)($config['upload_preset_image'] ?? '');

            $result = commerza_cloudinary_upload_file($absolutePath, [
                'resource_type' => $resourceType,
                'folder' => commerza_cloudinary_target_folder($folderSuffix),
                'public_id' => $publicId,
                'upload_preset' => $preset,
                'overwrite' => true,
                'invalidate' => true,
                'tags' => ['commerza', 'bulk-sync', $resourceType],
            ]);

            if (!(bool)($result['ok'] ?? false)) {
                $uploadStats['failed']++;
                $uploadStats['errors'][] = [
                    'file' => $relativePath,
                    'message' => (string)($result['message'] ?? 'Upload failed.'),
                ];
                cloudinary_sync_print(sprintf('[%d/%d] fail %s :: %s', $index + 1, $uploadStats['total'], $relativePath, (string)($result['message'] ?? 'Upload failed.')));
                continue;
            }

            $url = trim((string)($result['secure_url'] ?? ''));
            if ($url === '') {
                $url = trim((string)($result['url'] ?? ''));
            }

            if ($url === '') {
                $uploadStats['failed']++;
                $uploadStats['errors'][] = [
                    'file' => $relativePath,
                    'message' => 'Cloudinary did not return a delivery URL.',
                ];
                cloudinary_sync_print(sprintf('[%d/%d] fail %s :: Missing secure_url', $index + 1, $uploadStats['total'], $relativePath));
                continue;
            }

            $assetMap[$relativePath] = $url;
            $dbRewriteMap[$relativePath] = $url;
            $dbRewriteMap['/' . ltrim($relativePath, '/')] = $url;

            $previousUrl = trim((string)($existingMap[$relativePath] ?? ''));
            if ($previousUrl !== '' && $previousUrl !== $url) {
                $dbRewriteMap[$previousUrl] = $url;
            }

            $uploadStats['uploaded']++;
            cloudinary_sync_print(sprintf('[%d/%d] ok   %s', $index + 1, $uploadStats['total'], $relativePath));
        }
    }

    cloudinary_sync_print(sprintf(
        'Upload summary: %d total | %d uploaded | %d failed',
        $uploadStats['total'],
        $uploadStats['uploaded'],
        $uploadStats['failed']
    ));
} else {
    cloudinary_sync_print('Step 2/4: Upload step skipped by flag.');
}

$dbSummary = [
    'total_updates' => 0,
    'details' => [],
];

if (!(bool)$options['skip_db']) {
    cloudinary_sync_print('Step 3/4: Rewriting DB media references...');

    if ((bool)$options['dry_run']) {
        cloudinary_sync_print('Dry-run: DB rewrite skipped.');
    } elseif (!empty($dbRewriteMap)) {
        $con = cloudinary_sync_db_connection();
        if (!($con instanceof mysqli)) {
            cloudinary_sync_print('DB rewrite skipped: database connection unavailable.');
        } else {
            $dbSummary = cloudinary_sync_rewrite_db_paths($con, $dbRewriteMap);
            $con->close();
            cloudinary_sync_print('DB rows updated: ' . (int)$dbSummary['total_updates']);
        }
    } else {
        cloudinary_sync_print('DB rewrite skipped: no URL replacement map available.');
    }
} else {
    cloudinary_sync_print('Step 3/4: DB rewrite skipped by flag.');
}

if (!(bool)$options['dry_run'] && !empty($assetMap)) {
    $mapWrite = cloudinary_sync_write_map_files($projectRoot, $assetMap);
    if ((bool)($mapWrite['ok'] ?? false)) {
        cloudinary_sync_print('Asset map written to: ' . (string)$mapWrite['php_path']);
        cloudinary_sync_print('Asset map JSON written to: ' . (string)$mapWrite['json_path']);
    } else {
        cloudinary_sync_print('Asset map write warning: ' . (string)($mapWrite['message'] ?? 'Unknown write failure'));
    }
}

if (!(bool)$options['skip_sql']) {
    cloudinary_sync_print('Step 4/4: Rewriting SQL dump media references...');

    if ((bool)$options['dry_run']) {
        cloudinary_sync_print('Dry-run: SQL dump rewrite skipped.');
    } elseif (!empty($dbRewriteMap)) {
        $sqlRewrite = cloudinary_sync_rewrite_sql_dump($projectRoot, $dbRewriteMap);
        if ((bool)($sqlRewrite['ok'] ?? false)) {
            cloudinary_sync_print('SQL dump rewrites: ' . (int)($sqlRewrite['replacements'] ?? 0));
            cloudinary_sync_print('SQL dump path: ' . (string)($sqlRewrite['path'] ?? 'backend/database/commerza.sql'));
        } else {
            cloudinary_sync_print('SQL rewrite warning: ' . (string)($sqlRewrite['message'] ?? 'Unknown SQL rewrite failure'));
        }
    } else {
        cloudinary_sync_print('SQL rewrite skipped: no URL replacement map available.');
    }
} else {
    cloudinary_sync_print('Step 4/4: SQL dump rewrite skipped by flag.');
}

if (!empty($uploadStats['errors'])) {
    cloudinary_sync_print('Upload errors:');
    foreach ($uploadStats['errors'] as $error) {
        cloudinary_sync_print('- ' . (string)($error['file'] ?? '') . ' :: ' . (string)($error['message'] ?? 'Unknown error'));
    }
}

cloudinary_sync_print('Done.');

if ((int)$uploadStats['failed'] > 0) {
    exit(3);
}

exit(0);
