# Security Policy

## Supported Scope

This project applies security controls across:

- Customer auth, account, and checkout flows
- Admin authentication and admin APIs
- Database-backed APIs in `backend/` and `admin/backend/`

## Reporting a Vulnerability

If you identify a vulnerability:

1. Do not disclose publicly.
2. Share reproduction steps, affected path, and impact level privately.
3. Include request examples and any logs needed to validate the issue.

## Security Controls in Place

- CSRF tokens on sensitive form actions
- CAPTCHA validation on high-risk auth actions
- Rate limiting via `backend/rate_limit.php`
- Security event logging via `backend/security_events.php`
- Session cookie hardening and CSP/security headers via `backend/data.php`
- Password hashing and policy enforcement via `backend/security_helpers.php`

## Operational Hardening Checklist

- Enforce HTTPS in production
- Set secure, unique admin credentials
- Rotate OAuth, SMTP, and payment provider secrets
- Keep XAMPP/PHP/MySQL patched and updated
- Restrict write permissions to required upload paths only
- Review security event logs regularly

## Data Protection Notes

- Do not commit secrets to repository
- Store credentials in environment variables or protected settings table
- Avoid exposing stack traces or raw SQL errors in production

## Incident Response Baseline

1. Revoke compromised credentials.
2. Block abusive IPs and rotate tokens.
3. Review `security_events` and auth logs for timeline.
4. Patch root cause and verify affected endpoints.
5. Notify impacted operators or users as required.
