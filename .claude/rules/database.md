# Database rules

- Source of truth: `sql/ecom.sql` (MySQL 8), shared with a separate admin app. Never create/edit migrations, never run DDL, never write to the remote DB without explicit approval.
- Verify table/column names against `sql/ecom.sql` (grep the table) before writing queries or models. Don't invent columns.
- `customers` and `admin_users` use `password_hash` (not `password`); override `getAuthPassword()` if used for auth.
- Many tables have `created_by`/`updated_by` → `admin_users`. The storefront leaves these NULL.
- Money is `DECIMAL(12,2)`. Effective price = `COALESCE(variant.price_override, products.price)`; effective selling price = `COALESCE(variant.selling_price_override, products.selling_price, effective_price)`.
- Storefront lists only products with `status='active' AND visibility='visible'`; filter categories/subcategories by `status`, order by `sort_order`.
- Simple product (`has_variants=0`): stock/SKU on `products`. Variant product (`has_variants=1`): `products.stock_qty` is NULL; stock/SKU live on `product_variants`. `variant_scenario` ∈ color_size|color_only|size_only decides which of `color_id`/`size_id` is NULL.
- Exactly one `is_main` image per product (enforce in app). Images with no `product_image_colors` rows apply to all colors.
- Orders: snapshot name, SKU, image, color, size and prices into `order_items`; never hard-delete products/variants referenced by orders (deactivate). Write order + items + payment + timeline in one DB transaction.
- Never store plaintext OTPs, passwords, or payment/card secrets (`code_hash`, `password_hash`).
- `products.badge` and `site_settings.setting_value` are JSON; `site_settings` row `currency_code` is seeded NULL.
- Avoid N+1: join/eager-load, select needed columns, paginate. Index list is in docs/database.md.
