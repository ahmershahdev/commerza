# Commerza

Commerza is a PHP + MySQL ecommerce platform with a customer storefront and an operations-focused admin panel.

This README is the canonical engineering guide for setup, security posture, feature boundaries, and production operations.

## 1. Stack and Runtime

- Backend: PHP (mysqli), Apache, MySQL
- Frontend: Server-rendered PHP templates, Bootstrap, jQuery, modular CSS/JS assets
- Deployment shape: XAMPP-compatible (local) and Apache hosting (production)
- Shared bootstrap: backend/data.php

## 2. Project Structure

- admin/: admin panel UI and admin-only APIs
- backend/: shared helpers, storefront APIs, cron/report scripts, schema
- frontend/: storefront static assets (css, js, images, videos)
- root *.php pages: storefront and auth/public entrypoints
- infra/docs files: .htaccess, robots.txt, sitemap.xml, llms.txt, SECURITY.md

## 3. Public vs Restricted Surfaces

Public storefront routes are clean-route pages such as /, /products, /about, /contact, /shipping, /returns, and policy pages.

Restricted or sensitive surfaces include:

- /admin/*
- /backend/*
- account and transaction flows (/account, /cart, /order-tracking, /invoice)
- auth and recovery flows (/login, /signup, /forgot-password, /reset-password)

## 4. User Features

- Browse catalog and category pages
- Search and suggestions
- Cart, wishlist, compare
- Coupon-aware checkout (COD)
- Account profile management
- Password reset and account safety controls
- Order tracking and invoice views
- Product reviews (eligibility based on delivered/completed/received orders)

## 5. Admin Features

- Dashboard with KPI cards and recent-order visibility
- Product sections and product CRUD
- Product trash archive and restore workflows
- Orders table, status updates, and refund moderation
- Coupons management and campaign tooling
- Customer directory and blacklist controls
- Reviews moderation and review tooling
- Website settings, social links, slider/ticker controls
- Security event monitoring
- Email center and recipient management

## 6. Checkout and Payments

Checkout is currently COD-only.

- Accepted payment method on cart checkout: Cash on Delivery (COD)
- Stripe card checkout is disabled in cart.php (UI, client flow, and server verification path removed)
- Order placement still enforces CSRF, idempotency, CAPTCHA, stock locking, and coupon checks

## 7. Password Hashing and Policy Workflow

Password security is centralized in backend/security_helpers.php.

- Primary hash algorithm: Argon2id (when PASSWORD_ARGON2ID is available)
- Fallback hash algorithm: bcrypt with cost 12
- Emergency fallback in hashing: PASSWORD_DEFAULT if primary hashing fails
- Verify path: password_verify
- Rehash path: password_needs_rehash against current algorithm/options

Password policy baseline:

- Length: 10 to 64
- Requires uppercase, lowercase, number, special character
- No whitespace

## 8. Username Locking Policy

Account username changes are lock-protected.

- Lock duration: 90 days after each successful username change
- Enforcement: server-side during profile update (not only UI)
- Data source: users.username_changed_at

## 9. CAPTCHA and Anti-Bot Model

CAPTCHA is centralized in backend/security_helpers.php and uses a layered hybrid model.

Layered checks:

- Honeypot field and submit-time checks
- reCAPTCHA v3 verification
- reCAPTCHA v2 verification
- Built-in fallback challenge when needed

Current v3 hardening details:

- Default minimum score is stricter (0.65 default, floor 0.55)
- Action must match server-expected context
- Hostname must match request host
- challenge_ts freshness is validated
- Invalid token length and invalid score payloads are rejected

Fallback challenge hardening details:

- Randomized knowledge plus arithmetic challenge generation
- Answer normalization (case/spacing/symbol tolerance within strict bounds)
- Hashed answer verification per nonce and context
- Minimum solve-time guard
- Attempt tracking with lockout after repeated failures

## 10. OAuth (Google and Facebook)

OAuth login supports both providers through backend/oauth.php (via public oauth.php entrypoint).

Supported providers:

- Google OAuth
- Facebook OAuth

Configuration sources:

- Environment variables (COMMERZA_* and provider aliases)
- site_settings fallback keys

Security properties:

- Provider allowlist validation
- State/nonce validation with expiry handling
- Provider token exchange + profile fetch validation
- Safe error redirection back to auth flows

## 11. Email Routing, Automation, and SMTP Failover

Email behavior is centralized in backend/mailer.php plus notification helpers.

SMTP routing strategy:

- Primary route: sender_net_primary (configurable host/port/auth/encryption)
- Secondary route: gmail_fallback (configurable fallback transport)
- Duplicate route suppression when primary and secondary point to same host/account

Sender/logo handling:

- Sender identity resolved from configured SMTP sender or support fallback
- Email logo prefers PNG for compatibility
- If only WebP exists and GD supports conversion, PNG is generated automatically

Automation scripts include:

- backend/send_engagement_reminders.php
- backend/monthly_profit_report.php
- backend/weekly_analytics_report.php

## 12. Caching and Performance Model

Commerza uses layered caching and response optimization.

Application cache layers:

- Runtime in-process cache
- APCu cache (when available)
- Redis cache (when available)

Cache helper file:

- backend/cache_helpers.php

Important cache environment keys:

- COMMERZA_CACHE_ENABLED
- COMMERZA_CACHE_NAMESPACE
- COMMERZA_REDIS_HOST
- COMMERZA_REDIS_PORT
- COMMERZA_REDIS_DB
- COMMERZA_REDIS_PASSWORD
- COMMERZA_REDIS_TIMEOUT
- COMMERZA_SITE_SETTINGS_CACHE_TTL

Delivery optimizations:

- Static asset cache headers from .htaccess
- Shared HTML normalization and asset-loading optimizations in backend/data.php
- Fragment cache support for expensive render sections

## 13. Upload Security Controls

Upload paths are validated and scanned.

Controls include:

- MIME and extension checks
- Parser-based validation/transformation for images
- Malware signature checks
- Optional ClamAV scan command integration
- Fail-open or fail-closed behavior configurable by environment

Related environment keys:

- COMMERZA_UPLOAD_SCAN_ENABLED
- COMMERZA_UPLOAD_SCAN_FAIL_CLOSED
- COMMERZA_CLAMSCAN_PATH

## 14. Locking and Concurrency Safety

Critical flows include transactional locking and anti-race protections.

Examples:

- Checkout stock protection:
  - Transactional order placement
  - SELECT ... FOR UPDATE stock lock before decrement
  - Conditional stock update guard
- Checkout idempotency key consumption to block duplicate submissions
- Refund status update flow includes row-level lock semantics in admin orders API
- Username change lock window enforcement (90 days)

## 15. Product Lifecycle and Trash Semantics

Storefront product feeds now exclude archived/deleted entries.

- products API excludes products with deleted_at (when column exists)
- products API excludes products present in product_trash (when table exists)
- Result: trashed products do not appear on user-facing product/search payloads

## 16. Review Eligibility Semantics

Review eligibility is verified server-side.

- Accepted order statuses for eligibility: delivered, completed, received
- Product linkage supports product_id matching and legacy product_name fallback
- Refund-linked restrictions remain enforced where refund tables exist

## 17. Security Levels (Operational Reference)

Use this severity model when extending or reviewing features.

- Level 1 (baseline): input validation, output escaping, prepared statements
- Level 2 (sensitive forms/APIs): CSRF, rate limit, CAPTCHA, audit logging
- Level 3 (critical money/data paths): transaction boundaries, row locks, idempotency, explicit permission checks

## 18. Local Setup (XAMPP)

1. Put project in C:/xampp/htdocs/commerza
2. Import backend/database/commerza.sql into database commerza
3. Configure .env values
4. Start Apache and MySQL from XAMPP
5. Open http://localhost/commerza/

## 19. Useful Commands

PHP lint examples:

- C:/xampp/php/php.exe -l backend/security_helpers.php
- C:/xampp/php/php.exe -l cart.php
- C:/xampp/php/php.exe -l admin/backend/orders_api.php

Automation examples:

- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/send_engagement_reminders.php 180
- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/monthly_profit_report.php
- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/weekly_analytics_report.php

Backup/restore verification:

- powershell -ExecutionPolicy Bypass -File backend/backup_restore_test.ps1 -MySqlBinPath "C:/xampp/mysql/bin"

## 20. Release Checklist

1. Verify clean-route canonical behavior
2. Validate CAPTCHA flows in signup/login/reset/checkout/admin verification
3. Validate OAuth login for Google and Facebook
4. Validate SMTP primary and fallback delivery
5. Validate checkout, coupon, stock, and order status workflows
6. Validate admin KPI cards and core operations tabs
7. Run PHP lint on touched files

## 21. Documentation and Operations Files

- SECURITY.md: security policy and disclosure channel
- llms.txt: LLM-safe discovery guidance
- instructions.md: integration requirements reference
- backend/BACKUP_RESTORE_TESTS.md: backup and restore runbook
- backend/backup_restore_test.ps1: automated backup/restore verification script
