# API Surface Map

## Storefront Backend APIs (`backend/`)

- `products_api.php`: product feeds, filtering, listing data
- `cart_api.php`: cart add/update/remove and cart state
- `wishlist_api.php`: wishlist CRUD and status
- `reviews_api.php`: review list, eligibility, submit/update
- `newsletter_api.php`: newsletter subscription handling
- `viewers_api.php`: live viewer stats/mode behavior
- `stripe_intent.php`: Stripe intent creation for checkout
- `oauth.php`: OAuth callback/link/login handling
- `order_tracking_api.php`: AJAX order tracking lookup

## Admin Backend APIs (`admin/backend/`)

- `orders_api.php`: order operations, refunds, summaries
- `products_sync_api.php`: product sync and catalog writes
- `coupons_api.php`: coupon CRUD and campaign sends
- `reviews_api.php`: moderation and review admin actions
- `security_api.php`: security settings + event listing
- `viewers_api.php`: admin viewer controls
- `website_api.php`: branding/contact/social/ticker/slider settings
- `media_api.php`: media upload handling

## Shared Security Gateways

- Storefront bootstrap and headers: `backend/data.php`
- Rate limiter: `backend/rate_limit.php`
- Security event logger: `backend/security_events.php`
- Admin auth/permission helpers: `admin/backend/auth.php`

## API Design Notes

- Prefer JSON responses with explicit `ok` and `message` fields.
- Return updated CSRF token when token rotation is used.
- Keep validation strict on both content and type.
- Avoid exposing stack traces or SQL details in responses.
