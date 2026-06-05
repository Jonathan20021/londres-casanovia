<?php
/**
 * Facturas — Listado con filtros, resumen y paginación.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('invoices.manage');

/* ------------------------------------------------------------------ *
 *  Filtros de búsqueda
 * ------------------------------------------------------------------ */
$q      = get_param('q');
$status = get_param('status');   // pending|partial|paid|void
$type   = get_param('type');     // rental|sale

$validStatuses = ['pending', 'partial', 'paid', 'void'];
$validTypes    = ['rental', 'sale'];

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(i.invoice_number LIKE :q OR c.full_name LIKE :q OR c.phone LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if (in_array($status, $validStatuses, true)) {
    $where[] = 'i.status = :status';
    $params['status'] = $status;
}
if (in_array($type, $validTypes, true)) {
    $where[] = 'i.invoice_type = :type';
    $params['type'] = $type;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ------------------------------------------------------------------ *
 *  Conteo + paginación
 * ------------------------------------------------------------------ */
$total = (int) db_value(
    "SELECT COUNT(*) FROM invoices i JOIN customers c ON c.id = i.customer_id $whereSql",
    $params
);
$pg = paginate($total, 12);

$rows = db_all(
    "SELECT i.*, c.full_name AS customer_name
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     $whereSql
     ORDER BY i.issued_at DESC, i.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

/* ------------------------------------------------------------------ *
 *  Tarjetas de resumen (sobre las facturas NO anuladas)
 * ------------------------------------------------------------------ */
$summary = db_one(
    "SELECT
        COALESCE(SUM(total), 0)       AS billed,
        COALESCE(SUM(paid_amount), 0) AS collected,
        COALESCE(SUM(balance), 0)     AS receivable
     FROM invoices
     WHERE status <> 'void'"
) ?: ['billed' => 0, 'collected' => 0, 'receivable' => 0];

$page_title    = 'Facturas';
$page_subtitle = 'Gestión de facturación de alquileres y ventas';
$active        = 'facturas';
$header_actions = '<a href="' . e(admin_url('facturas/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nueva factura</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Tarjetas de resumen -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <?= metric_card('Total facturado', money($summary['billed']), 'document', 'red', 'Facturas vigentes') ?>
    <?= metric_card('Total cobrado', money($summary['collected']), 'banknotes', 'emerald', 'Pagos recibidos') ?>
    <?= metric_card('Por cobrar', money($summary['receivable']), 'clock', 'amber', 'Saldo pendiente') ?>
</div>

<!-- Filtros -->
<form method="get" class="mt-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:items-end">
        <div class="sm:col-span-5">
            <label class="lcn-label" for="q">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
                <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="N.º factura, cliente o teléfono…" class="lcn-input pl-10">
            </div>
        </div>
        <div class="sm:col-span-3">
            <label class="lcn-label" for="status">Estado</label>
            <select id="status" name="status" class="lcn-input">
                <option value="">Todos</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Parcialmente pagada</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Pagada</option>
                <option value="void" <?= $status === 'void' ? 'selected' : '' ?>>Anulada</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="lcn-label" for="type">Tipo</label>
            <select id="type" name="type" class="lcn-input">
                <option value="">Todos</option>
                <option value="rental" <?= $type === 'rental' ? 'selected' : '' ?>>Alquiler</option>
                <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>Venta</option>
            </select>
        </div>
        <div class="flex gap-2 sm:col-span-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('filter', 'w-4 h-4') ?> Filtrar
            </button>
            <?php if ($q !== '' || $status !== '' || $type !== ''): ?>
                <a href="<?= e(admin_url('facturas/index.php')) ?>" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50" title="Limpiar filtros">
                    <?= icon('x', 'w-4 h-4') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Tabla -->
<div class="mt-6">
    <?php if (!$rows): ?>
        <?= empty_state(
            'No hay facturas',
            ($q !== '' || $status !== '' || $type !== '')
                ? 'No se encontraron facturas con los filtros aplicados.'
                : 'Aún no se ha generado ninguna factura.',
            'document',
            '<a href="' . e(admin_url('facturas/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nueva factura</a>'
        ) ?>
    <?php else: ?>
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Factura</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pagado</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($rows as $r): ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-4 text-gray-700">
                                    <a href="<?= e(admin_url('facturas/ver.php?id=' . (int) $r['id'])) ?>" class="font-semibold text-brand-red hover:underline"><?= e($r['invoice_number']) ?></a>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <div class="flex items-center gap-2.5">
                                        <?= avatar($r['customer_name'], 'h-8 w-8 text-xs') ?>
                                        <span class="font-medium text-gray-900"><?= e($r['customer_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                        <?= icon($r['invoice_type'] === 'sale' ? 'bag' : 'box', 'w-3.5 h-3.5') ?>
                                        <?= $r['invoice_type'] === 'sale' ? 'Venta' : 'Alquiler' ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-gray-700"><?= format_date($r['issued_at']) ?></td>
                                <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($r['total'])) ?></td>
                                <td class="px-5 py-4 text-right text-emerald-600"><?= e(money($r['paid_amount'])) ?></td>
                                <td class="px-5 py-4 text-right <?= (float) $r['balance'] > 0 ? 'font-semibold text-rose-600' : 'text-gray-400' ?>"><?= e(money($r['balance'])) ?></td>
                                <td class="px-5 py-4"><?= status_badge($r['status'], 'invoice') ?></td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end">
                                        <?= row_menu('menu-invoice-' . (int) $r['id'], [
                                            ['label' => 'Ver factura',    'url' => admin_url('facturas/ver.php?id=' . (int) $r['id']),       'icon' => 'eye'],
                                            ['label' => 'Imprimir / PDF',  'url' => admin_url('facturas/imprimir.php?id=' . (int) $r['id']),  'icon' => 'printer'],
                                            ['label' => 'Descargar PDF',   'url' => admin_url('facturas/imprimir.php?id=' . (int) $r['id'] . '&pdf=1'), 'icon' => 'download'],
                                        ]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pg['pages'] > 1): ?>
            <div class="mt-5 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Mostrando <span class="font-medium text-gray-700"><?= count($rows) ?></span> de <span class="font-medium text-gray-700"><?= (int) $pg['total'] ?></span> facturas
                </p>
                <?= render_pagination($pg['page'], $pg['pages']) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
