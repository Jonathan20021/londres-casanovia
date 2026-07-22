<?php
/**
 * Calendario mensual de alquileres — LONDRES Casa de Novias
 * Vista en PHP puro (sin librerías). Pinta los alquileres cuyo rango
 * [delivery_date, return_date] incluye cada día, coloreados por estado.
 *
 * Ruta: admin/alquileres/calendario.php  (N=2)
 * Permiso: calendar.view
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('calendar.view');

/* ------------------------------------------------------------------ *
 *  Mes visible y navegación (?month=YYYY-MM)
 * ------------------------------------------------------------------ */
$monthParam = get_param('month');
// Validamos el formato YYYY-MM; si es inválido usamos el mes actual.
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
    $monthParam = date('Y-m');
}

try {
    $cursor = new DateTimeImmutable($monthParam . '-01');
} catch (Exception $e) {
    $cursor = new DateTimeImmutable(date('Y-m') . '-01');
    $monthParam = $cursor->format('Y-m');
}

$firstOfMonth = $cursor;                                   // primer día del mes
$lastOfMonth  = $cursor->modify('last day of this month'); // último día del mes
$monthStart   = $firstOfMonth->format('Y-m-d');
$monthEnd     = $lastOfMonth->format('Y-m-d');
$daysInMonth  = (int) $lastOfMonth->format('j');

$prevMonth = $cursor->modify('-1 month')->format('Y-m');
$nextMonth = $cursor->modify('+1 month')->format('Y-m');
$thisMonth = date('Y-m');
$today     = date('Y-m-d');

// Nombre del mes en español (sin depender de locale del servidor).
$mesesEs = [1=>'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$tituloMes = $mesesEs[(int) $cursor->format('n')] . ' ' . $cursor->format('Y');

/* ------------------------------------------------------------------ *
 *  Filtros GET opcionales
 * ------------------------------------------------------------------ */
$fProduct  = (int) get_param('product_id');
$fCategory = (int) get_param('category_id');
$fStatus   = get_param('rental_status');
$fCustomer = (int) get_param('customer_id');

// Estados de alquiler que se muestran en el calendario:
// los que bloquean disponibilidad + devueltos (histórico) + vencidos.
$calendarStatuses = array_merge(RENTAL_BLOCKING_STATUSES, ['returned', 'overdue']);

// Validamos el filtro de estado contra la lista permitida.
$statusOptions = [
    'reserved'       => 'Reservado',
    'confirmed'      => 'Confirmado',
    'delivered'      => 'Entregado',
    'pending_return' => 'Pendiente devolución',
    'returned'       => 'Devuelto',
    'overdue'        => 'Vencido',
];
if ($fStatus !== '' && !isset($statusOptions[$fStatus])) {
    $fStatus = '';
}

/* ------------------------------------------------------------------ *
 *  Una sola query del mes visible.
 *  Solapamiento con el mes: delivery_date <= fin de mes AND return_date >= inicio de mes
 * ------------------------------------------------------------------ */
$statusIn = implode(',', array_fill(0, count($calendarStatuses), '?'));
$sql = "SELECT r.id, r.rental_number, r.delivery_date, r.return_date, r.event_date,
               r.rental_status, r.payment_status,
               c.id AS customer_id, c.full_name AS customer_name,
               p.id AS product_id, p.name AS product_name, p.category_id
        FROM rentals r
        JOIN rental_items ri ON ri.rental_id = r.id
        JOIN customers c ON c.id = r.customer_id
        JOIN products  p ON p.id = ri.product_id
        WHERE r.rental_status IN ($statusIn)
          AND r.delivery_date <= ?
          AND r.return_date   >= ?";
$params = array_merge($calendarStatuses, [$monthEnd, $monthStart]);

if ($fProduct > 0)  { $sql .= ' AND ri.product_id = ?'; $params[] = $fProduct; }
if ($fCategory > 0) { $sql .= ' AND p.category_id = ?'; $params[] = $fCategory; }
if ($fStatus !== '') { $sql .= ' AND r.rental_status = ?'; $params[] = $fStatus; }
if ($fCustomer > 0) { $sql .= ' AND r.customer_id = ?'; $params[] = $fCustomer; }

$sql .= ' ORDER BY r.delivery_date ASC, r.rental_number ASC';

$rentals = db_all($sql, $params);

/* ------------------------------------------------------------------ *
 *  Indexamos por día: para cada día del mes, los alquileres que lo cubren.
 * ------------------------------------------------------------------ */
$eventsByDay = []; // ['Y-m-d' => [fila, ...]]
foreach ($rentals as $r) {
    // Acotamos el rango del alquiler a los límites del mes visible.
    $start = max($r['delivery_date'], $monthStart);
    $end   = min($r['return_date'],   $monthEnd);
    try {
        $d   = new DateTimeImmutable($start);
        $lim = new DateTimeImmutable($end);
    } catch (Exception $e) {
        continue;
    }
    while ($d <= $lim) {
        $key = $d->format('Y-m-d');
        $eventsByDay[$key][] = $r;
        $d = $d->modify('+1 day');
    }
}

/* ------------------------------------------------------------------ *
 *  Paleta de colores por estado para los chips (cal-event).
 * ------------------------------------------------------------------ */
$statusColors = [
    'reserved'       => 'bg-sky-100 text-sky-700 hover:bg-sky-200',
    'confirmed'      => 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200',
    'delivered'      => 'bg-amber-100 text-amber-700 hover:bg-amber-200',
    'pending_return' => 'bg-rose-100 text-rose-700 hover:bg-rose-200',
    'returned'       => 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200',
    'overdue'        => 'bg-red-100 text-red-700 hover:bg-red-200',
];
// Leyenda (incluye mantenimiento como referencia visual de producto).
$legend = [
    'reserved'       => ['Reservado',            'bg-sky-100 text-sky-700'],
    'confirmed'      => ['Confirmado',           'bg-indigo-100 text-indigo-700'],
    'delivered'      => ['Entregado',            'bg-amber-100 text-amber-700'],
    'pending_return' => ['Pendiente devolución', 'bg-rose-100 text-rose-700'],
    'returned'       => ['Devuelto',             'bg-emerald-100 text-emerald-700'],
    'overdue'        => ['Vencido',              'bg-red-100 text-red-700'],
    'maintenance'    => ['Mantenimiento',        'bg-yellow-100 text-yellow-700'],
];

/* ------------------------------------------------------------------ *
 *  Datos para los selects de filtro.
 * ------------------------------------------------------------------ */
$categories = db_all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
$products   = db_all("SELECT id, name FROM products WHERE status = 'active' ORDER BY name ASC");
$customers  = db_all("SELECT id, full_name FROM customers ORDER BY full_name ASC");

/* ------------------------------------------------------------------ *
 *  Cálculo de la cuadrícula (Lun-Dom).
 *  ISO: 1=Lunes ... 7=Domingo -> celdas vacías iniciales = ISO-1.
 * ------------------------------------------------------------------ */
$leadingBlanks = (int) $firstOfMonth->format('N') - 1; // 0..6
$totalCells    = $leadingBlanks + $daysInMonth;
$trailingBlanks = (7 - ($totalCells % 7)) % 7;

$diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

/* ------------------------------------------------------------------ *
 *  Conserva filtros al navegar entre meses.
 * ------------------------------------------------------------------ */
$keepFilters = array_filter([
    'product_id'    => $fProduct ?: '',
    'category_id'   => $fCategory ?: '',
    'rental_status' => $fStatus,
    'customer_id'   => $fCustomer ?: '',
], fn($v) => $v !== '' && $v !== 0);

$navUrl = function (string $ym) use ($keepFilters): string {
    return query_url(array_merge($keepFilters, ['month' => $ym]));
};

$page_title    = 'Calendario de alquileres';
$page_subtitle = 'Disponibilidad y ocupación por día';
$active        = 'calendario';
$header_actions = '<a href="' . e(admin_url('alquileres/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nuevo alquiler</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- ====================== Filtros ====================== -->
<form method="get" class="mb-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft sm:p-5">
    <input type="hidden" name="month" value="<?= e($monthParam) ?>">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="lcn-label" for="fCategoria">Categoría</label>
            <select id="fCategoria" name="category_id" class="lcn-input">
                <option value="">Todas las categorías</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $fCategory === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="lcn-label" for="fProducto">Producto</label>
            <select id="fProducto" name="product_id" class="lcn-input">
                <option value="">Todos los productos</option>
                <?php foreach ($products as $prod): ?>
                    <option value="<?= (int) $prod['id'] ?>" <?= $fProduct === (int) $prod['id'] ? 'selected' : '' ?>>
                        <?= e($prod['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="lcn-label" for="fCliente">Cliente</label>
            <select id="fCliente" name="customer_id" class="lcn-input">
                <option value="">Todos los clientes</option>
                <?php foreach ($customers as $cli): ?>
                    <option value="<?= (int) $cli['id'] ?>" <?= $fCustomer === (int) $cli['id'] ? 'selected' : '' ?>>
                        <?= e($cli['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="lcn-label" for="fEstado">Estado</label>
            <select id="fEstado" name="rental_status" class="lcn-input">
                <option value="">Todos los estados</option>
                <?php foreach ($statusOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $fStatus === $val ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('filter', 'w-4 h-4') ?> Filtrar
            </button>
            <?php if ($keepFilters): ?>
                <a href="<?= e(query_url(['month' => $monthParam, 'product_id' => null, 'category_id' => null, 'rental_status' => null, 'customer_id' => null])) ?>"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                   title="Limpiar filtros">
                    <?= icon('x', 'w-4 h-4') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- ====================== Barra de navegación de mes ====================== -->
<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2">
        <a href="<?= e($navUrl($prevMonth)) ?>"
           class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50"
           title="Mes anterior"><?= icon('chevron-left', 'w-5 h-5') ?></a>
        <a href="<?= e($navUrl($nextMonth)) ?>"
           class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50"
           title="Mes siguiente"><?= icon('chevron-right', 'w-5 h-5') ?></a>
        <a href="<?= e($navUrl($thisMonth)) ?>"
           class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
            <?= icon('calendar', 'w-4 h-4') ?> Hoy
        </a>
    </div>
    <h2 class="font-serif text-xl font-bold capitalize text-gray-900 sm:text-2xl"><?= e($tituloMes) ?></h2>
</div>

<!-- ====================== Leyenda de colores ====================== -->
<div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-gray-100 bg-white px-5 py-3 shadow-soft">
    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Leyenda</span>
    <?php foreach ($legend as $cls): ?>
        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-600">
            <span class="inline-block h-3 w-3 rounded-full <?= e($cls[1]) ?>"></span>
            <?= e($cls[0]) ?>
        </span>
    <?php endforeach; ?>
</div>

<!-- ====================== Calendario ====================== -->
<div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-soft">
    <div class="min-w-[820px]">
        <!-- Cabecera de días de la semana -->
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            <?php foreach ($diasSemana as $i => $dia): ?>
                <div class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide <?= $i >= 5 ? 'text-brand-red' : 'text-gray-500' ?>">
                    <?= e($dia) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Celdas -->
        <div class="grid grid-cols-7">
            <?php
            // Celdas vacías iniciales
            for ($b = 0; $b < $leadingBlanks; $b++):
                $colIdx = $b % 7;
            ?>
                <div class="cal-cell border-b border-r border-gray-100 bg-gray-50/40 <?= $colIdx >= 5 ? 'bg-gray-50/70' : '' ?>"></div>
            <?php endfor; ?>

            <?php
            // Días del mes
            for ($day = 1; $day <= $daysInMonth; $day++):
                $dateObj = $firstOfMonth->modify('+' . ($day - 1) . ' day');
                $dateKey = $dateObj->format('Y-m-d');
                $colIdx  = ($leadingBlanks + $day - 1) % 7;
                $isWeekend = $colIdx >= 5;
                $isToday   = ($dateKey === $today);
                $dayEvents = $eventsByDay[$dateKey] ?? [];
            ?>
                <div class="cal-cell flex flex-col border-b border-r border-gray-100 p-1.5 transition
                            <?= $isToday ? 'bg-red-50/60 ring-1 ring-inset ring-brand-red/30' : ($isWeekend ? 'bg-gray-50/40' : 'bg-white') ?>">
                    <div class="mb-1 flex items-center justify-between px-1">
                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full text-xs font-semibold
                                     <?= $isToday ? 'bg-brand-red text-white' : ($isWeekend ? 'text-brand-red' : 'text-gray-500') ?>">
                            <?= $day ?>
                        </span>
                        <?php if (count($dayEvents) > 0): ?>
                            <span class="text-[10px] font-medium text-gray-400"><?= count($dayEvents) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-1 flex-col gap-1">
                        <?php
                        // Mostramos hasta 4 chips por celda; el resto se resume.
                        $maxChips = 4;
                        $shown = array_slice($dayEvents, 0, $maxChips);
                        foreach ($shown as $ev):
                            $chipCls = $statusColors[$ev['rental_status']] ?? 'bg-gray-100 text-gray-700 hover:bg-gray-200';
                            $tip = $ev['product_name'] . ' · ' . $ev['customer_name']
                                 . ' (' . format_date($ev['delivery_date']) . ' → ' . format_date($ev['return_date']) . ')';
                        ?>
                            <a href="<?= e(admin_url('alquileres/ver.php?id=' . (int) $ev['id'])) ?>"
                               title="<?= e($tip) ?>"
                               class="cal-event flex items-center gap-1 truncate rounded-md px-1.5 py-1 font-medium transition <?= e($chipCls) ?>">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>
                                <span class="truncate"><?= e($ev['product_name']) ?></span>
                            </a>
                        <?php endforeach; ?>

                        <?php if (count($dayEvents) > $maxChips): ?>
                            <span class="cal-event px-1.5 text-gray-400">+<?= count($dayEvents) - $maxChips ?> más</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>

            <?php
            // Celdas vacías finales
            for ($t = 0; $t < $trailingBlanks; $t++):
                $colIdx = ($leadingBlanks + $daysInMonth + $t) % 7;
            ?>
                <div class="cal-cell border-b border-r border-gray-100 bg-gray-50/40 <?= $colIdx >= 5 ? 'bg-gray-50/70' : '' ?>"></div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php if (!$rentals): ?>
    <div class="mt-5">
        <?= empty_state(
            'Sin alquileres en este mes',
            'No hay alquileres que coincidan con el mes y los filtros seleccionados.',
            'calendar',
            '<a href="' . e(admin_url('alquileres/crear.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Registrar alquiler</a>'
        ) ?>
    </div>
<?php else: ?>
    <p class="mt-4 text-sm text-gray-500">
        Mostrando <span class="font-semibold text-gray-700"><?= count($rentals) ?></span>
        alquiler<?= count($rentals) === 1 ? '' : 'es' ?> con actividad en <?= e($tituloMes) ?>.
    </p>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
