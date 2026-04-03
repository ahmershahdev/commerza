# Backend Folder Guide

This folder contains the server-side application logic for customer features, security, integrations, and automation jobs.

## Core Runtime Files

- `data.php`: App bootstrap. Loads environment values, starts session, configures security headers/CSP, creates DB connection, and remembers logged-in users via secure cookies.
- `rate_limit.php`: Shared IP/identifier-based rate limiting with escalation support.
- `security_helpers.php`: Password utilities and shared CAPTCHA configuration/verification helpers.
- `security_events.php`: Structured security event logging helpers.
- `expiry_cleanup.php`: Background cleanup logic for expired records.
- `nav_state.php`: Navigation badge/count helpers used by storefront pages.

## Commerce and User APIs

- `products_api.php`: Product data API for storefront listing/filtering.
- `cart_api.php`: Cart API endpoints for add/update/remove behavior.
- `wishlist_api.php`: Wishlist API endpoints.
- `reviews_api.php`: Public review API endpoints.
- `newsletter_api.php`: Newsletter subscription endpoint.
- `viewers_api.php`: Live viewers simulation/real-mode endpoint.
- `check_exists.php`: Signup helper endpoint for email/phone availability checks.

## Checkout and Payments

- `coupon_helpers.php`: Coupon validation, normalization, and redemption logic.
- `payment_helpers.php`: Stripe key resolution and Stripe API client helpers.
- `stripe_intent.php`: Creates Stripe PaymentIntents with CSRF/session validation.
- `cart_helpers.php`: Cart snapshot and cart identity helper functions.

## Authentication and Notifications

- `oauth.php`: OAuth provider callback and account linking/login logic.
- `mailer.php`: Mail transport wrapper.
- `notifications.php`: Email templates and event-triggered notification senders.

## Scheduled and Maintenance Jobs

- `send_engagement_reminders.php`: Sends follow-up reminders for inactive carts/wishlists.
- `monthly_profit_report.php`: Generates/sends monthly profit summaries.
- `weekly_analytics_report.php`: Generates/sends weekly analytics summaries.
- `backup_restore_test.ps1`: Validates backup and restore integrity in local/XAMPP setups.
- `BACKUP_RESTORE_TESTS.md`: How to run and interpret backup/restore checks.

## Data Definition

- `database/commerza.sql`: Full schema and seed data for tables/settings.

## Notes

- Most files assume they are included after `data.php` so `$con` and session state are available.
- Security-sensitive endpoints use CSRF checks, rate limiting, and structured security event logging.
