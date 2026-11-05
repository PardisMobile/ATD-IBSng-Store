-- Unify product validity on IBSng validity days.
-- Prerequisite: database/migrations/002_ibsng_renewal_policy.sql has been applied.
-- This installation has no product/order data; no duration conversion is needed.
ALTER TABLE products
    MODIFY COLUMN ibsng_validity_days INT UNSIGNED NOT NULL,
    DROP COLUMN duration_seconds;

ALTER TABLE orders
    CHANGE COLUMN duration_seconds_snapshot validity_days_snapshot INT UNSIGNED NOT NULL;
