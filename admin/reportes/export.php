<?php
/**
 * Exportación de reportes a CSV — LONDRES Casa de Novias
 *
 * Ruta: admin/reportes/export.php  (N=2)
 * Permiso: reports.view
 *
 * Uso:  export.php?report=NOMBRE&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Genera el CSV directamente a php://output con fputcsv (sin layout).
 * Cada reporte coincide con una sección del panel admin/reportes/index.php.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('reports.view');

/* ------------------------------------------------------------------ *
 *  Parámetros: reporte + rango de fechas
 * ------------------------------------------------------------------ */
$report = get_param('report', 'resumen-general');

$default_from = date('Y-m-01');
$default_to   = date('Y-m-t');
$from = get_param('from', $default_from);
$to   = get_param('to',   $default_to);

$valid_date = static function (string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
};
if (!$valid_date($from)) $from = $default_from;
if (!$valid_date($to))   $to   = $default_to;
if (strtotime($to) < strtotime($from)) {
    [$from, $to] = [$to, $from];
}

$from_dt = $from . ' 00:00:00';
$to_dt   = $to   . ' 23:59:59';

/* ------------------------------------------------------------------ *
 *  Definición de cada reporte: título, encabezados y filas.
 *  Cada entrada devuelve ['headers' => [...], 'rows' => [[...], ...]].
 * ------------------------------------------------------------------ */

/** Formatea un decimal sin símbolo de moneda para CSV (punto decimal). */
$num = static function ($v): string {
    return number_format((float) $v, 2, '.', '');
};

/** Etiqueta legible de estados para CSV. */
$labels = [
    'rental' => [
        'pending' => 'Solicitud pendiente', 'reserved' => 'Reservado', 'confirmed' => 'Confirmado',
        'delivered' => 'Entregado', 'pending_return' => 'Pendiente devolución', 'returned' => 'Devuelto',
        'cancelled' => 'Cancelado', 'overdue' => 'Vencido',
    ],
    'payment' => [
        'pending' => 'Pendiente', 'partial' => 'Parcial', 'paid' => 'Pagado', 'overdue' => 'Vencido',
    ],
    'commercial' => [
        'available' => 'Disponible', 'reserved' => 'Reservado', 'rented' => 'Alquilado',
        'sold' => 'Vendido', 'unavailable' => 'No disponible', 'maintenance' => 'En reparación',
    ],
    'condition' => [
        'new' => 'Nuevo', 'excellent' => 'Excelente', 'good' => 'Bueno',
        'repair' => 'En reparación', 'out_of_service' => 'Fuera de servicio',
    ],
    'sale' => [
        'pending' => 'Pendiente', 'completed' => 'Completada', 'cancelled' => 'Cancelada',
    ],
];
$lbl = static function (string $group, ?string $val) use ($labels): string {
    return $labels[$group][$val] ?? (string) $val;
};

$data = ['headers' => [], 'rows' => []];

switch ($report) {

    case 'ingresos-diarios':
        $filename = 'ingresos-diarios';
        $data['headers'] = ['Fecha', 'Pagos', 'Total recibido'];
        $rows = db_all(
            "SELECT DATE(COALESCE(paid_at, created_at)) AS dia,
                    COUNT(*) AS pagos, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE COALESCE(paid_at, created_at) BETWEEN :from AND :to
             GROUP BY DATE(COALESCE(paid_at, created_at))
             ORDER BY dia ASC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        foreach ($rows as $r) {
            $data['rows'][] = [format_date($r['dia']), (int) $r['pagos'], $num($r['total'])];
        }
        break;

    case 'ingresos-mensuales':
        $filename = 'ingresos-mensuales';
        $data['headers'] = ['Mes', 'Pagos', 'Total recibido'];
        $rows = db_all(
            "SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') AS mes,
                    COUNT(*) AS pagos, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE COALESCE(paid_at, created_at) >= :desde
             GROUP BY DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m')
             ORDER BY mes ASC",
            ['desde' => date('Y-m-01', strtotime('-11 months'))]
        );
        foreach ($rows as $r) {
            $data['rows'][] = [$r['mes'], (int) $r['pagos'], $num($r['total'])];
        }
        break;

    case 'alquileres-por-rango':
        $filename = 'alquileres';
        $data['headers'] = ['Número', 'Fecha creación', 'Cliente', 'Producto', 'Entrega', 'Devolución',
                            'Total', 'Pagado', 'Saldo', 'Estado pago', 'Estado alquiler'];
        $rows = db_all(
            "SELECT r.rental_number, r.created_at, r.delivery_date, r.return_date,
                    r.total_amount, r.initial_payment_paid, r.remaining_balance,
                    r.payment_status, r.rental_status,
                    cu.full_name AS customer_name,
                    (SELECT GROUP_CONCAT(p2.name ORDER BY ri.sort_order SEPARATOR ', ')
                     FROM rental_items ri JOIN products p2 ON p2.id = ri.product_id
                     WHERE ri.rental_id = r.id) AS product_name
             FROM rentals r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.created_at BETWEEN :from AND :to
             ORDER BY r.created_at DESC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        foreach ($rows as $r) {
            $data['rows'][] = [
                $r['rental_number'], format_date($r['created_at']),
                $r['customer_name'], $r['product_name'],
                format_date($r['delivery_date']), format_date($r['return_date']),
                $num($r['total_amount']), $num($r['initial_payment_paid']), $num($r['remaining_balance']),
                $lbl('payment', $r['payment_status']), $lbl('rental', $r['rental_status']),
            ];
        }
        break;

    case 'productos-mas-alquilados':
        $filename = 'productos-mas-alquilados';
        $data['headers'] = ['#', 'Producto', 'SKU', 'Categoría', 'Veces alquilado', 'Ingresos'];
        $rows = db_all(
            "SELECT p.name, p.sku, c.name AS category_name,
                    COUNT(ri.id) AS veces, COALESCE(SUM(ri.unit_price),0) AS ingresos
             FROM rentals r
             JOIN rental_items ri ON ri.rental_id = r.id
             JOIN products p ON p.id = ri.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE r.created_at BETWEEN :from AND :to AND r.rental_status <> 'cancelled'
             GROUP BY p.id, p.name, p.sku, c.name
             ORDER BY veces DESC, ingresos DESC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $i = 1;
        foreach ($rows as $r) {
            $data['rows'][] = [$i++, $r['name'], $r['sku'] ?? '', $r['category_name'] ?? 'General',
                               (int) $r['veces'], $num($r['ingresos'])];
        }
        break;

    case 'clientes-frecuentes':
        $filename = 'clientes-frecuentes';
        $data['headers'] = ['#', 'Cliente', 'Teléfono', 'Alquileres', 'Total facturado'];
        $rows = db_all(
            "SELECT cu.full_name, cu.phone,
                    COUNT(r.id) AS alquileres, COALESCE(SUM(r.total_amount),0) AS total
             FROM rentals r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.created_at BETWEEN :from AND :to AND r.rental_status <> 'cancelled'
             GROUP BY cu.id, cu.full_name, cu.phone
             ORDER BY alquileres DESC, total DESC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $i = 1;
        foreach ($rows as $r) {
            $data['rows'][] = [$i++, $r['full_name'], $r['phone'] ?? '',
                               (int) $r['alquileres'], $num($r['total'])];
        }
        break;

    case 'pagos-pendientes':
        $filename = 'pagos-pendientes';
        $data['headers'] = ['Alquiler', 'Cliente', 'Producto', 'Total', 'Pagado', 'Saldo',
                            'Estado pago', 'Estado alquiler', 'Fecha devolución'];
        $rows = db_all(
            "SELECT r.rental_number, r.total_amount, r.initial_payment_paid, r.remaining_balance,
                    r.payment_status, r.rental_status, r.return_date,
                    cu.full_name AS customer_name,
                    (SELECT GROUP_CONCAT(p2.name ORDER BY ri.sort_order SEPARATOR ', ')
                     FROM rental_items ri JOIN products p2 ON p2.id = ri.product_id
                     WHERE ri.rental_id = r.id) AS product_name
             FROM rentals r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.remaining_balance > 0 AND r.rental_status <> 'cancelled'
             ORDER BY r.remaining_balance DESC",
            []
        );
        foreach ($rows as $r) {
            $data['rows'][] = [
                $r['rental_number'], $r['customer_name'], $r['product_name'],
                $num($r['total_amount']), $num($r['initial_payment_paid']), $num($r['remaining_balance']),
                $lbl('payment', $r['payment_status']), $lbl('rental', $r['rental_status']),
                format_date($r['return_date']),
            ];
        }
        break;

    case 'inventario':
        $filename = 'inventario';
        $data['headers'] = ['Categoría', 'Estado comercial', 'Condición', 'Productos'];
        // Conteo combinado por estado comercial y condición.
        $rows = db_all(
            "SELECT c.name AS category_name, p.commercial_status, p.condition_status, COUNT(*) AS total
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'active'
             GROUP BY c.name, p.commercial_status, p.condition_status
             ORDER BY c.name ASC, total DESC",
            []
        );
        foreach ($rows as $r) {
            $data['rows'][] = [
                $r['category_name'] ?? 'Sin categoría',
                $lbl('commercial', $r['commercial_status']),
                $lbl('condition', $r['condition_status']),
                (int) $r['total'],
            ];
        }
        break;

    case 'ventas':
        $filename = 'ventas';
        $data['headers'] = ['Número', 'Fecha', 'Cliente', 'Producto', 'Precio', 'Descuento',
                            'Total', 'Estado pago', 'Estado'];
        $rows = db_all(
            "SELECT s.sale_number, s.created_at, s.sale_price, s.discount, s.total_amount,
                    s.payment_status, s.status,
                    cu.full_name AS customer_name, p.name AS product_name
             FROM sales s
             JOIN customers cu ON cu.id = s.customer_id
             JOIN products p ON p.id = s.product_id
             WHERE s.created_at BETWEEN :from AND :to
             ORDER BY s.created_at DESC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        foreach ($rows as $r) {
            $data['rows'][] = [
                $r['sale_number'], format_date($r['created_at']),
                $r['customer_name'], $r['product_name'],
                $num($r['sale_price']), $num($r['discount']), $num($r['total_amount']),
                $lbl('payment', $r['payment_status']), $lbl('sale', $r['status']),
            ];
        }
        break;

    case 'rentabilidad':
        $filename = 'rentabilidad-por-producto';
        $data['headers'] = ['Producto', 'SKU', 'Alquileres', 'Ingresos alquiler',
                            'Ingresos venta', 'Costo', 'Margen aproximado'];
        $rows = db_all(
            "SELECT p.name, p.sku, p.cost_price,
                    COALESCE((
                        SELECT SUM(ri.unit_price) FROM rental_items ri JOIN rentals r ON r.id = ri.rental_id
                        WHERE ri.product_id = p.id AND r.rental_status <> 'cancelled'
                          AND r.created_at BETWEEN :rf AND :rt
                    ),0) AS ingresos_alquiler,
                    COALESCE((
                        SELECT COUNT(*) FROM rental_items ri JOIN rentals r ON r.id = ri.rental_id
                        WHERE ri.product_id = p.id AND r.rental_status <> 'cancelled'
                          AND r.created_at BETWEEN :rf2 AND :rt2
                    ),0) AS num_alquileres,
                    COALESCE((
                        SELECT SUM(s.total_amount) FROM sales s
                        WHERE s.product_id = p.id AND s.status <> 'cancelled'
                          AND s.created_at BETWEEN :sf AND :st
                    ),0) AS ingresos_venta
             FROM products p
             WHERE p.status = 'active'
             HAVING (ingresos_alquiler + ingresos_venta) > 0
             ORDER BY (ingresos_alquiler + ingresos_venta) DESC",
            [
                'rf' => $from_dt, 'rt' => $to_dt,
                'rf2' => $from_dt, 'rt2' => $to_dt,
                'sf' => $from_dt, 'st' => $to_dt,
            ]
        );
        foreach ($rows as $r) {
            $ingresos = (float) $r['ingresos_alquiler'] + (float) $r['ingresos_venta'];
            $costo    = (float) ($r['cost_price'] ?? 0);
            $data['rows'][] = [
                $r['name'], $r['sku'] ?? '', (int) $r['num_alquileres'],
                $num($r['ingresos_alquiler']), $num($r['ingresos_venta']),
                $num($costo), $num($ingresos - $costo),
            ];
        }
        break;

    case 'cancelados':
        $filename = 'alquileres-cancelados';
        $data['headers'] = ['Alquiler', 'Fecha creación', 'Cliente', 'Producto', 'Total', 'Entrega', 'Devolución'];
        $rows = db_all(
            "SELECT r.rental_number, r.created_at, r.total_amount, r.delivery_date, r.return_date,
                    cu.full_name AS customer_name,
                    (SELECT GROUP_CONCAT(p2.name ORDER BY ri.sort_order SEPARATOR ', ')
                     FROM rental_items ri JOIN products p2 ON p2.id = ri.product_id
                     WHERE ri.rental_id = r.id) AS product_name
             FROM rentals r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.rental_status = 'cancelled' AND r.created_at BETWEEN :from AND :to
             ORDER BY r.created_at DESC",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        foreach ($rows as $r) {
            $data['rows'][] = [
                $r['rental_number'], format_date($r['created_at']),
                $r['customer_name'], $r['product_name'], $num($r['total_amount']),
                format_date($r['delivery_date']), format_date($r['return_date']),
            ];
        }
        break;

    case 'devoluciones-vencidas':
        $filename = 'devoluciones-vencidas';
        $data['headers'] = ['Alquiler', 'Cliente', 'Teléfono', 'Producto', 'Fecha devolución',
                            'Días de retraso', 'Saldo', 'Estado'];
        $rows = db_all(
            "SELECT r.rental_number, r.return_date, r.rental_status, r.remaining_balance,
                    cu.full_name AS customer_name, cu.phone,
                    (SELECT GROUP_CONCAT(p2.name ORDER BY ri.sort_order SEPARATOR ', ')
                     FROM rental_items ri JOIN products p2 ON p2.id = ri.product_id
                     WHERE ri.rental_id = r.id) AS product_name
             FROM rentals r
             JOIN customers cu ON cu.id = r.customer_id
             WHERE r.return_date < CURDATE()
               AND r.rental_status IN ('delivered','pending_return')
             ORDER BY r.return_date ASC",
            []
        );
        foreach ($rows as $r) {
            $dias = abs(days_between($r['return_date'], date('Y-m-d')));
            $data['rows'][] = [
                $r['rental_number'], $r['customer_name'], $r['phone'] ?? '', $r['product_name'],
                format_date($r['return_date']), (int) $dias, $num($r['remaining_balance']),
                $lbl('rental', $r['rental_status']),
            ];
        }
        break;

    case 'resumen-general':
    default:
        $report   = 'resumen-general';
        $filename = 'resumen-general';
        $data['headers'] = ['Indicador', 'Valor'];

        $period_income = (float) db_value(
            "SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE COALESCE(paid_at, created_at) BETWEEN :from AND :to",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $period_payments = (int) db_value(
            "SELECT COUNT(*) FROM payments
             WHERE COALESCE(paid_at, created_at) BETWEEN :from AND :to",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $rentals_count = (int) db_value(
            "SELECT COUNT(*) FROM rentals WHERE created_at BETWEEN :from AND :to",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $rentals_total = (float) db_value(
            "SELECT COALESCE(SUM(total_amount),0) FROM rentals
             WHERE created_at BETWEEN :from AND :to AND rental_status <> 'cancelled'",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $cancelled_count = (int) db_value(
            "SELECT COUNT(*) FROM rentals
             WHERE rental_status = 'cancelled' AND created_at BETWEEN :from AND :to",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $sales_count = (int) db_value(
            "SELECT COUNT(*) FROM sales
             WHERE created_at BETWEEN :from AND :to AND status <> 'cancelled'",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $sales_total = (float) db_value(
            "SELECT COALESCE(SUM(total_amount),0) FROM sales
             WHERE created_at BETWEEN :from AND :to AND status <> 'cancelled'",
            ['from' => $from_dt, 'to' => $to_dt]
        );
        $pending_total = (float) db_value(
            "SELECT COALESCE(SUM(remaining_balance),0) FROM rentals
             WHERE remaining_balance > 0 AND rental_status <> 'cancelled'",
            []
        );
        $pending_count = (int) db_value(
            "SELECT COUNT(*) FROM rentals
             WHERE remaining_balance > 0 AND rental_status <> 'cancelled'",
            []
        );
        $available_count = (int) db_value(
            "SELECT COUNT(*) FROM products WHERE status = 'active' AND commercial_status = 'available'"
        );
        $out_of_service_count = (int) db_value(
            "SELECT COUNT(*) FROM products WHERE status = 'active'
             AND condition_status IN ('repair','out_of_service')"
        );
        $overdue_count = (int) db_value(
            "SELECT COUNT(*) FROM rentals
             WHERE return_date < CURDATE() AND rental_status IN ('delivered','pending_return')"
        );

        $data['rows'] = [
            ['Ingresos del periodo', $num($period_income)],
            ['Cantidad de pagos', $period_payments],
            ['Alquileres creados', $rentals_count],
            ['Monto facturado en alquileres', $num($rentals_total)],
            ['Alquileres cancelados', $cancelled_count],
            ['Ventas realizadas', $sales_count],
            ['Monto vendido', $num($sales_total)],
            ['Saldo total por cobrar', $num($pending_total)],
            ['Alquileres con saldo pendiente', $pending_count],
            ['Productos disponibles', $available_count],
            ['Productos fuera de servicio / reparación', $out_of_service_count],
            ['Devoluciones vencidas', $overdue_count],
        ];
        break;
}

/* Registrar la actividad de exportación. */
log_activity('export', 'report', null, 'Exportó reporte CSV: ' . $report . ' (' . $from . ' a ' . $to . ')');

/* ------------------------------------------------------------------ *
 *  Envío del CSV
 * ------------------------------------------------------------------ */
$download_name = 'londres-' . $filename . '-' . $from . '_a_' . $to . '.csv';

// Limpiar cualquier buffer previo para no contaminar el CSV.
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $download_name . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM UTF-8 para que Excel reconozca los acentos correctamente.
fwrite($out, "\xEF\xBB\xBF");

// Metadatos del reporte (filas de contexto).
fputcsv($out, ['LONDRES Casa de Novias — Reporte: ' . $report]);
fputcsv($out, ['Periodo:', $from . ' a ' . $to]);
fputcsv($out, ['Generado:', date('Y-m-d H:i:s')]);
fputcsv($out, []); // línea en blanco separadora

// Encabezados de columnas.
fputcsv($out, $data['headers']);

// Filas de datos.
foreach ($data['rows'] as $row) {
    fputcsv($out, $row);
}

// Si no hubo datos, dejar constancia.
if (empty($data['rows'])) {
    fputcsv($out, ['Sin datos para el periodo seleccionado.']);
}

fclose($out);
exit;
