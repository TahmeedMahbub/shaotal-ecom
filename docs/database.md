# Database (from `sql/ecom.sql`)

MySQL 8, InnoDB, utf8mb4. 39 tables, shared with a separate admin app. PKs are `BIGINT UNSIGNED AUTO_INCREMENT id` unless noted. Most tables also carry `created_by`/`updated_by` → `admin_users(id)` (SET NULL) and timestamps; omitted below. "(U)" = unique.

## Admin
- `admin_users` — name, email(U), password_hash, status(active|inactive|blocked), last_login_at.

## Catalog
- `categories` — name, slug(U), image_url, sort_order, status. Idx (status, sort_order).
- `subcategories` — category_id→categories (RESTRICT), name, slug(U), sort_order, status. Idx (category_id, status, sort_order). Also has `section` (VARCHAR, nullable, groups subcategories into mega-menu columns) — present on the live/admin DB but **not yet added to `sql/ecom.sql`**; confirmed by the user 2026-10-17, not verified against the file.
- `brands` — name, slug(U), logo_url, origin_country, status.
- `products` — category_id→categories, subcategory_id→subcategories, brand_id→brands, size_guide_id→size_guides (all nullable except category_id); name, slug(U), sku(U), short_description, description, price, selling_price, has_variants, variant_scenario, stock_qty, availability(in_stock|out_of_stock|preorder|coming_soon), badge(JSON), preorder_release_date, low_stock_threshold, visibility(visible|hidden), status(draft|active|inactive|archived), seo_title/description. Idx (status, visibility, created_at), (category_id, status), (size_guide_id).
- `product_colors` — product_id (CASCADE), name, hex_code, sort_order, status. U(product_id, name), U(id, product_id).
- `product_sizes` — product_id (CASCADE), size_label, sort_order, status. U(product_id, size_label), U(id, product_id).
- `product_variants` — product_id, color_id/size_id (composite FKs with product_id), sku(U), price_override, selling_price_override, stock_qty, is_active; generated color_key/size_key; U(product_id, color_key, size_key). Idx (product_id, is_active).
- `product_images` — product_id (CASCADE), image_url, alt_text, is_main, sort_order. Idx (product_id, is_main, sort_order).
- `product_image_colors` — PK(image_id, color_id); no rows for an image = general image.
- `product_specifications` — product_id(U, 1:1), material, fabric, dimensions, fit, pattern, care_instructions.
- `size_guides` — title, `key`(U), customer_details, admin_details, status.
- `size_guide_attributes` — size_guide_id (CASCADE), attribute_name, attribute_key, unit, sort_order.
- `size_guide_values` — (size_guide_id, attribute_id)→size_guide_attributes, size_label, measurement_value, sort_order.

## Customers
- `customers` — first_name, last_name, email(U), phone(U), password_hash, status, email_verified_at, phone_verified_at.
- `customer_addresses` — customer_id (CASCADE), label, recipient_name, phone, street_address, suburb, state, postcode, is_default. No created_by/updated_by.
- `customer_verification_codes` — customer_id (CASCADE, nullable), destination, channel(email|sms), purpose, code_hash, expires_at, verified_at, attempts.
- `customer_security_logs` — customer_id (SET NULL), event_type, source, ip_address, user_agent, metadata(JSON).

## Cart / wishlist
- `carts` — customer_id (SET NULL), session_token_hash(U), status(active|converted|abandoned), last_activity_at.
- `cart_items` — cart_id (CASCADE), product_id (RESTRICT), variant_id (RESTRICT, nullable), quantity>0.
- `wishlist_items` — customer_id, product_id (both CASCADE), U(customer_id, product_id).
- `abandoned_cart_logs` — cart_id (CASCADE), customer_id, reminder_status, reminder_sent_at, converted_order_id→orders.

## Orders / payments
- `orders` — order_number(U), customer_id (SET NULL), customer_email/phone, shipping_* snapshot, subtotal, discount_total, shipping_total, tax_total, grand_total, coupon_code, payment_method, payment_status, order_status, fulfillment_type(delivery|pickup), customer_note, admin_note, placed_at, cancelled_at. Idx (order_status, created_at), (payment_status, created_at), (customer_id, created_at).
- `order_items` — order_id (CASCADE), product_id/variant_id (SET NULL), snapshots: product_name, sku, image_url, variant_color, variant_size, unit_price, unit_selling_price, quantity, line_discount, line_total.
- `order_timeline` — order_id (CASCADE), old_status, new_status, note, changed_by_admin_id.
- `payments` — order_id (RESTRICT), provider, provider_transaction_id (U with provider), payment_method, amount, status, paid_at, response_data(JSON).
- `coupons` — code(U), discount_type(percentage|fixed_amount|free_shipping), discount_value, minimum_order_amount, maximum_discount_amount, usage_limit, usage_limit_per_customer, starts_at, expires_at, status(active|inactive|expired).
- `coupon_products` PK(coupon_id, product_id); `coupon_categories` PK(coupon_id, category_id).

## Content / engagement
- `hero_slides` — title, subtitle, image_url, mobile_image_url, button_text/url, sort_order, starts_at, ends_at, status.
- `site_settings` — setting_key(U), setting_value(JSON). Seeded row `currency_code` = NULL.
- `newsletter_subscribers` — email(U), status(subscribed|unsubscribed), source.
- `product_reviews` — product_id (CASCADE), customer_id, order_item_id (SET NULL), rating 1–5, title, body, status(pending|approved|rejected), admin_reply.
- `contact_messages` — name, email, phone, subject, message, status(new|in_progress|resolved|spam), assigned_admin_id.

## B2B requests
- `bulk_order_requests` — request_number(U), customer_id, contact_name, email, phone, company_name, message, status(new|reviewing|quoted|accepted|rejected|completed), assigned_admin_id.
- `bulk_order_request_items` — request_id (CASCADE), product_id (SET NULL), product_name, quantity, target_price, notes.
- `bulk_order_item_variants` — bulk_order_request_item_id (CASCADE), color_name, size_label, quantity.
- `sourcing_requests` — request_number(U), customer_id, customer_name, email, phone, product_url, product_name, requested_quantity, target_budget, details, status (same enum as bulk), assigned_admin_id.
- `sourcing_request_images` — sourcing_request_id (CASCADE), image_url.

## Not in this schema
Laravel's `users`, `cache`, `jobs`, `sessions` tables exist only in the default migrations, not in `sql/ecom.sql`.
