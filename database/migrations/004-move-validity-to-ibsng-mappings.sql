-- Move IBSng validity ownership from products to individual IBSng group mappings.
-- Safe for existing installations: copy each product's configured validity to every mapped group first.
-- Existing orders retain their validity_days_snapshot and server_targets_snapshot.

ALTER TABLE ibsng_groups
    ADD COLUMN validity_days INT UNSIGNED NOT NULL DEFAULT 30;

UPDATE ibsng_groups g
JOIN product_server_groups m ON m.ibsng_group_id = g.id
JOIN products p ON p.id = m.product_id
SET g.validity_days = p.ibsng_validity_days;

ALTER TABLE products
    DROP COLUMN ibsng_validity_days;
