<?php
/**
 * Alquileres · Crear
 * LONDRES Casa de Novias
 *
 * Crea un alquiler tras RE-VALIDAR la disponibilidad de todos los productos en el
 * servidor. Si llega ?request=ID precarga datos de una solicitud pública.
 * Al guardar: genera número, calcula 50/50, reserva las piezas y crea la
 * factura asociada.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$user = current_user();

/* ------------------------------------------------------------------ *
 *  Catálogos para el formulario
 * ------------------------------------------------------------------ */
$customers = db_all("SELECT id, full_name, phone FROM customers ORDER BY full_name ASC");

// Productos elegibles para alquiler (rental o both) y activos
$products = db_all(
    "SELECT p.id, p.name, p.sku, p.barcode, p.rental_price, p.deposit_amount,
            p.commercial_status, p.type, p.size, p.color, p.material, p.main_image,
            c.name AS category_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.status = 'active' AND p.type IN ('rental','both')
      ORDER BY p.name ASC"
);

foreach ($products as &$catalogProduct) {
    $catalogProduct['image_url'] = upload_url($catalogProduct['main_image'] ?? null);
}
unset($catalogProduct);

/* ------------------------------------------------------------------ *
 *  Precarga desde una solicitud (?request=ID)
 * ------------------------------------------------------------------ */
$requestId = (int) get_param('request', '0');
$prefill = [
    'customer_id'   => '',
    'product_id'    => (int) get_param('product', '0') ?: '',
    'product_ids'   => (int) get_param('product', '0') > 0 ? [(int) get_param('product', '0')] : [],
    'event_date'    => '',
    'delivery_date' => '',
    'return_date'   => '',
    'rental_price'  => '',
    'discount'      => '0',
    'late_penalty'  => '0',
    'rental_status' => 'reserved',
];
$request = null;
if ($requestId > 0) {
    $request = db_one('SELECT * FROM rental_requests WHERE id = :id', ['id' => $requestId]);
    if ($request) {
        $prefill['product_id']    = $request['product_id'] ?: $prefill['product_id'];
        $prefill['product_ids']   = $request['product_id'] ? [(int) $request['product_id']] : $prefill['product_ids'];
        $prefill['customer_id']   = $request['customer_id'] ?: '';
        $prefill['event_date']    = $request['event_date'] ?: '';
        $prefill['delivery_date'] = $request['delivery_date'] ?: '';
        $prefill['return_date']   = $request['return_date'] ?: '';
        if ($request['product_id']) {
            $rp = db_value('SELECT rental_price FROM products WHERE id = :id', ['id' => $request['product_id']]);
            if ($rp !== null) $prefill['rental_price'] = $rp;
        }
    }
}

$errors    = [];        // errores de validación a mostrar
$conflict  = null;      // conflicto de disponibilidad (banner rojo)
$form      = $prefill;  // valores a re-pintar

/* ------------------------------------------------------------------ *
 *  Manejo del POST
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    // Recoger y normalizar entrada
    $form = [
        'customer_id'   => (int) post('customer_id', 0),
        'product_ids'   => array_values(array_unique(array_filter(
            array_map('intval', (array) post('product_ids', [])),
            static fn(int $id): bool => $id > 0
        ))),
        'event_date'    => trim((string) post('event_date', '')),
        'delivery_date' => trim((string) post('delivery_date', '')),
        'return_date'   => trim((string) post('return_date', '')),
        'rental_price'  => (float) post('rental_price', 0),
        'discount'      => (float) post('discount', 0),
        'late_penalty'  => (float) post('late_penalty', 0),
        'rental_status' => (string) post('rental_status', 'reserved'),
    ];
    $form['product_id'] = $form['product_ids'][0] ?? 0;
    $requestId = (int) post('request_id', $requestId);

    // --- Validaciones básicas ---
    if ($form['customer_id'] <= 0) $errors[] = 'Seleccione un cliente.';
    if (!$form['product_ids'])     $errors[] = 'Escanee o agregue al menos un producto.';
    if ($form['delivery_date'] === '') $errors[] = 'Indique la fecha de entrega.';
    if ($form['return_date'] === '')   $errors[] = 'Indique la fecha de devolución.';
    if ($form['delivery_date'] !== '' && $form['return_date'] !== ''
        && strtotime($form['return_date']) < strtotime($form['delivery_date'])) {
        $errors[] = 'La fecha de devolución no puede ser anterior a la de entrega.';
    }
    if ($form['discount'] < 0 || $form['late_penalty'] < 0) $errors[] = 'Los montos no pueden ser negativos.';

    // Estados de creación permitidos
    if (!in_array($form['rental_status'], ['reserved', 'confirmed'], true)) {
        $form['rental_status'] = 'reserved';
    }

    // El cliente y el producto deben existir
    if ($form['customer_id'] > 0 && !db_one('SELECT id FROM customers WHERE id = :id', ['id' => $form['customer_id']])) {
        $errors[] = 'El cliente seleccionado no existe.';
    }
    $selectedProducts = [];
    if ($form['product_ids']) {
        $marks = implode(',', array_fill(0, count($form['product_ids']), '?'));
        $selectedProducts = db_all(
            "SELECT * FROM products WHERE id IN ($marks) AND status = 'active' AND type IN ('rental','both')",
            $form['product_ids']
        );
        $selectedById = [];
        foreach ($selectedProducts as $selectedProduct) {
            $selectedById[(int) $selectedProduct['id']] = $selectedProduct;
        }
        if (count($selectedById) !== count($form['product_ids'])) {
            $errors[] = 'Uno o más productos seleccionados no son válidos para alquiler.';
        } else {
            $selectedProducts = array_map(
                static fn(int $productId): array => $selectedById[$productId],
                $form['product_ids']
            );
            $form['rental_price'] = array_sum(array_map(
                static fn(array $item): float => (float) $item['rental_price'],
                $selectedProducts
            ));
            if ($form['rental_price'] <= 0) {
                $errors[] = 'El precio total de los productos debe ser mayor que cero.';
            }
        }
    }

    // --- RE-VALIDACIÓN de disponibilidad en el servidor ---
    if (!$errors) {
        foreach ($selectedProducts as $selectedProduct) {
            $check = checkProductAvailability(
                (int) $selectedProduct['id'],
                $form['delivery_date'],
                $form['return_date']
            );
            if (!empty($check['error'])) {
                $errors[] = $check['error'];
                break;
            }
            if (!$check['available']) {
                $conflict = $check['conflict'];
                flash('error', 'El producto ' . $selectedProduct['name'] . ' no está disponible para las fechas seleccionadas.');
                break;
            }
        }
    }

    // --- Si todo está bien, persistir ---
    if (!$errors && !$conflict) {
        $total = round($form['rental_price'] - $form['discount'] + $form['late_penalty'], 2);
        if ($total < 0) $total = 0.0;
        $calc = calculateRentalPayments($total);

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $rentalNumber = generate_number('rentals', 'rental_number', 'ALQ');

            $rentalId = db_insert('rentals', [
                'rental_number'            => $rentalNumber,
                'customer_id'              => $form['customer_id'],
                // Compatibilidad con reportes y pantallas anteriores: la primera pieza es la principal.
                'product_id'               => $form['product_ids'][0],
                'request_id'               => $requestId > 0 ? $requestId : null,
                'event_date'               => $form['event_date'] !== '' ? $form['event_date'] : null,
                'delivery_date'            => $form['delivery_date'],
                'return_date'              => $form['return_date'],
                'rental_price'             => $form['rental_price'],
                'discount'                 => $form['discount'],
                'late_penalty'             => $form['late_penalty'],
                'total_amount'             => $total,
                'initial_payment_required' => $calc['initial'],
                'initial_payment_paid'     => 0,
                'remaining_balance'        => $total,
                'payment_status'           => 'pending',
                'rental_status'            => $form['rental_status'],
                'created_by'               => $user['id'],
            ]);

            $productNames = [];
            foreach ($selectedProducts as $position => $selectedProduct) {
                db_insert('rental_items', [
                    'rental_id'  => $rentalId,
                    'product_id' => (int) $selectedProduct['id'],
                    'unit_price' => (float) $selectedProduct['rental_price'],
                    'sort_order' => $position,
                ]);
                $productNames[] = $selectedProduct['name'];
            }
            sync_rental_products_status($rentalId, 'reserved');

            // Factura de alquiler asociada; las líneas se leen desde rental_items.
            $invoiceNumber = generate_number('invoices', 'invoice_number', (string) setting('invoice_prefix', 'FAC'));
            db_insert('invoices', [
                'invoice_number' => $invoiceNumber,
                'customer_id'    => $form['customer_id'],
                'rental_id'      => $rentalId,
                'sale_id'        => null,
                'invoice_type'   => 'rental',
                'concept'        => 'Alquiler ' . $rentalNumber . ' · ' . count($productNames) . (count($productNames) === 1 ? ' producto' : ' productos'),
                'subtotal'       => $form['rental_price'],
                'discount'       => $form['discount'],
                'tax'            => 0,
                'total'          => $total,
                'paid_amount'    => 0,
                'balance'        => $total,
                'status'         => 'pending',
                'issued_at'      => date('Y-m-d'),
                'created_by'     => $user['id'],
            ]);

            if ($requestId > 0 && $request) {
                db_update('rental_requests', ['status' => 'converted'], 'id = :id', ['id' => $requestId]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }

        log_activity('rental.create', 'rental', $rentalId,
            'Alquiler ' . $rentalNumber . ' creado con ' . count($selectedProducts) . ' producto(s) por ' . $total);

        flash('success', 'Alquiler ' . $rentalNumber . ' creado correctamente con ' . count($selectedProducts) . ' producto(s).');
        redirect(admin_url('alquileres/ver.php?id=' . $rentalId));
    }
    // Si hubo errores o conflicto, se re-pinta el formulario más abajo con $errors / $conflict.
}

$page_title    = 'Nuevo alquiler';
$page_subtitle = 'Registra una reserva validando la disponibilidad de todas las piezas';
$active        = 'alquileres';
$header_actions = '<a href="' . admin_url('alquileres/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if ($errors): ?>
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
        <div class="flex items-center gap-2 font-semibold"><?= icon('warning', 'w-5 h-5') ?> Revise lo siguiente:</div>
        <ul class="mt-2 list-inside list-disc space-y-0.5">
            <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($conflict): ?>
    <!-- Banner de conflicto de disponibilidad (admin: sí muestra el cliente) -->
    <div class="mb-5 overflow-hidden rounded-2xl border border-rose-200 bg-rose-50 shadow-soft">
        <div class="flex items-center gap-2 border-b border-rose-200/70 bg-rose-100/50 px-4 py-3 text-sm font-semibold text-rose-800">
            <?= icon('warning', 'w-5 h-5') ?> Producto no disponible para esas fechas
        </div>
        <div class="grid grid-cols-1 gap-3 px-4 py-4 text-sm text-rose-900 sm:grid-cols-2 lg:grid-cols-4">
            <div><p class="text-xs uppercase tracking-wide text-rose-500">Producto</p><p class="font-medium"><?= e($conflict['product_name']) ?></p></div>
            <div><p class="text-xs uppercase tracking-wide text-rose-500">Alquiler en conflicto</p><p class="font-medium"><?= e($conflict['rental_number']) ?> · <?= e($conflict['customer_name']) ?></p></div>
            <div><p class="text-xs uppercase tracking-wide text-rose-500">Fechas ocupadas</p><p class="font-medium"><?= format_date($conflict['delivery_date']) ?> → <?= format_date($conflict['return_date']) ?></p></div>
            <div><p class="text-xs uppercase tracking-wide text-rose-500">Estado</p><p><?= status_badge($conflict['rental_status'], 'rental') ?></p></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($request): ?>
    <div class="mb-5 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 shadow-soft">
        <span class="mt-0.5 text-sky-500"><?= icon('inbox', 'w-5 h-5') ?></span>
        <div>Convirtiendo la solicitud de <strong><?= e($request['full_name'] ?? 'cliente') ?></strong>. Verifica los datos y selecciona el cliente registrado antes de guardar.</div>
    </div>
<?php endif; ?>

<form method="post" action="" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>
    <input type="hidden" name="request_id" value="<?= (int) $requestId ?>">

    <!-- Columna principal -->
    <div class="space-y-6 lg:col-span-2">
        <!-- Cliente y productos -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-serif text-lg font-bold text-gray-900">Cliente y productos</h2>
                    <p class="mt-1 text-xs text-gray-400">Escanee cada etiqueta o agregue las piezas manualmente.</p>
                </div>
                <span id="productCount" class="rounded-full bg-brand-cream px-3 py-1 text-xs font-semibold text-brand-red">0 productos</span>
            </div>
            <div class="mt-4">
                <div>
                    <label class="lcn-label" for="customer_id">Cliente</label>
                    <select id="customer_id" name="customer_id" class="lcn-input" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $form['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['full_name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= admin_url('clientes/crear.php') ?>" target="_blank" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-brand-red hover:underline">
                        <?= icon('plus', 'w-3.5 h-3.5') ?> Crear nuevo cliente
                    </a>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-brand-red/15 bg-red-50/40 p-4">
                <label class="lcn-label" for="barcodeScanner">Lector de código</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-red"><?= icon('tag', 'w-5 h-5') ?></span>
                        <input id="barcodeScanner" type="text" autocomplete="off" inputmode="none"
                               class="lcn-input pl-11 font-mono uppercase tracking-wider"
                               placeholder="Escanee el código del producto">
                    </div>
                    <button type="button" id="scanAddButton" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-dark px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
                        <?= icon('plus', 'w-4 h-4') ?> Agregar
                    </button>
                </div>
                <p id="scanFeedback" class="mt-2 text-xs text-gray-500">El lector puede enviar Enter o Tab. El campo quedará listo para la siguiente pieza.</p>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="lcn-label" for="productPicker">Agregar manualmente</label>
                    <select id="productPicker" class="lcn-input">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= e($p['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?> · <?= e(money($p['rental_price'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="pickerAddButton" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    <?= icon('plus', 'w-4 h-4') ?> Agregar producto
                </button>
            </div>

            <div id="selectedProducts" class="mt-5 space-y-3"></div>
            <div id="selectedProductsEmpty" class="mt-5 rounded-2xl border border-dashed border-gray-200 px-4 py-8 text-center">
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-gray-400"><?= icon('tag', 'w-5 h-5') ?></span>
                <p class="mt-2 text-sm font-medium text-gray-600">Aún no hay productos</p>
                <p class="mt-1 text-xs text-gray-400">Escanee una etiqueta o use el selector manual.</p>
            </div>
            <div id="productHiddenInputs"></div>
        </div>

        <!-- Fechas -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Fechas del alquiler</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lcn-label" for="event_date">Fecha del evento</label>
                    <input type="date" id="event_date" name="event_date" value="<?= e($form['event_date']) ?>" class="lcn-input">
                </div>
                <div>
                    <label class="lcn-label" for="delivery_date">Entrega</label>
                    <input type="date" id="delivery_date" name="delivery_date" value="<?= e($form['delivery_date']) ?>" class="lcn-input" required>
                </div>
                <div>
                    <label class="lcn-label" for="return_date">Devolución</label>
                    <input type="date" id="return_date" name="return_date" value="<?= e($form['return_date']) ?>" class="lcn-input" required>
                </div>
            </div>

            <!-- Verificador de disponibilidad -->
            <div class="mt-4 rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">Confirma que todas las piezas estén libres antes de guardar.</p>
                    <button type="button" id="checkAllAvailability"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('check', 'w-4 h-4') ?> Verificar todas
                    </button>
                </div>
                <div id="boxDisp" class="mt-3"></div>
            </div>
        </div>

        <!-- Precios -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Precios</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lcn-label" for="rental_price">Subtotal de productos</label>
                    <input type="number" step="0.01" min="0" id="rental_price" name="rental_price"
                           value="<?= e((string) $form['rental_price']) ?>" class="lcn-input bg-gray-50" readonly required>
                </div>
                <div>
                    <label class="lcn-label" for="discount">Descuento</label>
                    <input type="number" step="0.01" min="0" id="discount" name="discount"
                           value="<?= e((string) $form['discount']) ?>" class="lcn-input">
                </div>
                <div>
                    <label class="lcn-label" for="late_penalty">Penalidad por mora</label>
                    <input type="number" step="0.01" min="0" id="late_penalty" name="late_penalty"
                           value="<?= e((string) $form['late_penalty']) ?>" class="lcn-input">
                </div>
            </div>
        </div>
    </div>

    <!-- Columna lateral: resumen y estado -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Resumen de pago</h2>
            <p class="mt-1 text-xs text-gray-400">El total se calcula en vivo y el abono inicial es del <?= e((string) setting('initial_payment_percentage', 50)) ?>%.</p>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="lcn-label" for="totalCalc">Total estimado</label>
                    <!-- input gobernado por JS data-payment-total (lo recalculamos abajo) -->
                    <input type="text" id="totalCalc" class="lcn-input bg-gray-50 font-semibold" readonly
                           data-payment-total data-payment-percentage="<?= e((string) setting('initial_payment_percentage', 50)) ?>"
                           data-payment-initial="#calcInitial" data-payment-remaining="#calcRemaining"
                           value="0.00">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lcn-label" for="calcInitial">Abono inicial</label>
                        <input type="text" id="calcInitial" class="lcn-input bg-gray-50" readonly value="0.00">
                    </div>
                    <div>
                        <label class="lcn-label" for="calcRemaining">Saldo restante</label>
                        <input type="text" id="calcRemaining" class="lcn-input bg-gray-50" readonly value="0.00">
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Estado inicial</h2>
            <div class="mt-4">
                <label class="lcn-label" for="rental_status">Estado del alquiler</label>
                <select id="rental_status" name="rental_status" class="lcn-input">
                    <option value="reserved"  <?= $form['rental_status'] === 'reserved' ? 'selected' : '' ?>>Reservado</option>
                    <option value="confirmed" <?= $form['rental_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmado</option>
                </select>
                <p class="mt-1.5 text-xs text-gray-400">Ambos estados reservan el producto y bloquean la disponibilidad.</p>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Crear alquiler
            </button>
            <a href="<?= admin_url('alquileres/index.php') ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </div>
</form>

<script>
(function () {
    var catalog = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var initialIds = <?= json_encode(array_map('intval', (array) ($form['product_ids'] ?? []))) ?>;
    var currency = <?= json_encode((string) setting('currency', 'RD$'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var byId = {};
    var byCode = {};
    var selected = [];

    catalog.forEach(function (product) {
        product.id = parseInt(product.id, 10);
        byId[product.id] = product;
        [product.barcode, product.sku].forEach(function (code) {
            code = normalizeCode(code || '');
            if (code) byCode[code] = product;
        });
    });

    var scanner = document.getElementById('barcodeScanner');
    var feedback = document.getElementById('scanFeedback');
    var picker = document.getElementById('productPicker');
    var list = document.getElementById('selectedProducts');
    var empty = document.getElementById('selectedProductsEmpty');
    var hidden = document.getElementById('productHiddenInputs');
    var count = document.getElementById('productCount');
    var price  = document.getElementById('rental_price');
    var disc   = document.getElementById('discount');
    var pen    = document.getElementById('late_penalty');
    var total  = document.getElementById('totalCalc');
    var form = scanner.closest('form');

    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function normalizeCode(value) { return String(value).trim().replace(/\s+/g, '').toUpperCase(); }
    function money(value) { return currency + ' ' + num(value).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function setFeedback(message, kind) {
        feedback.textContent = message;
        feedback.className = 'mt-2 text-xs ' + (kind === 'error' ? 'text-rose-600' : kind === 'ok' ? 'text-emerald-600' : 'text-gray-500');
    }

    function recalcTotal() {
        var subtotal = selected.reduce(function (sum, product) { return sum + num(product.rental_price); }, 0);
        price.value = subtotal.toFixed(2);
        var t = num(price.value) - num(disc.value) + num(pen.value);
        if (t < 0) t = 0;
        total.value = t.toFixed(2);
        total.dispatchEvent(new Event('input'));
    }

    function render() {
        list.innerHTML = '';
        hidden.innerHTML = '';
        selected.forEach(function (product) {
            var card = document.createElement('div');
            card.className = 'flex gap-3 rounded-2xl border border-gray-100 bg-gray-50/50 p-3';

            var image = document.createElement('img');
            image.src = product.image_url;
            image.alt = product.name;
            image.className = 'h-24 w-20 flex-none rounded-xl object-cover bg-white ring-1 ring-gray-100';
            card.appendChild(image);

            var body = document.createElement('div');
            body.className = 'min-w-0 flex-1';
            var top = document.createElement('div');
            top.className = 'flex items-start justify-between gap-2';
            var titleBox = document.createElement('div');
            titleBox.className = 'min-w-0';
            var title = document.createElement('p');
            title.className = 'truncate font-semibold text-gray-900';
            title.textContent = product.name;
            var meta = document.createElement('p');
            meta.className = 'mt-0.5 text-xs text-gray-500';
            meta.textContent = [product.category_name, product.size ? 'Talla ' + product.size : '', product.color].filter(Boolean).join(' · ') || 'Sin detalles adicionales';
            titleBox.appendChild(title);
            titleBox.appendChild(meta);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rounded-lg p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600';
            remove.setAttribute('aria-label', 'Quitar ' + product.name);
            remove.title = 'Quitar producto';
            remove.innerHTML = '×';
            remove.addEventListener('click', function () {
                selected = selected.filter(function (item) { return item.id !== product.id; });
                render();
                scanner.focus();
            });
            top.appendChild(titleBox);
            top.appendChild(remove);
            body.appendChild(top);

            var bottom = document.createElement('div');
            bottom.className = 'mt-3 flex flex-wrap items-center justify-between gap-2';
            var code = document.createElement('span');
            code.className = 'rounded-lg bg-white px-2 py-1 font-mono text-[11px] tracking-wider text-gray-500 ring-1 ring-gray-100';
            code.textContent = product.barcode || product.sku || 'Sin código';
            var itemPrice = document.createElement('span');
            itemPrice.className = 'text-sm font-semibold text-brand-red';
            itemPrice.textContent = money(product.rental_price);
            bottom.appendChild(code);
            bottom.appendChild(itemPrice);
            body.appendChild(bottom);
            card.appendChild(body);
            list.appendChild(card);

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = product.id;
            hidden.appendChild(input);
        });

        empty.classList.toggle('hidden', selected.length > 0);
        count.textContent = selected.length + (selected.length === 1 ? ' producto' : ' productos');
        recalcTotal();
    }

    function addProduct(product, source) {
        if (!product) {
            setFeedback('No se encontró un producto de alquiler con ese código.', 'error');
            scanner.select();
            return;
        }
        if (selected.some(function (item) { return item.id === product.id; })) {
            setFeedback(product.name + ' ya está agregado.', 'error');
            scanner.value = '';
            scanner.focus();
            return;
        }
        selected.push(product);
        render();
        setFeedback('Agregado: ' + product.name + '. Listo para escanear la siguiente pieza.', 'ok');
        scanner.value = '';
        picker.value = '';
        if (source !== 'initial') scanner.focus();
    }

    function addScanned() {
        if (scanTimer) {
            clearTimeout(scanTimer);
            scanTimer = null;
        }
        var code = normalizeCode(scanner.value);
        if (!code) {
            setFeedback('Escanee o escriba un código.', 'error');
            scanner.focus();
            return;
        }
        addProduct(byCode[code], 'scanner');
    }

    document.getElementById('scanAddButton').addEventListener('click', addScanned);
    document.getElementById('pickerAddButton').addEventListener('click', function () {
        addProduct(byId[parseInt(picker.value, 10)], 'picker');
    });
    scanner.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            addScanned();
        }
    });

    // Algunos lectores no envían Enter: una ráfaga rápida se confirma tras 180 ms.
    var previousKeyAt = 0;
    var fastKeys = 0;
    var scanTimer = null;
    scanner.addEventListener('input', function () {
        var now = Date.now();
        fastKeys = previousKeyAt && now - previousKeyAt < 55 ? fastKeys + 1 : 0;
        previousKeyAt = now;
        clearTimeout(scanTimer);
        if (fastKeys >= 3 && scanner.value.trim().length >= 3) {
            scanTimer = setTimeout(addScanned, 180);
        }
    });

    document.getElementById('checkAllAvailability').addEventListener('click', function () {
        var delivery = document.getElementById('delivery_date').value;
        var returnDate = document.getElementById('return_date').value;
        var box = document.getElementById('boxDisp');
        if (!selected.length || !delivery || !returnDate) {
            box.textContent = 'Agregue productos e indique las fechas de entrega y devolución.';
            box.className = 'mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700';
            return;
        }
        box.textContent = 'Verificando ' + selected.length + (selected.length === 1 ? ' producto…' : ' productos…');
        box.className = 'mt-3 text-sm text-gray-500';
        Promise.all(selected.map(function (product) {
            var url = window.LCN_BASE + '/public/api/check-availability.php?product_id=' + encodeURIComponent(product.id)
                + '&delivery=' + encodeURIComponent(delivery) + '&return=' + encodeURIComponent(returnDate);
            return fetch(url).then(function (response) { return response.json(); }).then(function (result) {
                return {product: product, result: result};
            });
        })).then(function (checks) {
            var failed = checks.find(function (check) { return !check.result.available; });
            if (failed) {
                box.textContent = failed.product.name + ': ' + (failed.result.error || 'no está disponible para esas fechas.');
                box.className = 'mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700';
            } else {
                box.textContent = '✓ Todas las piezas están disponibles para las fechas seleccionadas.';
                box.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700';
            }
        }).catch(function () {
            box.textContent = 'No se pudo verificar la disponibilidad. Intente nuevamente.';
            box.className = 'mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700';
        });
    });

    [disc, pen].forEach(function (el) { el.addEventListener('input', recalcTotal); });
    form.addEventListener('submit', function (event) {
        if (!selected.length) {
            event.preventDefault();
            setFeedback('Debe agregar al menos un producto antes de crear el alquiler.', 'error');
            scanner.focus();
        }
    });

    initialIds.forEach(function (id) { addProduct(byId[id], 'initial'); });
    render();
    scanner.focus();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
