<?php

function commerza_security_ensure_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS security_events (
            id BIGINT NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            severity ENUM("info", "warning", "critical") NOT NULL DEFAULT "info",
            actor_type VARCHAR(32) DEFAULT NULL,
            actor_identifier VARCHAR(191) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            admin_id INT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            details_json JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_security_event_type_time (event_type, created_at),
            KEY idx_security_actor_time (actor_type, actor_identifier, created_at),
            KEY idx_security_severity_time (severity, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function commerza_security_client_ip(): string
{
    if (function_exists('commerza_client_ip')) {
        return commerza_client_ip();
    }

    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        $parts = explode(',', (string)$candidate);
        $ip = trim((string)$parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function commerza_security_user_agent(): string
{
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return 'unknown';
    }

    if (strlen($userAgent) > 255) {
        return substr($userAgent, 0, 255);
    }

    return $userAgent;
}

function commerza_security_normalize_identifier(string $value): string
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return 'anonymous';
    }

    if (strlen($normalized) > 191) {
        return substr($normalized, 0, 191);
    }

    return $normalized;
}

function commerza_security_log_event(mysqli $con, array $event): bool
{
    commerza_security_ensure_table($con);

    $eventType = trim((string)($event['event_type'] ?? 'unknown_event'));
    $severity = strtolower(trim((string)($event['severity'] ?? 'info')));
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'info';
    }

    $actorType = trim((string)($event['actor_type'] ?? ''));
    if ($actorType === '') {
        $actorType = null;
    }

    $actorIdentifier = commerza_security_normalize_identifier((string)($event['actor_identifier'] ?? ''));
    if ($actorIdentifier === 'anonymous') {
        $actorIdentifier = null;
    }

    $userId = isset($event['user_id']) ? max(0, (int)$event['user_id']) : 0;
    $adminId = isset($event['admin_id']) ? max(0, (int)$event['admin_id']) : 0;

    $ipAddress = trim((string)($event['ip_address'] ?? commerza_security_client_ip()));
    if ($ipAddress === '' || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        $ipAddress = '0.0.0.0';
    }

    $userAgent = trim((string)($event['user_agent'] ?? commerza_security_user_agent()));
    if ($userAgent === '') {
        $userAgent = 'unknown';
    }

    if (strlen($userAgent) > 255) {
        $userAgent = substr($userAgent, 0, 255);
    }

    $details = $event['details'] ?? null;
    if (is_array($details) || is_object($details)) {
        $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES);
    } elseif ($details !== null && $details !== '') {
        $detailsJson = json_encode(['message' => (string)$details], JSON_UNESCAPED_SLASHES);
    } else {
        $detailsJson = null;
    }

    $stmt = $con->prepare(
        'INSERT INTO security_events (
            event_type,
            severity,
            actor_type,
            actor_identifier,
            user_id,
            admin_id,
            ip_address,
            user_agent,
            details_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'ssssiisss',
        $eventType,
        $severity,
        $actorType,
        $actorIdentifier,
        $userId,
        $adminId,
        $ipAddress,
        $userAgent,
        $detailsJson
    );

    $ok = $stmt->execute();
    $stmt->close();

    return (bool)$ok;
}

function commerza_security_log_auth_attempt(
    mysqli $con,
    string $actorType,
    string $identifier,
    string $ipAddress,
    bool $success,
    string $reason = '',
    int $userId = 0,
    int $adminId = 0
): void {
    $actorType = strtolower(trim($actorType));
    if (!in_array($actorType, ['user', 'admin'], true)) {
        $actorType = 'user';
    }

    $identifier = commerza_security_normalize_identifier($identifier);
    $ipAddress = trim($ipAddress);
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        $ipAddress = commerza_security_client_ip();
    }

    $eventType = $success ? 'login_success' : 'login_failed';
    $severity = $success ? 'info' : 'warning';

    commerza_security_log_event($con, [
        'event_type' => $eventType,
        'severity' => $severity,
        'actor_type' => $actorType,
        'actor_identifier' => $identifier,
        'user_id' => $userId,
        'admin_id' => $adminId,
        'ip_address' => $ipAddress,
        'details' => [
            'reason' => $reason,
        ],
    ]);

    if (!$success) {
        return;
    }

    commerza_security_log_suspicious_login_if_ip_changed($con, $actorType, $identifier, $ipAddress, $userId, $adminId);
}

function commerza_security_log_suspicious_login_if_ip_changed(
    mysqli $con,
    string $actorType,
    string $identifier,
    string $ipAddress,
    int $userId = 0,
    int $adminId = 0
): void {
    commerza_security_ensure_table($con);

    $query =
        'SELECT ip_address, created_at
         FROM security_events
         WHERE event_type = "login_success"
           AND actor_type = ?
           AND actor_identifier = ?
           AND ip_address <> ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         ORDER BY id DESC
         LIMIT 1';

    $stmt = $con->prepare($query);
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('sss', $actorType, $identifier, $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result();
    $previous = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$previous) {
        return;
    }

    commerza_security_log_event($con, [
        'event_type' => 'suspicious_login_ip_change',
        'severity' => 'warning',
        'actor_type' => $actorType,
        'actor_identifier' => $identifier,
        'user_id' => $userId,
        'admin_id' => $adminId,
        'ip_address' => $ipAddress,
        'details' => [
            'current_ip' => $ipAddress,
            'previous_ip' => (string)($previous['ip_address'] ?? ''),
            'previous_at' => (string)($previous['created_at'] ?? ''),
        ],
    ]);
}

function commerza_security_log_rate_limit_block(
    mysqli $con,
    string $scope,
    string $actorType,
    string $identifier,
    string $ipAddress,
    int $retryAfterSeconds
): void {
    commerza_security_log_event($con, [
        'event_type' => 'rate_limit_block',
        'severity' => 'warning',
        'actor_type' => strtolower(trim($actorType)),
        'actor_identifier' => $identifier,
        'ip_address' => $ipAddress,
        'details' => [
            'scope' => $scope,
            'retry_after_seconds' => max(0, $retryAfterSeconds),
        ],
    ]);
}