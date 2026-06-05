<?php
/**
 * Alquileres · Editar
 * LONDRES Casa de Novias
 *
 * Permite cambiar fechas, precios y estado de un alquiler.
 *  - Si cambian las fechas se re-valida disponibilidad excluyendo el propio id.
 *  - Para pasar a 'delivered' se exige saldo <= 0, salvo autorización expresa.
 *  - El estado sincroniza el commercial_status del producto.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$user = current_user();

$id = (int) get_param('id', '0');
$rental = $id > 0 ? db_one(
    "SELECT r.*, p.name AS product_name, c.full_name AS customer_name
       FROM rentals r
       JOIN products  p ON p.id = r.product_id
       JOIN customers c ON c.id = r.customer_id
      WHERE r.id = :id",
    ['id' => $id]
) : null;

if (!$rental) {
    flash('error', 'El alquiler solicitado no existe.');
    redirect(admin_url('alquileres/index.php'));
}

// Estados a los que se puede transicionar desde la edición
$statusOptions = ['reserved','confirmed','delivered','pending_return','returned','cancelled'];

$errors   = [];
$conflict = null;
$form = [
    'event_date'    => $rental['event_date'] ?? '',
    'delivery_date' => $rental['delivery_date'],
    'return_date'   => $rental['return_date'],
    'rental_price'  => $rental['rental_price'],
    'discount'      => $rental['discount'],
    'late_penalty'  => $rental['late_penalty'],
    'rental_status' => $rental['rental_status'],
    'authorized_delivery_without_full_payment' => (int) $rental['authorized_delivery_without_full_payment'],
    'authorization_reason' => $rental['authorization_reason'] ?? '',
];

if (is_post()) {
    require_csrf();

    $form = [
        'event_date'    => trim((string) post('event_date', '')),
        'delivery_date' => trim((string) post('delivery_date', '')),
        'return_date'   => trim((string) post('return_date', '')),
        'rental_price'  => (float) post('rental_price', 0),
        'discount'      => (float) post('discount', 0),
        'late_penalty'  => (float) post('late_penalty', 0),
        'rental_status' => (string) post('rental_status', $rental['rental_status']),
        'authorized_delivery_without_full_payment' => (int) (post('authorized_delivery_without_full_payment') ? 1 : 0),
        'authorization_reason' => trim((string) post('authorization_reason', '')),
    ];

    // --- Validaciones básicas ---
    if ($form['delivery_date'] === '') $errors[] = 'Indique la fecha de entrega.';
    if ($form['return_date'] === '')   $errors[] = 'Indique la fecha de devolución.';
    if ($form['delivery_date'] !== '' && $form['return_date'] !== ''
        && strtotime($form['return_date']) < strtotime($form['delivery_date'])) {
        $errors[] = 'La fecha de devolución no puede ser anterior a la de entrega.';
    }
    if ($form['rental_price'] <= 0) $errors[] = 'El precio de alquiler debe ser mayor que cero.';
    if ($form['discount'] < 0 || $form['late_penalty'] < 0) $errors[] = 'Los montos no pueden ser negativos.';
    if (!in_array($form['rental_status'], $statusOptions, true)) {
        $errors[] = 'Estado de alquiler inválido.';
    }

    // --- Recalcular totales (necesario para la regla de entrega) ---
    $total = round($form['rental_price'] - $form['discount'] + $form['late_penalty'], 2);
    if ($total < 0) $total = 0.0;
    $paid    = rental_paid_amount($id);
    $balance = round($total - $paid, 2);

    // --- Regla de negocio: entrega sin pago total ---
    if ($form['rental_status'] === 'delivered' && $balance > 0.009) {
        if ($form['authorized_delivery_without_full_payment'] !== 1) {
            $errors[] = 'No puede marcar como ENTREGADO con saldo pendiente. Autorice la entrega o registre el pago.';
        } elseif ($form['authorization_reason'] === '') {
            $errors[] = 'Indique el motivo de la autorización para entregar sin el pago completo.';
        }
    } else {
        // Si no aplica, limpiamos la autorización para no dejar datos colgados
        $form['authorized_delivery_without_full_payment'] = 0;
        $form['authorization_reason'] = '';
    }

    // --- Re-validación de disponibilidad si el estado bloquea y las fechas/estado cambiaron ---
    $datesChanged = $form['delivery_date'] !== $rental['delivery_date']
                 || $form['return_date']   !== $rental['return_date'];
    $blocking = in_array($form['rental_status'], RENTAL_BLOCKING_STATUSES, true);

    if (!$errors && $blocking && $datesChanged) {
        $check = checkProductAvailability(
            (int) $rental['product_id'],
            $form['delivery_date'],
            $form['return_date'],
            $id // excluir el propio alquiler
        );
        if (!empty($check['error'])) {
            $errors[] = $check['error'];
        } elseif (!$check['available']) {
            $conflict = $check['conflict'];
            flash('error', 'El producto no está disponible para las nuevas fechas. Revise el conflicto indicado.');
        }
    }

    // --- Persistir ---
    if (!$errors && !$conflict) {
        // Recalcular estructura de pago / estado de pago
        if ($paid <= 0)             $paymentStatus = 'pending';
        elseif ($balance > 0.009)   $paymentStatus = 'partial';
        else                        $paymentStatus = 'paid';

        $calc = calculateRentalPayments($total);

        db_update('rentals', [
            'event_date'    => $form['event_date'] !== '' ? $form['event_date'] : null,
            'delivery_date' => $form['delivery_date'],
            'return_date'   => $form['return_date'],
            'rental_price'  => $form['rental_price'],
            'discount'      => $form['discount'],
            'late_penalty'  => $form['late_penalty'],
            'total_amount'  => $total,
            'initial_payment_required' => $calc['initial'],
            'remaining_balance' => max(0, $balance),
            'payment_status'    => $paymentStatus,
            'rental_status'     => $form['rental_status'],
            'authorized_delivery_without_full_payment' => $form['authorized_delivery_without_full_payment'],
            'authorization_reason' => $form['authorization_reason'] !== '' ? $form['authorization_reason'] : null,
        ], 'id = :id', ['id' => $id]);

        // Sincronizar commercial_status del producto según el nuevo estado del alquiler
        $commercialMap = [
            'reserved'       => 'reserved',
            'confirmed'      => 'reserved',
            'delivered'      => 'rented',
            'pending_return' => 'rented',
            'returned'       => 'available',
            'cancelled'      => 'available',
        ];
        if (isset($commercialMap[$form['rental_status']])) {
            db_update('products', ['commercial_status' => $commercialMap[$form['rental_status']]],
                'id = :id', ['id' => $rental['product_id']]);
        }

        // Sincronizar la factura asociada (totales y saldo)
        $invoice = db_one("SELECT * FROM invoices WHERE rental_id = :rid AND status <> 'void' ORDER BY id DESC LIMIT 1",
            ['rid' => $id]);
        if ($invoice) {
            if ($paid <= 0)            $invStatus = 'pending';
            elseif ($balance > 0.009)  $invStatus = 'partial';
            else                       $invStatus = 'paid';
            db_update('invoices', [
                'subtotal'    => $total,
                'discount'    => $form['discount'],
                'total'       => $total,
                'paid_amount' => $paid,
                'balance'     => max(0, $balance),
                'status'      => $invStatus,
            ], 'id = :id', ['id' => $invoice['id']]);
        }

        log_activity('rental.update', 'rental', $id,
            'Alquiler ' . $rental['rental_number'] . ' actualizado (estado: ' . $form['rental_status'] . ')');

        flash('success', 'Alquiler actualizado correctamente.');
        redirect(admin_url('alquileres/ver.php?id=' . $id));
    }
}

// Saldo de referencia para el formulario (estado actual)
$currentPaid    = rental_paid_amount($id);
$currentBalance = round((float) $rental['total_amount'] - $currentPaid, 2);

$page_title    = 'Editar alquiler ' . $rental['rental_number'];
$page_subtitle = $rental['customer_name'] . ' · ' . $rental['product_name'];
$active        = 'alquileres';
$header_actions = '<a href="' . admin_url('alquileres/ver.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver al detalle</a>';

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

<form method="post" action="" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?= csrf_field() ?>

    <div class="space-y-6 lg:col-span-2">
        <!-- Datos fijos (solo lectura) -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-serif text-lg font-bold text-gray-900">Alquiler <?= e($rental['rental_number']) ?></h2>
                <div class="flex items-center gap-2">
                    <?= status_badge($rental['rental_status'], 'rental') ?>
                    <?= status_badge($rental['payment_status'], 'payment') ?>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Cliente</p><p class="font-medium text-gray-900"><?= e($rental['customer_name']) ?></p></div>
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Producto</p><p class="font-medium text-gray-900"><?= e($rental['product_name']) ?></p></div>
            </div>
            <p class="mt-3 text-xs text-gray-400">El cliente y el producto no se modifican en la edición. Para cambiarlos, cancele este alquiler y cree uno nuevo.</p>
        </div>

        <!-- Fechas -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Fechas</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lcn-label" for="event_date">Fecha del evento</label>
                    <input type="date" id="event_date" name="event_date" value="<?= e((string) $form['event_date']) ?>" class="lcn-input">
                </div>
                <div>
                    <label class="lcn-label" for="delivery_date">Entrega</label>
                    <input type="date" id="delivery_date" name="delivery_date" value="<?= e((string) $form['delivery_date']) ?>" class="lcn-input" required>
                </div>
                <div>
                    <label class="lcn-label" for="return_date">Devolución</label>
                    <input type="date" id="return_date" name="return_date" value="<?= e((string) $form['return_date']) ?>" class="lcn-input" required>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">Si cambias las fechas, verifica disponibilidad (excluye este alquiler).</p>
                    <button type="button" data-check-availability
                            data-product="#productHidden" data-delivery="#delivery_date"
                            data-return="#return_date" data-exclude="<?= (int) $id ?>" data-result="#boxDisp"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('check', 'w-4 h-4') ?> Verificar disponibilidad
                    </button>
                </div>
                <div id="boxDisp" class="mt-3"></div>
                <!-- producto fijo para el verificador -->
                <input type="hidden" id="productHidden" value="<?= (int) $rental['product_id'] ?>">
            </div>
        </div>

        <!-- Precios -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Precios</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lcn-label" for="rental_price">Precio de alquiler</label>
                    <input type="number" step="0.01" min="0" id="rental_price" name="rental_price" value="<?= e((string) $form['rental_price']) ?>" class="lcn-input" required>
                </div>
                <div>
                    <label class="lcn-label" for="discount">Descuento</label>
                    <input type="number" step="0.01" min="0" id="discount" name="discount" value="<?= e((string) $form['discount']) ?>" class="lcn-input">
                </div>
                <div>
                    <label class="lcn-label" for="late_penalty">Penalidad por mora</label>
                    <input type="number" step="0.01" min="0" id="late_penalty" name="late_penalty" value="<?= e((string) $form['late_penalty']) ?>" class="lcn-input">
                </div>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Total actual</p>
                    <p id="totBox" class="mt-1 text-lg font-semibold text-gray-900"><?= e(money($rental['total_amount'])) ?></p>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Pagado</p>
                    <p class="mt-1 text-lg font-semibold text-emerald-600"><?= e(money($currentPaid)) ?></p>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Saldo</p>
                    <p class="mt-1 text-lg font-semibold <?= $currentBalance > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money(max(0, $currentBalance))) ?></p>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400">El total se calcula como precio − descuento + penalidad por mora.</p>
        </div>
    </div>

    <!-- Lateral: estado + autorización -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Estado del alquiler</h2>
            <div class="mt-4">
                <label class="lcn-label" for="rental_status">Estado</label>
                <select id="rental_status" name="rental_status" class="lcn-input">
                    <?php foreach ($statusOptions as $s): ?>
                        <option value="<?= e($s) ?>" <?= $form['rental_status'] === $s ? 'selected' : '' ?>>
                            <?= e(strip_tags(status_badge($s, 'rental'))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Autorización de entrega sin pago total -->
            <div id="authBox" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 <?= $form['rental_status'] === 'delivered' && $currentBalance > 0 ? '' : 'hidden' ?>">
                <label class="flex items-start gap-2.5 text-sm text-amber-900">
                    <input type="checkbox" name="authorized_delivery_without_full_payment" value="1"
                           class="mt-0.5 h-4 w-4 rounded border-amber-300 text-brand-red focus:ring-brand-red"
                           <?= (int) $form['authorized_delivery_without_full_payment'] === 1 ? 'checked' : '' ?>>
                    <span><strong>Autorizar entrega con saldo pendiente.</strong> Requerido si entrega sin el pago completo.</span>
                </label>
                <div class="mt-3">
                    <label class="lcn-label" for="authorization_reason">Motivo de la autorización</label>
                    <textarea id="authorization_reason" name="authorization_reason" rows="2" class="lcn-input" placeholder="Ej.: cliente frecuente, abono pendiente para el día del evento…"><?= e((string) $form['authorization_reason']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Guardar cambios
            </button>
            <a href="<?= admin_url('alquileres/ver.php?id=' . $id) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
        </div>
    </div>
</form>

<script>
/* Muestra el bloque de autorización solo cuando el estado es "delivered" y
   hay saldo pendiente. Recalcula el total mostrado en vivo. */
(function () {
    var status = document.getElementById('rental_status');
    var authBox = document.getElementById('authBox');
    var balance = <?= json_encode((float) max(0, $currentBalance)) ?>;
    var price = document.getElementById('rental_price');
    var disc  = document.getElementById('discount');
    var pen   = document.getElementById('late_penalty');
    var totBox = document.getElementById('totBox');
    var currency = <?= json_encode((string) setting('currency', 'RD$')) ?>;

    function num(v){ var n = parseFloat(v); return isNaN(n) ? 0 : n; }

    function toggleAuth() {
        var show = status.value === 'delivered' && balance > 0;
        authBox.classList.toggle('hidden', !show);
    }
    function recalc() {
        var t = num(price.value) - num(disc.value) + num(pen.value);
        if (t < 0) t = 0;
        totBox.textContent = currency + ' ' + t.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    status.addEventListener('change', toggleAuth);
    [price, disc, pen].forEach(function (el) { el.addEventListener('input', recalc); });
    toggleAuth();
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
