-- ATD IBSng Store initial schema.
-- Installer executes this file only after a successful database connection.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
    ('ibsng_gift_days_first', '1'),
    ('ibsng_gift_days_repeat', '5'),
    ('price_display_unit', 'IRT'),
    ('crypto_gateway_enabled', '0');

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ibsng_servers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port SMALLINT UNSIGNED NOT NULL DEFAULT 80,
    scheme ENUM('http','https') NOT NULL DEFAULT 'http',
    isp_name VARCHAR(120) NOT NULL DEFAULT 'Main',
    username VARCHAR(150) NOT NULL,
    password_ciphertext TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    last_test_at DATETIME NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ibsng_server_active (is_active),
    KEY idx_ibsng_server_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ibsng_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT UNSIGNED NOT NULL,
    plan_name VARCHAR(150) NOT NULL,
    group_name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_server_plan (server_id, plan_name),
    UNIQUE KEY uq_server_group (server_id, group_name),
    CONSTRAINT fk_ibsng_groups_server FOREIGN KEY (server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    duration_seconds BIGINT UNSIGNED NOT NULL,
    -- Explicit IBSng renewal validity; never inferred from generic duration_seconds.
    ibsng_validity_days INT UNSIGNED NULL,
    price_minor BIGINT UNSIGNED NOT NULL,
    -- Independent product price for crypto settlement; never FX-derived.
    price_usdt DECIMAL(18,8) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    quota_bytes BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_products_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_server_groups (
    product_id BIGINT UNSIGNED NOT NULL,
    server_id BIGINT UNSIGNED NOT NULL,
    ibsng_group_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, server_id),
    CONSTRAINT fk_psg_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_psg_server FOREIGN KEY (server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE,
    CONSTRAINT fk_psg_group FOREIGN KEY (ibsng_group_id) REFERENCES ibsng_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(32) NOT NULL,
    buyer_name VARCHAR(150) NOT NULL DEFAULT '',
    buyer_phone VARCHAR(40) NOT NULL DEFAULT '',
    buyer_email VARCHAR(190) NOT NULL DEFAULT '',
    product_id BIGINT UNSIGNED NULL,
    product_name_snapshot VARCHAR(180) NOT NULL,
    duration_seconds_snapshot BIGINT UNSIGNED NOT NULL,
    price_minor_snapshot BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    username VARCHAR(150) NOT NULL,
    password_ciphertext TEXT NULL,
    server_policy ENUM('selected','default','configured_set') NOT NULL DEFAULT 'default',
    payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    order_status ENUM('pending_payment','paid','provisioning','active','partial','failed','cancelled') NOT NULL DEFAULT 'pending_payment',
    idempotency_key VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_public_id (public_id),
    UNIQUE KEY uq_order_idempotency (idempotency_key),
    KEY idx_order_status_created (order_status, created_at),
    CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(80) NOT NULL,
    provider_reference VARCHAR(190) NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'IRR',
    status ENUM('initiated','pending','verified','failed','refunded') NOT NULL DEFAULT 'initiated',
    callback_id VARCHAR(190) NULL,
    verified_at DATETIME NULL,
    raw_result_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_provider_reference (provider, provider_reference),
    UNIQUE KEY uq_payment_callback (provider, callback_id),
    KEY idx_payment_order (order_id),
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NULL,
    server_id BIGINT UNSIGNED NOT NULL,
    ibsng_user_id VARCHAR(150) NULL,
    username VARCHAR(150) NOT NULL,
    status ENUM('pending','active','expired','suspended','failed','unknown') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NULL,
    quota_limit_bytes BIGINT UNSIGNED NULL,
    quota_used_bytes BIGINT UNSIGNED NULL,
    last_synced_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_server_username (server_id, username),
    UNIQUE KEY uq_server_ibsng_id (server_id, ibsng_user_id),
    KEY idx_subscription_order (order_id),
    KEY idx_subscription_status_expiry (status, expires_at),
    CONSTRAINT fk_subscription_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_subscription_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_subscription_server FOREIGN KEY (server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provisioning_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    server_id BIGINT UNSIGNED NOT NULL,
    operation ENUM('create','renew','change_group','delete','sync') NOT NULL,
    status ENUM('queued','running','succeeded','failed','skipped') NOT NULL DEFAULT 'queued',
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    idempotency_key VARCHAR(190) NOT NULL,
    remote_user_id VARCHAR(150) NULL,
    error_code VARCHAR(100) NULL,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provision_idempotency (idempotency_key),
    KEY idx_provision_order_server (order_id, server_id),
    KEY idx_provision_status (status),
    CONSTRAINT fk_provision_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_provision_server FOREIGN KEY (server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_reset_token_hash (token_hash),
    KEY idx_admin_reset_user_requested (admin_user_id, requested_at),
    KEY idx_admin_reset_expiry (expires_at),
    CONSTRAINT fk_admin_reset_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- IBSng renewal operation ledger. The unique idempotency key prevents the
-- same requested renewal from issuing a second remote mutation.
CREATE TABLE IF NOT EXISTS ibsng_renewals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idempotency_key VARCHAR(190) NOT NULL,
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
    UNIQUE KEY uq_ibsng_renewal_idempotency (idempotency_key),
    KEY idx_ibsng_renewal_user_history (server_id, ibsng_user_id, status, created_at),
    KEY idx_ibsng_renewal_review (status, updated_at),
    CONSTRAINT fk_ibsng_renewal_server FOREIGN KEY (server_id) REFERENCES ibsng_servers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ibsng_renewal_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
