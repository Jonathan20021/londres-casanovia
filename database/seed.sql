-- ============================================================
--  LONDRES Casa de Novias — Datos de prueba (seed)
--  Usuario demo:  admin@londresnovias.com  /  Admin12345
-- ============================================================
USE `londres_casa_novias`;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `activity_logs`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `invoices`;
TRUNCATE TABLE `rental_evidence`;
TRUNCATE TABLE `rentals`;
TRUNCATE TABLE `rental_requests`;
TRUNCATE TABLE `sales`;
TRUNCATE TABLE `product_images`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `business_settings`;
TRUNCATE TABLE `role_permissions`;
TRUNCATE TABLE `permissions`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- ROLES ----------
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin',   'Acceso total al sistema'),
(2, 'Administrador', 'Gestión completa excepto configuración crítica'),
(3, 'Cajera',        'Facturas, pagos, clientes y alquileres'),
(4, 'Vendedora',     'Clientes, solicitudes y alquileres; productos en lectura'),
(5, 'Inventario',    'Productos, imágenes, disponibilidad y estados físicos');

-- ---------- PERMISOS ----------
INSERT INTO `permissions` (`name`, `description`) VALUES
('*',                 'Comodín total'),
('products.view',     'Ver productos'),
('products.manage',   'Gestionar productos e inventario'),
('categories.manage', 'Gestionar categorías'),
('customers.manage',  'Gestionar clientes'),
('rentals.manage',    'Gestionar alquileres'),
('requests.manage',   'Gestionar solicitudes de alquiler'),
('invoices.manage',   'Gestionar facturas'),
('payments.manage',   'Gestionar pagos'),
('sales.manage',      'Gestionar ventas'),
('reports.view',      'Ver reportes'),
('calendar.view',     'Ver calendario'),
('users.manage',      'Gestionar usuarios y roles'),
('settings.manage',   'Configuración del negocio');

-- ---------- ROLE_PERMISSIONS ----------
-- Super Admin: comodín
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM permissions p WHERE p.name = '*';

-- Administrador: todo excepto configuración crítica (settings.manage)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, p.id FROM permissions p
WHERE p.name IN ('products.view','products.manage','categories.manage','customers.manage',
                 'rentals.manage','requests.manage','invoices.manage','payments.manage',
                 'sales.manage','reports.view','calendar.view','users.manage');

-- Cajera
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, p.id FROM permissions p
WHERE p.name IN ('customers.manage','rentals.manage','requests.manage','invoices.manage',
                 'payments.manage','products.view','calendar.view');

-- Vendedora
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, p.id FROM permissions p
WHERE p.name IN ('customers.manage','requests.manage','rentals.manage','products.view','calendar.view');

-- Inventario
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, p.id FROM permissions p
WHERE p.name IN ('products.view','products.manage','categories.manage','calendar.view');

-- ---------- USUARIOS ----------
-- Contraseña en texto plano para TODOS los usuarios demo: Admin12345
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role_id`, `status`) VALUES
(1, 'Administrador Londres', 'admin@londresnovias.com',     '$2y$10$OZGgxGfdn8.mgLz98f8D1eZBJmviimh8Sj5Ih9iNFq/QECSlzp.py', 1, 'active'),
(2, 'María Gerente',         'gerente@londresnovias.com',   '$2y$10$OZGgxGfdn8.mgLz98f8D1eZBJmviimh8Sj5Ih9iNFq/QECSlzp.py', 2, 'active'),
(3, 'Lucía Cajera',          'caja@londresnovias.com',      '$2y$10$OZGgxGfdn8.mgLz98f8D1eZBJmviimh8Sj5Ih9iNFq/QECSlzp.py', 3, 'active'),
(4, 'Sofía Vendedora',       'ventas@londresnovias.com',    '$2y$10$OZGgxGfdn8.mgLz98f8D1eZBJmviimh8Sj5Ih9iNFq/QECSlzp.py', 4, 'active'),
(5, 'Pedro Inventario',      'inventario@londresnovias.com','$2y$10$OZGgxGfdn8.mgLz98f8D1eZBJmviimh8Sj5Ih9iNFq/QECSlzp.py', 5, 'active');

-- ---------- CONFIGURACIÓN DEL NEGOCIO ----------
INSERT INTO `business_settings`
(`id`,`business_name`,`logo`,`phone`,`whatsapp`,`email`,`address`,`instagram`,`opening_hours`,
 `rental_policy`,`return_policy`,`initial_payment_percentage`,`currency`,`tax_percentage`,`invoice_prefix`,`primary_color`)
VALUES
(1,'LONDRES Casa de Novias', NULL, '+1 809 555 0123', '18095550123', 'info@londresnovias.com',
 'Av. Principal #45, Santo Domingo, R.D.', 'londrescasadenovias',
 'Lun a Sáb · 9:00 AM – 7:00 PM',
 'Para reservar se requiere un depósito inicial del 50% del valor del alquiler. El saldo restante se paga al momento de retirar la pieza. La reserva se confirma únicamente tras el pago inicial.',
 'La pieza debe devolverse en la fecha acordada y en las mismas condiciones de entrega. El retraso en la devolución genera una penalidad diaria. Cualquier daño será evaluado y cobrado según corresponda.',
 50.00, 'RD$', 0.00, 'FAC', '#C8102E');

-- ---------- CATEGORÍAS ----------
INSERT INTO `categories` (`id`,`name`,`slug`,`description`,`status`) VALUES
(1,'Vestidos de Novia','vestidos-de-novia','Vestidos de novia exclusivos para tu gran día','active'),
(2,'Vestidos de Gala','vestidos-de-gala','Vestidos elegantes para eventos y galas','active'),
(3,'Trajes','trajes','Trajes y esmóquines para caballeros','active'),
(4,'Accesorios','accesorios','Accesorios y complementos para novias','active'),
(5,'Velos','velos','Velos y mantillas premium','active'),
(6,'Coronas','coronas','Coronas, tiaras y tocados','active');

-- ---------- PRODUCTOS ----------
INSERT INTO `products`
(`id`,`category_id`,`name`,`slug`,`sku`,`type`,`description`,`internal_notes`,`rental_price`,`sale_price`,`cost_price`,`deposit_amount`,
 `size`,`color`,`material`,`condition_status`,`commercial_status`,`is_unique`,`quantity`,`main_image`,`featured`,`status`,`created_by`)
VALUES
(1,1,'Vestido Aurora','vestido-aurora','VN-001','both','Vestido de novia corte sirena con encaje francés y cola catedral. Elegancia atemporal para una novia inolvidable.','Pieza estrella de la colección.',12000.00,85000.00,40000.00,6000.00,'M','Blanco','Encaje y tul','excellent','reserved',1,1,NULL,1,'active',1),
(2,1,'Vestido Isabella','vestido-isabella','VN-002','rental','Vestido de novia princesa con falda voluminosa, pedrería y escote corazón. Romántico y majestuoso.','',10500.00,NULL,35000.00,5250.00,'S','Marfil','Tul y pedrería','excellent','available',1,1,NULL,1,'active',1),
(3,3,'Traje Londres Classic','traje-londres-classic','TR-001','both','Esmóquin negro entallado de corte inglés, solapa de raso. El complemento perfecto para el novio.','',6000.00,28000.00,12000.00,3000.00,'L','Negro','Lana fría','excellent','available',1,1,NULL,1,'active',1),
(4,2,'Vestido Gala Royal','vestido-gala-royal','VG-001','rental','Vestido de gala rojo con abertura lateral y drapeado sofisticado. Ideal para eventos de alfombra roja.','',8000.00,NULL,18000.00,4000.00,'M','Rojo','Satén','excellent','rented',1,1,NULL,1,'active',1),
(5,5,'Velo Premium','velo-premium','VE-001','both','Velo catedral de 3 metros con borde bordado a mano. Caída impecable.','',2500.00,9000.00,4000.00,1250.00,'Único','Marfil','Tul ilusión','new','available',1,1,NULL,0,'active',1),
(6,6,'Corona Cristal','corona-cristal','CO-001','both','Tiara con cristales Swarovski y baño de plata. Brillo deslumbrante.','',1800.00,7500.00,3000.00,900.00,'Único','Plateado','Cristal y aleación','new','available',1,1,NULL,0,'active',1),
(7,2,'Vestido Gala Esmeralda','vestido-gala-esmeralda','VG-002','rental','Vestido de gala verde esmeralda, espalda descubierta y silueta columna.','',7500.00,NULL,15000.00,3750.00,'S','Verde','Crepé','good','available',1,1,NULL,0,'active',1),
(8,4,'Ramo de Lujo','ramo-de-lujo','AC-001','sale','Ramo de novia de flores preservadas, presentación premium.','',0.00,4500.00,1800.00,0.00,'Único','Blanco','Flores preservadas','new','available',0,10,NULL,0,'active',1);

-- ---------- IMÁGENES DE PRODUCTOS (fotos reales) ----------
UPDATE products SET main_image = 'img/products/aurora.jpg'          WHERE id = 1;
UPDATE products SET main_image = 'img/products/isabella.jpg'        WHERE id = 2;
UPDATE products SET main_image = 'img/products/londres-classic.jpg' WHERE id = 3;
UPDATE products SET main_image = 'img/products/gala-royal.jpg'      WHERE id = 4;
UPDATE products SET main_image = 'img/products/velo-premium.jpg'    WHERE id = 5;
UPDATE products SET main_image = 'img/products/corona-cristal.jpg'  WHERE id = 6;
UPDATE products SET main_image = 'img/products/gala-esmeralda.jpg'  WHERE id = 7;
UPDATE products SET main_image = 'img/products/ramo-lujo.jpg'       WHERE id = 8;

INSERT INTO `product_images` (`product_id`,`image_path`,`is_main`,`sort_order`) VALUES
(1,'img/products/aurora.jpg',1,0),
(1,'img/products/aurora-2.jpg',0,1),
(1,'img/products/aurora-3.jpg',0,2),
(2,'img/products/isabella.jpg',1,0),
(2,'img/products/isabella-2.jpg',0,1),
(2,'img/products/isabella-3.jpg',0,2),
(3,'img/products/londres-classic.jpg',1,0),
(3,'img/products/londres-classic-2.jpg',0,1),
(4,'img/products/gala-royal.jpg',1,0),
(4,'img/products/gala-royal-2.jpg',0,1),
(5,'img/products/velo-premium.jpg',1,0),
(5,'img/products/velo-premium-2.jpg',0,1),
(6,'img/products/corona-cristal.jpg',1,0),
(6,'img/products/corona-cristal-2.jpg',0,1),
(7,'img/products/gala-esmeralda.jpg',1,0),
(7,'img/products/gala-esmeralda-2.jpg',0,1),
(8,'img/products/ramo-lujo.jpg',1,0),
(8,'img/products/ramo-lujo-2.jpg',0,1);

-- ---------- IMÁGENES DE CATEGORÍAS (portadas reales) ----------
UPDATE categories SET image = 'img/categories/novia.jpg'      WHERE id = 1;
UPDATE categories SET image = 'img/categories/gala.jpg'       WHERE id = 2;
UPDATE categories SET image = 'img/categories/trajes.jpg'     WHERE id = 3;
UPDATE categories SET image = 'img/categories/accesorios.jpg' WHERE id = 4;
UPDATE categories SET image = 'img/categories/velos.jpg'      WHERE id = 5;
UPDATE categories SET image = 'img/categories/coronas.jpg'    WHERE id = 6;

-- ---------- CLIENTES ----------
INSERT INTO `customers` (`id`,`full_name`,`phone`,`whatsapp`,`email`,`document_number`,`address`,`instagram`,`notes`) VALUES
(1,'Gabriela Méndez','+1 809 555 1001','18095551001','gabriela.mendez@email.com','001-1234567-8','Calle Las Flores #12, Santiago','gabymendez','Novia para boda de junio.'),
(2,'Valentina Rosario','+1 809 555 1002','18095551002','valentina.r@email.com','402-7654321-0','Av. Independencia #88, Santo Domingo','valen.rosario','Cliente frecuente de galas.'),
(3,'Andrés Castillo','+1 809 555 1003','18095551003','andres.castillo@email.com','001-9876543-2','Res. El Portal, Apt 5B','','Requiere traje para padrino.'),
(4,'Camila Fernández','+1 829 555 1004','18295551004','camila.f@email.com',NULL,'Calle Duarte #3, La Vega','camifer','Interesada en compra de vestido.');

-- ---------- ALQUILERES ----------
INSERT INTO `rentals`
(`id`,`rental_number`,`customer_id`,`product_id`,`request_id`,`event_date`,`delivery_date`,`return_date`,
 `rental_price`,`discount`,`late_penalty`,`total_amount`,`initial_payment_required`,`initial_payment_paid`,`remaining_balance`,
 `payment_status`,`rental_status`,`delivery_condition`,`return_condition`,`created_by`)
VALUES
(1,'ALQ-00001',1,1,NULL,'2026-06-20','2026-06-18','2026-06-23',12000.00,0.00,0.00,12000.00,6000.00,6000.00,6000.00,'partial','confirmed',NULL,NULL,1),
(2,'ALQ-00002',2,4,NULL,'2026-06-10','2026-06-08','2026-06-12',8000.00,0.00,0.00,8000.00,4000.00,8000.00,0.00,'paid','delivered','Entregado en perfecto estado',NULL,1),
(3,'ALQ-00003',3,3,NULL,'2026-05-15','2026-05-13','2026-05-18',6000.00,0.00,0.00,6000.00,3000.00,6000.00,0.00,'paid','returned','Entregado en buen estado','Devuelto sin daños',1);

-- ---------- FACTURAS ----------
INSERT INTO `invoices`
(`id`,`invoice_number`,`customer_id`,`rental_id`,`sale_id`,`invoice_type`,`concept`,`subtotal`,`discount`,`tax`,`total`,`paid_amount`,`balance`,`status`,`issued_at`,`created_by`)
VALUES
(1,'FAC-00001',1,1,NULL,'rental','Alquiler Vestido Aurora',12000.00,0.00,0.00,12000.00,6000.00,6000.00,'partial','2026-06-03',1),
(2,'FAC-00002',2,2,NULL,'rental','Alquiler Vestido Gala Royal',8000.00,0.00,0.00,8000.00,8000.00,0.00,'paid','2026-06-08',1),
(3,'FAC-00003',3,3,NULL,'rental','Alquiler Traje Londres Classic',6000.00,0.00,0.00,6000.00,6000.00,0.00,'paid','2026-05-13',1);

-- ---------- PAGOS ----------
INSERT INTO `payments`
(`id`,`payment_number`,`customer_id`,`rental_id`,`sale_id`,`invoice_id`,`amount`,`payment_method`,`reference`,`notes`,`received_by`,`paid_at`)
VALUES
(1,'REC-00001',1,1,NULL,1,6000.00,'cash','','Pago inicial 50%',1,'2026-06-03 10:30:00'),
(2,'REC-00002',2,2,NULL,2,4000.00,'transfer','TRX-558','Pago inicial 50%',1,'2026-06-05 12:00:00'),
(3,'REC-00003',2,2,NULL,2,4000.00,'cash','','Saldo restante al retirar',1,'2026-06-08 09:15:00'),
(4,'REC-00004',3,3,NULL,3,3000.00,'card','APR-7781','Pago inicial 50%',1,'2026-05-13 16:40:00'),
(5,'REC-00005',3,3,NULL,3,3000.00,'cash','','Saldo restante',1,'2026-05-13 17:00:00');

-- ---------- SOLICITUDES DE ALQUILER (públicas pendientes) ----------
INSERT INTO `rental_requests`
(`id`,`customer_id`,`product_id`,`full_name`,`phone`,`email`,`event_date`,`delivery_date`,`return_date`,`message`,`status`,`source`)
VALUES
(1,NULL,2,'Patricia Núñez','+1 809 555 2001','patricia.n@email.com','2026-07-12','2026-07-10','2026-07-15','Me encanta el Vestido Isabella, ¿está disponible para mi boda?','pending','public'),
(2,NULL,7,'Daniela Suárez','+1 829 555 2002','daniela.s@email.com','2026-06-28','2026-06-26','2026-06-30','Quisiera reservar el vestido esmeralda para una gala.','pending','public');

-- ---------- NOTIFICACIONES ----------
INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`) VALUES
(1,'Nueva solicitud de alquiler','Patricia Núñez solicitó el Vestido Isabella','info'),
(1,'Nueva solicitud de alquiler','Daniela Suárez solicitó el Vestido Gala Esmeralda','info');

-- ---------- ACTIVITY LOG ----------
INSERT INTO `activity_logs` (`user_id`,`action`,`entity_type`,`entity_id`,`description`) VALUES
(1,'seed','system',NULL,'Datos de prueba cargados');
