<?php
/**
 * Alquileres · Listado
 * LONDRES Casa de Novias
 *
 * Tabla de alquileres con filtros (q, rental_status, payment_status, rango de
 * fechas de entrega), tarjetas resumen y paginación.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

/* ------------------------------------------------------------------ *
 *  Filtros recibidos por GET
 * ------------------------------------------------------------------ */
$q             = get_param('q');
$rentalStatus  = get_param('rental_status');
$paymentStatus = get_param('payment_status');
$from          = get_param('from');   // delivery_date desde
$to            = get_param('to');     // delivery_date hasta

// Listas válidas (defensa contra valores arbitrarios en el WHERE)
$rentalStatuses  = ['pending','reserved','confirmed','delivered','pending_return','returned','cancelled','overdue'];
$paymentStatuses = ['pending','partial','paid','overdue'];

/* ------------------------------------------------------------------ *
 *  Construcción dinámica del WHERE (siempre con placeholders)
 * ------------------------------------------------------------------ */
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(r.rental_number LIKE :q OR c.full_name LIKE :q OR EXISTS (
        SELECT 1 FROM rental_items riq
        JOIN products pq ON pq.id = riq.product_id
        WHERE riq.rental_id = r.id AND (pq.name LIKE :q OR pq.sku LIKE :q OR pq.barcode LIKE :q)
    ))';
    $params['q'] = '%' . $q . '%';
}
if ($rentalStatus !== '' && in_array($rentalStatus, $rentalStatuses, true)) {
    $where[] = 'r.rental_status = :rs';
    $params['rs'] = $rentalStatus;
}
if ($paymentStatus !== '' && in_array($paymentStatus, $paymentStatuses, true)) {
    $where[] = 'r.payment_status = :ps';
    $params['ps'] = $paymentStatus;
}
if ($from !== '') {
    $where[] = 'r.delivery_date >= :from';
    $params['from'] = $from;
}
if ($to !== '') {
    $where[] = 'r.delivery_date <= :to';
    $params['to'] = $to;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ------------------------------------------------------------------ *
 *  Conteo + paginación
 * ------------------------------------------------------------------ */
$total = (int) db_value(
    "SELECT COUNT(*)
       FROM rentals r
       JOIN customers c ON c.id = r.customer_id
     $whereSql",
    $params
);

$pg = paginate($total, 15);

$rows = db_all(
    "SELECT r.*, c.full_name AS customer_name, c.whatsapp AS customer_whatsapp,
            p.name AS product_name, p.main_image AS product_image,
            (SELECT COUNT(*) FROM rental_items ric WHERE ric.rental_id = r.id) AS product_count
       FROM rentals r
       JOIN customers c ON c.id = r.customer_id
       JOIN products  p ON p.id = r.product_id
     $whereSql
     ORDER BY r.delivery_date DESC, r.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

/* ------------------------------------------------------------------ *
 *  Métricas para las tarjetas resumen
 * ------------------------------------------------------------------ */
$today = date('Y-m-d');

// Activos: estados que bloquean disponibilidad
$activeCount = (int) db_value(
    "SELECT COUNT(*) FROM rentals WHERE rental_status IN ('reserved','confirmed','delivered','pending_return')"
);
// Entregas de hoy (pendientes de entregar)
$deliveriesToday = (int) db_value(
    "SELECT COUNT(*) FROM rentals
      WHERE delivery_date = :t AND rental_status IN ('reserved','confirmed')",
    ['t' => $today]
);
// Devoluciones pendientes (entregados cuya fecha de devolución ya llegó o pasó)
$pendingReturns = (int) db_value(
    "SELECT COUNT(*) FROM rentals
      WHERE rental_status IN ('delivered','pending_return') AND return_date <= :t",
    ['t' => $today]
);
// Por cobrar: saldo pendiente de alquileres no cancelados/devueltos
$toCollect = (float) db_value(
    "SELECT COALESCE(SUM(remaining_balance),0) FROM rentals
      WHERE rental_status NOT IN ('cancelled') AND remaining_balance > 0"
);

$page_title    = 'Alquileres';
$page_subtitle = 'Gestión completa de reservas, entregas y devoluciones';
$active        = 'alquileres';
$header_actions = '<a href="' . admin_url('alquileres/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nuevo alquiler</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Tarjetas resumen -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?= metric_card('Alquileres activos', $activeCount, 'box', 'sky', 'Reservados, confirmados y entregados') ?>
    <?= metric_card('Entregas hoy', $deliveriesToday, 'truck', 'amber', format_date($today)) ?>
    <?= metric_card('Devoluciones pendientes', $pendingReturns, 'return', 'violet', 'Con fecha vencida o de hoy') ?>
    <?= metric_card('Por cobrar', money($toCollect), 'banknotes', 'red', 'Saldo total pendiente') ?>
</div>

<!-- Filtros -->
<form method="get" class="mt-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
        <div class="md:col-span-4">
            <label class="lcn-label" for="q">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
                <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="N.º, cliente o producto"
                       class="lcn-input pl-10">
            </div>
        </div>
        <div class="md:col-span-3">
            <label class="lcn-label" for="rental_status">Estado del alquiler</label>
            <select id="rental_status" name="rental_status" class="lcn-input">
                <option value="">Todos</option>
                <?php foreach ($rentalStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $rentalStatus === $s ? 'selected' : '' ?>>
                        <?= e(strip_tags(status_badge($s, 'rental'))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="lcn-label" for="payment_status">Pago</label>
            <select id="payment_status" name="payment_status" class="lcn-input">
                <option value="">Todos</option>
                <?php foreach ($paymentStatuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $paymentStatus === $s ? 'selected' : '' ?>>
                        <?= e(strip_tags(status_badge($s, 'payment'))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-3 grid grid-cols-2 gap-3">
            <div>
                <label class="lcn-label" for="from">Entrega desde</label>
                <input type="date" id="from" name="from" value="<?= e($from) ?>" class="lcn-input">
            </div>
            <div>
                <label class="lcn-label" for="to">Entrega hasta</label>
                <input type="date" id="to" name="to" value="<?= e($to) ?>" class="lcn-input">
            </div>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            <?= icon('filter', 'w-4 h-4') ?> Filtrar
        </button>
        <?php if ($q !== '' || $rentalStatus !== '' || $paymentStatus !== '' || $from !== '' || $to !== ''): ?>
            <a href="<?= admin_url('alquileres/index.php') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('x', 'w-4 h-4') ?> Limpiar
            </a>
        <?php endif; ?>
        <span class="ml-auto text-sm text-gray-400"><?= number_format($total) ?> resultado(s)</span>
    </div>
</form>

<!-- Tabla -->
<div class="mt-6">
    <?php if (!$rows): ?>
        <?= empty_state(
            'No hay alquileres',
            'No se encontraron alquileres con los criterios actuales. Crea uno nuevo para comenzar.',
            'box',
            '<a href="' . admin_url('alquileres/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo alquiler</a>'
        ) ?>
    <?php else: ?>
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">N.º</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Evento</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Entrega</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Devolución</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($rows as $r):
                            $overdue = in_array($r['rental_status'], ['delivered','pending_return'], true)
                                       && $r['return_date'] < $today; ?>
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-4 text-gray-700">
                                    <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $r['id']) ?>" class="font-semibold text-brand-red hover:underline"><?= e($r['rental_number']) ?></a>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <div class="flex items-center gap-2.5">
                                        <?= avatar($r['customer_name'], 'h-8 w-8 text-xs') ?>
                                        <span class="font-medium text-gray-900"><?= e($r['customer_name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= e(upload_url($r['product_image'])) ?>" alt="" class="h-9 w-9 flex-none rounded-lg object-cover ring-1 ring-gray-100">
                                        <span class="line-clamp-1"><?= e($r['product_name']) ?><?= (int) $r['product_count'] > 1 ? ' +' . ((int) $r['product_count'] - 1) : '' ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-700"><?= format_date($r['event_date']) ?></td>
                                <td class="px-5 py-4 text-gray-700">
                                    <?= format_date($r['delivery_date']) ?>
                                    <?php if (format_time($r['delivery_time']) !== ''): ?>
                                        <span class="block text-xs font-medium text-brand-red"><?= e(format_time($r['delivery_time'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <span class="<?= $overdue ? 'font-semibold text-rose-600' : '' ?>"><?= format_date($r['return_date']) ?></span>
                                    <?php if ($overdue): ?><span class="ml-1 text-xs text-rose-500">(vencido)</span><?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($r['total_amount'])) ?></td>
                                <td class="px-5 py-4 text-right">
                                    <span class="<?= ((float) $r['remaining_balance']) > 0 ? 'font-semibold text-rose-600' : 'text-emerald-600' ?>">
                                        <?= e(money($r['remaining_balance'])) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4"><?= status_badge($r['rental_status'], 'rental') ?></td>
                                <td class="px-5 py-4"><?= status_badge($r['payment_status'], 'payment') ?></td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end">
                                        <?= row_menu('menu-rental-' . (int) $r['id'], [
                                            ['label' => 'Ver detalle',     'url' => admin_url('alquileres/ver.php?id=' . (int) $r['id']),     'icon' => 'eye'],
                                            ['label' => 'Editar',           'url' => admin_url('alquileres/editar.php?id=' . (int) $r['id']),  'icon' => 'pencil'],
                                            ['label' => 'Registrar pago',   'url' => admin_url('pagos/crear.php?rental=' . (int) $r['id']),    'icon' => 'banknotes'],
                                            ['label' => 'Imprimir contrato','url' => admin_url('alquileres/contrato.php?id=' . (int) $r['id']),'icon' => 'document'],
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
                <p class="text-sm text-gray-400">Página <?= $pg['page'] ?> de <?= $pg['pages'] ?></p>
                <?= render_pagination($pg['page'], $pg['pages']) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
