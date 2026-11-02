-- One-time email ownership challenges for the unified customer order recovery flow.
-- Store only a keyed/hash representation of the verification code, never the raw code.
-- The application must enforce expiry, single-use, attempt limits and per-email/IP throttling.
CREATE TABLE IF NOT EXISTS customer_order_recovery_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email_normalized VARCHAR(190) NOT NULL,
    code_hash CHAR(255) NOT NULL,
    request_ip_hash CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_recovery_email_created (email_normalized, created_at),
    KEY idx_customer_recovery_expiry (expires_at),
    KEY idx_customer_recovery_email_hash (email_normalized, code_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
