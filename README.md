# Commerza

Commerza is a full-stack ecommerce CMS/store built with PHP, MySQL, jQuery, and Bootstrap.
It includes a customer storefront, account/auth flows, payment integrations, email automation, and an operations-focused admin panel.

## Stack

- PHP (session-based auth, CSRF protection, server-rendered pages)
- MySQL (products, users, orders, cart, wishlist, settings)
- JavaScript + jQuery (frontend actions and admin panel interactions)
- Bootstrap 5 (UI components)

## Core Features

- Storefront: homepage, categories, products, compare, wishlist, cart, checkout, order tracking.
- Account: profile update, password change, profile picture upload, order history.
- Auth: signup with email verification code, login, remember-me, forgot/reset password, OAuth (Google/Facebook).
- Refund lifecycle:
  - user can request refund from account within 7 days for delivered orders
  - admin can mark refund as pending/accepted/rejected
  - status emails sent to user + admin alerts
- Admin panel:
  - product and section management
  - product video support
  - order/customer management with bulk delete actions
  - dynamic analytics (weekly performance, top products, AOV, returning customer rate)
  - website controls (branding, social links, ticker, slider)
  - secure media uploads for logo/favicon/social/slider/product image + video
- Payments: COD and Stripe sandbox intent.
- Security hardening: prepared statements, CSRF checks, rate limits, session controls, OAuth state validation.
- Email automation:
  - signup verification + signup success
  - user/admin login alerts
  - order placement and order status updates
  - refund request and refund decision updates
  - cart/wishlist reminder queue + scheduled sender
  - monthly profit summary and weekly analytics report

## Local Setup

1. Place the project in your web root (example: `C:\xampp\htdocs\commerza`).
2. Import database schema from `backend/database/commerza.sql`.
3. Update DB credentials in `backend/data.php`.
4. Start Apache and MySQL.
5. Open `http://localhost/commerza/`.

## Admin Panel

- URL: `http://localhost/commerza/admin/frontend/admin-login.php`
- Admin login now uses password + email verification code (2FA).
- Core APIs:
  - `admin/backend/products_sync_api.php`
  - `admin/backend/orders_api.php`
  - `admin/backend/website_api.php`
  - `admin/backend/media_api.php`
  - `admin/backend/security_api.php`
  - `admin/backend/viewers_api.php`
- The panel is DB-backed (orders, customers, refunds, analytics, website settings).

## Scheduled Jobs

Use Windows Task Scheduler (or cron on Linux) with your PHP binary.

- Engagement reminders (cart/wishlist add-and-forget):
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\send_engagement_reminders.php 180`
  - `180` means remind after 180 minutes of inactivity.
- Monthly admin profit email:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\monthly_profit_report.php`
  - Optional month override: `YYYY-MM`.
- Weekly analytics email:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\weekly_analytics_report.php`
  - Optional week end override: `YYYY-MM-DD`.

## Security Notes

- Keep `backend/data.php` credentials private and environment-specific.
- Replace OAuth/payment keys in `site_settings` for production.
- Use HTTPS in production so secure cookies (session + remember-me) are always enforced.
- Rotate admin reset key and default admin credentials after first deployment.

## Backup/Restore Validation

- Use `backend/backup_restore_test.ps1` to validate database and media backup/restore integrity.
- Quick run example:
  - `powershell -ExecutionPolicy Bypass -File backend/backup_restore_test.ps1 -MySqlBinPath "C:\xampp\mysql\bin"`
- Full instructions are in `backend/BACKUP_RESTORE_TESTS.md`.

## Integration Keys (Stored in `site_settings`)

- `google_oauth_client_id`
- `google_oauth_client_secret`
- `google_oauth_redirect_uri`
- `facebook_oauth_client_id`
- `facebook_oauth_client_secret`
- `facebook_oauth_redirect_uri`
- `stripe_publishable_key`
- `stripe_secret_key`
- `captcha_enabled`
- `captcha_provider`
- `turnstile_site_key`
- `turnstile_secret_key`
- `recaptcha_site_key`
- `recaptcha_secret_key`

Environment fallback is also supported via:

- `COMMERZA_CAPTCHA_ENABLED`
- `COMMERZA_CAPTCHA_PROVIDER`
- `COMMERZA_TURNSTILE_SITE_KEY`
- `COMMERZA_TURNSTILE_SECRET_KEY`
- `COMMERZA_RECAPTCHA_SITE_KEY`
- `COMMERZA_RECAPTCHA_SECRET_KEY`
- `COMMERZA_CAPTCHA_BYPASS_LOCAL` (set to `1` only for local development)

Detailed provider onboarding instructions are in `instructions.md`.

## License

This project is proprietary. See `license.txt`.

## Contact

- LinkedIn: https://www.linkedin.com/in/syedahmershah
- GitHub: https://github.com/ahmershahdev
- Email: syedahmershahofficial@gmail.com
