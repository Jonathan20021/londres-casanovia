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
            FROM rental_items ri
            JOIN rentals r ON r.id = ri.rental_id
            JOIN customers c ON c.id = r.customer_id
            JOIN products  p ON p.id = ri.product_id
            WHERE ri.product_id = ?
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
        "SELECT r.delivery_date, r.return_date, r.rental_status
         FROM rental_items ri
         JOIN rentals r ON r.id = ri.rental_id
         WHERE ri.product_id = ? AND r.rental_status IN ($in)
         ORDER BY r.delivery_date ASC",
        array_merge([$productId], $statuses)
    );
}

/** IDs de todos los productos incluidos en un alquiler, en orden de captura. */
function rental_product_ids(int $rentalId): array
{
    return array_map(
        'intval',
        array_column(
            db_all(
                'SELECT product_id FROM rental_items WHERE rental_id = :id ORDER BY sort_order ASC, id ASC',
                ['id' => $rentalId]
            ),
            'product_id'
        )
    );
}

/** Productos y precio registrado de un alquiler. */
function rental_items_details(int $rentalId): array
{
    // OJO: p.* va primero y las columnas de ri.* después, para que los datos de
    // la línea (precio, modificación) no queden pisados por los del producto.
    return db_all(
        "SELECT p.*, c.name AS category_name,
                ri.id AS rental_item_id, ri.unit_price, ri.sort_order,
                ri.needs_alteration, ri.alteration_notes, ri.alteration_status,
                ri.alteration_done_at, ri.alteration_done_by,
                au.name AS alteration_done_by_name
         FROM rental_items ri
         JOIN products p ON p.id = ri.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN users au ON au.id = ri.alteration_done_by
         WHERE ri.rental_id = :id
         ORDER BY ri.sort_order ASC, ri.id ASC",
        ['id' => $rentalId]
    );
}

/* ------------------------------------------------------------------ *
 *  PIEZAS POR MODIFICAR (ruedo, cintura, tirantes…)
 *  Se marcan por línea de alquiler (rental_items) con su nota de taller.
 * ------------------------------------------------------------------ */

/**
 * Piezas marcadas para modificar, con los datos del alquiler y del cliente.
 *
 * @param string $status 'pending' | 'done' | 'all'
 */
function alteration_items(string $status = 'pending', int $limit = 200): array
{
    $where = 'ri.needs_alteration = 1';
    $params = [];
    if ($status !== 'all') {
        $where .= ' AND ri.alteration_status = :st';
        $params['st'] = $status === 'done' ? 'done' : 'pending';
    }

    return db_all(
        "SELECT ri.id AS rental_item_id, ri.alteration_notes, ri.alteration_status,
                ri.alteration_done_at, ri.unit_price,
                p.id AS product_id, p.name AS product_name, p.main_image, p.size, p.color,
                p.barcode, p.sku,
                r.id AS rental_id, r.rental_number, r.delivery_date, r.delivery_time,
                r.return_date, r.event_date, r.rental_status,
                c.full_name AS customer_name, c.phone AS customer_phone,
                u.name AS done_by_name
           FROM rental_items ri
           JOIN rentals   r ON r.id = ri.rental_id
           JOIN products  p ON p.id = ri.product_id
           JOIN customers c ON c.id = r.customer_id
           LEFT JOIN users u ON u.id = ri.alteration_done_by
          WHERE $where AND r.rental_status <> 'cancelled'
          ORDER BY r.delivery_date ASC, r.delivery_time ASC, ri.id ASC
          LIMIT " . max(1, $limit),
        $params
    );
}

/** Cuántas piezas están pendientes de modificar (badge del tablero/menú). */
function alterations_pending_count(): int
{
    return (int) db_value(
        "SELECT COUNT(*)
           FROM rental_items ri
           JOIN rentals r ON r.id = ri.rental_id
          WHERE ri.needs_alteration = 1
            AND ri.alteration_status = 'pending'
            AND r.rental_status <> 'cancelled'"
    );
}

/** Marca una pieza como modificada (o la reabre). */
function set_alteration_status(int $rentalItemId, string $status, ?int $userId = null): bool
{
    $status = $status === 'done' ? 'done' : 'pending';
    $item = db_one('SELECT * FROM rental_items WHERE id = :id', ['id' => $rentalItemId]);
    if (!$item) return false;

    db_update('rental_items', [
        'alteration_status'  => $status,
        'alteration_done_at' => $status === 'done' ? date('Y-m-d H:i:s') : null,
        'alteration_done_by' => $status === 'done' ? $userId : null,
    ], 'id = :id', ['id' => $rentalItemId]);

    return true;
}

/** Sincroniza el estado comercial de todas las piezas de un alquiler. */
function sync_rental_products_status(int $rentalId, string $commercialStatus): void
{
    db_exec(
        'UPDATE products p
         JOIN rental_items ri ON ri.product_id = p.id
         SET p.commercial_status = :status
         WHERE ri.rental_id = :rental_id',
        ['status' => $commercialStatus, 'rental_id' => $rentalId]
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

/* ------------------------------------------------------------------ *
 *  PENALIDAD POR MORA
 *  Monto fijo por cada DÍA LABORABLE de atraso sobre la fecha pactada
 *  de devolución. La tarifa y qué días cuentan como laborables se
 *  configuran en Admin → Configuración.
 * ------------------------------------------------------------------ */

/** Tarifa fija de mora por día laborable (RD$500 por defecto). */
function late_fee_per_day(): float
{
    return max(0, (float) setting('late_fee_per_day', 500));
}

/** ¿El sábado cuenta como día laborable para la mora? */
function late_fee_counts_saturday(): bool
{
    return setting('late_fee_workweek', 'mon_fri') === 'mon_sat';
}

/**
 * Días laborables transcurridos entre dos fechas (excluye la fecha inicial
 * e incluye la final). Domingo nunca cuenta; el sábado depende del ajuste.
 * Devuelve 0 si no hay atraso.
 */
function business_days_between(string $from, string $to): int
{
    try {
        $start = new DateTime($from);
        $end   = new DateTime($to);
    } catch (Exception $e) {
        return 0;
    }
    $start->setTime(0, 0);
    $end->setTime(0, 0);
    if ($end <= $start) return 0;

    $lastWorkday = late_fee_counts_saturday() ? 6 : 5; // N (ISO): 6 = sábado, 5 = viernes
    $days = 0;
    $cursor = clone $start;
    while ($cursor < $end) {
        $cursor->modify('+1 day');
        if ((int) $cursor->format('N') <= $lastWorkday) {
            $days++;
        }
    }
    return $days;
}

/**
 * Días laborables de atraso de un alquiler.
 * Si aún no se ha devuelto se mide contra hoy.
 */
function rental_late_days(string $returnDate, ?string $actualReturnDate = null): int
{
    $reference = $actualReturnDate ?: date('Y-m-d');
    return business_days_between($returnDate, $reference);
}

/** Penalidad acumulada = días laborables de atraso × tarifa fija. */
function rental_late_penalty(string $returnDate, ?string $actualReturnDate = null): float
{
    return round(rental_late_days($returnDate, $actualReturnDate) * late_fee_per_day(), 2);
}

/**
 * Recalcula la penalidad por mora de un alquiler y propaga el nuevo total
 * al propio alquiler y a su factura activa.
 *
 * @param ?string $actualReturnDate Fecha real de devolución (null = sigue fuera).
 * @return array{late_days:int, late_penalty:float, total:float, balance:float}
 */
function apply_rental_late_penalty(int $rentalId, ?string $actualReturnDate = null): array
{
    $rental = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $rentalId]);
    if (!$rental) {
        return ['late_days' => 0, 'late_penalty' => 0.0, 'total' => 0.0, 'balance' => 0.0];
    }

    $reference = $actualReturnDate ?: ($rental['actual_return_date'] ?: null);
    $lateDays  = rental_late_days((string) $rental['return_date'], $reference);
    $penalty   = round($lateDays * late_fee_per_day(), 2);

    $total = round((float) $rental['rental_price'] - (float) $rental['discount'] + $penalty, 2);
    if ($total < 0) $total = 0.0;

    $paid    = rental_paid_amount($rentalId);
    $balance = round($total - $paid, 2);

    if ($paid <= 0.009)       $paymentStatus = 'pending';
    elseif ($balance > 0.009) $paymentStatus = 'partial';
    else                      $paymentStatus = 'paid';

    $fields = [
        'late_penalty'      => $penalty,
        'total_amount'      => $total,
        'remaining_balance' => max(0, $balance),
        'payment_status'    => $paymentStatus,
    ];
    if ($actualReturnDate !== null) {
        $fields['actual_return_date'] = $actualReturnDate;
    }
    db_update('rentals', $fields, 'id = :id', ['id' => $rentalId]);

    sync_rental_invoice($rentalId);

    return ['late_days' => $lateDays, 'late_penalty' => $penalty, 'total' => $total, 'balance' => $balance];
}

/**
 * Sincroniza la factura activa de un alquiler con los importes del alquiler.
 */
function sync_rental_invoice(int $rentalId): void
{
    $rental = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $rentalId]);
    if (!$rental) return;

    $invoice = db_one(
        "SELECT * FROM invoices WHERE rental_id = :rid AND status <> 'void' ORDER BY id DESC LIMIT 1",
        ['rid' => $rentalId]
    );
    if (!$invoice) return;

    $total   = (float) $rental['total_amount'];
    $paid    = rental_paid_amount($rentalId);
    $balance = round($total - $paid, 2);

    if ($paid <= 0.009)       $status = 'pending';
    elseif ($balance > 0.009) $status = 'partial';
    else                      $status = 'paid';

    db_update('invoices', [
        'subtotal'    => (float) $rental['rental_price'],
        'discount'    => (float) $rental['discount'],
        'total'       => $total,
        'paid_amount' => $paid,
        'balance'     => max(0, $balance),
        'status'      => $status,
    ], 'id = :id', ['id' => (int) $invoice['id']]);
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
