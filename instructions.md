# Commerza Integration Instructions

This document lists exactly what is needed to complete third-party integrations in this project.

## 1) Google OAuth

### What You Must Share

- Google Cloud project access (or the generated credentials)
- OAuth Client ID
- OAuth Client Secret
- Authorized redirect URI
- Production app base URL

### Redirect URI to Register

- `https://commerza.ahmershah.dev/backend/oauth.php?provider=google`
- `http://localhost:80/backend/oauth.php?provider=google`
- `http://localhost/backend/oauth.php?provider=google`
- `http://localhost/commerza/backend/oauth.php?provider=google`
- `http://localhost/commerza`
-`http://localhost/commerza/oauth.php?provider=google`
### Localhost/XAMPP Checklist

- If your app runs from `C:\xampp\htdocs\commerza`, set app URL as `http://localhost/commerza`.
- Keep Google Console redirect URI exactly the same as callback (including path and query string).
- Recommended env fallback key: `COMMERZA_APP_URL=http://localhost/commerza`.

### Where It Is Used

- Backend flow: `backend/oauth.php`
- Settings keys:
  - `google_oauth_client_id`
  - `google_oauth_client_secret`
  - `google_oauth_redirect_uri`

## 2) Facebook OAuth

### What You Must Share

- Meta app access (or the generated credentials)
- App ID
- App Secret
- Valid OAuth redirect URI
- Production app base URL

### Redirect URI to Register

- `https://commerza.ahmershah.dev/backend/oauth.php?provider=facebook`
- `http://localhost/backend/oauth.php?provider=facebook`
- `http://localhost/commerza/backend/oauth.php?provider=facebook`

### Where It Is Used

- Backend flow: `backend/oauth.php`
- Settings keys:
  - `facebook_oauth_client_id`
  - `facebook_oauth_client_secret`
  - `facebook_oauth_redirect_uri`

## 3) Stripe

### What You Must Share

- Stripe publishable key (test/live as needed)
- Stripe secret key (test/live as needed)
- Account mode required now (sandbox vs live)
- (Optional) webhook signing secret for future webhook automation

### Where It Is Used

- Checkout flow: `cart.php`
- Intent creation: `backend/stripe_intent.php`
- Stripe helpers: `backend/payment_helpers.php`
- Settings keys:
  - `stripe_publishable_key`
  - `stripe_secret_key`

## 4) JazzCash

### Current State

- The current checkout implementation records wallet number and transaction reference (manual verification style).

### What You Must Share for Full API Integration

- Merchant ID
- Password / API key
- Integrity salt / hash secret
- Return URL / callback URL requirements
- Sandbox and production endpoint docs
- Required request/response signatures and checksum method

### Next Step After Sharing

- Implement server-to-server payment verification endpoint and callback handling.

## 5) Easypaisa

### Current State

- The current checkout implementation records wallet number and transaction reference (manual verification style).

### What You Must Share for Full API Integration

- Merchant account credentials
- Store ID / account ID
- API token/secret
- Callback URL requirements
- Sandbox and production endpoint docs
- Signing/encryption requirements

### Next Step After Sharing

- Implement server-side verification and callback reconciliation for order payment status.

## 6) Email Delivery

### What You Must Share

- SMTP host
- SMTP port
- SMTP username
- SMTP password or app password
- Encryption type (TLS/SSL)
- Sender email and sender name

### Current Behavior

- Uses PHP mail transport (`backend/mailer.php`).
- Notification templates are generated in `backend/notifications.php`.

### Recommendation

- Configure SMTP/sendmail at server level and verify outbound delivery before production launch.

## 7) Required Scheduled Tasks

Set these tasks after email configuration.

### Engagement Reminder Sender

- Command:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\send_engagement_reminders.php 180`
- Suggested schedule:
  - Every 30 minutes

### Monthly Profit Report Sender

- Command:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\commerza\backend\monthly_profit_report.php`
- Suggested schedule:
  - 1st day of every month

## 8) Quick SQL Update Template

Use this template to write keys in `site_settings`:

```sql
INSERT INTO site_settings (setting_key, setting_val)
VALUES ('google_oauth_client_id', 'YOUR_VALUE')
ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val);
```

Repeat for each key listed above.

## 9) Do We Need New SQL Tables For OAuth/Payments?

- No extra table is required for credentials.
- Use the existing `site_settings` key-value store for OAuth and payment provider config.
- Existing/expected keys include:
  - `google_oauth_client_id`
  - `google_oauth_client_secret`
  - `google_oauth_redirect_uri`
  - `facebook_oauth_client_id`
  - `facebook_oauth_client_secret`
  - `facebook_oauth_redirect_uri`
  - `stripe_publishable_key`
  - `stripe_secret_key`
  - `jazzcash_merchant_id`
  - `jazzcash_api_key`
  - `jazzcash_integrity_salt`
  - `jazzcash_callback_url`
  - `easypaisa_merchant_id`
  - `easypaisa_api_key`
  - `easypaisa_callback_url`
