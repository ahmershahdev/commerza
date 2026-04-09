<?php

function commerza_client_ip(): string
{
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

function commerza_rate_limit_ensure_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            id INT NOT NULL AUTO_INCREMENT,
            scope VARCHAR(80) NOT NULL,
            identifier VARCHAR(191) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            window_started_at DATETIME NOT NULL,
            blocked_until DATETIME DEFAULT NULL,
            strikes INT NOT NULL DEFAULT 0,
            last_blocked_at DATETIME DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rate_limit_scope_identifier_ip (scope, identifier, ip_address),
            KEY idx_rate_limit_blocked_until (blocked_until),
            KEY idx_rate_limit_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $columns = [
        'strikes' => 'INT NOT NULL DEFAULT 0',
        'last_blocked_at' => 'DATETIME DEFAULT NULL',
    ];

    foreach ($columns as $name => $definition) {
        $safeName = $con->real_escape_string($name);
        $check = $con->query("SHOW COLUMNS FROM rate_limits LIKE '{$safeName}'");
        if (!($check instanceof mysqli_result) || $check->num_rows === 0) {
            $con->query("ALTER TABLE rate_limits ADD COLUMN {$name} {$definition}");
        }
    }

    $initialized = true;
}

function commerza_rate_limit_normalize_identifier(string $value): string
{
    $normalized = strtolower(trim($value));

    if ($normalized === '') {
        return 'anonymous';
    }

    if (strlen($normalized) > 190) {
        return substr($normalized, 0, 190);
    }

    return $normalized;
}

function commerza_rate_limit_check(
    mysqli $con,
    string $scope,
    string $identifier,
    string $ipAddress,
    int $maxAttempts,
    int $windowSeconds,
    int $blockSeconds,
    int $escalatedBlockSeconds = 0,
    int $escalationResetSeconds = 14400
): array {
    $scope = trim($scope);
    $identifier = commerza_rate_limit_normalize_identifier($identifier);
    $ipAddress = trim($ipAddress) !== '' ? trim($ipAddress) : '0.0.0.0';
    $maxAttempts = max(1, $maxAttempts);
    $windowSeconds = max(60, $windowSeconds);
    $blockSeconds = max(60, $blockSeconds);
    $escalatedBlockSeconds = max($blockSeconds, $escalatedBlockSeconds);
    $escalationResetSeconds = max(60, $escalationResetSeconds);

    if ($scope === '') {
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    commerza_rate_limit_ensure_table($con);

    $nowTs = time();
    $now = date('Y-m-d H:i:s', $nowTs);

    if (!$con->begin_transaction()) {
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    $upsertStmt = $con->prepare(
        'INSERT INTO rate_limits (
            scope,
            identifier,
            ip_address,
            attempts,
            window_started_at,
            blocked_until,
            strikes,
            last_blocked_at
         ) VALUES (?, ?, ?, 0, ?, NULL, 0, NULL)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );

    if (!$upsertStmt) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    $upsertStmt->bind_param('ssss', $scope, $identifier, $ipAddress, $now);
    $upsertOk = $upsertStmt->execute();
    $recordId = (int)$con->insert_id;
    $upsertStmt->close();

    if (!$upsertOk || $recordId <= 0) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    $selectStmt = $con->prepare(
        'SELECT attempts, window_started_at, blocked_until, strikes, last_blocked_at
         FROM rate_limits
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$selectStmt) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    $selectStmt->bind_param('i', $recordId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();

    if (!$row) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => $maxAttempts,
            'limit' => $maxAttempts,
        ];
    }

    $attempts = max(0, (int)($row['attempts'] ?? 0));
    $strikes = max(0, (int)($row['strikes'] ?? 0));
    $windowStartedAt = strtotime((string)($row['window_started_at'] ?? ''));
    $blockedUntil = strtotime((string)($row['blocked_until'] ?? ''));
    $lastBlockedAt = strtotime((string)($row['last_blocked_at'] ?? ''));

    if ($blockedUntil !== false && $blockedUntil > $nowTs) {
        $con->commit();
        return [
            'allowed' => false,
            'retry_after' => $blockedUntil - $nowTs,
            'remaining' => 0,
            'limit' => $maxAttempts,
        ];
    }

    $windowExpired = $windowStartedAt === false || ($nowTs - $windowStartedAt) >= $windowSeconds;

    if ($windowExpired) {
        if ($lastBlockedAt === false || ($nowTs - $lastBlockedAt) > $escalationResetSeconds) {
            $strikes = 0;
        }

        $attempts = 1;
        $updateStmt = $con->prepare(
            'UPDATE rate_limits
             SET attempts = ?, window_started_at = ?, blocked_until = NULL, strikes = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateStmt) {
            $con->rollback();
            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => max(0, $maxAttempts - 1),
                'limit' => $maxAttempts,
            ];
        }

        $updateStmt->bind_param('isii', $attempts, $now, $strikes, $recordId);
        $updateOk = $updateStmt->execute();
        $updateStmt->close();

        if (!$updateOk || !$con->commit()) {
            $con->rollback();
            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => max(0, $maxAttempts - 1),
                'limit' => $maxAttempts,
            ];
        }

        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => max(0, $maxAttempts - 1),
            'limit' => $maxAttempts,
        ];
    }

    $attempts++;

    if ($attempts > $maxAttempts) {
        $withinEscalationWindow = $lastBlockedAt !== false && ($nowTs - $lastBlockedAt) <= $escalationResetSeconds;
        if (!$withinEscalationWindow) {
            $strikes = 0;
        }

        $nextStrike = $strikes + 1;
        $appliedBlockSeconds = ($nextStrike >= 2) ? $escalatedBlockSeconds : $blockSeconds;

        $blockedUntilTs = $nowTs + $appliedBlockSeconds;
        $blockedUntilText = date('Y-m-d H:i:s', $blockedUntilTs);
        $lastBlockedAtText = $now;

        $blockStmt = $con->prepare(
            'UPDATE rate_limits
             SET attempts = ?, blocked_until = ?, strikes = ?, last_blocked_at = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$blockStmt) {
            $con->rollback();
            return [
                'allowed' => false,
                'retry_after' => $blockSeconds,
                'remaining' => 0,
                'limit' => $maxAttempts,
            ];
        }

        $blockStmt->bind_param('isisi', $attempts, $blockedUntilText, $nextStrike, $lastBlockedAtText, $recordId);
        $blockOk = $blockStmt->execute();
        $blockStmt->close();

        if (!$blockOk || !$con->commit()) {
            $con->rollback();
            return [
                'allowed' => false,
                'retry_after' => $blockSeconds,
                'remaining' => 0,
                'limit' => $maxAttempts,
            ];
        }

        return [
            'allowed' => false,
            'retry_after' => $appliedBlockSeconds,
            'remaining' => 0,
            'limit' => $maxAttempts,
            'block_level' => $nextStrike >= 2 ? 'elevated' : 'standard',
        ];
    }

    $updateStmt = $con->prepare(
        'UPDATE rate_limits
         SET attempts = ?, blocked_until = NULL
         WHERE id = ?
         LIMIT 1'
    );

    if (!$updateStmt) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => max(0, $maxAttempts - $attempts),
            'limit' => $maxAttempts,
        ];
    }

    $updateStmt->bind_param('ii', $attempts, $recordId);
    $updateOk = $updateStmt->execute();
    $updateStmt->close();

    if (!$updateOk || !$con->commit()) {
        $con->rollback();
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => max(0, $maxAttempts - $attempts),
            'limit' => $maxAttempts,
        ];
    }

    return [
        'allowed' => true,
        'retry_after' => 0,
        'remaining' => max(0, $maxAttempts - $attempts),
        'limit' => $maxAttempts,
    ];
}

function commerza_rate_limit_reset(mysqli $con, string $scope, string $identifier, string $ipAddress): void
{
    $scope = trim($scope);
    if ($scope === '') {
        return;
    }

    $identifier = commerza_rate_limit_normalize_identifier($identifier);
    $ipAddress = trim($ipAddress) !== '' ? trim($ipAddress) : '0.0.0.0';

    commerza_rate_limit_ensure_table($con);

    $deleteStmt = $con->prepare(
        'DELETE FROM rate_limits
         WHERE scope = ? AND identifier = ? AND ip_address = ?
         LIMIT 1'
    );

    if (!$deleteStmt) {
        return;
    }

    $deleteStmt->bind_param('sss', $scope, $identifier, $ipAddress);
    $deleteStmt->execute();
    $deleteStmt->close();
}
