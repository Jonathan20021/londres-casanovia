<?php
/**
 * Listado de pagos (recibos) — LONDRES Casa de Novias
 * Tabla de payments con JOIN a clientes y a alquiler/venta/factura.
 * Filtros: q (texto), método de pago y rango de fechas.
 * Permiso: payments.manage · Sidebar: pagos
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('payments.manage');

/* ------------------------------------------------------------------ *
 *  Etiquetas en español para los métodos de pago
 * ------------------------------------------------------------------ */
$METHOD_LABELS = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

/* ------------------------------------------------------------------ *
 *  Filtros de búsqueda
 * ------------------------------------------------------------------ */
$q        = get_param('q');
$method   = get_param('method');
$from     = get_param('from');   // YYYY-MM-DD
$to       = get_param('to');     // YYYY-MM-DD

// Validar método contra la lista permitida
if ($method !== '' && !isset($METHOD_LABELS[$method])) {
    $method = '';
}

/* Construcción dinámica del WHERE con prepared statements */
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(p.payment_number LIKE :q OR c.full_name LIKE :q
                 OR r.rental_number LIKE :q OR s.sale_number LIKE :q
                 OR i.invoice_number LIKE :q OR p.reference LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($method !== '') {
    $where[] = 'p.payment_method = :method';
    $params['method'] = $method;
}
if ($from !== '') {
    $where[] = 'p.paid_at >= :from';
    $params['from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'p.paid_at <= :to';
    $params['to'] = $to . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Base de JOINs reutilizable para conteo, total y listado */
$baseFrom = "FROM payments p
             LEFT JOIN customers c ON c.id = p.customer_id
             LEFT JOIN rentals   r ON r.id = p.rental_id
             LEFT JOIN sales     s ON s.id = p.sale_id
             LEFT JOIN invoices  i ON i.id = p.invoice_id
             LEFT JOIN users     u ON u.id = p.received_by
             $whereSql";

/* ------------------------------------------------------------------ *
 *  Totales del periodo filtrado + paginación
 * ------------------------------------------------------------------ */
$total       = (int) db_value("SELECT COUNT(*) $baseFrom", $params);
$totalAmount = (float) db_value("SELECT COALESCE(SUM(p.amount),0) $baseFrom", $params);

$pg = paginate($total, 15);

$rows = db_all(
    "SELECT p.*, c.full_name AS customer_name,
            r.rental_number, s.sale_number, i.invoice_number,
            u.name AS received_by_name
     $baseFrom
     ORDER BY p.paid_at DESC, p.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

$page_title    = 'Pagos';
$page_subtitle = 'Recibos de cobro de alquileres y ventas';
$active        = 'pagos';
$header_actions = '<a href="' . e(admin_url('pagos/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Registrar pago</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Tarjeta resumen: total cobrado del periodo filtrado -->
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <?= metric_card(
        'Total cobrado' . ($from || $to || $q || $method ? ' (periodo)' : ''),
        money($totalAmount),
        'banknotes',
        'emerald',
        $total . ' ' . ($total === 1 ? 'pago registrado' : 'pagos registrados')
    ) ?>
</div>

<!-- Filtros -->
<form method="get" class="mb-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft sm:p-5">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label class="lcn-label" for="q">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
                <input type="search" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="N.º recibo, cliente, alquiler, venta…"
                       class="lcn-input pl-10">
            </div>
        </div>

        <div>
            <label class="lcn-label" for="method">Método</label>
            <select id="method" name="method" class="lcn-input">
                <option value="">Todos</option>
                <?php foreach ($METHOD_LABELS as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $method === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="lcn-label" for="from">Desde</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>" class="lcn-input">
        </div>

        <div>
            <label class="lcn-label" for="to">Hasta</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>" class="lcn-input">
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            <?= icon('filter', 'w-4 h-4') ?> Filtrar
        </button>
        <?php if ($q !== '' || $method !== '' || $from !== '' || $to !== ''): ?>
            <a href="<?= e(admin_url('pagos/index.php')) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('x', 'w-4 h-4') ?> Limpiar
            </a>
        <?php endif; ?>
    </div>
</form>

<!-- Tabla de pagos -->
<?php if (!$rows): ?>
    <?= empty_state(
        'Sin pagos registrados',
        ($q !== '' || $method !== '' || $from !== '' || $to !== '')
            ? 'No se encontraron pagos con los filtros aplicados. Pruebe a ajustarlos.'
            : 'Aún no se ha registrado ningún pago. Comience registrando el primer cobro.',
        'banknotes',
        '<a href="' . e(admin_url('pagos/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
        . icon('plus', 'w-4 h-4') . ' Registrar pago</a>'
    ) ?>
<?php else: ?>
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibo</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Concepto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Método</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibido por</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($rows as $p):
                        // Determinar el concepto (alquiler / venta / factura)
                        if (!empty($p['rental_number'])) {
                            $conceptIcon  = 'box';
                            $conceptLabel = 'Alquiler';
                            $conceptRef   = $p['rental_number'];
                        } elseif (!empty($p['sale_number'])) {
                            $conceptIcon  = 'bag';
                            $conceptLabel = 'Venta';
                            $conceptRef   = $p['sale_number'];
                        } elseif (!empty($p['invoice_number'])) {
                            $conceptIcon  = 'document';
                            $conceptLabel = 'Factura';
                            $conceptRef   = $p['invoice_number'];
                        } else {
                            $conceptIcon  = 'banknotes';
                            $conceptLabel = 'Pago directo';
                            $conceptRef   = '';
                        }
                        $methodLabel = $METHOD_LABELS[$p['payment_method']] ?? ucfirst((string) $p['payment_method']);
                    ?>
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-4">
                                <span class="font-semibold text-gray-900"><?= e($p['payment_number']) ?></span>
                                <?php if (!empty($p['reference'])): ?>
                                    <p class="text-xs text-gray-400">Ref. <?= e($p['reference']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <span class="block"><?= e(format_date($p['paid_at'])) ?></span>
                                <span class="block text-xs text-gray-400"><?= e(format_date($p['paid_at'], 'h:i A')) ?></span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2.5">
                                    <?= avatar($p['customer_name'] ?? 'Cliente', 'h-8 w-8 text-xs') ?>
                                    <span class="font-medium text-gray-900"><?= e($p['customer_name'] ?? 'Cliente eliminado') ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <span class="inline-flex items-center gap-2">
                                    <span class="text-gray-400"><?= icon($conceptIcon, 'w-4 h-4') ?></span>
                                    <span>
                                        <span class="block font-medium text-gray-700"><?= e($conceptLabel) ?></span>
                                        <?php if ($conceptRef !== ''): ?>
                                            <span class="block text-xs text-gray-400"><?= e($conceptRef) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"><?= e($methodLabel) ?></span>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-gray-900"><?= e(money($p['amount'])) ?></td>
                            <td class="px-5 py-4 text-gray-600"><?= e($p['received_by_name'] ?? '—') ?></td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= e(admin_url('pagos/recibo.php?id=' . (int) $p['id'])) ?>" target="_blank"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                   title="Ver / imprimir recibo">
                                    <?= icon('printer', 'w-4 h-4') ?> Recibo
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pg['pages'] > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-4">
                <p class="text-xs text-gray-500">
                    Mostrando <?= count($rows) ?> de <?= e((string) $total) ?> pagos
                </p>
                <?= render_pagination($pg['page'], $pg['pages']) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
