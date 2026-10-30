# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-10-30

### Changed

- **Project structure:** storefront pages moved from the repository root into `pages/`. Apache rewrites clean routes there, and legacy `*.php` URLs still redirect to the clean routes.
- **Shared templates:** the navbar, mobile offcanvas, and footer copied into 17 pages are replaced by `templates/partials/site-header.php` and `site-footer.php`, driven by `commerza_site_view_context()`.
- The SQL dump moved to `database/commerza.sql` and was sanitized: no admin password hash, no personal data, no runtime rate-limit or viewer rows, no duplicate ticker rows.
- Database connection settings are resolved once in `commerza_db_config()` and shared by mysqli and PDO.
- Store contact details updated to support@ahmershah.dev and +92 370 4831994.
- License changed to **MIT** (`LICENSE`).
- Documentation rewritten: README with screenshots and Mermaid architecture/ERD diagrams, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `instructions.md`, `llms.txt`, and the new `llms-full.txt`.

### Added

- `COMMERZA_MAIL_DRIVER=log` writes outgoing email to `storage/mail/`, so signup, 2FA, and order flows work locally without SMTP.
- `scripts/dev/reset_admin_password.php` sets or creates an admin from the CLI.
- `COMMERZA_ROOT` constant for project-relative paths.
- CI workflow: PHP 8.1/8.2/8.3 lint and a database import test. Also Dependabot for Actions, CODEOWNERS, issue and PR templates, `CONTRIBUTING.md`, `.gitattributes`.

### Fixed

- Checkout could not be submitted on localhost: the client required a CAPTCHA token while the widget was suppressed by the local bypass. Added `commerza_captcha_is_active()`.
- Customers never received the dedicated "order shipped", "order delivered", or post-delivery review request emails. These now fire on status changes.
- Admin edits to **social links** and the **home slider** never reached the storefront because the public settings payload returned empty arrays.
- A database connection failure produced an uncaught fatal error that exposed file paths. It now returns a clean 503 and logs the cause.
- The storefront wishlist page could fail to create a wishlist under concurrent requests. It now uses the race-safe helper shared with the API.
- Admin cache header typo (`mert-revalidate` → `must-revalidate`).
- Header search form posted to a non-existent `/search` route.
- `target="_blank"` social links lacked `rel="noopener noreferrer"`.
- "1 people viewing now" now reads "1 person viewing now".
- Forgot-password showed CAPTCHA instructions when no CAPTCHA was rendered.
- `ErrorDocument` pointed at `/404.php`, which broke in subdirectory installs. 404s are routed by rewrite.

### Removed

- 13 one-line `dark-mode/*.css` stubs that only re-imported module files. The theme now imports modules directly, which removes a request waterfall.
- 4 duplicate page scripts (`faq.js`, `returns.js`, `shipping.js`, `warranty.js`), merged into `static-page.js`.
- Duplicated PHP helpers (`get_or_create_wishlist_id`, `order_tracking_safe_image_src`), the unused `backend/data.php` shim, an unreferenced stylesheet, and empty placeholder directories.

## [1.0.0] - 2026-10-25

- First public release: storefront, admin panel, COD + Stripe checkout, OAuth, layered security controls.

[2.0.0]: https://github.com/ahmershahdev/commerza/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/ahmershahdev/commerza/releases/tag/v1.0.0
