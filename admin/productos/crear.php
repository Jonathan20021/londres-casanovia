<?php
/**
 * Productos / Inventario — Crear producto.
 * LONDRES Casa de Novias
 *
 * admin/productos/crear.php  (N=2)
 * Permiso: products.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

$errors = [];
/* Valores por defecto del formulario (se reusan al re-pintar tras error) */
$form = [
    'name'              => '',
    'sku'               => '',
    'category_id'       => '',
    'type'              => 'rental',
    'is_complement'     => '0',
    'rental_price'      => '',
    'sale_price'        => '',
    'cost_price'        => '',
    'deposit_amount'    => '',
    'size'              => '',
    'color'             => '',
    'material'          => '',
    'condition_status'  => 'excellent',
    'commercial_status' => 'available',
    'is_unique'         => '1',
    'quantity'          => '1',
    'featured'          => '0',
    'status'            => 'active',
    'description'       => '',
    'internal_notes'    => '',
];

/* ------------------------------------------------------------------ *
 *  Manejo POST
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    /* Recoger campos */
    foreach ($form as $k => $_) {
        if (in_array($k, ['is_unique', 'featured', 'is_complement'], true)) {
            $form[$k] = post($k) ? '1' : '0';
        } else {
            $form[$k] = trim((string) post($k, ''));
        }
    }

    /* Validaciones */
    $allowedTypes  = ['rental', 'sale', 'both'];
    $allowedCond   = ['new', 'excellent', 'good', 'repair', 'out_of_service'];
    $allowedComm   = ['available', 'reserved', 'rented', 'sold', 'unavailable', 'maintenance'];
    $allowedStatus = ['active', 'inactive'];

    if ($form['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }
    if (!in_array($form['type'], $allowedTypes, true)) {
        $errors['type'] = 'Tipo inválido.';
    }
    if (!in_array($form['condition_status'], $allowedCond, true))   $form['condition_status'] = 'excellent';
    if (!in_array($form['commercial_status'], $allowedComm, true))  $form['commercial_status'] = 'available';
    if (!in_array($form['status'], $allowedStatus, true))           $form['status'] = 'active';

    /* SKU único (si se proporciona) */
    if ($form['sku'] !== '') {
        $exists = (int) db_value('SELECT COUNT(*) FROM products WHERE sku = :sku', ['sku' => $form['sku']]);
        if ($exists > 0) {
            $errors['sku'] = 'Ya existe un producto con ese SKU.';
        }
    }

    /* Categoría válida (si se elige) */
    $categoryId = $form['category_id'] !== '' ? (int) $form['category_id'] : null;
    if ($categoryId !== null) {
        $catOk = (int) db_value('SELECT COUNT(*) FROM categories WHERE id = :id', ['id' => $categoryId]);
        if ($catOk === 0) $categoryId = null;
    }

    if (!$errors) {
        $u = current_user();

        /* Subir imagen principal (opcional) */
        $mainPath = null;
        if (!empty($_FILES['main_image']['name'])) {
            $up = upload_image($_FILES['main_image'], 'products');
            if ($up['ok']) {
                $mainPath = $up['path'];
            } else {
                $errors['main_image'] = $up['error'];
            }
        }

        if (!$errors) {
            /* Insertar producto */
            $productId = db_insert('products', [
                'category_id'       => $categoryId,
                'name'              => $form['name'],
                'slug'              => unique_slug('products', $form['name']),
                'sku'               => $form['sku'] !== '' ? $form['sku'] : null,
                'type'              => $form['type'],
                'is_complement'     => $form['is_complement'] === '1' ? 1 : 0,
                'description'       => $form['description'] !== '' ? $form['description'] : null,
                'internal_notes'    => $form['internal_notes'] !== '' ? $form['internal_notes'] : null,
                'rental_price'      => (float) ($form['rental_price'] !== '' ? $form['rental_price'] : 0),
                'sale_price'        => $form['sale_price'] !== '' ? (float) $form['sale_price'] : null,
                'cost_price'        => $form['cost_price'] !== '' ? (float) $form['cost_price'] : null,
                'deposit_amount'    => (float) ($form['deposit_amount'] !== '' ? $form['deposit_amount'] : 0),
                'size'              => $form['size'] !== '' ? $form['size'] : null,
                'color'             => $form['color'] !== '' ? $form['color'] : null,
                'material'          => $form['material'] !== '' ? $form['material'] : null,
                'condition_status'  => $form['condition_status'],
                'commercial_status' => $form['commercial_status'],
                'is_unique'         => $form['is_unique'] === '1' ? 1 : 0,
                'quantity'          => max(0, (int) ($form['quantity'] !== '' ? $form['quantity'] : 1)),
                'main_image'        => $mainPath,
                'featured'          => $form['featured'] === '1' ? 1 : 0,
                'status'            => $form['status'],
                'created_by'        => $u['id'] ?? null,
            ]);

            $sort = 0;

            /* Imagen principal -> product_images (is_main = 1) */
            if ($mainPath !== null) {
                db_insert('product_images', [
                    'product_id' => $productId,
                    'image_path' => $mainPath,
                    'is_main'    => 1,
                    'sort_order' => $sort++,
                ]);
            }

            /* Galería (input multiple name="images[]") */
            if (!empty($_FILES['images']['name'][0])) {
                $gallery = upload_images($_FILES['images'], 'products');
                foreach ($gallery as $i => $gpath) {
                    $isMain = ($mainPath === null && $i === 0) ? 1 : 0;
                    db_insert('product_images', [
                        'product_id' => $productId,
                        'image_path' => $gpath,
                        'is_main'    => $isMain,
                        'sort_order' => $sort++,
                    ]);
                    /* Si no había principal, la primera de la galería pasa a serlo */
                    if ($isMain) {
                        $mainPath = $gpath;
                        db_update('products', ['main_image' => $gpath], 'id = :id', ['id' => $productId]);
                    }
                }
            }

            /* Código de barras automático (Code 128) */
            $newBarcode = barcode_assign($productId);

            log_activity('product.create', 'product', $productId, 'Producto creado: ' . $form['name'] . ' · código ' . $newBarcode);
            flash('success', 'Producto creado correctamente. Código de barras asignado: ' . $newBarcode);
            redirect(admin_url('productos/ver.php?id=' . $productId));
        }
    }

    if ($errors) {
        flash('error', 'Revise los campos marcados e intente de nuevo.');
    }
}

/* Categorías para el select */
$categories = db_all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");

/* Código de barras que se asignará automáticamente al guardar */
$nextBarcode = barcode_next_preview();

$page_title    = 'Crear producto';
$page_subtitle = 'Añade un nuevo artículo al inventario';
$active        = 'productos';
$header_actions = '<a href="' . admin_url('productos/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver al listado</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<form method="post" action="<?= admin_url('productos/crear.php') ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <?= csrf_field() ?>

    <!-- =================== IZQUIERDA: Previsualización (sticky) =================== -->
    <div class="lg:col-span-4">
        <div class="lg:sticky lg:top-28 space-y-4">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Previsualización</p>
                <div class="overflow-hidden rounded-2xl bg-gray-100">
                    <div class="relative aspect-[3/4] w-full">
                        <img id="imgPrev" src="<?= e(asset('img/placeholder.svg')) ?>" alt="Vista previa"
                             class="h-full w-full object-cover">
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-brand-red" data-preview-category>Categoría</p>
                    <h3 class="mt-1 font-serif text-xl leading-tight text-gray-900" data-preview-name>Nombre del producto</h3>
                    <p class="mt-2 text-lg font-semibold text-gray-900" data-preview-price>—</p>
                    <div class="mt-3 flex flex-wrap gap-2" data-preview-chips></div>
                </div>
            </div>

            <!-- Código de barras automático -->
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Código de barras</p>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Automático</span>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white px-3 py-3">
                    <?= barcode_svg($nextBarcode, ['module' => 1.6, 'height' => 48]) ?>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-gray-500">
                    Al guardar, el sistema asignará el código
                    <strong class="font-mono text-gray-800"><?= e($nextBarcode) ?></strong>
                    (Code 128, legible por cualquier lector). Podrá imprimir su etiqueta desde
                    <a href="<?= admin_url('codigos-barra/index.php') ?>" class="font-medium text-brand-red hover:underline">Códigos de barra</a>.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?= admin_url('productos/index.php') ?>" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Crear producto
                </button>
            </div>
        </div>
    </div>

    <!-- =================== DERECHA: Formulario =================== -->
    <div class="space-y-6 lg:col-span-8">

        <!-- Foto principal -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 font-serif text-lg text-gray-900">Foto principal</h2>
            <p class="mb-4 text-sm text-gray-500">Esta imagen representará al producto en el catálogo.</p>
            <label class="dropzone flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/60 px-6 py-8 text-center transition hover:border-brand-red/40">
                <span class="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-red shadow-soft"><?= icon('upload', 'w-6 h-6') ?></span>
                <span class="text-sm font-medium text-gray-700">Haz clic para subir la foto principal</span>
                <span class="mt-1 text-xs text-gray-400">JPG, PNG o WEBP · máx. 5 MB</span>
                <input type="file" name="main_image" accept="image/*" data-image-preview="#imgPrev" class="hidden">
            </label>
            <?php if (!empty($errors['main_image'])): ?>
                <p class="mt-2 text-sm text-rose-600"><?= e($errors['main_image']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Galería -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 font-serif text-lg text-gray-900">Galería</h2>
            <p class="mb-4 text-sm text-gray-500">Añade más fotos del producto (ángulos, detalles).</p>
            <div data-dropzone data-dropzone-preview="#galPrev"
                 class="dropzone flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/60 px-6 py-8 text-center transition hover:border-brand-red/40">
                <span class="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-gold shadow-soft"><?= icon('photo', 'w-6 h-6') ?></span>
                <span class="text-sm font-medium text-gray-700">Arrastra imágenes o haz clic para seleccionar</span>
                <span class="mt-1 text-xs text-gray-400">Puedes seleccionar varias a la vez</span>
                <input type="file" name="images[]" multiple accept="image/*" class="hidden">
            </div>
            <div id="galPrev" class="mt-3 flex flex-wrap gap-3"></div>
        </div>

        <!-- Información del producto -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-5 font-serif text-lg text-gray-900">Información del producto</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="lcn-label" for="name">Nombre <span class="text-brand-red">*</span></label>
                    <input id="name" name="name" type="text" value="<?= e($form['name']) ?>" required
                           class="lcn-input <?= isset($errors['name']) ? 'border-rose-300' : '' ?>"
                           data-live-name placeholder="Ej. Vestido de novia sirena con encaje">
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-rose-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label class="lcn-label" for="sku">SKU / Código</label>
                    <input id="sku" name="sku" type="text" value="<?= e($form['sku']) ?>"
                           class="lcn-input <?= isset($errors['sku']) ? 'border-rose-300' : '' ?>" placeholder="Ej. NOV-0001">
                    <?php if (isset($errors['sku'])): ?><p class="mt-1 text-sm text-rose-600"><?= e($errors['sku']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label class="lcn-label" for="category_id">Categoría</label>
                    <select id="category_id" name="category_id" class="lcn-input" data-live-category>
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) $form['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="lcn-label" for="type">Tipo <span class="text-brand-red">*</span></label>
                    <select id="type" name="type" class="lcn-input">
                        <option value="rental" <?= $form['type'] === 'rental' ? 'selected' : '' ?>>Alquiler</option>
                        <option value="sale"   <?= $form['type'] === 'sale'   ? 'selected' : '' ?>>Venta</option>
                        <option value="both"   <?= $form['type'] === 'both'   ? 'selected' : '' ?>>Alquiler y venta</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-brand-gold/30 bg-amber-50/50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">Es un complemento</p>
                            <p class="text-xs text-gray-500">Corbata, corona, velo, ramo… Puede tener precio 0 y su precio se ajusta al facturar.</p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" name="is_complement" value="1" class="peer sr-only" <?= $form['is_complement'] === '1' ? 'checked' : '' ?>>
                            <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-brand-gold after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="lcn-label" for="commercial_status">Estado comercial</label>
                    <select id="commercial_status" name="commercial_status" class="lcn-input" data-live-status>
                        <option value="available"   <?= $form['commercial_status'] === 'available'   ? 'selected' : '' ?>>Disponible</option>
                        <option value="reserved"    <?= $form['commercial_status'] === 'reserved'    ? 'selected' : '' ?>>Reservado</option>
                        <option value="rented"      <?= $form['commercial_status'] === 'rented'      ? 'selected' : '' ?>>Alquilado</option>
                        <option value="sold"        <?= $form['commercial_status'] === 'sold'        ? 'selected' : '' ?>>Vendido</option>
                        <option value="maintenance" <?= $form['commercial_status'] === 'maintenance' ? 'selected' : '' ?>>En reparación</option>
                        <option value="unavailable" <?= $form['commercial_status'] === 'unavailable' ? 'selected' : '' ?>>No disponible</option>
                    </select>
                </div>

                <div>
                    <label class="lcn-label" for="rental_price">Precio de alquiler</label>
                    <input id="rental_price" name="rental_price" type="number" step="0.01" min="0" value="<?= e($form['rental_price']) ?>"
                           class="lcn-input" data-live-price placeholder="0.00">
                </div>

                <div>
                    <label class="lcn-label" for="sale_price">Precio de venta</label>
                    <input id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="<?= e($form['sale_price']) ?>"
                           class="lcn-input" placeholder="0.00">
                </div>

                <div>
                    <label class="lcn-label" for="cost_price">Costo</label>
                    <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="<?= e($form['cost_price']) ?>"
                           class="lcn-input" placeholder="0.00">
                </div>

                <div>
                    <label class="lcn-label" for="deposit_amount">Depósito / Garantía</label>
                    <input id="deposit_amount" name="deposit_amount" type="number" step="0.01" min="0" value="<?= e($form['deposit_amount']) ?>"
                           class="lcn-input" placeholder="0.00">
                </div>

                <div>
                    <label class="lcn-label" for="size">Talla</label>
                    <input id="size" name="size" type="text" value="<?= e($form['size']) ?>" class="lcn-input" data-live-size placeholder="Ej. M / 8 / 38">
                </div>

                <div>
                    <label class="lcn-label" for="color">Color</label>
                    <input id="color" name="color" type="text" value="<?= e($form['color']) ?>" class="lcn-input" data-live-color placeholder="Ej. Blanco marfil">
                </div>

                <div>
                    <label class="lcn-label" for="material">Material</label>
                    <input id="material" name="material" type="text" value="<?= e($form['material']) ?>" class="lcn-input" placeholder="Ej. Encaje y tul">
                </div>

                <div>
                    <label class="lcn-label" for="condition_status">Condición</label>
                    <select id="condition_status" name="condition_status" class="lcn-input">
                        <option value="new"            <?= $form['condition_status'] === 'new'            ? 'selected' : '' ?>>Nuevo</option>
                        <option value="excellent"      <?= $form['condition_status'] === 'excellent'      ? 'selected' : '' ?>>Excelente</option>
                        <option value="good"           <?= $form['condition_status'] === 'good'           ? 'selected' : '' ?>>Bueno</option>
                        <option value="repair"         <?= $form['condition_status'] === 'repair'         ? 'selected' : '' ?>>En reparación</option>
                        <option value="out_of_service" <?= $form['condition_status'] === 'out_of_service' ? 'selected' : '' ?>>Fuera de servicio</option>
                    </select>
                </div>

                <div>
                    <label class="lcn-label" for="quantity">Cantidad en stock</label>
                    <input id="quantity" name="quantity" type="number" step="1" min="0" value="<?= e($form['quantity']) ?>" class="lcn-input">
                </div>

                <div>
                    <label class="lcn-label" for="status">Estado de publicación</label>
                    <select id="status" name="status" class="lcn-input">
                        <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Activo</option>
                        <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <!-- Switches -->
                <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Pieza única</p>
                        <p class="text-xs text-gray-400">Solo existe una unidad</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="is_unique" value="1" class="peer sr-only" <?= $form['is_unique'] === '1' ? 'checked' : '' ?>>
                        <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-brand-red after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                </div>

                <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/60 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Destacado</p>
                        <p class="text-xs text-gray-400">Resaltar en el catálogo</p>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="featured" value="1" class="peer sr-only" <?= $form['featured'] === '1' ? 'checked' : '' ?>>
                        <span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-brand-gold after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <label class="lcn-label" for="description">Descripción</label>
                    <textarea id="description" name="description" rows="4" class="lcn-input" placeholder="Descripción visible en el catálogo público…"><?= e($form['description']) ?></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="lcn-label" for="internal_notes">Notas internas</label>
                    <textarea id="internal_notes" name="internal_notes" rows="3" class="lcn-input" placeholder="Notas privadas (no se muestran al público)…"><?= e($form['internal_notes']) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
/* Datos para el formateo de moneda en la previsualización en vivo */
$lcnCurrency = setting('currency', 'RD$');
?>
<script>
(function () {
    var currency = <?= json_encode($lcnCurrency) ?>;
    var nameEl  = document.querySelector('[data-live-name]');
    var catEl   = document.querySelector('[data-live-category]');
    var priceEl = document.querySelector('[data-live-price]');
    var sizeEl  = document.querySelector('[data-live-size]');
    var colorEl = document.querySelector('[data-live-color]');
    var statusEl= document.querySelector('[data-live-status]');

    var pName  = document.querySelector('[data-preview-name]');
    var pCat   = document.querySelector('[data-preview-category]');
    var pPrice = document.querySelector('[data-preview-price]');
    var pChips = document.querySelector('[data-preview-chips]');

    function money(v) {
        var n = parseFloat(v || '0') || 0;
        return currency + ' ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function chip(text) {
        var s = document.createElement('span');
        s.className = 'inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600';
        s.textContent = text;
        return s;
    }
    function refresh() {
        if (pName)  pName.textContent  = (nameEl && nameEl.value.trim()) || 'Nombre del producto';
        if (pCat && catEl) {
            var opt = catEl.options[catEl.selectedIndex];
            pCat.textContent = (opt && opt.value) ? opt.textContent : 'Categoría';
        }
        if (pPrice && priceEl) pPrice.textContent = priceEl.value ? money(priceEl.value) : '—';
        if (pChips) {
            pChips.innerHTML = '';
            if (sizeEl && sizeEl.value.trim())   pChips.appendChild(chip('Talla ' + sizeEl.value.trim()));
            if (colorEl && colorEl.value.trim()) pChips.appendChild(chip(colorEl.value.trim()));
            if (statusEl) {
                var opt = statusEl.options[statusEl.selectedIndex];
                if (opt) pChips.appendChild(chip(opt.textContent));
            }
        }
    }
    [nameEl, catEl, priceEl, sizeEl, colorEl, statusEl].forEach(function (el) {
        if (el) { el.addEventListener('input', refresh); el.addEventListener('change', refresh); }
    });
    refresh();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
