<?php
/**
 * Reportes — Visión 360 de LONDRES Casa de Novias
 * Cabina de analítica con KPIs comparados, gráficos interactivos (ApexCharts)
 * y paneles operativos. Filtro de periodo con presets.
 *
 * Ruta: admin/reportes/index.php (N=2) · Permiso: reports.view
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('reports.view');

/* ------------------------------------------------------------------ *
 *  Rango de fechas (presets + personalizado)
 * ------------------------------------------------------------------ */
$valid_date = static function (string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
};
$preset = get_param('preset', 'month');
$presets = ['7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', 'month' => 'Este mes', 'quarter' => 'Trimestre', 'year' => 'Este año', 'custom' => 'Personalizado'];

$today = date('Y-m-d');
switch ($preset) {
    case '7d':      $from = date('Y-m-d', strtotime('-6 days'));  $to = $today; break;
    case '30d':     $from = date('Y-m-d', strtotime('-29 days')); $to = $today; break;
    case 'quarter': $q = (int) floor((date('n') - 1) / 3); $from = date('Y-m-01', mktime(0,0,0, $q*3+1, 1)); $to = date('Y-m-t', mktime(0,0,0, $q*3+3, 1)); break;
    case 'year':    $from = date('Y-01-01'); $to = date('Y-12-31'); break;
    case 'custom':
        $from = get_param('from', date('Y-m-01'));
        $to   = get_param('to', date('Y-m-t'));
        if (!$valid_date($from)) $from = date('Y-m-01');
        if (!$valid_date($to))   $to   = date('Y-m-t');
        break;
    case 'month':
    default:        $preset = 'month'; $from = date('Y-m-01'); $to = date('Y-m-t'); break;
}
if (strtotime($to) < strtotime($from)) [$from, $to] = [$to, $from];

$from_dt = $from . ' 00:00:00';
$to_dt   = $to . ' 23:59:59';
$days    = max(1, days_between($from, $to) + 1);

/* Periodo anterior equivalente (para comparativas) */
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' day'));
$pfrom_dt = $prevFrom . ' 00:00:00';
$pto_dt   = $prevTo . ' 23:59:59';

$period_label = format_date($from) . ' — ' . format_date($to);

/* Helpers locales */
$sumPay = fn($a, $b) => (float) db_value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE COALESCE(paid_at, created_at) BETWEEN :a AND :b", ['a' => $a, 'b' => $b]);
$cntRen = fn($a, $b) => (int) db_value("SELECT COUNT(*) FROM rentals WHERE created_at BETWEEN :a AND :b", ['a' => $a, 'b' => $b]);
$sumRen = fn($a, $b) => (float) db_value("SELECT COALESCE(SUM(total_amount),0) FROM rentals WHERE created_at BETWEEN :a AND :b AND rental_status <> 'cancelled'", ['a' => $a, 'b' => $b]);

$delta = static function (float $cur, float $prev): array {
    if ($prev <= 0.0001) return ['pct' => $cur > 0 ? 100 : 0, 'up' => $cur > 0, 'has' => $cur > 0];
    $p = round((($cur - $prev) / $prev) * 100);
    return ['pct' => abs($p), 'up' => $p >= 0, 'has' => true];
};

$export_url = static fn(string $r): string => admin_url('reportes/export.php?' . http_build_query(['report' => $r, 'from' => $from, 'to' => $to]));
$export_btn = static fn(string $r): string => '<a href="' . e($export_url($r)) . '" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:bg-gray-50">' . icon('download', 'w-3.5 h-3.5') . 'CSV</a>';

/* ================================================================== *
 *  KPIs
 * ================================================================== */
$income     = $sumPay($from_dt, $to_dt);
$incomePrev = $sumPay($pfrom_dt, $pto_dt);
$rentalsN     = $cntRen($from_dt, $to_dt);
$rentalsNPrev = $cntRen($pfrom_dt, $pto_dt);
$rentalsTotal = $sumRen($from_dt, $to_dt);
$rentalsTotalPrev = $sumRen($pfrom_dt, $pto_dt);
$ticket     = $rentalsN > 0 ? $rentalsTotal / $rentalsN : 0;
$ticketPrev = $rentalsNPrev > 0 ? $rentalsTotalPrev / $rentalsNPrev : 0;
$pendingTotal = (float) db_value("SELECT COALESCE(SUM(remaining_balance),0) FROM rentals WHERE remaining_balance > 0 AND rental_status <> 'cancelled'");
$pendingCount = (int) db_value("SELECT COUNT(*) FROM rentals WHERE remaining_balance > 0 AND rental_status <> 'cancelled'");

$prodActive = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active'");
$prodBusy   = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active' AND commercial_status IN ('rented','reserved')");
$occupancy  = $prodActive > 0 ? round($prodBusy / $prodActive * 100) : 0;

$dIncome = $delta($income, $incomePrev);
$dRentals = $delta($rentalsN, $rentalsNPrev);
$dTicket = $delta($ticket, $ticketPrev);

/* ================================================================== *
 *  Datos para gráficos
 * ================================================================== */
/* Serie de ingresos (diaria si el rango es corto; mensual si es largo) */
$revLabels = []; $revCur = []; $revPrev = []; $revCompare = false;
if ($days <= 62) {
    $revCompare = true;
    $curRows = db_all("SELECT DATE(COALESCE(paid_at,created_at)) d, SUM(amount) t FROM payments WHERE COALESCE(paid_at,created_at) BETWEEN :a AND :b GROUP BY d", ['a' => $from_dt, 'b' => $to_dt]);
    $prevRows = db_all("SELECT DATE(COALESCE(paid_at,created_at)) d, SUM(amount) t FROM payments WHERE COALESCE(paid_at,created_at) BETWEEN :a AND :b GROUP BY d", ['a' => $pfrom_dt, 'b' => $pto_dt]);
    $curMap = array_column($curRows, 't', 'd');
    $prevMap = array_column($prevRows, 't', 'd');
    for ($i = 0; $i < $days; $i++) {
        $d  = date('Y-m-d', strtotime($from . " +$i day"));
        $pd = date('Y-m-d', strtotime($prevFrom . " +$i day"));
        $revLabels[] = date('d/m', strtotime($d));
        $revCur[]  = round((float) ($curMap[$d] ?? 0), 2);
        $revPrev[] = round((float) ($prevMap[$pd] ?? 0), 2);
    }
} else {
    $rows = db_all("SELECT DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m') m, SUM(amount) t FROM payments WHERE COALESCE(paid_at,created_at) BETWEEN :a AND :b GROUP BY m ORDER BY m", ['a' => $from_dt, 'b' => $to_dt]);
    $mesesEs = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
    foreach ($rows as $r) { [$y,$m] = explode('-', $r['m']); $revLabels[] = ($mesesEs[$m] ?? $m) . " $y"; $revCur[] = round((float) $r['t'], 2); }
}

/* Métodos de pago */
$payRows = db_all("SELECT payment_method, SUM(amount) t FROM payments WHERE COALESCE(paid_at,created_at) BETWEEN :a AND :b GROUP BY payment_method ORDER BY t DESC", ['a' => $from_dt, 'b' => $to_dt]);
$methodEs = ['cash'=>'Efectivo','transfer'=>'Transferencia','card'=>'Tarjeta','deposit'=>'Depósito','other'=>'Otro'];
$payLabels = array_map(fn($r) => $methodEs[$r['payment_method']] ?? $r['payment_method'], $payRows);
$payData   = array_map(fn($r) => round((float) $r['t'], 2), $payRows);

/* Alquileres por estado */
$stRows = db_all("SELECT rental_status, COUNT(*) c FROM rentals WHERE created_at BETWEEN :a AND :b GROUP BY rental_status ORDER BY c DESC", ['a' => $from_dt, 'b' => $to_dt]);
$stEs = ['pending'=>'Solicitud','reserved'=>'Reservado','confirmed'=>'Confirmado','delivered'=>'Entregado','pending_return'=>'Pend. devolución','returned'=>'Devuelto','cancelled'=>'Cancelado','overdue'=>'Vencido'];
$stLabels = array_map(fn($r) => $stEs[$r['rental_status']] ?? $r['rental_status'], $stRows);
$stData   = array_map(fn($r) => (int) $r['c'], $stRows);

/* Ingresos por categoría (alquileres del periodo) */
$catRows = db_all("SELECT COALESCE(c.name,'Sin categoría') n, SUM(r.total_amount) t FROM rentals r JOIN products p ON p.id=r.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE r.created_at BETWEEN :a AND :b AND r.rental_status <> 'cancelled' GROUP BY c.id, n ORDER BY t DESC LIMIT 6", ['a' => $from_dt, 'b' => $to_dt]);
$catLabels = array_map(fn($r) => $r['n'], $catRows);
$catData   = array_map(fn($r) => round((float) $r['t'], 2), $catRows);

/* Top productos por # alquileres */
$topRows = db_all("SELECT p.name n, COUNT(r.id) c FROM rentals r JOIN products p ON p.id=r.product_id WHERE r.created_at BETWEEN :a AND :b AND r.rental_status <> 'cancelled' GROUP BY p.id, p.name ORDER BY c DESC LIMIT 7", ['a' => $from_dt, 'b' => $to_dt]);
$topLabels = array_map(fn($r) => $r['n'], $topRows);
$topData   = array_map(fn($r) => (int) $r['c'], $topRows);

/* Embudo de conversión (periodo) */
$fRequests = (int) db_value("SELECT COUNT(*) FROM rental_requests WHERE created_at BETWEEN :a AND :b", ['a' => $from_dt, 'b' => $to_dt]);
$fRentals  = (int) db_value("SELECT COUNT(*) FROM rentals WHERE created_at BETWEEN :a AND :b AND rental_status <> 'cancelled'", ['a' => $from_dt, 'b' => $to_dt]);
$fDelivered = (int) db_value("SELECT COUNT(*) FROM rentals WHERE created_at BETWEEN :a AND :b AND rental_status IN ('delivered','pending_return','returned')", ['a' => $from_dt, 'b' => $to_dt]);
$fReturned = (int) db_value("SELECT COUNT(*) FROM rentals WHERE created_at BETWEEN :a AND :b AND rental_status = 'returned'", ['a' => $from_dt, 'b' => $to_dt]);

/* Salud del inventario (snapshot) */
$invRows = db_all("SELECT commercial_status s, COUNT(*) c FROM products WHERE status='active' GROUP BY commercial_status");
$invEs = ['available'=>'Disponible','reserved'=>'Reservado','rented'=>'Alquilado','sold'=>'Vendido','maintenance'=>'En reparación','unavailable'=>'No disponible'];
$invLabels = array_map(fn($r) => $invEs[$r['s']] ?? $r['s'], $invRows);
$invData   = array_map(fn($r) => (int) $r['c'], $invRows);
$invColorMap = ['Disponible'=>'#2E9C76','Reservado'=>'#38BDF8','Alquilado'=>'#F59E0B','Vendido'=>'#8B5CF6','En reparación'=>'#EAB308','No disponible'=>'#9AA0AA'];
$invColors = array_map(fn($l) => $invColorMap[$l] ?? '#9AA0AA', $invLabels);

/* ================================================================== *
 *  Paneles 360 (tablas)
 * ================================================================== */
$topClients = db_all("SELECT cu.id, cu.full_name, COUNT(r.id) n, COALESCE(SUM(r.total_amount),0) total FROM rentals r JOIN customers cu ON cu.id=r.customer_id WHERE r.created_at BETWEEN :a AND :b AND r.rental_status <> 'cancelled' GROUP BY cu.id, cu.full_name ORDER BY n DESC, total DESC LIMIT 6", ['a' => $from_dt, 'b' => $to_dt]);

$profitability = db_all(
    "SELECT p.name, p.cost_price,
            COALESCE((SELECT SUM(r.total_amount) FROM rentals r WHERE r.product_id=p.id AND r.rental_status<>'cancelled' AND r.created_at BETWEEN :a AND :b),0)
          + COALESCE((SELECT SUM(s.total_amount) FROM sales s WHERE s.product_id=p.id AND s.status<>'cancelled' AND s.created_at BETWEEN :a2 AND :b2),0) AS ingresos
     FROM products p WHERE p.status='active'
     HAVING ingresos > 0 ORDER BY ingresos DESC LIMIT 6",
    ['a' => $from_dt, 'b' => $to_dt, 'a2' => $from_dt, 'b2' => $to_dt]
);

$overdue = db_all("SELECT r.id, r.rental_number, r.return_date, r.remaining_balance, cu.full_name AS customer_name, p.name AS product_name FROM rentals r JOIN customers cu ON cu.id=r.customer_id JOIN products p ON p.id=r.product_id WHERE r.return_date < CURDATE() AND r.rental_status IN ('delivered','pending_return') ORDER BY r.return_date ASC LIMIT 6");

/* Operativo: próximas entregas / devoluciones (7 días) */
$nextDeliveries = (int) db_value("SELECT COUNT(*) FROM rentals WHERE delivery_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND rental_status IN ('reserved','confirmed')");
$nextReturns    = (int) db_value("SELECT COUNT(*) FROM rentals WHERE return_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND rental_status IN ('delivered','pending_return')");
$overdueCount   = (int) db_value("SELECT COUNT(*) FROM rentals WHERE return_date < CURDATE() AND rental_status IN ('delivered','pending_return')");

/* JSON para JS */
$RC = [
    'currency' => setting('currency', 'RD$'),
    'revenue'  => ['labels' => $revLabels, 'cur' => $revCur, 'prev' => $revPrev, 'compare' => $revCompare],
    'methods'  => ['labels' => $payLabels, 'data' => $payData],
    'status'   => ['labels' => $stLabels, 'data' => $stData],
    'category' => ['labels' => $catLabels, 'data' => $catData],
    'top'      => ['labels' => $topLabels, 'data' => $topData],
    'funnel'   => ['labels' => ['Solicitudes', 'Alquileres', 'Entregados', 'Devueltos'], 'data' => [$fRequests, $fRentals, $fDelivered, $fReturned]],
    'inventory'=> ['labels' => $invLabels, 'data' => $invData, 'colors' => $invColors],
];

$use_charts    = true;
$page_title    = 'Reportes';
$page_subtitle = 'Visión 360 · ' . $period_label;
$active        = 'reportes';
$header_actions = $export_btn('resumen-general');

require LCN_ROOT . '/app/views/layouts/admin_header.php';

/* Render de una tarjeta de gráfico */
function chart_card(string $title, string $sub, string $id, string $extra = '', string $colspan = ''): string {
    return '<div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft ' . $colspan . '">'
        . '<div class="mb-1 flex items-start justify-between gap-3"><div><h3 class="font-serif text-lg font-semibold text-gray-900">' . e($title) . '</h3>'
        . ($sub ? '<p class="text-xs text-gray-400">' . e($sub) . '</p>' : '') . '</div>' . $extra . '</div>'
        . '<div id="' . e($id) . '" class="mt-2"></div></div>';
}
?>

<!-- ================= FILTRO DE PERIODO ================= -->
<div class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-wrap items-center gap-1.5">
        <?php foreach ($presets as $k => $lbl): if ($k === 'custom') continue; ?>
            <a href="<?= admin_url('reportes/index.php?preset=' . $k) ?>"
               class="rounded-full px-3.5 py-2 text-sm font-medium transition <?= $preset === $k ? 'bg-brand-red text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?= admin_url('reportes/index.php') ?>" class="flex flex-wrap items-end gap-2">
        <input type="hidden" name="preset" value="custom">
        <div>
            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Desde</label>
            <input type="date" name="from" value="<?= e($from) ?>" class="lcn-input py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Hasta</label>
            <input type="date" name="to" value="<?= e($to) ?>" class="lcn-input py-2 text-sm">
        </div>
        <button type="submit" class="rounded-full bg-brand-dark px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">Aplicar</button>
    </form>
</div>

<!-- ================= KPIs CON COMPARATIVA ================= -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
    <?php
    $kpi = function (string $label, string $value, array $d, string $iconName, string $foot) {
        $chip = '';
        if ($d['has']) {
            $cls = $d['up'] ? 'text-emerald-600' : 'text-rose-600';
            $arrow = $d['up'] ? '▲' : '▼';
            $chip = '<span class="inline-flex items-center gap-1 text-xs font-semibold ' . $cls . '">' . $arrow . ' ' . $d['pct'] . '%</span>';
        } else {
            $chip = '<span class="text-xs text-gray-300">—</span>';
        }
        return '<div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-card">'
            . '<div class="flex items-center justify-between"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-brand-red">' . icon($iconName, 'w-5 h-5') . '</span>' . $chip . '</div>'
            . '<p class="mt-3 text-2xl font-bold tracking-tight text-gray-900">' . e($value) . '</p>'
            . '<p class="mt-0.5 text-sm font-medium text-gray-500">' . e($label) . '</p>'
            . '<p class="mt-1 text-[11px] text-gray-400">' . e($foot) . '</p></div>';
    };
    echo $kpi('Ingresos del periodo', money($income), $dIncome, 'banknotes', 'vs. ' . money($incomePrev) . ' anterior');
    echo $kpi('Alquileres', (string) $rentalsN, $dRentals, 'box', 'vs. ' . $rentalsNPrev . ' anterior');
    echo $kpi('Ticket promedio', money($ticket), $dTicket, 'chart', 'por alquiler');
    echo $kpi('Ocupación', $occupancy . '%', ['has' => false], 'truck', $prodBusy . ' de ' . $prodActive . ' piezas');
    echo $kpi('Por cobrar', money($pendingTotal), ['has' => false], 'clock', $pendingCount . ' alquileres');
    ?>
</div>

<!-- ================= INGRESOS + INVENTARIO ================= -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?= chart_card('Tendencia de ingresos', $revCompare ? 'Periodo actual vs. anterior' : 'Por mes', 'rc-revenue', $export_btn('ingresos-diarios'), 'lg:col-span-2') ?>
    <?php if ($invData): ?>
        <?= chart_card('Salud del inventario', $prodActive . ' piezas activas', 'rc-inventory') ?>
    <?php else: ?>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft"><?= empty_state('Sin inventario', 'Agrega productos para ver su estado.', 'box') ?></div>
    <?php endif; ?>
</div>

<!-- ================= ESTADO + MÉTODOS + EMBUDO ================= -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?php if ($stData): ?><?= chart_card('Alquileres por estado', 'En el periodo', 'rc-status') ?><?php else: ?><div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft"><?= empty_state('Sin alquileres', 'No hay alquileres en el periodo.', 'calendar') ?></div><?php endif; ?>
    <?php if ($payData): ?><?= chart_card('Métodos de pago', 'Distribución de cobros', 'rc-methods') ?><?php else: ?><div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft"><?= empty_state('Sin pagos', 'No hay pagos en el periodo.', 'banknotes') ?></div><?php endif; ?>
    <?= chart_card('Embudo de conversión', 'Solicitud → devolución', 'rc-funnel') ?>
</div>

<!-- ================= CATEGORÍA + TOP PRODUCTOS ================= -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?php if ($catData): ?><?= chart_card('Ingresos por categoría', 'Alquileres del periodo', 'rc-category', $export_btn('productos-mas-alquilados')) ?><?php else: ?><div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft"><?= empty_state('Sin datos', 'No hay alquileres por categoría.', 'squares') ?></div><?php endif; ?>
    <?php if ($topData): ?><?= chart_card('Productos más alquilados', 'Top del periodo', 'rc-top', $export_btn('productos-mas-alquilados'), 'lg:col-span-2') ?><?php else: ?><div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft lg:col-span-2"><?= empty_state('Sin datos', 'Aún no hay productos alquilados en el periodo.', 'tag') ?></div><?php endif; ?>
</div>

<!-- ================= OPERATIVO (7 días) ================= -->
<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <a href="<?= admin_url('alquileres/index.php') ?>" class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-soft transition hover:shadow-card">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><?= icon('truck', 'w-6 h-6') ?></span>
        <div><p class="text-2xl font-bold text-gray-900"><?= $nextDeliveries ?></p><p class="text-sm text-gray-500">Entregas próximas (7 días)</p></div>
    </a>
    <a href="<?= admin_url('alquileres/index.php') ?>" class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-soft transition hover:shadow-card">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><?= icon('return', 'w-6 h-6') ?></span>
        <div><p class="text-2xl font-bold text-gray-900"><?= $nextReturns ?></p><p class="text-sm text-gray-500">Devoluciones próximas (7 días)</p></div>
    </a>
    <a href="<?= admin_url('alquileres/index.php') ?>" class="flex items-center gap-4 rounded-2xl border <?= $overdueCount > 0 ? 'border-rose-200 bg-rose-50/50' : 'border-gray-100 bg-white' ?> p-5 shadow-soft transition hover:shadow-card">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><?= icon('warning', 'w-6 h-6') ?></span>
        <div><p class="text-2xl font-bold <?= $overdueCount > 0 ? 'text-rose-700' : 'text-gray-900' ?>"><?= $overdueCount ?></p><p class="text-sm text-gray-500">Devoluciones vencidas</p></div>
    </a>
</div>

<!-- ================= PANELES 360 ================= -->
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">

    <!-- Top clientes -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="font-serif text-lg font-semibold text-gray-900">Clientes frecuentes</h3><?= $export_btn('clientes-frecuentes') ?></div>
        <?php if (!$topClients): ?><div class="p-5"><?= empty_state('Sin clientes', 'No hay alquileres en el periodo.', 'users') ?></div>
        <?php else: ?><div class="divide-y divide-gray-50">
            <?php foreach ($topClients as $i => $cl): ?>
                <a href="<?= admin_url('clientes/ver.php?id=' . (int) $cl['id']) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50/60">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500"><?= $i + 1 ?></span>
                    <?= avatar($cl['full_name'], 'h-8 w-8 text-xs') ?>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-900"><?= e($cl['full_name']) ?></p><p class="text-xs text-gray-400"><?= (int) $cl['n'] ?> alquiler(es)</p></div>
                    <span class="text-sm font-semibold text-gray-700"><?= e(money($cl['total'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </div>

    <!-- Rentabilidad -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="font-serif text-lg font-semibold text-gray-900">Rentabilidad por pieza</h3><?= $export_btn('rentabilidad') ?></div>
        <?php if (!$profitability): ?><div class="p-5"><?= empty_state('Sin datos', 'No hay ingresos por producto en el periodo.', 'chart') ?></div>
        <?php else: ?><div class="divide-y divide-gray-50">
            <?php foreach ($profitability as $pr): $marg = (float) $pr['ingresos'] - (float) ($pr['cost_price'] ?? 0); ?>
                <div class="flex items-center justify-between px-5 py-3">
                    <div class="min-w-0 flex-1 pr-3"><p class="truncate text-sm font-semibold text-gray-900"><?= e($pr['name']) ?></p><p class="text-xs text-gray-400">Ingresos <?= e(money($pr['ingresos'])) ?></p></div>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">+<?= e(money($marg)) ?></span>
                </div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </div>

    <!-- Devoluciones vencidas -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4"><h3 class="font-serif text-lg font-semibold text-gray-900">Devoluciones vencidas</h3><?= $export_btn('devoluciones-vencidas') ?></div>
        <?php if (!$overdue): ?><div class="p-5"><?= empty_state('Todo al día', 'No hay devoluciones vencidas.', 'check') ?></div>
        <?php else: ?><div class="divide-y divide-gray-50">
            <?php foreach ($overdue as $ov): $dlate = abs(days_between($ov['return_date'], date('Y-m-d'))); ?>
                <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $ov['id']) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-rose-50/40">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600"><?= icon('warning', 'w-4 h-4') ?></span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-900"><?= e($ov['customer_name']) ?></p><p class="truncate text-xs text-gray-400"><?= e($ov['product_name']) ?></p></div>
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700"><?= (int) $dlate ?>d</span>
                </a>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </div>
</div>

<?php
ob_start(); ?>
<script>
(function () {
  if (typeof ApexCharts === 'undefined') return;
  var D = <?= json_encode($RC, JSON_UNESCAPED_UNICODE) ?>;
  var cur = D.currency || 'RD$';
  var nf = new Intl.NumberFormat('es-DO'), nf2 = new Intl.NumberFormat('es-DO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var money = function (v) { return cur + ' ' + nf.format(Math.round(v)); };
  var base = { fontFamily: 'Plus Jakarta Sans, ui-sans-serif, sans-serif', foreColor: '#64748b' };
  var PAL = ['#C8102E', '#C9A86A', '#1A1A1D', '#E0303F', '#7C8089', '#2E9C76', '#38BDF8', '#8B5CF6'];
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var anim = { enabled: !reduce, easing: 'easeinout', speed: reduce ? 0 : 700 };
  var make = function (sel, opt) { var el = document.querySelector(sel); if (el) { try { new ApexCharts(el, opt).render(); } catch (e) {} } };

  // Ingresos (área, actual vs anterior)
  var rSeries = [{ name: 'Actual', data: D.revenue.cur }];
  if (D.revenue.compare) rSeries.push({ name: 'Periodo anterior', data: D.revenue.prev });
  make('#rc-revenue', {
    chart: Object.assign({ type: 'area', height: 320, toolbar: { show: false }, zoom: { enabled: false }, animations: anim }, base),
    series: rSeries, xaxis: { categories: D.revenue.labels, axisBorder: { show: false }, axisTicks: { show: false }, tickAmount: 8 },
    colors: ['#C8102E', '#C9A86A'], fill: { type: ['gradient', 'solid'], gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.03, stops: [0, 95, 100] }, opacity: [1, 0] },
    stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] }, dataLabels: { enabled: false },
    legend: { show: D.revenue.compare, position: 'top', horizontalAlign: 'right' },
    grid: { borderColor: '#eef0f3', strokeDashArray: 5 }, yaxis: { labels: { formatter: money } },
    tooltip: { y: { formatter: function (v) { return cur + ' ' + nf2.format(v); } } }, markers: { size: 0, hover: { size: 6 } }
  });

  // Salud del inventario (donut)
  make('#rc-inventory', {
    chart: Object.assign({ type: 'donut', height: 300, animations: anim }, base),
    series: D.inventory.data, labels: D.inventory.labels, colors: D.inventory.colors,
    legend: { position: 'bottom', fontSize: '12px' }, stroke: { width: 2, colors: ['#fff'] }, dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, value: { fontSize: '22px', fontWeight: 700, color: '#0B0B0C' }, total: { show: true, label: 'Piezas', color: '#64748b' } } } } },
    tooltip: { y: { formatter: function (v) { return v + ' pieza(s)'; } } }
  });

  // Estado (donut)
  make('#rc-status', {
    chart: Object.assign({ type: 'donut', height: 300, animations: anim }, base),
    series: D.status.data, labels: D.status.labels, colors: PAL,
    legend: { position: 'bottom', fontSize: '12px' }, stroke: { width: 2, colors: ['#fff'] }, dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '70%', labels: { show: true, value: { fontSize: '22px', fontWeight: 700, color: '#0B0B0C' }, total: { show: true, label: 'Total', color: '#64748b' } } } } }
  });

  // Métodos (pie)
  make('#rc-methods', {
    chart: Object.assign({ type: 'donut', height: 300, animations: anim }, base),
    series: D.methods.data, labels: D.methods.labels, colors: PAL,
    legend: { position: 'bottom', fontSize: '12px' }, stroke: { width: 2, colors: ['#fff'] }, dataLabels: { enabled: true, formatter: function (v) { return Math.round(v) + '%'; }, style: { fontSize: '11px' } },
    tooltip: { y: { formatter: function (v) { return cur + ' ' + nf2.format(v); } } }
  });

  // Embudo (barra horizontal descendente)
  make('#rc-funnel', {
    chart: Object.assign({ type: 'bar', height: 300, toolbar: { show: false }, animations: anim }, base),
    series: [{ name: 'Cantidad', data: D.funnel.data }], xaxis: { categories: D.funnel.labels },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true, barHeight: '62%' } },
    colors: ['#1A1A1D', '#C8102E', '#E0303F', '#2E9C76'], dataLabels: { enabled: true, style: { colors: ['#fff'] } },
    legend: { show: false }, grid: { borderColor: '#eef0f3' }, tooltip: { y: { formatter: function (v) { return v + ' alquiler(es)'; } } }
  });

  // Categoría (barra horizontal, ingresos)
  make('#rc-category', {
    chart: Object.assign({ type: 'bar', height: 300, toolbar: { show: false }, animations: anim }, base),
    series: [{ name: 'Ingresos', data: D.category.data }], xaxis: { categories: D.category.labels, labels: { formatter: money } },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } }, colors: ['#C9A86A'], dataLabels: { enabled: false },
    grid: { borderColor: '#eef0f3' }, tooltip: { y: { formatter: function (v) { return cur + ' ' + nf2.format(v); } } }
  });

  // Top productos (barra horizontal, # alquileres)
  make('#rc-top', {
    chart: Object.assign({ type: 'bar', height: 320, toolbar: { show: false }, animations: anim }, base),
    series: [{ name: 'Alquileres', data: D.top.data }], xaxis: { categories: D.top.labels },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '58%' } }, colors: ['#C8102E'], dataLabels: { enabled: true, style: { colors: ['#fff'] } },
    grid: { borderColor: '#eef0f3' }, tooltip: { y: { formatter: function (v) { return v + ' alquiler(es)'; } } }
  });
})();
</script>
<?php
$page_scripts = ob_get_clean();
require LCN_ROOT . '/app/views/layouts/admin_footer.php';
?>
