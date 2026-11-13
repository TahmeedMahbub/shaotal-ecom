-- PROPOSED, NOT EXECUTED. Review, then run manually against the shared database (the admin app also uses it).
-- The storefront works without these: it currently stores the extra form fields inside the message/details text.

-- 1. Bulk order form fields (design/bulk-order.html): delivery postcode, target date; allow phone-only contact.
ALTER TABLE bulk_order_requests
  ADD COLUMN postcode VARCHAR(60) NULL AFTER company_name,
  ADD COLUMN target_date DATE NULL AFTER postcode,
  MODIFY email VARCHAR(190) NULL;

-- 2. Sourcing request form fields (design/request.html): postcode, preferred colour/size; allow phone-only contact.
ALTER TABLE sourcing_requests
  ADD COLUMN postcode CHAR(4) NULL AFTER phone,
  ADD COLUMN preferred_variant VARCHAR(200) NULL AFTER product_name,
  MODIFY email VARCHAR(190) NULL;

-- 3. Static home page images and footer social links (replace the example URLs with your Cloudinary / social URLs).
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
 ('home_living_image', JSON_QUOTE('https://res.cloudinary.com/<cloud>/image/upload/<living-room>.jpg'), 'Home page: living room banner image'),
 ('home_jute_image',   JSON_QUOTE('https://res.cloudinary.com/<cloud>/image/upload/<jute>.jpg'),        'Home page: jute showcase image'),
 ('home_story_image',  JSON_QUOTE('https://res.cloudinary.com/<cloud>/image/upload/<story>.jpg'),       'Home page: craft story image'),
 ('social_facebook',   JSON_QUOTE('https://facebook.com/<page>'),  'Footer social link'),
 ('social_instagram',  JSON_QUOTE('https://instagram.com/<page>'), 'Footer social link'),
 ('social_tiktok',     JSON_QUOTE('https://tiktok.com/@<page>'),   'Footer social link')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- 4. Return requests (Part 6, Task 4). The storefront feature stays OFF until these tables exist AND the owner sets
--    STOREFRONT_RETURNS_ENABLED=true with an approved policy. No refunds or stock changes are made by these tables.
--    Admin review flow: docs/admin-order-integration.md §7.
CREATE TABLE IF NOT EXISTS order_return_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL UNIQUE,               -- one request per order: no duplicates
 customer_id BIGINT UNSIGNED NULL,
 status ENUM('requested','approved','rejected','completed') NOT NULL DEFAULT 'requested',
 reason VARCHAR(80) NOT NULL,
 details TEXT NULL,
 admin_note TEXT NULL,
 status_changed_at DATETIME NULL,
 changed_by_admin_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(changed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
 INDEX(customer_id, created_at), INDEX(status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_return_request_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 return_request_id BIGINT UNSIGNED NOT NULL,
 old_status VARCHAR(40) NULL,
 new_status VARCHAR(40) NOT NULL,
 note VARCHAR(500) NULL,
 changed_by_admin_id BIGINT UNSIGNED NULL,               -- NULL = the customer
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(return_request_id) REFERENCES order_return_requests(id) ON DELETE CASCADE,
 FOREIGN KEY(changed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
 INDEX(return_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Queue tables for the storefront's background email delivery (verification + password-reset codes).
--    Optional: without them AUTH_MAIL_QUEUE_CONNECTION=deferred sends right after the response (no worker needed).
--    With them: set AUTH_MAIL_QUEUE_CONNECTION=database (or failover) and keep a worker running, e.g.
--    `php artisan queue:work database --queue=default --tries=3 --max-time=3600` under Supervisor/systemd, or a cron
--    `* * * * * php artisan queue:work database --stop-when-empty --tries=3`. Job payloads are encrypted (APP_KEY).
--    Same structure as Laravel's default jobs / failed_jobs tables (QUEUE_FAILED_DRIVER=database-uuids).
CREATE TABLE IF NOT EXISTS jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 queue VARCHAR(255) NOT NULL,
 payload LONGTEXT NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL,
 reserved_at INT UNSIGNED NULL,
 available_at INT UNSIGNED NOT NULL,
 created_at INT UNSIGNED NOT NULL,
 INDEX jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS failed_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 uuid VARCHAR(255) NOT NULL UNIQUE,
 connection TEXT NOT NULL,
 queue TEXT NOT NULL,
 payload LONGTEXT NOT NULL,
 exception LONGTEXT NOT NULL,
 failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Gift wrapping: dedicated columns (OPTIONAL, NOT EXECUTED; the storefront works without them)
-- Today gift wrapping needs no schema change: the fee is an extra order_items row (sku 'GIFT-WRAP', product_id NULL,
-- included in orders.subtotal) and the wrapped items + gift message are a readable block in orders.customer_note
-- (marker "--- GIFT WRAPPING ---"; see App\Support\GiftWrap). If the admin wants first-class fields (print a gift
-- slip, filter gift orders), add these and change GiftWrap::composeNote/split + Checkout::place to fill them:
--   ALTER TABLE orders
--     ADD COLUMN is_gift TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_note,
--     ADD COLUMN gift_message VARCHAR(300) NULL AFTER is_gift,
--     ADD COLUMN gift_wrap_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gift_message;
--   ALTER TABLE order_items ADD COLUMN gift_wrap_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;
-- (CHECK gift_wrap_qty <= quantity; keep the fee row too, or drop it and add gift_wrap_total to grand_total.)

-- 7. Online payment = pre-booking (20% deposit now, balance later). PROPOSED, NOT EXECUTED; the storefront works without it.
-- Today the storefront needs NO schema change: the deposit is a `payments` row (provider "stripe", amount = deposit,
-- status "paid"); the order stays payment_status "pending" (never "paid") until the sum of its paid payment rows reaches
-- grand_total; the rates/discount/deposit/balance snapshot is JSON in payments.response_data->plan; orders.discount_total
-- = coupon + online-payment discount. The admin app should read "amount received" as SUM(payments.amount WHERE status='paid').
-- If the admin wants a first-class state and fast filtering, add (then set it from CardPayments::markPaid):
--   ALTER TABLE orders
--     MODIFY payment_status ENUM('pending','partially_paid','paid','failed','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
--     ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total,
--     ADD COLUMN online_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER discount_total;
--   (balance due = grand_total - amount_paid; keep CHECK(amount_paid>=0).) Admin settings for the rates would be two
--   site_settings rows (e.g. online_payment_discount_percent, online_payment_deposit_percent) read in OnlinePaymentPlan::settings().
