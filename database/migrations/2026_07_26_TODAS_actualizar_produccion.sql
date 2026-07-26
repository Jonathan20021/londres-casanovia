-- ==========================================================================
--  LONDRES Casa de Novias — ACTUALIZACIÓN COMPLETA DE PRODUCCIÓN
--  Fecha: 2026-07-26
--
--  Reúne las dos migraciones de esta versión en un solo archivo y es
--  IDEMPOTENTE: puede ejecutarse las veces que haga falta sin romper nada.
--  Cada columna se añade SOLO si no existe, así que también repara una
--  actualización aplicada a medias. No usa DELIMITER ni procedimientos:
--  se puede pegar tal cual en phpMyAdmin.
--
--  QUÉ ARREGLA (errores 500 que da el servidor ahora mismo):
--    · Guardar/crear un producto        -> products.is_complement
--    · Nuevo alquiler                    -> products.is_complement
--    · Tablero de alquileres             -> rentals.delivery_time
--    · Ver/editar alquiler y contrato    -> rental_items.needs_alteration
--    · Ver/imprimir factura              -> rentals.delivery_time
--    · Guardar configuración             -> business_settings.late_fee_per_day
--
--  CÓMO EJECUTARLO
--    phpMyAdmin -> base neetjbte_londrescasadenovia -> pestaña SQL ->
--    pegar todo -> Continuar.
--    Al final imprime una tabla: las 11 columnas deben decir OK.
-- ==========================================================================
SET NAMES utf8mb4;

-- ---------- 1. Complementos (corbata, corona, velo…) ----------------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='is_complement'),
    'DO 0', 'ALTER TABLE `products` ADD COLUMN `is_complement` TINYINT(1) NOT NULL DEFAULT 0 AFTER `type`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- 2. Penalidad por mora fija por día laborable ------------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='business_settings' AND COLUMN_NAME='late_fee_per_day'),
    'DO 0', 'ALTER TABLE `business_settings` ADD COLUMN `late_fee_per_day` DECIMAL(12,2) NOT NULL DEFAULT 500.00 AFTER `tax_percentage`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='business_settings' AND COLUMN_NAME='late_fee_workweek'),
    'DO 0', "ALTER TABLE `business_settings` ADD COLUMN `late_fee_workweek` ENUM('mon_fri','mon_sat') NOT NULL DEFAULT 'mon_fri' AFTER `late_fee_per_day`"));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- 3. Descuento en % y fecha real de devolución ------------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rentals' AND COLUMN_NAME='actual_return_date'),
    'DO 0', 'ALTER TABLE `rentals` ADD COLUMN `actual_return_date` DATE NULL AFTER `return_date`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rentals' AND COLUMN_NAME='discount_percent'),
    'DO 0', 'ALTER TABLE `rentals` ADD COLUMN `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- 4. Hora de entrega -------------------------------------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rentals' AND COLUMN_NAME='delivery_time'),
    'DO 0', 'ALTER TABLE `rentals` ADD COLUMN `delivery_time` TIME NULL AFTER `delivery_date`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- 5. Piezas por modificar (ruedo, cintura…) --------------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND COLUMN_NAME='needs_alteration'),
    'DO 0', 'ALTER TABLE `rental_items` ADD COLUMN `needs_alteration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `unit_price`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND COLUMN_NAME='alteration_notes'),
    'DO 0', 'ALTER TABLE `rental_items` ADD COLUMN `alteration_notes` TEXT NULL AFTER `needs_alteration`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND COLUMN_NAME='alteration_status'),
    'DO 0', "ALTER TABLE `rental_items` ADD COLUMN `alteration_status` ENUM('pending','done') NOT NULL DEFAULT 'pending' AFTER `alteration_notes`"));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND COLUMN_NAME='alteration_done_at'),
    'DO 0', 'ALTER TABLE `rental_items` ADD COLUMN `alteration_done_at` DATETIME NULL AFTER `alteration_status`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND COLUMN_NAME='alteration_done_by'),
    'DO 0', 'ALTER TABLE `rental_items` ADD COLUMN `alteration_done_by` INT UNSIGNED NULL AFTER `alteration_done_at`'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Índice y clave foránea de las modificaciones -----------------
SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND INDEX_NAME='idx_ri_alteration'),
    'DO 0', 'ALTER TABLE `rental_items` ADD KEY `idx_ri_alteration` (`needs_alteration`, `alteration_status`)'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rental_items' AND CONSTRAINT_NAME='fk_ri_alteration_user'),
    'DO 0', 'ALTER TABLE `rental_items` ADD CONSTRAINT `fk_ri_alteration_user` FOREIGN KEY (`alteration_done_by`) REFERENCES `users`(`id`) ON DELETE SET NULL'));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------- Datos coherentes en los registros que ya existían ------------
UPDATE `rentals`
   SET `discount_percent` = ROUND(`discount` / `rental_price` * 100, 2)
 WHERE `discount` > 0 AND `rental_price` > 0 AND `discount_percent` = 0;

UPDATE `rentals`
   SET `actual_return_date` = `return_date`
 WHERE `rental_status` = 'returned' AND `actual_return_date` IS NULL;

-- ==========================================================================
--  VERIFICACIÓN — las 11 filas deben decir OK
-- ==========================================================================
SELECT t.tabla, t.columna,
       IF(c.COLUMN_NAME IS NULL, '*** FALTA ***', 'OK') AS estado
  FROM (
        SELECT 'products'          AS tabla, 'is_complement'      AS columna
  UNION SELECT 'business_settings',       'late_fee_per_day'
  UNION SELECT 'business_settings',       'late_fee_workweek'
  UNION SELECT 'rentals',                 'actual_return_date'
  UNION SELECT 'rentals',                 'discount_percent'
  UNION SELECT 'rentals',                 'delivery_time'
  UNION SELECT 'rental_items',            'needs_alteration'
  UNION SELECT 'rental_items',            'alteration_notes'
  UNION SELECT 'rental_items',            'alteration_status'
  UNION SELECT 'rental_items',            'alteration_done_at'
  UNION SELECT 'rental_items',            'alteration_done_by'
       ) t
  LEFT JOIN information_schema.COLUMNS c
         ON c.TABLE_SCHEMA = DATABASE()
        AND c.TABLE_NAME   = t.tabla
        AND c.COLUMN_NAME  = t.columna
 ORDER BY t.tabla, t.columna;
