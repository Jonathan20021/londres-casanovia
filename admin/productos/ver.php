<?php
/**
 * Productos / Inventario — Detalle del producto.
 * LONDRES Casa de Novias
 *
 * admin/productos/ver.php?id=ID  (N=2)
 * Permiso: products.view
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.view');

$id = (int) get_param('id', '0');
$product = $id ? db_one(
    'SELECT p.*, c.name AS category_name, u.name AS created_by_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     LEFT JOIN users u ON u.id = p.created_by
     WHERE p.id = :id',
    ['id' => $id]
) : null;

if (!$product) {
    flash('error', 'El producto solicitado no existe.');
    redirect(admin_url('productos/index.php'));
}

$canManage = user_can('products.manage');

/* Código de barras (se crea al vuelo si el producto es anterior a esta función) */
$productBarcode = (string) ($product['barcode'] ?? '');
if ($productBarcode === '') {
    $productBarcode = barcode_assign($id);
}

/* Imágenes (principal primero) */
$images = db_all('SELECT * FROM product_images WHERE product_id = :id ORDER BY is_main DESC, sort_order ASC, id ASC', ['id' => $id]);
$mainImage = $product['main_image'] ?: ($images[0]['image_path'] ?? null);

/* Historial de alquileres del producto */
$rentals = db_all(
    'SELECT r.id, r.rental_number, r.event_date, r.delivery_date, r.return_date,
            r.total_amount, r.rental_status, r.payment_status, c.full_name AS customer_name
     FROM rentals r
     JOIN rental_items ri ON ri.rental_id = r.id
     JOIN customers c ON c.id = r.customer_id
     WHERE ri.product_id = :id
     ORDER BY r.delivery_date DESC
     LIMIT 25',
    ['id' => $id]
);

/* Conteos para resumen */
$rentalCount = (int) db_value('SELECT COUNT(*) FROM rental_items WHERE product_id = :id', ['id' => $id]);
$saleCount   = (int) db_value('SELECT COUNT(*) FROM sales WHERE product_id = :id', ['id' => $id]);

$typeLabels = ['rental' => 'Alquiler', 'sale' => 'Venta', 'both' => 'Alquiler y venta'];

$page_title    = $product['name'];
$page_subtitle = 'Detalle del producto · ' . ($product['sku'] ? 'SKU ' . $product['sku'] : 'Sin SKU') . ' · Código ' . $productBarcode;
$active        = 'productos';

$header_actions = '<a href="' . admin_url('productos/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';
if ($canManage) {
    $header_actions .= '<a href="' . admin_url('productos/editar.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
        . icon('pencil', 'w-4 h-4') . ' Editar</a>';
}

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

    <!-- =================== Galería =================== -->
    <div class="lg:col-span-5">
        <div class="lg:sticky lg:top-28 space-y-4">
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="aspect-[3/4] w-full bg-gray-100">
                    <img id="mainView" src="<?= e(upload_url($mainImage)) ?>" alt="<?= e($product['name']) ?>" class="h-full w-full object-cover">
                </div>
            </div>

            <!-- Código de barras -->
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-soft">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Código de barras</p>
                    <span class="font-mono text-[11px] tracking-widest text-gray-500">Code 128</span>
                </div>
                <div class="rounded-xl border border-gray-100 px-3 py-3">
                    <?= barcode_svg($productBarcode, ['module' => 1.7, 'height' => 52]) ?>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="<?= admin_url('codigos-barra/exportar.php?ids=' . $id) ?>"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-brand-dark px-3 py-2 text-xs font-semibold text-white transition hover:bg-black">
                        <?= icon('printer', 'w-4 h-4') ?> Imprimir etiqueta
                    </a>
                    <a href="<?= admin_url('codigos-barra/index.php?q=' . urlencode($productBarcode)) ?>"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50">
                        <?= icon('tag', 'w-4 h-4') ?> Módulo
                    </a>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= admin_url('codigos-barra/regenerar.php') ?>" class="inline"
                              onsubmit="return confirm('¿Regenerar el código de barras? Las etiquetas ya impresas dejarán de coincidir.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 transition hover:bg-gray-50">
                                <?= icon('return', 'w-4 h-4') ?> Regenerar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="grid grid-cols-4 gap-3">
                    <?php foreach ($images as $img): ?>
                        <button type="button"
                                onclick="document.getElementById('mainView').src=this.dataset.src"
                                data-src="<?= e(upload_url($img['image_path'])) ?>"
                                class="overflow-hidden rounded-xl ring-1 ring-gray-100 transition hover:ring-brand-red">
                            <img src="<?= e(upload_url($img['image_path'])) ?>" alt="Miniatura" class="aspect-square w-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- =================== Datos =================== -->
    <div class="space-y-6 lg:col-span-7">

        <!-- Cabecera de datos -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-brand-red"><?= e($product['category_name'] ?? 'General') ?></p>
                    <h2 class="mt-1 font-serif text-2xl text-gray-900"><?= e($product['name']) ?></h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?= status_badge($product['commercial_status'], 'commercial') ?>
                    <?= status_badge($product['condition_status'], 'condition') ?>
                    <?= status_badge($product['status'], 'user') ?>
                    <?php if (!empty($product['featured'])): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-brand-gold ring-1 ring-inset ring-amber-600/20"><?= icon('sparkles', 'w-3.5 h-3.5') ?> Destacado</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Precios -->
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50/70 p-3">
                    <p class="text-xs text-gray-400">Alquiler</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900"><?= e(money($product['rental_price'])) ?></p>
                </div>
                <div class="rounded-xl bg-gray-50/70 p-3">
                    <p class="text-xs text-gray-400">Venta</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900"><?= !empty($product['sale_price']) ? e(money($product['sale_price'])) : '—' ?></p>
                </div>
                <div class="rounded-xl bg-gray-50/70 p-3">
                    <p class="text-xs text-gray-400">Depósito</p>
                    <p class="mt-0.5 text-lg font-semibold text-gray-900"><?= e(money($product['deposit_amount'])) ?></p>
                </div>
                <?php if ($canManage): ?>
                    <div class="rounded-xl bg-gray-50/70 p-3">
                        <p class="text-xs text-gray-400">Costo</p>
                        <p class="mt-0.5 text-lg font-semibold text-gray-900"><?= !empty($product['cost_price']) ? e(money($product['cost_price'])) : '—' ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['description'])): ?>
                <div class="mt-5 border-t border-gray-50 pt-5">
                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Descripción</h3>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600"><?= e($product['description']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Especificaciones -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h3 class="mb-4 font-serif text-lg text-gray-900">Especificaciones</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Tipo</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e($typeLabels[$product['type']] ?? $product['type']) ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">SKU</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e($product['sku'] ?: '—') ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Talla</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e($product['size'] ?: '—') ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Color</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e($product['color'] ?: '—') ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Material</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e($product['material'] ?: '—') ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Pieza única</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= $product['is_unique'] ? 'Sí' : 'No' ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Cantidad en stock</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= (int) $product['quantity'] ?></dd>
                </div>
                <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                    <dt class="text-sm text-gray-500">Creado</dt>
                    <dd class="text-sm font-medium text-gray-800"><?= e(format_date($product['created_at'])) ?></dd>
                </div>
                <?php if (!empty($product['created_by_name'])): ?>
                    <div class="flex items-center justify-between border-b border-gray-50 pb-2">
                        <dt class="text-sm text-gray-500">Creado por</dt>
                        <dd class="text-sm font-medium text-gray-800"><?= e($product['created_by_name']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Notas internas (solo gestores) -->
        <?php if ($canManage && !empty($product['internal_notes'])): ?>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-6 shadow-soft">
                <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800"><?= icon('warning', 'w-4 h-4') ?> Notas internas</h3>
                <p class="whitespace-pre-line text-sm leading-relaxed text-amber-900/80"><?= e($product['internal_notes']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Historial de alquileres -->
        <div class="rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="flex items-center justify-between px-6 py-4">
                <h3 class="font-serif text-lg text-gray-900">Historial de alquileres</h3>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"><?= $rentalCount ?> alquiler<?= $rentalCount === 1 ? '' : 'es' ?> · <?= $saleCount ?> venta<?= $saleCount === 1 ? '' : 's' ?></span>
            </div>

            <?php if (!$rentals): ?>
                <div class="px-6 pb-6">
                    <?= empty_state('Sin alquileres registrados', 'Este producto aún no tiene alquileres asociados.', 'box') ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto border-t border-gray-100">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nº</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Entrega → Devolución</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($rentals as $r): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 text-gray-700">
                                        <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $r['id']) ?>" class="font-medium text-brand-red hover:underline"><?= e($r['rental_number']) ?></a>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($r['customer_name']) ?></td>
                                    <td class="px-5 py-4 text-gray-600"><?= e(format_date($r['delivery_date'])) ?> → <?= e(format_date($r['return_date'])) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(money($r['total_amount'])) ?></td>
                                    <td class="px-5 py-4"><?= status_badge($r['rental_status'], 'rental') ?></td>
                                    <td class="px-5 py-4"><?= status_badge($r['payment_status'], 'payment') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acciones -->
        <?php if ($canManage): ?>
            <div class="flex flex-wrap items-center gap-3">
                <a href="<?= admin_url('productos/editar.php?id=' . $id) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('pencil', 'w-4 h-4') ?> Editar producto
                </a>
                <form method="post" action="<?= admin_url('productos/eliminar.php') ?>" data-confirm="¿Eliminar este producto? Si tiene alquileres o ventas asociados se desactivará en su lugar." class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">
                        <?= icon('trash', 'w-4 h-4') ?> Eliminar
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
