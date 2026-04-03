# Admin Backend Folder Guide

This folder contains all admin-only server-side endpoints and auth/session logic.

## Files

- `auth.php`: Admin authentication core (session, CSRF, login/logout, reset-key handling, 2FA issue/verify, schema bootstrap for `admin_users`).
- `products_sync_api.php`: Product CRUD/sync endpoint used by admin panel catalog management.
- `orders_api.php`: Order listing/state updates/refund-related admin actions.
- `coupons_api.php`: Coupon creation, validation, listing, and management endpoint.
- `reviews_api.php`: Admin moderation endpoint for product reviews.
- `website_api.php`: Branding/contact/social/ticker/slider settings management.
- `media_api.php`: Upload and media management for admin-managed assets.
- `viewers_api.php`: Admin controls for live-viewers behavior mode and ranges.
- `security_api.php`: Security control endpoints (admin email/password/reset-key operations, security events).

## Security Characteristics

- Requires active admin session for protected endpoints.
- Uses admin CSRF token checks on state-changing requests.
- Records security-relevant actions through shared security logging utilities.
