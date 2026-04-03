# Admin Assets Folder Guide

This folder contains static assets used by admin pages.

## Subfolders

- `css/`: Admin stylesheets.
- `js/`: Admin runtime logic and page-specific controllers.
- `images/`: Admin images/icons/logos used in panel UI.
- `videos/`: Admin-side video media directories (`products/`, `slider/`).

## Asset Strategy

- Keep executable logic in `js/` and avoid embedding credentials/secrets in client code.
- Keep reusable visual styles in CSS modules.
- Store uploaded media paths in settings/tables and reference via sanitized relative paths.
