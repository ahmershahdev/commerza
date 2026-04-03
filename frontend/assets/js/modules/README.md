# Frontend JS Modules Guide

This folder contains structured JS modules loaded by the storefront runtime.

## Files

- `01-settings.js`: Shared client runtime settings/constants.
- `02-notifications.js`: Notification/toast helpers.
- `03-account.js`: Account page helper behaviors.
- `04-document-ready.js`: Startup wiring for DOM-ready tasks.
- `05-products.js`: Product listing/search/filter interactions.
- `06-newsletter.js`: Newsletter form handling.
- `07-order-tracking.js`: Order tracking page behavior.
- `08-wishlist-state.js`: Wishlist state store/sync helpers.
- `09-compare-core.js`: Compare feature core logic.
- `10-wishlist-actions.js`: Wishlist action handlers and UI updates.
- `11-compare-render.js`: Compare UI rendering and updates.

## Notes

- Numeric prefixes preserve deterministic load order.
- Keep cross-module dependencies explicit and minimal.
