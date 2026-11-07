-- Per-server IBSng UTC offset used only to interpret IBSng wall-clock expiry values.
-- NULL intentionally preserves the existing UTC interpretation.
ALTER TABLE ibsng_servers
    ADD COLUMN utc_offset VARCHAR(5) NULL DEFAULT NULL AFTER port;
