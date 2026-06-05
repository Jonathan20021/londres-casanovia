<?php
/**
 * Configuración general de la aplicación
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  Override de entorno local (desarrollo). Este archivo NO se sube a
 *  producción; allí no existe y se usan los valores de producción.
 *  Define: APP_ENV, APP_URL y las credenciales DB_* para XAMPP.
 * ------------------------------------------------------------------ */
if (is_file(__DIR__ . '/local.php')) {
    require __DIR__ . '/local.php';
}

/* ------------------------------------------------------------------ *
 *  Entorno  ('local' si hay override, 'production' en el servidor)
 * ------------------------------------------------------------------ */
if (!defined('APP_ENV')) {
    define('APP_ENV', is_file(__DIR__ . '/local.php') ? 'local' : 'production');
}
define('APP_DEBUG', APP_ENV !== 'production');

/* ------------------------------------------------------------------ *
 *  Rutas del sistema de archivos y URLs
 * ------------------------------------------------------------------ */
// Raíz del proyecto en disco (…/londres-casanovia)
if (!defined('LCN_ROOT')) {
    define('LCN_ROOT', dirname(__DIR__, 2));
}

// Carpeta del proyecto bajo el document root del servidor web.
// Producción (subido a la raíz del dominio) = '' . Si lo subes a una
// subcarpeta, ponla aquí (ej. '/londres'). En local lo fija local.php.
if (!defined('APP_URL')) {
    define('APP_URL', '');
}

define('APP_NAME', 'LONDRES Casa de Novias');

// Almacenamiento privado y uploads públicos
define('STORAGE_PATH', LCN_ROOT . '/storage');
define('UPLOADS_PATH', LCN_ROOT . '/public/assets/uploads');
define('UPLOADS_URL',  APP_URL  . '/public/assets/uploads');

/* ------------------------------------------------------------------ *
 *  Manejo de errores
 * ------------------------------------------------------------------ */
error_reporting(E_ALL);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
}

// Zona horaria (República Dominicana)
date_default_timezone_set('America/Santo_Domingo');
