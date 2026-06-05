<?php
/**
 * Endpoint: cambiar el estado de un alquiler (usado por el tablero Kanban).
 * POST { rental_id, status } · CSRF por cabecera X-CSRF-TOKEN o campo _csrf.
 * Devuelve JSON. Aplica las reglas de negocio (no entregar con saldo pendiente
 * salvo autorización) y sincroniza el estado comercial del producto.
 *
 * admin/alquileres/cambiar-estado.php (N=2) · Permiso: rentals.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
if (!verify_csrf()) {
    json_response(['ok' => false, 'error' => 'Token de seguridad inválido.'], 419);
}

$rentalId = (int) post('rental_id', 0);
$status   = (string) post('status', '');

$allowed = ['pending', 'reserved', 'confirmed', 'delivered', 'pending_return', 'returned', 'cancelled', 'overdue'];
if ($rentalId <= 0 || !in_array($status, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Datos inválidos.'], 422);
}

$rental = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $rentalId]);
if (!$rental) {
    json_response(['ok' => false, 'error' => 'El alquiler no existe.'], 404);
}

/* Regla: no se puede marcar como "Entregado" con saldo pendiente,
   salvo que se haya autorizado previamente la entrega sin pago total. */
if ($status === 'delivered'
    && (float) $rental['remaining_balance'] > 0.009
    && (int) $rental['authorized_delivery_without_full_payment'] !== 1) {
    json_response([
        'ok'    => false,
        'error' => 'No se puede entregar con saldo pendiente (' . money($rental['remaining_balance']) . '). Registre el pago o autorice la entrega desde el detalle del alquiler.',
    ], 409);
}

/* Estado comercial del producto según el nuevo estado del alquiler */
$productStatus = match ($status) {
    'delivered', 'pending_return'        => 'rented',
    'reserved', 'confirmed', 'pending'   => 'reserved',
    'returned', 'cancelled'              => 'available',
    default                              => null,
};

db_update('rentals', ['rental_status' => $status], 'id = :id', ['id' => $rentalId]);
if ($productStatus !== null) {
    db_update('products', ['commercial_status' => $productStatus], 'id = :id', ['id' => (int) $rental['product_id']]);
}

log_activity('rental.status', 'rental', $rentalId, 'Estado cambiado a ' . $status . ' (' . $rental['rental_number'] . ')');

json_response(['ok' => true, 'status' => $status, 'rental_number' => $rental['rental_number']]);
