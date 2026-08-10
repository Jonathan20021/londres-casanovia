-- ==========================================================================
--  LONDRES Casa de Novias — Talla por UNIDAD
--  Fecha: 2026-08-10
--
--  Del mismo traje (mismo color, mismo diseño) suele haber varias tallas.
--  Con esto, al poner "Cantidad en stock = 5" se pueden registrar las 5 tallas,
--  cada una con su propio código de barras.
--
--  products.size pasa a ser el RESUMEN de las tallas de las unidades
--  ("S · M · L"), por eso se amplía la columna.
--
--  Requiere haber ejecutado antes 2026_08_03_product_units.sql.
--
--  Importar con:
--    mysql.exe -u root --default-character-set=utf8mb4 -D londres_casa_novias < database/migrations/2026_08_10_unit_sizes.sql
-- ==========================================================================
SET NAMES utf8mb4;

-- Talla de cada unidad (idempotente: sólo añade la columna si falta).
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_units' AND COLUMN_NAME = 'size') = 0,
    'ALTER TABLE `product_units` ADD COLUMN `size` VARCHAR(40) NULL AFTER `unit_number`, ADD KEY `idx_pu_size` (`size`)',
    'SELECT ''product_units.size ya existe'' AS aviso'
);
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- El resumen de tallas del producto necesita más espacio que una sola talla.
ALTER TABLE `products` MODIFY `size` VARCHAR(120) NULL;

-- Las unidades ya creadas heredan la talla que tuviera el producto.
UPDATE `product_units` u
JOIN `products` p ON p.`id` = u.`product_id`
SET u.`size` = p.`size`
WHERE (u.`size` IS NULL OR u.`size` = '')
  AND p.`size` IS NOT NULL AND p.`size` <> ''
  AND CHAR_LENGTH(p.`size`) <= 40;
