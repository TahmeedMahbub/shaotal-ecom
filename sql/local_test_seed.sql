-- LOCAL TEST DATA ONLY. Load into a scratch database (never the shared/production one):
--   mysql -uroot shaotal_local < sql/ecom.sql && mysql -uroot shaotal_local < sql/local_test_seed.sql
-- Images use Cloudinary's public demo account so Cloudinary-hosted URLs can be exercised.

INSERT INTO categories (id,name,slug,description,image_url,sort_order) VALUES
 (1,'Fashion','fashion','Everyday clothing, relaxed fits, linen pieces and modern essentials.','https://res.cloudinary.com/demo/image/upload/w_600/sample.jpg',1),
 (2,'Home & Living','home-decor','Handcrafted accents, textiles, ceramics and natural materials.','https://res.cloudinary.com/demo/image/upload/w_600/cld-sample-5.jpg',2),
 (3,'Jute','jute','Practical and beautiful products crafted from natural golden jute fiber.','https://res.cloudinary.com/demo/image/upload/w_600/sample.jpg',3),
 (4,'Gifts','lifestyle','Thoughtful gifts wrapped with care.','https://res.cloudinary.com/demo/image/upload/w_600/sample.jpg',4);

INSERT INTO subcategories (id,category_id,name,slug,sort_order) VALUES
 (1,1,'T-Shirts','t-shirts',1),(2,1,'Trousers','trousers',2),(3,2,'Cushions','cushions',1);

INSERT INTO brands (id,name,slug) VALUES (1,'Heritage Weaves','heritage-weaves'),(2,'Sonali Jute','sonali-jute');

INSERT INTO size_guides (id,title,`key`,customer_details) VALUES (1,'Apparel Size Guide','apparel','Measurements are taken flat in cm.');
INSERT INTO size_guide_attributes (id,size_guide_id,attribute_name,attribute_key,unit,sort_order) VALUES (1,1,'Chest Width','chest','cm',1),(2,1,'Body Length','length','cm',2);
INSERT INTO size_guide_values (size_guide_id,size_label,attribute_id,measurement_value,sort_order) VALUES
 (1,'M',1,55,1),(1,'M',2,73,1),(1,'L',1,58,2),(1,'L',2,75,2);

-- 1: variant product (color_size) with sale price
INSERT INTO products (id,category_id,subcategory_id,brand_id,name,slug,sku,short_description,description,price,selling_price,has_variants,variant_scenario,stock_qty,availability,badge,low_stock_threshold,size_guide_id,visibility,status)
VALUES (1,1,1,1,'Essential Heavyweight 240GSM Tee','essential-heavyweight-tee','TEE-001','100% Organic Combed Cotton · Relaxed Fit','Heavyweight tee.\nPre-shrunk and breathable.',38.00,26.00,1,'color_size',NULL,'in_stock','["new"]',NULL,1,'visible','active');
INSERT INTO product_colors (id,product_id,name,hex_code,sort_order) VALUES (1,1,'Olive Green','#5C6B4F',1),(2,1,'Midnight Black','#1A1A1A',2);
INSERT INTO product_sizes (id,product_id,size_label,sort_order) VALUES (1,1,'S',1),(2,1,'M',2),(3,1,'L',3);
INSERT INTO product_variants (product_id,color_id,size_id,sku,stock_qty,price_override) VALUES
 (1,1,1,'TEE-001-OG-S',0,NULL),(1,1,2,'TEE-001-OG-M',10,NULL),(1,1,3,'TEE-001-OG-L',5,NULL),
 (1,2,1,'TEE-001-BK-S',4,NULL),(1,2,2,'TEE-001-BK-M',8,NULL),(1,2,3,'TEE-001-BK-L',2,32.00);
INSERT INTO product_images (id,product_id,image_url,alt_text,is_main,sort_order) VALUES
 (1,1,'https://res.cloudinary.com/demo/image/upload/w_800/sample.jpg','Tee',1,1),
 (2,1,'https://res.cloudinary.com/demo/image/upload/w_800/cld-sample.jpg','Tee olive',0,2);
INSERT INTO product_image_colors (image_id,color_id) VALUES (2,1);
INSERT INTO product_specifications (product_id,material,fabric,dimensions,fit,care_instructions) VALUES (1,'Organic cotton','240GSM combed cotton',NULL,'Relaxed','Machine wash cold. Line dry.');

-- 2: simple product
INSERT INTO products (id,category_id,subcategory_id,brand_id,name,slug,sku,short_description,description,price,selling_price,has_variants,stock_qty,availability,low_stock_threshold,visibility,status)
VALUES (2,2,3,1,'Nakshi Kantha Cushion','nakshi-kantha-cushion','CUSH-001','Hand-embroidered cotton cushion cover','Embroidered by artisans.',55.00,55.00,0,20,'in_stock',3,'visible','active');
INSERT INTO product_images (product_id,image_url,is_main,sort_order) VALUES (2,'https://res.cloudinary.com/demo/image/upload/w_800/cld-sample-2.jpg',1,1);

-- 3: jute, low stock
INSERT INTO products (id,category_id,brand_id,name,slug,sku,short_description,price,selling_price,has_variants,stock_qty,availability,low_stock_threshold,visibility,status)
VALUES (3,3,2,'Handwoven Jute Tote','handwoven-jute-tote','JUTE-001','Natural golden jute tote',45.00,45.00,0,2,'in_stock',3,'visible','active');
INSERT INTO product_images (product_id,image_url,is_main,sort_order) VALUES (3,'https://res.cloudinary.com/demo/image/upload/w_800/cld-sample-3.jpg',1,1);

-- 4: pre-order
INSERT INTO products (id,category_id,brand_id,name,slug,sku,short_description,price,selling_price,has_variants,stock_qty,availability,preorder_release_date,visibility,status)
VALUES (4,4,1,'Leather Journal','leather-journal','JRN-001','Full-grain leather journal',79.00,79.00,0,0,'preorder','2026-11-30','visible','active');
INSERT INTO product_images (product_id,image_url,is_main,sort_order) VALUES (4,'https://res.cloudinary.com/demo/image/upload/w_800/cld-sample-4.jpg',1,1);

-- 5: sold out
INSERT INTO products (id,category_id,name,slug,sku,short_description,price,has_variants,stock_qty,availability,visibility,status)
VALUES (5,3,'Jute Floor Mat','jute-floor-mat','JUTE-002','Round jute mat',60.00,0,0,'out_of_stock','visible','active');
INSERT INTO product_images (product_id,image_url,is_main,sort_order) VALUES (5,'https://res.cloudinary.com/demo/image/upload/w_800/sample.jpg',1,1);

INSERT INTO hero_slides (title,subtitle,image_url,button_text,button_url,sort_order) VALUES
 ('Thoughtfully Crafted. Curated for Australia.','Ethically made fashion, handcrafted decor and natural jute.','https://res.cloudinary.com/demo/image/upload/w_1600/sample.jpg','Shop Collection','/products',1);

INSERT INTO coupons (code,description,discount_type,discount_value,minimum_order_amount,status) VALUES
 ('10AUD','A$10 off','fixed_amount',10.00,NULL,'active'),
 ('10PER','10% off','percentage',10.00,NULL,'active'),
 ('FREESHIP','Free shipping','free_shipping',0,NULL,'active');

INSERT INTO site_settings (setting_key,setting_value) VALUES
 ('home_living_image','"https://res.cloudinary.com/demo/image/upload/w_900/sample.jpg"'),
 ('home_jute_image','"https://res.cloudinary.com/demo/image/upload/w_900/cld-sample-3.jpg"'),
 ('home_story_image','"https://res.cloudinary.com/demo/image/upload/w_900/cld-sample.jpg"');
