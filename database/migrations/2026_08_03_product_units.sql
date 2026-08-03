-- ==========================================================================
--  LONDRES Casa de Novias — Un código de barras por UNIDAD física
--  Fecha: 2026-08-03
--
--  Hasta ahora cada producto tenía un solo código. Con esta tabla, un producto
--  con "Cantidad en stock" = 10 genera 10 unidades (10 etiquetas distintas),
--  para poder pegarle una a cada traje/vestido y saber cuál se escaneó.
--
--  Formato del código de unidad:  PREFIJO + id producto (6 díg.) + U + nº unidad
--  Ej.: producto 42, unidad 3  ->  LCN000042U03
--
--  Importar con:
--    mysql.exe -u root --default-character-set=utf8mb4 -D londres_casa_novias < database/migrations/2026_08_03_product_units.sql
--
--  Tras importar, entre a Admin → Inventario → Códigos de barra: el sistema
--  crea automáticamente las unidades que falten según la cantidad en stock.
-- ==========================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `product_units` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED NOT NULL,
    `unit_number` INT UNSIGNED NOT NULL,
    `barcode`     VARCHAR(56) NOT NULL,
    `status`      ENUM('available','reserved','rented','sold','maintenance','unavailable','retired') NOT NULL DEFAULT 'available',
    `notes`       VARCHAR(255) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pu_product_number` (`product_id`, `unit_number`),
    UNIQUE KEY `uq_pu_barcode` (`barcode`),
    KEY `idx_pu_product` (`product_id`),
    CONSTRAINT `fk_pu_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primera unidad de cada producto existente (el resto las completa el módulo
-- de Códigos de barra, que ya conoce el prefijo configurado del negocio).
INSERT INTO `product_units` (`product_id`, `unit_number`, `barcode`)
SELECT p.`id`, 1,
       CONCAT(
           COALESCE((SELECT NULLIF(bs.`barcode_prefix`, '') FROM `business_settings` bs LIMIT 1), 'LCN'),
           LPAD(p.`id`, 6, '0'), 'U01'
       )
FROM `products` p
WHERE p.`quantity` >= 1
  AND NOT EXISTS (SELECT 1 FROM `product_units` u WHERE u.`product_id` = p.`id` AND u.`unit_number` = 1);
