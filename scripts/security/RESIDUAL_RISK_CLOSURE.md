# Residual Risk Closure

This phase closes residual security risk with three focused layers:

1. Authenticated admin E2E abuse checks
2. Scripted crawler-based reflected XSS probing
3. CI security gate enforcement

## Scripts

- `scripts/security/admin_e2e_abuse_tests.ps1`
- `scripts/security/xss_crawler_probe.ps1`
- `scripts/security/ci_security_gate.ps1`

## 1) Admin E2E Abuse Checks

Command:

```powershell
pwsh -File scripts/security/admin_e2e_abuse_tests.ps1 -BaseUrl "http://localhost/commerza"
```

Authentication options:

- Provide an active admin session cookie:
  - `COMMERZA_ADMIN_TEST_SESSION_ID`
- Or provide credentials (and optional 2FA code):
  - `COMMERZA_ADMIN_TEST_EMAIL`
  - `COMMERZA_ADMIN_TEST_PASSWORD`
  - `COMMERZA_ADMIN_TEST_2FA_CODE`

Coverage includes:

- Authenticated admin panel access
- Admin coupons API GET access with authenticated session
- POST mutation attempts without CSRF (must be rejected)
- POST mutation attempts with invalid CSRF (must be rejected)
- Invalid payload rejection after valid CSRF
- Security API POST without CSRF (must be rejected)

## 2) Crawler XSS Probe

Command:

```powershell
pwsh -File scripts/security/xss_crawler_probe.ps1 -BaseUrl "http://localhost/commerza" -MaxPages 100
```

Coverage includes:

- Same-origin crawl of storefront/admin entry pages
- Parameterized payload injection across common query keys
- Detection of raw reflected payloads in HTML responses

## 3) CI Security Gate

The static gate runs in GitHub Actions:

- Workflow: `.github/workflows/security-gate.yml`
- Static checks: `scripts/security/ci_security_gate.ps1`

Dynamic probes are executed in CI only when `SECURITY_BASE_URL` secret is configured.

Required optional secrets for full dynamic coverage:

- `SECURITY_BASE_URL`
- `COMMERZA_ADMIN_TEST_EMAIL`
- `COMMERZA_ADMIN_TEST_PASSWORD`
- `COMMERZA_ADMIN_TEST_2FA_CODE` (optional)
- `COMMERZA_ADMIN_TEST_SESSION_ID` (optional)
