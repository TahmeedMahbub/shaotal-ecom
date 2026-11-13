-- TEST/CI DATABASES ONLY. Never run against the shared database.
-- The Admin app's finalized order contract (identical to shaotal_admin_test): 11 order_status values and the dedicated
-- gift-wrapping columns. Applied after sql/ecom.sql + ci-schema-drift.sql + proposed-schema.sql. Delete once sql/ecom.sql
-- includes them. Not idempotent: run once on a fresh database.
ALTER TABLE orders
  MODIFY COLUMN order_status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned','ready_for_dispatch','in_transit','arrived_at_hub','out_for_delivery') NOT NULL DEFAULT 'pending',
  ADD COLUMN gift_wrap_requested   TINYINT(1)    NOT NULL DEFAULT 0 AFTER customer_note,
  ADD COLUMN gift_wrap_items_count INT UNSIGNED  NOT NULL DEFAULT 0 AFTER gift_wrap_requested,
  ADD COLUMN gift_wrap_fee         DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gift_wrap_items_count,
  ADD COLUMN gift_message          VARCHAR(500)  NULL AFTER gift_wrap_fee;
ALTER TABLE order_items ADD COLUMN gift_wrap_qty INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity;
