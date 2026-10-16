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
