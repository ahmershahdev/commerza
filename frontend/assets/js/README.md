# Frontend JS Folder Guide

This folder contains storefront JavaScript entry scripts and modules.

## Files

- `script.js`: Main storefront runtime controller for UI interactions and feature bootstrapping.
- `global-protection.js`: Global client-side hardening helpers (defensive browser-side protections).
- `auth.js`: Client auth facade that defers sensitive operations to secure server forms.

## Subfolders

- `modules/`: Ordered feature modules consumed by the main runtime.

## Notes

- Security-sensitive operations must remain server-side.
- Keep module logic focused; use `script.js` for composition/initialization.
