<?php
/**
 * LONDRES Casa de Novias — Catálogo público con filtros (GET).
 *
 * Filtros soportados (todos vía GET, conservados con query_url):
 *   category_id, type (rental|sale|both), size, color,
 *   price_min, price_max (sobre rental_price), q (búsqueda),
 *   sort (recientes|precio_asc|precio_desc|destacados),
 *   date (disponibilidad para una fecha: excluye piezas con alquiler
 *         bloqueante que solape ese día).
 *
 * Sólo muestra productos con status = 'active'.
 * Reusa product_card(), render_pagination(), empty_state(), icon().
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';   // public/*.php => N=1

/* ------------------------------------------------------------------ *
 *  Lectura y saneo de filtros GET
 * ------------------------------------------------------------------ */
$catId    = (int) get_param('category_id', '0');
$type     = get_param('type');
$size     = get_param('size');
$color    = get_param('color');
$q        = get_param('q');
$sort     = get_param('sort', 'recientes');
$date     = get_param('date');           // YYYY-MM-DD para disponibilidad
$priceMin = get_param('price_min');
$priceMax = get_param('price_max');

// Normalizar valores numéricos opcionales
$priceMinNum = ($priceMin !== '' && is_numeric($priceMin)) ? (float) $priceMin : null;
$priceMaxNum = ($priceMax !== '' && is_numeric($priceMax)) ? (float) $priceMax : null;

// Validar fecha (formato y existencia real)
$dateValid = null;
if ($date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) {
        $dateValid = $date;
    }
}

$allowedTypes = ['rental', 'sale', 'both'];
if (!in_array($type, $allowedTypes, true)) {
    $type = '';
}

/* ------------------------------------------------------------------ *
 *  Construcción dinámica del WHERE (siempre prepared statements)
 * ------------------------------------------------------------------ */
$where  = ["p.status = 'active'"];
$params = [];

if ($catId > 0) {
    $where[]            = 'p.category_id = :cat';
    $params['cat']      = $catId;
}
if ($type !== '') {
    $where[]            = 'p.type = :type';
    $params['type']     = $type;
}
if ($size !== '') {
    $where[]            = 'p.size = :size';
    $params['size']     = $size;
}
if ($color !== '') {
    $where[]            = 'p.color = :color';
    $params['color']    = $color;
}
if ($q !== '') {
    // Placeholders distintos por columna: PDO nativo no permite reusar uno.
    $like               = '%' . $q . '%';
    $where[]            = '(p.name LIKE :q1 OR p.description LIKE :q2 OR p.sku LIKE :q3)';
    $params['q1']       = $like;
    $params['q2']       = $like;
    $params['q3']       = $like;
}
if ($priceMinNum !== null) {
    $where[]            = 'p.rental_price >= :pmin';
    $params['pmin']     = $priceMinNum;
}
if ($priceMaxNum !== null) {
    $where[]            = 'p.rental_price <= :pmax';
    $params['pmax']     = $priceMaxNum;
}

/*
 * Disponibilidad en fecha: excluye los productos que tengan un alquiler
 * en estado bloqueante (mismas reglas que checkProductAvailability) cuyo
 * rango [delivery_date, return_date] cubra la fecha seleccionada.
 */
if ($dateValid !== null) {
    $blocking = RENTAL_BLOCKING_STATUSES;
    $place    = [];
    foreach ($blocking as $i => $st) {
        $key            = 'bs' . $i;
        $place[]        = ':' . $key;
        $params[$key]   = $st;
    }
    // Se consultan rental_items (no rentals.product_id): un alquiler puede
    // llevar varias piezas y todas quedan ocupadas, no solo la principal.
    // Dos placeholders distintos: PDO (sin emulación) no permite reusar uno.
    $where[]            = 'p.id NOT IN (
        SELECT ri.product_id FROM rental_items ri
        JOIN rentals r ON r.id = ri.rental_id
        WHERE r.rental_status IN (' . implode(',', $place) . ')
          AND r.delivery_date <= :avd1
          AND r.return_date   >= :avd2
    )';
    $params['avd1']     = $dateValid;
    $params['avd2']     = $dateValid;
}

$whereSql = implode(' AND ', $where);

/* ------------------------------------------------------------------ *
 *  Orden
 * ------------------------------------------------------------------ */
$orderMap = [
    'recientes'   => 'p.created_at DESC, p.id DESC',
    'precio_asc'  => 'p.rental_price ASC, p.id DESC',
    'precio_desc' => 'p.rental_price DESC, p.id DESC',
    'destacados'  => 'p.featured DESC, p.created_at DESC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['recientes'];

/* ------------------------------------------------------------------ *
 *  Conteo total + paginación
 * ------------------------------------------------------------------ */
$total = (int) db_value("SELECT COUNT(*) FROM products p WHERE $whereSql", $params);
$pg    = paginate($total, 12);

/* ------------------------------------------------------------------ *
 *  Resultados (product_card requiere p.* + category_name)
 *  LIMIT/OFFSET interpolados de forma segura (enteros validados).
 * ------------------------------------------------------------------ */
$limit  = (int) $pg['perPage'];
$offset = (int) $pg['offset'];
$products = db_all(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY $orderSql
     LIMIT $limit OFFSET $offset",
    $params
);

/* ------------------------------------------------------------------ *
 *  Datos auxiliares para los selects de filtro
 * ------------------------------------------------------------------ */
$categories = db_all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
$sizes      = db_all("SELECT DISTINCT size  FROM products WHERE status='active' AND size  IS NOT NULL AND size  <> '' ORDER BY size  ASC");
$colors     = db_all("SELECT DISTINCT color FROM products WHERE status='active' AND color IS NOT NULL AND color <> '' ORDER BY color ASC");

// ¿Hay filtros activos? (para mostrar el botón "Limpiar")
$hasFilters = ($catId > 0) || $type !== '' || $size !== '' || $color !== ''
           || $q !== '' || $priceMin !== '' || $priceMax !== '' || $dateValid !== null;

$page_title    = 'Inventario';
$active_public = 'inventario';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<!-- ============================================================= -->
<!--  ENCABEZADO DEL CATÁLOGO                                      -->
<!-- ============================================================= -->
<section class="border-b border-gray-100 bg-brand-cream/50">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <p class="font-script text-2xl text-brand-red">Nuestra colección</p>
        <h1 class="mt-1 font-display text-4xl font-medium text-brand-dark sm:text-5xl">Inventario</h1>
        <p class="mt-3 max-w-2xl text-gray-500">
            Explora vestidos de novia, vestidos de gala, trajes y accesorios. Filtra por categoría,
            talla, color, precio y disponibilidad para encontrar tu pieza ideal.
        </p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="lg:grid lg:grid-cols-12 lg:gap-8">

        <!-- =============================== -->
        <!--  PANEL DE FILTROS (lateral)     -->
        <!-- =============================== -->
        <aside class="lg:col-span-3">
            <!-- Botón móvil para abrir filtros -->
            <button type="button" data-modal-open="filtros-modal"
                    class="mb-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-soft transition hover:bg-gray-50 lg:hidden">
                <?= icon('filter', 'w-4 h-4') ?> Filtros
                <?php if ($hasFilters): ?><span class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-red px-1.5 text-xs font-semibold text-white">•</span><?php endif; ?>
            </button>

            <!-- Filtros: visibles en escritorio, modal en móvil -->
            <form method="get" action="<?= pub_url('inventario.php') ?>"
                  class="hidden rounded-2xl border border-gray-100 bg-white p-5 shadow-soft lg:block">
                <?php require __DIR__ . '/_inventario_filtros.php'; ?>
            </form>
        </aside>

        <!-- =============================== -->
        <!--  RESULTADOS                     -->
        <!-- =============================== -->
        <section class="mt-2 lg:col-span-9 lg:mt-0">
            <!-- Barra superior: conteo + orden -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    <span class="font-semibold text-gray-900"><?= e((string) $total) ?></span>
                    <?= $total === 1 ? 'pieza encontrada' : 'piezas encontradas' ?>
                    <?php if ($dateValid !== null): ?>
                        · disponibles el <span class="font-medium text-brand-red"><?= e(format_date($dateValid)) ?></span>
                    <?php endif; ?>
                </p>

                <form method="get" action="<?= pub_url('inventario.php') ?>" class="flex items-center gap-2">
                    <!-- Conservar filtros actuales al cambiar el orden -->
                    <?php
                    $keep = ['category_id' => $catId ?: '', 'type' => $type, 'size' => $size, 'color' => $color,
                             'q' => $q, 'price_min' => $priceMin, 'price_max' => $priceMax, 'date' => $dateValid ?? ''];
                    foreach ($keep as $k => $v):
                        if ($v === '' || $v === null) continue; ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
                    <?php endforeach; ?>
                    <label for="sort" class="text-sm text-gray-500">Ordenar:</label>
                    <select id="sort" name="sort" class="lcn-input !w-auto !py-2" onchange="this.form.submit()">
                        <?php
                        $sortOpts = [
                            'recientes'   => 'Más recientes',
                            'destacados'  => 'Destacados',
                            'precio_asc'  => 'Precio: menor a mayor',
                            'precio_desc' => 'Precio: mayor a menor',
                        ];
                        foreach ($sortOpts as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($products): ?>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($products as $p): ?>
                        <?= product_card($p) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($pg['pages'] > 1): ?>
                    <div class="mt-10 flex justify-center">
                        <?= render_pagination($pg['page'], $pg['pages']) ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?= empty_state(
                    'No encontramos piezas',
                    'No hay productos que coincidan con los filtros seleccionados. Prueba a ajustar tu búsqueda.',
                    'search',
                    '<a href="' . e(pub_url('inventario.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
                    . icon('return', 'w-4 h-4') . ' Limpiar filtros</a>'
                ) ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- =============================== -->
<!--  MODAL DE FILTROS (móvil)       -->
<!-- =============================== -->
<div id="filtros-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-dark/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-card animate-scale-in">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-serif text-xl font-semibold text-brand-dark">Filtrar inventario</h3>
            <button type="button" data-modal-close class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"><?= icon('x', 'w-5 h-5') ?></button>
        </div>
        <form method="get" action="<?= pub_url('inventario.php') ?>" class="max-h-[70vh] overflow-y-auto pr-1">
            <?php require __DIR__ . '/_inventario_filtros.php'; ?>
        </form>
    </div>
</div>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
