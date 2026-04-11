<?php

function commerza_server_timing_sanitize_token(string $value, string $fallback): string
{
    $token = preg_replace('/[^a-zA-Z0-9_.-]+/', '', trim($value));
    if (!is_string($token) || $token === '') {
        return $fallback;
    }

    return substr($token, 0, 64);
}

function commerza_server_timing_sanitize_desc(string $value): string
{
    $desc = preg_replace('/[\x00-\x1F\x7F\"\\\\]+/', ' ', trim($value));
    if (!is_string($desc)) {
        return '';
    }

    $desc = preg_replace('/\s+/', ' ', $desc);
    if (!is_string($desc) || $desc === '') {
        return '';
    }

    return substr($desc, 0, 120);
}

function commerza_server_timing_start(string $metric = 'app', string $desc = ''): void
{
    if (!function_exists('hrtime')) {
        return;
    }

    $GLOBALS['commerza_server_timing'] = [
        'started_at_ns' => hrtime(true),
        'metric' => commerza_server_timing_sanitize_token($metric, 'app'),
        'desc' => commerza_server_timing_sanitize_desc($desc),
    ];
}

function commerza_server_timing_desc(string $desc): void
{
    if (!isset($GLOBALS['commerza_server_timing']) || !is_array($GLOBALS['commerza_server_timing'])) {
        return;
    }

    $GLOBALS['commerza_server_timing']['desc'] = commerza_server_timing_sanitize_desc($desc);
}

function commerza_server_timing_emit(?string $metric = null, ?string $desc = null): void
{
    if (headers_sent()) {
        return;
    }

    $timing = $GLOBALS['commerza_server_timing'] ?? null;
    if (!is_array($timing)) {
        return;
    }

    $startedAt = (int)($timing['started_at_ns'] ?? 0);
    if ($startedAt <= 0 || !function_exists('hrtime')) {
        return;
    }

    $metricToken = commerza_server_timing_sanitize_token(
        (string)($metric ?? ($timing['metric'] ?? 'app')),
        'app'
    );

    $descText = commerza_server_timing_sanitize_desc(
        (string)($desc ?? ($timing['desc'] ?? ''))
    );

    $durationMs = max(0.0, (hrtime(true) - $startedAt) / 1000000);
    $headerValue = $metricToken . ';dur=' . number_format($durationMs, 2, '.', '');

    if ($descText !== '') {
        $headerValue .= ';desc="' . $descText . '"';
    }

    header('Server-Timing: ' . $headerValue);
}
