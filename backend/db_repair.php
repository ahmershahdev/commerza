<?php

declare(strict_types=1);

require __DIR__ . '/data.php';

function db_repair_table_exists(mysqli $con, string $table): bool
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function db_repair_column_exists(mysqli $con, string $table, string $column): bool
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

$sqlPath = __DIR__ . '/database/commerza.sql';
$sql = is_file($sqlPath) ? (string)file_get_contents($sqlPath) : '';
if ($sql === '') {
    echo json_encode([
        'ok' => false,
        'message' => 'SQL schema file not found.',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`\s*\((.*?)\)\s*ENGINE=InnoDB[^;]*;/is', $sql, $matches, PREG_SET_ORDER);

$created = [];
$skipped = [];
$errors = [];

foreach ($matches as $match) {
    $table = strtolower((string)($match[1] ?? ''));
    $statement = (string)($match[0] ?? '');

    if ($table === '' || $statement === '') {
        continue;
    }

    if (db_repair_table_exists($con, $table)) {
        $skipped[] = $table;
        continue;
    }

    $statement = preg_replace('/^CREATE TABLE\s+`/i', 'CREATE TABLE IF NOT EXISTS `', $statement, 1) ?? $statement;
    try {
        $ok = $con->query($statement);
        if ($ok) {
            $created[] = $table;
        } else {
            $errors[] = [
                'table' => $table,
                'error' => (string)$con->error,
            ];
        }
    } catch (Throwable $exception) {
        $errors[] = [
            'table' => $table,
            'error' => $exception->getMessage(),
        ];
    }
}

$sliderFixes = [];

if (db_repair_table_exists($con, 'slider')) {
    if (!db_repair_column_exists($con, 'slider', 'subtitle')) {
        if ($con->query('ALTER TABLE slider ADD COLUMN subtitle VARCHAR(255) DEFAULT NULL AFTER title')) {
            $sliderFixes[] = 'added_subtitle';
        } else {
            $errors[] = ['table' => 'slider', 'error' => 'add_subtitle_failed: ' . (string)$con->error];
        }
    }

    if (!db_repair_column_exists($con, 'slider', 'cta_text_2')) {
        if ($con->query('ALTER TABLE slider ADD COLUMN cta_text_2 VARCHAR(80) DEFAULT NULL AFTER cta_url')) {
            $sliderFixes[] = 'added_cta_text_2';
        } else {
            $errors[] = ['table' => 'slider', 'error' => 'add_cta_text_2_failed: ' . (string)$con->error];
        }
    }

    if (!db_repair_column_exists($con, 'slider', 'cta_url_2')) {
        if ($con->query('ALTER TABLE slider ADD COLUMN cta_url_2 VARCHAR(255) DEFAULT NULL AFTER cta_text_2')) {
            $sliderFixes[] = 'added_cta_url_2';
        } else {
            $errors[] = ['table' => 'slider', 'error' => 'add_cta_url_2_failed: ' . (string)$con->error];
        }
    }

    if (!db_repair_column_exists($con, 'slider', 'overlay_opacity')) {
        if ($con->query('ALTER TABLE slider ADD COLUMN overlay_opacity DECIMAL(3,2) DEFAULT 0.40 AFTER cta_url_2')) {
            $sliderFixes[] = 'added_overlay_opacity';
        } else {
            $errors[] = ['table' => 'slider', 'error' => 'add_overlay_opacity_failed: ' . (string)$con->error];
        }
    }

    if (db_repair_column_exists($con, 'slider', 'page')) {
        if ($con->query('ALTER TABLE slider MODIFY page VARCHAR(100) NULL')) {
            $sliderFixes[] = 'page_nullable';
        } else {
            $errors[] = ['table' => 'slider', 'error' => 'modify_page_nullable_failed: ' . (string)$con->error];
        }
    }
}

if (db_repair_table_exists($con, 'live_product_viewers')) {
    if (!db_repair_column_exists($con, 'live_product_viewers', 'user_id')) {
        if ($con->query('ALTER TABLE live_product_viewers ADD COLUMN user_id INT DEFAULT NULL AFTER session_key')) {
            $sliderFixes[] = 'live_viewers_added_user_id';
        } else {
            $errors[] = ['table' => 'live_product_viewers', 'error' => 'add_user_id_failed: ' . (string)$con->error];
        }
    }

    if (db_repair_column_exists($con, 'live_product_viewers', 'page')) {
        if ($con->query('ALTER TABLE live_product_viewers MODIFY page VARCHAR(100) NULL DEFAULT NULL')) {
            $sliderFixes[] = 'live_viewers_page_nullable';
        } else {
            $errors[] = ['table' => 'live_product_viewers', 'error' => 'modify_page_nullable_failed: ' . (string)$con->error];
        }
    }

    $hasUserIndex = false;
    $idxStmt = $con->prepare(
        'SELECT COUNT(*) AS total
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );
    if ($idxStmt) {
        $tableName = 'live_product_viewers';
        $indexName = 'user_id';
        $idxStmt->bind_param('ss', $tableName, $indexName);
        $idxStmt->execute();
        $idxResult = $idxStmt->get_result();
        $idxRow = $idxResult ? $idxResult->fetch_assoc() : null;
        $idxStmt->close();
        $hasUserIndex = (int)($idxRow['total'] ?? 0) > 0;
    }

    if (!$hasUserIndex && db_repair_column_exists($con, 'live_product_viewers', 'user_id')) {
        if ($con->query('ALTER TABLE live_product_viewers ADD KEY user_id (user_id)')) {
            $sliderFixes[] = 'live_viewers_added_user_index';
        } else {
            $errors[] = ['table' => 'live_product_viewers', 'error' => 'add_user_index_failed: ' . (string)$con->error];
        }
    }
}

echo json_encode([
    'ok' => count($errors) === 0,
    'created_tables' => $created,
    'already_present_tables' => $skipped,
    'slider_fixes' => $sliderFixes,
    'errors' => $errors,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
