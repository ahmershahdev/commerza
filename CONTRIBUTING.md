# Contributing to Commerza

Thanks for helping improve Commerza. This guide keeps changes consistent and safe.

## Getting set up

Follow the local setup in [instructions.md](instructions.md). Use `COMMERZA_MAIL_DRIVER=log` so verification emails land in `storage/mail/`.

## Workflow

1. Fork the repository and create a branch from `main`: `feat/<topic>`, `fix/<topic>`, `docs/<topic>`, or `refactor/<topic>`.
2. Keep each pull request focused on one change.
3. Use [Conventional Commits](https://www.conventionalcommits.org/): `feat: add coupon expiry badge`, `fix(cart): keep totals in sync after removal`.
4. Open a pull request against `main`. CI (PHP lint on 8.1–8.3, schema import, security gate) must pass, and a code-owner review is required.

`main` is protected: no direct pushes, no force pushes, linear history, squash merges.

## Code guidelines

- Follow the layout and conventions in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
- Put logic shared by a page and an API in `backend/helpers/`. Do not copy it into both.
- Use prepared statements for any request-derived value, cast IDs to `int`, and validate enum-like inputs against an allowlist.
- Every state-changing endpoint needs CSRF validation and rate limiting. Checkout and refund paths also need idempotency and row locks.
- Escape all output with `htmlspecialchars` / `commerza_e()`.
- Render storefront chrome with `commerza_partial('site-header', ...)` and `commerza_partial('site-footer', ...)`.
- Match the surrounding code style: 4-space indentation in PHP backend files, existing naming prefixes (`commerza_`, `admin_`, `<api>_`).

## Before opening a pull request

```bash
find . -name '*.php' -not -path './.git/*' -exec php -l {} \; | grep -v 'No syntax errors'
pwsh scripts/security/ci_security_gate.ps1
```

Then manually test the flow you changed in the browser, both storefront and admin if relevant.

## Reporting bugs and security issues

- Bugs and feature ideas: open an issue using the templates.
- Security vulnerabilities: **do not open a public issue.** Follow [SECURITY.md](SECURITY.md).
