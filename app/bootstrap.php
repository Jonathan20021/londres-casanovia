<?php
/**
 * Bootstrap central de la aplicación.
 *
 * Inclúyelo al inicio de CADA página (admin y público) con el siguiente
 * encabezado universal — encuentra la raíz sin importar la profundidad:
 *
 *   <?php require_once dirname(__DIR__) . '/app/bootstrap.php';
 *
 * (ajusta el número de dirname() según la profundidad, o usa el buscador
 *  de abajo que ya es a prueba de profundidad).
 */
declare(strict_types=1);

if (!defined('LCN_ROOT')) {
    // app/ -> raíz del proyecto
    define('LCN_ROOT', dirname(__DIR__));
}

require_once LCN_ROOT . '/app/config/app.php';
require_once LCN_ROOT . '/app/config/database.php';
require_once LCN_ROOT . '/app/config/security.php';

// Autoload de Composer (Dompdf u otras libs) si está instalado
if (is_file(LCN_ROOT . '/vendor/autoload.php')) {
    require_once LCN_ROOT . '/vendor/autoload.php';
}

require_once LCN_ROOT . '/app/helpers/db.php';
require_once LCN_ROOT . '/app/helpers/functions.php';
require_once LCN_ROOT . '/app/helpers/auth.php';
require_once LCN_ROOT . '/app/helpers/availability.php';
require_once LCN_ROOT . '/app/helpers/upload.php';
require_once LCN_ROOT . '/app/helpers/ui.php';
require_once LCN_ROOT . '/app/helpers/pdf.php';
