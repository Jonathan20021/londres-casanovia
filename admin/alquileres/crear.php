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
            p.commercial_status, p.type, p.is_complement, p.size, p.color, p.material, p.main_image,
            c.name AS category_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.status = 'active' AND p.type IN ('rental','both')
      ORDER BY p.name ASC"
);

// Códigos de las UNIDADES físicas (…U03): son los que van cosidos a la prenda,
// así el lector encuentra la pieza aunque la etiqueta no lleve el código maestro.
$unitsByProduct = barcode_units_by_product(array_column($products, 'id'));
foreach ($products as &$catalogProduct) {
    $catalogProduct['image_url'] = upload_url($catalogProduct['main_image'] ?? null);
    $catalogProduct['units']     = $unitsByProduct[(int) $catalogProduct['id']] ?? [];
}
unset($catalogProduct);

/* ------------------------------------------------------------------ *
 *  Precarga desde una solicitud (?request=ID)
 * ------------------------------------------------------------------ */
$requestId = (int) get_param('request', '0');
$prefill = [
    'customer_id'      => '',
    'product_id'       => (int) get_param('product', '0') ?: '',
    'product_ids'      => (int) get_param('product', '0') > 0 ? [(int) get_param('product', '0')] : [],
    'item_prices'      => [],
    'alterations'      => [],
    'alteration_notes' => [],
    'event_date'       => '',
    'delivery_date'    => '',
    'delivery_time'    => '',
    'return_date'      => '',
    'rental_price'     => '',
    'discount_percent' => '0',
    'initial_payment'  => '',
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
        'item_prices'      => (array) post('item_prices', []),
        'alterations'      => (array) post('alterations', []),
        'alteration_notes' => (array) post('alteration_notes', []),
        'event_date'       => trim((string) post('event_date', '')),
        'delivery_date'    => trim((string) post('delivery_date', '')),
        'delivery_time'    => trim((string) post('delivery_time', '')),
        'return_date'      => trim((string) post('return_date', '')),
        'rental_price'     => 0.0,   // se recalcula desde las líneas
        'discount_percent' => (float) post('discount_percent', 0),
        'initial_payment'  => trim((string) post('initial_payment', '')),
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
    if ($form['discount_percent'] < 0 || $form['discount_percent'] > 100) {
        $errors[] = 'El descuento debe estar entre 0% y 100%.';
        $form['discount_percent'] = max(0, min(100, $form['discount_percent']));
    }
    if ($form['delivery_time'] !== '' && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $form['delivery_time'])) {
        $errors[] = 'La hora de entrega no es válida.';
        $form['delivery_time'] = '';
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

            // Los complementos (corbata, corona, velo…) llevan el precio que se
            // teclea al facturar; el resto conserva el precio del catálogo.
            $itemPrices = [];
            foreach ($selectedProducts as $selectedProduct) {
                $productId = (int) $selectedProduct['id'];
                if (!empty($selectedProduct['is_complement'])) {
                    $typed = $form['item_prices'][$productId] ?? null;
                    $price = ($typed === null || trim((string) $typed) === '')
                        ? (float) $selectedProduct['rental_price']
                        : (float) $typed;
                } else {
                    $price = (float) $selectedProduct['rental_price'];
                }
                if ($price < 0) {
                    $errors[] = 'El precio de ' . $selectedProduct['name'] . ' no puede ser negativo.';
                    $price = 0.0;
                }
                $itemPrices[$productId] = round($price, 2);
            }
            $form['item_prices']  = $itemPrices;
            $form['rental_price'] = round(array_sum($itemPrices), 2);
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

    // --- Cálculo de importes: descuento en % y abono inicial escrito a mano ---
    $discountAmount = round($form['rental_price'] * ($form['discount_percent'] / 100), 2);
    $total = round($form['rental_price'] - $discountAmount, 2);
    if ($total < 0) $total = 0.0;

    // Abono inicial: lo que escriba el usuario; si lo deja vacío, el % por defecto.
    $calc = calculateRentalPayments($total);
    if ($form['initial_payment'] === '') {
        $initialPayment = $calc['initial'];
    } else {
        $initialPayment = round((float) $form['initial_payment'], 2);
        if ($initialPayment < 0) {
            $errors[] = 'El abono inicial no puede ser negativo.';
            $initialPayment = 0.0;
        } elseif ($initialPayment > $total) {
            $errors[] = 'El abono inicial no puede ser mayor que el total (' . money($total) . ').';
            $initialPayment = $total;
        }
    }

    // --- Si todo está bien, persistir ---
    if (!$errors && !$conflict) {
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
                'delivery_time'            => $form['delivery_time'] !== '' ? $form['delivery_time'] : null,
                'return_date'              => $form['return_date'],
                'rental_price'             => $form['rental_price'],
                'discount'                 => $discountAmount,
                'discount_percent'         => $form['discount_percent'],
                // La mora se calcula sola al vencer la devolución (monto fijo por día laborable).
                'late_penalty'             => 0,
                'total_amount'             => $total,
                'initial_payment_required' => $initialPayment,
                'initial_payment_paid'     => 0,
                'remaining_balance'        => $total,
                'payment_status'           => 'pending',
                // Un alquiler se registra ya confirmado: no se aparta nada sin abono.
                'rental_status'            => 'confirmed',
                'created_by'               => $user['id'],
            ]);

            $productNames = [];
            $alterationCount = 0;
            foreach ($selectedProducts as $position => $selectedProduct) {
                $productId = (int) $selectedProduct['id'];
                // Pieza marcada para MODIFICAR (ruedo, cintura…) + nota de taller
                $needsAlteration = !empty($form['alterations'][$productId]);
                $alterationNote  = trim((string) ($form['alteration_notes'][$productId] ?? ''));
                if ($needsAlteration) $alterationCount++;

                db_insert('rental_items', [
                    'rental_id'        => $rentalId,
                    'product_id'       => $productId,
                    'unit_price'       => $form['item_prices'][$productId] ?? (float) $selectedProduct['rental_price'],
                    'needs_alteration' => $needsAlteration ? 1 : 0,
                    'alteration_notes' => ($needsAlteration && $alterationNote !== '') ? $alterationNote : null,
                    'sort_order'       => $position,
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
                'discount'       => $discountAmount,
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

        flash('success', 'Alquiler ' . $rentalNumber . ' creado correctamente con ' . count($selectedProducts) . ' producto(s).'
            . ($alterationCount > 0
                ? ' ' . $alterationCount . ' pieza(s) quedaron pendientes de modificar en el tablero.'
                : ''));
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
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                    <label class="lcn-label !mb-0" for="barcodeScanner">Lector de código</label>
                    <div class="flex items-center gap-3">
                        <span id="scanStatus" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Modo escaneo activo
                        </span>
                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] font-medium text-gray-500">
                            <input type="checkbox" id="scanSound" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-red focus:ring-brand-red/40" checked>
                            Sonido
                        </label>
                    </div>
                </div>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-red"><?= icon('tag', 'w-5 h-5') ?></span>
                        <input id="barcodeScanner" type="text" autocomplete="off" inputmode="none"
                               class="lcn-input pl-11 font-mono uppercase tracking-wider"
                               placeholder="Escanee una etiqueta tras otra…">
                    </div>
                    <button type="button" id="scanAddButton" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-dark px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
                        <?= icon('plus', 'w-4 h-4') ?> Agregar
                    </button>
                </div>
                <p id="scanFeedback" class="mt-2 text-xs text-gray-500">Cada pieza se agrega sola y el campo queda listo para la siguiente: no hay que hacer clic ni borrar el código anterior.</p>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="lcn-label" for="productPicker">Agregar manualmente</label>
                    <select id="productPicker" class="lcn-input">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= e($p['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?> · <?= e(money($p['rental_price'])) ?><?= !empty($p['is_complement']) ? ' · Complemento' : '' ?>
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
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="lcn-label" for="event_date">Fecha del evento</label>
                    <input type="date" id="event_date" name="event_date" value="<?= e($form['event_date']) ?>" class="lcn-input">
                </div>
                <div>
                    <label class="lcn-label" for="delivery_date">Entrega</label>
                    <input type="date" id="delivery_date" name="delivery_date" value="<?= e($form['delivery_date']) ?>" class="lcn-input" required>
                </div>
                <div>
                    <label class="lcn-label" for="delivery_time">Hora de entrega</label>
                    <input type="time" id="delivery_time" name="delivery_time" value="<?= e($form['delivery_time']) ?>" class="lcn-input">
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
                    <label class="lcn-label" for="discount_percent">Descuento (%)</label>
                    <div class="relative">
                        <input type="number" step="0.01" min="0" max="100" id="discount_percent" name="discount_percent"
                               value="<?= e((string) $form['discount_percent']) ?>" class="lcn-input pr-9">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">%</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Equivale a <span id="discountAmount" class="font-medium text-gray-600"><?= e(money(0)) ?></span></p>
                </div>
                <div>
                    <label class="lcn-label">Penalidad por mora</label>
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-3 py-2.5 text-sm text-gray-600">
                        <?= e(money(late_fee_per_day())) ?> por día
                    </div>
                    <p class="mt-1 text-xs text-gray-400">
                        Se aplica sola por cada día <?= late_fee_counts_saturday() ? 'laborable (lun–sáb)' : 'laborable (lun–vie)' ?>
                        de atraso en la devolución.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna lateral: resumen y estado -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Resumen de pago</h2>
            <p class="mt-1 text-xs text-gray-400">
                El total se calcula en vivo. El abono sugerido es del <?= e((string) setting('initial_payment_percentage', 50)) ?>%,
                pero puede escribir el monto exacto que reciba.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <label class="lcn-label" for="totalCalc">Total estimado</label>
                    <input type="text" id="totalCalc" class="lcn-input bg-gray-50 font-semibold" readonly value="0.00">
                </div>
                <div>
                    <label class="lcn-label" for="initial_payment">Abono inicial recibido</label>
                    <input type="number" step="0.01" min="0" id="initial_payment" name="initial_payment"
                           value="<?= e((string) $form['initial_payment']) ?>" class="lcn-input font-semibold"
                           placeholder="0.00">
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <button type="button" id="fillSuggested"
                                class="text-xs font-medium text-brand-red hover:underline">
                            Usar el <?= e((string) setting('initial_payment_percentage', 50)) ?>% sugerido
                        </button>
                        <span id="initialHint" class="text-xs text-gray-400"></span>
                    </div>
                </div>
                <div>
                    <label class="lcn-label" for="calcRemaining">Saldo restante</label>
                    <input type="text" id="calcRemaining" class="lcn-input bg-gray-50" readonly value="0.00">
                </div>
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
    var initialPrices = <?= json_encode((object) array_map('floatval', (array) ($form['item_prices'] ?? []))) ?>;
    var initialAlterations = <?= json_encode((object) array_map('intval', (array) ($form['alterations'] ?? []))) ?>;
    var initialAlterNotes  = <?= json_encode((object) array_map('strval', (array) ($form['alteration_notes'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var currency = <?= json_encode((string) setting('currency', 'RD$'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var byId = {};
    var byCode = {};
    var selected = [];

    // Cada codigo (maestro, SKU y el de CADA unidad fisica) apunta a
    // {product, unit}: la etiqueta cosida a la prenda es la de la unidad.
    function indexProduct(product) {
        product.id = parseInt(product.id, 10);
        product.units = product.units || [];
        byId[product.id] = product;
        indexCode(product.barcode, product, 0);
        indexCode(product.sku, product, 0);
        product.units.forEach(function (unit) { indexCode(unit.code, product, unit.n); });
        return product;
    }

    function indexCode(code, product, unitNumber) {
        code = normalizeCode(code || '');
        if (!code || byCode[code]) return;
        byCode[code] = {product: product, unit: unitNumber || 0};
    }

    // El lector puede mandar el codigo de la unidad, el del producto, el SKU,
    // solo los digitos o el id: se prueban en ese orden antes de rendirse.
    function resolveCode(code) {
        if (!code) return null;
        if (byCode[code]) return byCode[code];

        var unitParts = code.match(/^(.+?)U(\d+)$/);
        var base = unitParts ? unitParts[1] : code;
        var unitNumber = unitParts ? parseInt(unitParts[2], 10) : 0;

        if (byCode[base]) return {product: byCode[base].product, unit: unitNumber};

        var digits = base.replace(/\D+/g, '');
        if (digits) {
            var product = byId[parseInt(digits, 10)];
            if (product) return {product: product, unit: unitNumber};
        }
        return null;
    }

    catalog.forEach(indexProduct);

    var scanner = document.getElementById('barcodeScanner');
    var feedback = document.getElementById('scanFeedback');
    var soundToggle = document.getElementById('scanSound');
    var picker = document.getElementById('productPicker');
    var list = document.getElementById('selectedProducts');
    var empty = document.getElementById('selectedProductsEmpty');
    var hidden = document.getElementById('productHiddenInputs');
    var count = document.getElementById('productCount');
    var price  = document.getElementById('rental_price');
    var discPct = document.getElementById('discount_percent');
    var discAmountBox = document.getElementById('discountAmount');
    var total  = document.getElementById('totalCalc');
    var initial = document.getElementById('initial_payment');
    var initialHint = document.getElementById('initialHint');
    var remaining = document.getElementById('calcRemaining');
    var suggestedPct = <?= json_encode((float) setting('initial_payment_percentage', 50)) ?>;
    var initialTouched = initial.value !== '';
    var form = scanner.closest('form');

    var SCISSORS = <?= json_encode(icon('scissors', 'w-3.5 h-3.5'), JSON_UNESCAPED_SLASHES) ?>;

    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function isComplement(product) { return String(product.is_complement) === '1'; }
    function alterClass(on) {
        return 'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition '
            + (on ? 'bg-brand-gold text-white hover:bg-amber-600'
                  : 'border border-gray-200 bg-white text-gray-600 hover:border-brand-gold hover:text-brand-gold');
    }
    function normalizeCode(value) { return String(value).trim().replace(/\s+/g, '').toUpperCase(); }
    function money(value) { return currency + ' ' + num(value).toLocaleString('es-DO', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function setFeedback(message, kind) {
        feedback.textContent = message;
        feedback.className = 'mt-2 text-xs ' + (kind === 'error' ? 'text-rose-600' : kind === 'ok' ? 'text-emerald-600' : 'text-gray-500');
    }

    function recalcTotal() {
        var subtotal = selected.reduce(function (product_sum, product) { return product_sum + num(product.price); }, 0);
        price.value = subtotal.toFixed(2);

        var pct = Math.min(100, Math.max(0, num(discPct.value)));
        var discountAmount = Math.round(subtotal * pct) / 100;
        discAmountBox.textContent = money(discountAmount);

        var t = subtotal - discountAmount;
        if (t < 0) t = 0;
        total.value = t.toFixed(2);

        // Abono sugerido mientras el usuario no escriba uno propio
        if (!initialTouched) {
            initial.value = (Math.round(t * suggestedPct) / 100).toFixed(2);
        }
        var abono = Math.min(Math.max(0, num(initial.value)), t);
        remaining.value = (t - abono).toFixed(2);

        if (num(initial.value) > t) {
            initialHint.textContent = 'El abono no puede superar el total.';
            initialHint.className = 'text-xs text-rose-600';
        } else {
            initialHint.textContent = t > 0 ? 'Equivale al ' + Math.round(abono / t * 100) + '% del total' : '';
            initialHint.className = 'text-xs text-gray-400';
        }
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
            if (isComplement(product)) {
                var tag = document.createElement('span');
                tag.className = 'ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 align-middle text-[10px] font-semibold text-amber-700';
                tag.textContent = 'Complemento';
                title.appendChild(tag);
            }
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
            code.textContent = product.unitCode || product.barcode || product.sku || 'Sin código';
            bottom.appendChild(code);
            if (product.unitNumber) {
                var unitTag = document.createElement('span');
                unitTag.className = 'rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700';
                unitTag.textContent = 'Unidad ' + product.unitNumber;
                bottom.appendChild(unitTag);
            }

            if (isComplement(product)) {
                // Los complementos se cobran al precio que se defina en la factura.
                var priceBox = document.createElement('label');
                priceBox.className = 'flex items-center gap-2';
                var priceLabel = document.createElement('span');
                priceLabel.className = 'text-xs text-gray-500';
                priceLabel.textContent = currency;
                var priceInput = document.createElement('input');
                priceInput.type = 'number';
                priceInput.step = '0.01';
                priceInput.min = '0';
                priceInput.name = 'item_prices[' + product.id + ']';
                priceInput.value = num(product.price).toFixed(2);
                priceInput.className = 'w-28 rounded-lg border border-gray-200 bg-white px-2 py-1 text-right text-sm font-semibold text-brand-red focus:border-brand-red focus:outline-none focus:ring-1 focus:ring-brand-red';
                priceInput.setAttribute('aria-label', 'Precio de ' + product.name);
                priceInput.addEventListener('input', function () {
                    product.price = num(priceInput.value);
                    recalcTotal();
                });
                priceBox.appendChild(priceLabel);
                priceBox.appendChild(priceInput);
                bottom.appendChild(priceBox);
            } else {
                var itemPrice = document.createElement('span');
                itemPrice.className = 'text-sm font-semibold text-brand-red';
                itemPrice.textContent = money(product.price);
                bottom.appendChild(itemPrice);
            }

            body.appendChild(bottom);

            /* ---- Marcar la pieza para MODIFICAR (ruedo, cintura…) ---- */
            var alterRow = document.createElement('div');
            alterRow.className = 'mt-3 border-t border-gray-100 pt-2.5';

            var alterButton = document.createElement('button');
            alterButton.type = 'button';
            alterButton.className = alterClass(product.alter);
            alterButton.innerHTML = SCISSORS + '<span>' + (product.alter ? 'Se modificará' : 'Modificar') + '</span>';

            var noteWrap = document.createElement('div');
            noteWrap.className = 'mt-2' + (product.alter ? '' : ' hidden');
            var note = document.createElement('textarea');
            note.name = 'alteration_notes[' + product.id + ']';
            note.rows = 2;
            note.placeholder = '¿Qué hay que modificarle? Ej.: reducir el ruedo 5 cm, coger de la cintura…';
            note.value = product.alterNote || '';
            note.className = 'w-full rounded-lg border border-amber-200 bg-amber-50/50 px-3 py-2 text-xs text-gray-700 placeholder:text-gray-400 focus:border-brand-gold focus:outline-none focus:ring-1 focus:ring-brand-gold';
            note.addEventListener('input', function () { product.alterNote = note.value; });
            noteWrap.appendChild(note);

            var alterFlag = document.createElement('input');
            alterFlag.type = 'hidden';
            alterFlag.name = 'alterations[' + product.id + ']';
            alterFlag.value = '1';
            alterFlag.disabled = !product.alter;
            noteWrap.appendChild(alterFlag);

            alterButton.addEventListener('click', function () {
                product.alter = !product.alter;
                alterButton.className = alterClass(product.alter);
                alterButton.innerHTML = SCISSORS + '<span>' + (product.alter ? 'Se modificará' : 'Modificar') + '</span>';
                noteWrap.classList.toggle('hidden', !product.alter);
                alterFlag.disabled = !product.alter;
                if (product.alter) note.focus();
            });

            alterRow.appendChild(alterButton);
            alterRow.appendChild(noteWrap);
            body.appendChild(alterRow);

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

    /* ------------------------------------------------------------------ *
     *  Escaneo continuo: leer -> agregar -> listo para la siguiente
     * ------------------------------------------------------------------ */
    function clearScanner() {
        scanner.value = '';
        lastLength = 0;
        fastKeys = 0;
        previousKeyAt = 0;
    }

    function scanOk(message) {
        setFeedback(message, 'ok');
        signal(true);
    }

    function scanError(message) {
        setFeedback(message, 'error');
        signal(false);
    }

    function unitCodeOf(product, unitNumber) {
        var match = (product.units || []).filter(function (unit) { return unit.n === unitNumber; })[0];
        return match ? match.code : '';
    }

    function addProduct(product, source, unitNumber) {
        if (!product) {
            if (source !== 'initial') {
                scanError('No se encontró ningún producto con ese código.');
                clearScanner();
                scanner.focus();
            }
            return;
        }
        var already = selected.filter(function (item) { return item.id === product.id; })[0];
        if (already) {
            // El alquiler guarda una línea por modelo: la pieza no se duplica.
            if (source !== 'initial') {
                scanError(product.name + ' ya está en la lista'
                    + (already.unitNumber ? ' (unidad ' + already.unitNumber + ')' : '') + '.');
                clearScanner();
                scanner.focus();
            }
            return;
        }
        // Copia propia de la línea: el precio de los complementos se edita aquí.
        var line = Object.assign({}, product);
        line.price = initialPrices[product.id] !== undefined
            ? num(initialPrices[product.id])
            : num(product.rental_price);
        line.alter      = !!initialAlterations[product.id];
        line.alterNote  = initialAlterNotes[product.id] || '';
        line.unitNumber = unitNumber || 0;
        line.unitCode   = line.unitNumber ? unitCodeOf(product, line.unitNumber) : '';
        selected.push(line);
        render();
        clearScanner();
        picker.value = '';
        if (source !== 'initial') {
            scanOk('Agregado: ' + product.name
                + (line.unitNumber ? ' · unidad ' + line.unitNumber : '')
                + '. Escanee la siguiente pieza.');
            scanner.focus();
        }
    }

    function addScanned() {
        stopScanTimer();
        var code = normalizeCode(scanner.value);
        clearScanner();               // el campo queda SIEMPRE listo para la siguiente
        if (!code) {
            scanError('Escanee o escriba un código.');
            scanner.focus();
            return;
        }
        var hit = resolveCode(code);
        if (hit) {
            addProduct(hit.product, 'scanner', hit.unit);
            return;
        }
        lookupOnServer(code);         // etiqueta que no está en el catálogo cargado
    }

    /*
     * El catálogo del formulario solo trae piezas alquilables y activas. Si el
     * código no aparece se pregunta al servidor, que dice POR QUÉ (es de venta,
     * está inactivo, no existe) o devuelve un producto creado después de abrir
     * esta pantalla para agregarlo igualmente.
     */
    var pendingLookup = null;
    function lookupOnServer(code) {
        setFeedback('Buscando ' + code + '…', 'info');
        if (pendingLookup) pendingLookup.abort();
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        pendingLookup = controller;
        fetch(window.LCN_BASE + '/admin/alquileres/buscar-producto.php?code=' + encodeURIComponent(code), {
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('http ' + response.status));
        }).then(function (data) {
            pendingLookup = null;
            if (data && data.ok && data.product) {
                var product = byId[parseInt(data.product.id, 10)] || indexProduct(data.product);
                addProduct(product, 'scanner', parseInt(data.unit_number || 0, 10));
                return;
            }
            scanError((data && data.message) || ('Ningún producto tiene el código ' + code + '.'));
            scanner.focus();
        }).catch(function (error) {
            pendingLookup = null;
            if (error && error.name === 'AbortError') return;
            scanError('No se pudo consultar el código ' + code + '. Intente de nuevo.');
            scanner.focus();
        });
    }

    document.getElementById('scanAddButton').addEventListener('click', addScanned);
    document.getElementById('pickerAddButton').addEventListener('click', function () {
        addProduct(byId[parseInt(picker.value, 10)], 'picker', 0);
    });
    // También al elegir en el desplegable, sin tener que pulsar el botón.
    picker.addEventListener('change', function () {
        if (picker.value) addProduct(byId[parseInt(picker.value, 10)], 'picker', 0);
    });

    scanner.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            addScanned();
        }
    });

    /*
     * Muchos lectores no envían Enter: la ráfaga de teclas (o el pegado de golpe)
     * se confirma sola tras 160 ms de silencio.
     */
    var previousKeyAt = 0;
    var fastKeys = 0;
    var lastLength = 0;
    var scanTimer = null;

    function stopScanTimer() {
        if (scanTimer) { clearTimeout(scanTimer); scanTimer = null; }
    }

    function noteKeystroke(jump) {
        var now = Date.now();
        fastKeys = previousKeyAt && (now - previousKeyAt) < 90 ? fastKeys + 1 : 0;
        previousKeyAt = now;
        lastLength = scanner.value.length;
        stopScanTimer();
        if ((fastKeys >= 2 || jump >= 4) && normalizeCode(scanner.value).length >= 4) {
            scanTimer = setTimeout(addScanned, 160);
        }
    }

    scanner.addEventListener('input', function () {
        noteKeystroke(scanner.value.length - lastLength);
    });

    /*
     * El lector escribe como un teclado. Si el foco no está dentro de un campo,
     * las teclas se desvían al campo de escaneo: así no hay que hacer clic en él.
     */
    document.addEventListener('keydown', function (event) {
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        var target = event.target;
        var tag = target && target.tagName ? target.tagName.toUpperCase() : '';
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'BUTTON'
            || tag === 'A' || (target && target.isContentEditable)) {
            return;
        }
        if (event.key && event.key.length === 1) {
            event.preventDefault();
            scanner.value += event.key;
            scanner.focus();
            noteKeystroke(1);
        } else if (event.key === 'Enter' && scanner.value !== '') {
            event.preventDefault();
            addScanned();
        }
    });

    /* Aviso sonoro + destello: la usuaria mira la prenda, no la pantalla. */
    var audioContext = null;
    function signal(ok) {
        scanner.classList.remove('ring-2', 'ring-emerald-400', 'ring-rose-400');
        void scanner.offsetWidth;
        scanner.classList.add('ring-2', ok ? 'ring-emerald-400' : 'ring-rose-400');
        setTimeout(function () {
            scanner.classList.remove('ring-2', 'ring-emerald-400', 'ring-rose-400');
        }, 500);
        if (soundToggle && !soundToggle.checked) return;
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            audioContext = audioContext || new Ctx();
            if (audioContext.state === 'suspended') audioContext.resume();
            var osc  = audioContext.createOscillator();
            var gain = audioContext.createGain();
            osc.type = ok ? 'sine' : 'square';
            osc.frequency.value = ok ? 1180 : 240;
            gain.gain.value = 0.06;
            osc.connect(gain);
            gain.connect(audioContext.destination);
            osc.start();
            osc.stop(audioContext.currentTime + (ok ? 0.09 : 0.24));
        } catch (error) { /* sin audio disponible: basta el destello */ }
    }

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

    discPct.addEventListener('input', recalcTotal);
    initial.addEventListener('input', function () {
        initialTouched = true;
        recalcTotal();
    });
    document.getElementById('fillSuggested').addEventListener('click', function () {
        initialTouched = false;
        recalcTotal();
        initialTouched = initial.value !== '';
    });

    form.addEventListener('submit', function (event) {
        if (!selected.length) {
            event.preventDefault();
            setFeedback('Debe agregar al menos un producto antes de crear el alquiler.', 'error');
            scanner.focus();
            return;
        }
        if (num(initial.value) > num(total.value) + 0.009) {
            event.preventDefault();
            initial.focus();
        }
    });

    initialIds.forEach(function (id) { addProduct(byId[id], 'initial', 0); });
    render();
    scanner.focus();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
