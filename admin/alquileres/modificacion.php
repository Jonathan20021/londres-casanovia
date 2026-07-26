<?php
/**
 * Modificaciones de piezas (ruedo, cintura, tirantes…) — LONDRES Casa de Novias
 *
 * Marca/desmarca una línea de alquiler como "por modificar", guarda su nota de
 * taller y permite darla por lista. Lo usan el tablero (AJAX) y el detalle del
 * alquiler (POST normal con redirect).
 *
 * admin/alquileres/modificacion.php (N=2) · Permiso: rentals.manage
 *
 * Entrada POST:
 *   item_id  int     id de rental_items
 *   action   string  'done' | 'pending' | 'save' | 'remove'
 *   notes    string  nota de taller (solo con 'save')
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$isAjax = !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
$user   = current_user();

/** Responde según el origen de la petición. */
function alteration_reply(bool $ok, string $message, array $extra = [], ?int $rentalId = null): void
{
    global $isAjax;
    if ($isAjax) {
        json_response(array_merge(['ok' => $ok, 'message' => $message], $extra), $ok ? 200 : 422);
    }
    flash($ok ? 'success' : 'error', $message);
    redirect($rentalId
        ? admin_url('alquileres/ver.php?id=' . $rentalId)
        : admin_url('alquileres/kanban.php'));
}

if (!is_post()) {
    redirect(admin_url('alquileres/kanban.php'));
}
require_csrf();

$itemId = (int) post('item_id', 0);
$action = (string) post('action', '');

$item = $itemId > 0
    ? db_one(
        'SELECT ri.*, r.rental_number, r.id AS rental_id, p.name AS product_name
           FROM rental_items ri
           JOIN rentals  r ON r.id = ri.rental_id
           JOIN products p ON p.id = ri.product_id
          WHERE ri.id = :id',
        ['id' => $itemId]
    )
    : null;

if (!$item) {
    alteration_reply(false, 'La pieza indicada no existe.');
}

$rentalId = (int) $item['rental_id'];

switch ($action) {
    /* --- Marcar la modificación como hecha --- */
    case 'done':
        set_alteration_status($itemId, 'done', (int) $user['id']);
        log_activity('rental.alteration.done', 'rental', $rentalId,
            'Modificación lista: ' . $item['product_name'] . ' (' . $item['rental_number'] . ')');
        alteration_reply(true, 'Modificación de ' . $item['product_name'] . ' marcada como lista.',
            ['status' => 'done', 'pending_count' => alterations_pending_count()], $rentalId);
        break;

    /* --- Reabrir la modificación --- */
    case 'pending':
        set_alteration_status($itemId, 'pending');
        log_activity('rental.alteration.reopen', 'rental', $rentalId,
            'Modificación reabierta: ' . $item['product_name'] . ' (' . $item['rental_number'] . ')');
        alteration_reply(true, 'Modificación de ' . $item['product_name'] . ' reabierta.',
            ['status' => 'pending', 'pending_count' => alterations_pending_count()], $rentalId);
        break;

    /* --- Marcar la pieza para modificar / actualizar la nota --- */
    case 'save':
        $notes = trim((string) post('notes', ''));
        db_update('rental_items', [
            'needs_alteration'   => 1,
            'alteration_notes'   => $notes !== '' ? $notes : null,
            'alteration_status'  => 'pending',
            'alteration_done_at' => null,
            'alteration_done_by' => null,
        ], 'id = :id', ['id' => $itemId]);
        log_activity('rental.alteration.save', 'rental', $rentalId,
            'Modificación registrada: ' . $item['product_name'] . ' (' . $item['rental_number'] . ')');
        alteration_reply(true, 'Se registró la modificación de ' . $item['product_name'] . '.',
            ['status' => 'pending', 'pending_count' => alterations_pending_count()], $rentalId);
        break;

    /* --- Quitar la marca de modificación --- */
    case 'remove':
        db_update('rental_items', [
            'needs_alteration'   => 0,
            'alteration_notes'   => null,
            'alteration_status'  => 'pending',
            'alteration_done_at' => null,
            'alteration_done_by' => null,
        ], 'id = :id', ['id' => $itemId]);
        log_activity('rental.alteration.remove', 'rental', $rentalId,
            'Modificación anulada: ' . $item['product_name'] . ' (' . $item['rental_number'] . ')');
        alteration_reply(true, 'Se quitó la modificación de ' . $item['product_name'] . '.',
            ['status' => 'none', 'pending_count' => alterations_pending_count()], $rentalId);
        break;

    default:
        alteration_reply(false, 'Acción no válida.', [], $rentalId);
}
