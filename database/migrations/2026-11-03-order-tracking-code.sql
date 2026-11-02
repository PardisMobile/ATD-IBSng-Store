-- Add human-readable customer order references.
-- Existing records receive an opaque random-looking reference during migration.
ALTER TABLE orders
    ADD COLUMN tracking_code VARCHAR(20) NULL AFTER public_id;

UPDATE orders
SET tracking_code = CONCAT('ATD-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 7)))
WHERE tracking_code IS NULL;

ALTER TABLE orders
    MODIFY COLUMN tracking_code VARCHAR(20) NOT NULL,
    ADD UNIQUE KEY uq_order_tracking_code (tracking_code);
