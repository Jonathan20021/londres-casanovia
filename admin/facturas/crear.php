<?php
/**
 * Facturas — Crear factura manual (o asociada a un alquiler/venta).
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('invoices.manage');

$u        = current_user();
$taxPct   = (float) setting('tax_percentage', 0);
$errors   = [];

/* Datos para preseleccionar desde el origen (?rental=ID o ?sale=ID) */
$preRental = (int) get_param('rental');
$preSale   = (int) get_param('sale');

/* ------------------------------------------------------------------ *
 *  Manejo POST
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    $invoiceType = post('invoice_type') === 'sale' ? 'sale' : 'rental';
    $customerId  = (int) post('customer_id');
    $rentalId    = (int) post('rental_id') ?: null;
    $saleId      = (int) post('sale_id') ?: null;
    $concept     = trim((string) post('concept', ''));
    $subtotal    = round((float) post('subtotal', 0), 2);
    $discount    = round((float) post('discount', 0), 2);
    $issuedAt    = trim((string) post('issued_at', '')) ?: date('Y-m-d');

    // El tipo determina qué origen conservar
    if ($invoiceType === 'rental') {
        $saleId = null;
    } else {
        $rentalId = null;
    }

    // Validaciones
    if ($customerId <= 0) {
        $errors[] = 'Debe seleccionar un cliente.';
    } elseif (!db_one('SELECT id FROM customers WHERE id = :id', ['id' => $customerId])) {
        $errors[] = 'El cliente seleccionado no existe.';
    }
    if ($subtotal <= 0) {
        $errors[] = 'El subtotal debe ser mayor que cero.';
    }
    if ($discount < 0) {
        $errors[] = 'El descuento no puede ser negativo.';
    }
    if ($discount > $subtotal) {
        $errors[] = 'El descuento no puede ser mayor que el subtotal.';
    }
    if ($concept === '') {
        $errors[] = 'Indique un concepto para la factura.';
    }

    // Validar coherencia del origen asociado (si se indicó)
    if ($invoiceType === 'rental' && $rentalId) {
        $rental = db_one('SELECT id, customer_id FROM rentals WHERE id = :id', ['id' => $rentalId]);
        if (!$rental) {
            $errors[] = 'El alquiler asociado no existe.';
        }
    }
    if ($invoiceType === 'sale' && $saleId) {
        $sale = db_one('SELECT id, customer_id FROM sales WHERE id = :id', ['id' => $saleId]);
        if (!$sale) {
            $errors[] = 'La venta asociada no existe.';
        }
    }

    if (!$errors) {
        // Cálculo de montos (tax sobre base imponible = subtotal - discount)
        $base  = max(0, $subtotal - $discount);
        $tax   = round($base * ($taxPct / 100), 2);
        $totalAmount = round($base + $tax, 2);

        // Monto ya pagado según el origen asociado
        $paid = 0.0;
        if ($invoiceType === 'rental' && $rentalId) {
            $paid = rental_paid_amount($rentalId);
        } elseif ($invoiceType === 'sale' && $saleId) {
            $paid = sale_paid_amount($saleId);
        }
        $paid    = min($paid, $totalAmount); // nunca exceder el total
        $balance = round($totalAmount - $paid, 2);

        // Estado según pagos
        if ($paid <= 0.009)        $status = 'pending';
        elseif ($balance > 0.009)  $status = 'partial';
        else                       $status = 'paid';

        $invoiceNumber = generate_number('invoices', 'invoice_number', (string) setting('invoice_prefix', 'FAC'));

        $invoiceId = db_insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'customer_id'    => $customerId,
            'rental_id'      => $rentalId,
            'sale_id'        => $saleId,
            'invoice_type'   => $invoiceType,
            'concept'        => $concept,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'tax'            => $tax,
            'total'          => $totalAmount,
            'paid_amount'    => $paid,
            'balance'        => max(0, $balance),
            'status'         => $status,
            'issued_at'      => $issuedAt,
            'created_by'     => (int) $u['id'],
        ]);

        log_activity('create', 'invoice', $invoiceId, 'Factura creada: ' . $invoiceNumber);
        flash('success', 'Factura ' . $invoiceNumber . ' creada correctamente.');
        redirect(admin_url('facturas/ver.php?id=' . $invoiceId));
    }
}

/* ------------------------------------------------------------------ *
 *  Datos para el formulario
 * ------------------------------------------------------------------ */
$customers = db_all('SELECT id, full_name, phone FROM customers ORDER BY full_name ASC');

$rentals = db_all(
    "SELECT r.id, r.rental_number, r.total_amount, c.full_name AS customer_name,
            (SELECT GROUP_CONCAT(p2.name ORDER BY ri.sort_order SEPARATOR ', ')
             FROM rental_items ri JOIN products p2 ON p2.id = ri.product_id
             WHERE ri.rental_id = r.id) AS product_name
     FROM rentals r
     JOIN customers c ON c.id = r.customer_id
     ORDER BY r.created_at DESC
     LIMIT 200"
);
$sales = db_all(
    "SELECT s.id, s.sale_number, s.total_amount, c.full_name AS customer_name, p.name AS product_name
     FROM sales s
     JOIN customers c ON c.id = s.customer_id
     JOIN products  p ON p.id = s.product_id
     ORDER BY s.created_at DESC
     LIMIT 200"
);

/* Mapa JS para autocompletar líneas al elegir un origen */
$rentalMap = [];
foreach ($rentals as $r) {
    $rentalMap[(int) $r['id']] = [
        'customer_id' => (int) db_value('SELECT customer_id FROM rentals WHERE id = :id', ['id' => $r['id']]),
        'subtotal'    => (float) $r['total_amount'],
        'concept'     => 'Alquiler ' . $r['rental_number'] . ' — ' . $r['product_name'],
    ];
}
$saleMap = [];
foreach ($sales as $s) {
    $saleMap[(int) $s['id']] = [
        'customer_id' => (int) db_value('SELECT customer_id FROM sales WHERE id = :id', ['id' => $s['id']]),
        'subtotal'    => (float) $s['total_amount'],
        'concept'     => 'Venta ' . $s['sale_number'] . ' — ' . $s['product_name'],
    ];
}

$page_title    = 'Nueva factura';
$page_subtitle = 'Generar una factura manual o asociada a una operación';
$active        = 'facturas';
$header_actions = '<a href="' . e(admin_url('facturas/index.php')) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if ($errors): ?>
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
        <p class="flex items-center gap-2 font-semibold"><?= icon('warning', 'w-5 h-5') ?> Revise los siguientes puntos:</p>
        <ul class="mt-2 list-inside list-disc space-y-0.5">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>

    <!-- Columna principal -->
    <div class="space-y-6 lg:col-span-2">
        <!-- Tipo y origen -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-semibold text-gray-900">Origen de la factura</h2>
            <p class="mt-1 text-sm text-gray-500">Elija el tipo y, opcionalmente, asocie una operación existente.</p>

            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lcn-label" for="invoice_type">Tipo de factura</label>
                    <select id="invoice_type" name="invoice_type" class="lcn-input" data-fac-type>
                        <option value="rental" <?= (post('invoice_type', 'rental') === 'rental') ? 'selected' : '' ?>>Alquiler</option>
                        <option value="sale" <?= (post('invoice_type') === 'sale') ? 'selected' : '' ?>>Venta</option>
                    </select>
                </div>
                <div>
                    <label class="lcn-label" for="issued_at">Fecha de emisión</label>
                    <input type="date" id="issued_at" name="issued_at" value="<?= e(post('issued_at', date('Y-m-d'))) ?>" class="lcn-input">
                </div>
            </div>

            <!-- Selector de alquiler -->
            <div class="mt-4" data-origin="rental">
                <label class="lcn-label" for="rental_id">Asociar a alquiler (opcional)</label>
                <select id="rental_id" name="rental_id" class="lcn-input" data-fac-rental>
                    <option value="">— Factura manual (sin alquiler) —</option>
                    <?php foreach ($rentals as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= ($preRental === (int) $r['id'] || (int) post('rental_id') === (int) $r['id']) ? 'selected' : '' ?>>
                            <?= e($r['rental_number']) ?> · <?= e($r['customer_name']) ?> · <?= e($r['product_name']) ?> (<?= e(money($r['total_amount'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Selector de venta -->
            <div class="mt-4 hidden" data-origin="sale">
                <label class="lcn-label" for="sale_id">Asociar a venta (opcional)</label>
                <select id="sale_id" name="sale_id" class="lcn-input" data-fac-sale>
                    <option value="">— Factura manual (sin venta) —</option>
                    <?php foreach ($sales as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= ($preSale === (int) $s['id'] || (int) post('sale_id') === (int) $s['id']) ? 'selected' : '' ?>>
                            <?= e($s['sale_number']) ?> · <?= e($s['customer_name']) ?> · <?= e($s['product_name']) ?> (<?= e(money($s['total_amount'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$sales): ?>
                    <p class="mt-1.5 text-xs text-gray-400">No hay ventas registradas; puede crear una factura de venta manual.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cliente y concepto -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-semibold text-gray-900">Cliente y concepto</h2>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="lcn-label" for="customer_id">Cliente</label>
                    <select id="customer_id" name="customer_id" class="lcn-input" data-fac-customer required>
                        <option value="">Seleccione un cliente…</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= ((int) post('customer_id') === (int) $c['id']) ? 'selected' : '' ?>>
                                <?= e($c['full_name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$customers): ?>
                        <p class="mt-1.5 text-xs text-rose-500">No hay clientes registrados. Registre uno antes de facturar.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="lcn-label" for="concept">Concepto</label>
                    <textarea id="concept" name="concept" rows="2" class="lcn-input" placeholder="Ej. Alquiler de vestido de novia, evento del 15/08…" data-fac-concept><?= e(post('concept', '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna lateral: montos -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft lg:sticky lg:top-24">
            <h2 class="font-serif text-lg font-semibold text-gray-900">Montos</h2>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="lcn-label" for="subtotal">Subtotal</label>
                    <input type="number" step="0.01" min="0" id="subtotal" name="subtotal" value="<?= e(post('subtotal', '')) ?>" class="lcn-input" data-fac-subtotal required>
                </div>
                <div>
                    <label class="lcn-label" for="discount">Descuento</label>
                    <input type="number" step="0.01" min="0" id="discount" name="discount" value="<?= e(post('discount', '0')) ?>" class="lcn-input" data-fac-discount>
                </div>

                <div class="rounded-xl bg-gray-50 p-4 text-sm">
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-500">Base imponible</span>
                        <span class="font-medium text-gray-800" data-fac-base>—</span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-500">Impuesto (<?= e(rtrim(rtrim(number_format($taxPct, 2, '.', ''), '0'), '.')) ?>%)</span>
                        <span class="font-medium text-gray-800" data-fac-tax>—</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between border-t border-gray-200 pt-2.5">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="text-lg font-bold text-brand-red" data-fac-total>—</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Generar factura
            </button>
            <a href="<?= e(admin_url('facturas/index.php')) ?>" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';
    var TAX = <?= json_encode($taxPct) ?>;
    var CURRENCY = <?= json_encode((string) setting('currency', 'RD$')) ?>;
    var RENTALS = <?= json_encode($rentalMap, JSON_UNESCAPED_UNICODE) ?>;
    var SALES   = <?= json_encode($saleMap, JSON_UNESCAPED_UNICODE) ?>;

    var typeSel   = document.querySelector('[data-fac-type]');
    var rentalSel = document.querySelector('[data-fac-rental]');
    var saleSel   = document.querySelector('[data-fac-sale]');
    var custSel   = document.querySelector('[data-fac-customer]');
    var concept   = document.querySelector('[data-fac-concept]');
    var subInput  = document.querySelector('[data-fac-subtotal]');
    var discInput = document.querySelector('[data-fac-discount]');
    var blocks    = document.querySelectorAll('[data-origin]');

    function fmt(n) {
        return CURRENCY + ' ' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc() {
        var sub  = parseFloat(subInput.value) || 0;
        var disc = parseFloat(discInput.value) || 0;
        if (disc > sub) disc = sub;
        var base = Math.max(0, sub - disc);
        var tax  = Math.round(base * (TAX / 100) * 100) / 100;
        var total = Math.round((base + tax) * 100) / 100;
        document.querySelector('[data-fac-base]').textContent  = fmt(base);
        document.querySelector('[data-fac-tax]').textContent   = fmt(tax);
        document.querySelector('[data-fac-total]').textContent = fmt(total);
    }

    function toggleOrigin() {
        var t = typeSel.value;
        blocks.forEach(function (b) {
            b.classList.toggle('hidden', b.getAttribute('data-origin') !== t);
        });
    }

    function applyOrigin(map, sel) {
        var id = sel.value;
        if (!id || !map[id]) return;
        var data = map[id];
        if (data.customer_id) custSel.value = String(data.customer_id);
        if (data.subtotal != null) subInput.value = data.subtotal;
        if (data.concept) concept.value = data.concept;
        recalc();
    }

    typeSel.addEventListener('change', toggleOrigin);
    if (rentalSel) rentalSel.addEventListener('change', function () { applyOrigin(RENTALS, rentalSel); });
    if (saleSel)   saleSel.addEventListener('change', function () { applyOrigin(SALES, saleSel); });
    subInput.addEventListener('input', recalc);
    discInput.addEventListener('input', recalc);

    toggleOrigin();
    recalc();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
