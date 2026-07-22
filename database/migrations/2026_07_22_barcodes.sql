-- ==========================================================================
--  LONDRES Casa de Novias — Códigos de barras por producto
--  Fecha: 2026-07-22
--
--  Importar con:
--    mysql.exe -u root --default-character-set=utf8mb4 -D londres_casa_novias < database/migrations/2026_07_22_barcodes.sql
--
--  Tras importar, entre a Admin → Inventario → Códigos de barra: el sistema
--  asigna automáticamente el código a todos los productos existentes.
-- ==========================================================================
SET NAMES utf8mb4;

ALTER TABLE `products`
    ADD COLUMN `barcode` VARCHAR(48) NULL AFTER `sku`,
    ADD UNIQUE KEY `uq_products_barcode` (`barcode`);

ALTER TABLE `business_settings`
    ADD COLUMN `barcode_prefix` VARCHAR(6) NOT NULL DEFAULT 'LCN' AFTER `invoice_prefix`;

-- Códigos para los productos ya existentes (PREFIJO + id a 6 dígitos).
UPDATE `products`
SET `barcode` = CONCAT('LCN', LPAD(`id`, 6, '0'))
WHERE `barcode` IS NULL OR `barcode` = '';
