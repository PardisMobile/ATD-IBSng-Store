-- Distinguish purchase and renewal orders while preserving legacy purchases.
ALTER TABLE orders
    ADD COLUMN operation ENUM('create','renew') NOT NULL DEFAULT 'create' AFTER username;
