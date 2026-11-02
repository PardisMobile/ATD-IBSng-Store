-- Preserve the independent USDT price captured when each order is created.
-- Existing rows remain NULL because their historical USDT price cannot be reconstructed reliably.
ALTER TABLE orders
    ADD COLUMN price_usdt_snapshot DECIMAL(18,8) NULL
    AFTER price_minor_snapshot;
