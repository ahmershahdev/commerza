# Backend Guide

## Purpose

This folder contains backend runtime logic, API endpoints, security helpers, and scheduled jobs.

## Core Runtime

- `data.php`: bootstrap, headers, DB connection, session initialization
- `rate_limit.php`: generic request throttling and escalation
- `security_helpers.php`: password policy, CAPTCHA config, common security helpers
- `security_events.php`: structured event logging for auth and abuse telemetry

## Customer APIs

- `products_api.php`
- `cart_api.php`
- `wishlist_api.php`
- `reviews_api.php`
- `newsletter_api.php`
- `viewers_api.php`

## Commerce and Payment

- `cart_helpers.php`
- `coupon_helpers.php`
- `payment_helpers.php`
- `stripe_intent.php`

## Email and Notification Layer

- `mailer.php`: SMTP transport and fallback mail sending
- `notifications.php`: branded notification templates and event senders

## Scheduled Jobs

- `send_engagement_reminders.php`
- `monthly_profit_report.php`
- `weekly_analytics_report.php`

## Database

- `database/commerza.sql`: canonical schema and seed baseline

## Validation Commands

- PHP lint:
  - `C:\xampp\php\php.exe -l backend/<file>.php`
- Table count:
  - `SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'commerza';`
- FK count:
  - `SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'commerza';`

## Notes

- Most files assume initialization through `data.php`.
- Keep endpoint changes backward-compatible with existing frontend JS payloads.
