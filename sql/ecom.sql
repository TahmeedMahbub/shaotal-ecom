-- Ecommerce + Admin shared schema | MySQL 8.0+
-- Two separate Laravel monolithic applications connect directly to this shared database.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE admin_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 profile_image_url VARCHAR(2048),
 status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
 last_login_at DATETIME,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
 description TEXT, image_url VARCHAR(2048), sort_order INT NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subcategories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, description TEXT, image_url VARCHAR(2048),
 sort_order INT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT, INDEX(category_id,status,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
 logo_url VARCHAR(2048), description TEXT, origin_country VARCHAR(100),
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NOT NULL,
 subcategory_id BIGINT UNSIGNED NULL, brand_id BIGINT UNSIGNED NULL,
 name VARCHAR(220) NOT NULL, slug VARCHAR(240) NOT NULL UNIQUE, sku VARCHAR(120) UNIQUE,
 short_description VARCHAR(500), description LONGTEXT,
 price DECIMAL(12,2) NOT NULL, selling_price DECIMAL(12,2) NULL,
 has_variants TINYINT(1) NOT NULL DEFAULT 0,
 variant_scenario ENUM('color_size','color_only','size_only') NULL,
 stock_qty INT UNSIGNED NULL,
 availability ENUM('in_stock','out_of_stock','preorder','coming_soon') NOT NULL DEFAULT 'in_stock',
 badge JSON NULL, preorder_release_date DATE, low_stock_threshold INT UNSIGNED,
 size_guide_id BIGINT UNSIGNED NULL,
 visibility ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
 status ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
 seo_title VARCHAR(255), seo_description VARCHAR(500),
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT,
 FOREIGN KEY(subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
 FOREIGN KEY(brand_id) REFERENCES brands(id) ON DELETE SET NULL,
 CHECK(price>=0 AND (selling_price IS NULL OR (selling_price>=0 AND selling_price<=price))),
 CHECK((has_variants=0 AND stock_qty IS NOT NULL AND variant_scenario IS NULL) OR
       (has_variants=1 AND stock_qty IS NULL AND variant_scenario IS NOT NULL)),
 INDEX(status,visibility,created_at), INDEX(category_id,status), INDEX(size_guide_id),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_colors (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL, hex_code CHAR(7), sort_order INT NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 UNIQUE(product_id,name), UNIQUE(id,product_id), INDEX(product_id,status,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_sizes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL,
 size_label VARCHAR(80) NOT NULL, sort_order INT NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 UNIQUE(product_id,size_label), UNIQUE(id,product_id), INDEX(product_id,status,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL,
 color_id BIGINT UNSIGNED NULL, size_id BIGINT UNSIGNED NULL, sku VARCHAR(120) NOT NULL UNIQUE,
 price_override DECIMAL(12,2), selling_price_override DECIMAL(12,2),
 stock_qty INT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
 color_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(color_id,0)) STORED,
 size_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(size_id,0)) STORED,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY(color_id,product_id) REFERENCES product_colors(id,product_id) ON DELETE RESTRICT,
 FOREIGN KEY(size_id,product_id) REFERENCES product_sizes(id,product_id) ON DELETE RESTRICT,
 CHECK(price_override IS NULL OR price_override>=0),
 CHECK(selling_price_override IS NULL OR selling_price_override>=0),
 UNIQUE(product_id,color_key,size_key), INDEX(product_id,is_active), INDEX(stock_qty),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL,
 image_url VARCHAR(2048) NOT NULL, alt_text VARCHAR(255), is_main TINYINT(1) NOT NULL DEFAULT 0,
 sort_order INT NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE, INDEX(product_id,is_main,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images with no product_image_colors mappings are general and apply to every color.
-- One image can be assigned to several colors.
CREATE TABLE product_image_colors (
 image_id BIGINT UNSIGNED NOT NULL, color_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(image_id,color_id),
 FOREIGN KEY(image_id) REFERENCES product_images(id) ON DELETE CASCADE,
 FOREIGN KEY(color_id) REFERENCES product_colors(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_specifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL UNIQUE,
 material VARCHAR(160), fabric VARCHAR(160), dimensions VARCHAR(160), fit VARCHAR(100), pattern VARCHAR(100),
 care_instructions TEXT, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE size_guides (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(160) NOT NULL,
 `key` VARCHAR(100) NOT NULL UNIQUE,
 customer_details TEXT NULL,
 admin_details TEXT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE size_guide_attributes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 size_guide_id BIGINT UNSIGNED NOT NULL,
 attribute_name VARCHAR(100) NOT NULL,
 attribute_key VARCHAR(100) NOT NULL,
 unit VARCHAR(20) NOT NULL DEFAULT 'cm',
 sort_order INT NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_size_guide_attribute_key (size_guide_id, attribute_key),
 UNIQUE KEY uq_size_guide_attribute_id (size_guide_id, id),
 KEY idx_size_guide_attributes_order (size_guide_id, sort_order),
 FOREIGN KEY(size_guide_id) REFERENCES size_guides(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE size_guide_values (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 size_guide_id BIGINT UNSIGNED NOT NULL,
 size_label VARCHAR(80) NOT NULL,
 attribute_id BIGINT UNSIGNED NOT NULL,
 measurement_value DECIMAL(10,2) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_size_guide_size_attribute (size_guide_id, size_label, attribute_id),
 KEY idx_size_guide_values_order (size_guide_id, sort_order),
 FOREIGN KEY(size_guide_id, attribute_id) REFERENCES size_guide_attributes(size_guide_id, id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products
 ADD CONSTRAINT fk_products_size_guide
 FOREIGN KEY(size_guide_id) REFERENCES size_guides(id) ON DELETE SET NULL;

CREATE TABLE customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100),
 email VARCHAR(190) UNIQUE, phone VARCHAR(40) UNIQUE, password_hash VARCHAR(255), profile_image_url VARCHAR(2048),
 status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
 email_verified_at DATETIME, phone_verified_at DATETIME, notes TEXT,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(last_name,first_name), INDEX(status),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_addresses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 label VARCHAR(80) NULL,
 recipient_name VARCHAR(180) NOT NULL,
 phone VARCHAR(40) NOT NULL,
 street_address VARCHAR(255) NOT NULL,
 suburb VARCHAR(120) NULL,
 state VARCHAR(120) NULL,
 postcode VARCHAR(30) NULL,
 is_default TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 INDEX(customer_id,is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_verification_codes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NULL, destination VARCHAR(190) NOT NULL,
 channel ENUM('email','sms') NOT NULL, purpose ENUM('signup','login','password_reset','phone_verification','email_verification') NOT NULL,
 code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, verified_at DATETIME,
 attempts INT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE, INDEX(destination,purpose,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_security_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NULL, event_type VARCHAR(100) NOT NULL, source VARCHAR(80) NOT NULL,
 ip_address VARCHAR(45), user_agent VARCHAR(500), metadata JSON, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL, INDEX(customer_id,created_at), INDEX(ip_address,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NULL, session_token_hash VARCHAR(255) UNIQUE,
 status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active', last_activity_at DATETIME,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL, INDEX(status,last_activity_at), INDEX(customer_id,status),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 variant_id BIGINT UNSIGNED NULL, quantity INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE RESTRICT,
 CHECK(quantity>0), INDEX(cart_id), INDEX(product_id,variant_id),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE, UNIQUE(customer_id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL,
 customer_email VARCHAR(190) NULL,
 customer_phone VARCHAR(40) NULL,
 shipping_recipient_name VARCHAR(180) NOT NULL,
 shipping_phone VARCHAR(40) NOT NULL,
 shipping_street_address VARCHAR(255) NOT NULL,
 shipping_suburb VARCHAR(120) NULL,
 shipping_state VARCHAR(120) NULL,
 shipping_postcode VARCHAR(30) NULL,
 subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
 discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 coupon_code VARCHAR(100) NULL,
 payment_method VARCHAR(80) NULL,
 payment_status ENUM('pending','paid','failed','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
 order_status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
 fulfillment_type ENUM('delivery','pickup') NOT NULL DEFAULT 'delivery',
 customer_note TEXT NULL,
 admin_note TEXT NULL,
 placed_at DATETIME NULL,
 cancelled_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 CHECK(subtotal>=0 AND discount_total>=0 AND shipping_total>=0 AND tax_total>=0 AND grand_total>=0),
 INDEX(order_status,created_at), INDEX(payment_status,created_at), INDEX(customer_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL, variant_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(220) NOT NULL, sku VARCHAR(120), image_url VARCHAR(2048),
 variant_color VARCHAR(80), variant_size VARCHAR(80),
 unit_price DECIMAL(12,2) NOT NULL, unit_selling_price DECIMAL(12,2) NOT NULL,
 quantity INT UNSIGNED NOT NULL, line_discount DECIMAL(12,2) NOT NULL DEFAULT 0, line_total DECIMAL(12,2) NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 FOREIGN KEY(variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
 CHECK(unit_price>=0 AND unit_selling_price>=0 AND quantity>0 AND line_discount>=0 AND line_total>=0),
 INDEX(product_id), INDEX(variant_id),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_timeline (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, old_status VARCHAR(40),
 new_status VARCHAR(40) NOT NULL, note VARCHAR(500), changed_by_admin_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 FOREIGN KEY(changed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL, INDEX(order_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(80),
 provider_transaction_id VARCHAR(190), payment_method VARCHAR(80), amount DECIMAL(12,2) NOT NULL,
 status ENUM('pending','authorized','paid','failed','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
 paid_at DATETIME, response_data JSON, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT, CHECK(amount>=0),
 UNIQUE(provider,provider_transaction_id), INDEX(order_id,status),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(500),
 discount_type ENUM('percentage','fixed_amount','free_shipping') NOT NULL, discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
 minimum_order_amount DECIMAL(12,2), maximum_discount_amount DECIMAL(12,2),
 usage_limit INT UNSIGNED, usage_limit_per_customer INT UNSIGNED, starts_at DATETIME, expires_at DATETIME,
 status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK(discount_value>=0), INDEX(status,starts_at,expires_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_products (
 coupon_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(coupon_id,product_id),
 FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_categories (
 coupon_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(coupon_id,category_id),
 FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hero_slides (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(220), subtitle VARCHAR(500),
 image_url VARCHAR(2048) NOT NULL, mobile_image_url VARCHAR(2048), button_text VARCHAR(100), button_url VARCHAR(2048),
 sort_order INT NOT NULL DEFAULT 0, starts_at DATETIME, ends_at DATETIME,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status,sort_order),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(160) NOT NULL UNIQUE,
 setting_value JSON, description VARCHAR(500), created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value, description)
VALUES ('currency_code', NULL, 'Canonical currency code used by both Ecommerce and Admin applications; configure before launch.')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

CREATE TABLE newsletter_subscribers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE,
 status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
 subscribed_at DATETIME, unsubscribed_at DATETIME, source VARCHAR(100),
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_reviews (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NULL,
 order_item_id BIGINT UNSIGNED NULL, reviewer_name VARCHAR(160), reviewer_email VARCHAR(190),
 rating TINYINT UNSIGNED NOT NULL, title VARCHAR(220), body TEXT,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', admin_reply TEXT, replied_at DATETIME,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
 CHECK(rating BETWEEN 1 AND 5), INDEX(product_id,status,created_at), INDEX(status,created_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, email VARCHAR(190), phone VARCHAR(40),
 subject VARCHAR(220), message TEXT NOT NULL,
 status ENUM('new','in_progress','resolved','spam') NOT NULL DEFAULT 'new', admin_notes TEXT,
 assigned_admin_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL, INDEX(status,created_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE abandoned_cart_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cart_id BIGINT UNSIGNED NOT NULL, customer_id BIGINT UNSIGNED NULL,
 reminder_status ENUM('not_sent','sent','clicked','converted','failed') NOT NULL DEFAULT 'not_sent',
 reminder_sent_at DATETIME, converted_order_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(converted_order_id) REFERENCES orders(id) ON DELETE SET NULL, INDEX(reminder_status,created_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bulk_order_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL, contact_name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL,
 phone VARCHAR(40), company_name VARCHAR(180), message TEXT,
 status ENUM('new','reviewing','quoted','accepted','rejected','completed') NOT NULL DEFAULT 'new',
 admin_notes TEXT, assigned_admin_id BIGINT UNSIGNED NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL, INDEX(status,created_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bulk_order_request_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(220) NOT NULL, quantity INT UNSIGNED NOT NULL DEFAULT 1, target_price DECIMAL(12,2), notes VARCHAR(500),
 FOREIGN KEY(request_id) REFERENCES bulk_order_requests(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL, CHECK(quantity>0), INDEX(request_id), created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bulk_order_item_variants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bulk_order_request_item_id BIGINT UNSIGNED NOT NULL,
 color_name VARCHAR(80), size_label VARCHAR(80), quantity INT UNSIGNED NOT NULL DEFAULT 1,
 FOREIGN KEY(bulk_order_request_item_id) REFERENCES bulk_order_request_items(id) ON DELETE CASCADE,
 CHECK(quantity>0), INDEX(bulk_order_request_item_id), created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sourcing_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NULL, customer_name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40),
 product_url VARCHAR(2048), product_name VARCHAR(220), requested_quantity INT UNSIGNED NOT NULL DEFAULT 1,
 target_budget DECIMAL(12,2), details TEXT,
 status ENUM('new','reviewing','quoted','accepted','rejected','completed') NOT NULL DEFAULT 'new',
 admin_notes TEXT, assigned_admin_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
 CHECK(requested_quantity>0), INDEX(status,created_at),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sourcing_request_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sourcing_request_id BIGINT UNSIGNED NOT NULL,
 image_url VARCHAR(2048) NOT NULL, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(sourcing_request_id) REFERENCES sourcing_requests(id) ON DELETE CASCADE, INDEX(sourcing_request_id),
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- Application rules:
-- * Simple product: has_variants=0, variant_scenario=NULL, products.sku and products.stock_qty are used.
-- * Variant product: has_variants=1, stock_qty=NULL; variant rows own SKU and stock.
-- * color_only: each variant has color_id and NULL size_id.
-- * size_only: each variant has size_id and NULL color_id.
-- * color_size: each variant has both color_id and size_id.
-- * Product/variant effective price: COALESCE(price_override, products.price).
-- * Effective selling price: COALESCE(selling_price_override, products.selling_price, effective_price).
-- * Exactly one main image per product must be enforced in application logic.
-- * Images with no product_image_colors mapping are general images for all colors;
--   mapped images are available only to their assigned colors. One image may map to many colors.
-- * Validate that a variant's color/size belongs to its product and matches variant_scenario.
-- * Do not hard-delete products/variants referenced by historical orders; deactivate instead.
-- * Store product name, SKU, image, color, size, and prices as order-item snapshots.
-- * Never store plaintext OTP, passwords, payment secrets, or card details.
