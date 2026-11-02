-- Durable idempotency record for account delivery after provisioning.
CREATE TABLE IF NOT EXISTS customer_email_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(80) NOT NULL,
    status ENUM('sending','sent','failed') NOT NULL DEFAULT 'sending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_customer_email_delivery (order_id, event_key),
    KEY idx_customer_email_delivery_status (status, updated_at),
    CONSTRAINT fk_customer_email_delivery_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
