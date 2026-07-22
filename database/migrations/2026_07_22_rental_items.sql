-- Varios productos por alquiler.
-- Mantiene rentals.product_id como producto principal para compatibilidad
-- y traslada cada alquiler existente a su primera línea de detalle.

CREATE TABLE IF NOT EXISTS `rental_items` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rental_id`  INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rental_items_product` (`rental_id`, `product_id`),
    KEY `idx_rental_items_product` (`product_id`, `rental_id`),
    CONSTRAINT `fk_ri_rental`  FOREIGN KEY (`rental_id`)  REFERENCES `rentals`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_ri_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rental_items` (`rental_id`, `product_id`, `unit_price`, `sort_order`)
SELECT r.id, r.product_id, r.rental_price, 0
FROM `rentals` r
LEFT JOIN `rental_items` ri
       ON ri.rental_id = r.id AND ri.product_id = r.product_id
WHERE ri.id IS NULL;
