-- Upgrade existing ATD IBSng Store installations for pricing policy.
-- Run once on the existing database after taking a backup.
-- New installations receive these definitions from database/schema.sql.
ALTER TABLE products
    ADD COLUMN price_usdt DECIMAL(18,8) NULL AFTER price_minor;

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('price_display_unit', 'IRT');
