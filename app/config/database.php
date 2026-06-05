<?php
/**
 * Conexión a la base de datos (PDO singleton)
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/*
 * Credenciales de PRODUCCIÓN por defecto.
 * En desarrollo se sobrescriben desde app/config/local.php (que NO se sube
 * al servidor). Por eso cada constante se define solo si no existe ya.
 *
 * Nota: en el hosting (cPanel) la app y la base suelen compartir servidor,
 * por lo que el host es 'localhost'. Si tu app está en otro servidor, usa
 * la IP pública 129.121.81.172.
 */
// Host por IP: funciona aunque la app y la base estén en servidores distintos
// (el usuario de la BD admite conexiones remotas). Si tu app y tu base comparten
// servidor y prefieres el socket local, puedes usar 'localhost'.
if (!defined('DB_HOST'))    define('DB_HOST',    '129.121.81.172');
if (!defined('DB_PORT'))    define('DB_PORT',    '3306');
if (!defined('DB_NAME'))    define('DB_NAME',    'neetjbte_londrescasadenovia');
if (!defined('DB_USER'))    define('DB_USER',    'neetjbte_londres');
if (!defined('DB_PASS'))    define('DB_PASS',    'Miguel#2026#');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

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

    // Compatibilidad MySQL 8 / MariaDB: relajar ONLY_FULL_GROUP_BY para que las
    // consultas con GROUP BY se comporten igual en producción y en desarrollo.
    try {
        $pdo->exec("SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
    } catch (Throwable $e) { /* no crítico */ }

    return $pdo;
}
