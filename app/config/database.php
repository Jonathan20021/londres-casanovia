<?php
/**
 * Conexión a la base de datos (PDO singleton)
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* Credenciales — valores por defecto de XAMPP (root sin contraseña). */
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    '3306');
define('DB_NAME',    'londres_casa_novias');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una única instancia de PDO compartida en toda la petición.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Emulación activada: permite reutilizar placeholders con nombre
            // (p. ej. :q en búsquedas multi-columna) y vincular LIMIT sin error.
            // La protección contra inyección SQL se mantiene (PDO escapa los valores).
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            http_response_code(500);
            die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
        }
        error_log('DB connection error: ' . $e->getMessage());
        http_response_code(500);
        die('No se pudo conectar a la base de datos. Intente más tarde.');
    }

    return $pdo;
}
