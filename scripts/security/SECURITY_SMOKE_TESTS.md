# Security Smoke Tests

This repository includes an automated smoke test script for high-risk security controls across checkout, coupons, reviews, and live viewers.

Script: `scripts/security/security_smoke_tests.ps1`

## What It Tests

1. Checkout endpoint rejects POST without CSRF (`cart.php`)
2. Cart API rejects add-to-cart without CSRF (`backend/cart_api.php`)
3. Coupon apply rejects invalid code with proper validation status (`backend/cart_api.php`)
4. Viewers count rejects invalid product id (`backend/viewers_api.php`)
5. Viewers heartbeat rejects missing CSRF (`backend/viewers_api.php`)
6. Reviews submit rejects missing CSRF (`backend/reviews_api.php`)
7. Admin coupons API blocks unauthenticated requests (`admin/backend/coupons_api.php`)
8. Admin reviews API blocks unauthenticated requests (`admin/backend/reviews_api.php`)

## Usage

From project root:

```powershell
pwsh -File scripts/security/security_smoke_tests.ps1
```

Optional base URL override:

```powershell
pwsh -File scripts/security/security_smoke_tests.ps1 -BaseUrl "http://localhost/commerza"
```

## Exit Codes

- `0`: all tests passed
- `1`: one or more tests failed

## Notes

- The script uses an isolated web session and does not require admin login.
- Tests are intentionally non-destructive and focus on auth/CSRF/input-validation guard behavior.
- If local routing/base path differs, set `-BaseUrl` accordingly.
