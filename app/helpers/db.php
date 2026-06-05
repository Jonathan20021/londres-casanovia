<?php
/**
 * Helpers de acceso a datos sobre PDO (siempre prepared statements).
 * LONDRES Casa de Novias
 *
 *   db_all($sql, $params)    -> array de filas
 *   db_one($sql, $params)    -> una fila o null
 *   db_value($sql, $params)  -> primer valor escalar
 *   db_insert($table, $data) -> id insertado
 *   db_update($table, $data, $where, $whereParams) -> filas afectadas
 *   db_delete($table, $where, $params) -> filas afectadas
 */
declare(strict_types=1);

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Devuelve el primer valor de la primera fila (o null). */
function db_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

/** Inserta un registro asociativo y devuelve el ID. */
function db_insert(string $table, array $data): int
{
    $cols  = array_keys($data);
    $names = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $holds = implode(', ', array_map(fn($c) => ":$c", $cols));

    $sql  = "INSERT INTO `$table` ($names) VALUES ($holds)";
    $stmt = db()->prepare($sql);
    $stmt->execute($data);

    return (int) db()->lastInsertId();
}

/**
 * Actualiza registros.
 * $where usa placeholders con nombre distintos de los datos, p.ej. "id = :id".
 * $whereParams: ['id' => 5]  (la clave puede llevar o no ':')
 */
function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(', ', array_map(fn($c) => "`$c` = :set_$c", array_keys($data)));
    $sql = "UPDATE `$table` SET $set WHERE $where";

    $params = [];
    foreach ($data as $k => $v) {
        $params["set_$k"] = $v;
    }
    foreach ($whereParams as $k => $v) {
        $params[ltrim((string) $k, ':')] = $v;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_delete(string $table, string $where, array $params = []): int
{
    $stmt = db()->prepare("DELETE FROM `$table` WHERE $where");
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Ejecuta una sentencia arbitraria (UPDATE/DELETE) y devuelve filas afectadas. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
