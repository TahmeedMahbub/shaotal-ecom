-- TEST/CI DATABASES ONLY. Never run against the shared database.
-- Columns that exist on the live/admin database but are not yet in sql/ecom.sql (see docs/database.md and
-- STOREFRONT_PRODUCTION_READINESS_PLAN.md → I8). Applied after sql/ecom.sql + sql/google_login.sql so the storefront's
-- queries match the real schema. Delete this file once sql/ecom.sql includes the column.
ALTER TABLE subcategories ADD COLUMN section VARCHAR(190) NULL;
