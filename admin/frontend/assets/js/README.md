# Admin JS Folder Guide

This folder contains admin-side JavaScript runtime code.

## Files

- `script.js`: Main admin dashboard controller (API calls, tables, forms, security panel actions, website settings UI, notifications, and state orchestration).
- `admin-config.js`: Admin branding/meta customization utility sourced from session-stored site settings (no credentials stored here).

## Subfolders

- `pages/`: Lightweight page-specific behavior scripts for auth/recovery pages.

## Notes

- `script.js` expects backend API endpoints exposed by `window.CommerzaAdminRuntime` and same-origin authenticated sessions.
- Page scripts in `pages/` use shared helpers from `pages/admin-auth-common.js`.
