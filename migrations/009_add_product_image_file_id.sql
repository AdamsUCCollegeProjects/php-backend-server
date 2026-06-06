ALTER TABLE products
    ADD COLUMN image_file_id CHAR(36) NULL,
    ADD CONSTRAINT products_image_file_id_foreign
        FOREIGN KEY (image_file_id) REFERENCES files (id) ON DELETE SET NULL;
