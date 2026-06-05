<?php
/**
 * Parcial reutilizable: campos del formulario de filtros del inventario.
 * Se incluye dentro de un <form method="get"> (escritorio y modal móvil).
 *
 * Espera estas variables ya definidas por inventario.php:
 *   $categories, $sizes, $colors, $sort
 *   $catId, $type, $size, $color, $q, $priceMin, $priceMax, $dateValid, $hasFilters
 *
 * No abre ni cierra el <form>: solo aporta los campos internos.
 */
if (!defined('LCN_ROOT')) { exit; }
?>
<!-- Conservar el orden seleccionado al aplicar filtros -->
<input type="hidden" name="sort" value="<?= e($sort) ?>">

<!-- Búsqueda -->
<div class="mb-5">
    <label for="f-q-<?= e($sort) ?>" class="lcn-label">Buscar</label>
    <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><?= icon('search', 'w-4 h-4') ?></span>
        <input type="search" id="f-q-<?= e($sort) ?>" name="q" value="<?= e($q) ?>"
               placeholder="Nombre, SKU…" class="lcn-input !pl-9">
    </div>
</div>

<!-- Categoría -->
<div class="mb-5">
    <label class="lcn-label">Categoría</label>
    <select name="category_id" class="lcn-input">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $catId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Tipo -->
<div class="mb-5">
    <label class="lcn-label">Tipo</label>
    <select name="type" class="lcn-input">
        <option value="">Todos</option>
        <option value="rental" <?= $type === 'rental' ? 'selected' : '' ?>>Solo alquiler</option>
        <option value="sale"   <?= $type === 'sale'   ? 'selected' : '' ?>>Solo venta</option>
        <option value="both"   <?= $type === 'both'   ? 'selected' : '' ?>>Alquiler y venta</option>
    </select>
</div>

<!-- Talla -->
<?php if ($sizes): ?>
<div class="mb-5">
    <label class="lcn-label">Talla</label>
    <select name="size" class="lcn-input">
        <option value="">Cualquier talla</option>
        <?php foreach ($sizes as $s): ?>
            <option value="<?= e($s['size']) ?>" <?= $size === $s['size'] ? 'selected' : '' ?>><?= e($s['size']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<!-- Color -->
<?php if ($colors): ?>
<div class="mb-5">
    <label class="lcn-label">Color</label>
    <select name="color" class="lcn-input">
        <option value="">Cualquier color</option>
        <?php foreach ($colors as $c): ?>
            <option value="<?= e($c['color']) ?>" <?= $color === $c['color'] ? 'selected' : '' ?>><?= e($c['color']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<!-- Rango de precio de alquiler -->
<div class="mb-5">
    <label class="lcn-label">Precio de alquiler (<?= e(setting('currency', 'RD$')) ?>)</label>
    <div class="flex items-center gap-2">
        <input type="number" name="price_min" value="<?= e($priceMin) ?>" min="0" step="100" placeholder="Mín." class="lcn-input">
        <span class="text-gray-400">—</span>
        <input type="number" name="price_max" value="<?= e($priceMax) ?>" min="0" step="100" placeholder="Máx." class="lcn-input">
    </div>
</div>

<!-- Disponibilidad en fecha -->
<div class="mb-6">
    <label for="f-date-<?= e($sort) ?>" class="lcn-label">Disponible en fecha</label>
    <input type="date" id="f-date-<?= e($sort) ?>" name="date" value="<?= e($dateValid ?? '') ?>" class="lcn-input">
    <p class="mt-1.5 text-xs text-gray-400">Mostramos solo las piezas libres ese día.</p>
</div>

<!-- Acciones -->
<div class="flex flex-col gap-2">
    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
        <?= icon('filter', 'w-4 h-4') ?> Aplicar filtros
    </button>
    <?php if ($hasFilters): ?>
        <a href="<?= pub_url('inventario.php') ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            <?= icon('return', 'w-4 h-4') ?> Limpiar filtros
        </a>
    <?php endif; ?>
</div>
