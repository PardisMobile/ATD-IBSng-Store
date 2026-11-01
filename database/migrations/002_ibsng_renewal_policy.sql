-- Upgrade existing ATD IBSng Store installations for renewal policy configuration.
-- Apply once after backing up the database. Existing generic duration_seconds
-- values are intentionally NOT copied into ibsng_validity_days.
ALTER TABLE products
    ADD COLUMN ibsng_validity_days INT UNSIGNED NULL AFTER duration_seconds;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
    ('ibsng_gift_days_first', '1'),
    ('ibsng_gift_days_repeat', '5');
