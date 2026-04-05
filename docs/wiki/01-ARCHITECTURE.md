# Architecture

## High-Level Model

Commerza uses server-rendered PHP pages for storefront and admin screens, with API-style PHP endpoints for async interactions.

## Layers

- Presentation Layer:
  - Root-level public pages (`index.php`, `products.php`, `contact.php`, etc.)
  - Admin screens in `admin/frontend/`
- Application Layer:
  - `backend/*.php` for auth, cart, reviews, payments, notifications, reports
  - `admin/backend/*.php` for admin CRUD and system controls
- Data Layer:
  - MySQL schema in `backend/database/commerza.sql`

## Storefront Flow

1. Public pages bootstrap via `backend/data.php`.
2. Session, security headers, and DB connection are initialized.
3. UI scripts call backend endpoints for cart, reviews, wishlist, tracking, etc.
4. Responses are rendered into dynamic components client-side.

## Admin Flow

1. `admin/backend/auth.php` validates admin sessions, CSRF, and permissions.
2. Admin API modules expose domain actions (orders, products, reviews, security, media).
3. `admin/frontend/assets/js/script.js` orchestrates tabpane interactions and payload rendering.

## Shared Infrastructure

- CSP and security headers: `backend/data.php`
- Rate limiting and blocks: `backend/rate_limit.php`
- Security event logging: `backend/security_events.php`
- Mail transport + templates: `backend/mailer.php`, `backend/notifications.php`

## Design Guidance

- Keep validation server-side even when client checks exist.
- Reuse existing helpers before adding new utility functions.
- Preserve endpoint response compatibility with existing frontend JS.
