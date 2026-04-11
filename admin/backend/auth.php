<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/core/data.php';
require_once __DIR__ . '/../../backend/mailer/mailer.php';
require_once __DIR__ . '/../../backend/helpers/server_timing_helpers.php';

function admin_is_backend_api_request(): bool
{
    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return str_contains($script, '/admin/backend/');
}

function admin_bootstrap_server_timing(): void
{
    if (!function_exists('commerza_server_timing_start')) {
        return;
    }

    if (isset($GLOBALS['commerza_server_timing']) && is_array($GLOBALS['commerza_server_timing'])) {
        return;
    }

    $scriptBase = strtolower((string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''), '.php'));
    if ($scriptBase === '') {
        $scriptBase = 'request';
    }

    $isApi = admin_is_backend_api_request();
    $metric = $isApi ? 'admin_api' : 'admin';
    $descPrefix = $isApi ? 'admin_api.' : 'admin.';

    commerza_server_timing_start($metric, $descPrefix . $scriptBase);
}

function admin_apply_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $script = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $isAdminScope = str_contains($script, '/admin/backend/') || str_contains($script, '/admin/frontend/');

    if (!$isAdminScope) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Vary: Cookie, Accept-Encoding, Accept');
}

function admin_api_apply_response_headers(?string $metric = null, ?string $desc = null): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    admin_apply_cache_headers();
    commerza_server_timing_emit($metric, $desc);
}

function admin_api_json_exit(array $payload, int $statusCode): void
{
    admin_api_apply_response_headers();
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_table_has_index(mysqli $con, string $table, string $indexName): bool
{
    $safeTable = $con->real_escape_string($table);
    $safeIndex = $con->real_escape_string($indexName);

    $result = $con->query("SHOW INDEX FROM {$safeTable} WHERE Key_name = '{$safeIndex}'");
    if (!($result instanceof mysqli_result)) {
        return false;
    }

    $hasIndex = $result->num_rows > 0;
    $result->free();

    return $hasIndex;
}

function admin_ensure_schema(mysqli $con): void
{
    $createTableSql =
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INT NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM("admin", "operations_manager", "customer_support", "marketing_website", "read_only", "view_only", "custom") NOT NULL DEFAULT "admin",
            permissions_json LONGTEXT DEFAULT NULL,
            hidden_tabs_json LONGTEXT DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            profile_picture VARCHAR(255) DEFAULT NULL,
            reset_token VARCHAR(255) DEFAULT NULL,
            reset_token_expiry DATETIME DEFAULT NULL,
            verification_code_hash VARCHAR(255) DEFAULT NULL,
            verification_expires_at DATETIME DEFAULT NULL,
            verification_attempts INT NOT NULL DEFAULT 0,
            verification_last_sent_at DATETIME DEFAULT NULL,
            email_verified_at DATETIME DEFAULT NULL,
            two_factor_code_hash VARCHAR(255) DEFAULT NULL,
            two_factor_expires_at DATETIME DEFAULT NULL,
            two_factor_attempts INT NOT NULL DEFAULT 0,
            two_factor_last_sent_at DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            last_login_ip VARCHAR(45) DEFAULT NULL,
            invited_by_admin_id INT DEFAULT NULL,
            suspended_until DATETIME DEFAULT NULL,
            suspended_reason VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $con->query($createTableSql);

    $createSessionsTableSql =
        'CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            admin_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $con->query($createSessionsTableSql);

    $missingColumns = [
        'two_factor_code_hash' => 'VARCHAR(255) DEFAULT NULL',
        'two_factor_expires_at' => 'DATETIME DEFAULT NULL',
        'two_factor_attempts' => 'INT NOT NULL DEFAULT 0',
        'two_factor_last_sent_at' => 'DATETIME DEFAULT NULL',
        'permissions_json' => 'LONGTEXT DEFAULT NULL',
        'hidden_tabs_json' => 'LONGTEXT DEFAULT NULL',
        'phone' => 'VARCHAR(20) DEFAULT NULL',
        'verification_code_hash' => 'VARCHAR(255) DEFAULT NULL',
        'verification_expires_at' => 'DATETIME DEFAULT NULL',
        'verification_attempts' => 'INT NOT NULL DEFAULT 0',
        'verification_last_sent_at' => 'DATETIME DEFAULT NULL',
        'email_verified_at' => 'DATETIME DEFAULT NULL',
        'invited_by_admin_id' => 'INT DEFAULT NULL',
        'suspended_until' => 'DATETIME DEFAULT NULL',
        'suspended_reason' => 'VARCHAR(255) DEFAULT NULL',
        'deleted_at' => 'DATETIME DEFAULT NULL',
    ];

    foreach ($missingColumns as $column => $definition) {
        $safeColumn = $con->real_escape_string($column);
        $check = $con->query("SHOW COLUMNS FROM admin_users LIKE '{$safeColumn}'");
        if (!($check instanceof mysqli_result) || $check->num_rows === 0) {
            $con->query("ALTER TABLE admin_users ADD COLUMN {$column} {$definition}");
        }
    }

    $con->query(
        'ALTER TABLE admin_users
         MODIFY COLUMN role ENUM("admin", "operations_manager", "customer_support", "marketing_website", "read_only", "view_only", "custom") NOT NULL DEFAULT "admin"'
    );

    $requiredIndexes = [
        'idx_admin_users_deleted_role_created' =>
        'ALTER TABLE admin_users ADD KEY idx_admin_users_deleted_role_created (deleted_at, role, created_at, id)',
        'idx_admin_users_deleted_created' =>
        'ALTER TABLE admin_users ADD KEY idx_admin_users_deleted_created (deleted_at, created_at, id)',
        'idx_admin_users_invited_by_admin_id' =>
        'ALTER TABLE admin_users ADD KEY idx_admin_users_invited_by_admin_id (invited_by_admin_id)',
    ];

    foreach ($requiredIndexes as $indexName => $sql) {
        if (admin_table_has_index($con, 'admin_users', $indexName)) {
            continue;
        }

        $con->query($sql);
    }

    $configuredEmail = trim((string)getenv('COMMERZA_ADMIN_BOOTSTRAP_EMAIL'));
    $defaultEmail = filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)
        ? strtolower($configuredEmail)
        : 'commerza.ahmer@gmail.com';

    $configuredPassword = trim((string)getenv('COMMERZA_ADMIN_BOOTSTRAP_PASSWORD'));
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''))));
    $isLocalHost = $host === ''
        || $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1')
        || str_starts_with($host, '[::1]');

    $defaultPassword = $configuredPassword !== ''
        ? $configuredPassword
        : ($isLocalHost ? 'Commerza@2026' : '');

    $countResult = $con->query('SELECT COUNT(*) AS total FROM admin_users');
    $total = 0;
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $total = $row ? (int)$row['total'] : 0;
    }

    if ($total > 0) {
        $con->query(
            'UPDATE admin_users
             SET email_verified_at = COALESCE(email_verified_at, created_at)
             WHERE email_verified_at IS NULL
               AND verification_code_hash IS NULL
               AND is_active = 1'
        );
        return;
    }

    if (!filter_var($defaultEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $passwordPolicyError = null;
    if ($defaultPassword === '' || !commerza_password_validate($defaultPassword, $passwordPolicyError)) {
        return;
    }

    $hash = commerza_password_hash($defaultPassword);
    $insertStmt = $con->prepare(
        'INSERT INTO admin_users (full_name, email, password_hash, role, is_active, email_verified_at)
            VALUES (?, ?, ?, "admin", 1, NOW())'
    );

    if (!$insertStmt) {
        return;
    }

    $name = 'Commerza Admin';
    $insertStmt->bind_param('sss', $name, $defaultEmail, $hash);
    $insertStmt->execute();
    $insertStmt->close();
}

admin_bootstrap_server_timing();
admin_ensure_schema($con);
admin_apply_cache_headers();

function admin_generate_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['admin_csrf_token'];
}

function admin_validate_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['admin_csrf_token'])) {
        return false;
    }

    return hash_equals((string)$_SESSION['admin_csrf_token'], (string)$token);
}

function admin_get_client_ip(): string
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

function admin_loopback_ip_aliases(): array
{
    return [
        '127.0.0.1',
        '::1',
        '[::1]',
        '0:0:0:0:0:0:0:1',
    ];
}

function admin_client_ip_matches(string $expectedIp, string $currentIp): bool
{
    $expected = strtolower(trim($expectedIp));
    $current = strtolower(trim($currentIp));

    if ($expected === '' || $current === '') {
        return false;
    }

    if ($expected === $current) {
        return true;
    }

    $loopbackAliases = admin_loopback_ip_aliases();
    return in_array($expected, $loopbackAliases, true) && in_array($current, $loopbackAliases, true);
}

function admin_get_reset_key(mysqli $con): string
{
    $legacyDefaultKey = 'COMMERZA-RESET-2026';
    $envKey = trim((string)getenv('COMMERZA_ADMIN_RESET_KEY'));

    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $envKey !== '' ? $envKey : $legacyDefaultKey;
    }

    $keyName = 'admin_reset_key';
    $stmt->bind_param('s', $keyName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $value = trim((string)($row['setting_val'] ?? ''));

    if ($envKey !== '' && ($value === '' || hash_equals($legacyDefaultKey, $value))) {
        return $envKey;
    }

    if ($value !== '') {
        return $value;
    }

    return $envKey !== '' ? $envKey : $legacyDefaultKey;
}

function admin_get_by_email(mysqli $con, string $email): ?array
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $stmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            password_hash,
            reset_token,
            reset_token_expiry,
            verification_code_hash,
            verification_expires_at,
            verification_attempts,
            verification_last_sent_at,
            email_verified_at,
            two_factor_code_hash,
            two_factor_expires_at,
            two_factor_attempts,
            suspended_until,
            suspended_reason,
            deleted_at,
            is_active
         FROM admin_users
         WHERE email = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function admin_get_by_id(mysqli $con, int $adminId): ?array
{
    $stmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            email_verified_at,
            suspended_until,
            suspended_reason,
            deleted_at,
            is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function admin_get_primary_admin(mysqli $con): ?array
{
    $result = $con->query(
        'SELECT id, full_name, email, role, reset_token, reset_token_expiry, email_verified_at, is_active
         FROM admin_users
                 WHERE is_active = 1
                     AND deleted_at IS NULL
         ORDER BY id ASC
         LIMIT 1'
    );

    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row ?: null;
}

function admin_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    $allowed = [
        'admin',
        'operations_manager',
        'customer_support',
        'marketing_website',
        'read_only',
        'view_only',
        'custom',
    ];

    if (!in_array($normalized, $allowed, true)) {
        return 'custom';
    }

    return $normalized;
}

function admin_role_labels(): array
{
    return [
        'admin' => 'Administrator',
        'operations_manager' => 'Operations Manager',
        'customer_support' => 'Customer Support',
        'marketing_website' => 'Marketing & Website',
        'read_only' => 'Read Only',
        'view_only' => 'View Only',
        'custom' => 'Custom Access',
    ];
}

function admin_role_label(string $role): string
{
    $normalized = admin_normalize_role($role);
    $labels = admin_role_labels();

    return (string)($labels[$normalized] ?? 'Custom Access');
}

function admin_permission_catalog(): array
{
    return [
        'dashboard.view' => [
            'label' => 'Dashboard Access',
            'description' => 'View performance snapshots and navigation shortcuts.',
            'tab_id' => 'dashboard-tab',
        ],
        'products.manage' => [
            'label' => 'Products',
            'description' => 'Create, edit, import, and publish products.',
            'tab_id' => 'products-tab',
        ],
        'product_trash.manage' => [
            'label' => 'Product Trash',
            'description' => 'Restore and permanently remove trashed products.',
            'tab_id' => 'product-trash-tab',
        ],
        'orders.manage' => [
            'label' => 'Orders',
            'description' => 'Process orders, shipping, and refund status changes.',
            'tab_id' => 'orders-tab',
        ],
        'customers.manage' => [
            'label' => 'Customers',
            'description' => 'Manage customer profiles, blacklist, and account cleanup.',
            'tab_id' => 'customers-tab',
        ],
        'coupons.manage' => [
            'label' => 'Coupons',
            'description' => 'Create and manage coupon campaigns.',
            'tab_id' => 'coupons-tab',
        ],
        'reviews.manage' => [
            'label' => 'Reviews',
            'description' => 'Moderate reviews and trust controls.',
            'tab_id' => 'reviews-tab',
        ],
        'email.manage' => [
            'label' => 'Email Center',
            'description' => 'Manage outbound campaigns and templates.',
            'tab_id' => 'email-tab',
        ],
        'analytics.view' => [
            'label' => 'Analytics',
            'description' => 'View revenue, conversion, and trend analytics.',
            'tab_id' => 'analytics-tab',
        ],
        'website.manage' => [
            'label' => 'Website',
            'description' => 'Edit website content and storefront settings.',
            'tab_id' => 'website-tab',
        ],
        'security.manage' => [
            'label' => 'Security Events',
            'description' => 'Review security incidents and controls.',
            'tab_id' => 'security-events-tab',
        ],
        'homepage.manage' => [
            'label' => 'Homepage',
            'description' => 'Manage homepage hero and feature content.',
            'tab_id' => 'homepage-tab',
        ],
        'sub_admins.manage' => [
            'label' => 'Sub Admins',
            'description' => 'Create, verify, suspend, and remove sub-admin accounts.',
            'tab_id' => 'sub-admins-tab',
        ],
        'media.manage' => [
            'label' => 'Media Library',
            'description' => 'Upload and manage media assets.',
            'tab_id' => 'website-tab',
        ],
        'viewers.manage' => [
            'label' => 'Live Viewers',
            'description' => 'Configure live viewers experience controls.',
            'tab_id' => 'analytics-tab',
        ],
    ];
}

function admin_tab_catalog(): array
{
    return [
        'dashboard-tab' => [
            'label' => 'Dashboard',
            'permissions' => ['dashboard.view'],
        ],
        'products-tab' => [
            'label' => 'Products',
            'permissions' => ['products.manage'],
        ],
        'product-trash-tab' => [
            'label' => 'Product Trash',
            'permissions' => ['product_trash.manage', 'products.manage'],
        ],
        'orders-tab' => [
            'label' => 'Orders',
            'permissions' => ['orders.manage'],
        ],
        'customers-tab' => [
            'label' => 'Customers',
            'permissions' => ['customers.manage', 'orders.manage'],
        ],
        'sub-admins-tab' => [
            'label' => 'Sub Admins',
            'permissions' => ['sub_admins.manage'],
        ],
        'coupons-tab' => [
            'label' => 'Coupons',
            'permissions' => ['coupons.manage'],
        ],
        'reviews-tab' => [
            'label' => 'Reviews',
            'permissions' => ['reviews.manage'],
        ],
        'email-tab' => [
            'label' => 'Email Center',
            'permissions' => ['email.manage'],
        ],
        'analytics-tab' => [
            'label' => 'Analytics',
            'permissions' => ['analytics.view', 'orders.manage'],
        ],
        'website-tab' => [
            'label' => 'Website',
            'permissions' => ['website.manage'],
        ],
        'security-events-tab' => [
            'label' => 'Security Events',
            'permissions' => ['security.manage'],
        ],
        'homepage-tab' => [
            'label' => 'Homepage',
            'permissions' => ['homepage.manage', 'website.manage'],
        ],
    ];
}

function admin_role_permission_presets(): array
{
    return [
        'admin' => ['*'],
        'operations_manager' => [
            'dashboard.view',
            'products.manage',
            'product_trash.manage',
            'orders.manage',
            'customers.manage',
            'coupons.manage',
            'reviews.manage',
            'analytics.view',
        ],
        'customer_support' => [
            'dashboard.view',
            'orders.manage',
            'customers.manage',
            'reviews.manage',
        ],
        'marketing_website' => [
            'dashboard.view',
            'products.manage',
            'coupons.manage',
            'email.manage',
            'analytics.view',
            'website.manage',
            'homepage.manage',
            'media.manage',
            'viewers.manage',
        ],
        'read_only' => [
            'dashboard.view',
            'analytics.view',
        ],
        'view_only' => [
            'dashboard.view',
        ],
        'custom' => [],
    ];
}

function admin_decode_json_string_list(?string $value): array
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $list = [];
        foreach ($decoded as $item) {
            if (is_scalar($item)) {
                $list[] = trim((string)$item);
            }
        }
        return $list;
    }

    $parts = preg_split('/[\s,]+/', $raw) ?: [];
    $list = [];
    foreach ($parts as $item) {
        $token = trim((string)$item);
        if ($token !== '') {
            $list[] = $token;
        }
    }

    return $list;
}

function admin_normalize_permission_name(string $permission): string
{
    $normalized = strtolower(trim($permission));
    if ($normalized === '' || strlen($normalized) > 120) {
        return '';
    }

    if ($normalized === '*') {
        return '*';
    }

    if (preg_match('/^[a-z0-9_]+\.[a-z0-9_*]+$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
}

function admin_sanitize_permissions(array $permissions): array
{
    $catalog = admin_permission_catalog();
    $normalized = [];

    foreach ($permissions as $permission) {
        $resolved = admin_normalize_permission_name((string)$permission);
        if ($resolved === '') {
            continue;
        }

        if ($resolved === '*' || isset($catalog[$resolved])) {
            $normalized[] = $resolved;
            continue;
        }

        if (str_ends_with($resolved, '.*')) {
            $prefix = substr($resolved, 0, -2);
            if ($prefix !== '' && preg_match('/^[a-z0-9_]+$/', $prefix) === 1) {
                $normalized[] = $resolved;
            }
        }
    }

    return array_values(array_unique($normalized));
}

function admin_sanitize_hidden_tabs(array $tabs): array
{
    $catalog = admin_tab_catalog();
    $normalized = [];

    foreach ($tabs as $tab) {
        $tabId = strtolower(trim((string)$tab));
        if ($tabId === '' || !isset($catalog[$tabId])) {
            continue;
        }

        $normalized[] = $tabId;
    }

    return array_values(array_unique($normalized));
}

function admin_role_default_permissions(string $role): array
{
    $presets = admin_role_permission_presets();
    $normalizedRole = admin_normalize_role($role);

    return $presets[$normalizedRole] ?? [];
}

function admin_effective_permissions(array $admin): array
{
    $role = admin_normalize_role((string)($admin['role'] ?? 'admin'));
    $basePermissions = admin_role_default_permissions($role);
    $customPermissions = admin_sanitize_permissions(
        admin_decode_json_string_list((string)($admin['permissions_json'] ?? ''))
    );

    $combined = $role === 'custom'
        ? $customPermissions
        : array_merge($basePermissions, $customPermissions);

    if ($role === 'admin') {
        $combined[] = '*';
    }

    return array_values(array_unique($combined));
}

function admin_hidden_tabs(array $admin): array
{
    return admin_sanitize_hidden_tabs(
        admin_decode_json_string_list((string)($admin['hidden_tabs_json'] ?? ''))
    );
}

function admin_permissions_payload(): array
{
    $catalog = admin_permission_catalog();
    $payload = [];

    foreach ($catalog as $key => $meta) {
        $payload[] = [
            'key' => $key,
            'label' => (string)($meta['label'] ?? $key),
            'description' => (string)($meta['description'] ?? ''),
            'tabId' => (string)($meta['tab_id'] ?? ''),
        ];
    }

    return $payload;
}

function admin_tabs_payload(): array
{
    $catalog = admin_tab_catalog();
    $payload = [];

    foreach ($catalog as $tabId => $meta) {
        $payload[] = [
            'id' => $tabId,
            'label' => (string)($meta['label'] ?? $tabId),
            'permissions' => array_values(array_map('strval', (array)($meta['permissions'] ?? []))),
        ];
    }

    return $payload;
}

function admin_is_deleted(array $admin): bool
{
    return trim((string)($admin['deleted_at'] ?? '')) !== '';
}

function admin_is_email_verified(array $admin): bool
{
    return trim((string)($admin['email_verified_at'] ?? '')) !== '';
}

function admin_is_suspended(array $admin): bool
{
    $isActive = (int)($admin['is_active'] ?? 1) === 1;
    $suspendedUntilRaw = trim((string)($admin['suspended_until'] ?? ''));

    if (!$isActive) {
        return true;
    }

    if ($suspendedUntilRaw === '') {
        return false;
    }

    $timestamp = strtotime($suspendedUntilRaw);
    if ($timestamp === false) {
        return false;
    }

    return $timestamp > time();
}

function admin_account_block_reason(array $admin): ?string
{
    if (admin_is_deleted($admin)) {
        return 'This admin account is no longer available.';
    }

    $isActive = (int)($admin['is_active'] ?? 1) === 1;
    $suspendedUntilRaw = trim((string)($admin['suspended_until'] ?? ''));
    $suspendedReason = trim((string)($admin['suspended_reason'] ?? ''));

    if ($suspendedUntilRaw !== '') {
        $timestamp = strtotime($suspendedUntilRaw);
        if ($timestamp !== false && $timestamp > time()) {
            $label = date('M d, Y h:i A', $timestamp);
            $message = 'This admin account is suspended until ' . $label . '.';
            if ($suspendedReason !== '') {
                $message .= ' Reason: ' . $suspendedReason;
            }
            return $message;
        }
    }

    if (!$isActive) {
        $message = 'This admin account is suspended until changed by the primary admin.';
        if ($suspendedReason !== '') {
            $message .= ' Reason: ' . $suspendedReason;
        }
        return $message;
    }

    return null;
}

function admin_has_any_permission(array $admin, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (admin_has_permission($admin, (string)$permission)) {
            return true;
        }
    }

    return false;
}

function admin_set_email_verification_pending_session(array $admin, string $nextTarget): void
{
    $_SESSION['admin_email_verify_pending'] = [
        'admin_id' => (int)($admin['id'] ?? 0),
        'email' => strtolower(trim((string)($admin['email'] ?? ''))),
        'next' => admin_safe_redirect_target($nextTarget, 'admin-panel.php'),
        'created_at' => time(),
        'ip' => admin_get_client_ip(),
        'ua_hash' => admin_two_factor_user_agent_hash(),
    ];
}

function admin_get_email_verification_pending_session(): ?array
{
    $pending = $_SESSION['admin_email_verify_pending'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $adminId = isset($pending['admin_id']) ? (int)$pending['admin_id'] : 0;
    $email = strtolower(trim((string)($pending['email'] ?? '')));
    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    $ip = (string)($pending['ip'] ?? '');
    $uaHash = (string)($pending['ua_hash'] ?? '');
    $next = admin_safe_redirect_target((string)($pending['next'] ?? ''), 'admin-panel.php');

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $createdAt <= 0) {
        unset($_SESSION['admin_email_verify_pending']);
        return null;
    }

    if ((time() - $createdAt) > 900) {
        unset($_SESSION['admin_email_verify_pending']);
        return null;
    }

    if (!admin_client_ip_matches($ip, admin_get_client_ip()) || $uaHash !== admin_two_factor_user_agent_hash()) {
        unset($_SESSION['admin_email_verify_pending']);
        return null;
    }

    return [
        'admin_id' => $adminId,
        'email' => $email,
        'next' => $next,
        'created_at' => $createdAt,
    ];
}

function admin_clear_email_verification_pending_session(): void
{
    unset($_SESSION['admin_email_verify_pending']);
}

function admin_clear_email_verification_code(mysqli $con, int $adminId): void
{
    $stmt = $con->prepare(
        'UPDATE admin_users
         SET verification_code_hash = NULL,
             verification_expires_at = NULL,
             verification_attempts = 0
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_store_email_verification_code(mysqli $con, int $adminId, string $code): bool
{
    $normalizedCode = admin_normalize_numeric_code($code);
    if (!preg_match('/^\d{6}$/', $normalizedCode)) {
        return false;
    }

    $hash = commerza_password_hash($normalizedCode);
    if ($hash === '') {
        return false;
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET verification_code_hash = ?,
             verification_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
             verification_attempts = 0,
             verification_last_sent_at = NOW()
         WHERE id = ?
           AND deleted_at IS NULL
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $hash, $adminId);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    return $ok && $affected === 1;
}

function admin_email_verification_resend_retry_after(mysqli $con, int $adminId, int $cooldownSeconds = 45): int
{
    if ($adminId <= 0 || $cooldownSeconds <= 0) {
        return 0;
    }

    $stmt = $con->prepare(
        'SELECT verification_last_sent_at
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $lastSentAt = trim((string)($row['verification_last_sent_at'] ?? ''));
    if ($lastSentAt === '') {
        return 0;
    }

    $lastSentTs = strtotime($lastSentAt);
    if ($lastSentTs === false) {
        return 0;
    }

    $elapsed = time() - $lastSentTs;
    if ($elapsed >= $cooldownSeconds) {
        return 0;
    }

    return max(1, $cooldownSeconds - max(0, $elapsed));
}

function admin_send_email_verification_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $context = admin_email_context();
    $siteName = (string)($context['site_name'] ?? 'Commerza');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = $siteName . ' Admin Email Verification Code';

    $body =
        '<p style="margin:0 0 10px 0;">Hello ' . $safeName . ',</p>' .
        '<p style="margin:0 0 14px 0;">Use this 6-digit code to verify your admin account email. The code expires in <strong>15 minutes</strong> and can be used only once.</p>' .
        '<div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:4px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>' .
        '<p style="margin:16px 0 0 0;color:#cfcfcf;">If you did not expect this code, contact the primary admin immediately.</p>';

    $html = admin_email_layout('Admin Email Verification', 'Verify your admin email before accessing the panel.', $body);

    $fromEmail = admin_mail_from_email();

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $html,
        $fromEmail,
        $siteName . ' Admin Security',
        $errorMessage
    );
}

function admin_issue_email_verification_challenge(mysqli $con, array $admin, ?string &$errorMessage = null): bool
{
    $adminId = (int)($admin['id'] ?? 0);
    $email = strtolower(trim((string)($admin['email'] ?? '')));
    $fullName = trim((string)($admin['full_name'] ?? 'Admin'));

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid admin account state.';
        return false;
    }

    if (admin_is_email_verified($admin)) {
        return true;
    }

    $retryAfterSeconds = admin_email_verification_resend_retry_after($con, $adminId, 45);
    if ($retryAfterSeconds > 0) {
        $errorMessage = 'Please wait ' . $retryAfterSeconds . ' second(s) before requesting another verification code.';
        return false;
    }

    $code = admin_generate_two_factor_code();
    if (!admin_store_email_verification_code($con, $adminId, $code)) {
        $errorMessage = 'Unable to generate verification code.';
        return false;
    }

    $mailError = null;
    $mailSent = admin_send_email_verification_code_email($email, $fullName, $code, $mailError);
    if (!$mailSent) {
        admin_clear_email_verification_code($con, $adminId);
        $errorMessage = $mailError ?: 'Unable to send verification email.';
        return false;
    }

    return true;
}

function admin_verify_email_verification_code(mysqli $con, int $adminId, string $code): array
{
    $normalizedCode = admin_normalize_numeric_code($code);

    if ($adminId <= 0) {
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
            'admin' => null,
        ];
    }

    if (!preg_match('/^\d{6}$/', $normalizedCode)) {
        return [
            'ok' => false,
            'status' => 'invalid_code_format',
            'message' => 'Enter a valid 6-digit verification code.',
            'admin' => null,
        ];
    }

    if (!$con->begin_transaction()) {
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $stmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            verification_code_hash,
            verification_expires_at,
            verification_attempts,
            email_verified_at,
            suspended_until,
            suspended_reason,
            deleted_at,
            is_active,
            CASE
                WHEN verification_expires_at IS NOT NULL AND verification_expires_at >= NOW() THEN 1
                ELSE 0
            END AS verification_code_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$stmt) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($admin)) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
            'admin' => null,
        ];
    }

    if (admin_is_deleted($admin)) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'deleted',
            'message' => 'This admin account is no longer available.',
            'admin' => null,
        ];
    }

    if (admin_is_email_verified($admin)) {
        $con->commit();
        return [
            'ok' => true,
            'status' => 'already_verified',
            'message' => 'Email is already verified.',
            'admin' => $admin,
        ];
    }

    $codeHash = (string)($admin['verification_code_hash'] ?? '');
    $attempts = (int)($admin['verification_attempts'] ?? 0);
    $isCodeActive = (int)($admin['verification_code_active'] ?? 0) === 1;

    if ($codeHash === '' || !$isCodeActive) {
        admin_clear_email_verification_code($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Verification code expired. Request a new code.',
            'admin' => null,
        ];
    }

    if ($attempts >= 6) {
        admin_clear_email_verification_code($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid attempts. Request a new code.',
            'admin' => null,
        ];
    }

    if (commerza_password_verify($normalizedCode, $codeHash)) {
        $updateStmt = $con->prepare(
            'UPDATE admin_users
             SET email_verified_at = NOW(),
                 verification_code_hash = NULL,
                 verification_expires_at = NULL,
                 verification_attempts = 0
             WHERE id = ?
             LIMIT 1'
        );

        if (!$updateStmt) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        $resolvedId = (int)$admin['id'];
        $updateStmt->bind_param('i', $resolvedId);
        $updated = $updateStmt->execute();
        $updateStmt->close();

        if (!$updated || !$con->commit()) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        $freshAdmin = admin_get_by_id($con, $resolvedId);
        return [
            'ok' => true,
            'status' => 'verified',
            'message' => 'Email verified successfully.',
            'admin' => $freshAdmin,
        ];
    }

    $nextAttempts = $attempts + 1;
    if ($nextAttempts >= 6) {
        admin_clear_email_verification_code($con, (int)$admin['id']);
    } else {
        $attemptStmt = $con->prepare(
            'UPDATE admin_users
             SET verification_attempts = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$attemptStmt) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        $resolvedId = (int)$admin['id'];
        $attemptStmt->bind_param('ii', $nextAttempts, $resolvedId);
        $attemptStmt->execute();
        $attemptStmt->close();
    }

    if (!$con->commit()) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    if ($nextAttempts >= 6) {
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid attempts. Request a new code.',
            'admin' => null,
        ];
    }

    return [
        'ok' => false,
        'status' => 'invalid_code',
        'message' => 'Invalid verification code.',
        'admin' => null,
    ];
}

function admin_update_last_login(mysqli $con, int $adminId): void
{
    $ip = admin_get_client_ip();

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET last_login_at = NOW(), last_login_ip = ?
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('si', $ip, $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_session_lifetime_seconds(): int
{
    $seconds = (int)ini_get('session.gc_maxlifetime');
    if ($seconds < 900) {
        $seconds = 21600;
    }

    if ($seconds > 172800) {
        $seconds = 172800;
    }

    return $seconds;
}

function admin_session_table_ready(mysqli $con): bool
{
    static $ready = null;

    if (is_bool($ready)) {
        return $ready;
    }

    $sql =
        'CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT NOT NULL AUTO_INCREMENT,
            admin_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $ready = $con->query($sql) === true;
    return $ready;
}

function admin_session_cleanup_expired(mysqli $con): void
{
    static $cleaned = false;

    if ($cleaned || !admin_session_table_ready($con)) {
        return;
    }

    $con->query('DELETE FROM admin_sessions WHERE expires_at < NOW()');
    $cleaned = true;
}

function admin_session_token_value(): string
{
    $sessionId = trim((string)session_id());
    if ($sessionId === '') {
        return '';
    }

    return hash('sha256', $sessionId);
}

function admin_session_register(mysqli $con, int $adminId): bool
{
    if ($adminId <= 0 || !admin_session_table_ready($con)) {
        return false;
    }

    admin_session_cleanup_expired($con);

    $token = admin_session_token_value();
    if ($token === '') {
        return false;
    }

    $ip = admin_get_client_ip();
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($userAgent) > 255) {
        $userAgent = substr($userAgent, 0, 255);
    }

    $lifetimeSeconds = admin_session_lifetime_seconds();

    $stmt = $con->prepare(
        'INSERT INTO admin_sessions (admin_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
         ON DUPLICATE KEY UPDATE
            admin_id = VALUES(admin_id),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isssii', $adminId, $token, $ip, $userAgent, $lifetimeSeconds, $lifetimeSeconds);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function admin_session_validate(mysqli $con, int $adminId): bool
{
    if ($adminId <= 0) {
        return false;
    }

    if (!admin_session_table_ready($con)) {
        return true;
    }

    admin_session_cleanup_expired($con);

    $token = admin_session_token_value();
    if ($token === '') {
        return false;
    }

    $stmt = $con->prepare(
        'SELECT id
         FROM admin_sessions
         WHERE admin_id = ?
           AND token = ?
           AND expires_at >= NOW()
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $adminId, $token);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function admin_session_touch(mysqli $con, int $adminId): void
{
    if ($adminId <= 0 || !admin_session_table_ready($con)) {
        return;
    }

    $token = admin_session_token_value();
    if ($token === '') {
        return;
    }

    $lifetimeSeconds = admin_session_lifetime_seconds();
    $stmt = $con->prepare(
        'UPDATE admin_sessions
            SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
         WHERE admin_id = ?
           AND token = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iis', $lifetimeSeconds, $adminId, $token);
    $stmt->execute();
    $stmt->close();
}

function admin_session_revoke_current(mysqli $con, int $adminId): void
{
    if ($adminId <= 0 || !admin_session_table_ready($con)) {
        return;
    }

    $token = admin_session_token_value();
    if ($token === '') {
        return;
    }

    $stmt = $con->prepare(
        'DELETE FROM admin_sessions
         WHERE admin_id = ?
           AND token = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('is', $adminId, $token);
    $stmt->execute();
    $stmt->close();
}

function admin_revoke_all_sessions_for_admin(mysqli $con, int $adminId): void
{
    if ($adminId <= 0 || !admin_session_table_ready($con)) {
        return;
    }

    $stmt = $con->prepare('DELETE FROM admin_sessions WHERE admin_id = ?');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_clear_login_challenges(mysqli $con, int $adminId): void
{
    if ($adminId <= 0) {
        return;
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET reset_token = NULL,
             reset_token_expiry = NULL,
             verification_code_hash = NULL,
             verification_expires_at = NULL,
             verification_attempts = 0,
             two_factor_code_hash = NULL,
             two_factor_expires_at = NULL,
             two_factor_attempts = 0
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_login_user(mysqli $con, array $admin): void
{
    session_regenerate_id(true);

    $adminId = (int)($admin['id'] ?? 0);
    if ($adminId <= 0) {
        return;
    }

    $_SESSION['admin_user_id'] = $adminId;
    $_SESSION['admin_authenticated_at'] = time();
    unset($_SESSION['admin_2fa_pending']);

    admin_clear_login_challenges($con, $adminId);
    admin_update_last_login($con, $adminId);
    admin_generate_csrf_token();
    admin_session_register($con, $adminId);
}

function admin_logout_user(?mysqli $con = null): void
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($con instanceof mysqli && $adminId > 0) {
        admin_session_revoke_current($con, $adminId);
    }

    unset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_authenticated_at'],
        $_SESSION['admin_csrf_token'],
        $_SESSION['admin_2fa_pending']
    );
}

function admin_safe_redirect_target(?string $value, string $fallback = 'admin-panel.php'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        return $fallback;
    }

    if (strpos($value, '/') !== false || strpos($value, '\\') !== false) {
        return $fallback;
    }

    $allowed = [
        'admin-panel.php',
        'admin-login.php',
        'admin-verify-email.php',
        'admin-verify-2fa.php',
        'admin-forgot-password.php',
        'admin-forgot-email.php',
    ];

    return in_array($value, $allowed, true) ? $value : $fallback;
}

function admin_require_login(mysqli $con): array
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($adminId <= 0) {
        header('Location: admin-login.php');
        exit;
    }

    $admin = admin_get_by_id($con, $adminId);
    if (!$admin) {
        admin_logout_user($con);
        header('Location: admin-login.php');
        exit;
    }

    $blockReason = admin_account_block_reason($admin);
    if ($blockReason !== null) {
        admin_revoke_all_sessions_for_admin($con, $adminId);
        admin_logout_user($con);
        $_SESSION['admin_login_error'] = $blockReason;
        header('Location: admin-login.php');
        exit;
    }

    if (!admin_session_validate($con, $adminId)) {
        admin_logout_user($con);
        $_SESSION['admin_login_error'] = 'Session expired or revoked. Please login again.';
        header('Location: admin-login.php');
        exit;
    }

    admin_session_touch($con, $adminId);

    return $admin;
}

function admin_require_login_api(mysqli $con): array
{
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;
    if ($adminId <= 0) {
        admin_api_json_exit([
            'ok' => false,
            'message' => 'Unauthorized.',
        ], 401);
    }

    $admin = admin_get_by_id($con, $adminId);
    if (!$admin) {
        admin_logout_user($con);
        admin_api_json_exit([
            'ok' => false,
            'message' => 'Unauthorized.',
        ], 401);
    }

    $blockReason = admin_account_block_reason($admin);
    if ($blockReason !== null) {
        admin_revoke_all_sessions_for_admin($con, $adminId);
        admin_logout_user($con);
        admin_api_json_exit([
            'ok' => false,
            'message' => $blockReason,
        ], 403);
    }

    if (!admin_session_validate($con, $adminId)) {
        admin_logout_user($con);
        admin_api_json_exit([
            'ok' => false,
            'message' => 'Session expired or revoked. Please login again.',
        ], 401);
    }

    admin_session_touch($con, $adminId);

    return $admin;
}

function admin_has_permission(array $admin, string $permission): bool
{
    if (admin_is_deleted($admin) || admin_is_suspended($admin)) {
        return false;
    }

    $permission = admin_normalize_permission_name($permission);
    if ($permission === '') {
        return true;
    }

    $role = admin_normalize_role((string)($admin['role'] ?? 'admin'));
    if ($role === 'admin') {
        return true;
    }

    $effectivePermissions = admin_effective_permissions($admin);
    if (in_array('*', $effectivePermissions, true)) {
        return true;
    }

    if (in_array($permission, $effectivePermissions, true)) {
        return true;
    }

    $segments = explode('.', $permission, 2);
    if (count($segments) !== 2) {
        return false;
    }

    [$prefix, $scope] = $segments;
    if ($prefix === '' || $scope === '') {
        return false;
    }

    if (in_array($prefix . '.*', $effectivePermissions, true)) {
        return true;
    }

    if ($scope === 'view' && in_array($prefix . '.manage', $effectivePermissions, true)) {
        return true;
    }

    return false;
}

function admin_require_permission_api(array $admin, string $permission): void
{
    if (admin_has_permission($admin, $permission)) {
        return;
    }

    admin_api_json_exit([
        'ok' => false,
        'message' => 'Forbidden.',
    ], 403);
}

function admin_api_scope(string $prefix, string $action = 'default'): string
{
    $prefix = strtolower(trim($prefix));
    $action = strtolower(trim($action));

    if ($prefix === '') {
        $prefix = 'admin_api';
    }

    if ($action === '') {
        $action = 'default';
    }

    $normalizedPrefix = preg_replace('/[^a-z0-9_\-.]+/', '_', $prefix) ?? 'admin_api';
    $normalizedAction = preg_replace('/[^a-z0-9_\-.]+/', '_', $action) ?? 'default';

    return trim($normalizedPrefix . '.' . $normalizedAction, '.');
}

function admin_api_rate_limit_guard(
    mysqli $con,
    array $admin,
    string $scope,
    int $maxAttempts = 120,
    int $windowSeconds = 60,
    int $blockSeconds = 120,
    int $escalatedBlockSeconds = 300
): void {
    $identifier = strtolower(trim((string)($admin['email'] ?? '')));
    if ($identifier === '') {
        $identifier = 'admin_' . (int)($admin['id'] ?? 0);
    }

    $ipAddress = admin_get_client_ip();
    $rate = commerza_rate_limit_check(
        $con,
        $scope,
        $identifier,
        $ipAddress,
        max(1, $maxAttempts),
        max(60, $windowSeconds),
        max(60, $blockSeconds),
        max($blockSeconds, $escalatedBlockSeconds)
    );

    if ((bool)($rate['allowed'] ?? true)) {
        return;
    }

    $retryAfter = max(1, (int)($rate['retry_after'] ?? $blockSeconds));

    commerza_security_log_rate_limit_block(
        $con,
        $scope,
        'admin',
        $identifier,
        $ipAddress,
        $retryAfter
    );

    admin_api_json_exit([
        'ok' => false,
        'message' => 'Too many requests. Please retry shortly.',
        'retry_after' => $retryAfter,
    ], 429);
}

function admin_api_log_security_event(
    mysqli $con,
    array $admin,
    string $eventType,
    string $severity = 'info',
    array $details = []
): void {
    $actorIdentifier = strtolower(trim((string)($admin['email'] ?? '')));
    if ($actorIdentifier === '') {
        $actorIdentifier = 'admin_' . (int)($admin['id'] ?? 0);
    }

    commerza_security_log_event($con, [
        'event_type' => trim($eventType) !== '' ? $eventType : 'admin.action',
        'severity' => $severity,
        'actor_type' => 'admin',
        'actor_identifier' => $actorIdentifier,
        'admin_id' => (int)($admin['id'] ?? 0),
        'ip_address' => admin_get_client_ip(),
        'details' => $details,
    ]);
}

function admin_request_id_value(array $request = []): string
{
    $headerValue = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($headerValue !== '') {
        return $headerValue;
    }

    return trim((string)($request['request_id'] ?? ''));
}

function admin_enforce_post_idempotency_guard(mysqli $con): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        return;
    }

    $scriptName = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (!str_contains($scriptName, '/admin/backend/')) {
        return;
    }

    if (str_ends_with($scriptName, '/auth.php')) {
        return;
    }

    $scriptBase = strtolower((string)basename($scriptName, '.php'));
    if ($scriptBase === '') {
        $scriptBase = 'admin_api';
    }

    $action = strtolower(trim((string)($_POST['action'] ?? 'post')));
    if ($action === '') {
        $action = 'post';
    }

    $csrfToken = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($csrfToken === '') {
        $csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
    }

    $sessionCsrf = trim((string)($_SESSION['admin_csrf_token'] ?? ''));
    if ($csrfToken === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrfToken)) {
        // Let endpoint-level CSRF guards return the canonical 403 response.
        return;
    }

    $scope = 'admin_' . preg_replace('/[^a-z0-9_\-.]+/', '_', $scriptBase) . '.' . preg_replace('/[^a-z0-9_\-.]+/', '_', $action);
    $requestId = admin_request_id_value($_POST);
    $idempotency = commerza_idempotency_consume($con, $scope, $requestId, 21600);

    if ((bool)($idempotency['ok'] ?? false)) {
        return;
    }

    $status = (int)($idempotency['status'] ?? 409);
    if ($status <= 0) {
        $status = 409;
    }

    admin_api_json_exit([
        'ok' => false,
        'message' => (string)($idempotency['message'] ?? 'Duplicate request detected and ignored.'),
    ], $status);
}

admin_enforce_post_idempotency_guard($con);

function admin_normalize_numeric_code(string $code): string
{
    $digits = preg_replace('/\D+/', '', trim($code));
    if (!is_string($digits)) {
        return '';
    }

    if (strlen($digits) > 6) {
        $digits = substr($digits, 0, 6);
    }

    return $digits;
}

function admin_store_reset_code(mysqli $con, int $adminId, string $code): bool
{
    $code = admin_normalize_numeric_code($code);

    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $hash = commerza_password_hash($code);
    if ($hash === '') {
        return false;
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
            SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $hash, $adminId);
    $ok = $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    return $ok && $affected === 1;
}

function admin_verify_reset_code_status(mysqli $con, int $adminId, string $code): array
{
    $code = admin_normalize_numeric_code($code);

    if ($adminId <= 0) {
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
        ];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return [
            'ok' => false,
            'status' => 'invalid_code_format',
            'message' => 'Enter a valid 6-digit reset code.',
        ];
    }

    $stmt = $con->prepare(
        'SELECT
            reset_token,
            CASE
                WHEN reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW() THEN 1
                ELSE 0
            END AS reset_code_is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify reset code right now.',
        ];
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return [
            'ok' => false,
            'status' => 'invalid_admin',
            'message' => 'Invalid admin account.',
        ];
    }

    $hash = (string)($row['reset_token'] ?? '');
    $isActive = (int)($row['reset_code_is_active'] ?? 0) === 1;

    if ($hash === '') {
        return [
            'ok' => false,
            'status' => 'missing_code',
            'message' => 'No active reset code found. Request a new code.',
        ];
    }

    if (!$isActive) {
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Reset code expired. Request a new code.',
        ];
    }

    if (!commerza_password_verify($code, $hash)) {
        return [
            'ok' => false,
            'status' => 'invalid_code',
            'message' => 'Invalid reset code.',
        ];
    }

    return [
        'ok' => true,
        'status' => 'verified',
        'message' => 'Reset code verified.',
    ];
}

function admin_verify_reset_code(mysqli $con, array $admin, string $code): bool
{
    $adminId = (int)($admin['id'] ?? 0);
    $status = admin_verify_reset_code_status($con, $adminId, $code);

    return (bool)($status['ok'] ?? false);
}

function admin_clear_reset_code(mysqli $con, int $adminId): void
{
    $stmt = $con->prepare(
        'UPDATE admin_users
         SET reset_token = NULL, reset_token_expiry = NULL
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_public_url(string $path = ''): string
{
    if (function_exists('commerza_public_base_url')) {
        $base = rtrim((string)commerza_public_base_url(), '/');
    } else {
        $configured = trim((string)(getenv('COMMERZA_APP_URL') ?: getenv('COMMERZA_PUBLIC_URL') ?: getenv('APP_URL') ?: ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            $base = rtrim($configured, '/');
        } else {
            $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
            $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            $cfVisitor = strtolower((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));
            $isHttps = ($https !== '' && $https !== 'off')
                || ($forwardedProto !== '' && str_contains($forwardedProto, 'https'))
                || ($cfVisitor !== '' && str_contains($cfVisitor, '"https"'))
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
            $scheme = $isHttps ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost')));
            if ($host === '') {
                $host = 'localhost';
            }

            $base = $scheme . '://' . $host;
        }
    }

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function admin_email_setting(mysqli $con, string $key, string $fallback = ''): string
{
    $stmt = $con->prepare(
        'SELECT setting_val
         FROM site_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $fallback;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return $fallback;
    }

    $value = trim((string)($row['setting_val'] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function admin_normalize_site_name_for_display(string $value, string $fallback = 'Commerza'): string
{
    $name = trim($value);
    if ($name === '') {
        return $fallback;
    }

    $lettersOnly = preg_replace('/[^a-z]/i', '', $name);
    if (is_string($lettersOnly) && $lettersOnly !== '' && strtoupper($lettersOnly) === $lettersOnly) {
        return ucwords(strtolower($name));
    }

    return $name;
}

function admin_email_context(): array
{
    global $con;

    $siteName = 'Commerza';
    $supportEmail = trim((string)(getenv('COMMERZA_SUPPORT_EMAIL') ?: 'support@ahmershah.dev'));

    if ($con instanceof mysqli) {
        $siteName = admin_email_setting($con, 'site_name', $siteName);

        $siteEmail = strtolower(trim(admin_email_setting($con, 'site_email', '')));
        if ($siteEmail !== '' && filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
            $supportEmail = $siteEmail;
        }
    }

    $siteName = admin_normalize_site_name_for_display($siteName, 'Commerza');

    if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $supportEmail = 'support@ahmershah.dev';
    }

    return [
        'site_name' => $siteName,
        'support_email' => $supportEmail,
    ];
}

function admin_mail_from_email(): string
{
    global $con;

    $defaultSender = commerza_mail_default_sender();
    $candidates = [
        getenv('COMMERZA_SUPPORT_EMAIL'),
        $defaultSender['email'] ?? '',
        getenv('COMMERZA_SMTP_USERNAME'),
    ];

    if ($con instanceof mysqli) {
        $candidates[] = admin_email_setting($con, 'site_email', '');
    }

    foreach ($candidates as $candidate) {
        $email = strtolower(trim((string)$candidate));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }

    return 'support@ahmershah.dev';
}

function admin_email_layout(string $title, string $intro, string $bodyHtml): string
{
    $context = admin_email_context();
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $safeSiteName = htmlspecialchars((string)($context['site_name'] ?? 'Commerza'), ENT_QUOTES, 'UTF-8');
    $safeHome = htmlspecialchars(admin_public_url('/'), ENT_QUOTES, 'UTF-8');
    $safeSupportEmail = htmlspecialchars((string)($context['support_email'] ?? 'support@ahmershah.dev'), ENT_QUOTES, 'UTF-8');
    $socialLinks = '<a href="https://instagram.com/commerza" style="color:#ffb066;text-decoration:none;">Instagram</a> <span style="color:#666;">|</span> <a href="https://facebook.com/commerza" style="color:#ffb066;text-decoration:none;">Facebook</a> <span style="color:#666;">|</span> <a href="https://www.linkedin.com/in/syedahmershah" style="color:#ffb066;text-decoration:none;">LinkedIn</a> <span style="color:#666;">|</span> <a href="https://github.com/ahmershahdev" style="color:#ffb066;text-decoration:none;">GitHub</a>';

    return '<!DOCTYPE html>
<html>
    <body style="margin:0;padding:0;background:#080808;font-family:Segoe UI,Arial,sans-serif;color:#f5f5f5;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#080808;padding:24px 0;">
            <tr>
                <td align="center">
                    <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#121212;border:1px solid #2d2d2d;border-radius:12px;overflow:hidden;">
                        <tr>
                            <td style="padding:18px 24px;background:linear-gradient(90deg,#161616,#101010);border-bottom:1px solid #2b2b2b;">
                                <a href="' . $safeHome . '" style="text-decoration:none;display:inline-flex;align-items:center;gap:10px;">
                                    <span style="color:#ff8a2b;font-size:18px;font-weight:800;letter-spacing:.6px;">' . $safeSiteName . ' Admin Security</span>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:22px 28px 8px 28px;">
                                <h1 style="margin:0;color:#ff9d45;font-size:22px;">' . $safeTitle . '</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 28px 0 28px;">
                                <p style="margin:0;color:#d7d7d7;line-height:1.65;">' . $safeIntro . '</p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:16px 28px 26px 28px;line-height:1.65;color:#ececec;">' . $bodyHtml . '</td>
                        </tr>
                        <tr>
                            <td style="padding:12px 28px;background:#0f0f0f;border-top:1px solid #2b2b2b;">
                                <p style="margin:0;color:#999;font-size:12px;">If you did not initiate this request, secure your admin account immediately.</p>
                                <p style="margin:8px 0 0 0;color:#999;font-size:12px;">Support: <a href="mailto:' . $safeSupportEmail . '" style="color:#ffb066;text-decoration:none;">' . $safeSupportEmail . '</a></p>
                                <p style="margin:8px 0 0 0;color:#999;font-size:12px;">Connect: ' . $socialLinks . '</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>';
}

function admin_send_password_reset_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $context = admin_email_context();
    $siteName = (string)($context['site_name'] ?? 'Commerza');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = $siteName . ' Admin Password Reset Code';

    $body =
        '<p style="margin:0 0 10px 0;">Hello ' . $safeName . ',</p>' .
        '<p style="margin:0 0 14px 0;">Use this 6-digit code to reset your admin password. The code expires in <strong>15 minutes</strong>.</p>' .
        '<div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:4px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>' .
        '<p style="margin:16px 0 0 0;color:#cfcfcf;">For your security, do not share this code with anyone.</p>';

    $html = admin_email_layout('Admin Password Reset', 'A secure verification step is required to continue.', $body);

    $fromEmail = admin_mail_from_email();

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $html,
        $fromEmail,
        $siteName . ' Admin',
        $errorMessage
    );
}

function admin_generate_two_factor_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function admin_two_factor_user_agent_hash(): string
{
    $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return hash('sha256', $userAgent);
}

function admin_set_two_factor_pending_session(array $admin, string $nextTarget): void
{
    $_SESSION['admin_2fa_pending'] = [
        'admin_id' => (int)($admin['id'] ?? 0),
        'email' => (string)($admin['email'] ?? ''),
        'next' => admin_safe_redirect_target($nextTarget, 'admin-panel.php'),
        'created_at' => time(),
        'ip' => admin_get_client_ip(),
        'ua_hash' => admin_two_factor_user_agent_hash(),
    ];
}

function admin_get_two_factor_pending_session(): ?array
{
    $pending = $_SESSION['admin_2fa_pending'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $adminId = isset($pending['admin_id']) ? (int)$pending['admin_id'] : 0;
    $email = strtolower(trim((string)($pending['email'] ?? '')));
    $createdAt = isset($pending['created_at']) ? (int)$pending['created_at'] : 0;
    $ip = (string)($pending['ip'] ?? '');
    $uaHash = (string)($pending['ua_hash'] ?? '');
    $next = admin_safe_redirect_target((string)($pending['next'] ?? ''), 'admin-panel.php');

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $createdAt <= 0) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    if ((time() - $createdAt) > 900) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    if (!admin_client_ip_matches($ip, admin_get_client_ip()) || $uaHash !== admin_two_factor_user_agent_hash()) {
        unset($_SESSION['admin_2fa_pending']);
        return null;
    }

    return [
        'admin_id' => $adminId,
        'email' => $email,
        'next' => $next,
        'created_at' => $createdAt,
    ];
}

function admin_clear_two_factor_pending_session(): void
{
    unset($_SESSION['admin_2fa_pending']);
}

function admin_send_two_factor_code_email(
    string $recipientEmail,
    string $recipientName,
    string $code,
    ?string &$errorMessage = null
): bool {
    $context = admin_email_context();
    $siteName = (string)($context['site_name'] ?? 'Commerza');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    $subject = $siteName . ' Admin Login Verification Code';

    $body =
        '<p style="margin:0 0 10px 0;">Hello ' . $safeName . ',</p>' .
        '<p style="margin:0 0 14px 0;">Use this 6-digit code to complete your admin login. The code expires in <strong>15 minutes</strong>.</p>' .
        '<div style="display:inline-block;padding:12px 18px;background:#1b1b1b;border:1px solid #ff6600;border-radius:8px;font-size:24px;letter-spacing:4px;font-weight:700;color:#ffcc00;">' . $safeCode . '</div>' .
        '<p style="margin:16px 0 0 0;color:#cfcfcf;">If this was not you, reset your admin password immediately.</p>';

    $html = admin_email_layout('Two-Factor Login Verification', 'Enter this code to finish secure admin authentication.', $body);

    $fromEmail = admin_mail_from_email();

    return commerza_send_html_mail(
        $recipientEmail,
        $subject,
        $html,
        $fromEmail,
        $siteName . ' Admin Security',
        $errorMessage
    );
}

function admin_clear_two_factor_challenge(mysqli $con, int $adminId): void
{
    $stmt = $con->prepare(
        'UPDATE admin_users
         SET two_factor_code_hash = NULL,
             two_factor_expires_at = NULL,
             two_factor_attempts = 0
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $stmt->close();
}

function admin_issue_two_factor_challenge(mysqli $con, array $admin, string $nextTarget, ?string &$errorMessage = null): bool
{
    $adminId = (int)($admin['id'] ?? 0);
    $email = strtolower(trim((string)($admin['email'] ?? '')));
    $fullName = (string)($admin['full_name'] ?? 'Admin');

    $blockReason = admin_account_block_reason($admin);
    if ($blockReason !== null) {
        $errorMessage = $blockReason;
        return false;
    }

    if (!admin_is_email_verified($admin)) {
        $errorMessage = 'Email verification is required before login.';
        return false;
    }

    if ($adminId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid admin account state.';
        return false;
    }

    $code = admin_generate_two_factor_code();
    $hash = commerza_password_hash($code);
    if ($hash === '') {
        $errorMessage = 'Unable to generate verification code.';
        return false;
    }

    $stmt = $con->prepare(
        'UPDATE admin_users
         SET two_factor_code_hash = ?,
             two_factor_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
             two_factor_attempts = 0,
             two_factor_last_sent_at = NOW()
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );

    if (!$stmt) {
        $errorMessage = 'Unable to start two-factor authentication.';
        return false;
    }

    $stmt->bind_param('si', $hash, $adminId);
    $ok = $stmt->execute();
    $affectedRows = (int)$stmt->affected_rows;
    $stmt->close();

    if (!$ok || $affectedRows !== 1) {
        $errorMessage = 'Unable to start two-factor authentication.';
        return false;
    }

    $mailError = null;
    $mailSent = admin_send_two_factor_code_email($email, $fullName, $code, $mailError);
    if (!$mailSent) {
        admin_clear_two_factor_challenge($con, $adminId);
        $errorMessage = $mailError ?: 'Unable to send verification email.';
        return false;
    }

    admin_set_two_factor_pending_session($admin, $nextTarget);
    return true;
}

function admin_verify_two_factor_code(mysqli $con, int $adminId, string $code): array
{
    $code = admin_normalize_numeric_code($code);

    if ($adminId <= 0) {
        return [
            'ok' => false,
            'status' => 'invalid_session',
            'message' => 'Two-factor session expired. Please login again.',
            'admin' => null,
        ];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return [
            'ok' => false,
            'status' => 'invalid_code_format',
            'message' => 'Enter the 6-digit verification code.',
            'admin' => null,
        ];
    }

    if (!$con->begin_transaction()) {
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $selectStmt = $con->prepare(
        'SELECT
            id,
            full_name,
            email,
            role,
            permissions_json,
            hidden_tabs_json,
            phone,
            email_verified_at,
            is_active,
            suspended_until,
            suspended_reason,
            deleted_at,
            two_factor_code_hash,
            two_factor_expires_at,
            two_factor_attempts,
            CASE
                WHEN two_factor_expires_at IS NOT NULL AND two_factor_expires_at >= NOW() THEN 1
                ELSE 0
            END AS two_factor_is_active
         FROM admin_users
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );

    if (!$selectStmt) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    $selectStmt->bind_param('i', $adminId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $admin = $result ? $result->fetch_assoc() : null;
    $selectStmt->close();

    $blockReason = is_array($admin) ? admin_account_block_reason($admin) : 'Two-factor session expired. Please login again.';
    if (!$admin || $blockReason !== null) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'invalid_session',
            'message' => $blockReason !== null ? $blockReason : 'Two-factor session expired. Please login again.',
            'admin' => null,
        ];
    }

    $codeHash = (string)($admin['two_factor_code_hash'] ?? '');
    $attempts = (int)($admin['two_factor_attempts'] ?? 0);
    $isChallengeActive = (int)($admin['two_factor_is_active'] ?? 0) === 1;

    if ($codeHash === '' || !$isChallengeActive) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Verification code expired. Please login again.',
            'admin' => null,
        ];
    }

    if ($attempts >= 6) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        $con->commit();
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid verification attempts. Please login again.',
            'admin' => null,
        ];
    }

    if (commerza_password_verify($code, $codeHash)) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
        if (!$con->commit()) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        return [
            'ok' => true,
            'status' => 'verified',
            'message' => 'Verification successful.',
            'admin' => $admin,
        ];
    }

    $nextAttempts = $attempts + 1;
    if ($nextAttempts >= 6) {
        admin_clear_two_factor_challenge($con, (int)$admin['id']);
    } else {
        $attemptStmt = $con->prepare(
            'UPDATE admin_users
             SET two_factor_attempts = ?
             WHERE id = ?
             LIMIT 1'
        );

        if (!$attemptStmt) {
            $con->rollback();
            return [
                'ok' => false,
                'status' => 'server_error',
                'message' => 'Unable to verify code right now.',
                'admin' => null,
            ];
        }

        $attemptStmt->bind_param('ii', $nextAttempts, $adminId);
        $attemptStmt->execute();
        $attemptStmt->close();
    }

    if (!$con->commit()) {
        $con->rollback();
        return [
            'ok' => false,
            'status' => 'server_error',
            'message' => 'Unable to verify code right now.',
            'admin' => null,
        ];
    }

    if ($nextAttempts >= 6) {
        return [
            'ok' => false,
            'status' => 'locked',
            'message' => 'Too many invalid verification attempts. Please login again.',
            'admin' => null,
        ];
    }

    return [
        'ok' => false,
        'status' => 'invalid_code',
        'message' => 'Invalid verification code.',
        'admin' => null,
    ];
}

