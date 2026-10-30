# Commerza Database

- **Engine:** MySQL 8 / MariaDB 10.4+ (InnoDB, `utf8mb4`)
- **Schema file:** [`database/commerza.sql`](../database/commerza.sql), 43 tables and 11 reporting views
- **Seed data:** 5 catalog sections, 40 products, FAQ, slider, ticker, testimonials, email templates, page meta, site settings, username blacklist
- **Not seeded:** admin accounts (created from `COMMERZA_ADMIN_BOOTSTRAP_*` on first admin request), customers, orders, sessions, rate-limit rows

Some columns and indexes are also self-healed at runtime (`ensure_*_schema` helpers), so an older database is upgraded the first time the relevant page or API runs.

## Entity relationship diagram (core commerce)

```mermaid
erDiagram
    users ||--o{ orders : places
    users ||--o| cart : owns
    users ||--o| wishlist : owns
    users ||--o| compare_list : owns
    users ||--o{ user_sessions : "remember-me"
    users ||--o{ product_reviews : writes
    users ||--o{ refund_requests : files
    users ||--o{ coupon_redemptions : redeems

    sections ||--o{ products : groups
    products ||--o{ order_items : "sold as"
    products ||--o{ cart_items : "added to"
    products ||--o{ wishlist_items : "saved in"
    products ||--o{ compare_items : "compared in"
    products ||--o{ product_reviews : receives

    orders ||--|{ order_items : contains
    orders ||--o{ refund_requests : "may have"
    orders ||--o{ coupon_redemptions : applies
    coupons ||--o{ coupon_redemptions : "used by"

    cart ||--o{ cart_items : holds
    wishlist ||--o{ wishlist_items : holds
    compare_list ||--o{ compare_items : holds
    product_reviews ||--o{ product_review_images : has

    users {
        int id PK
        varchar username UK
        varchar email UK
        varchar password_hash
        enum profile_visibility
        datetime username_changed_at
    }
    products {
        int id PK
        varchar sectionId FK
        varchar name
        varchar slug UK
        decimal price
        decimal salePrice
        int stock
        enum movement
        varchar product_code UK
        datetime deleted_at
    }
    sections {
        int id PK
        varchar sectionId UK
        varchar sectionName
        varchar page
    }
    orders {
        int id PK
        varchar order_number UK
        int user_id FK
        decimal subtotal
        decimal shipping_cost
        decimal discount_total
        decimal grand_total
        enum status
        enum payment_status
        varchar payment_method
        datetime delivery_estimate
    }
    order_items {
        int id PK
        int order_id FK
        int product_id FK
        varchar product_name
        decimal unit_price
        int quantity
        decimal line_total
    }
    coupons {
        int id PK
        varchar code UK
        enum discount_type
        decimal discount_value
        decimal min_order
        int usage_limit
        int per_user_limit
        datetime expires_at
    }
    product_reviews {
        int id PK
        int user_id FK
        int product_id FK
        int order_id FK
        tinyint rating
        tinyint is_verified_purchase
        tinyint is_visible
    }
    refund_requests {
        int id PK
        int order_id FK
        int user_id FK
        enum status
        varchar evidence_path
    }
```

## Entity relationship diagram (administration and content)

```mermaid
erDiagram
    admin_users ||--o{ admin_sessions : "signs in"
    admin_users ||--o{ notifications : receives
    admin_users ||--o{ email_outbox : sends
    admin_users ||--o{ email_manual_recipients : adds
    admin_users ||--o{ ticker : edits
    admin_users ||--o{ collectors_speak : edits
    admin_users ||--o{ product_fake_reviews : generates
    email_templates ||--o{ email_outbox : renders
    faq_categories ||--o{ faq : groups

    admin_users {
        int id PK
        varchar email UK
        enum role
        longtext permissions_json
        longtext hidden_tabs_json
        datetime email_verified_at
        varchar two_factor_code_hash
        datetime suspended_until
    }
    security_events {
        int id PK
        varchar event_type
        enum severity
        varchar actor_type
        varchar ip_address
        longtext details_json
    }
    site_settings {
        int id PK
        varchar setting_key UK
        text setting_val
        varchar setting_group
    }
```

## Table groups

| Group | Tables |
| --- | --- |
| Customers | `users`, `user_sessions`, `username_blacklist`, `customer_blacklist` |
| Catalog | `sections`, `products`, `product_trash`, `live_product_viewers` |
| Shopping | `cart`, `cart_items`, `wishlist`, `wishlist_items`, `compare_list`, `compare_items` |
| Orders | `orders`, `order_items`, `refund_requests`, `coupons`, `coupon_redemptions` |
| Reviews | `product_reviews`, `product_review_images`, `product_fake_reviews` |
| Admin | `admin_users`, `admin_sessions`, `notifications`, `security_events`, `rate_limits` |
| Email | `email_templates`, `email_outbox`, `email_manual_recipients`, `email_suppressed`, `engagement_reminders`, `newsletter_subscribers`, `contact_messages` |
| Content | `site_settings`, `page_meta`, `page_content`, `slider`, `ticker`, `social_links`, `collectors_speak`, `faq`, `faq_categories` |
| Reporting views | `vw_admin_dashboard_metrics`, `vw_order_summary`, `vw_order_details`, `vw_customer_stats`, `vw_low_stock_products`, `vw_products`, `vw_cart_details`, `vw_wishlist_products`, `vw_compare_products`, `vw_email_outbox_full`, `vw_faq_full` |

## Import

```bash
mysql -u root -e "CREATE DATABASE commerza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root commerza < database/commerza.sql
```

Backup and restore verification lives in [`scripts/maintenance/BACKUP_RESTORE_TESTS.md`](../scripts/maintenance/BACKUP_RESTORE_TESTS.md).
