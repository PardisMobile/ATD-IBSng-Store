-- Snapshot the storefront language selected when an order is created.
-- Existing orders default to Persian to preserve their historical behavior.
ALTER TABLE orders
    ADD COLUMN language CHAR(2) NOT NULL DEFAULT 'fa' AFTER buyer_email;
