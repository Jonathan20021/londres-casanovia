<?php
/**
 * Panel principal (Dashboard).
 * Métricas del negocio, gráficos en CSS/SVG y listados operativos.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$u = current_user();

/* ================================================================== *
 *  MÉTRICAS PRINCIPALES (consultas reales)
 * ================================================================== */

// Inventario
$totalProductos = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active'");
$disponibles    = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active' AND commercial_status = 'available'");
$reservados     = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active' AND commercial_status = 'reserved'");
$alquilados     = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active' AND commercial_status = 'rented'");

// Operación financiera del mes en curso
$alquileresMes = (int) db_value(
    "SELECT COUNT(*) FROM rentals
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$ingresosMes = (float) db_value(
    "SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE paid_at IS NOT NULL
       AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())"
);

// Saldos por cobrar (alquileres aún vigentes)
$pagosPendientes = (float) db_value(
    "SELECT COALESCE(SUM(remaining_balance), 0) FROM rentals
     WHERE rental_status NOT IN ('cancelled', 'returned')"
);

// Solicitudes públicas pendientes
$solicitudesPendientes = (int) db_value("SELECT COUNT(*) FROM rental_requests WHERE status = 'pending'");

// Entregas programadas para hoy
$entregasHoy = (int) db_value(
    "SELECT COUNT(*) FROM rentals
     WHERE delivery_date = CURDATE() AND rental_status IN ('reserved', 'confirmed')"
);

// Devoluciones para hoy o vencidas
$devolucionesHoy = (int) db_value(
    "SELECT COUNT(*) FROM rentals
     WHERE return_date <= CURDATE() AND rental_status IN ('delivered', 'pending_return')"
);

/* ================================================================== *
 *  GRÁFICO: INGRESOS ÚLTIMOS 6 MESES (barras verticales en CSS)
 * ================================================================== */
$ingresosPorMes = db_all(
    "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE paid_at IS NOT NULL
       AND paid_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY ym
     ORDER BY ym ASC"
);

// Indexar resultados por año-mes para luego rellenar los 6 meses completos.
$ingresosMap = [];
foreach ($ingresosPorMes as $row) {
    $ingresosMap[$row['ym']] = (float) $row['total'];
}

$mesesCortos = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',
                7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

$chartIngresos = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("first day of -$i month");
    $ym  = date('Y-m', $ts);
    $mes = (int) date('n', $ts);
    $chartIngresos[] = [
        'label' => $mesesCortos[$mes],
        'value' => $ingresosMap[$ym] ?? 0.0,
    ];
}
$maxIngreso = 0.0;
foreach ($chartIngresos as $c) { $maxIngreso = max($maxIngreso, $c['value']); }

/* ================================================================== *
 *  GRÁFICO: ALQUILERES POR CATEGORÍA (barras horizontales)
 * ================================================================== */
$alquileresPorCategoria = db_all(
    "SELECT COALESCE(c.name, 'Sin categoría') AS category_name, COUNT(r.id) AS total
     FROM rentals r
     JOIN rental_items ri ON ri.rental_id = r.id
     JOIN products p ON p.id = ri.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     GROUP BY c.id, category_name
     ORDER BY total DESC, category_name ASC
     LIMIT 6"
);
$maxCategoria = 0;
foreach ($alquileresPorCategoria as $c) { $maxCategoria = max($maxCategoria, (int) $c['total']); }

/* ================================================================== *
 *  TABLA: PRÓXIMOS ALQUILERES
 * ================================================================== */
$proximosAlquileres = db_all(
    "SELECT r.id, r.rental_number, r.event_date, r.delivery_date, r.return_date,
            r.rental_status, r.initial_payment_paid, r.remaining_balance,
            c.full_name AS customer_name, p.name AS product_name,
            (SELECT COUNT(*) FROM rental_items ric WHERE ric.rental_id = r.id) AS product_count
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     JOIN products  p ON p.id = r.product_id
     WHERE r.rental_status IN ('pending', 'reserved', 'confirmed', 'delivered', 'pending_return')
     ORDER BY r.delivery_date ASC
     LIMIT 8"
);

/* ================================================================== *
 *  TARJETA LATERAL: SOLICITUDES PENDIENTES
 * ================================================================== */
$solicitudes = db_all(
    "SELECT rr.id, rr.full_name, rr.event_date, rr.created_at, p.name AS product_name
     FROM rental_requests rr
     LEFT JOIN products p ON p.id = rr.product_id
     WHERE rr.status = 'pending'
     ORDER BY rr.created_at DESC
     LIMIT 6"
);

/* ================================================================== *
 *  TARJETA: PRODUCTOS MÁS ALQUILADOS
 * ================================================================== */
$masAlquilados = db_all(
    "SELECT p.id, p.name, p.main_image, COALESCE(c.name, 'General') AS category_name,
            COUNT(r.id) AS total
     FROM rentals r
     JOIN rental_items ri ON ri.rental_id = r.id
     JOIN products p ON p.id = ri.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     GROUP BY p.id, p.name, p.main_image, category_name
     ORDER BY total DESC, p.name ASC
     LIMIT 5"
);

/* ================================================================== *
 *  DATOS PARA GRÁFICOS INTERACTIVOS (ApexCharts)
 * ================================================================== */
$dashData = [
    'currency'   => setting('currency', 'RD$'),
    'revenue'    => [
        'labels' => array_map(fn($c) => $c['label'], $chartIngresos),
        'data'   => array_map(fn($c) => round((float) $c['value'], 2), $chartIngresos),
    ],
    'categories' => [
        'labels' => array_map(fn($c) => $c['category_name'], $alquileresPorCategoria),
        'data'   => array_map(fn($c) => (int) $c['total'], $alquileresPorCategoria),
    ],
];
$use_charts = true;

/* ================================================================== *
 *  RENDER
 * ================================================================== */
$page_title    = 'Dashboard';
$page_subtitle = 'Resumen general · ' . format_date(date('Y-m-d'), 'd \d\e F \d\e Y');
$active        = 'dashboard';
$header_actions = ''
    . (user_can('rentals.manage')
        ? '<a href="' . admin_url('alquileres/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo alquiler</a>'
        : '')
    . '<a href="' . admin_url('reportes/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('chart', 'w-4 h-4') . ' Reportes</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- ===================== MÉTRICAS ===================== -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?= metric_card('Productos activos', $totalProductos, 'box', 'gray', 'En el catálogo') ?>
    <?= metric_card('Disponibles', $disponibles, 'check', 'emerald', 'Listos para alquilar') ?>
    <?= metric_card('Reservados', $reservados, 'calendar', 'sky', 'Con reserva activa') ?>
    <?= metric_card('Alquilados', $alquilados, 'truck', 'amber', 'Entregados actualmente') ?>
    <?= metric_card('Alquileres del mes', $alquileresMes, 'document', 'violet', format_date(date('Y-m-d'), 'F Y')) ?>
    <?= metric_card('Ingresos del mes', money($ingresosMes), 'banknotes', 'emerald', 'Pagos recibidos') ?>
    <?= metric_card('Por cobrar', money($pagosPendientes), 'clock', 'red', 'Saldos pendientes') ?>
    <?= metric_card('Solicitudes', $solicitudesPendientes, 'inbox', 'gold', 'Pendientes de revisión') ?>
</div>

<!-- ===================== ALERTAS OPERATIVAS DEL DÍA ===================== -->
<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="flex items-center gap-4 rounded-2xl border border-sky-100 bg-sky-50/60 p-5 shadow-soft">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600"><?= icon('truck', 'w-6 h-6') ?></span>
        <div class="min-w-0">
            <p class="text-sm text-sky-700">Entregas para hoy</p>
            <p class="text-2xl font-semibold text-sky-900"><?= $entregasHoy ?></p>
        </div>
        <a href="<?= admin_url('alquileres/index.php') ?>" class="ml-auto text-sm font-medium text-sky-700 hover:underline">Ver</a>
    </div>
    <div class="flex items-center gap-4 rounded-2xl border border-rose-100 bg-rose-50/60 p-5 shadow-soft">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600"><?= icon('return', 'w-6 h-6') ?></span>
        <div class="min-w-0">
            <p class="text-sm text-rose-700">Devoluciones para hoy o vencidas</p>
            <p class="text-2xl font-semibold text-rose-900"><?= $devolucionesHoy ?></p>
        </div>
        <a href="<?= admin_url('alquileres/index.php') ?>" class="ml-auto text-sm font-medium text-rose-700 hover:underline">Ver</a>
    </div>
</div>

<!-- ===================== GRÁFICOS INTERACTIVOS ===================== -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

    <!-- Ingresos (área interactiva) -->
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft lg:col-span-2">
        <div class="mb-2 flex items-start justify-between">
            <div>
                <h2 class="font-serif text-xl font-semibold text-gray-900">Ingresos</h2>
                <p class="text-sm text-gray-500">Pagos recibidos · últimos 6 meses</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total 6 meses</p>
                <p class="font-serif text-2xl font-semibold text-brand-red"><?= e(money(array_sum(array_column($chartIngresos, 'value')))) ?></p>
            </div>
        </div>
        <div id="chart-ingresos" class="-mx-2 mt-2"></div>
    </div>

    <!-- Alquileres por categoría (donut interactivo) -->
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-2 flex items-center justify-between">
            <div>
                <h2 class="font-serif text-xl font-semibold text-gray-900">Por categoría</h2>
                <p class="text-sm text-gray-500">Distribución de alquileres</p>
            </div>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><?= icon('squares', 'w-5 h-5') ?></span>
        </div>
        <?php if (!$alquileresPorCategoria): ?>
            <div class="py-6"><?= empty_state('Sin alquileres aún', 'Cuando registres alquileres verás aquí su distribución.', 'tag') ?></div>
        <?php else: ?>
            <div id="chart-categorias" class="mt-2"></div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== PRÓXIMOS ALQUILERES + LATERALES ===================== -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

    <!-- Tabla próximos alquileres -->
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h2 class="font-serif text-lg font-bold text-gray-900">Próximos alquileres</h2>
                    <p class="text-sm text-gray-500">Reservas y entregas en curso</p>
                </div>
                <a href="<?= admin_url('alquileres/index.php') ?>" class="text-sm font-medium text-brand-red hover:underline">Ver todos</a>
            </div>

            <?php if (!$proximosAlquileres): ?>
                <div class="p-5">
                    <?= empty_state('No hay alquileres próximos', 'Las nuevas reservas aparecerán aquí ordenadas por fecha de entrega.', 'calendar') ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Evento</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Entrega</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Devolución</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Pago inicial</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($proximosAlquileres as $r): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 text-gray-700">
                                        <div class="flex items-center gap-2.5">
                                            <?= avatar($r['customer_name'], 'h-8 w-8 text-xs') ?>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-gray-900"><?= e($r['customer_name']) ?></p>
                                                <p class="text-xs text-gray-400"><?= e($r['rental_number']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($r['product_name']) ?><?= (int) $r['product_count'] > 1 ? ' +' . ((int) $r['product_count'] - 1) : '' ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($r['event_date'])) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($r['delivery_date'])) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($r['return_date'])) ?></td>
                                    <td class="px-5 py-4"><?= status_badge($r['rental_status'], 'rental') ?></td>
                                    <td class="px-5 py-4 text-right font-medium text-gray-700"><?= e(money($r['initial_payment_paid'])) ?></td>
                                    <td class="px-5 py-4 text-right font-semibold <?= ((float) $r['remaining_balance'] > 0) ? 'text-brand-red' : 'text-emerald-600' ?>"><?= e(money($r['remaining_balance'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $r['id']) ?>"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-brand-red"
                                           title="Ver alquiler"><?= icon('eye', 'w-5 h-5') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Columna lateral -->
    <div class="space-y-6">

        <!-- Solicitudes pendientes -->
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="font-serif text-lg font-bold text-gray-900">Solicitudes pendientes</h2>
                <?php if ($solicitudesPendientes > 0): ?>
                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-red px-2 text-xs font-semibold text-white"><?= $solicitudesPendientes ?></span>
                <?php endif; ?>
            </div>
            <?php if (!$solicitudes): ?>
                <div class="p-5">
                    <?= empty_state('Todo al día', 'No hay solicitudes públicas pendientes por revisar.', 'inbox') ?>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($solicitudes as $s): ?>
                        <div class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50/60">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><?= icon('user', 'w-5 h-5') ?></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900"><?= e($s['full_name'] ?: 'Cliente') ?></p>
                                <p class="truncate text-xs text-gray-500"><?= e($s['product_name'] ?: 'Producto no especificado') ?></p>
                                <p class="mt-0.5 text-[11px] text-gray-400">Evento: <?= e(format_date($s['event_date'])) ?></p>
                            </div>
                            <a href="<?= admin_url('solicitudes/index.php') ?>"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-brand-red"
                               title="Ver solicitud"><?= icon('eye', 'w-5 h-5') ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-gray-100 px-5 py-3 text-center">
                    <a href="<?= admin_url('solicitudes/index.php') ?>" class="text-sm font-medium text-brand-red hover:underline">Ver todas las solicitudes</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Productos más alquilados -->
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 class="font-serif text-lg font-bold text-gray-900">Más alquilados</h2>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('sparkles', 'w-5 h-5') ?></span>
            </div>
            <?php if (!$masAlquilados): ?>
                <div class="p-5">
                    <?= empty_state('Sin datos', 'Aún no hay productos alquilados.', 'tag') ?>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-50">
                    <?php foreach ($masAlquilados as $i => $p): ?>
                        <div class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50/60">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500"><?= $i + 1 ?></span>
                            <img src="<?= e(upload_url($p['main_image'])) ?>" alt="<?= e($p['name']) ?>"
                                 class="h-11 w-11 shrink-0 rounded-xl object-cover" loading="lazy">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900"><?= e($p['name']) ?></p>
                                <p class="truncate text-xs text-gray-400"><?= e($p['category_name']) ?></p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-brand-red">
                                <?= (int) $p['total'] ?> <span class="font-normal text-brand-red/70">alq.</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/* Script de gráficos: se inyecta tras cargar ApexCharts (ver admin_footer). */
ob_start(); ?>
<script>
(function () {
  if (typeof ApexCharts === 'undefined') return;
  var D = <?= json_encode($dashData, JSON_UNESCAPED_UNICODE) ?>;
  var cur = D.currency || 'RD$';
  var nf  = new Intl.NumberFormat('es-DO');
  var nf2 = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var base = { fontFamily: 'Plus Jakarta Sans, ui-sans-serif, sans-serif', foreColor: '#64748b' };

  var elR = document.querySelector('#chart-ingresos');
  if (elR) new ApexCharts(elR, {
    chart: Object.assign({ type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false }, animations: { easing: 'easeinout', speed: 750 } }, base),
    series: [{ name: 'Ingresos', data: D.revenue.data }],
    xaxis: { categories: D.revenue.labels, axisBorder: { show: false }, axisTicks: { show: false } },
    colors: ['#C8102E'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.03, stops: [0, 95, 100] } },
    stroke: { curve: 'smooth', width: 3 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#eef0f3', strokeDashArray: 5, padding: { left: 10, right: 10 } },
    yaxis: { labels: { formatter: function (v) { return cur + ' ' + nf.format(Math.round(v)); } } },
    tooltip: { y: { formatter: function (v) { return cur + ' ' + nf2.format(v); } } },
    markers: { size: 0, strokeWidth: 2, hover: { size: 6 } }
  }).render();

  var elC = document.querySelector('#chart-categorias');
  if (elC && D.categories.data.length) new ApexCharts(elC, {
    chart: Object.assign({ type: 'donut', height: 300 }, base),
    series: D.categories.data,
    labels: D.categories.labels,
    colors: ['#C8102E', '#C9A86A', '#1A1A1D', '#E0303F', '#7C8089', '#2E9C76'],
    legend: { position: 'bottom', fontSize: '13px', markers: { radius: 12 } },
    stroke: { width: 2, colors: ['#fff'] },
    dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '72%', labels: { show: true, value: { fontSize: '24px', fontWeight: 700, color: '#0B0B0C' }, total: { show: true, label: 'Total', color: '#64748b', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } },
    tooltip: { y: { formatter: function (v) { return v + ' alquiler(es)'; } } }
  }).render();
})();
</script>
<?php
$page_scripts = ob_get_clean();
require LCN_ROOT . '/app/views/layouts/admin_footer.php';
?>
