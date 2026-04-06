# Commerza

## Overview

Commerza is a PHP and MySQL ecommerce application with a customer storefront and a separate admin panel.
The project includes account and auth flows, cart and wishlist APIs, order lifecycle management, notification emails, and operational reporting jobs.

## Wiki

- Project wiki entrypoint: `WIKI.md`
- Detailed chapters: `docs/wiki/`

## Tech Stack

- PHP (server-rendered pages and API endpoints)
- MySQL (commerce, auth, settings, and analytics tables)
- Bootstrap and jQuery (frontend and admin UI behavior)
- XAMPP runtime (Apache, MySQL, PHP)

## Directory Map

- `backend/`: Core runtime logic, APIs, helpers, jobs, schema
- `frontend/`: Storefront assets (CSS, JS, images, videos)
- `admin/backend/`: Admin auth and API controllers
- `admin/frontend/`: Admin pages and assets

## Core Features

- Storefront: product browsing, category pages, compare, wishlist, cart, checkout, order tracking
- Account: profile updates, password change, profile image upload, order history, refund requests
- Auth: signup verification, password reset, OAuth integration, admin 2FA verification
- Notifications: transactional emails, engagement reminders, monthly and weekly reports
- Security: CSRF protection, CAPTCHA verification, rate limiting, security event logging

## Local Setup

1. Place the project in XAMPP web root, for example `C:\xampp\htdocs\commerza`.
2. Import schema from `backend/database/commerza.sql` into the `commerza` database.
3. Configure environment values in `.env`.
4. Start Apache and MySQL from XAMPP.
5. Open `http://localhost/commerza/`.

## Environment Configuration

Minimum keys for production-ready behavior:

- DB: `COMMERZA_DB_HOST`, `COMMERZA_DB_USER`, `COMMERZA_DB_PASSWORD`, `COMMERZA_DB_NAME`
- App URL: `COMMERZA_APP_URL`
- SMTP: `COMMERZA_SMTP_HOST`, `COMMERZA_SMTP_PORT`, `COMMERZA_SMTP_ENCRYPTION`, `COMMERZA_SMTP_USERNAME`, `COMMERZA_SMTP_PASSWORD`, `COMMERZA_SMTP_FROM_EMAIL`, `COMMERZA_SMTP_FROM_NAME`
- CAPTCHA: `COMMERZA_CAPTCHA_ENABLED`, `COMMERZA_CAPTCHA_PROVIDER`, `COMMERZA_RECAPTCHA_SITE_KEY`, `COMMERZA_RECAPTCHA_SECRET_KEY`

## Database Health Checks

Run these SQL checks against live DB:

- Table count parity with schema baseline:
  - `SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'commerza';`
- Missing table parity checks should show none after import.
- Foreign key inventory:
  - `SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'commerza';`
- Orphan user reference spot-check:
  - `SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.user_id IS NOT NULL AND u.id IS NULL;`

## Scheduled Jobs

- Engagement reminders:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\send_engagement_reminders.php 180`
- Monthly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\monthly_profit_report.php`
- Weekly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\weekly_analytics_report.php`

## Release Readiness Checklist

- SMTP delivery verified from real flows (signup and admin 2FA)
- CAPTCHA verification active on sensitive actions
- CSP and security headers applied without runtime breakage
- Database schema parity and FK presence validated
- PHP lint and runtime checks passing

## Notes

- Use `C:\xampp\php\php.exe` for CLI linting and job execution in this environment.
- Keep credentials and secrets out of source control.
