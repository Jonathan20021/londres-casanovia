<?php
/**
 * Registrar pago (recibo) — LONDRES Casa de Novias
 *
 * Acepta por GET para precargar:
 *   ?rental=ID   ?invoice=ID   ?sale=ID
 * Muestra el saldo pendiente del documento y sugiere el monto.
 *
 * POST: valida amount>0 (sin exceder saldo), genera payment_number,
 * inserta el pago (received_by = usuario actual) y recalcula:
 *   - rental_id  -> recalc_rental_payment() + actualizar factura asociada
 *   - sale_id    -> actualizar sales.payment_status
 *   - invoice    -> recalcular paid_amount/balance/status
 *
 * Permiso: payments.manage · Sidebar: pagos
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('payments.manage');

$METHOD_LABELS = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

/* ------------------------------------------------------------------ *
 *  Carga del contexto (alquiler / venta / factura) para precarga
 * ------------------------------------------------------------------ */
$rentalId  = (int) get_param('rental', '0');
$saleId    = (int) get_param('sale', '0');
$invoiceId = (int) get_param('invoice', '0');

$rental = $sale = $invoice = $customer = null;

/* Si llega una factura, derivamos el alquiler/venta y el cliente desde ella */
if ($invoiceId > 0) {
    $invoice = db_one('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
    if ($invoice) {
        if (!$rentalId && !empty($invoice['rental_id'])) $rentalId = (int) $invoice['rental_id'];
        if (!$saleId   && !empty($invoice['sale_id']))   $saleId   = (int) $invoice['sale_id'];
    }
}

if ($rentalId > 0) {
    $rental = db_one(
        'SELECT r.*, c.full_name AS customer_name, c.id AS cust_id, p.name AS product_name
         FROM rentals r
         JOIN customers c ON c.id = r.customer_id
         JOIN products  p ON p.id = r.product_id
         WHERE r.id = :id',
        ['id' => $rentalId]
    );
    if (!$rental) $rentalId = 0;
}

if ($saleId > 0) {
    $sale = db_one(
        'SELECT s.*, c.full_name AS customer_name, c.id AS cust_id, p.name AS product_name
         FROM sales s
         JOIN customers c ON c.id = s.customer_id
         JOIN products  p ON p.id = s.product_id
         WHERE s.id = :id',
        ['id' => $saleId]
    );
    if (!$sale) $saleId = 0;
}

/* Determinar cliente y saldo pendiente según el contexto */
$customerId   = 0;
$customerName = '';
$pendingBalance = null;   // null = no se conoce un saldo concreto (pago libre)
$suggested      = 0.0;

if ($rental) {
    $customerId   = (int) $rental['cust_id'];
    $customerName = (string) $rental['customer_name'];
    $paid           = rental_paid_amount($rentalId);
    $pendingBalance = round((float) $rental['total_amount'] - $paid, 2);
    // Sugerencia: si no hay nada pagado, el inicial requerido (50%); si no, el saldo
    if ($paid <= 0 && (float) $rental['initial_payment_required'] > 0) {
        $suggested = min((float) $rental['initial_payment_required'], $pendingBalance);
    } else {
        $suggested = max(0, $pendingBalance);
    }
} elseif ($sale) {
    $customerId   = (int) $sale['cust_id'];
    $customerName = (string) $sale['customer_name'];
    $paid           = sale_paid_amount($saleId);
    $pendingBalance = round((float) $sale['total_amount'] - $paid, 2);
    $suggested      = max(0, $pendingBalance);
} elseif ($invoice) {
    $customerId   = (int) $invoice['customer_id'];
    $customerName = (string) db_value('SELECT full_name FROM customers WHERE id = :id', ['id' => $customerId]);
    $pendingBalance = round((float) $invoice['balance'], 2);
    $suggested      = max(0, $pendingBalance);
}

/* ------------------------------------------------------------------ *
 *  Procesar POST
 * ------------------------------------------------------------------ */
$errors = [];
if (is_post()) {
    require_csrf();

    // Re-leer contexto desde el POST (oculto) para no confiar en el GET
    $pRental  = (int) post('rental_id', 0);
    $pSale    = (int) post('sale_id', 0);
    $pInvoice = (int) post('invoice_id', 0);
    $pCustomer = (int) post('customer_id', 0);

    $amount = (float) str_replace([',', ' '], ['', ''], (string) post('amount', '0'));
    $methodVal = (string) post('payment_method', 'cash');
    $reference = trim((string) post('reference', ''));
    $notes     = trim((string) post('notes', ''));
    $paidAtRaw = trim((string) post('paid_at', ''));

    // Normalizar fecha/hora del pago
    $paidAt = date('Y-m-d H:i:s');
    if ($paidAtRaw !== '') {
        $ts = strtotime($paidAtRaw);
        if ($ts !== false) $paidAt = date('Y-m-d H:i:s', $ts);
    }

    // Validaciones
    if (!isset($METHOD_LABELS[$methodVal])) {
        $errors[] = 'Seleccione un método de pago válido.';
    }
    if ($amount <= 0) {
        $errors[] = 'El monto debe ser mayor a cero.';
    }
    if ($pCustomer <= 0) {
        $errors[] = 'No se pudo determinar el cliente del pago.';
    }

    // Recalcular saldo real en servidor para no exceder
    $serverBalance = null;
    $linkedInvoiceId = $pInvoice > 0 ? $pInvoice : null;

    if ($pRental > 0) {
        $rRow = db_one('SELECT total_amount FROM rentals WHERE id = :id', ['id' => $pRental]);
        if (!$rRow) {
            $errors[] = 'El alquiler asociado no existe.';
        } else {
            $serverBalance = round((float) $rRow['total_amount'] - rental_paid_amount($pRental), 2);
            // Si no se pasó factura explícita, buscar la del alquiler
            if (!$linkedInvoiceId) {
                $inv = db_value('SELECT id FROM invoices WHERE rental_id = :id ORDER BY id DESC LIMIT 1', ['id' => $pRental]);
                if ($inv) $linkedInvoiceId = (int) $inv;
            }
        }
    } elseif ($pSale > 0) {
        $sRow = db_one('SELECT total_amount FROM sales WHERE id = :id', ['id' => $pSale]);
        if (!$sRow) {
            $errors[] = 'La venta asociada no existe.';
        } else {
            $serverBalance = round((float) $sRow['total_amount'] - sale_paid_amount($pSale), 2);
            if (!$linkedInvoiceId) {
                $inv = db_value('SELECT id FROM invoices WHERE sale_id = :id ORDER BY id DESC LIMIT 1', ['id' => $pSale]);
                if ($inv) $linkedInvoiceId = (int) $inv;
            }
        }
    } elseif ($pInvoice > 0) {
        $iRow = db_one('SELECT balance FROM invoices WHERE id = :id', ['id' => $pInvoice]);
        if (!$iRow) {
            $errors[] = 'La factura asociada no existe.';
        } else {
            $serverBalance = round((float) $iRow['balance'], 2);
        }
    }

    // No exceder el saldo pendiente (cuando hay un saldo definido)
    if (!$errors && $serverBalance !== null && $amount > $serverBalance + 0.009) {
        $errors[] = 'El monto (' . money($amount) . ') excede el saldo pendiente (' . money(max(0, $serverBalance)) . ').';
    }

    if (!$errors) {
        // Generar número de recibo correlativo
        $paymentNumber = generate_number('payments', 'payment_number', 'REC');

        $paymentId = db_insert('payments', [
            'payment_number' => $paymentNumber,
            'customer_id'    => $pCustomer ?: null,
            'rental_id'      => $pRental > 0 ? $pRental : null,
            'sale_id'        => $pSale > 0 ? $pSale : null,
            'invoice_id'     => $linkedInvoiceId,
            'amount'         => $amount,
            'payment_method' => $methodVal,
            'reference'      => $reference !== '' ? $reference : null,
            'notes'          => $notes !== '' ? $notes : null,
            'received_by'    => current_user()['id'] ?? null,
            'paid_at'        => $paidAt,
        ]);

        // --- Recalcular según el origen ---
        if ($pRental > 0) {
            recalc_rental_payment($pRental);
        }

        if ($pSale > 0) {
            $sTotal = (float) db_value('SELECT total_amount FROM sales WHERE id = :id', ['id' => $pSale]);
            $sPaid  = sale_paid_amount($pSale);
            if ($sPaid <= 0)                       $sStatus = 'pending';
            elseif (round($sTotal - $sPaid, 2) > 0.009) $sStatus = 'partial';
            else                                   $sStatus = 'paid';
            db_update('sales', ['payment_status' => $sStatus], 'id = :id', ['id' => $pSale]);
        }

        // Actualizar la factura asociada (paid_amount = SUM de pagos de esa factura)
        if ($linkedInvoiceId) {
            $iRow = db_one('SELECT total, status FROM invoices WHERE id = :id', ['id' => $linkedInvoiceId]);
            if ($iRow && $iRow['status'] !== 'void') {
                $iTotal = (float) $iRow['total'];
                $iPaid  = (float) db_value('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = :id', ['id' => $linkedInvoiceId]);
                $iBalance = round($iTotal - $iPaid, 2);
                if ($iPaid <= 0)            $iStatus = 'pending';
                elseif ($iBalance > 0.009) $iStatus = 'partial';
                else                       $iStatus = 'paid';
                db_update('invoices', [
                    'paid_amount' => $iPaid,
                    'balance'     => max(0, $iBalance),
                    'status'      => $iStatus,
                ], 'id = :id', ['id' => $linkedInvoiceId]);
            }
        }

        log_activity(
            'payment.create',
            'payment',
            $paymentId,
            'Pago ' . $paymentNumber . ' por ' . money($amount)
        );

        flash('success', 'Pago ' . $paymentNumber . ' registrado correctamente por ' . money($amount) . '.');
        redirect(admin_url('pagos/recibo.php?id=' . $paymentId));
    }

    // Si hubo errores, preservar valores para re-pintar el formulario
    $customerId   = $pCustomer;
    $customerName = (string) db_value('SELECT full_name FROM customers WHERE id = :id', ['id' => $pCustomer]) ?: $customerName;
    $rentalId  = $pRental;
    $saleId    = $pSale;
    $invoiceId = $pInvoice;
}

/* Valores por defecto para los campos del formulario */
$valAmount = is_post() ? (string) post('amount', '') : ($suggested > 0 ? number_format($suggested, 2, '.', '') : '');
$valMethod = is_post() ? (string) post('payment_method', 'cash') : 'cash';
$valRef    = is_post() ? (string) post('reference', '') : '';
$valNotes  = is_post() ? (string) post('notes', '') : '';
$valPaidAt = is_post() ? (string) post('paid_at', '') : date('Y-m-d\TH:i');

$page_title    = 'Registrar pago';
$page_subtitle = 'Genere un recibo de cobro para un alquiler o una venta';
$active        = 'pagos';
$header_actions = '<a href="' . e(admin_url('pagos/index.php')) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver a pagos</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if ($errors): ?>
    <div class="mb-5 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
        <span class="mt-0.5 text-rose-500"><?= icon('warning', 'w-5 h-5') ?></span>
        <div class="flex-1">
            <p class="font-semibold">No se pudo registrar el pago</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Formulario -->
    <div class="lg:col-span-2">
        <form method="post" action="<?= e(admin_url('pagos/crear.php')) ?>" class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <?= csrf_field() ?>
            <input type="hidden" name="rental_id"   value="<?= (int) $rentalId ?>">
            <input type="hidden" name="sale_id"     value="<?= (int) $saleId ?>">
            <input type="hidden" name="invoice_id"  value="<?= (int) $invoiceId ?>">
            <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">

            <h2 class="font-serif text-lg font-bold text-gray-900">Datos del pago</h2>
            <p class="mt-1 text-sm text-gray-500">Complete la información del cobro recibido.</p>

            <!-- Cliente (auto) -->
            <div class="mt-5">
                <label class="lcn-label">Cliente</label>
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <?= avatar($customerName ?: 'Cliente', 'h-9 w-9 text-sm') ?>
                    <div class="min-w-0">
                        <?php if ($customerName !== ''): ?>
                            <p class="truncate font-medium text-gray-900"><?= e($customerName) ?></p>
                            <p class="text-xs text-gray-400">Tomado automáticamente del documento</p>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">Sin cliente asociado</p>
                            <p class="text-xs text-gray-400">Acceda desde un alquiler o una venta para cobrar</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <!-- Monto -->
                <div>
                    <label class="lcn-label" for="amount">Monto a cobrar <span class="text-brand-red">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" required
                           value="<?= e($valAmount) ?>"
                           placeholder="0.00" class="lcn-input">
                    <?php if ($pendingBalance !== null): ?>
                        <p class="mt-1 text-xs text-gray-400">Saldo pendiente: <span class="font-medium text-gray-600"><?= e(money(max(0, $pendingBalance))) ?></span></p>
                    <?php endif; ?>
                </div>

                <!-- Método de pago -->
                <div>
                    <label class="lcn-label" for="payment_method">Método de pago <span class="text-brand-red">*</span></label>
                    <select id="payment_method" name="payment_method" class="lcn-input">
                        <?php foreach ($METHOD_LABELS as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $valMethod === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Referencia -->
                <div>
                    <label class="lcn-label" for="reference">Referencia / N.º transacción</label>
                    <input type="text" id="reference" name="reference" maxlength="120"
                           value="<?= e($valRef) ?>" placeholder="Ej. transferencia #00123" class="lcn-input">
                </div>

                <!-- Fecha del pago -->
                <div>
                    <label class="lcn-label" for="paid_at">Fecha y hora del pago <span class="text-brand-red">*</span></label>
                    <input type="datetime-local" id="paid_at" name="paid_at" required
                           value="<?= e($valPaidAt) ?>" class="lcn-input">
                </div>
            </div>

            <!-- Notas -->
            <div class="mt-4">
                <label class="lcn-label" for="notes">Notas (opcional)</label>
                <textarea id="notes" name="notes" rows="3" maxlength="255"
                          placeholder="Observaciones del cobro…" class="lcn-input"><?= e($valNotes) ?></textarea>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Registrar pago
                </button>
                <a href="<?= e(admin_url('pagos/index.php')) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    <!-- Resumen lateral del documento -->
    <aside class="space-y-4">
        <?php if ($rental): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <div class="flex items-center gap-2 text-brand-red"><?= icon('box', 'w-5 h-5') ?>
                    <span class="text-xs font-semibold uppercase tracking-wide">Alquiler</span>
                </div>
                <h3 class="mt-2 font-serif text-lg font-bold text-gray-900"><?= e($rental['rental_number']) ?></h3>
                <p class="text-sm text-gray-500"><?= e($rental['product_name']) ?></p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Entrega</dt><dd class="text-gray-700"><?= e(format_date($rental['delivery_date'])) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Devolución</dt><dd class="text-gray-700"><?= e(format_date($rental['return_date'])) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-medium text-gray-900"><?= e(money($rental['total_amount'])) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Pagado</dt><dd class="text-gray-700"><?= e(money(rental_paid_amount($rentalId))) ?></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2"><dt class="font-medium text-gray-700">Saldo pendiente</dt><dd class="font-semibold text-brand-red"><?= e(money(max(0, (float) $pendingBalance))) ?></dd></div>
                    <div class="flex justify-between pt-1"><dt class="text-gray-500">Estado de pago</dt><dd><?= status_badge($rental['payment_status'], 'payment') ?></dd></div>
                </dl>
            </div>
        <?php elseif ($sale): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <div class="flex items-center gap-2 text-brand-red"><?= icon('bag', 'w-5 h-5') ?>
                    <span class="text-xs font-semibold uppercase tracking-wide">Venta</span>
                </div>
                <h3 class="mt-2 font-serif text-lg font-bold text-gray-900"><?= e($sale['sale_number']) ?></h3>
                <p class="text-sm text-gray-500"><?= e($sale['product_name']) ?></p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-medium text-gray-900"><?= e(money($sale['total_amount'])) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Pagado</dt><dd class="text-gray-700"><?= e(money(sale_paid_amount($saleId))) ?></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2"><dt class="font-medium text-gray-700">Saldo pendiente</dt><dd class="font-semibold text-brand-red"><?= e(money(max(0, (float) $pendingBalance))) ?></dd></div>
                    <div class="flex justify-between pt-1"><dt class="text-gray-500">Estado de pago</dt><dd><?= status_badge($sale['payment_status'], 'payment') ?></dd></div>
                </dl>
            </div>
        <?php elseif ($invoice): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <div class="flex items-center gap-2 text-brand-red"><?= icon('document', 'w-5 h-5') ?>
                    <span class="text-xs font-semibold uppercase tracking-wide">Factura</span>
                </div>
                <h3 class="mt-2 font-serif text-lg font-bold text-gray-900"><?= e($invoice['invoice_number']) ?></h3>
                <?php if (!empty($invoice['concept'])): ?><p class="text-sm text-gray-500"><?= e($invoice['concept']) ?></p><?php endif; ?>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-medium text-gray-900"><?= e(money($invoice['total'])) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Pagado</dt><dd class="text-gray-700"><?= e(money($invoice['paid_amount'])) ?></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2"><dt class="font-medium text-gray-700">Saldo pendiente</dt><dd class="font-semibold text-brand-red"><?= e(money(max(0, (float) $pendingBalance))) ?></dd></div>
                    <div class="flex justify-between pt-1"><dt class="text-gray-500">Estado</dt><dd><?= status_badge($invoice['status'], 'invoice') ?></dd></div>
                </dl>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-center shadow-soft">
                <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-gray-400 shadow-soft"><?= icon('document', 'w-6 h-6') ?></span>
                <h3 class="text-sm font-semibold text-gray-900">Sin documento asociado</h3>
                <p class="mt-1 text-sm text-gray-500">Para precargar el saldo, abra esta pantalla desde un alquiler, venta o factura.</p>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="<?= e(admin_url('alquileres/index.php')) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('box', 'w-4 h-4') ?> Ir a alquileres</a>
                    <a href="<?= e(admin_url('ventas/index.php')) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('bag', 'w-4 h-4') ?> Ir a ventas</a>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
