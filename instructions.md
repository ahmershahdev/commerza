# Commerza Integration Instructions

## Purpose

This document lists the exact integration requirements for OAuth, SMTP, CAPTCHA, payments, and scheduled automation.

## 1) Google OAuth

### Required Inputs

- Google OAuth Client ID
- Google OAuth Client Secret
- Redirect URI registered in Google console

### Redirect URI

- `https://<your-domain>/backend/oauth.php?provider=google`
- `http://localhost/commerza/backend/oauth.php?provider=google`

### Config Keys

- `google_oauth_client_id`
- `google_oauth_client_secret`
- `google_oauth_redirect_uri`

## 2) Facebook OAuth

### Required Inputs

- Facebook App ID
- Facebook App Secret
- Redirect URI registered in Meta app

### Redirect URI

- `https://<your-domain>/backend/oauth.php?provider=facebook`
- `http://localhost/commerza/backend/oauth.php?provider=facebook`

### Config Keys

- `facebook_oauth_client_id`
- `facebook_oauth_client_secret`
- `facebook_oauth_redirect_uri`

## 3) Stripe

### Required Inputs

- Stripe publishable key
- Stripe secret key

### Config Keys

- `stripe_publishable_key`
- `stripe_secret_key`

### Runtime Paths

- Checkout page: `cart.php`
- Intent endpoint: `backend/stripe_intent.php`
- Helper functions: `backend/payment_helpers.php`

## 4) SMTP (Gmail Recommended)

### Required Inputs

- SMTP host, port, encryption mode
- Sender mailbox credentials or app password

### `.env` Keys

- `COMMERZA_SMTP_HOST=smtp.gmail.com`
- `COMMERZA_SMTP_PORT=587`
- `COMMERZA_SMTP_ENCRYPTION=tls`
- `COMMERZA_SMTP_USERNAME=<gmail>`
- `COMMERZA_SMTP_PASSWORD=<gmail-app-password>`
- `COMMERZA_SMTP_FROM_EMAIL=<gmail>`
- `COMMERZA_SMTP_FROM_NAME=Commerza`

### Verification

Trigger real flow emails and confirm delivery:

- Signup verification email
- Admin 2FA code email

## 5) CAPTCHA

### Required Inputs

- reCAPTCHA v2 site key
- reCAPTCHA v2 secret key

### `.env` Keys

- `COMMERZA_CAPTCHA_ENABLED=1`
- `COMMERZA_CAPTCHA_PROVIDER=recaptcha`
- `COMMERZA_RECAPTCHA_SITE_KEY=<site-key>`
- `COMMERZA_RECAPTCHA_SECRET_KEY=<secret-key>`

## 6) Scheduled Tasks

### Commands

- Engagement reminders:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\send_engagement_reminders.php 180`
- Monthly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\monthly_profit_report.php`
- Weekly report:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\weekly_analytics_report.php`

## 7) Go-Live Validation

- OAuth login tested for Google and Facebook
- SMTP delivery tested from real app flows
- CAPTCHA passes for signup, password reset, and admin verification
- Stripe checkout intent and confirmation validated
- Rate limit and CSRF protections active
