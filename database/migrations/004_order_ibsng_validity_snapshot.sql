-- Preserve the IBSng-specific validity selected when a renewal order is created.
ALTER TABLE orders
    ADD COLUMN ibsng_validity_days_snapshot INT UNSIGNED NULL AFTER duration_seconds_snapshot;
