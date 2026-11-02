-- Optional customer-facing service label for IBSng destinations.
-- Run once on an existing installation after backing up the database.
-- Fresh installations receive this column from database/schema.sql.
ALTER TABLE ibsng_servers
    ADD COLUMN service_name VARCHAR(150) NULL AFTER name;
