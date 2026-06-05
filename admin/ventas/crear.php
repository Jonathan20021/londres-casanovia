<?php
/**
 * Registrar nueva venta — LONDRES Casa de Novias
 *
 * Flujo POST:
 *   total = sale_price - discount
 *   sale_number = generate_number('sales','sale_number','VEN')
 *   insert sale (status='completed', payment_status según pago inicial)
 *   marcar producto: si is_unique -> commercial_status='sold';
 *                    si maneja quantity -> decrementa y si llega a 0 'sold'
 *   crear factura (invoice_type='sale')
 *   si hay pago inicial -> payment (REC) ligado a sale/invoice y ajustar estados
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('sales.manage');

$errors  = [];
// Valores previos para repoblar el formulario tras un error de validación
$old = [
    'customer_id'    => '',
    'product_id'     => '',
    'sale_price'     => '',
    'discount'       => '0',
    'notes'          => '',
    'initial_amount' => '',
    'payment_method' => 'cash',
];

/* ------------------------------------------------------------------ *
 *  Manejo POST
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    $old['customer_id']    = (string) post('customer_id', '');
    $old['product_id']     = (string) post('product_id', '');
    $old['sale_price']     = (string) post('sale_price', '');
    $old['discount']       = (string) post('discount', '0');
    $old['notes']          = (string) post('notes', '');
    $old['initial_amount'] = (string) post('initial_amount', '');
    $old['payment_method'] = (string) post('payment_method', 'cash');

    $customerId = (int) $old['customer_id'];
    $productId  = (int) $old['product_id'];
    $salePrice  = round((float) str_replace(',', '', $old['sale_price']), 2);
    $discount   = round((float) str_replace(',', '', $old['discount']), 2);
    $notes      = trim($old['notes']);
    $initial    = $old['initial_amount'] === '' ? 0.0 : round((float) str_replace(',', '', $old['initial_amount']), 2);

    $validMethods = ['cash', 'transfer', 'card', 'deposit', 'other'];
    $payMethod = in_array($old['payment_method'], $validMethods, true) ? $old['payment_method'] : 'cash';

    // --- Validaciones ---
    $customer = $customerId ? db_one('SELECT * FROM customers WHERE id = :id', ['id' => $customerId]) : null;
    if (!$customer) {
        $errors[] = 'Debe seleccionar un cliente válido.';
    }

    // El producto debe ser vendible: tipo sale/both y aún no vendido
    $product = $productId
        ? db_one("SELECT * FROM products
                  WHERE id = :id AND status = 'active'
                    AND type IN ('sale','both')
                    AND commercial_status <> 'sold'",
                 ['id' => $productId])
        : null;
    if (!$product) {
        $errors[] = 'Debe seleccionar un producto disponible para venta.';
    }

    if ($salePrice <= 0) {
        $errors[] = 'El precio de venta debe ser mayor que cero.';
    }
    if ($discount < 0) {
        $errors[] = 'El descuento no puede ser negativo.';
    }
    if ($discount > $salePrice) {
        $errors[] = 'El descuento no puede ser mayor que el precio de venta.';
    }

    $total = round($salePrice - $discount, 2);
    if ($total < 0) {
        $total = 0.0;
    }

    if ($initial < 0) {
        $errors[] = 'El pago inicial no puede ser negativo.';
    }
    if ($initial > $total) {
        $errors[] = 'El pago inicial no puede ser mayor que el total de la venta.';
    }

    // Validar disponibilidad por inventario (productos con cantidad)
    if ($product && (int) $product['is_unique'] !== 1 && (int) $product['quantity'] <= 0) {
        $errors[] = 'El producto seleccionado no tiene unidades disponibles en inventario.';
    }

    if (!$errors) {
        // Estado de pago de la venta según lo cobrado
        if ($initial <= 0) {
            $payStatus = 'pending';
        } elseif ($initial + 0.009 < $total) {
            $payStatus = 'partial';
        } else {
            $payStatus = 'paid';
        }

        $userId     = (int) (current_user()['id'] ?? 0);
        $saleNumber = generate_number('sales', 'sale_number', 'VEN');

        // 1) Insertar la venta
        $saleId = db_insert('sales', [
            'sale_number'    => $saleNumber,
            'customer_id'    => $customerId,
            'product_id'     => $productId,
            'sale_price'     => $salePrice,
            'discount'       => $discount,
            'total_amount'   => $total,
            'payment_status' => $payStatus,
            'status'         => 'completed',
            'notes'          => $notes !== '' ? $notes : null,
            'created_by'     => $userId ?: null,
        ]);

        // 2) Actualizar inventario del producto
        if ((int) $product['is_unique'] === 1) {
            // Producto único: queda vendido
            db_update('products', ['commercial_status' => 'sold'], 'id = :id', ['id' => $productId]);
        } else {
            // Producto con cantidad: descontar una unidad
            $newQty = max(0, (int) $product['quantity'] - 1);
            $update = ['quantity' => $newQty];
            if ($newQty <= 0) {
                $update['commercial_status'] = 'sold';
            }
            db_update('products', $update, 'id = :id', ['id' => $productId]);
        }

        // 3) Crear la factura asociada (tipo venta)
        $invoicePaid    = $initial;
        $invoiceBalance = round($total - $invoicePaid, 2);
        if ($invoiceBalance < 0) {
            $invoiceBalance = 0.0;
        }
        if ($invoicePaid <= 0) {
            $invoiceStatus = 'pending';
        } elseif ($invoiceBalance > 0.009) {
            $invoiceStatus = 'partial';
        } else {
            $invoiceStatus = 'paid';
        }

        $invoiceNumber = generate_number('invoices', 'invoice_number', (string) setting('invoice_prefix', 'FAC'));
        $invoiceId = db_insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'customer_id'    => $customerId,
            'rental_id'      => null,
            'sale_id'        => $saleId,
            'invoice_type'   => 'sale',
            'concept'        => 'Venta de ' . $product['name'],
            'subtotal'       => $salePrice,
            'discount'       => $discount,
            'tax'            => 0.00,
            'total'          => $total,
            'paid_amount'    => $invoicePaid,
            'balance'        => $invoiceBalance,
            'status'         => $invoiceStatus,
            'issued_at'      => date('Y-m-d'),
            'created_by'     => $userId ?: null,
        ]);

        // 4) Registrar el pago inicial (si aplica)
        if ($initial > 0) {
            $paymentNumber = generate_number('payments', 'payment_number', 'REC');
            db_insert('payments', [
                'payment_number' => $paymentNumber,
                'customer_id'    => $customerId,
                'rental_id'      => null,
                'sale_id'        => $saleId,
                'invoice_id'     => $invoiceId,
                'amount'         => $initial,
                'payment_method' => $payMethod,
                'reference'      => null,
                'notes'          => 'Pago inicial de venta ' . $saleNumber,
                'received_by'    => $userId ?: null,
                'paid_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        log_activity('sale.create', 'sale', $saleId,
            'Venta ' . $saleNumber . ' por ' . money($total) . ' — ' . $product['name']);

        flash('success', 'Venta ' . $saleNumber . ' registrada correctamente. Factura ' . $invoiceNumber . ' generada.');
        redirect(admin_url('facturas/imprimir.php?id=' . $invoiceId));
    }
}

/* ------------------------------------------------------------------ *
 *  Datos para los selects
 * ------------------------------------------------------------------ */
$customers = db_all('SELECT id, full_name, phone FROM customers ORDER BY full_name ASC');

// Productos vendibles: tipo venta/ambos, activos y no vendidos
$products = db_all(
    "SELECT id, name, sale_price, is_unique, quantity, commercial_status
     FROM products
     WHERE status = 'active'
       AND type IN ('sale','both')
       AND commercial_status <> 'sold'
     ORDER BY name ASC"
);

// Mapa de precios para autollenado en JS (id => sale_price)
$productPrices = [];
foreach ($products as $p) {
    $productPrices[(int) $p['id']] = (float) ($p['sale_price'] ?? 0);
}

$page_title    = 'Nueva venta';
$page_subtitle = 'Registra la venta de un vestido, traje o accesorio';
$active        = 'ventas';
$header_actions = '<a href="' . admin_url('ventas/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver a ventas</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if ($errors): ?>
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
        <span class="mt-0.5 text-rose-500"><?= icon('warning', 'w-5 h-5') ?></span>
        <div class="flex-1">
            <p class="font-semibold">No se pudo registrar la venta:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if (!$customers || !$products): ?>
    <?= empty_state(
        'Faltan datos para registrar una venta',
        (!$products
            ? 'No hay productos disponibles para venta. Asegúrese de tener productos de tipo Venta o Ambos que no estén vendidos.'
            : 'No hay clientes registrados. Registre primero un cliente.'),
        'warning',
        (!$products
            ? '<a href="' . admin_url('productos/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo producto</a>'
            : '<a href="' . admin_url('clientes/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo cliente</a>')
    ) ?>
<?php else: ?>

<form method="post" action="<?= admin_url('ventas/crear.php') ?>" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>

    <!-- Columna principal -->
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-4 flex items-center gap-2 font-serif text-lg font-semibold text-gray-900">
                <span class="text-brand-red"><?= icon('bag', 'w-5 h-5') ?></span> Detalle de la venta
            </h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <!-- Cliente -->
                <div class="sm:col-span-2">
                    <label for="customer_id" class="lcn-label">Cliente <span class="text-brand-red">*</span></label>
                    <select id="customer_id" name="customer_id" required class="lcn-input">
                        <option value="">Seleccione un cliente…</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['customer_id'] ? 'selected' : '' ?>>
                                <?= e($c['full_name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Producto -->
                <div class="sm:col-span-2">
                    <label for="product_id" class="lcn-label">Producto <span class="text-brand-red">*</span></label>
                    <select id="product_id" name="product_id" required class="lcn-input"
                            data-prices='<?= e(json_encode($productPrices, JSON_UNESCAPED_UNICODE)) ?>'>
                        <option value="">Seleccione un producto…</option>
                        <?php foreach ($products as $p):
                            $stockLabel = (int) $p['is_unique'] === 1 ? 'pieza única' : ('stock: ' . (int) $p['quantity']); ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $old['product_id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> · <?= e(money($p['sale_price'] ?? 0)) ?> · <?= e($stockLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Sólo se muestran productos de venta no vendidos.</p>
                </div>

                <!-- Precio de venta -->
                <div>
                    <label for="sale_price" class="lcn-label">Precio de venta <span class="text-brand-red">*</span></label>
                    <input type="number" id="sale_price" name="sale_price" step="0.01" min="0" required
                           value="<?= e($old['sale_price']) ?>" class="lcn-input"
                           data-payment-total placeholder="0.00">
                    <p class="mt-1 text-xs text-gray-400">Se autocompleta con el precio del producto; puede ajustarlo.</p>
                </div>

                <!-- Descuento -->
                <div>
                    <label for="discount" class="lcn-label">Descuento</label>
                    <input type="number" id="discount" name="discount" step="0.01" min="0"
                           value="<?= e($old['discount']) ?>" class="lcn-input" placeholder="0.00">
                </div>

                <!-- Notas -->
                <div class="sm:col-span-2">
                    <label for="notes" class="lcn-label">Notas</label>
                    <textarea id="notes" name="notes" rows="3" class="lcn-input"
                              placeholder="Observaciones de la venta (opcional)…"><?= e($old['notes']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Pago inicial -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-1 flex items-center gap-2 font-serif text-lg font-semibold text-gray-900">
                <span class="text-brand-red"><?= icon('banknotes', 'w-5 h-5') ?></span> Pago inicial
            </h2>
            <p class="mb-4 text-sm text-gray-500">Opcional. Si registra un monto, se generará un recibo de pago vinculado a la factura.</p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="initial_amount" class="lcn-label">Monto recibido</label>
                    <input type="number" id="initial_amount" name="initial_amount" step="0.01" min="0"
                           value="<?= e($old['initial_amount']) ?>" class="lcn-input" placeholder="0.00">
                </div>
                <div>
                    <label for="payment_method" class="lcn-label">Método de pago</label>
                    <select id="payment_method" name="payment_method" class="lcn-input">
                        <?php
                        $methods = [
                            'cash'     => 'Efectivo',
                            'transfer' => 'Transferencia',
                            'card'     => 'Tarjeta',
                            'deposit'  => 'Depósito',
                            'other'    => 'Otro',
                        ];
                        foreach ($methods as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $old['payment_method'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna lateral: resumen -->
    <div class="lg:col-span-1">
        <div class="sticky top-24 rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="mb-4 flex items-center gap-2 font-serif text-lg font-semibold text-gray-900">
                <span class="text-brand-red"><?= icon('document', 'w-5 h-5') ?></span> Resumen
            </h2>

            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500">Precio de venta</dt>
                    <dd class="font-medium text-gray-900" id="sumPrice">—</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500">Descuento</dt>
                    <dd class="font-medium text-gray-900" id="sumDiscount">—</dd>
                </div>
                <div class="border-t border-gray-100 pt-3 flex items-center justify-between">
                    <dt class="font-semibold text-gray-900">Total a pagar</dt>
                    <dd class="text-lg font-bold text-brand-red" id="sumTotal">—</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500">Pago inicial</dt>
                    <dd class="font-medium text-gray-900" id="sumInitial">—</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500">Saldo pendiente</dt>
                    <dd class="font-medium text-gray-900" id="sumBalance">—</dd>
                </div>
            </dl>

            <button type="submit" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Registrar venta
            </button>
            <a href="<?= admin_url('ventas/index.php') ?>" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </div>
</form>

<!-- Lógica de autollenado de precio y cálculo de totales (vanilla JS, sin librerías) -->
<script>
(function () {
    var moneda = <?= json_encode((string) setting('currency', 'RD$')) ?>;
    var sel    = document.getElementById('product_id');
    var price  = document.getElementById('sale_price');
    var disc   = document.getElementById('discount');
    var init   = document.getElementById('initial_amount');
    var prices = {};
    try { prices = JSON.parse(sel.getAttribute('data-prices') || '{}'); } catch (e) { prices = {}; }

    function fmt(n) {
        n = isNaN(n) ? 0 : n;
        return moneda + ' ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function num(el) {
        var v = parseFloat((el && el.value ? el.value : '').toString().replace(/,/g, ''));
        return isNaN(v) ? 0 : v;
    }

    function recalc() {
        var p = num(price);
        var d = num(disc);
        var total = p - d;
        if (total < 0) total = 0;
        var ini = num(init);
        if (ini > total) ini = total;
        var bal = total - ini;
        if (bal < 0) bal = 0;

        document.getElementById('sumPrice').textContent    = fmt(p);
        document.getElementById('sumDiscount').textContent = fmt(d);
        document.getElementById('sumTotal').textContent    = fmt(total);
        document.getElementById('sumInitial').textContent  = fmt(ini);
        document.getElementById('sumBalance').textContent  = fmt(bal);
    }

    if (sel) {
        sel.addEventListener('change', function () {
            var id = sel.value;
            if (id && prices.hasOwnProperty(id) && (!price.value || parseFloat(price.value) === 0)) {
                price.value = parseFloat(prices[id]).toFixed(2);
            }
            recalc();
        });
    }
    [price, disc, init].forEach(function (el) {
        if (el) el.addEventListener('input', recalc);
    });
    recalc();
})();
</script>

<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
