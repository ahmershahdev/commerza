# Admin Folder Guide

This folder contains the admin application split into backend APIs and frontend pages/assets.

## Subfolders

- `backend/`: Auth, admin APIs, and server-side admin controls.
- `frontend/`: Admin UI pages and static assets.

## Architectural Notes

- Admin runtime uses server-rendered PHP pages and API endpoints under `admin/backend`.
- Admin auth/session handling is centralized in `admin/backend/auth.php`.
- Frontend page scripts call backend APIs with CSRF protection and same-origin credentials.
