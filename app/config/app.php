<?php
/**
 * Configuración general de la aplicación
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  Entorno
 * ------------------------------------------------------------------ */
// 'local' en desarrollo (XAMPP) | 'production' en hosting/cPanel
define('APP_ENV', 'local');
define('APP_DEBUG', APP_ENV !== 'production');

/* ------------------------------------------------------------------ *
 *  Rutas del sistema de archivos y URLs
 * ------------------------------------------------------------------ */
// Raíz del proyecto en disco (…/londres-casanovia)
if (!defined('LCN_ROOT')) {
    define('LCN_ROOT', dirname(__DIR__, 2));
}

// Carpeta del proyecto bajo el document root del servidor web.
// En XAMPP el proyecto vive en http://localhost/londres-casanovia
// En un dominio raíz cambia esto a '' (cadena vacía).
define('APP_URL', '/londres-casanovia');

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
