# Admin Frontend Folder Guide

This folder contains admin-facing pages and client-side assets.

## Page Files

- `admin-login.php`: Primary admin login page (now includes CAPTCHA + server-side validation + email 2FA handoff).
- `admin-verify-2fa.php`: OTP verification page for admin login challenge completion.
- `admin-forgot-password.php`: Admin password recovery flow (send code + reset password, with CAPTCHA protection).
- `admin-forgot-email.php`: Admin email recovery/update page guarded by reset key and validation.
- `admin-panel.php`: Main admin dashboard and control center.

## Assets

- `assets/css/`: Admin styling layers.
- `assets/js/`: Admin runtime scripts and page-level controllers.
- `assets/images/`: Admin UI images/icons.
- `assets/videos/`: Admin-managed video assets.

## Runtime Notes

- Shared auth helpers are loaded from `admin/backend/auth.php`.
- Admin pages use session-based auth and CSRF token meta/hidden fields.
- Most interactive dashboard operations call APIs in `admin/backend/`.
