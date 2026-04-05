# Operations Runbook

## Local Runtime

- XAMPP root expected: `C:\xampp\htdocs\commerza`
- PHP lint binary in this environment: `C:\xampp\php\php.exe`

## Validation Commands

- Lint a file:
  - `C:\xampp\php\php.exe -l path\to\file.php`
- Validate changed PHP set:
  - Run lint on each touched file after patching.

## Scheduled Jobs

- Engagement reminders:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\send_engagement_reminders.php 180`
- Monthly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\monthly_profit_report.php`
- Weekly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\weekly_analytics_report.php`

## Incident Triage

- Email send failures:
  - Verify SMTP credentials and provider limits.
  - Check fallback provider configuration.
- Login/signup abuse spikes:
  - Review `security_events` and rate-limit counters.
- Broken media links in emails:
  - Confirm absolute URL generation via public base URL helpers.

## Release Checklist

1. Lint all touched backend/admin PHP files.
2. Verify key customer flows: login, signup, forgot/reset, account, checkout.
3. Verify high-risk forms with CAPTCHA/CSRF enabled.
4. Verify `robots.txt`, `sitemap.xml`, and `llms.txt` consistency.
5. Smoke-test admin critical tabs: orders, security events, website settings.
