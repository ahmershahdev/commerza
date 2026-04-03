# Security Policy

## Supported Versions

Security fixes are applied to the active development branch/release of Commerza.

## Security Controls in This Project

- Session-based authentication for user/admin flows.
- CSRF tokens for sensitive forms and AJAX endpoints.
- OAuth state validation and expiry checks.
- Input validation and prepared statements for DB queries.
- Output escaping with `htmlspecialchars` in server-rendered templates.
- Transaction-safe stock checks and decrement during checkout to prevent race-condition overselling.
- Rate limiting for high-risk endpoints:
  - user login
  - user signup
  - contact form
  - user forgot password
  - admin login
  - admin forgot password (send + reset)
  - admin forgot email change
- Order/customer data in admin panel served from backend APIs (not browser-only storage).

## Threat Coverage Notes

- XSS: mitigated by escaping untrusted output and validating input.
- CSRF: mitigated via per-session CSRF tokens verified on state-changing requests.
- SSRF: no user-controlled arbitrary server-side URL fetch endpoints are exposed in public flows.
- Race conditions: checkout now locks product rows before stock decrement and order commit.

## Operational Recommendations

- Keep PHP, Apache, and MySQL patched.
- Use HTTPS in production.
- Restrict DB user permissions to only required operations.
- Configure SMTP/sendmail securely for notification delivery.
- Rotate OAuth and Stripe keys if leaked.
- Protect admin routes behind strong credentials and IP/network controls where possible.

## Reporting a Vulnerability

Please report privately with reproducible details.

Contact:

- Email: syedahmershahofficial@gmail.com
- LinkedIn: https://www.linkedin.com/in/syedahmershah
- GitHub: https://github.com/ahmershahdev

Please include:

- Clear summary of the vulnerability
- Exact reproduction steps
- Affected routes/files
- Impact and severity estimate
- Logs, screenshots, or proof-of-concept (if available)

## Responsible Disclosure

Please do not publish vulnerabilities publicly until a fix has been confirmed and released.
