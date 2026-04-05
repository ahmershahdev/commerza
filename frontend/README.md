# Frontend Module Guide

## Purpose

The frontend module powers the storefront user experience and shared static assets.

## Structure

- `frontend/assets/css/`: stylesheets and modular CSS
- `frontend/assets/js/`: browser-side behavior and API integrations
- `frontend/assets/images/`: logos, products, slider, and user images
- `frontend/assets/videos/`: promotional and product videos

## Responsibilities

- Maintain responsive storefront behavior across desktop and mobile
- Preserve visual consistency with brand colors and components
- Keep API interactions aligned with backend contract expectations

## Asset Rules

1. Keep large media optimized for web delivery.
2. Use predictable, lowercase, hyphenated filenames.
3. Avoid duplicating component styles in page-specific files.
4. Remove stale media when no longer referenced.

## QA Checklist

- Navigation, cart, wishlist, and account interactions work correctly
- Critical pages load without console errors
- Forms remain usable with validation and CAPTCHA enabled
- Core pages maintain acceptable performance on mobile networks

## Notes

- Keep shared logic in modules to reduce page-level duplication.
- Validate cross-page SEO and metadata behavior after layout updates.
