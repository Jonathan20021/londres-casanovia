-- ==========================================================================
--  LONDRES Casa de Novias — Limpieza de datos de prueba y demo
--  Fecha: 2026-07-26
--
--  DEJA SOLO: los 83 vestidos reales (productos #12–#94) con sus fotos,
--             las 6 categorías, los 5 usuarios y la configuración del negocio.
--
--  BORRA:     los 8 productos demo de la instalación (#1–#8: Vestido Aurora,
--             Isabella, Traje Londres Classic, Gala Royal, Velo Premium,
--             Corona Cristal, Gala Esmeralda, Ramo de Lujo),
--             los 3 productos de prueba (#9 traje azul, #10 camisa s,
--             #11 gfhthsgfh), los 7 clientes (4 demo + prueba/jean santos/
--             Jean Luis s), los 9 alquileres, las 10 facturas, los 9 pagos,
--             la venta VEN-00001, las 4 solicitudes y el historial.
--
--  ANTES DE EJECUTAR: hay un respaldo completo de 590 filas. Si algo sale
--  mal, restaure ese archivo.
--
--  Cómo ejecutarlo:
--    · phpMyAdmin → base neetjbte_londrescasadenovia → pestaña SQL → pegar → Continuar
--    · o por consola:
--      mysql -h 129.121.81.172 -u neetjbte_londres -p -D neetjbte_londrescasadenovia \
--            --default-character-set=utf8mb4 < limpieza_datos_prueba_2026_07_26.sql
-- ==========================================================================
SET NAMES utf8mb4;

START TRANSACTION;

-- El orden respeta las claves foráneas: primero lo que depende de otros.
DELETE FROM `payments`;
DELETE FROM `invoices`;
DELETE FROM `rental_evidence`;
DELETE FROM `rental_items`;
DELETE FROM `rentals`;
DELETE FROM `sales`;
DELETE FROM `rental_requests`;

-- Fotos y fichas de los 11 productos demo/prueba (el catálogo real es id >= 12)
DELETE FROM `product_images` WHERE `product_id` <= 11;
DELETE FROM `products`       WHERE `id` <= 11;

-- Clientes demo y de prueba (no queda ninguno: los reales se registrarán al operar)
DELETE FROM `customers`;

-- Historial de la etapa de pruebas
DELETE FROM `notifications`;
DELETE FROM `activity_logs`;

COMMIT;

-- Reinicia la numeración: el próximo alquiler será ALQ-00001 y la próxima
-- factura FAC-00001.
ALTER TABLE `customers`        AUTO_INCREMENT = 1;
ALTER TABLE `rentals`          AUTO_INCREMENT = 1;
ALTER TABLE `rental_items`     AUTO_INCREMENT = 1;
ALTER TABLE `invoices`         AUTO_INCREMENT = 1;
ALTER TABLE `payments`         AUTO_INCREMENT = 1;
ALTER TABLE `sales`            AUTO_INCREMENT = 1;
ALTER TABLE `rental_requests`  AUTO_INCREMENT = 1;
ALTER TABLE `activity_logs`    AUTO_INCREMENT = 1;
ALTER TABLE `notifications`    AUTO_INCREMENT = 1;

-- ==========================================================================
--  COMPROBACIÓN (debe devolver 83 productos y 0 en todo lo demás)
-- ==========================================================================
SELECT 'productos (deben ser 83)' AS concepto, COUNT(*) AS total FROM `products`
UNION ALL SELECT 'clientes (0)',      COUNT(*) FROM `customers`
UNION ALL SELECT 'alquileres (0)',    COUNT(*) FROM `rentals`
UNION ALL SELECT 'facturas (0)',      COUNT(*) FROM `invoices`
UNION ALL SELECT 'pagos (0)',         COUNT(*) FROM `payments`
UNION ALL SELECT 'ventas (0)',        COUNT(*) FROM `sales`
UNION ALL SELECT 'solicitudes (0)',   COUNT(*) FROM `rental_requests`
UNION ALL SELECT 'categorias (6)',    COUNT(*) FROM `categories`
UNION ALL SELECT 'usuarios (5)',      COUNT(*) FROM `users`;
