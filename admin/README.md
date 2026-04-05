# Admin Module Guide

## Purpose

The admin module provides operational controls for products, orders, settings, media, and security workflows.

## Structure

- `admin/backend/`: authentication, authorization, and admin-facing APIs
- `admin/frontend/`: admin pages, scripts, and visual assets

## Security Model

- Admin login with password plus verification code flow
- CSRF validation on sensitive admin forms and API mutations
- Rate limiting and security event logging integrated with shared backend helpers

## Operational Areas

- Product and inventory management
- Order status management and customer operations
- Website content and branding controls
- Security settings and admin profile controls

## Maintenance Rules

1. Keep admin auth logic centralized in backend auth helpers.
2. Avoid duplicating validation logic between frontend and backend.
3. Preserve API response contract expected by admin JS pages.
4. Validate permission boundaries for every new endpoint.

## Validation Checklist

- Admin login, 2FA verification, and reset flows work end-to-end
- No admin endpoint can be called without valid session and CSRF token
- Error responses are structured and user-safe
- UI changes remain responsive on desktop and tablet widths
