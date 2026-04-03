# Database Folder Guide

This folder stores SQL artifacts for Commerza.

## Files

- `commerza.sql`: Canonical database schema + seed data.

## What `commerza.sql` Includes

- Core business tables (users, products, carts, orders, reviews, coupons, etc.).
- Admin and security support tables.
- Default `site_settings` rows for branding, integrations, analytics, and security.
- Initial content for ticker/slider and other runtime data.

## Usage

- Use this file for first-time local setup and reproducible environment provisioning.
- Keep schema changes additive and backward-compatible when possible.
- When adding new settings keys, also document them in project docs and relevant APIs.
