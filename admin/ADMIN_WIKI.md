# Commerza Admin Wiki

## 1. Scope

This wiki documents the full admin folder architecture, API responsibilities, security controls, and operational workflows for performance-safe and secure maintenance.

Root: `admin/`

- `backend/`: Auth + admin APIs.
- `frontend/`: Admin pages + UI assets.

## 2. Admin Folder Inventory

### 2.1 Backend (`admin/backend`)

- `auth.php`: Admin auth/session/CSRF helpers, permission checks, 2FA helpers, shared admin API rate-limit guard, shared admin action security-event logger.
- `orders_api.php`: Orders, customers, refunds data API; order status updates; bulk delete actions; refund status updates.
- `products_sync_api.php`: Full section/product sync read/write for panel product data.
- `coupons_api.php`: Coupon CRUD + coupon email campaign actions.
- `reviews_api.php`: Review moderation CRUD, visibility updates, stats.
- `security_api.php`: Security settings updates (email/password/reset key) + security events listing.
- `viewers_api.php`: Live viewer analytics mode/settings and snapshot.
- `website_api.php`: Branding/contact/social/ticker/slider/feature video settings.
- `media_api.php`: Secure admin media upload endpoint by target type.

### 2.2 Frontend Pages (`admin/frontend`)

- `admin-panel.php`: Main admin dashboard with all tabpanes.
- `admin-login.php`: Admin login + CAPTCHA + 2FA challenge issue.
- `admin-verify-2fa.php`: 2FA verification and resend flow.
- `admin-forgot-password.php`: Password reset code and reset flow.
- `admin-forgot-email.php`: Admin email recovery/update flow.

### 2.3 Frontend Assets (`admin/frontend/assets`)

- `css/style.css`: Modular CSS entrypoint.
- `css/modules/01-base.css` to `05-responsive.css`: Theme, layout, component styling, utilities, responsive behavior.
- `js/script.js`: Main admin-panel logic (all tabpane behavior + API integration).
- `js/admin-config.js`: Admin branding/meta adaptations from runtime settings.
- `js/pages/*`: Auth page-specific scripts.

## 3. Security Architecture

### 3.1 Session and Permission Gates

All admin APIs are protected by:

1. `admin_require_login_api($con)`
2. `admin_require_permission_api($admin, '<domain>.manage')`

### 3.2 CSRF Model

Mutating endpoints require `X-CSRF-Token` or posted `csrf_token`, validated via `admin_validate_csrf_token()`.

### 3.3 Rate Limiting

Admin APIs now apply action-scoped limits through:

- `admin_api_rate_limit_guard()` in `admin/backend/auth.php`
- Scope pattern: `admin_<api>.<action>`

This is enforced in:

- `orders_api.php`
- `products_sync_api.php`
- `coupons_api.php`
- `reviews_api.php`
- `security_api.php`
- `viewers_api.php`
- `website_api.php`
- `media_api.php`

### 3.4 Security Event Logging

Sensitive admin actions log via:

- `admin_api_log_security_event()` (wrapper)
- shared backend logger `commerza_security_log_event()`

New/strengthened logging includes:

- Order status changes
- Bulk order/customer deletion
- Refund status updates
- Coupon create/update/delete/campaign sends
- Review visibility/update/delete/upsert
- Media uploads
- Website settings writes
- Security settings writes (email/password/reset key)
- Viewer settings writes

## 4. Performance and Race Condition Hardening

### 4.1 Order Status Update Path

`orders_api.php` now uses a transaction + row lock (`FOR UPDATE`) for `update-status`.

Benefits:

- Prevents concurrent stale writes on rapid click bursts.
- Guarantees consistent old/new status transitions per order row.

### 4.2 Fast Response Contract for Status Changes

`update-status` supports `refresh_mode: minimal` to return:

- patched order state
- refreshed metrics only

This avoids expensive full summary payload rebuilds for every status click.

### 4.3 Frontend In-Flight Locking

`admin/frontend/assets/js/script.js` now uses per-order lock set (`ORDER_STATUS_LOCKS`) to:

- Block duplicate clicks while request is active.
- Disable status/logistics buttons during update.
- Prevent accidental race requests from UI.

## 5. Tabpane UX and Responsiveness Improvements

### 5.1 Responsive Tab Content

`05-responsive.css` improvements:

- Constrained tab content height on mobile for smoother scrolling.
- Better horizontal scrolling for dense tables.
- Mobile-safe padding refinements.
- Analytics chart container sizing by breakpoint.

### 5.2 Notifications UX

Notification dropdown now supports:

- actionable entries that jump to relevant tab
- clear notifications action
- resume notifications action

## 6. Analytics: Loss vs Progress Graph

### 6.1 New Analytics Card

`admin-panel.php` includes a new chart card:

- `#analyticsProfitLossChart`
- title: Profit vs Loss Trend (7 Days)

### 6.2 Data Model Extension

`orders_api_fetch_metrics()` now exposes:

- `refundLoss` (30-day accepted refund loss total)
- `netRevenue` (`totalRevenue - refundLoss`)
- weekly entries include `loss` and `net`

### 6.3 Rendering

`script.js` now renders a mixed chart:

- Revenue (bar)
- Loss (bar)
- Net Progress (line)

## 7. Metadata and Share Tags

Admin pages now consistently include canonical + OG tags while keeping `noindex, nofollow`:

- `admin-panel.php`
- `admin-login.php`
- `admin-forgot-password.php`
- `admin-forgot-email.php`
- `admin-verify-2fa.php`

URLs/images are generated with `admin_public_url(...)` for deployment-safe absolute links.

## 8. API Surface Summary

### Orders API Actions

- `summary` (GET)
- `refund-summary` (GET)
- `update-status` (POST)
- `delete-orders` (POST)
- `delete-customers` (POST)
- `update-refund-status` (POST)

### Coupons API Actions

- `list` (GET)
- `save-coupon` (POST)
- `delete-coupon` (POST)
- `send-coupon-email` (POST)

### Reviews API Actions

- `list` (GET)
- `set-visibility` (POST)
- `update-review` (POST)
- `delete-review` (POST)
- `add-review` (POST)

### Security API Actions

- `list-events` (GET/POST)
- `update-email` (POST)
- `update-password` (POST)
- `update-reset-key` (POST)

### Website API Actions

- `get` (GET)
- `save-brand` (POST)
- `save-contact` (POST)
- `save-social` (POST)
- `delete-social` (POST)
- `save-ticker` (POST)
- `save-slider` (POST)
- `delete-slider` (POST)
- `save-feature-videos` (POST)

### Viewers API Actions

- `get` (GET)
- `save` (POST)

### Media API

- Upload by target (POST)

## 9. Validation Workflow

Use explicit XAMPP PHP binary in this repo environment:

```powershell
C:\xampp\php\php.exe -l admin\backend\auth.php
C:\xampp\php\php.exe -l admin\backend\orders_api.php
C:\xampp\php\php.exe -l admin\backend\coupons_api.php
C:\xampp\php\php.exe -l admin\backend\reviews_api.php
C:\xampp\php\php.exe -l admin\backend\security_api.php
C:\xampp\php\php.exe -l admin\backend\viewers_api.php
C:\xampp\php\php.exe -l admin\backend\website_api.php
C:\xampp\php\php.exe -l admin\backend\products_sync_api.php
C:\xampp\php\php.exe -l admin\backend\media_api.php
C:\xampp\php\php.exe -l admin\frontend\admin-panel.php
```

## 10. Extension Rules

- Always add new admin API endpoints behind login + permission + CSRF + action-scoped rate limit.
- Log all sensitive admin writes (especially deletes, credential/security updates, or financial state changes).
- Prefer minimal payload returns for high-frequency UI actions.
- Keep frontend optimistic updates bounded and rollback-safe.
- Preserve response shape compatibility for existing tabpane renderers.
