-- Persist immutable IBSng provisioning destinations on each order.
-- Run once on an existing installation after backing up the database.
-- Fresh installations receive this column from database/schema.sql.
ALTER TABLE orders
    ADD COLUMN server_targets_snapshot JSON NULL;
