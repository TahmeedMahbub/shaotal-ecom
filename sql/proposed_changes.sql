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
-- Gift wrapping: DONE, owned by the Admin app (its sql/proposed_changes.sql "GIFT WRAPPING" block; do not re-run from here).
-- The storefront now writes orders.gift_wrap_requested / gift_wrap_items_count / gift_wrap_fee / gift_message and
-- order_items.gift_wrap_qty; subtotal is goods only and grand_total includes gift_wrap_fee once. Orders placed before
-- that (GIFT-WRAP order line + note block) are still read by App\Support\GiftWrap::split / CustomerOrders.
-- Likewise the 11-value orders.order_status enum (ready_for_dispatch, in_transit, arrived_at_hub, out_for_delivery) is Admin-owned.

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

-- ── 6. Queue tables for a real queue (OPTIONAL, NOT EXECUTED) ──────────────────────────────────────────────────────
-- The order confirmation email works without them (ORDER_MAIL_QUEUE_CONNECTION=deferred, default). To use a database queue
-- (ORDER_MAIL_QUEUE_CONNECTION=database or failover) with `php artisan queue:work`, create Laravel's standard tables:
--   CREATE TABLE jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, queue VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL,
--     attempts TINYINT UNSIGNED NOT NULL, reserved_at INT UNSIGNED NULL, available_at INT UNSIGNED NOT NULL, created_at INT UNSIGNED NOT NULL,
--     KEY jobs_queue_index (queue)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--   CREATE TABLE failed_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, uuid VARCHAR(255) NOT NULL UNIQUE, connection TEXT NOT NULL,
--     queue TEXT NOT NULL, payload LONGTEXT NOT NULL, exception LONGTEXT NOT NULL, failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 8. Account "My Locations": address line 2 and country (PROPOSED, NOT EXECUTED; the storefront works without it) ──
-- customer_addresses has no address_line2/country columns. Today the optional line 2 is stored after a newline in
-- street_address ("12 Smith St\nUnit 5"); AccountController::streetLine() joins it as "12 Smith St, Unit 5" for checkout,
-- order snapshots (orders.shipping_street_address) and the profile view. Country is always Australia (not stored).
-- Custom "Other" labels are stored in customer_addresses.label (VARCHAR 80); "Office" is shown to customers as "Work".
-- If the admin app wants real columns:
--   ALTER TABLE customer_addresses
--     ADD COLUMN address_line2 VARCHAR(120) NULL AFTER street_address,
--     ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT 'AU' AFTER postcode;
--   UPDATE customer_addresses SET address_line2 = TRIM(SUBSTRING_INDEX(street_address, '\n', -1)),
--     street_address = TRIM(SUBSTRING_INDEX(street_address, '\n', 1)) WHERE street_address LIKE CONCAT('%', CHAR(10), '%');
--   (then switch AccountController::addressRow()/addressList() to the new columns.)

-- ── 9. Central Australian locations (PROPOSED, NOT EXECUTED on any shared DB) ──
-- Reference data for every address form (checkout + account). Kept separate from customer_addresses, which still stores
-- the chosen suburb/state/postcode as text (so existing saved addresses and order snapshots are untouched).
-- Filled by `php artisan locations:import-australia` from database/data/locations (Geoscape G-NAF Aug 2026, see README there).
-- Until these tables exist the storefront falls back to the old state/postcode-range validation and a plain suburb field.
-- (Also copied to tests/Support/proposed-schema.sql for local *_test DBs.)
CREATE TABLE IF NOT EXISTS au_states (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 code VARCHAR(3) NOT NULL UNIQUE,
 name VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_localities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 state_id TINYINT UNSIGNED NOT NULL,
 source_pid VARCHAR(40) NOT NULL UNIQUE,            -- G-NAF locality_pid: makes re-imports idempotent
 name VARCHAR(120) NOT NULL,                        -- display case ("Mount Isa")
 class_code CHAR(1) NULL,                           -- G-NAF class: G=gazetted, U=unofficial, I=indigenous, T=topo, D=distributed, H=historic
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(state_id) REFERENCES au_states(id),
 INDEX(state_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_locality_postcodes (
 locality_id INT UNSIGNED NOT NULL,
 postcode CHAR(4) NOT NULL,                         -- text: keeps leading zeros (0800)
 PRIMARY KEY(locality_id, postcode),
 INDEX(postcode),
 FOREIGN KEY(locality_id) REFERENCES au_localities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_location_imports (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source VARCHAR(160) NOT NULL, dataset_version VARCHAR(60) NOT NULL, release_date DATE NULL, file_sha256 CHAR(64) NOT NULL,
 states_count SMALLINT UNSIGNED NOT NULL, localities_count INT UNSIGNED NOT NULL, postcodes_count INT UNSIGNED NOT NULL,
 inserted INT UNSIGNED NOT NULL, updated INT UNSIGNED NOT NULL, skipped INT UNSIGNED NOT NULL, invalid INT UNSIGNED NOT NULL,
 imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Shipping zones / pickup and partially paid deposits (PROPOSED, NOT EXECUTED; the storefront works without it) ──
-- Since 2026-11-14 shipping is Adelaide A$5 / rest of Australia A$12 / free pickup (West Croydon) and every card order pays a
-- 30% deposit. Today: orders.fulfillment_type = delivery|pickup (existing column), the method snapshot is JSON on the first
-- payments row (response_data->shipping) plus an order_timeline note, the deposit plan is response_data->plan, and a paid
-- deposit leaves orders.payment_status 'pending' (amount received = SUM(payments.amount WHERE status='paid')).
-- For first-class admin filtering, the admin/DB owner may add (then the storefront would set them in Checkout::place and
-- CardPayments::markPaid):
--   ALTER TABLE orders
--     MODIFY payment_status ENUM('pending','partially_paid','paid','failed','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
--     ADD COLUMN shipping_method VARCHAR(40) NULL AFTER fulfillment_type,          -- adelaide | australia | pickup
--     ADD COLUMN deposit_required DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER grand_total,
--     ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER deposit_required;
--   (keep CHECK(deposit_required >= 0 AND amount_paid >= 0); balance due = grand_total - amount_paid.)
