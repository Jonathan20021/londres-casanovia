-- ============================================================
--  LONDRES Casa de Novias — Esquema de base de datos
--  MySQL / MariaDB (utf8mb4, InnoDB)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `londres_casa_novias`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `londres_casa_novias`;

-- ---------- Limpieza (orden inverso por FKs) ----------
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `rental_evidence`;
DROP TABLE IF EXISTS `rental_items`;
DROP TABLE IF EXISTS `rentals`;
DROP TABLE IF EXISTS `rental_requests`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `business_settings`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ============================================================
--  ROLES Y PERMISOS
-- ============================================================
CREATE TABLE `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(80)  NOT NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_perm` (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  USUARIOS
-- ============================================================
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120) NOT NULL,
    `email`         VARCHAR(150) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id`       INT UNSIGNED NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `avatar`        VARCHAR(255) NULL,
    `last_login_at` TIMESTAMP NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CLIENTES
-- ============================================================
CREATE TABLE `customers` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`       VARCHAR(150) NOT NULL,
    `phone`           VARCHAR(40)  NULL,
    `whatsapp`        VARCHAR(40)  NULL,
    `email`           VARCHAR(150) NULL,
    `document_number` VARCHAR(40)  NULL,
    `address`         VARCHAR(255) NULL,
    `birthdate`       DATE         NULL,
    `instagram`       VARCHAR(80)  NULL,
    `notes`           TEXT         NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customers_name` (`full_name`),
    KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CATEGORÍAS
-- ============================================================
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `image`       VARCHAR(255) NULL,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  PRODUCTOS / INVENTARIO
-- ============================================================
CREATE TABLE `products` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED NULL,
    `name`              VARCHAR(160) NOT NULL,
    `slug`              VARCHAR(180) NOT NULL,
    `sku`               VARCHAR(60)  NULL,
    `barcode`           VARCHAR(48)  NULL,
    `type`              ENUM('rental','sale','both') NOT NULL DEFAULT 'rental',
    `description`       TEXT NULL,
    `internal_notes`    TEXT NULL,
    `rental_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sale_price`        DECIMAL(12,2) NULL,
    `cost_price`        DECIMAL(12,2) NULL,
    `deposit_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `size`              VARCHAR(40) NULL,
    `color`             VARCHAR(60) NULL,
    `material`          VARCHAR(80) NULL,
    `condition_status`  ENUM('new','excellent','good','repair','out_of_service') NOT NULL DEFAULT 'excellent',
    `commercial_status` ENUM('available','reserved','rented','sold','unavailable','maintenance') NOT NULL DEFAULT 'available',
    `is_unique`         TINYINT(1) NOT NULL DEFAULT 1,
    `quantity`          INT NOT NULL DEFAULT 1,
    `main_image`        VARCHAR(255) NULL,
    `featured`          TINYINT(1) NOT NULL DEFAULT 0,
    `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_by`        INT UNSIGNED NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    UNIQUE KEY `uq_products_barcode` (`barcode`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_status` (`commercial_status`, `status`),
    KEY `idx_products_featured` (`featured`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_products_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_images` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_main`    TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pi_product` (`product_id`),
    CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SOLICITUDES DE ALQUILER (públicas)
-- ============================================================
CREATE TABLE `rental_requests` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   INT UNSIGNED NULL,
    `product_id`    INT UNSIGNED NULL,
    `full_name`     VARCHAR(150) NULL,
    `phone`         VARCHAR(40)  NULL,
    `email`         VARCHAR(150) NULL,
    `event_date`    DATE NULL,
    `delivery_date` DATE NULL,
    `return_date`   DATE NULL,
    `message`       TEXT NULL,
    `status`        ENUM('pending','reviewed','converted','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `source`        ENUM('public','admin') NOT NULL DEFAULT 'public',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rr_status` (`status`),
    KEY `idx_rr_product` (`product_id`),
    CONSTRAINT `fk_rr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rr_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ALQUILERES
-- ============================================================
CREATE TABLE `rentals` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rental_number`            VARCHAR(40) NOT NULL,
    `customer_id`              INT UNSIGNED NOT NULL,
    `product_id`               INT UNSIGNED NOT NULL,
    `request_id`               INT UNSIGNED NULL,
    `event_date`               DATE NULL,
    `delivery_date`            DATE NOT NULL,
    `return_date`              DATE NOT NULL,
    `rental_price`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `late_penalty`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `initial_payment_required` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `initial_payment_paid`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `remaining_balance`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status`           ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
    `rental_status`            ENUM('pending','reserved','confirmed','delivered','pending_return','returned','cancelled','overdue') NOT NULL DEFAULT 'pending',
    `delivery_condition`       VARCHAR(255) NULL,
    `return_condition`         VARCHAR(255) NULL,
    `delivery_notes`           TEXT NULL,
    `return_notes`             TEXT NULL,
    `authorized_delivery_without_full_payment` TINYINT(1) NOT NULL DEFAULT 0,
    `authorization_reason`     VARCHAR(255) NULL,
    `created_by`               INT UNSIGNED NULL,
    `created_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rentals_number` (`rental_number`),
    KEY `idx_rentals_customer` (`customer_id`),
    KEY `idx_rentals_product` (`product_id`),
    KEY `idx_rentals_dates` (`product_id`, `rental_status`, `delivery_date`, `return_date`),
    KEY `idx_rentals_status` (`rental_status`),
    CONSTRAINT `fk_rentals_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rentals_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_rentals_request`  FOREIGN KEY (`request_id`)  REFERENCES `rental_requests`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rentals_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rental_items` (
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

CREATE TABLE `rental_evidence` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rental_id`  INT UNSIGNED NOT NULL,
    `type`       ENUM('delivery','return') NOT NULL DEFAULT 'delivery',
    `image_path` VARCHAR(255) NOT NULL,
    `notes`      VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_re_rental` (`rental_id`),
    CONSTRAINT `fk_re_rental` FOREIGN KEY (`rental_id`) REFERENCES `rentals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  VENTAS
-- ============================================================
CREATE TABLE `sales` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_number`    VARCHAR(40) NOT NULL,
    `customer_id`    INT UNSIGNED NOT NULL,
    `product_id`     INT UNSIGNED NOT NULL,
    `sale_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
    `status`         ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
    `notes`          TEXT NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sales_number` (`sale_number`),
    KEY `idx_sales_customer` (`customer_id`),
    KEY `idx_sales_product` (`product_id`),
    CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_sales_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  FACTURAS
-- ============================================================
CREATE TABLE `invoices` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(40) NOT NULL,
    `customer_id`    INT UNSIGNED NOT NULL,
    `rental_id`      INT UNSIGNED NULL,
    `sale_id`        INT UNSIGNED NULL,
    `invoice_type`   ENUM('rental','sale') NOT NULL DEFAULT 'rental',
    `concept`        VARCHAR(255) NULL,
    `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `paid_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `balance`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`         ENUM('pending','partial','paid','void') NOT NULL DEFAULT 'pending',
    `issued_at`      DATE NULL,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoices_number` (`invoice_number`),
    KEY `idx_invoices_customer` (`customer_id`),
    KEY `idx_invoices_rental` (`rental_id`),
    KEY `idx_invoices_sale` (`sale_id`),
    CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoices_rental`   FOREIGN KEY (`rental_id`)   REFERENCES `rentals`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_sale`     FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoices_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  PAGOS
-- ============================================================
CREATE TABLE `payments` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_number` VARCHAR(40) NOT NULL,
    `customer_id`    INT UNSIGNED NULL,
    `rental_id`      INT UNSIGNED NULL,
    `sale_id`        INT UNSIGNED NULL,
    `invoice_id`     INT UNSIGNED NULL,
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash','transfer','card','deposit','other') NOT NULL DEFAULT 'cash',
    `reference`      VARCHAR(120) NULL,
    `notes`          VARCHAR(255) NULL,
    `received_by`    INT UNSIGNED NULL,
    `paid_at`        DATETIME NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payments_number` (`payment_number`),
    KEY `idx_payments_rental` (`rental_id`),
    KEY `idx_payments_sale` (`sale_id`),
    KEY `idx_payments_invoice` (`invoice_id`),
    CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_rental`   FOREIGN KEY (`rental_id`)   REFERENCES `rentals`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_payments_sale`     FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_payments_invoice`  FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_payments_user`     FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CONFIGURACIÓN DEL NEGOCIO
-- ============================================================
CREATE TABLE `business_settings` (
    `id`                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_name`              VARCHAR(150) NOT NULL DEFAULT 'LONDRES Casa de Novias',
    `logo`                       VARCHAR(255) NULL,
    `phone`                      VARCHAR(40)  NULL,
    `whatsapp`                   VARCHAR(40)  NULL,
    `email`                      VARCHAR(150) NULL,
    `address`                    VARCHAR(255) NULL,
    `instagram`                  VARCHAR(120) NULL,
    `opening_hours`              VARCHAR(255) NULL,
    `rental_policy`              TEXT NULL,
    `return_policy`              TEXT NULL,
    `initial_payment_percentage` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    `currency`                   VARCHAR(10)  NOT NULL DEFAULT 'RD$',
    `tax_percentage`             DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `invoice_prefix`             VARCHAR(20)  NOT NULL DEFAULT 'FAC',
    `barcode_prefix`             VARCHAR(6)   NOT NULL DEFAULT 'LCN',
    `primary_color`              VARCHAR(20)  NOT NULL DEFAULT '#C8102E',
    `created_at`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  LOGS Y NOTIFICACIONES
-- ============================================================
CREATE TABLE `activity_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `action`      VARCHAR(80)  NOT NULL,
    `entity_type` VARCHAR(60)  NULL,
    `entity_id`   INT UNSIGNED NULL,
    `description` VARCHAR(255) NULL,
    `ip_address`  VARCHAR(45)  NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,
    `title`      VARCHAR(150) NOT NULL,
    `message`    VARCHAR(255) NULL,
    `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`, `is_read`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
