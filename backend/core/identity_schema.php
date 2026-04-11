<?php

function commerza_users_table_exists(mysqli $con): bool
{
    $result = $con->query("SHOW TABLES LIKE 'users'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_has_column(mysqli $con, string $column): bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_has_unique_index_for_column(mysqli $con, string $column): bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW INDEX FROM users WHERE Column_name = '{$safeColumn}' AND Non_unique = 0");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function commerza_users_column_is_nullable(mysqli $con, string $column): ?bool
{
    $safeColumn = $con->real_escape_string($column);
    $result = $con->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    $nullable = strtoupper((string)($row['Null'] ?? 'YES')) === 'YES';
    return $nullable;
}

function commerza_username_slug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = str_replace([' ', '-'], '_', $normalized);
    $normalized = preg_replace('/[^a-z0-9_]+/', '', $normalized);

    if (!is_string($normalized)) {
        return '';
    }

    $normalized = preg_replace('/_+/', '_', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    $normalized = trim($normalized, '_');

    if ($normalized !== '' && preg_match('/^[a-z]/', $normalized) !== 1) {
        $normalized = 'u_' . $normalized;
    }

    if (strlen($normalized) > 24) {
        $normalized = substr($normalized, 0, 24);
        $normalized = rtrim($normalized, '_');
    }

    return $normalized;
}

function commerza_username_is_valid(string $username): bool
{
    return preg_match('/^[a-z][a-z0-9_]{2,23}$/', trim($username)) === 1;
}

function commerza_username_blacklist_type_from_reason(string $reason, string $category = ''): string
{
    $normalizedReason = strtolower(trim($reason));
    $normalizedCategory = strtoupper(trim($category));

    if (
        str_contains($normalizedReason, 'religious')
        || str_contains($normalizedReason, 'religion')
    ) {
        return 'religious';
    }

    if (
        str_contains($normalizedReason, 'explicit')
        || str_contains($normalizedReason, 'sexual')
        || str_contains($normalizedReason, 'adult')
    ) {
        return 'explicit';
    }

    if ($normalizedCategory === 'A' || $normalizedCategory === 'C') {
        return 'system';
    }

    return 'harmful';
}

function commerza_username_blacklist_feedback_message(array $blocked): string
{
    $type = strtolower(trim((string)($blocked['type'] ?? 'harmful')));

    if ($type === 'religious') {
        return 'This username contains religious targeting terms. Please choose a neutral username.';
    }

    if ($type === 'explicit') {
        return 'This username includes explicit sexual language. Please choose a cleaner username.';
    }

    if ($type === 'system') {
        return 'This username is reserved for Commerza system use. Please choose another username.';
    }

    return 'This username contains harmful or offensive language. Please choose a respectful username.';
}

function commerza_username_blacklist_lookup(mysqli $con, string $username): ?array
{
    $slug = commerza_username_slug($username);
    if ($slug === '') {
        return null;
    }

    static $entries = null;

    if (!is_array($entries)) {
        $entries = [];

        $hasTable = false;
        $tableResult = $con->query("SHOW TABLES LIKE 'username_blacklist'");
        if ($tableResult instanceof mysqli_result) {
            $hasTable = $tableResult->num_rows > 0;
            $tableResult->free();
        }

        if ($hasTable) {
            $result = $con->query(
                "SELECT term, category, reason
                 FROM username_blacklist
                 WHERE is_active = 1
                 ORDER BY CHAR_LENGTH(term) DESC, id ASC"
            );

            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $term = commerza_username_slug((string)($row['term'] ?? ''));
                    if ($term === '') {
                        continue;
                    }

                    $reason = trim((string)($row['reason'] ?? ''));
                    $category = trim((string)($row['category'] ?? ''));

                    $entries[] = [
                        'term' => $term,
                        'reason' => $reason,
                        'category' => $category,
                        'type' => commerza_username_blacklist_type_from_reason($reason, $category),
                    ];
                }

                $result->free();
            }
        }

        if (empty($entries)) {
            $entries = [
                ['term' => 'admin', 'reason' => 'System role name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'root', 'reason' => 'System role name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'commerza', 'reason' => 'Brand reserved name', 'category' => 'A', 'type' => 'system'],
                ['term' => 'products', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'account', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'wishlist', 'reason' => 'Route-like reserved name', 'category' => 'C', 'type' => 'system'],
                ['term' => 'porn', 'reason' => 'Explicit sexual term', 'category' => 'B', 'type' => 'explicit'],
                ['term' => 'sex', 'reason' => 'Explicit sexual term', 'category' => 'B', 'type' => 'explicit'],
                ['term' => 'islam', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'christian', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'hindu', 'reason' => 'Religious targeting term', 'category' => 'B', 'type' => 'religious'],
                ['term' => 'nazi', 'reason' => 'Hateful extremist term', 'category' => 'B', 'type' => 'harmful'],
                ['term' => 'fuck', 'reason' => 'Offensive profanity term', 'category' => 'B', 'type' => 'harmful'],
            ];
        }
    }

    foreach ($entries as $entry) {
        $term = (string)($entry['term'] ?? '');
        if ($term === '') {
            continue;
        }

        $isMatch = false;

        if ($slug === $term) {
            $isMatch = true;
        } elseif (strlen($term) <= 3) {
            if (
                str_starts_with($slug, $term)
                || str_ends_with($slug, $term)
                || preg_match('/(^|_)' . preg_quote($term, '/') . '(_|$)/', $slug) === 1
            ) {
                $isMatch = true;
            }
        } elseif (str_contains($slug, $term)) {
            $isMatch = true;
        }

        if ($isMatch) {
            return [
                'term' => $term,
                'reason' => (string)($entry['reason'] ?? ''),
                'category' => (string)($entry['category'] ?? ''),
                'type' => (string)($entry['type'] ?? 'harmful'),
            ];
        }
    }

    return null;
}

function commerza_customer_blacklist_normalize_email(string $email): string
{
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
        return '';
    }

    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL) || strlen($normalized) > 150) {
        return '';
    }

    return $normalized;
}

function commerza_customer_blacklist_normalize_phone(string $phone): string
{
    $normalized = preg_replace('/\s+/', '', trim($phone));
    if (!is_string($normalized)) {
        return '';
    }

    if ($normalized === '') {
        return '';
    }

    if (preg_match('/^\d{11,15}$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function commerza_customer_blacklist_ensure_table(mysqli $con): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $con->query(
        'CREATE TABLE IF NOT EXISTS customer_blacklist (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_by_admin_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_customer_blacklist_active (is_active, created_at),
            KEY idx_customer_blacklist_email (email),
            KEY idx_customer_blacklist_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

    $initialized = true;
}

function commerza_customer_blacklist_lookup(mysqli $con, string $email = '', string $phone = ''): ?array
{
    commerza_customer_blacklist_ensure_table($con);

    $normalizedEmail = commerza_customer_blacklist_normalize_email($email);
    $normalizedPhone = commerza_customer_blacklist_normalize_phone($phone);

    if ($normalizedEmail === '' && $normalizedPhone === '') {
        return null;
    }

    if ($normalizedEmail !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason, created_at
             FROM customer_blacklist
             WHERE is_active = 1
               AND LOWER(TRIM(email)) = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => trim((string)($row['email'] ?? '')),
                    'phone' => trim((string)($row['phone'] ?? '')),
                    'reason' => trim((string)($row['reason'] ?? '')),
                    'match' => 'email',
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    if ($normalizedPhone !== '') {
        $stmt = $con->prepare(
            'SELECT id, email, phone, reason, created_at
             FROM customer_blacklist
             WHERE is_active = 1
               AND phone = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($stmt) {
            $stmt->bind_param('s', $normalizedPhone);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if ($row) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => trim((string)($row['email'] ?? '')),
                    'phone' => trim((string)($row['phone'] ?? '')),
                    'reason' => trim((string)($row['reason'] ?? '')),
                    'match' => 'phone',
                    'created_at' => (string)($row['created_at'] ?? ''),
                ];
            }
        }
    }

    return null;
}

function commerza_customer_blacklist_feedback_message(array $blocked): string
{
    $match = strtolower(trim((string)($blocked['match'] ?? 'contact')));
    $reason = trim((string)($blocked['reason'] ?? ''));

    if ($reason !== '') {
        if ($match === 'email') {
            return 'This email has been blocked by admin. Reason: ' . $reason;
        }

        if ($match === 'phone') {
            return 'This phone number has been blocked by admin. Reason: ' . $reason;
        }

        return 'This contact is blocked by admin. Reason: ' . $reason;
    }

    if ($match === 'email') {
        return 'This email has been blocked by admin. Please contact support.';
    }

    if ($match === 'phone') {
        return 'This phone number has been blocked by admin. Please contact support.';
    }

    return 'This contact is blocked by admin. Please contact support.';
}

function commerza_username_base_from_identity(string $fullName, string $email, int $fallbackId = 0): string
{
    $emailLocal = '';
    if ($email !== '') {
        $parts = explode('@', $email, 2);
        $emailLocal = (string)($parts[0] ?? '');
    }

    $candidates = [
        $fullName,
        $emailLocal,
        'user' . max(1, $fallbackId),
    ];

    foreach ($candidates as $candidate) {
        $slug = commerza_username_slug((string)$candidate);
        if ($slug !== '') {
            if (strlen($slug) < 3) {
                $slug = str_pad($slug, 3, '0');
            }
            return substr($slug, 0, 24);
        }
    }

    try {
        $random = 'user_' . substr(bin2hex(random_bytes(4)), 0, 6);
    } catch (Throwable $exception) {
        $random = 'user_' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    return substr(commerza_username_slug($random), 0, 24);
}

function commerza_username_slug_exists(mysqli $con, string $slug, int $excludeUserId = 0): bool
{
    if ($slug === '') {
        return false;
    }

    $sql = 'SELECT id FROM users WHERE username_slug = ?';
    if ($excludeUserId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return true;
    }

    if ($excludeUserId > 0) {
        $stmt->bind_param('si', $slug, $excludeUserId);
    } else {
        $stmt->bind_param('s', $slug);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return is_array($row);
}

function commerza_username_resolve_unique(
    mysqli $con,
    string $preferred,
    string $fullName = '',
    string $email = '',
    int $excludeUserId = 0
): array {
    $base = commerza_username_slug($preferred);

    if (!commerza_username_is_valid($base)) {
        $base = commerza_username_base_from_identity($fullName, $email, $excludeUserId);
    }

    if (!commerza_username_is_valid($base)) {
        $base = commerza_username_slug('user_' . max(1, $excludeUserId));
    }

    if (!commerza_username_is_valid($base)) {
        $base = 'user001';
    }

    $candidate = $base;

    for ($attempt = 0; $attempt < 500; $attempt++) {
        if ($attempt > 0) {
            $suffix = (string)$attempt;
            $maxPrefixLength = max(1, 24 - strlen($suffix) - 1);
            $prefix = substr($base, 0, $maxPrefixLength);
            $candidate = rtrim($prefix, '_') . '_' . $suffix;
        }

        $slug = commerza_username_slug($candidate);
        if (!commerza_username_is_valid($slug)) {
            continue;
        }

        if (!commerza_username_slug_exists($con, $slug, $excludeUserId)) {
            return [
                'username' => $slug,
                'slug' => $slug,
            ];
        }
    }

    $fallbackBase = commerza_username_base_from_identity($fullName, $email, $excludeUserId);
    $fallback = commerza_username_slug($fallbackBase . '_' . substr((string)time(), -4));
    if (!commerza_username_is_valid($fallback)) {
        $fallback = 'user_' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    return [
        'username' => $fallback,
        'slug' => $fallback,
    ];
}

function commerza_ensure_users_identity_schema(mysqli $con): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    if (!commerza_users_table_exists($con)) {
        $ready = true;
        return;
    }

    $requiredColumns = [
        'username' => 'ALTER TABLE users ADD COLUMN username VARCHAR(24) DEFAULT NULL AFTER full_name',
        'username_slug' => 'ALTER TABLE users ADD COLUMN username_slug VARCHAR(48) DEFAULT NULL AFTER username',
        'profile_visibility' => "ALTER TABLE users ADD COLUMN profile_visibility ENUM('private','public') NOT NULL DEFAULT 'private' AFTER profile_picture",
        'username_changed_at' => 'ALTER TABLE users ADD COLUMN username_changed_at DATETIME DEFAULT NULL AFTER profile_visibility',
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!commerza_users_has_column($con, $column)) {
            $con->query($sql);
        }
    }

    $result = $con->query(
        "SELECT id, full_name, email, username, username_slug, profile_visibility
         FROM users
         WHERE username IS NULL
            OR TRIM(username) = ''
            OR username_slug IS NULL
            OR TRIM(username_slug) = ''
            OR profile_visibility IS NULL
            OR TRIM(profile_visibility) = ''
            OR username NOT REGEXP '^[a-z][a-z0-9_]{2,23}$'
            OR username_slug NOT REGEXP '^[a-z][a-z0-9_]{2,23}$'
         ORDER BY id ASC"
    );
    if ($result instanceof mysqli_result) {
        $updateStmt = $con->prepare(
            'UPDATE users
             SET username = ?, username_slug = ?, profile_visibility = ?
             WHERE id = ?
             LIMIT 1'
        );

        if ($updateStmt) {
            while ($row = $result->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $currentUsername = trim((string)($row['username'] ?? ''));
                $currentSlug = trim((string)($row['username_slug'] ?? ''));
                $fullName = (string)($row['full_name'] ?? '');
                $email = (string)($row['email'] ?? '');
                $visibility = strtolower(trim((string)($row['profile_visibility'] ?? 'private')));

                if (!in_array($visibility, ['private', 'public'], true)) {
                    $visibility = 'private';
                }

                $preferred = $currentUsername !== '' ? $currentUsername : $currentSlug;
                $resolved = commerza_username_resolve_unique($con, $preferred, $fullName, $email, $id);
                $normalizedUsername = (string)($resolved['username'] ?? '');
                $normalizedSlug = (string)($resolved['slug'] ?? $normalizedUsername);

                $usernameChanged = strcasecmp($currentUsername, $normalizedUsername) !== 0;
                $slugChanged = strcasecmp($currentSlug, $normalizedSlug) !== 0;
                $visibilityChanged = strtolower(trim((string)($row['profile_visibility'] ?? ''))) !== $visibility;

                if (!$usernameChanged && !$slugChanged && !$visibilityChanged) {
                    continue;
                }

                $updateStmt->bind_param('sssi', $normalizedUsername, $normalizedSlug, $visibility, $id);
                $updateStmt->execute();
            }

            $updateStmt->close();
        }

        $result->free();
    }

    if (commerza_users_has_column($con, 'profile_visibility')) {
        $con->query("UPDATE users SET profile_visibility = 'private' WHERE profile_visibility IS NULL OR TRIM(profile_visibility) = ''");
    }

    if (commerza_users_has_column($con, 'username') && commerza_users_column_is_nullable($con, 'username') === true) {
        $con->query('ALTER TABLE users MODIFY username VARCHAR(24) NOT NULL');
    }

    if (commerza_users_has_column($con, 'username_slug') && commerza_users_column_is_nullable($con, 'username_slug') === true) {
        $con->query('ALTER TABLE users MODIFY username_slug VARCHAR(48) NOT NULL');
    }

    if (!commerza_users_has_unique_index_for_column($con, 'username')) {
        $con->query('ALTER TABLE users ADD UNIQUE INDEX uq_users_username (username)');
    }

    if (!commerza_users_has_unique_index_for_column($con, 'username_slug')) {
        $con->query('ALTER TABLE users ADD UNIQUE INDEX uq_users_username_slug (username_slug)');
    }

    $ready = true;
}
