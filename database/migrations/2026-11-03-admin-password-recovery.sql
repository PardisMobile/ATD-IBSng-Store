-- Admin password recovery tokens; apply once to an existing Store database.

CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    request_ip_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_password_reset_token (token_hash),
    KEY idx_admin_password_reset_admin (admin_id, created_at),
    KEY idx_admin_password_reset_ip (request_ip_hash, created_at),
    CONSTRAINT fk_admin_password_reset_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
