-- Upgrade path for an existing ATD IBSng Store database.
CREATE TABLE IF NOT EXISTS ibsng_renewals (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 idempotency_key VARCHAR(190) NOT NULL UNIQUE,
 server_id BIGINT UNSIGNED NOT NULL,
 ibsng_user_id VARCHAR(150) NOT NULL,
 username VARCHAR(150) NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 account_state ENUM('active','expired','unknown') NOT NULL DEFAULT 'unknown',
 validity_days INT UNSIGNED NOT NULL,
 gift_days INT UNSIGNED NOT NULL DEFAULT 0,
 relative_expiration_after INT UNSIGNED NULL,
 status ENUM('pending','completed','failed','needs_review') NOT NULL DEFAULT 'pending',
 result_json JSON NULL,
 error_message TEXT NULL,
 started_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_ibsng_renewal_user_history (server_id, ibsng_user_id, status, created_at),
 KEY idx_ibsng_renewal_review (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
