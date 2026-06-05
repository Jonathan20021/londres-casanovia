<?php
/**
 * Alquileres · Crear
 * LONDRES Casa de Novias
 *
 * Crea un alquiler tras RE-VALIDAR la disponibilidad del producto en el
 * servidor. Si llega ?request=ID precarga datos de una solicitud pública.
 * Al guardar: genera número, calcula 50/50, reserva el producto y crea la
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
    "SELECT id, name, rental_price, deposit_amount, commercial_status, type
       FROM products
      WHERE status = 'active' AND type IN ('rental','both')
      ORDER BY name ASC"
);

/* ------------------------------------------------------------------ *
 *  Precarga desde una solicitud (?request=ID)
 * ------------------------------------------------------------------ */
$requestId = (int) get_param('request', '0');
$prefill = [
    'customer_id'   => '',
    'product_id'    => (int) get_param('product', '0') ?: '',
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
        'product_id'    => (int) post('product_id', 0),
        'event_date'    => trim((string) post('event_date', '')),
        'delivery_date' => trim((string) post('delivery_date', '')),
        'return_date'   => trim((string) post('return_date', '')),
        'rental_price'  => (float) post('rental_price', 0),
        'discount'      => (float) post('discount', 0),
        'late_penalty'  => (float) post('late_penalty', 0),
        'rental_status' => (string) post('rental_status', 'reserved'),
    ];
    $requestId = (int) post('request_id', $requestId);

    // --- Validaciones básicas ---
    if ($form['customer_id'] <= 0) $errors[] = 'Seleccione un cliente.';
    if ($form['product_id'] <= 0)  $errors[] = 'Seleccione un producto.';
    if ($form['delivery_date'] === '') $errors[] = 'Indique la fecha de entrega.';
    if ($form['return_date'] === '')   $errors[] = 'Indique la fecha de devolución.';
    if ($form['delivery_date'] !== '' && $form['return_date'] !== ''
        && strtotime($form['return_date']) < strtotime($form['delivery_date'])) {
        $errors[] = 'La fecha de devolución no puede ser anterior a la de entrega.';
    }
    if ($form['rental_price'] <= 0) $errors[] = 'El precio de alquiler debe ser mayor que cero.';
    if ($form['discount'] < 0 || $form['late_penalty'] < 0) $errors[] = 'Los montos no pueden ser negativos.';

    // Estados de creación permitidos
    if (!in_array($form['rental_status'], ['reserved', 'confirmed'], true)) {
        $form['rental_status'] = 'reserved';
    }

    // El cliente y el producto deben existir
    if ($form['customer_id'] > 0 && !db_one('SELECT id FROM customers WHERE id = :id', ['id' => $form['customer_id']])) {
        $errors[] = 'El cliente seleccionado no existe.';
    }
    $product = null;
    if ($form['product_id'] > 0) {
        $product = db_one("SELECT * FROM products WHERE id = :id AND status = 'active' AND type IN ('rental','both')",
            ['id' => $form['product_id']]);
        if (!$product) $errors[] = 'El producto seleccionado no es válido para alquiler.';
    }

    // --- RE-VALIDACIÓN de disponibilidad en el servidor ---
    if (!$errors) {
        $check = checkProductAvailability(
            $form['product_id'],
            $form['delivery_date'],
            $form['return_date']
        );
        if (!empty($check['error'])) {
            $errors[] = $check['error'];
        } elseif (!$check['available']) {
            $conflict = $check['conflict'];
            flash('error', 'El producto no está disponible para las fechas seleccionadas. Revise el conflicto indicado.');
        }
    }

    // --- Si todo está bien, persistir ---
    if (!$errors && !$conflict) {
        $total = round($form['rental_price'] - $form['discount'] + $form['late_penalty'], 2);
        if ($total < 0) $total = 0.0;
        $calc = calculateRentalPayments($total);

        $rentalNumber = generate_number('rentals', 'rental_number', 'ALQ');

        $rentalId = db_insert('rentals', [
            'rental_number'            => $rentalNumber,
            'customer_id'              => $form['customer_id'],
            'product_id'               => $form['product_id'],
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

        // El producto pasa a reservado
        db_update('products', ['commercial_status' => 'reserved'], 'id = :id', ['id' => $form['product_id']]);

        // Factura de alquiler asociada
        $invoiceNumber = generate_number('invoices', 'invoice_number', (string) setting('invoice_prefix', 'FAC'));
        db_insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'customer_id'    => $form['customer_id'],
            'rental_id'      => $rentalId,
            'sale_id'        => null,
            'invoice_type'   => 'rental',
            'concept'        => 'Alquiler ' . $rentalNumber . ' · ' . ($product['name'] ?? ''),
            'subtotal'       => $total,
            'discount'       => $form['discount'],
            'tax'            => 0,
            'total'          => $total,
            'paid_amount'    => 0,
            'balance'        => $total,
            'status'         => 'pending',
            'issued_at'      => date('Y-m-d'),
            'created_by'     => $user['id'],
        ]);

        // Si proviene de una solicitud, marcarla como convertida
        if ($requestId > 0 && $request) {
            db_update('rental_requests', ['status' => 'converted'], 'id = :id', ['id' => $requestId]);
        }

        log_activity('rental.create', 'rental', $rentalId,
            'Alquiler ' . $rentalNumber . ' creado por ' . $total);

        flash('success', 'Alquiler ' . $rentalNumber . ' creado correctamente.');
        redirect(admin_url('alquileres/ver.php?id=' . $rentalId));
    }
    // Si hubo errores o conflicto, se re-pinta el formulario más abajo con $errors / $conflict.
}

$page_title    = 'Nuevo alquiler';
$page_subtitle = 'Registra una reserva validando la disponibilidad del producto';
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
        <!-- Cliente y producto -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Cliente y producto</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div>
                    <label class="lcn-label" for="product_id">Producto</label>
                    <select id="product_id" name="product_id" class="lcn-input" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"
                                    data-price="<?= e((string) $p['rental_price']) ?>"
                                    <?= (int) $form['product_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> · <?= e(money($p['rental_price'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1.5 text-xs text-gray-400">Solo productos disponibles para alquiler.</p>
                </div>
            </div>
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
                    <p class="text-sm text-gray-600">Confirma que el producto esté libre antes de guardar.</p>
                    <button type="button" data-check-availability
                            data-product="#product_id" data-delivery="#delivery_date"
                            data-return="#return_date" data-exclude="" data-result="#boxDisp"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('check', 'w-4 h-4') ?> Verificar disponibilidad
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
                    <label class="lcn-label" for="rental_price">Precio de alquiler</label>
                    <input type="number" step="0.01" min="0" id="rental_price" name="rental_price"
                           value="<?= e((string) $form['rental_price']) ?>" class="lcn-input" required>
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
/* Autocompleta el precio al elegir producto y recalcula el total en vivo.
   El campo #totalCalc usa el hook data-payment-total del núcleo (app.js). */
(function () {
    var sel    = document.getElementById('product_id');
    var price  = document.getElementById('rental_price');
    var disc   = document.getElementById('discount');
    var pen    = document.getElementById('late_penalty');
    var total  = document.getElementById('totalCalc');

    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }

    function recalcTotal() {
        var t = num(price.value) - num(disc.value) + num(pen.value);
        if (t < 0) t = 0;
        total.value = t.toFixed(2);
        // dispara el recálculo 50/50 del núcleo
        total.dispatchEvent(new Event('input'));
    }

    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        var p = opt ? opt.getAttribute('data-price') : null;
        // solo autocompleta si el campo está vacío o en 0
        if (p && (!price.value || num(price.value) === 0)) {
            price.value = parseFloat(p).toFixed(2);
        }
        recalcTotal();
    });

    [price, disc, pen].forEach(function (el) { el.addEventListener('input', recalcTotal); });
    recalcTotal();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
