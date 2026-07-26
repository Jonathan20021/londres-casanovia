-- ==========================================================================
--  LONDRES Casa de Novias — Ajustes solicitados por el cliente
--  Fecha: 2026-07-26
--
--  Importar con:
--    mysql.exe -u root --default-character-set=utf8mb4 -D londres_casa_novias < database/migrations/2026_07_26_ajustes_cliente.sql
--
--  Incluye:
--   1. Productos de tipo "Complemento" (corbata, corona, velo…): pueden
--      tener precio 0 y su precio se ajusta al momento de facturar.
--   2. Penalidad por mora fija por día laborable de atraso (RD$500 por
--      defecto) + qué días se consideran laborables.
--   3. Descuento del alquiler expresado en porcentaje.
--   4. Fecha real de devolución (para calcular los días de atraso).
-- ==========================================================================
SET NAMES utf8mb4;

-- 1) Complementos ----------------------------------------------------------
ALTER TABLE `products`
    ADD COLUMN `is_complement` TINYINT(1) NOT NULL DEFAULT 0 AFTER `type`;

-- 2) Mora fija por día laborable ------------------------------------------
ALTER TABLE `business_settings`
    ADD COLUMN `late_fee_per_day` DECIMAL(12,2) NOT NULL DEFAULT 500.00 AFTER `tax_percentage`,
    ADD COLUMN `late_fee_workweek` ENUM('mon_fri','mon_sat') NOT NULL DEFAULT 'mon_fri' AFTER `late_fee_per_day`;

-- 3) y 4) Descuento en porcentaje + fecha real de devolución ---------------
ALTER TABLE `rentals`
    ADD COLUMN `actual_return_date` DATE NULL AFTER `return_date`,
    ADD COLUMN `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount`;

-- Deja coherente el porcentaje de los alquileres ya existentes.
UPDATE `rentals`
   SET `discount_percent` = ROUND(`discount` / `rental_price` * 100, 2)
 WHERE `discount` > 0 AND `rental_price` > 0;

-- Los alquileres ya devueltos toman su fecha de devolución pactada como real.
UPDATE `rentals`
   SET `actual_return_date` = `return_date`
 WHERE `rental_status` = 'returned' AND `actual_return_date` IS NULL;
