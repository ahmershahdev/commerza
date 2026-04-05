# Wiki Home

## Purpose

Commerza is a full-stack PHP ecommerce system with customer and admin surfaces. This wiki provides practical maintenance and review guidance.

## Quick Start

1. Read [Architecture](01-ARCHITECTURE.md) to understand modules and request flow.
2. Read [Security Hardening](02-SECURITY-HARDENING.md) before touching auth, account, or checkout paths.
3. Use [Operations Runbook](03-OPERATIONS-RUNBOOK.md) for validations and recurring jobs.
4. Use [SEO/Crawlers/LLMs](04-SEO-CRAWLERS-LLMS.md) when updating indexability files.
5. Use [API Surface](05-API-SURFACE.md) for endpoint ownership and integration mapping.

## Repository Landmarks

- Public pages: root-level `*.php`
- Storefront assets: `frontend/assets/`
- Runtime backend: `backend/`
- Admin backend APIs: `admin/backend/`
- Admin UI and scripts: `admin/frontend/`

## Baseline Validation

- Run PHP lint with explicit XAMPP binary:
  - `C:\xampp\php\php.exe -l <file.php>`
- Prefer validating touched files after each patch burst.
