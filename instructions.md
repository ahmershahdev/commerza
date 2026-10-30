# Commerza Setup and Integration Guide

This guide covers everything needed to run Commerza locally and connect it to real providers: database, environment, admin access, email, CAPTCHA, OAuth, Stripe, Cloudinary, scheduled jobs, and production deployment.

All configuration lives in `.env` at the project root (copy it from `.env.example`). Never commit `.env`.

---

## 1. Local setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) with PHP 8.1 or newer.
2. Clone into the document root:
   ```bash
   cd C:/xampp/htdocs
   git clone https://github.com/ahmershahdev/commerza.git
   ```
3. Start **Apache** and **MySQL** from the XAMPP control panel.
4. Create and import the database:
   ```bash
   C:/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE commerza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:/xampp/mysql/bin/mysql.exe -u root commerza < C:/xampp/htdocs/commerza/database/commerza.sql
   ```
5. Copy `.env.example` to `.env` and apply the local profile below.
6. Open <http://localhost/commerza/>.

### Local `.env` profile

```dotenv
COMMERZA_APP_URL=http://localhost/commerza
COMMERZA_DB_HOST=localhost
COMMERZA_DB_USER=root
COMMERZA_DB_PASS=
COMMERZA_DB_NAME=commerza

COMMERZA_MAIL_DRIVER=log
COMMERZA_CAPTCHA_BYPASS_LOCAL=1
COMMERZA_OAUTH_STRICT_SSL=0

COMMERZA_ADMIN_BOOTSTRAP_EMAIL=admin@example.com
COMMERZA_ADMIN_BOOTSTRAP_PASSWORD=Change-Me-2026!
COMMERZA_ADMIN_RESET_KEY=<at least 16 random characters>
```

| Key | Effect |
| --- | --- |
| `COMMERZA_MAIL_DRIVER=log` | Every email (signup code, admin 2FA, order receipts) is written as HTML to `storage/mail/` instead of being sent. |
| `COMMERZA_CAPTCHA_BYPASS_LOCAL=1` | CAPTCHA is skipped for `localhost`, `127.0.0.1`, `*.test`, and `*.localhost` hosts only. |

### Other web servers

Commerza needs Apache with `mod_rewrite` and `AllowOverride All`. On Nginx, route clean URLs to `pages/<route>.php`, deny `database/`, `docs/`, `scripts/`, `storage/`, `templates/`, `.env`, and the internal `backend/` folders listed in `.htaccess`, and add the same security headers.

---

## 2. Admin access

- The first admin is created automatically the first time any admin page loads, using `COMMERZA_ADMIN_BOOTSTRAP_EMAIL` and `COMMERZA_ADMIN_BOOTSTRAP_PASSWORD`. The password must meet the policy: 10 to 64 characters with upper case, lower case, a digit, a symbol, and no spaces.
- Every admin login requires a 6-digit email code that expires after 15 minutes. With the `log` mail driver, read it from the newest `storage/mail/*admin-login-verification-code.html`.
- Reset or create an admin from the CLI:
  ```bash
  php scripts/dev/reset_admin_password.php admin@example.com 'New-Strong-Pass-2026!'
  ```
- `COMMERZA_ADMIN_RESET_KEY` protects the admin recovery flows. If it is missing or shorter than 16 characters, those flows refuse requests by design.

---

## 3. Email (SMTP)

Mail is sent through a primary route with a secondary fallback. If both point at the same account, the duplicate is skipped.

```dotenv
COMMERZA_MAIL_DRIVER=smtp

# Primary (e.g. Sender.net, Mailgun, SES SMTP)
COMMERZA_SMTP_PRIMARY_HOST=smtp.example.com
COMMERZA_SMTP_PRIMARY_PORT=587
COMMERZA_SMTP_PRIMARY_ENCRYPTION=tls
COMMERZA_SMTP_PRIMARY_USERNAME=<user>
COMMERZA_SMTP_PRIMARY_PASSWORD=<password>
COMMERZA_SMTP_PRIMARY_FROM_EMAIL=no-reply@your-domain.com
COMMERZA_SMTP_PRIMARY_FROM_NAME=Commerza

# Secondary (Gmail with an app password)
COMMERZA_SMTP_SECONDARY_HOST=smtp.gmail.com
COMMERZA_SMTP_SECONDARY_PORT=587
COMMERZA_SMTP_SECONDARY_ENCRYPTION=tls
COMMERZA_SMTP_SECONDARY_USERNAME=<gmail address>
COMMERZA_SMTP_SECONDARY_PASSWORD=<gmail app password>
COMMERZA_SMTP_SECONDARY_FROM_EMAIL=<gmail address>

COMMERZA_SUPPORT_EMAIL=support@ahmershah.dev
```

To verify, sign up a customer and log in as an admin. You should receive the signup code and the admin 2FA code.

Emails sent by the platform include signup verification and welcome, login alerts, order confirmation, order shipped, order delivered with a review request, status changes, refund updates, COD OTP, account deletion code, engagement reminders, and admin reports.

---

## 4. CAPTCHA (reCAPTCHA v3 + v2)

Verification is layered: reCAPTCHA v3 runs first, v2 is the checkbox fallback, and a built-in question challenge covers network or key failures. A honeypot field is always checked.

```dotenv
COMMERZA_CAPTCHA_ENABLED=1
COMMERZA_CAPTCHA_REQUIRED=1
COMMERZA_CAPTCHA_PROVIDER=recaptcha
COMMERZA_RECAPTCHA_SITE_KEY=<v2 site key>
COMMERZA_RECAPTCHA_SECRET_KEY=<v2 secret>
COMMERZA_RECAPTCHA_V3_SITE_KEY=<v3 site key>
COMMERZA_RECAPTCHA_V3_SECRET_KEY=<v3 secret>
COMMERZA_RECAPTCHA_V3_MIN_SCORE=0.65
```

Register both `localhost` and your production domain in the reCAPTCHA admin console. To test real CAPTCHA locally, set `COMMERZA_CAPTCHA_BYPASS_LOCAL=0` and `COMMERZA_CAPTCHA_REMOTE_LOCAL=1`.

---

## 5. Google OAuth

1. In Google Cloud Console, create an **OAuth client ID** of type *Web application*.
2. Add these authorized redirect URIs:
   - `http://localhost/commerza/oauth.php?provider=google`
   - `https://<your-domain>/oauth.php?provider=google`
3. Configure:
   ```dotenv
   COMMERZA_GOOGLE_CLIENT_ID=<client id>
   COMMERZA_GOOGLE_CLIENT_SECRET=<client secret>
   COMMERZA_GOOGLE_REDIRECT_URI=http://localhost/commerza/oauth.php?provider=google
   ```

## 6. Facebook OAuth

1. In Meta for Developers, create an app with **Facebook Login**.
2. Add these valid OAuth redirect URIs:
   - `http://localhost/commerza/oauth.php?provider=facebook`
   - `https://<your-domain>/oauth.php?provider=facebook`
3. Configure:
   ```dotenv
   COMMERZA_FACEBOOK_CLIENT_ID=<app id>
   COMMERZA_FACEBOOK_CLIENT_SECRET=<app secret>
   COMMERZA_FACEBOOK_REDIRECT_URI=http://localhost/commerza/oauth.php?provider=facebook
   ```

OAuth credentials can also be stored in `site_settings` (`google_oauth_client_id`, `facebook_oauth_client_id`, and so on). Environment values take precedence. Blacklisted contacts are blocked when OAuth sign-in completes.

---

## 7. Payments

Checkout supports **Cash on Delivery** and **Stripe** card payments.

```dotenv
COMMERZA_CHECKOUT_PAYMENT_MODE=cod_stripe
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...

# High-value COD protection
COMMERZA_COD_OTP_THRESHOLD=15000        # orders at or above this total need an email OTP
COMMERZA_COD_HIGH_VALUE_HARD_LIMIT=0    # > 0 blocks COD above this total
```

- The PaymentIntent is created and verified server-side (`backend/payment/`). The browser only confirms the card with Stripe.js.
- Use Stripe test keys and [test cards](https://docs.stripe.com/testing) such as `4242 4242 4242 4242` while developing.
- Every order passes CSRF, idempotency, CAPTCHA, stock locking, coupon validation, and blacklist checks.
- Shipping fee and free-shipping threshold are managed in **Admin → Orders → Shipping Rules**.

---

## 8. Cloudinary (optional)

```dotenv
COMMERZA_CLOUDINARY_ENABLED=1
COMMERZA_CLOUDINARY_CLOUD_NAME=<cloud>
COMMERZA_CLOUDINARY_API_KEY=<key>
COMMERZA_CLOUDINARY_API_SECRET=<secret>
COMMERZA_CLOUDINARY_FOLDER=commerza
COMMERZA_CLOUDINARY_UPLOAD_PRESET_IMAGE=commerza_image_optimized
COMMERZA_CLOUDINARY_UPLOAD_PRESET_VIDEO=commerza_video_optimized
```

```bash
php scripts/maintenance/cloudinary_sync.php --dry-run      # preview presets + uploads
php scripts/maintenance/cloudinary_sync.php                # provision presets and upload local assets
php scripts/maintenance/cloudinary_cleanup.php --apply     # delete unreferenced remote assets
```

---

## 9. Caching (optional)

```dotenv
COMMERZA_CACHE_ENABLED=1
COMMERZA_CACHE_NAMESPACE=commerza
COMMERZA_REDIS_HOST=127.0.0.1
COMMERZA_REDIS_PORT=6379
COMMERZA_SITE_SETTINGS_CACHE_TTL=120
```

APCu is used automatically when the extension is loaded. A sample OPcache preload config is in `backend/cache/opcache.ini.sample`.

---

## 10. Scheduled jobs

| Job | Schedule | Command |
| --- | --- | --- |
| Engagement reminders | every 3 hours | `php backend/jobs/send_engagement_reminders.php 180` |
| Weekly analytics report | Monday 08:00 | `php backend/jobs/weekly_analytics_report.php` |
| Monthly profit report | 1st of month 08:00 | `php backend/jobs/monthly_profit_report.php` |

Linux crontab example:

```cron
0 */3 * * * php /var/www/commerza/backend/jobs/send_engagement_reminders.php 180
0 8 * * 1   php /var/www/commerza/backend/jobs/weekly_analytics_report.php
0 8 1 * *   php /var/www/commerza/backend/jobs/monthly_profit_report.php
```

On Windows, create equivalent Task Scheduler entries that run `C:\xampp\php\php.exe` with the script path. Cart and wishlist expiry cleanup runs automatically during normal requests.

---

## 11. Production deployment (`commerza.ahmershah.dev`)

```dotenv
COMMERZA_APP_URL=https://commerza.ahmershah.dev
COMMERZA_GOOGLE_REDIRECT_URI=https://commerza.ahmershah.dev/oauth.php?provider=google
COMMERZA_FACEBOOK_REDIRECT_URI=https://commerza.ahmershah.dev/oauth.php?provider=facebook
COMMERZA_MAIL_DRIVER=smtp
COMMERZA_CAPTCHA_BYPASS_LOCAL=0
COMMERZA_OAUTH_STRICT_SSL=1
```

Database: use **either** TCP (`COMMERZA_DB_HOST`, `COMMERZA_DB_PORT`) **or** a socket (`COMMERZA_DB_SOCKET`), together with `COMMERZA_DB_USER`, `COMMERZA_DB_PASS`, and `COMMERZA_DB_NAME`.

### Go-live checklist

- [ ] HTTPS active. `.htaccess` redirects non-local HTTP and HSTS is sent.
- [ ] Strong admin password and `COMMERZA_ADMIN_RESET_KEY` set.
- [ ] Google/Facebook redirect URIs and reCAPTCHA domain registered for production.
- [ ] SMTP primary and secondary delivery tested from real flows.
- [ ] COD and Stripe orders placed end-to-end; stock decremented and emails received.
- [ ] Cron jobs scheduled.
- [ ] `robots.txt`, `sitemap.xml`, and `llms.txt` point at the production domain.
- [ ] `pwsh scripts/security/ci_security_gate.ps1` and `scripts/security/security_smoke_tests.ps1 -BaseUrl https://commerza.ahmershah.dev` pass.

---

## 12. Troubleshooting

| Symptom | Fix |
| --- | --- |
| Every page shows "Service unavailable." | Database unreachable or the database is missing. Check the `COMMERZA_DB_*` values. The real error is in the PHP/Apache error log. |
| Clean URLs return 404 | Enable `mod_rewrite` and set `AllowOverride All` for the project directory. |
| No verification emails | Set `COMMERZA_MAIL_DRIVER=log` locally and look in `storage/mail/`. In production, check SMTP credentials. Gmail needs an app password. |
| Checkout says "Complete one verification method" | CAPTCHA keys are missing for the current host. Configure reCAPTCHA, or use `COMMERZA_CAPTCHA_BYPASS_LOCAL=1` on localhost. |
| OAuth fails with `redirect_uri_mismatch` | The redirect URI in `.env` must match the provider console exactly, including `?provider=...`. |
| Admin cannot log in | Run `php scripts/dev/reset_admin_password.php <email> <password>` and read the 2FA code from `storage/mail/`. |

Support: support@ahmershah.dev · +92 370 4831994
