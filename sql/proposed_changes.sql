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
