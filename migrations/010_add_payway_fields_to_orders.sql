ALTER TABLE orders
    ADD COLUMN payway_tran_id VARCHAR(20) NULL,
    ADD COLUMN payway_apv VARCHAR(50) NULL,
    ADD COLUMN payment_status ENUM('awaiting', 'paid', 'failed') NOT NULL DEFAULT 'awaiting',
    ADD UNIQUE INDEX orders_payway_tran_id_unique (payway_tran_id);
