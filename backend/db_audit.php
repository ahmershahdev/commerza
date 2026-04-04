<?php

declare(strict_types=1);

require __DIR__ . '/data.php';

$sqlPath = __DIR__ . '/database/commerza.sql';
$sql = is_file($sqlPath) ? (string)file_get_contents($sqlPath) : '';

preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`/i', $sql, $matches);
$expected = array_values(array_unique(array_map(static fn($v) => strtolower((string)$v), $matches[1] ?? [])));
sort($expected);

$actual = [];
$result = $con->query('SHOW TABLES');
while ($result && ($row = $result->fetch_row())) {
    $actual[] = strtolower((string)$row[0]);
}
sort($actual);

$missing = array_values(array_diff($expected, $actual));
$extra = array_values(array_diff($actual, $expected));

$sliderColumns = [];
$sliderResult = $con->query('SHOW COLUMNS FROM slider');
while ($sliderResult && ($row = $sliderResult->fetch_assoc())) {
    $sliderColumns[] = (string)($row['Field'] ?? '');
}

echo json_encode([
    'expected_count' => count($expected),
    'actual_count' => count($actual),
    'missing_tables' => $missing,
    'extra_tables' => $extra,
    'slider_columns' => $sliderColumns,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
