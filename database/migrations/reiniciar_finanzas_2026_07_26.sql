-- ==========================================================================
--  LONDRES Casa de Novias — REINICIAR LAS FINANZAS A CERO
--  Fecha: 2026-07-26
--
--  Borra TODO el movimiento económico de la etapa de pruebas para que el
--  panel arranque en cero. Se comprobó que no hay ni una sola operación real:
--  el movimiento más reciente es del 22/07, anterior a la carga del catálogo.
--
--  QUÉ BORRA
--    · 9 alquileres  (ALQ-00001 … ALQ-00009)
--    · 10 facturas   (FAC-00001 … FAC-00010)
--    · 9 pagos       (REC-00001 … REC-00009)
--    · 1 venta       (VEN-00001)
--    · 4 solicitudes públicas de prueba
--
--  QUÉ NO TOCA
--    · El catálogo de productos (los 99 quedan intactos)
--    · Las categorías, los usuarios y la configuración del negocio
--    · Los clientes  (siguen ahí; si también los quiere fuera, descomente
--      la línea marcada más abajo)
--
--  EFECTO SECUNDARIO QUE SÍ SE CORRIGE
--    5 productos habían quedado marcados como "Reservado" por esos alquileres
--    de prueba. Al desaparecer los alquileres se devuelven a "Disponible".
--    Los productos que usted ya desactivó a mano NO se reactivan.
--
--  CÓMO EJECUTARLO
--    phpMyAdmin -> base neetjbte_londrescasadenovia -> pestaña SQL ->
--    pegar todo -> Continuar. Al final imprime una tabla: debe dar 0 en todo.
-- ==========================================================================
SET NAMES utf8mb4;

START TRANSACTION;

-- El orden respeta las claves foráneas
DELETE FROM `payments`;
DELETE FROM `invoices`;
DELETE FROM `rental_evidence`;
DELETE FROM `rental_items`;
DELETE FROM `rentals`;
DELETE FROM `sales`;
DELETE FROM `rental_requests`;

-- Liberar las piezas que los alquileres de prueba tenían apartadas.
-- Solo las que siguen publicadas: no se reactiva lo que usted desactivó.
UPDATE `products`
   SET `commercial_status` = 'available'
 WHERE `status` = 'active'
   AND `commercial_status` IN ('reserved', 'rented');

-- Si TAMBIÉN quiere borrar los clientes de prueba, quite el guion doble:
-- DELETE FROM `customers`;

COMMIT;

-- La numeración vuelve a empezar: el próximo será ALQ-00001 / FAC-00001.
ALTER TABLE `rentals`          AUTO_INCREMENT = 1;
ALTER TABLE `rental_items`     AUTO_INCREMENT = 1;
ALTER TABLE `rental_evidence`  AUTO_INCREMENT = 1;
ALTER TABLE `invoices`         AUTO_INCREMENT = 1;
ALTER TABLE `payments`         AUTO_INCREMENT = 1;
ALTER TABLE `sales`            AUTO_INCREMENT = 1;
ALTER TABLE `rental_requests`  AUTO_INCREMENT = 1;

-- ==========================================================================
--  VERIFICACIÓN — todo debe quedar en 0 salvo los productos
-- ==========================================================================
SELECT 'Ingresos (suma de pagos)'       AS concepto, COALESCE(SUM(`amount`), 0)            AS valor FROM `payments`
UNION ALL SELECT 'Por cobrar (saldos)',            COALESCE(SUM(`remaining_balance`), 0)   FROM `rentals`
UNION ALL SELECT 'Alquileres',                     COUNT(*)                                FROM `rentals`
UNION ALL SELECT 'Facturas',                       COUNT(*)                                FROM `invoices`
UNION ALL SELECT 'Pagos',                          COUNT(*)                                FROM `payments`
UNION ALL SELECT 'Ventas',                         COUNT(*)                                FROM `sales`
UNION ALL SELECT 'Solicitudes',                    COUNT(*)                                FROM `rental_requests`
UNION ALL SELECT 'Productos (deben seguir)',       COUNT(*)                                FROM `products`
UNION ALL SELECT 'Productos apartados (debe ser 0)', COUNT(*)                              FROM `products`
         WHERE `status` = 'active' AND `commercial_status` IN ('reserved', 'rented');
