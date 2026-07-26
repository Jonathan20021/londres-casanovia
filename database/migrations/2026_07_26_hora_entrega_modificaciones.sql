-- ==========================================================================
--  LONDRES Casa de Novias — Hora de entrega y piezas por modificar
--  Fecha: 2026-07-26
--
--  Importar con:
--    mysql.exe -u root --default-character-set=utf8mb4 -D londres_casa_novias < database/migrations/2026_07_26_hora_entrega_modificaciones.sql
--
--  Incluye:
--   1. Hora de entrega del alquiler (además de la fecha).
--   2. Piezas marcadas para MODIFICAR (ruedo, cintura…) con su nota y su
--      estado de taller, para llevar el control desde el tablero.
-- ==========================================================================
SET NAMES utf8mb4;

-- 1) Hora de entrega ------------------------------------------------------
ALTER TABLE `rentals`
    ADD COLUMN `delivery_time` TIME NULL AFTER `delivery_date`;

-- 2) Modificaciones por pieza --------------------------------------------
ALTER TABLE `rental_items`
    ADD COLUMN `needs_alteration`   TINYINT(1) NOT NULL DEFAULT 0 AFTER `unit_price`,
    ADD COLUMN `alteration_notes`   TEXT NULL AFTER `needs_alteration`,
    ADD COLUMN `alteration_status`  ENUM('pending','done') NOT NULL DEFAULT 'pending' AFTER `alteration_notes`,
    ADD COLUMN `alteration_done_at` DATETIME NULL AFTER `alteration_status`,
    ADD COLUMN `alteration_done_by` INT UNSIGNED NULL AFTER `alteration_done_at`,
    ADD KEY `idx_ri_alteration` (`needs_alteration`, `alteration_status`),
    ADD CONSTRAINT `fk_ri_alteration_user` FOREIGN KEY (`alteration_done_by`)
        REFERENCES `users`(`id`) ON DELETE SET NULL;
