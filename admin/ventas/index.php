<?php
/**
 * Listado de ventas — LONDRES Casa de Novias
 * Tabla de sales (JOIN customers, products) con filtros, resumen y paginación.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('sales.manage');

/* ------------------------------------------------------------------ *
 *  Filtros de búsqueda
 * ------------------------------------------------------------------ */
$q      = get_param('q');
$status = get_param('status');

// Estados válidos del enum sales.status (evita inyectar valores arbitrarios)
$validStatuses = ['pending', 'completed', 'cancelled'];
if ($status !== '' && !in_array($status, $validStatuses, true)) {
    $status = '';
}

/* ------------------------------------------------------------------ *
 *  Construcción dinámica del WHERE (siempre con placeholders)
 * ------------------------------------------------------------------ */
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(s.sale_number LIKE :q OR c.full_name LIKE :q OR p.name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($status !== '') {
    $where[] = 's.status = :status';
    $params['status'] = $status;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ------------------------------------------------------------------ *
 *  Paginación
 * ------------------------------------------------------------------ */
$total = (int) db_value(
    "SELECT COUNT(*)
     FROM sales s
     JOIN customers c ON c.id = s.customer_id
     JOIN products  p ON p.id = s.product_id
     $whereSql",
    $params
);
$pg = paginate($total, 12);

$rows = db_all(
    "SELECT s.*, c.full_name AS customer_name, p.name AS product_name,
            (SELECT i.id FROM invoices i WHERE i.sale_id = s.id ORDER BY i.id ASC LIMIT 1) AS invoice_id
     FROM sales s
     JOIN customers c ON c.id = s.customer_id
     JOIN products  p ON p.id = s.product_id
     $whereSql
     ORDER BY s.created_at DESC, s.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

/* ------------------------------------------------------------------ *
 *  Tarjetas de resumen (mes en curso, no canceladas)
 * ------------------------------------------------------------------ */
$monthSales = (int) db_value(
    "SELECT COUNT(*) FROM sales
     WHERE status <> 'cancelled'
       AND YEAR(created_at) = YEAR(CURDATE())
       AND MONTH(created_at) = MONTH(CURDATE())"
);
$monthRevenue = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales
     WHERE status <> 'cancelled'
       AND YEAR(created_at) = YEAR(CURDATE())
       AND MONTH(created_at) = MONTH(CURDATE())"
);
$totalRevenue = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE status <> 'cancelled'"
);

$page_title    = 'Ventas';
$page_subtitle = 'Gestión de ventas de vestidos, trajes y accesorios';
$active        = 'ventas';
$header_actions = '<a href="' . admin_url('ventas/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nueva venta</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Tarjetas de resumen -->
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <?= metric_card('Ventas del mes', (string) $monthSales, 'bag', 'violet', 'Mes en curso') ?>
    <?= metric_card('Ingresos del mes', money($monthRevenue), 'banknotes', 'emerald', 'Ventas no canceladas') ?>
    <?= metric_card('Ingresos totales', money($totalRevenue), 'chart', 'gold', 'Histórico de ventas') ?>
</div>

<!-- Filtros -->
<form method="get" class="mb-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="q" class="lcn-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
                <input type="search" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="N.º de venta, cliente o producto…"
                       class="lcn-input pl-10">
            </div>
        </div>
        <div class="sm:w-56">
            <label for="status" class="lcn-label">Estado</label>
            <select id="status" name="status" class="lcn-input">
                <option value="">Todos los estados</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completada</option>
                <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pendiente</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('filter', 'w-4 h-4') ?> Filtrar
            </button>
            <?php if ($q !== '' || $status !== ''): ?>
                <a href="<?= admin_url('ventas/index.php') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('x', 'w-4 h-4') ?> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$rows): ?>
    <?= empty_state(
        'Sin ventas registradas',
        ($q !== '' || $status !== '')
            ? 'No se encontraron ventas con los filtros aplicados. Pruebe ajustar la búsqueda.'
            : 'Aún no se ha registrado ninguna venta. Comience registrando la primera.',
        'bag',
        '<a href="' . admin_url('ventas/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
            . icon('plus', 'w-4 h-4') . ' Nueva venta</a>'
    ) ?>
<?php else: ?>
    <!-- Tabla de ventas -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">N.º venta</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($rows as $r): ?>
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-4 font-semibold text-gray-900"><?= e($r['sale_number']) ?></td>
                            <td class="px-5 py-4 text-gray-700"><?= e(format_date($r['created_at'])) ?></td>
                            <td class="px-5 py-4 text-gray-700">
                                <div class="flex items-center gap-2.5">
                                    <?= avatar($r['customer_name'], 'h-8 w-8 text-xs') ?>
                                    <span class="font-medium text-gray-900"><?= e($r['customer_name']) ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-gray-700"><?= e($r['product_name']) ?></td>
                            <td class="px-5 py-4 font-semibold text-gray-900"><?= e(money($r['total_amount'])) ?></td>
                            <td class="px-5 py-4"><?= status_badge($r['payment_status'], 'payment') ?></td>
                            <td class="px-5 py-4"><?= status_badge($r['status'], 'sale') ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if (!empty($r['invoice_id'])): ?>
                                        <a href="<?= admin_url('facturas/imprimir.php?id=' . (int) $r['invoice_id']) ?>"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                           title="Ver / imprimir factura">
                                            <?= icon('document', 'w-4 h-4') ?> Factura
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-400" title="Sin factura asociada">
                                            <?= icon('document', 'w-4 h-4') ?> Sin factura
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <?php if ($pg['pages'] > 1): ?>
        <div class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-gray-500">
                Mostrando <span class="font-medium text-gray-700"><?= count($rows) ?></span>
                de <span class="font-medium text-gray-700"><?= $total ?></span> ventas
            </p>
            <?= render_pagination($pg['page'], $pg['pages']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
