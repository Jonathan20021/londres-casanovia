<?php
/**
 * Endpoint JSON público: verifica disponibilidad de un producto en un rango de fechas.
 *
 *   GET product_id (int, requerido)
 *       delivery   (Y-m-d, requerido)
 *       return     (Y-m-d, requerido)
 *       exclude    (int, opcional — id de alquiler a excluir)
 *
 * Respuesta:
 *   { available: bool,
 *     conflict: { rental_number, delivery_date, return_date, rental_status } | null,
 *     error?: string }
 *
 * IMPORTANTE: nunca se expone información del cliente del alquiler en conflicto.
 * No requiere sesión. Devuelve siempre JSON.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';  // public/api/*.php => N=2

/* ------------------------------------------------------------------ *
 *  Lectura y validación de parámetros
 * ------------------------------------------------------------------ */
$productId = (int) get_param('product_id', '0');
$delivery  = get_param('delivery', '');
$return    = get_param('return', '');
$excludeRaw = get_param('exclude', '');
$exclude    = $excludeRaw !== '' ? (int) $excludeRaw : null;

// Parámetros obligatorios presentes.
if ($productId <= 0 || $delivery === '' || $return === '') {
    json_response([
        'available' => false,
        'conflict'  => null,
        'error'     => 'Parámetros incompletos. Se requieren product_id, delivery y return.',
    ], 400);
}

/** Valida una fecha en formato Y-m-d. */
$isValidDate = static function (string $value): bool {
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
};

if (!$isValidDate($delivery) || !$isValidDate($return)) {
    json_response([
        'available' => false,
        'conflict'  => null,
        'error'     => 'Formato de fecha inválido. Use AAAA-MM-DD.',
    ], 400);
}

// El producto debe existir y estar activo y disponible para alquiler.
$product = db_one(
    "SELECT id FROM products
     WHERE id = :id AND status = 'active' AND type IN ('rental','both')",
    ['id' => $productId]
);

if (!$product) {
    json_response([
        'available' => false,
        'conflict'  => null,
        'error'     => 'El producto solicitado no existe o no está disponible para alquiler.',
    ], 404);
}

/* ------------------------------------------------------------------ *
 *  Verificación de disponibilidad (núcleo)
 * ------------------------------------------------------------------ */
$result = checkProductAvailability($productId, $delivery, $return, $exclude);

// Si el rango es inválido, el núcleo devuelve 'error'.
if (!empty($result['error'])) {
    json_response([
        'available' => false,
        'conflict'  => null,
        'error'     => $result['error'],
    ], 400);
}

// Construye el conflicto SIN ningún dato del cliente.
$conflict = null;
if (!$result['available'] && !empty($result['conflict'])) {
    $c = $result['conflict'];
    $conflict = [
        'rental_number' => $c['rental_number'] ?? null,
        'delivery_date' => $c['delivery_date'] ?? null,
        'return_date'   => $c['return_date'] ?? null,
        'rental_status' => $c['rental_status'] ?? null,
    ];
}

json_response([
    'available' => (bool) $result['available'],
    'conflict'  => $conflict,
]);
