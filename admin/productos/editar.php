<?php
/**
 * Productos / Inventario — Editar producto.
 * LONDRES Casa de Novias
 *
 * admin/productos/editar.php?id=ID  (N=2)
 * Permiso: products.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

$id = (int) get_param('id', '0');
$product = $id ? db_one('SELECT * FROM products WHERE id = :id', ['id' => $id]) : null;

if (!$product) {
    flash('error', 'El producto solicitado no existe.');
    redirect(admin_url('productos/index.php'));
}

$errors = [];
/* Cargar valores actuales en el formulario */
$form = [
    'name'              => (string) $product['name'],
    'sku'               => (string) ($product['sku'] ?? ''),
    'category_id'       => (string) ($product['category_id'] ?? ''),
    'type'              => (string) $product['type'],
    'rental_price'      => (string) $product['rental_price'],
    'sale_price'        => (string) ($product['sale_price'] ?? ''),
    'cost_price'        => (string) ($product['cost_price'] ?? ''),
    'deposit_amount'    => (string) $product['deposit_amount'],
    'size'              => (string) ($product['size'] ?? ''),
    'color'             => (string) ($product['color'] ?? ''),
    'material'          => (string) ($product['material'] ?? ''),
    'condition_status'  => (string) $product['condition_status'],
    'commercial_status' => (string) $product['commercial_status'],
    'is_unique'         => $product['is_unique'] ? '1' : '0',
    'quantity'          => (string) $product['quantity'],
    'featured'          => $product['featured'] ? '1' : '0',
    'status'            => (string) $product['status'],
    'description'       => (string) ($product['description'] ?? ''),
    'internal_notes'    => (string) ($product['internal_notes'] ?? ''),
];

/* ------------------------------------------------------------------ *
 *  Manejo POST (actualizar datos + nuevas imágenes)
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    foreach ($form as $k => $_) {
        if (in_array($k, ['is_unique', 'featured'], true)) {
            $form[$k] = post($k) ? '1' : '0';
        } else {
            $form[$k] = trim((string) post($k, ''));
        }
    }

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
    if (!in_array($form['condition_status'], $allowedCond, true))  $form['condition_status'] = $product['condition_status'];
    if (!in_array($form['commercial_status'], $allowedComm, true)) $form['commercial_status'] = $product['commercial_status'];
    if (!in_array($form['status'], $allowedStatus, true))          $form['status'] = $product['status'];

    /* SKU único excluyendo este producto */
    if ($form['sku'] !== '') {
        $exists = (int) db_value('SELECT COUNT(*) FROM products WHERE sku = :sku AND id <> :id', ['sku' => $form['sku'], 'id' => $id]);
        if ($exists > 0) {
            $errors['sku'] = 'Ya existe otro producto con ese SKU.';
        }
    }

    $categoryId = $form['category_id'] !== '' ? (int) $form['category_id'] : null;
    if ($categoryId !== null) {
        $catOk = (int) db_value('SELECT COUNT(*) FROM categories WHERE id = :id', ['id' => $categoryId]);
        if ($catOk === 0) $categoryId = null;
    }

    if (!$errors) {
        /* Si cambia el nombre, regenerar slug único (ignorando este id) */
        $slug = $product['slug'];
        if ($form['name'] !== $product['name']) {
            $slug = unique_slug('products', $form['name'], $id);
        }

        db_update('products', [
            'category_id'       => $categoryId,
            'name'              => $form['name'],
            'slug'              => $slug,
            'sku'               => $form['sku'] !== '' ? $form['sku'] : null,
            'type'              => $form['type'],
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
            'featured'          => $form['featured'] === '1' ? 1 : 0,
            'status'            => $form['status'],
        ], 'id = :id', ['id' => $id]);

        /* Próximo sort_order disponible */
        $sort = (int) db_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_images WHERE product_id = :id', ['id' => $id]);
        $hasMain = (int) db_value('SELECT COUNT(*) FROM product_images WHERE product_id = :id AND is_main = 1', ['id' => $id]) > 0;

        /* Reemplazar foto principal (si se subió una nueva) */
        if (!empty($_FILES['main_image']['name'])) {
            $up = upload_image($_FILES['main_image'], 'products');
            if ($up['ok']) {
                /* Desmarcar la principal anterior */
                db_exec('UPDATE product_images SET is_main = 0 WHERE product_id = :id', ['id' => $id]);
                db_insert('product_images', [
                    'product_id' => $id,
                    'image_path' => $up['path'],
                    'is_main'    => 1,
                    'sort_order' => $sort++,
                ]);
                db_update('products', ['main_image' => $up['path']], 'id = :id', ['id' => $id]);
                $hasMain = true;
            } else {
                flash('warning', 'La foto principal no se pudo subir: ' . $up['error']);
            }
        }

        /* Añadir imágenes a la galería */
        if (!empty($_FILES['images']['name'][0])) {
            $gallery = upload_images($_FILES['images'], 'products');
            foreach ($gallery as $gpath) {
                $isMain = !$hasMain ? 1 : 0;
                db_insert('product_images', [
                    'product_id' => $id,
                    'image_path' => $gpath,
                    'is_main'    => $isMain,
                    'sort_order' => $sort++,
                ]);
                if ($isMain) {
                    db_update('products', ['main_image' => $gpath], 'id = :id', ['id' => $id]);
                    $hasMain = true;
                }
            }
        }

        log_activity('product.update', 'product', $id, 'Producto actualizado: ' . $form['name']);
        flash('success', 'Producto actualizado correctamente.');
        redirect(admin_url('productos/ver.php?id=' . $id));
    }

    flash('error', 'Revise los campos marcados e intente de nuevo.');
}

/* Datos para mostrar */
$categories = db_all("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
$images = db_all('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC', ['id' => $id]);
$mainImage = $product['main_image'] ?: ($images[0]['image_path'] ?? null);

/* Código de barras del producto (se asigna si aún no lo tenía) */
$productBarcode = (string) ($product['barcode'] ?? '');
if ($productBarcode === '') {
    $productBarcode = barcode_assign($id);
}

$page_title    = 'Editar producto';
$page_subtitle = $product['name'];
$active        = 'productos';
$header_actions = '<a href="' . admin_url('productos/ver.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('eye', 'w-4 h-4') . ' Ver detalle</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<form method="post" action="<?= admin_url('productos/editar.php?id=' . $id) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <?= csrf_field() ?>

    <!-- =================== IZQUIERDA: Previsualización (sticky) =================== -->
    <div class="lg:col-span-4">
        <div class="lg:sticky lg:top-28 space-y-4">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Previsualización</p>
                <div class="overflow-hidden rounded-2xl bg-gray-100">
                    <div class="relative aspect-[3/4] w-full">
                        <img id="imgPrev" src="<?= e(upload_url($mainImage)) ?>" alt="Vista previa"
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

            <!-- Código de barras -->
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Código de barras</p>
                    <span class="font-mono text-[11px] tracking-widest text-gray-500"><?= e($productBarcode) ?></span>
                </div>
                <div class="rounded-xl border border-gray-100 px-3 py-3">
                    <?= barcode_svg($productBarcode, ['module' => 1.6, 'height' => 48]) ?>
                </div>
                <a href="<?= admin_url('codigos-barra/exportar.php?ids=' . $id) ?>"
                   class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                    <?= icon('printer', 'w-4 h-4') ?> Imprimir etiqueta
                </a>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?= admin_url('productos/ver.php?id=' . $id) ?>" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancelar</a>
                <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Guardar cambios
                </button>
            </div>
        </div>
    </div>

    <!-- =================== DERECHA: Formulario =================== -->
    <div class="space-y-6 lg:col-span-8">

        <!-- Galería actual (arrastrable) -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 font-serif text-lg text-gray-900">Imágenes del producto</h2>
            <p class="mb-4 flex items-center gap-1.5 text-sm text-gray-500"><?= icon('grip', 'w-4 h-4 text-gray-400') ?> Arrastra para reordenar · pasa el cursor y pulsa la estrella para elegir la principal.</p>

            <div id="gallerySaved" class="mb-3 hidden items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700"><?= icon('check', 'w-3.5 h-3.5') ?> Orden guardado</div>

            <div id="galleryWrap">
                <?php if (!$images): ?>
                    <p class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-4 py-6 text-center text-sm text-gray-400">Aún no hay imágenes. Sube la foto principal o la galería más abajo.</p>
                <?php else: ?>
                    <div id="galleryGrid" data-product="<?= (int) $id ?>" class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        <?php foreach ($images as $img): $isMain = (int) $img['is_main'] === 1; ?>
                            <div data-image-id="<?= (int) $img['id'] ?>" class="group relative overflow-hidden rounded-xl ring-1 transition <?= $isMain ? 'ring-2 ring-brand-red' : 'ring-gray-100' ?>">
                                <img src="<?= e(upload_url($img['image_path'])) ?>" data-img-src="<?= e(upload_url($img['image_path'])) ?>" alt="Imagen del producto" class="pointer-events-none aspect-square w-full object-cover">
                                <span data-drag-handle class="absolute left-1.5 top-1.5 flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-white/85 text-gray-500 opacity-0 shadow-sm backdrop-blur transition group-hover:opacity-100" title="Arrastrar para reordenar"><?= icon('grip', 'w-4 h-4') ?></span>
                                <span data-main-badge class="absolute right-1.5 top-1.5 inline-flex items-center gap-1 rounded-full bg-brand-red px-2 py-0.5 text-[10px] font-semibold text-white shadow <?= $isMain ? '' : 'hidden' ?>"><?= icon('star', 'w-3 h-3') ?> Principal</span>
                                <div class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-gradient-to-t from-black/60 to-transparent p-1.5 opacity-0 transition group-hover:opacity-100">
                                    <button type="button" data-set-main title="Marcar como principal" class="inline-flex items-center justify-center rounded-lg bg-white/90 p-1.5 text-amber-500 transition hover:bg-white <?= $isMain ? 'hidden' : '' ?>"><?= icon('star', 'w-4 h-4') ?></button>
                                    <button type="button" data-del-image title="Eliminar imagen" class="inline-flex items-center justify-center rounded-lg bg-white/90 p-1.5 text-rose-600 transition hover:bg-white"><?= icon('trash', 'w-4 h-4') ?></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reemplazar foto principal -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 font-serif text-lg text-gray-900">Reemplazar foto principal</h2>
            <p class="mb-4 text-sm text-gray-500">Sube una nueva imagen para usarla como principal.</p>
            <label class="dropzone flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/60 px-6 py-8 text-center transition hover:border-brand-red/40">
                <span class="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-red shadow-soft"><?= icon('upload', 'w-6 h-6') ?></span>
                <span class="text-sm font-medium text-gray-700">Haz clic para subir una nueva foto principal</span>
                <span class="mt-1 text-xs text-gray-400">JPG, PNG o WEBP · máx. 5 MB</span>
                <input type="file" name="main_image" accept="image/*" data-image-preview="#imgPrev" class="hidden">
            </label>
        </div>

        <!-- Añadir a la galería -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 font-serif text-lg text-gray-900">Añadir a la galería</h2>
            <p class="mb-4 text-sm text-gray-500">Suma más fotos del producto.</p>
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
                           class="lcn-input <?= isset($errors['name']) ? 'border-rose-300' : '' ?>" data-live-name>
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-rose-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label class="lcn-label" for="sku">SKU / Código</label>
                    <input id="sku" name="sku" type="text" value="<?= e($form['sku']) ?>"
                           class="lcn-input <?= isset($errors['sku']) ? 'border-rose-300' : '' ?>">
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
                    <input id="rental_price" name="rental_price" type="number" step="0.01" min="0" value="<?= e($form['rental_price']) ?>" class="lcn-input" data-live-price>
                </div>

                <div>
                    <label class="lcn-label" for="sale_price">Precio de venta</label>
                    <input id="sale_price" name="sale_price" type="number" step="0.01" min="0" value="<?= e($form['sale_price']) ?>" class="lcn-input">
                </div>

                <div>
                    <label class="lcn-label" for="cost_price">Costo</label>
                    <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="<?= e($form['cost_price']) ?>" class="lcn-input">
                </div>

                <div>
                    <label class="lcn-label" for="deposit_amount">Depósito / Garantía</label>
                    <input id="deposit_amount" name="deposit_amount" type="number" step="0.01" min="0" value="<?= e($form['deposit_amount']) ?>" class="lcn-input">
                </div>

                <div>
                    <label class="lcn-label" for="size">Talla</label>
                    <input id="size" name="size" type="text" value="<?= e($form['size']) ?>" class="lcn-input" data-live-size>
                </div>

                <div>
                    <label class="lcn-label" for="color">Color</label>
                    <input id="color" name="color" type="text" value="<?= e($form['color']) ?>" class="lcn-input" data-live-color>
                </div>

                <div>
                    <label class="lcn-label" for="material">Material</label>
                    <input id="material" name="material" type="text" value="<?= e($form['material']) ?>" class="lcn-input">
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
                    <textarea id="description" name="description" rows="4" class="lcn-input"><?= e($form['description']) ?></textarea>
                </div>

                <div class="sm:col-span-2">
                    <label class="lcn-label" for="internal_notes">Notas internas</label>
                    <textarea id="internal_notes" name="internal_notes" rows="3" class="lcn-input"><?= e($form['internal_notes']) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<?php $lcnCurrency = setting('currency', 'RD$'); ?>
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

<!-- Galería: reordenar (drag & drop) + elegir principal + eliminar, todo vía AJAX -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
  var grid = document.getElementById('galleryGrid');
  if (!grid) return;
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var BASE = window.LCN_BASE || '';
  var PID  = grid.getAttribute('data-product');
  var prev = document.getElementById('imgPrev');
  var placeholder = <?= json_encode(asset('img/placeholder.svg')) ?>;
  var savedMsg = document.getElementById('gallerySaved');

  function postForm(url, params) {
    return fetch(BASE + url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
      body: params.toString()
    }).then(function (r) { return r.json(); });
  }

  // --- Reordenar ---
  if (typeof Sortable !== 'undefined') {
    new Sortable(grid, {
      animation: 160, handle: '[data-drag-handle]', ghostClass: 'opacity-40',
      onEnd: function () {
        var p = new URLSearchParams();
        p.append('product_id', PID);
        grid.querySelectorAll('[data-image-id]').forEach(function (el) { p.append('order[]', el.getAttribute('data-image-id')); });
        postForm('/admin/productos/ordenar-imagenes.php', p).then(function (d) {
          if (d && d.ok && savedMsg) {
            savedMsg.classList.remove('hidden'); savedMsg.classList.add('flex');
            clearTimeout(window.__galTimer);
            window.__galTimer = setTimeout(function () { savedMsg.classList.add('hidden'); savedMsg.classList.remove('flex'); }, 2200);
          }
        });
      }
    });
  }

  function clearMainUI() {
    grid.querySelectorAll('[data-image-id]').forEach(function (t) {
      t.classList.remove('ring-2', 'ring-brand-red'); t.classList.add('ring-gray-100');
      var b = t.querySelector('[data-main-badge]'); if (b) b.classList.add('hidden');
      var s = t.querySelector('[data-set-main]'); if (s) s.classList.remove('hidden');
    });
  }
  function setMainUI(tile) {
    tile.classList.add('ring-2', 'ring-brand-red'); tile.classList.remove('ring-gray-100');
    var b = tile.querySelector('[data-main-badge]'); if (b) b.classList.remove('hidden');
    var s = tile.querySelector('[data-set-main]'); if (s) s.classList.add('hidden');
    var img = tile.querySelector('img'); if (prev && img) prev.src = img.getAttribute('data-img-src') || img.src;
  }

  // --- Clic: elegir principal / eliminar ---
  grid.addEventListener('click', function (e) {
    var tile = e.target.closest('[data-image-id]'); if (!tile) return;
    var id = tile.getAttribute('data-image-id');

    if (e.target.closest('[data-set-main]')) {
      var p = new URLSearchParams(); p.append('image_id', id); p.append('product_id', PID);
      postForm('/admin/productos/set-main-imagen.php', p).then(function (d) {
        if (d && d.ok) { clearMainUI(); setMainUI(tile); }
        else alert((d && d.message) || 'No se pudo marcar como principal.');
      }).catch(function () { alert('Error de conexión.'); });
      return;
    }

    if (e.target.closest('[data-del-image]')) {
      if (!confirm('¿Eliminar esta imagen?')) return;
      var p2 = new URLSearchParams(); p2.append('image_id', id); p2.append('product_id', PID);
      postForm('/admin/productos/eliminar-imagen.php', p2).then(function (d) {
        if (!d || !d.ok) { alert((d && d.message) || 'No se pudo eliminar.'); return; }
        tile.remove();
        if (d.promoted) {
          var nt = grid.querySelector('[data-image-id="' + d.promoted.id + '"]');
          if (nt) setMainUI(nt);
          if (prev) prev.src = d.promoted.src;
        } else if (d.main_cleared && prev) {
          prev.src = placeholder;
        }
        if (!grid.querySelector('[data-image-id]')) {
          document.getElementById('galleryWrap').innerHTML = '<p class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-4 py-6 text-center text-sm text-gray-400">Ya no hay imágenes. Sube nuevas más abajo.</p>';
        }
      }).catch(function () { alert('Error de conexión.'); });
      return;
    }
  });
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
