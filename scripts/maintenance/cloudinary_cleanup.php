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

function cloudinary_cleanup_print(string $message): void
{
    echo $message . PHP_EOL;
}

function cloudinary_cleanup_parse_options(array $argv): array
{
    $options = [
        'apply' => false,
        'prefix' => '',
        'limit' => 0,
    ];

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        $value = trim((string)$arg);
        $normalized = strtolower($value);

        if ($normalized === '--apply') {
            $options['apply'] = true;
            continue;
        }

        if ($normalized === '--help' || $normalized === '-h') {
            echo "Usage: php scripts/maintenance/cloudinary_cleanup.php [--apply] [--prefix=<folder>] [--limit=<count>]\n";
            echo "  --apply          Delete stale Cloudinary resources (default is preview only)\n";
            echo "  --prefix=<path>  Restrict cleanup to a specific folder prefix (defaults to COMMERZA_CLOUDINARY_FOLDER)\n";
            echo "  --limit=<count>  Process only the first N stale resources in this run (0 = all)\n";
            exit(0);
        }

        if (str_starts_with($normalized, '--prefix=')) {
            $rawPrefix = substr($value, strlen('--prefix='));
            $options['prefix'] = trim(str_replace('\\', '/', $rawPrefix), '/');
            continue;
        }

        if (str_starts_with($normalized, '--limit=')) {
            $rawLimit = substr($value, strlen('--limit='));
            $parsed = (int)trim($rawLimit);
            $options['limit'] = max(0, $parsed);
            continue;
        }
    }

    return $options;
}

function cloudinary_cleanup_db_connection(): ?mysqli
{
    $host = trim((string)(getenv('COMMERZA_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost'));
    $user = trim((string)(getenv('COMMERZA_DB_USER') ?: getenv('DB_USER') ?: 'root'));
    $db = trim((string)(getenv('COMMERZA_DB_NAME') ?: getenv('DB_NAME') ?: 'commerza'));

    $passRaw = getenv('COMMERZA_DB_PASS');
    if ($passRaw === false) {
        $passRaw = getenv('DB_PASS');
    }
    $pass = $passRaw === false ? '' : (string)$passRaw;

    $connection = @mysqli_connect($host, $user, $pass, $db);
    if (!($connection instanceof mysqli)) {
        return null;
    }

    mysqli_set_charset($connection, 'utf8mb4');

    return $connection;
}

function cloudinary_cleanup_identifier_safe(string $value): bool
{
    return preg_match('/^[a-z0-9_]+$/i', $value) === 1;
}

function cloudinary_cleanup_table_has_column(mysqli $con, string $table, string $column): bool
{
    if (!cloudinary_cleanup_identifier_safe($table) || !cloudinary_cleanup_identifier_safe($column)) {
        return false;
    }

    $tableEscaped = $con->real_escape_string($table);
    $columnEscaped = $con->real_escape_string($column);

    $query = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEscaped}' AND COLUMN_NAME = '{$columnEscaped}' LIMIT 1";
    $result = $con->query($query);

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function cloudinary_cleanup_collect_urls_from_table(mysqli $con, string $table, string $column): array
{
    if (!cloudinary_cleanup_table_has_column($con, $table, $column)) {
        return [];
    }

    if (!cloudinary_cleanup_identifier_safe($table) || !cloudinary_cleanup_identifier_safe($column)) {
        return [];
    }

    $urls = [];
    $query = "SELECT `{$column}` AS media_url FROM `{$table}` WHERE `{$column}` IS NOT NULL AND TRIM(`{$column}`) <> ''";
    $result = $con->query($query);
    if (!($result instanceof mysqli_result)) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $candidate = trim((string)($row['media_url'] ?? ''));
        if ($candidate === '') {
            continue;
        }

        if (function_exists('commerza_cloudinary_is_managed_url') && commerza_cloudinary_is_managed_url($candidate)) {
            $urls[] = $candidate;
        }
    }

    $result->free();

    return $urls;
}

function cloudinary_cleanup_collect_used_urls(string $projectRoot, mysqli $con): array
{
    $targets = [
        ['table' => 'products', 'column' => 'image'],
        ['table' => 'products', 'column' => 'video_url'],
        ['table' => 'product_trash', 'column' => 'image'],
        ['table' => 'product_trash', 'column' => 'video_url'],
        ['table' => 'slider', 'column' => 'image_url'],
        ['table' => 'slider', 'column' => 'video_url'],
        ['table' => 'social_links', 'column' => 'icon'],
        ['table' => 'site_settings', 'column' => 'setting_val'],
        ['table' => 'users', 'column' => 'profile_picture'],
        ['table' => 'product_review_images', 'column' => 'image_path'],
        ['table' => 'refund_requests', 'column' => 'evidence_path'],
        ['table' => 'order_items', 'column' => 'product_img'],
        ['table' => 'page_meta', 'column' => 'og_image'],
    ];

    $urls = [];
    foreach ($targets as $target) {
        $table = (string)$target['table'];
        $column = (string)$target['column'];
        $rows = cloudinary_cleanup_collect_urls_from_table($con, $table, $column);
        foreach ($rows as $url) {
            $urls[] = $url;
        }
    }

    $assetMapPath = $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'cloudinary_asset_map.php';
    if (is_file($assetMapPath)) {
        $assetMap = include $assetMapPath;
        if (is_array($assetMap)) {
            foreach ($assetMap as $value) {
                $candidate = trim((string)$value);
                if (
                    $candidate !== ''
                    && function_exists('commerza_cloudinary_is_managed_url')
                    && commerza_cloudinary_is_managed_url($candidate)
                ) {
                    $urls[] = $candidate;
                }
            }
        }
    }

    $unique = [];
    foreach ($urls as $url) {
        $url = trim((string)$url);
        if ($url !== '') {
            $unique[$url] = true;
        }
    }

    return array_keys($unique);
}

function cloudinary_cleanup_keep_index(array $urls): array
{
    $index = [
        'image' => [],
        'video' => [],
        'raw' => [],
    ];

    foreach ($urls as $url) {
        if (!function_exists('commerza_cloudinary_extract_asset_from_url')) {
            continue;
        }

        $asset = commerza_cloudinary_extract_asset_from_url((string)$url);
        if (!is_array($asset)) {
            continue;
        }

        $resourceType = strtolower(trim((string)($asset['resource_type'] ?? '')));
        $publicId = trim((string)($asset['public_id'] ?? ''));
        if ($publicId === '' || !isset($index[$resourceType])) {
            continue;
        }

        $index[$resourceType][$publicId] = true;
    }

    return $index;
}

function cloudinary_cleanup_find_stale_resources(array $remoteResources, array $keepIndex): array
{
    $stale = [];

    foreach ($remoteResources as $resource) {
        if (!is_array($resource)) {
            continue;
        }

        $resourceType = strtolower(trim((string)($resource['resource_type'] ?? '')));
        $publicId = trim((string)($resource['public_id'] ?? ''));

        if ($publicId === '' || !isset($keepIndex[$resourceType])) {
            continue;
        }

        if (!isset($keepIndex[$resourceType][$publicId])) {
            $stale[] = [
                'resource_type' => $resourceType,
                'public_id' => $publicId,
                'secure_url' => (string)($resource['secure_url'] ?? ''),
                'asset_id' => (string)($resource['asset_id'] ?? ''),
                'placeholder' => (bool)($resource['placeholder'] ?? false),
                'bytes' => (int)($resource['bytes'] ?? 0),
            ];
        }
    }

    return $stale;
}

function cloudinary_cleanup_is_rate_limit_message(string $message): bool
{
    $normalized = strtolower(trim($message));
    return $normalized !== '' && str_contains($normalized, 'rate limit');
}

$options = cloudinary_cleanup_parse_options($argv);
$config = commerza_cloudinary_config();

if (!(bool)($config['enabled'] ?? false)) {
    cloudinary_cleanup_print('Cloudinary is not fully configured.');
    cloudinary_cleanup_print('Required: COMMERZA_CLOUDINARY_ENABLED=1 plus cloud name, API key, and API secret.');
    exit(1);
}

$prefix = trim((string)$options['prefix']);
if ($prefix === '') {
    $prefix = trim((string)($config['folder'] ?? ''), '/');
}

if ($prefix === '') {
    cloudinary_cleanup_print('Refusing to run without a Cloudinary folder prefix. Set COMMERZA_CLOUDINARY_FOLDER or pass --prefix=...');
    exit(1);
}

$con = cloudinary_cleanup_db_connection();
if (!($con instanceof mysqli)) {
    cloudinary_cleanup_print('Unable to connect to database for used-URL discovery.');
    exit(1);
}

$usedUrls = cloudinary_cleanup_collect_used_urls($projectRoot, $con);
$con->close();

$keepIndex = cloudinary_cleanup_keep_index($usedUrls);
$listResult = commerza_cloudinary_list_all_resources_by_prefix($prefix, ['image', 'video', 'raw']);

$listingErrors = is_array($listResult['errors'] ?? null) ? $listResult['errors'] : [];
$listingFailed = !(bool)($listResult['ok'] ?? false) && !empty($listingErrors);
$listingRateLimited = false;

if ($listingFailed) {
    cloudinary_cleanup_print('Cloudinary listing errors:');
    foreach ($listingErrors as $error) {
        if (!is_array($error)) {
            continue;
        }
        $message = (string)($error['message'] ?? 'Unknown error');
        cloudinary_cleanup_print('- ' . (string)($error['resource_type'] ?? 'unknown') . ': ' . $message);
        if (cloudinary_cleanup_is_rate_limit_message($message)) {
            $listingRateLimited = true;
        }
    }
}

$remoteResources = is_array($listResult['resources'] ?? null) ? $listResult['resources'] : [];

if ($listingFailed && empty($remoteResources)) {
    if ($listingRateLimited) {
        cloudinary_cleanup_print('Aborting cleanup because Cloudinary listing is currently rate-limited. Re-run after reset.');
    } else {
        cloudinary_cleanup_print('Aborting cleanup because Cloudinary resources could not be listed.');
    }
    exit(2);
}

$staleResources = cloudinary_cleanup_find_stale_resources($remoteResources, $keepIndex);
$limit = (int)($options['limit'] ?? 0);

cloudinary_cleanup_print('Cloudinary cleanup scope prefix: ' . $prefix);
cloudinary_cleanup_print('Used Cloudinary URLs discovered: ' . count($usedUrls));
cloudinary_cleanup_print('Remote resources discovered: ' . count($remoteResources));
cloudinary_cleanup_print('Stale resources detected: ' . count($staleResources));
if ($limit > 0) {
    cloudinary_cleanup_print('Apply limit configured: ' . $limit . ' stale resource(s) max this run.');
}

if (!$options['apply']) {
    $previewCount = min(25, count($staleResources));
    if ($previewCount > 0) {
        cloudinary_cleanup_print('Preview stale resources (first ' . $previewCount . '):');
        for ($i = 0; $i < $previewCount; $i++) {
            $entry = $staleResources[$i];
            $isPlaceholder = (bool)($entry['placeholder'] ?? false);
            $suffix = $isPlaceholder ? ' [placeholder]' : '';
            cloudinary_cleanup_print('- ' . (string)$entry['resource_type'] . ' :: ' . (string)$entry['public_id'] . $suffix);
        }
    }

    cloudinary_cleanup_print('Dry-run complete. Re-run with --apply to delete stale resources.');
    exit(0);
}

$deleted = 0;
$notFound = 0;
$failed = [];
$backupVersionsPurged = 0;
$backupAssetsPurged = 0;
$backupPurgeFailed = [];
$rateLimited = false;

$resourcesToProcess = $staleResources;
if ($limit > 0 && count($resourcesToProcess) > $limit) {
    $resourcesToProcess = array_slice($resourcesToProcess, 0, $limit);
}

$grouped = [
    'image' => [],
    'video' => [],
    'raw' => [],
];

foreach ($resourcesToProcess as $resource) {
    $resourceType = strtolower(trim((string)($resource['resource_type'] ?? '')));
    $publicId = trim((string)($resource['public_id'] ?? ''));
    if ($publicId === '' || !isset($grouped[$resourceType])) {
        continue;
    }

    $grouped[$resourceType][$publicId] = $resource;
}

foreach ($grouped as $resourceType => $resourcesById) {
    if (empty($resourcesById)) {
        continue;
    }

    $publicIds = array_keys($resourcesById);
    $chunks = array_chunk($publicIds, 100);

    foreach ($chunks as $chunk) {
        $bulkDelete = commerza_cloudinary_delete_assets_by_public_ids($chunk, $resourceType, true);
        if (!(bool)($bulkDelete['ok'] ?? false)) {
            $message = (string)($bulkDelete['message'] ?? 'Bulk delete failed.');

            foreach ($chunk as $publicId) {
                $failed[] = [
                    'resource_type' => $resourceType,
                    'public_id' => $publicId,
                    'message' => $message,
                ];
            }

            if (cloudinary_cleanup_is_rate_limit_message($message)) {
                $rateLimited = true;
                break 2;
            }

            continue;
        }

        $deletedMap = (array)($bulkDelete['deleted'] ?? []);
        foreach ($chunk as $publicId) {
            $state = strtolower(trim((string)($deletedMap[$publicId] ?? 'deleted')));
            if ($state === 'not_found') {
                $notFound++;
            } elseif ($state === 'deleted' || $state === '') {
                $deleted++;
            } else {
                $message = 'Cloudinary reported an unexpected delete status: ' . $state;
                $failed[] = [
                    'resource_type' => $resourceType,
                    'public_id' => $publicId,
                    'message' => $message,
                ];
                continue;
            }

            $entry = $resourcesById[$publicId] ?? [];
            $shouldPurgeBackup = (bool)($entry['placeholder'] ?? false);
            if (!$shouldPurgeBackup) {
                continue;
            }

            $purgeResult = commerza_cloudinary_purge_placeholder_asset($resourceType, $publicId);
            if (!(bool)($purgeResult['ok'] ?? false)) {
                $purgeMessage = (string)($purgeResult['message'] ?? 'Backup purge failed.');
                $backupPurgeFailed[] = [
                    'resource_type' => $resourceType,
                    'public_id' => $publicId,
                    'message' => $purgeMessage,
                ];

                if (cloudinary_cleanup_is_rate_limit_message($purgeMessage)) {
                    $rateLimited = true;
                    break 3;
                }

                continue;
            }

            $deletedVersionIds = (array)($purgeResult['deleted_version_ids'] ?? []);
            $deletedVersionCount = count($deletedVersionIds);
            if ($deletedVersionCount > 0) {
                $backupAssetsPurged++;
                $backupVersionsPurged += $deletedVersionCount;
            }
        }
    }
}

cloudinary_cleanup_print('Cleanup applied.');
cloudinary_cleanup_print('Resources processed this run: ' . count($resourcesToProcess));
cloudinary_cleanup_print('Deleted resources: ' . $deleted);
cloudinary_cleanup_print('Already missing (not_found): ' . $notFound);
cloudinary_cleanup_print('Failed deletions: ' . count($failed));
cloudinary_cleanup_print('Placeholder assets with backup purge: ' . $backupAssetsPurged);
cloudinary_cleanup_print('Backup versions deleted: ' . $backupVersionsPurged);
cloudinary_cleanup_print('Failed backup purges: ' . count($backupPurgeFailed));
if ($rateLimited) {
    cloudinary_cleanup_print('Cleanup stopped early due to Cloudinary API rate limit. Re-run later to continue.');
}

if (!empty($failed)) {
    cloudinary_cleanup_print('Failed delete details:');
    foreach ($failed as $error) {
        cloudinary_cleanup_print('- ' . (string)$error['resource_type'] . ' :: ' . (string)$error['public_id'] . ' :: ' . (string)$error['message']);
    }
}

if (!empty($backupPurgeFailed)) {
    cloudinary_cleanup_print('Failed backup purge details:');
    foreach ($backupPurgeFailed as $error) {
        cloudinary_cleanup_print('- ' . (string)$error['resource_type'] . ' :: ' . (string)$error['public_id'] . ' :: ' . (string)$error['message']);
    }
}

if (!empty($failed) || !empty($backupPurgeFailed) || $rateLimited) {
    exit(2);
}

exit(0);
