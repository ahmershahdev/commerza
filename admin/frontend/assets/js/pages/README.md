# Admin Auth Page Scripts Guide

This folder contains small scripts dedicated to admin authentication/recovery pages.

## Files

- `admin-auth-common.js`: Shared auth UI helpers (password toggle wiring, single-submit guard).
- `admin-login.js`: Login page controller that binds password reveal and submit loading state.
- `admin-forgot-password.js`: Forgot-password page controller (password toggles, submit states, post-reset redirect trigger).
- `admin-forgot-email.js`: Forgot-email page controller (submit state and success redirect behavior).

## Notes

- These scripts are intentionally lightweight and defer security-critical checks to server-side PHP handlers.
- Keep business logic out of these files; use backend endpoints for sensitive operations.
