# Commerza

Commerza is a PHP + MySQL ecommerce platform with a public storefront and an admin control panel.

This README is the operational map for developers, reviewers, and maintainers.

## 1. Architecture Overview

Commerza is organized into two major surfaces:

1. Storefront: server-rendered public pages and customer-facing interactions.
2. Admin panel: operations UI for products, orders, coupons, reviews, analytics, website content, and security events.

Core runtime helpers and shared protections are centralized in backend bootstrap files (especially backend/data.php).

## 2. High-Level Folder Structure

Top-level layout:

1. admin/
2. backend/
3. frontend/
4. public PHP pages at repository root (index.php, products.php, about.php, etc.)
5. configuration and crawler files (.htaccess, robots.txt, sitemap.xml, llms.txt)

Detailed map:

1. admin/backend/

- Admin auth, permissions, and admin-only APIs.
- Examples: orders_api.php, website_api.php, coupons_api.php, reviews_api.php, products_sync_api.php.

2. admin/frontend/

- Admin HTML pages and bundled assets.
- Key page: admin-panel.php.
- Assets under admin/frontend/assets/css and admin/frontend/assets/js.

3. backend/

- Shared app bootstrap and helpers.
- API endpoints for storefront actions (cart, wishlist, reviews, newsletter, order tracking, etc.).
- Security and utility helpers (rate limits, CSRF/session bootstrap, notifications, mailer).
- Scheduled jobs and reporting scripts.
- SQL schema in backend/database/commerza.sql.

4. frontend/

- Storefront static assets: CSS modules, JS modules, images, videos.

5. root public pages

- Customer-facing routes mapped by clean rewrite rules.
- Examples: home, products, about, contact, faq, shipping, returns, warranty.

## 3. Routing and .htaccess Behavior

Routing is split between Apache rewrite rules and PHP-side canonical normalization.

1. Apache (.htaccess)

- Rewrites clean routes to PHP entry pages.
- Blocks direct browser navigation to backend/admin backend API scripts.
- Restricts sensitive files and directories.
- Uses ErrorDocument 404 /error with a clean /error rewrite to 404.php.

2. PHP canonical route map (backend/data.php)

- Redirects legacy .php requests to clean canonical routes for GET/HEAD.
- Keeps special query-based routes intact where needed (example: product slug pages).

3. Current clean-route examples

- /home, /products, /about, /contact, /faq, /shipping, /returns, /warranty
- /login, /signup, /forgot-password, /reset-password
- /admin-login, /admin-panel, /admin-forgot-password, /admin-verify-2fa
- /error (404 presentation page)

## 4. Security Model (Practical Summary)

Major controls used across the codebase:

1. Session + CSRF protections for form and API actions.
2. Rate limiting on sensitive workflows (auth, reset, verification, etc.).
3. CAPTCHA checks on selected high-risk forms.
4. Security event logging in admin-facing audit streams.
5. Permission checks for admin APIs.
6. Input validation/sanitization and prepared SQL statements.
7. Content Security Policy and defensive headers emitted from shared bootstrap.

Operational guidance:

1. Never bypass admin permission gates in new APIs.
2. Keep all new mutating endpoints CSRF-protected.
3. Log meaningful security events for auth, destructive actions, and suspicious behavior.

## 5. SEO and Discoverability

SEO signals are distributed across page templates and shared runtime helpers.

1. Canonical URLs

- Prefer clean routes (without .php) in canonical and OG URL tags.

2. Sitemap

- Managed in sitemap.xml with clean canonical URLs.

3. Robots

- robots.txt allows public content and blocks sensitive/account/admin paths.

4. LLM discoverability

- llms.txt documents public vs restricted routes for AI agents.

5. Structured data

- Pages include JSON-LD snippets (WebPage/FAQPage/etc.) where relevant.

## 6. Key Runtime Files You Should Know

1. backend/data.php

- Bootstrap, DB connection, base URL resolution, canonical route redirects, output normalization, public settings payload injection.

2. .htaccess

- Rewrite rules, sensitive route/file access restrictions, clean-route mapping, 404 route behavior.

3. admin/frontend/admin-panel.php

- Primary admin UI shell and tab panes.

4. admin/frontend/assets/js/script.js

- Admin panel behavior, API integration, rendering logic.

5. backend/database/commerza.sql

- Baseline schema for local provisioning and parity checks.

## 7. Local Setup (XAMPP)

1. Place project in C:/xampp/htdocs/commerza.
2. Import backend/database/commerza.sql into MySQL database commerza.
3. Configure environment values in .env.
4. Start Apache + MySQL from XAMPP control panel.
5. Open http://localhost/commerza/.

## 8. Useful Commands

1. PHP lint example:

- C:/xampp/php/php.exe -l backend/data.php

2. Scheduled job examples:

- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/send_engagement_reminders.php 180
- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/monthly_profit_report.php
- C:/xampp/php/php.exe C:/xampp/htdocs/commerza/backend/weekly_analytics_report.php

## 9. Deployment/Release Checklist

1. Verify canonical URLs and clean routes work on target host.
2. Confirm robots.txt and sitemap.xml point to production domain.
3. Verify SMTP, CAPTCHA, and environment secrets are configured.
4. Run PHP lint on touched files.
5. Manually test key auth/order/admin flows before release.

## 10. Documentation and Policies

1. Security policy: SECURITY.md
2. Agent/LLM guidance: llms.txt
3. Crawler policy: robots.txt
4. URL discovery: sitemap.xml
