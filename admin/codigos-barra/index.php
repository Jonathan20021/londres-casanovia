<?php
/**
 * Códigos de barra — Listado, escáner y exportación por lote.
 * LONDRES Casa de Novias
 *
 * admin/codigos-barra/index.php  (N=2)  ·  Permiso: products.view
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.view');

$canManage = user_can('products.manage');

/* ------------------------------------------------------------------ *
 *  Asignación automática de códigos faltantes
 * ------------------------------------------------------------------ */
$generated = barcode_backfill();
if ($generated > 0) {
    flash('success', $generated . ' producto(s) recibieron su código de barras automáticamente.');
}

/* Una unidad (con código propio) por cada pieza en stock */
$generatedUnits = barcode_units_backfill();
if ($generatedUnits > 0) {
    flash('info', $generatedUnits . ' unidad(es) recibieron su código individual según la cantidad en stock.');
}

/* ------------------------------------------------------------------ *
 *  Escáner (?scan=) — funciona con cualquier lector tipo teclado
 * ------------------------------------------------------------------ */
$scan       = barcode_normalize(get_param('scan'));
$scanned    = $scan !== '' ? barcode_lookup($scan) : null;
$scanFailed = $scan !== '' && $scanned === null;

/* ------------------------------------------------------------------ *
 *  Filtros
 * ------------------------------------------------------------------ */
$q          = get_param('q');
$categoryId = (int) get_param('category_id', '0');
$type       = get_param('type');
$comStatus  = get_param('commercial_status');
$tam        = get_param('tam', 'mediana');
$copias     = max(1, min(50, (int) get_param('copias', '1')));
$modo       = get_param('modo', 'unidades');

if (!in_array($type, ['rental', 'sale', 'both'], true)) $type = '';
if (!in_array($comStatus, ['available', 'reserved', 'rented', 'sold', 'unavailable', 'maintenance'], true)) $comStatus = '';
if (!array_key_exists($tam, barcode_layouts())) $tam = 'mediana';
if (!in_array($modo, ['unidades', 'producto'], true)) $modo = 'unidades';

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q OR p.color LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($categoryId > 0)   { $where[] = 'p.category_id = :cat'; $params['cat']  = $categoryId; }
if ($type !== '')      { $where[] = 'p.type = :type';       $params['type'] = $type; }
if ($comStatus !== '') { $where[] = 'p.commercial_status = :com'; $params['com'] = $comStatus; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int) db_value("SELECT COUNT(*) FROM products p $whereSql", $params);
$pg    = paginate($total, 24);

/* Nº de unidades (etiquetas) de cada producto — 0 si aún no se migró la tabla */
$unitCountSql = barcode_units_enabled()
    ? '(SELECT COUNT(*) FROM product_units u WHERE u.product_id = p.id)'
    : '0';

$products = db_all(
    "SELECT p.id, p.name, p.sku, p.barcode, p.size, p.color, p.type, p.rental_price, p.sale_price,
            p.commercial_status, p.main_image, p.quantity, c.name AS category_name,
            $unitCountSql AS unit_count
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY p.name ASC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

/* Etiquetas que saldrían al exportar "todo lo filtrado" en modo unidades */
$totalUnits = barcode_units_enabled()
    ? (int) db_value("SELECT COALESCE(SUM($unitCountSql), 0) FROM products p $whereSql", $params)
    : 0;

$categories  = db_all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
$totalProds  = (int) db_value('SELECT COUNT(*) FROM products');
$withCode    = (int) db_value("SELECT COUNT(*) FROM products WHERE barcode IS NOT NULL AND barcode <> ''");
$unitsAll    = barcode_units_enabled() ? (int) db_value('SELECT COUNT(*) FROM product_units') : 0;
$hasFilters  = $q !== '' || $categoryId || $type !== '' || $comStatus !== '';
$layouts     = barcode_layouts();

/* Parámetros de filtro que viajan al PDF cuando se exporta "todo lo filtrado" */
$filterHidden = [
    'q'                 => $q,
    'category_id'       => $categoryId ?: '',
    'type'              => $type,
    'commercial_status' => $comStatus,
];

$page_title    = 'Códigos de barra';
$page_subtitle = 'Genera, escanea e imprime las etiquetas del inventario';
$active        = 'codigos-barra';
$header_actions = '<a href="' . admin_url('productos/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('box', 'w-4 h-4') . ' Ir a productos</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- =========================== ESCÁNER =========================== -->
<div class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
    <div class="grid grid-cols-1 gap-0 lg:grid-cols-12">

        <div class="border-b border-gray-100 p-6 lg:col-span-5 lg:border-b-0 lg:border-r">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('search', 'w-5 h-5') ?></span>
                <div>
                    <h2 class="font-serif text-lg text-gray-900">Escanear producto</h2>
                    <p class="text-xs text-gray-500">Dispare el lector sobre la etiqueta — el cursor ya está listo.</p>
                </div>
            </div>

            <form method="get" action="<?= admin_url('codigos-barra/index.php') ?>" class="mt-4" autocomplete="off" id="scanForm">
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><?= icon('tag', 'w-5 h-5') ?></span>
                    <input id="scanInput" type="text" name="scan" value="" inputmode="text" autocomplete="off"
                           placeholder="Escanee o escriba el código…"
                           class="lcn-input h-14 pl-12 pr-4 font-mono text-base tracking-widest">
                </label>
                <div class="mt-3 flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                        <?= icon('search', 'w-4 h-4') ?> Buscar
                    </button>
                    <?php if ($scan !== ''): ?>
                        <a href="<?= admin_url('codigos-barra/index.php') ?>" class="text-sm font-medium text-gray-500 hover:text-gray-900">Limpiar</a>
                    <?php endif; ?>
                    <span id="scanHint" class="ml-auto text-[11px] text-gray-400">Code 128 · compatible con cualquier lector</span>
                </div>
            </form>
        </div>

        <div class="p-6 lg:col-span-7">
            <?php if ($scanned): ?>
                <?php
                $sPrice = $scanned['type'] === 'sale' ? (float) ($scanned['sale_price'] ?? 0) : (float) $scanned['rental_price'];
                ?>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="h-24 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                        <?php if (!empty($scanned['main_image'])): ?>
                            <img src="<?= e(upload_url($scanned['main_image'])) ?>" alt="" class="h-full w-full object-cover">
                        <?php else: ?>
                            <div class="flex h-full w-full items-center justify-center text-gray-300"><?= icon('photo', 'w-6 h-6') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><?= icon('check', 'w-3.5 h-3.5') ?> Encontrado</span>
                            <?php if (!empty($scanned['unit_number'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-red/10 px-2.5 py-1 text-xs font-semibold text-brand-red">
                                    <?= icon('tag', 'w-3.5 h-3.5') ?>
                                    Unidad <?= (int) $scanned['unit_number'] ?><?= !empty($scanned['unit_total']) ? ' de ' . (int) $scanned['unit_total'] : '' ?>
                                </span>
                                <?php if (!empty($scanned['unit_size'])): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-dark px-2.5 py-1 text-xs font-semibold text-white">
                                        Talla <?= e((string) $scanned['unit_size']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?= status_badge($scanned['commercial_status'], 'commercial') ?>
                        </div>
                        <h3 class="mt-2 truncate font-serif text-xl text-gray-900"><?= e($scanned['name']) ?></h3>
                        <p class="mt-0.5 text-sm text-gray-500">
                            <span class="font-mono text-gray-700"><?= e((string) ($scanned['unit_barcode'] ?? $scanned['barcode'] ?? '')) ?></span>
                            <?php if (!empty($scanned['sku'])): ?> · SKU <?= e($scanned['sku']) ?><?php endif; ?>
                            <?php if ($sPrice > 0): ?> · <span class="font-semibold text-gray-900"><?= e(money($sPrice)) ?></span><?php endif; ?>
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="<?= admin_url('productos/ver.php?id=' . (int) $scanned['id']) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-dark px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-black">
                                <?= icon('eye', 'w-4 h-4') ?> Ver ficha
                            </a>
                            <?php if (!empty($scanned['unit_id'])): ?>
                                <a href="<?= admin_url('codigos-barra/exportar.php?unit_ids=' . (int) $scanned['unit_id'] . '&tam=' . e($tam)) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                    <?= icon('printer', 'w-4 h-4') ?> Reponer esta etiqueta
                                </a>
                            <?php endif; ?>
                            <a href="<?= admin_url('codigos-barra/exportar.php?ids=' . (int) $scanned['id'] . '&modo=unidades&tam=' . e($tam)) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                <?= icon('printer', 'w-4 h-4') ?> Todas sus etiquetas
                            </a>
                            <?php if ($canManage): ?>
                                <a href="<?= admin_url('alquileres/crear.php?product=' . (int) $scanned['id']) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                    <?= icon('calendar', 'w-4 h-4') ?> Alquilar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="w-full shrink-0 sm:w-48">
                        <?= barcode_svg((string) ($scanned['unit_barcode'] ?? $scanned['barcode'] ?? ''), ['module' => 1.6, 'height' => 46]) ?>
                    </div>
                </div>
            <?php elseif ($scanFailed): ?>
                <div class="flex items-start gap-3 rounded-xl border border-rose-100 bg-rose-50/60 p-4">
                    <span class="text-rose-500"><?= icon('warning', 'w-5 h-5') ?></span>
                    <div>
                        <p class="text-sm font-semibold text-rose-700">Sin coincidencias para «<?= e($scan) ?>»</p>
                        <p class="mt-0.5 text-xs text-rose-600/80">Verifique que la etiqueta pertenezca a este sistema o busque el producto por nombre más abajo.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex h-full flex-col justify-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Cómo funciona</p>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li class="flex gap-2"><span class="text-brand-gold">1.</span> Cada <strong>unidad</strong> en stock recibe su propio código <strong>Code 128</strong>: si hay 10 trajes negros, se generan 10 códigos distintos.</li>
                        <li class="flex gap-2"><span class="text-brand-gold">2.</span> Imprima las etiquetas y pegue una a cada vestido, traje o accesorio.</li>
                        <li class="flex gap-2"><span class="text-brand-gold">3.</span> Al escanear, el lector escribe el código y confirma con Enter: aquí verá la ficha y <strong>qué unidad</strong> es.</li>
                    </ul>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm">
                        <span class="text-gray-500">Productos: <strong class="text-gray-900"><?= $totalProds ?></strong></span>
                        <span class="text-gray-500">Con código: <strong class="text-gray-900"><?= $withCode ?></strong></span>
                        <span class="text-gray-500">Unidades etiquetables: <strong class="text-gray-900"><?= $unitsAll ?></strong></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =========================== FILTROS =========================== -->
<form method="get" action="<?= admin_url('codigos-barra/index.php') ?>" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
    <div class="min-w-[220px] flex-1">
        <label class="lcn-label" for="f-q">Buscar</label>
        <label class="relative block">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, SKU o código…" class="lcn-input pl-9 text-sm">
        </label>
    </div>
    <div class="w-44">
        <label class="lcn-label" for="f-cat">Categoría</label>
        <select id="f-cat" name="category_id" class="lcn-input text-sm">
            <option value="">Todas</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-40">
        <label class="lcn-label" for="f-com">Estado</label>
        <select id="f-com" name="commercial_status" class="lcn-input text-sm">
            <option value="">Todos</option>
            <?php foreach (['available' => 'Disponible', 'reserved' => 'Reservado', 'rented' => 'Alquilado', 'sold' => 'Vendido', 'maintenance' => 'En reparación', 'unavailable' => 'No disponible'] as $k => $lbl): ?>
                <option value="<?= $k ?>" <?= $comStatus === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-36">
        <label class="lcn-label" for="f-type">Tipo</label>
        <select id="f-type" name="type" class="lcn-input text-sm">
            <option value="">Todos</option>
            <option value="rental" <?= $type === 'rental' ? 'selected' : '' ?>>Alquiler</option>
            <option value="sale"   <?= $type === 'sale'   ? 'selected' : '' ?>>Venta</option>
            <option value="both"   <?= $type === 'both'   ? 'selected' : '' ?>>Ambos</option>
        </select>
    </div>
    <input type="hidden" name="tam" value="<?= e($tam) ?>">
    <input type="hidden" name="copias" value="<?= (int) $copias ?>">
    <input type="hidden" name="modo" value="<?= e($modo) ?>">
    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-dark px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
        <?= icon('filter', 'w-4 h-4') ?> Aplicar
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= admin_url('codigos-barra/index.php') ?>" class="text-sm font-medium text-brand-red hover:underline">Limpiar</a>
    <?php endif; ?>
</form>

<!-- =========================== LOTE + REJILLA =========================== -->
<form method="post" action="<?= admin_url('codigos-barra/exportar.php') ?>" id="barcodeForm">
    <?= csrf_field() ?>
    <?php foreach ($filterHidden as $k => $v): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
    <?php endforeach; ?>

    <!-- Barra de exportación (sticky) -->
    <div class="sticky top-20 z-20 mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-100 bg-white/95 p-4 shadow-soft backdrop-blur">
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 px-3 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            <input type="checkbox" id="checkAll" class="h-4 w-4 rounded border-gray-300 text-brand-red focus:ring-brand-red/40">
            Seleccionar todo
        </label>

        <div class="w-56">
            <label class="lcn-label" for="modo">Qué imprimir</label>
            <select id="modo" name="modo" class="lcn-input text-sm">
                <option value="unidades" <?= $modo === 'unidades' ? 'selected' : '' ?>>Una etiqueta por unidad</option>
                <option value="producto" <?= $modo === 'producto' ? 'selected' : '' ?>>Una etiqueta por producto</option>
            </select>
        </div>

        <div class="w-56">
            <label class="lcn-label" for="tam">Tamaño de etiqueta</label>
            <select id="tam" name="tam" class="lcn-input text-sm">
                <?php foreach ($layouts as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $tam === $k ? 'selected' : '' ?>><?= e($l['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-32">
            <label class="lcn-label" for="copias">Copias c/u</label>
            <input id="copias" name="copias" type="number" min="1" max="50" value="<?= (int) $copias ?>" class="lcn-input text-sm">
        </div>

        <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-sm text-gray-600">
            <!-- El hidden va antes: si la casilla queda sin marcar, se envía 0 -->
            <input type="hidden" name="encabezado" value="0">
            <input type="checkbox" name="encabezado" value="1" checked class="h-4 w-4 rounded border-gray-300 text-brand-red focus:ring-brand-red/40">
            Encabezado de marca
        </label>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <span id="selCount" class="text-sm text-gray-500">0 seleccionados</span>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                <?= icon('printer', 'w-4 h-4') ?> Exportar selección
            </button>
            <button type="submit" name="all" value="1" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                <?= icon('download', 'w-4 h-4') ?> Exportar todo<?= $hasFilters ? ' (filtrado)' : '' ?> · <?= $total ?>
                <span class="text-xs font-normal text-gray-400" data-total-units="<?= $totalUnits ?>" data-total-prods="<?= $total ?>">(<?= $totalUnits ?> etiquetas)</span>
            </button>
        </div>
    </div>

    <?php if (!$products): ?>
        <?= empty_state('Sin productos', 'Ajuste los filtros o registre productos para generar sus códigos de barra.', 'tag') ?>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <?php foreach ($products as $p):
                $code  = (string) ($p['barcode'] ?? '');
                $price = $p['type'] === 'sale' ? (float) ($p['sale_price'] ?? 0) : (float) $p['rental_price'];
                $nUnit = (int) $p['unit_count'];
            ?>
                <label data-card class="group relative flex cursor-pointer flex-col rounded-2xl border border-gray-100 bg-white p-4 shadow-soft transition hover:border-brand-red/30">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" data-item data-units="<?= $nUnit ?>"
                               class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-brand-red focus:ring-brand-red/40">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-gray-900" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></h3>
                            <p class="mt-0.5 truncate text-xs text-gray-500">
                                <?= e($p['category_name'] ?: 'Sin categoría') ?>
                                <?php if (!empty($p['size'])): ?> · Talla <?= e($p['size']) ?><?php endif; ?>
                                <?php if ($price > 0): ?> · <?= e(money($price)) ?><?php endif; ?>
                            </p>
                        </div>
                        <?= status_badge($p['commercial_status'], 'commercial') ?>
                    </div>

                    <div class="mt-3 rounded-xl border border-gray-100 bg-white px-3 py-3">
                        <?= barcode_svg($code, ['module' => 1.5, 'height' => 44]) ?>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full <?= $nUnit > 1 ? 'bg-brand-red/10 text-brand-red' : 'bg-gray-100 text-gray-500' ?> px-2 py-0.5 text-[11px] font-semibold">
                            <?= icon('tag', 'w-3 h-3') ?>
                            <?= $nUnit ?> etiqueta<?= $nUnit === 1 ? '' : 's' ?>
                        </span>
                        <span class="text-[11px] text-gray-400">Stock <?= (int) $p['quantity'] ?></span>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50">
                            <?= icon('eye', 'w-3.5 h-3.5') ?> Ficha
                        </a>
                        <a href="<?= admin_url('codigos-barra/exportar.php?ids=' . (int) $p['id'] . '&modo=unidades&tam=' . e($tam) . '&copias=' . (int) $copias) ?>"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50">
                            <?= icon('printer', 'w-3.5 h-3.5') ?> PDF<?= $nUnit > 1 ? ' · ' . $nUnit : '' ?>
                        </a>
                        <span class="ml-auto font-mono text-[11px] tracking-wider text-gray-400"><?= e($p['sku'] ?: '—') ?></span>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if ($pg['pages'] > 1): ?>
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
                <?= render_pagination($pg['page'], $pg['pages']) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</form>

<script>
(function () {
    /* El foco vive en el campo del escáner: los lectores escriben como un teclado. */
    var scanEl   = document.getElementById('scanInput');
    var scanForm = document.getElementById('scanForm');
    var hint     = document.getElementById('scanHint');

    if (scanEl) {
        scanEl.focus();

        /* Si el usuario empieza a "teclear" muy rápido fuera de un campo (lector),
           reenfocamos el escáner para no perder la lectura. */
        var lastKey = 0;
        document.addEventListener('keydown', function (ev) {
            var t = ev.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) return;
            if (ev.key && ev.key.length === 1) {
                var now = Date.now();
                if (now - lastKey < 60) scanEl.focus();
                lastKey = now;
            }
        });

        /*
         * Envío automático: hay lectores que no envían Enter (o envían Tab).
         * Si los caracteres llegan a velocidad de máquina (<50 ms entre pulsaciones)
         * y luego hay 180 ms de silencio, se busca solo. Al teclear a mano (mucho
         * más lento) no se dispara: ahí se usa Enter o el botón Buscar.
         */
        var prev = 0, fast = 0, timer = null, sent = false;

        scanEl.addEventListener('keydown', function (ev) {
            if (ev.key === 'Tab' && scanEl.value.trim().length >= 3) {
                ev.preventDefault();
                submit();
            }
        });

        scanEl.addEventListener('input', function () {
            var now = Date.now();
            fast = (prev && now - prev < 50) ? fast + 1 : 0;
            prev = now;

            if (timer) clearTimeout(timer);
            if (fast >= 3 && scanEl.value.trim().length >= 3) {
                if (hint) hint.textContent = 'Lectura detectada…';
                timer = setTimeout(submit, 180);
            }
        });

        function submit() {
            if (sent || !scanForm || scanEl.value.trim() === '') return;
            sent = true;                                  // evita doble envío si además llega Enter
            if (hint) hint.textContent = 'Buscando…';
            scanForm.submit();
        }
    }

    /* Selección por lote */
    var form  = document.getElementById('barcodeForm');
    if (!form) return;
    var all   = document.getElementById('checkAll');
    var items = form.querySelectorAll('[data-item]');
    var count = document.getElementById('selCount');

    /* Resalta la tarjeta seleccionada (sin depender de variantes CSS modernas) */
    function paint(box) {
        var card = box.closest('[data-card]');
        if (!card) return;
        var on = ['border-brand-red', 'ring-2', 'ring-brand-red/15'];
        on.forEach(function (c) { card.classList.toggle(c, box.checked); });
        card.classList.toggle('border-gray-100', !box.checked);
    }

    var modoEl  = document.getElementById('modo');
    var copiasEl= document.getElementById('copias');
    var totalEl = form.querySelector('[data-total-units]');

    function byUnits() { return !modoEl || modoEl.value === 'unidades'; }

    function refresh() {
        var checked = form.querySelectorAll('[data-item]:checked');
        var n = checked.length;
        items.forEach(paint);

        if (count) {
            var copies = Math.max(1, parseInt((copiasEl && copiasEl.value) || '1', 10) || 1);
            var labels = 0;
            checked.forEach(function (c) {
                labels += byUnits() ? (parseInt(c.getAttribute('data-units'), 10) || 1) : 1;
            });
            labels *= copies;
            count.textContent = n === 0
                ? '0 seleccionados'
                : n + (n === 1 ? ' seleccionado' : ' seleccionados') + ' · ' + labels + ' etiqueta' + (labels === 1 ? '' : 's');
        }

        if (totalEl) {
            var t = parseInt(totalEl.getAttribute(byUnits() ? 'data-total-units' : 'data-total-prods'), 10) || 0;
            totalEl.textContent = '(' + t + ' etiqueta' + (t === 1 ? '' : 's') + ')';
        }

        if (all) {
            all.checked = n > 0 && n === items.length;
            all.indeterminate = n > 0 && n < items.length;
        }
    }
    if (modoEl)   modoEl.addEventListener('change', refresh);
    if (copiasEl) copiasEl.addEventListener('input', refresh);
    if (all) all.addEventListener('change', function () {
        items.forEach(function (i) { i.checked = all.checked; });
        refresh();
    });
    items.forEach(function (i) { i.addEventListener('change', refresh); });
    refresh();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
