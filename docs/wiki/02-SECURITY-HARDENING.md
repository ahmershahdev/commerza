# Security Hardening

## Core Controls in Use

- CSRF token verification for sensitive POST actions
- CAPTCHA checks on login/signup/password-reset/contact/account high-risk paths
- Action-scoped rate limiting with block windows
- Security event logging for abuse and auth anomalies
- Session regeneration after successful login
- Centralized security headers and CSP from backend bootstrap

## Current Hardened User Flows

- `login.php`: CSRF + CAPTCHA + rate limit + session regeneration
- `signup.php`: CSRF + CAPTCHA + rate limit for start/verify/resend
- `forgot-password.php`: CSRF + CAPTCHA + rate limit + security logging
- `reset-password.php`: CSRF + CAPTCHA + rate limit + race-safe token update
- `account.php`: CSRF + CAPTCHA on protected actions + per-action throttle
- `contact.php`: CSRF + CAPTCHA + dedupe + rate limit

## Race Condition Notes

- Password reset token consumption uses guarded SQL update conditions.
- Review submit path uses upsert semantics to reduce duplicate conflicts.
- Account-sensitive actions use scoped throttles to reduce burst abuse.

## CSP Guidance

- Use centralized header policy from `backend/data.php`.
- Avoid divergent page-level meta CSP policies because they can conflict and create dead ends.
- Add new external origins only when required by actual runtime features.

## XSS Guidance

- Escape all user-facing dynamic output using `htmlspecialchars`.
- Never trust query or form values without validation.
- Keep rich HTML rendering controlled and minimal.

## Review Checklist for Security PRs

1. Does every mutating action validate CSRF?
2. Is brute-force/rate abuse mitigated?
3. Are errors generic for auth failures?
4. Are all dynamic outputs escaped?
5. Are new routes covered by robots restrictions when needed?
