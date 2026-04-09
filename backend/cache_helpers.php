<?php

declare(strict_types=1);

function commerza_cache_env(string $key, string $fallback = ''): string
{
    $value = getenv($key);
    if ($value === false) {
        return $fallback;
    }

    $trimmed = trim((string)$value);
    return $trimmed !== '' ? $trimmed : $fallback;
}

function commerza_cache_enabled(): bool
{
    static $enabled = null;

    if (is_bool($enabled)) {
        return $enabled;
    }

    $raw = strtolower(commerza_cache_env('COMMERZA_CACHE_ENABLED', '1'));
    $enabled = !in_array($raw, ['0', 'false', 'off', 'no', 'disabled'], true);

    return $enabled;
}

function commerza_cache_namespace(): string
{
    static $namespace = null;

    if (is_string($namespace) && $namespace !== '') {
        return $namespace;
    }

    $candidate = commerza_cache_env('COMMERZA_CACHE_NAMESPACE', 'commerza:v1');
    $candidate = preg_replace('/[^a-zA-Z0-9:_-]+/', '_', $candidate);
    if (!is_string($candidate) || trim($candidate) === '') {
        $candidate = 'commerza:v1';
    }

    $namespace = trim($candidate, ':');
    if ($namespace === '') {
        $namespace = 'commerza:v1';
    }

    return $namespace;
}

function commerza_cache_compose_key(string $key): string
{
    $normalized = trim($key);
    if ($normalized === '') {
        $normalized = 'default';
    }

    return commerza_cache_namespace() . ':' . $normalized;
}

function commerza_cache_serialize_payload($value): string
{
    return base64_encode(serialize(['value' => $value]));
}

function commerza_cache_deserialize_payload(string $payload, bool &$ok)
{
    $ok = false;

    $binary = base64_decode($payload, true);
    if (!is_string($binary) || $binary === '') {
        return null;
    }

    $decoded = @unserialize($binary, ['allowed_classes' => false]);
    if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
        return null;
    }

    $ok = true;
    return $decoded['value'];
}

function commerza_cache_apcu_available(): bool
{
    static $available = null;

    if (is_bool($available)) {
        return $available;
    }

    $available = function_exists('apcu_fetch') && function_exists('apcu_store') && (bool)ini_get('apc.enabled');
    return $available;
}

function commerza_cache_redis_client()
{
    static $initialized = false;
    static $client = null;

    if ($initialized) {
        return $client;
    }

    $initialized = true;

    if (!commerza_cache_enabled() || !class_exists('Redis')) {
        return null;
    }

    $host = commerza_cache_env('COMMERZA_REDIS_HOST', commerza_cache_env('REDIS_HOST', '127.0.0.1'));
    $port = (int)commerza_cache_env('COMMERZA_REDIS_PORT', commerza_cache_env('REDIS_PORT', '6379'));
    if ($port <= 0) {
        $port = 6379;
    }

    $timeout = (float)commerza_cache_env('COMMERZA_REDIS_TIMEOUT', '1.5');
    if ($timeout <= 0) {
        $timeout = 1.5;
    }

    $persistent = strtolower(commerza_cache_env('COMMERZA_REDIS_PERSISTENT', '0'));
    $usePersistent = in_array($persistent, ['1', 'true', 'on', 'yes'], true);
    $database = (int)commerza_cache_env('COMMERZA_REDIS_DB', '0');
    if ($database < 0) {
        $database = 0;
    }

    $password = commerza_cache_env('COMMERZA_REDIS_PASSWORD', commerza_cache_env('REDIS_PASSWORD', ''));

    try {
        $redis = new Redis();
        $connected = $usePersistent
            ? @$redis->pconnect($host, $port, $timeout)
            : @$redis->connect($host, $port, $timeout);

        if (!$connected) {
            return null;
        }

        if ($password !== '' && !$redis->auth($password)) {
            return null;
        }

        if ($database > 0 && !$redis->select($database)) {
            return null;
        }

        $client = $redis;
        return $client;
    } catch (Throwable $exception) {
        return null;
    }
}

function &commerza_cache_runtime_store(): array
{
    static $runtime = [];
    return $runtime;
}

function commerza_cache_get(string $key, &$value = null): bool
{
    $runtime = &commerza_cache_runtime_store();

    if (!commerza_cache_enabled()) {
        return false;
    }

    $cacheKey = commerza_cache_compose_key($key);
    $now = time();

    if (isset($runtime[$cacheKey]) && is_array($runtime[$cacheKey])) {
        $record = $runtime[$cacheKey];
        $expiresAt = (int)($record['expires_at'] ?? 0);
        if ($expiresAt === 0 || $expiresAt >= $now) {
            $value = $record['value'] ?? null;
            return true;
        }

        unset($runtime[$cacheKey]);
    }

    $redis = commerza_cache_redis_client();
    if ($redis !== null) {
        try {
            $raw = $redis->get($cacheKey);
            if (is_string($raw) && $raw !== '') {
                $decodedOk = false;
                $decoded = commerza_cache_deserialize_payload($raw, $decodedOk);
                if ($decodedOk) {
                    $value = $decoded;
                    $runtime[$cacheKey] = [
                        'value' => $decoded,
                        'expires_at' => $now + 30,
                    ];
                    return true;
                }
            }
        } catch (Throwable $exception) {
            // Ignore Redis transport failures and continue with fallback stores.
        }
    }

    if (commerza_cache_apcu_available()) {
        $apcuHit = false;
        $raw = call_user_func('apcu_fetch', $cacheKey, $apcuHit);
        if ($apcuHit && is_string($raw) && $raw !== '') {
            $decodedOk = false;
            $decoded = commerza_cache_deserialize_payload($raw, $decodedOk);
            if ($decodedOk) {
                $value = $decoded;
                $runtime[$cacheKey] = [
                    'value' => $decoded,
                    'expires_at' => $now + 30,
                ];
                return true;
            }
        }
    }

    return false;
}

function commerza_cache_set(string $key, $value, int $ttlSeconds = 120): bool
{
    $runtime = &commerza_cache_runtime_store();

    if (!commerza_cache_enabled()) {
        return false;
    }

    $ttl = max(1, $ttlSeconds);
    $cacheKey = commerza_cache_compose_key($key);
    $payload = commerza_cache_serialize_payload($value);
    $storedAnywhere = false;

    $runtime[$cacheKey] = [
        'value' => $value,
        'expires_at' => time() + $ttl,
    ];

    $redis = commerza_cache_redis_client();
    if ($redis !== null) {
        try {
            $storedAnywhere = (bool)$redis->setex($cacheKey, $ttl, $payload) || $storedAnywhere;
        } catch (Throwable $exception) {
            // Ignore Redis write failures and continue with fallback stores.
        }
    }

    if (commerza_cache_apcu_available()) {
        $storedAnywhere = (bool)call_user_func('apcu_store', $cacheKey, $payload, $ttl) || $storedAnywhere;
    }

    return $storedAnywhere;
}

function commerza_cache_delete(string $key): void
{
    $runtime = &commerza_cache_runtime_store();

    $cacheKey = commerza_cache_compose_key($key);
    unset($runtime[$cacheKey]);

    $redis = commerza_cache_redis_client();
    if ($redis !== null) {
        try {
            $redis->del($cacheKey);
        } catch (Throwable $exception) {
            // Ignore Redis delete failures.
        }
    }

    if (commerza_cache_apcu_available()) {
        call_user_func('apcu_delete', $cacheKey);
    }
}

function commerza_cache_remember(string $key, int $ttlSeconds, callable $resolver)
{
    $cached = null;
    if (commerza_cache_get($key, $cached)) {
        return $cached;
    }

    $resolved = $resolver();
    commerza_cache_set($key, $resolved, $ttlSeconds);

    return $resolved;
}

function commerza_fragment_cache_start(string $fragmentKey, int $ttlSeconds = 120): bool
{
    $cached = null;
    $cacheKey = 'fragment:' . trim($fragmentKey);

    if (commerza_cache_get($cacheKey, $cached) && is_string($cached) && $cached !== '') {
        echo $cached;
        return false;
    }

    $stack = $GLOBALS['commerza_fragment_cache_stack'] ?? [];
    if (!is_array($stack)) {
        $stack = [];
    }

    $stack[] = [
        'key' => $cacheKey,
        'ttl' => max(10, $ttlSeconds),
    ];

    $GLOBALS['commerza_fragment_cache_stack'] = $stack;
    ob_start();

    return true;
}

function commerza_fragment_cache_end(): void
{
    $stack = $GLOBALS['commerza_fragment_cache_stack'] ?? [];
    if (!is_array($stack) || empty($stack)) {
        return;
    }

    $entry = array_pop($stack);
    $GLOBALS['commerza_fragment_cache_stack'] = $stack;

    $content = (string)ob_get_clean();
    $ttl = (int)($entry['ttl'] ?? 120);
    $key = (string)($entry['key'] ?? 'fragment:default');

    commerza_cache_set($key, $content, max(10, $ttl));
    echo $content;
}

function commerza_fragment_cache_abort(): void
{
    $stack = $GLOBALS['commerza_fragment_cache_stack'] ?? [];
    if (!is_array($stack) || empty($stack)) {
        return;
    }

    array_pop($stack);
    $GLOBALS['commerza_fragment_cache_stack'] = $stack;

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
}
