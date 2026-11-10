<?php
declare(strict_types=1);

/**
 * Detects and applies additive schema updates introduced after the original
 * installation. The IBSng renewal upgrade remains a separate operation.
 */
final class DatabaseSchemaSync
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT is_nullable FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        return strtoupper((string)$stmt->fetchColumn()) === 'YES';
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** @return list<string> */
    public function pendingChanges(): array
    {
        $pending = [];

        if ($this->tableExists('ibsng_servers') && !$this->columnExists('ibsng_servers', 'service_name')) {
            $pending[] = 'افزودن عنوان نمایشی سرویس به سرورهای IBSng';
        }
        if ($this->tableExists('ibsng_servers') && !$this->columnExists('ibsng_servers', 'utc_offset')) {
            $pending[] = 'افزودن اختلاف ساعت UTC برای هر سرور IBSng';
        }
        if ($this->tableExists('products') && !$this->columnExists('products', 'price_usdt')) {
            $pending[] = 'افزودن قیمت مستقل USDT به محصولات';
        }
        if ($this->tableExists('orders') && !$this->columnExists('orders', 'server_targets_snapshot')) {
            $pending[] = 'افزودن تصویر ثابت مقصدهای IBSng به سفارش‌ها';
        }
        if ($this->tableExists('orders') && !$this->columnExists('orders', 'price_usdt_snapshot')) {
            $pending[] = 'افزودن تصویر ثابت قیمت USDT به سفارش‌ها';
        }
        if ($this->tableExists('orders') && !$this->columnExists('orders', 'language')) {
            $pending[] = 'افزودن زبان انتخاب‌شده به سفارش‌ها برای ارسال ایمیل';
        }
        if ($this->tableExists('orders') && !$this->columnExists('orders', 'tracking_code')) {
            $pending[] = 'افزودن کد پیگیری یکتای سفارش‌ها';
        } elseif ($this->tableExists('orders')) {
            $missingTrackingValues = (int)$this->pdo->query('SELECT COUNT(*) FROM orders WHERE tracking_code IS NULL OR tracking_code = ""')->fetchColumn();
            if ($this->columnIsNullable('orders', 'tracking_code') || $missingTrackingValues > 0) {
                $pending[] = 'تکمیل مقادیر کد پیگیری سفارش‌ها';
            }
            if (!$this->indexExists('orders', 'uq_order_tracking_code')) {
                $pending[] = 'تکمیل شاخص یکتای کد پیگیری سفارش‌ها';
            }
        }
        if ($this->tableExists('provisioning_attempts')) {
            $status = $this->columnType('provisioning_attempts', 'status');
            if ($status !== null && strpos($status, 'needs_review') === false) {
                $pending[] = 'افزودن وضعیت نیازمند بررسی به عملیات فعال‌سازی';
            }
        }
        if (!$this->tableExists('customer_email_deliveries') && $this->tableExists('orders')) {
            $pending[] = 'ایجاد جدول ثبت تحویل ایمیل حساب مشتری';
        }
        if ((!$this->tableExists('openvpn_profiles') || !$this->tableExists('openvpn_links')) && $this->tableExists('orders')) {
            $pending[] = 'ایجاد جداول مدیریت پروفایل و لینک OpenVPN';
        }

        if (!$this->tableExists('customer_order_recovery_codes')) {
            $pending[] = 'ایجاد جدول کدهای بازیابی سفارش مشتری';
        }

        if ($this->tableExists('app_settings')) {
            $stmt = $this->pdo->query("SELECT setting_key FROM app_settings WHERE setting_key IN ('price_display_unit','crypto_gateway_enabled')");
            $existing = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
            if (!isset($existing['price_display_unit']) || !isset($existing['crypto_gateway_enabled'])) {
                $pending[] = 'تکمیل تنظیمات سیاست قیمت‌گذاری';
            }
        }

        return $pending;
    }

    private function columnType(string $table, string $column): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function apply(): void
    {
        foreach (['ibsng_servers', 'products', 'orders', 'app_settings', 'provisioning_attempts'] as $required) {
            if (!$this->tableExists($required)) {
                throw new RuntimeException('جدول پایهٔ ' . $required . ' در دیتابیس وجود ندارد؛ همگام‌سازی خودکار متوقف شد.');
            }
        }

        if (!$this->columnExists('ibsng_servers', 'service_name')) {
            if (!$this->columnExists('ibsng_servers', 'name')) throw new RuntimeException('ستون ibsng_servers.name وجود ندارد.');
            $this->pdo->exec('ALTER TABLE ibsng_servers ADD COLUMN service_name VARCHAR(150) NULL AFTER name');
        }
        if (!$this->columnExists('ibsng_servers', 'utc_offset')) {
            $this->pdo->exec('ALTER TABLE ibsng_servers ADD COLUMN utc_offset VARCHAR(5) NULL DEFAULT NULL AFTER port');
        }
        if (!$this->columnExists('products', 'price_usdt')) {
            if (!$this->columnExists('products', 'price_minor')) throw new RuntimeException('ستون products.price_minor وجود ندارد.');
            $this->pdo->exec('ALTER TABLE products ADD COLUMN price_usdt DECIMAL(18,8) NULL AFTER price_minor');
        }
        if (!$this->columnExists('orders', 'server_targets_snapshot')) {
            $this->pdo->exec('ALTER TABLE orders ADD COLUMN server_targets_snapshot JSON NULL');
        }
        if (!$this->columnExists('orders', 'price_usdt_snapshot')) {
            if (!$this->columnExists('orders', 'price_minor_snapshot')) throw new RuntimeException('ستون orders.price_minor_snapshot وجود ندارد.');
            $this->pdo->exec('ALTER TABLE orders ADD COLUMN price_usdt_snapshot DECIMAL(18,8) NULL AFTER price_minor_snapshot');
        }
        if (!$this->columnExists('orders', 'language')) {
            $this->pdo->exec("ALTER TABLE orders ADD COLUMN language CHAR(2) NOT NULL DEFAULT 'fa' AFTER buyer_email");
        }
        if (!$this->columnExists('orders', 'tracking_code')) {
            if (!$this->columnExists('orders', 'public_id')) throw new RuntimeException('ستون orders.public_id وجود ندارد.');
            $this->pdo->exec('ALTER TABLE orders ADD COLUMN tracking_code VARCHAR(20) NULL AFTER public_id');
        }
        $this->fillMissingTrackingCodes();
        if (!$this->indexExists('orders', 'uq_order_tracking_code')) {
            $duplicates = (int)$this->pdo->query(
                'SELECT COUNT(*) FROM (SELECT tracking_code FROM orders GROUP BY tracking_code HAVING COUNT(*) > 1) duplicate_codes'
            )->fetchColumn();
            if ($duplicates > 0) throw new RuntimeException('کدهای پیگیری تکراری وجود دارند؛ ایجاد شاخص یکتا متوقف شد.');
            $this->pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_order_tracking_code (tracking_code)');
        }
        $this->ensureProvisioningReviewStatus();
        $this->ensureCustomerEmailDeliveries();
        $this->ensureCustomerRecoveryCodes();
        $this->ensureOpenVpnTables();
        $settings = $this->pdo->prepare('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)');
        $settings->execute(['price_display_unit', 'IRT']);
        $settings->execute(['crypto_gateway_enabled', '0']);

        $pending = $this->pendingChanges();
        if ($pending !== []) {
            throw new RuntimeException('بررسی پس از همگام‌سازی هنوز این موارد را ناقص تشخیص می‌دهد: ' . implode('، ', $pending));
        }
    }

    private function fillMissingTrackingCodes(): void
    {
        if (!$this->columnExists('orders', 'tracking_code')) return;
        $stmt = $this->pdo->query('SELECT id FROM orders WHERE tracking_code IS NULL OR tracking_code = ""');
        $update = $this->pdo->prepare('UPDATE orders SET tracking_code = ? WHERE id = ?');
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $code = 'ATD-' . strtoupper(bin2hex(random_bytes(8)));
            $update->execute([$code, $id]);
        }
        $this->pdo->exec('ALTER TABLE orders MODIFY COLUMN tracking_code VARCHAR(20) NOT NULL');
    }

    private function ensureProvisioningReviewStatus(): void
    {
        $type = $this->columnType('provisioning_attempts', 'status');
        if ($type === null || strpos($type, 'needs_review') !== false) return;
        $this->pdo->exec(
            "ALTER TABLE provisioning_attempts MODIFY COLUMN status ENUM('queued','running','succeeded','failed','skipped','needs_review') NOT NULL DEFAULT 'queued'"
        );
    }

    private function ensureCustomerEmailDeliveries(): void
    {
        if ($this->tableExists('customer_email_deliveries')) return;
        $this->pdo->exec(
            "CREATE TABLE customer_email_deliveries (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }


    private function ensureOpenVpnTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS openvpn_profiles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                profile_ciphertext MEDIUMTEXT NOT NULL,
                profile_sha256 CHAR(64) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_openvpn_profile_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS openvpn_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                profile_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NULL,
                link_type ENUM('public','personalized') NOT NULL,
                recipient_email VARCHAR(190) NULL,
                token_hash CHAR(64) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                expires_at DATETIME NULL,
                last_used_at DATETIME NULL,
                download_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_openvpn_link_token (token_hash),
                KEY idx_openvpn_link_profile (profile_id, is_active),
                KEY idx_openvpn_link_order (order_id, created_at),
                CONSTRAINT fk_openvpn_link_profile FOREIGN KEY (profile_id) REFERENCES openvpn_profiles(id) ON DELETE CASCADE,
                CONSTRAINT fk_openvpn_link_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureCustomerRecoveryCodes(): void
    {
        if ($this->tableExists('customer_order_recovery_codes')) return;
        $this->pdo->exec(
            "CREATE TABLE customer_order_recovery_codes (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
