<?php
/**
 * Productos / Inventario — Listado (Product Grid estilo Shoplytic).
 * LONDRES Casa de Novias
 *
 * admin/productos/index.php  (N=2)  ·  Permiso: products.view
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.view');

/* ------------------------------------------------------------------ *
 *  Filtros GET
 * ------------------------------------------------------------------ */
$q          = get_param('q');
$categoryId = (int) get_param('category_id', '0');
$type       = get_param('type');
$comStatus  = get_param('commercial_status');
$sort       = get_param('sort', 'recent');
$view       = get_param('view', 'grid');
$priceMin   = (float) get_param('price_min', '0');
$priceMax   = (float) get_param('price_max', '0');

$allowedTypes = ['rental', 'sale', 'both', 'complement'];
$allowedComm  = ['available', 'reserved', 'rented', 'sold', 'unavailable', 'maintenance'];
$allowedSorts = ['recent', 'name', 'price_low', 'price_high'];
if (!in_array($type, $allowedTypes, true))     $type = '';
if (!in_array($comStatus, $allowedComm, true)) $comStatus = '';
if (!in_array($sort, $allowedSorts, true))     $sort = 'recent';
if (!in_array($view, ['grid', 'list'], true))  $view = 'grid';

/* ------------------------------------------------------------------ *
 *  WHERE dinámico (prepared)
 * ------------------------------------------------------------------ */
$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q OR p.color LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($categoryId > 0) { $where[] = 'p.category_id = :cat'; $params['cat'] = $categoryId; }
if ($type === 'complement') {
    $where[] = 'p.is_complement = 1';
} elseif ($type !== '') {
    $where[] = 'p.type = :type';
    $params['type'] = $type;
}
if ($comStatus !== '') { $where[] = 'p.commercial_status = :com'; $params['com'] = $comStatus; }
if ($priceMin > 0)   { $where[] = 'p.rental_price >= :pmin'; $params['pmin'] = $priceMin; }
if ($priceMax > 0)   { $where[] = 'p.rental_price <= :pmax'; $params['pmax'] = $priceMax; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderSql = match ($sort) {
    'name'       => 'p.name ASC',
    'price_low'  => 'p.rental_price ASC',
    'price_high' => 'p.rental_price DESC',
    default      => 'p.created_at DESC',
};

/* Conteo + paginación */
$total = (int) db_value("SELECT COUNT(*) FROM products p $whereSql", $params);
$pg    = paginate($total, 12);

$products = db_all(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY $orderSql
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",
    $params
);

/* Categorías para el filtro (con conteo) */
$categories = db_all(
    "SELECT c.id, c.name, COUNT(p.id) AS total
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
     WHERE c.status = 'active'
     GROUP BY c.id, c.name
     ORDER BY c.name ASC"
);
$maxPrice  = (int) ceil((float) db_value("SELECT COALESCE(MAX(rental_price), 1000) FROM products WHERE status='active'"));
$canManage = user_can('products.manage');
$hasFilters = $q !== '' || $categoryId || $type !== '' || $comStatus !== '' || $priceMin > 0 || $priceMax > 0;

/* ------------------------------------------------------------------ *
 *  Cabecera de la página
 * ------------------------------------------------------------------ */
$page_title    = 'Productos';
$page_subtitle = 'Gestiona el inventario fácilmente';
$active        = 'productos';
$header_actions = '';
if ($canManage) {
    $header_actions = '<a href="' . admin_url('productos/crear.php') . '" class="inline-flex items-center gap-2 rounded-full bg-brand-red px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
        . icon('plus', 'w-4 h-4') . ' Crear producto</a>';
}
require LCN_ROOT . '/app/views/layouts/admin_header.php';

/* Iconos por estado de input radio reutilizables */
$radioCls = 'h-4 w-4 border-gray-300 text-brand-red focus:ring-brand-red/40';
?>

<div class="flex flex-col gap-6 lg:flex-row lg:items-start">

    <!-- =========================== SIDEBAR DE FILTROS =========================== -->
    <aside class="w-full lg:w-72 lg:shrink-0">
        <form method="get" action="<?= admin_url('productos/index.php') ?>" class="space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-soft lg:sticky lg:top-24">
            <input type="hidden" name="view" value="<?= e($view) ?>">

            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 font-serif text-lg font-semibold text-gray-900"><?= icon('filter', 'w-5 h-5 text-brand-red') ?> Filtros</h3>
                <?php if ($hasFilters): ?><a href="<?= admin_url('productos/index.php') ?>" class="text-xs font-medium text-brand-red hover:underline">Limpiar</a><?php endif; ?>
            </div>

            <!-- Buscar -->
            <div>
                <label class="lcn-label" for="f-q">Buscar</label>
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
                    <input id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, SKU, código o color…" class="lcn-input pl-9 text-sm">
                </label>
            </div>

            <!-- Categorías -->
            <div>
                <label class="lcn-label">Categorías</label>
                <div class="max-h-56 space-y-0.5 overflow-auto pr-1 no-scrollbar">
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                        <input type="radio" name="category_id" value="" <?= !$categoryId ? 'checked' : '' ?> class="<?= $radioCls ?>">
                        <span class="flex-1 text-sm text-gray-600">Todas</span>
                        <span class="text-xs text-gray-400"><?= (int) db_value("SELECT COUNT(*) FROM products WHERE status='active'") ?></span>
                    </label>
                    <?php foreach ($categories as $c): ?>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                            <input type="radio" name="category_id" value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'checked' : '' ?> class="<?= $radioCls ?>">
                            <span class="flex-1 text-sm text-gray-600"><?= e($c['name']) ?></span>
                            <span class="text-xs text-gray-400"><?= (int) $c['total'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tipo -->
            <div>
                <label class="lcn-label" for="f-type">Tipo</label>
                <select id="f-type" name="type" class="lcn-input text-sm">
                    <option value="">Todos</option>
                    <option value="rental" <?= $type === 'rental' ? 'selected' : '' ?>>Alquiler</option>
                    <option value="sale"   <?= $type === 'sale'   ? 'selected' : '' ?>>Venta</option>
                    <option value="both"   <?= $type === 'both'   ? 'selected' : '' ?>>Alquiler y venta</option>
                    <option value="complement" <?= $type === 'complement' ? 'selected' : '' ?>>Complementos</option>
                </select>
            </div>

            <!-- Estado -->
            <div>
                <label class="lcn-label" for="f-com">Estado</label>
                <select id="f-com" name="commercial_status" class="lcn-input text-sm">
                    <option value="">Todos</option>
                    <option value="available"   <?= $comStatus === 'available'   ? 'selected' : '' ?>>Disponible</option>
                    <option value="reserved"    <?= $comStatus === 'reserved'    ? 'selected' : '' ?>>Reservado</option>
                    <option value="rented"      <?= $comStatus === 'rented'      ? 'selected' : '' ?>>Alquilado</option>
                    <option value="sold"        <?= $comStatus === 'sold'        ? 'selected' : '' ?>>Vendido</option>
                    <option value="maintenance" <?= $comStatus === 'maintenance' ? 'selected' : '' ?>>En reparación</option>
                    <option value="unavailable" <?= $comStatus === 'unavailable' ? 'selected' : '' ?>>No disponible</option>
                </select>
            </div>

            <!-- Rango de precio -->
            <div>
                <label class="lcn-label">Precio de alquiler</label>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-gray-400">$</span>
                        <input type="number" name="price_min" min="0" value="<?= $priceMin > 0 ? (int) $priceMin : '' ?>" placeholder="0" class="lcn-input pl-6 text-sm">
                    </div>
                    <span class="text-gray-300">—</span>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-gray-400">$</span>
                        <input type="number" name="price_max" min="0" value="<?= $priceMax > 0 ? (int) $priceMax : '' ?>" placeholder="<?= $maxPrice ?>" class="lcn-input pl-6 text-sm">
                    </div>
                </div>
                <p class="mt-1.5 text-xs text-gray-400">Precio máximo del catálogo: <?= e(money($maxPrice)) ?></p>
            </div>

            <!-- Orden -->
            <div>
                <label class="lcn-label" for="f-sort">Ordenar por</label>
                <select id="f-sort" name="sort" class="lcn-input text-sm">
                    <option value="recent"     <?= $sort === 'recent'     ? 'selected' : '' ?>>Más recientes</option>
                    <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>Nombre (A-Z)</option>
                    <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>>Precio: menor a mayor</option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Precio: mayor a menor</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-full bg-brand-red py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                Aplicar filtros
            </button>
        </form>
    </aside>

    <!-- =========================== RESULTADOS =========================== -->
    <div class="min-w-0 flex-1">
        <!-- Barra de resultados -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-soft">
            <p class="text-sm text-gray-500">
                Mostrando <span class="font-semibold text-gray-900"><?= count($products) ?></span>
                de <span class="font-semibold text-gray-900"><?= (int) $total ?></span> producto<?= $total === 1 ? '' : 's' ?>
            </p>
            <div class="inline-flex rounded-full border border-gray-200 bg-gray-50 p-1">
                <a href="<?= e(query_url(['view' => 'grid'])) ?>" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition <?= $view === 'grid' ? 'bg-white text-brand-red shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>"><?= icon('squares', 'w-4 h-4') ?> <span class="hidden sm:inline">Cuadrícula</span></a>
                <a href="<?= e(query_url(['view' => 'list'])) ?>" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition <?= $view === 'list' ? 'bg-white text-brand-red shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>"><?= icon('menu', 'w-4 h-4') ?> <span class="hidden sm:inline">Lista</span></a>
            </div>
        </div>

        <?php if (!$products): ?>
            <?= empty_state(
                'No se encontraron productos',
                $hasFilters ? 'Ajusta los filtros o limpia la búsqueda para ver más resultados.' : 'Aún no hay productos en el inventario. Crea el primero para comenzar.',
                'tag',
                $canManage ? '<a href="' . admin_url('productos/crear.php') . '" class="inline-flex items-center gap-2 rounded-full bg-brand-red px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Crear producto</a>' : ''
            ) ?>

        <?php elseif ($view === 'grid'): ?>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($products as $p): ?>
                    <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft transition hover:-translate-y-1 hover:shadow-card">
                        <a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>" class="relative block aspect-[3/4] overflow-hidden bg-gray-100">
                            <img src="<?= e(upload_url($p['main_image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            <span class="absolute left-2.5 top-2.5"><?= status_badge($p['commercial_status'], 'commercial') ?></span>
                            <?php if (!empty($p['featured'])): ?>
                                <span class="absolute right-2.5 top-2.5 inline-flex items-center gap-1 rounded-full bg-brand-dark/80 px-2 py-0.5 text-[11px] font-medium text-brand-gold backdrop-blur"><?= icon('sparkles', 'w-3 h-3') ?> Destacado</span>
                            <?php endif; ?>
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-brand-red"><?= e($p['category_name'] ?? 'General') ?></p>
                            <h3 class="mt-1 line-clamp-2 font-serif text-base font-medium leading-tight text-gray-900"><a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>" class="hover:text-brand-red"><?= e($p['name']) ?></a></h3>
                            <p class="mt-0.5 text-xs text-gray-400">SKU: <?= e($p['sku'] ?: '—') ?></p>
                            <div class="mt-auto flex items-end justify-between pt-3">
                                <div>
                                    <?php if (in_array($p['type'], ['rental', 'both'], true)): ?>
                                        <p class="text-[11px] text-gray-400">Alquiler</p>
                                        <p class="text-lg font-bold text-gray-900"><?= e(money($p['rental_price'])) ?></p>
                                    <?php elseif (!empty($p['sale_price'])): ?>
                                        <p class="text-[11px] text-gray-400">Venta</p>
                                        <p class="text-lg font-bold text-gray-900"><?= e(money($p['sale_price'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:border-gray-300 hover:text-gray-700" title="Ver"><?= icon('eye', 'w-4 h-4') ?></a>
                                    <?php if ($canManage): ?>
                                        <a href="<?= admin_url('productos/editar.php?id=' . (int) $p['id']) ?>" class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-600" title="Editar"><?= icon('pencil', 'w-4 h-4') ?></a>
                                        <form method="post" action="<?= admin_url('productos/eliminar.php') ?>"
                                              data-confirm="¿Eliminar &quot;<?= e($p['name']) ?>&quot; del inventario? Si tiene alquileres o ventas asociados se desactivará en lugar de borrarse."
                                              class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" title="Eliminar"
                                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 text-gray-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
                                                <?= icon('trash', 'w-4 h-4') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoría</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">SKU</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Alquiler</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Venta</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($products as $p): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="<?= e(upload_url($p['main_image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="h-12 w-12 flex-shrink-0 rounded-xl object-cover ring-1 ring-gray-100">
                                            <div class="min-w-0">
                                                <a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>" class="block truncate font-medium text-gray-900 hover:text-brand-red"><?= e($p['name']) ?></a>
                                                <p class="text-xs text-gray-400"><?= e($p['size'] ?: '—') ?><?= $p['color'] ? ' · ' . e($p['color']) : '' ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($p['category_name'] ?? 'General') ?></td>
                                    <td class="px-5 py-4 text-gray-500"><?= e($p['sku'] ?: '—') ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= in_array($p['type'], ['rental', 'both'], true) ? e(money($p['rental_price'])) : '—' ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= !empty($p['sale_price']) ? e(money($p['sale_price'])) : '—' ?></td>
                                    <td class="px-5 py-4"><?= status_badge($p['commercial_status'], 'commercial') ?></td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="<?= admin_url('productos/ver.php?id=' . (int) $p['id']) ?>" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" title="Ver"><?= icon('eye', 'w-4 h-4') ?></a>
                                            <?php if ($canManage): ?>
                                                <a href="<?= admin_url('productos/editar.php?id=' . (int) $p['id']) ?>" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-400 transition hover:bg-sky-50 hover:text-sky-600" title="Editar"><?= icon('pencil', 'w-4 h-4') ?></a>
                                                <form method="post" action="<?= admin_url('productos/eliminar.php') ?>" data-confirm="¿Eliminar este producto? Esta acción no se puede deshacer." class="inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600" title="Eliminar"><?= icon('trash', 'w-4 h-4') ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pg['pages'] > 1): ?>
            <div class="mt-6 flex justify-center"><?= render_pagination($pg['page'], $pg['pages']) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
