ALTER TABLE products
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD INDEX products_deleted_at_index (deleted_at);
