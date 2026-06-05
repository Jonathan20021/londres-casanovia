<?php
/**
 * Disponibilidad de productos y cálculo de pagos de alquiler.
 * LONDRES Casa de Novias
 *
 * Reglas de negocio:
 *  - Un producto NO está disponible si existe un alquiler activo
 *    (reserved, confirmed, delivered, pending_return) que se solape
 *    con el rango [delivery_date, return_date] solicitado.
 *  - Solapamiento: new.delivery <= existing.return AND new.return >= existing.delivery
 */
declare(strict_types=1);

/** Estados de alquiler que bloquean disponibilidad. */
const RENTAL_BLOCKING_STATUSES = ['reserved', 'confirmed', 'delivered', 'pending_return'];

/**
 * Verifica si un producto está disponible en un rango de fechas.
 *
 * @return array{available:bool, conflict:?array}
 */
function checkProductAvailability(
    int $productId,
    string $deliveryDate,
    string $returnDate,
    ?int $excludeRentalId = null
): array {
    // Validación básica de rango
    if (strtotime($returnDate) < strtotime($deliveryDate)) {
        return ['available' => false, 'conflict' => null, 'error' => 'El rango de fechas es inválido.'];
    }

    $statuses = RENTAL_BLOCKING_STATUSES;
    $in = implode(',', array_fill(0, count($statuses), '?'));

    $sql = "SELECT r.id, r.rental_number, r.event_date, r.delivery_date, r.return_date,
                   r.rental_status, c.full_name AS customer_name, p.name AS product_name
            FROM rentals r
            JOIN customers c ON c.id = r.customer_id
            JOIN products  p ON p.id = r.product_id
            WHERE r.product_id = ?
              AND r.rental_status IN ($in)
              AND r.delivery_date <= ?   /* existing.delivery <= new.return  */
              AND r.return_date   >= ?   /* existing.return   >= new.delivery */";

    $params = array_merge([$productId], $statuses, [$returnDate, $deliveryDate]);

    if ($excludeRentalId !== null) {
        $sql .= ' AND r.id <> ?';
        $params[] = $excludeRentalId;
    }
    $sql .= ' ORDER BY r.delivery_date ASC LIMIT 1';

    $conflict = db_one($sql, $params);

    return [
        'available' => $conflict === null,
        'conflict'  => $conflict,
    ];
}

/**
 * Devuelve todos los rangos ocupados de un producto (para calendarios públicos).
 */
function productBusyRanges(int $productId): array
{
    $statuses = RENTAL_BLOCKING_STATUSES;
    $in = implode(',', array_fill(0, count($statuses), '?'));
    return db_all(
        "SELECT delivery_date, return_date, rental_status
         FROM rentals
         WHERE product_id = ? AND rental_status IN ($in)
         ORDER BY delivery_date ASC",
        array_merge([$productId], $statuses)
    );
}

/**
 * Calcula la estructura de pago de un alquiler (50/50 por defecto).
 *
 * @return array{total:float, percentage:float, initial:float, remaining:float}
 */
function calculateRentalPayments(float $totalAmount, ?float $percentage = null): array
{
    if ($percentage === null) {
        $percentage = (float) setting('initial_payment_percentage', 50);
    }
    $percentage = max(0, min(100, $percentage));
    $initial   = round($totalAmount * ($percentage / 100), 2);
    $remaining = round($totalAmount - $initial, 2);

    return [
        'total'      => round($totalAmount, 2),
        'percentage' => $percentage,
        'initial'    => $initial,
        'remaining'  => $remaining,
    ];
}

/**
 * Calcula el monto pagado de un alquiler/venta sumando sus pagos registrados.
 */
function rental_paid_amount(int $rentalId): float
{
    return (float) db_value(
        'SELECT COALESCE(SUM(amount),0) FROM payments WHERE rental_id = :id',
        ['id' => $rentalId]
    );
}

function sale_paid_amount(int $saleId): float
{
    return (float) db_value(
        'SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = :id',
        ['id' => $saleId]
    );
}

/**
 * Recalcula y persiste los totales de pago de un alquiler tras un pago.
 * Devuelve el nuevo estado de pago.
 */
function recalc_rental_payment(int $rentalId): string
{
    $rental = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $rentalId]);
    if (!$rental) return 'pending';

    $paid  = rental_paid_amount($rentalId);
    $total = (float) $rental['total_amount'];
    $balance = round($total - $paid, 2);

    if ($paid <= 0)            $status = 'pending';
    elseif ($balance > 0.009)  $status = 'partial';
    else                       $status = 'paid';

    db_update('rentals', [
        'initial_payment_paid' => $paid,
        'remaining_balance'    => max(0, $balance),
        'payment_status'       => $status,
    ], 'id = :id', ['id' => $rentalId]);

    return $status;
}
