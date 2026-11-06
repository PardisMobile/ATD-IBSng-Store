-- Product fiat currency is now controlled globally by app_settings.price_display_unit.
-- Keep orders.currency and payment_transactions.currency as immutable payment snapshots.
ALTER TABLE products DROP COLUMN currency;
