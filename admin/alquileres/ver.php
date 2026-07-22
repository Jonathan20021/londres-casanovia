<?php
/**
 * Alquileres · Detalle
 * LONDRES Casa de Novias
 *
 * Vista completa de un alquiler: datos, cliente, producto, línea de tiempo
 * de estados, desglose de pagos, evidencias y acciones.
 * Los cambios (cambiar estado, subir evidencia) se realizan SIEMPRE vía POST
 * con token CSRF.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$user = current_user();

$id = (int) get_param('id', '0');

/* ------------------------------------------------------------------ *
 *  Manejo POST (cambio de estado / evidencia)
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();
    $action = (string) post('action', '');

    // Cargar alquiler objetivo (con bloqueo lógico: debe existir)
    $r = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $id]);
    if (!$r) {
        flash('error', 'El alquiler no existe.');
        redirect(admin_url('alquileres/index.php'));
    }

    /* --- Cambiar estado del alquiler --- */
    if ($action === 'status') {
        $new = (string) post('rental_status', '');
        $allowed = ['reserved','confirmed','delivered','pending_return','returned','cancelled'];

        if (!in_array($new, $allowed, true)) {
            flash('error', 'Estado inválido.');
            redirect(admin_url('alquileres/ver.php?id=' . $id));
        }

        // Regla: no entregar con saldo pendiente salvo autorización
        $paid    = rental_paid_amount($id);
        $balance = round((float) $r['total_amount'] - $paid, 2);
        $authorized = (int) $r['authorized_delivery_without_full_payment'] === 1;

        if ($new === 'delivered' && $balance > 0.009 && !$authorized) {
            flash('error', 'No se puede entregar con saldo pendiente. Registre el pago o autorice la entrega desde Editar.');
            redirect(admin_url('alquileres/ver.php?id=' . $id));
        }

        // Si las fechas siguen bloqueando, re-validar todas las piezas (excluyendo este alquiler)
        if (in_array($new, RENTAL_BLOCKING_STATUSES, true)) {
            foreach (rental_product_ids($id) as $productId) {
                $check = checkProductAvailability($productId, $r['delivery_date'], $r['return_date'], $id);
                if (!$check['available']) {
                    flash('error', 'No se puede aplicar el estado: una de las piezas tiene un conflicto de fechas con otro alquiler.');
                    redirect(admin_url('alquileres/ver.php?id=' . $id));
                }
            }
        }

        db_update('rentals', ['rental_status' => $new], 'id = :id', ['id' => $id]);

        // Sincronizar commercial_status del producto
        $commercialMap = [
            'reserved'       => 'reserved',
            'confirmed'      => 'reserved',
            'delivered'      => 'rented',
            'pending_return' => 'rented',
            'returned'       => 'available',
            'cancelled'      => 'available',
        ];
        if (isset($commercialMap[$new])) {
            sync_rental_products_status($id, $commercialMap[$new]);
        }

        log_activity('rental.status', 'rental', $id, 'Estado de ' . $r['rental_number'] . ' → ' . $new);
        flash('success', 'Estado actualizado correctamente.');
        redirect(admin_url('alquileres/ver.php?id=' . $id));
    }

    /* --- Subir evidencia (entrega/devolución) --- */
    if ($action === 'evidence') {
        $type  = post('evidence_type') === 'return' ? 'return' : 'delivery';
        $notes = trim((string) post('evidence_notes', ''));

        if (empty($_FILES['evidence']['name'])) {
            flash('error', 'Seleccione al menos una imagen de evidencia.');
            redirect(admin_url('alquileres/ver.php?id=' . $id));
        }

        // Subida múltiple a /uploads/rentals
        $paths = upload_images($_FILES['evidence'], 'rentals');
        if (!$paths) {
            flash('error', 'No se pudo subir la evidencia. Verifique el formato (JPG, PNG, WEBP) y el tamaño (máx. 5 MB).');
            redirect(admin_url('alquileres/ver.php?id=' . $id));
        }
        foreach ($paths as $path) {
            db_insert('rental_evidence', [
                'rental_id'  => $id,
                'type'       => $type,
                'image_path' => $path,
                'notes'      => $notes !== '' ? $notes : null,
            ]);
        }
        log_activity('rental.evidence', 'rental', $id,
            count($paths) . ' evidencia(s) de ' . $type . ' en ' . $r['rental_number']);
        flash('success', count($paths) . ' imagen(es) de evidencia agregada(s).');
        redirect(admin_url('alquileres/ver.php?id=' . $id));
    }

    // Acción no reconocida
    flash('error', 'Acción no válida.');
    redirect(admin_url('alquileres/ver.php?id=' . $id));
}

/* ------------------------------------------------------------------ *
 *  Cargar datos para mostrar
 * ------------------------------------------------------------------ */
$rental = $id > 0 ? db_one(
    "SELECT r.*,
            c.full_name AS customer_name, c.phone AS customer_phone, c.whatsapp AS customer_whatsapp,
            c.email AS customer_email, c.document_number AS customer_document, c.address AS customer_address,
            p.name AS product_name, p.sku AS product_sku, p.size AS product_size, p.color AS product_color,
            p.main_image AS product_image, p.slug AS product_slug, p.commercial_status AS product_status,
            cat.name AS category_name,
            uc.name AS created_by_name
       FROM rentals r
       JOIN customers c   ON c.id = r.customer_id
       JOIN products  p   ON p.id = r.product_id
       LEFT JOIN categories cat ON cat.id = p.category_id
       LEFT JOIN users uc ON uc.id = r.created_by
      WHERE r.id = :id",
    ['id' => $id]
) : null;

if (!$rental) {
    flash('error', 'El alquiler solicitado no existe.');
    redirect(admin_url('alquileres/index.php'));
}

$rentalItems = rental_items_details($id);

$paid    = rental_paid_amount($id);
$balance = round((float) $rental['total_amount'] - $paid, 2);
$today   = date('Y-m-d');
$isOverdue = in_array($rental['rental_status'], ['delivered','pending_return'], true) && $rental['return_date'] < $today;

// Pagos del alquiler
$payments = db_all(
    "SELECT pay.*, u.name AS received_by_name
       FROM payments pay
       LEFT JOIN users u ON u.id = pay.received_by
      WHERE pay.rental_id = :id
      ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC, pay.id DESC",
    ['id' => $id]
);

// Factura asociada
$invoice = db_one("SELECT * FROM invoices WHERE rental_id = :id AND status <> 'void' ORDER BY id DESC LIMIT 1", ['id' => $id]);

// Evidencias
$evidence = db_all("SELECT * FROM rental_evidence WHERE rental_id = :id ORDER BY created_at ASC, id ASC", ['id' => $id]);

// Métodos de pago para mostrar etiquetas
$payMethods = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

// WhatsApp del cliente: solo dígitos
$waDigits = preg_replace('/\D+/', '', (string) ($rental['customer_whatsapp'] ?: $rental['customer_phone']));
$waMsg = 'Hola ' . $rental['customer_name'] . ', le escribimos de '
    . setting('business_name', APP_NAME) . ' sobre su alquiler ' . $rental['rental_number'] . '.';
$waLink = $waDigits ? 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waMsg) : '';

/* Línea de tiempo de estados (orden lógico) */
$timeline = [
    ['reserved',       'Reservado',            'calendar'],
    ['confirmed',      'Confirmado',           'check'],
    ['delivered',      'Entregado',            'truck'],
    ['pending_return', 'Pendiente devolución', 'clock'],
    ['returned',       'Devuelto',             'return'],
];
$orderIndex = ['reserved' => 0, 'confirmed' => 1, 'delivered' => 2, 'pending_return' => 3, 'returned' => 4];
$currentIdx = $orderIndex[$rental['rental_status']] ?? -1;
$isCancelled = $rental['rental_status'] === 'cancelled';

$page_title    = 'Alquiler ' . $rental['rental_number'];
$page_subtitle = $rental['customer_name'] . ' · ' . count($rentalItems) . (count($rentalItems) === 1 ? ' producto' : ' productos');
$active        = 'alquileres';

$header_actions  = '<a href="' . admin_url('alquileres/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';
$header_actions .= '<a href="' . admin_url('alquileres/editar.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('pencil', 'w-4 h-4') . ' Editar</a>';
$header_actions .= '<a href="' . admin_url('alquileres/contrato.php?id=' . $id) . '" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('printer', 'w-4 h-4') . ' Contrato</a>';
$header_actions .= '<a href="' . admin_url('pagos/crear.php?rental=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('banknotes', 'w-4 h-4') . ' Registrar pago</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Alertas -->
<?php if ($balance > 0): ?>
    <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-soft">
        <span class="mt-0.5 text-amber-500"><?= icon('warning', 'w-5 h-5') ?></span>
        <div>Saldo pendiente de <strong><?= e(money($balance)) ?></strong>.
            <a href="<?= admin_url('pagos/crear.php?rental=' . $id) ?>" class="font-medium underline">Registrar pago</a>.</div>
    </div>
<?php endif; ?>
<?php if ($isOverdue): ?>
    <div class="mb-4 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
        <span class="mt-0.5 text-rose-500"><?= icon('clock', 'w-5 h-5') ?></span>
        <div>Devolución vencida desde <strong><?= format_date($rental['return_date']) ?></strong>
            (<?= abs(days_between($rental['return_date'], $today)) ?> día(s)). Considere aplicar penalidad por mora.</div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Columna principal -->
    <div class="space-y-6 lg:col-span-2">

        <!-- Encabezado del alquiler -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-serif text-2xl font-bold text-gray-900"><?= e($rental['rental_number']) ?></h2>
                    <p class="mt-1 text-sm text-gray-500">Creado por <?= e($rental['created_by_name'] ?? 'Sistema') ?> · <?= format_datetime($rental['created_at']) ?></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?= status_badge($rental['rental_status'], 'rental') ?>
                    <?= status_badge($rental['payment_status'], 'payment') ?>
                </div>
            </div>

            <!-- Línea de tiempo de estados -->
            <?php if ($isCancelled): ?>
                <div class="mt-6 flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    <?= icon('x', 'w-5 h-5') ?> Este alquiler fue <strong>cancelado</strong>.
                </div>
            <?php else: ?>
                <div class="mt-6 grid grid-cols-5 gap-1">
                    <?php foreach ($timeline as $i => [$key, $label, $ic]):
                        $done    = $i <= $currentIdx;
                        $current = $i === $currentIdx; ?>
                        <div class="flex flex-col items-center text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full ring-2 transition
                                <?= $current ? 'bg-brand-red text-white ring-brand-red'
                                    : ($done ? 'bg-emerald-50 text-emerald-600 ring-emerald-200' : 'bg-gray-50 text-gray-300 ring-gray-200') ?>">
                                <?= icon($ic, 'w-5 h-5') ?>
                            </span>
                            <span class="mt-2 text-[11px] font-medium leading-tight <?= $done ? 'text-gray-700' : 'text-gray-400' ?>"><?= e($label) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Datos clave -->
            <div class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-5 text-sm sm:grid-cols-4">
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Evento</p><p class="mt-0.5 font-medium text-gray-900"><?= format_date($rental['event_date']) ?></p></div>
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Entrega</p><p class="mt-0.5 font-medium text-gray-900"><?= format_date($rental['delivery_date']) ?></p></div>
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Devolución</p><p class="mt-0.5 font-medium <?= $isOverdue ? 'text-rose-600' : 'text-gray-900' ?>"><?= format_date($rental['return_date']) ?></p></div>
                <div><p class="text-xs uppercase tracking-wide text-gray-400">Duración</p><p class="mt-0.5 font-medium text-gray-900"><?= e((string) max(0, days_between($rental['delivery_date'], $rental['return_date']))) ?> día(s)</p></div>
            </div>

            <?php if (!empty($rental['authorized_delivery_without_full_payment'])): ?>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <strong>Entrega autorizada con saldo pendiente.</strong>
                    <?php if (!empty($rental['authorization_reason'])): ?> Motivo: <?= e($rental['authorization_reason']) ?><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Productos -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-serif text-lg font-bold text-gray-900">Productos</h2>
                <span class="rounded-full bg-brand-cream px-3 py-1 text-xs font-semibold text-brand-red"><?= count($rentalItems) ?> pieza(s)</span>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php foreach ($rentalItems as $item): ?>
                    <div class="flex gap-3 rounded-2xl border border-gray-100 bg-gray-50/50 p-3">
                        <img src="<?= e(upload_url($item['main_image'] ?? null)) ?>" alt="<?= e($item['name']) ?>"
                             class="h-28 w-24 flex-none rounded-xl object-cover bg-white ring-1 ring-gray-100">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-serif text-base text-gray-900"><?= e($item['name']) ?></h3>
                                <?= status_badge($item['commercial_status'], 'commercial') ?>
                            </div>
                            <p class="text-xs text-brand-red"><?= e($item['category_name'] ?? 'General') ?></p>
                            <p class="mt-2 text-xs text-gray-500">
                                <?= e(implode(' · ', array_filter([
                                    $item['barcode'] ?? $item['sku'] ?? '',
                                    !empty($item['size']) ? 'Talla ' . $item['size'] : '',
                                    $item['color'] ?? '',
                                ]))) ?>
                            </p>
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-gray-900"><?= e(money($item['unit_price'])) ?></span>
                                <a href="<?= admin_url('productos/ver.php?id=' . (int) $item['id']) ?>" class="inline-flex items-center gap-1 text-xs font-medium text-brand-red hover:underline">
                                    <?= icon('eye', 'w-3.5 h-3.5') ?> Ver ficha
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pagos -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between">
                <h2 class="font-serif text-lg font-bold text-gray-900">Pagos</h2>
                <a href="<?= admin_url('pagos/crear.php?rental=' . $id) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-red-700"><?= icon('plus', 'w-4 h-4') ?> Pago</a>
            </div>

            <?php if (!$payments): ?>
                <div class="mt-4"><?= empty_state('Sin pagos registrados', 'Aún no se ha registrado ningún pago para este alquiler.', 'banknotes') ?></div>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibo</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Método</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($payments as $pay): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-4 py-3 font-medium text-gray-900"><?= e($pay['payment_number']) ?></td>
                                    <td class="px-4 py-3 text-gray-700"><?= format_date($pay['paid_at'] ?: $pay['created_at']) ?></td>
                                    <td class="px-4 py-3 text-gray-700"><?= e($payMethods[$pay['payment_method']] ?? $pay['payment_method']) ?></td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900"><?= e(money($pay['amount'])) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="<?= admin_url('pagos/recibo.php?id=' . (int) $pay['id']) ?>" target="_blank" title="Recibo" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-brand-red inline-flex"><?= icon('printer', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Desglose de saldo -->
            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs uppercase tracking-wide text-gray-400">Total</p><p class="mt-1 text-lg font-semibold text-gray-900"><?= e(money($rental['total_amount'])) ?></p></div>
                <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs uppercase tracking-wide text-gray-400">Abono requerido</p><p class="mt-1 text-lg font-semibold text-gray-900"><?= e(money($rental['initial_payment_required'])) ?></p></div>
                <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs uppercase tracking-wide text-gray-400">Pagado</p><p class="mt-1 text-lg font-semibold text-emerald-600"><?= e(money($paid)) ?></p></div>
                <div class="rounded-xl bg-gray-50 px-4 py-3"><p class="text-xs uppercase tracking-wide text-gray-400">Saldo</p><p class="mt-1 text-lg font-semibold <?= $balance > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money(max(0, $balance))) ?></p></div>
            </div>
        </div>

        <!-- Evidencias -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between">
                <h2 class="font-serif text-lg font-bold text-gray-900">Evidencias</h2>
                <button type="button" data-modal-open="modalEvidence" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('upload', 'w-4 h-4') ?> Subir</button>
            </div>
            <?php if (!$evidence): ?>
                <div class="mt-4"><?= empty_state('Sin evidencias', 'Sube fotos del estado del producto al entregar y al recibir.', 'photo') ?></div>
            <?php else: ?>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <?php foreach ($evidence as $ev): ?>
                        <a href="<?= e(upload_url($ev['image_path'])) ?>" target="_blank" class="group relative block overflow-hidden rounded-xl ring-1 ring-gray-100">
                            <img src="<?= e(upload_url($ev['image_path'])) ?>" alt="Evidencia" class="aspect-square w-full object-cover transition group-hover:scale-105">
                            <span class="absolute left-2 top-2 rounded-full px-2 py-0.5 text-[11px] font-medium <?= $ev['type'] === 'return' ? 'bg-emerald-600 text-white' : 'bg-brand-dark/80 text-white' ?>">
                                <?= $ev['type'] === 'return' ? 'Devolución' : 'Entrega' ?>
                            </span>
                            <?php if (!empty($ev['notes'])): ?>
                                <span class="absolute inset-x-0 bottom-0 truncate bg-brand-dark/70 px-2 py-1 text-[11px] text-white"><?= e($ev['notes']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Columna lateral -->
    <div class="space-y-6">
        <!-- Cliente -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Cliente</h2>
            <div class="mt-4 flex items-center gap-3">
                <?= avatar($rental['customer_name'], 'h-12 w-12 text-base') ?>
                <div class="min-w-0">
                    <a href="<?= admin_url('clientes/ver.php?id=' . (int) $rental['customer_id']) ?>" class="font-semibold text-gray-900 hover:text-brand-red"><?= e($rental['customer_name']) ?></a>
                    <?php if ($rental['customer_document']): ?><p class="text-xs text-gray-400"><?= e($rental['customer_document']) ?></p><?php endif; ?>
                </div>
            </div>
            <dl class="mt-4 space-y-2.5 text-sm">
                <?php if ($rental['customer_phone']): ?>
                    <div class="flex items-center gap-2 text-gray-600"><span class="text-gray-400"><?= icon('phone', 'w-4 h-4') ?></span><?= e($rental['customer_phone']) ?></div>
                <?php endif; ?>
                <?php if ($rental['customer_email']): ?>
                    <div class="flex items-center gap-2 text-gray-600"><span class="text-gray-400"><?= icon('mail', 'w-4 h-4') ?></span><span class="truncate"><?= e($rental['customer_email']) ?></span></div>
                <?php endif; ?>
                <?php if ($rental['customer_address']): ?>
                    <div class="flex items-start gap-2 text-gray-600"><span class="mt-0.5 text-gray-400"><?= icon('pin', 'w-4 h-4') ?></span><span><?= e($rental['customer_address']) ?></span></div>
                <?php endif; ?>
            </dl>
            <?php if ($waLink): ?>
                <a href="<?= e($waLink) ?>" target="_blank" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    <?= icon('whatsapp', 'w-4 h-4') ?> Escribir por WhatsApp
                </a>
            <?php endif; ?>
        </div>

        <!-- Factura -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Factura</h2>
            <?php if ($invoice): ?>
                <div class="mt-3 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900"><?= e($invoice['invoice_number']) ?></p>
                        <p class="text-xs text-gray-400"><?= e(money($invoice['total'])) ?> · saldo <?= e(money($invoice['balance'])) ?></p>
                    </div>
                    <?= status_badge($invoice['status'], 'invoice') ?>
                </div>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="<?= admin_url('facturas/ver.php?id=' . (int) $invoice['id']) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('document', 'w-4 h-4') ?> Ver factura</a>
                    <a href="<?= admin_url('facturas/imprimir.php?id=' . (int) $invoice['id']) ?>" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('printer', 'w-4 h-4') ?> Imprimir factura</a>
                </div>
            <?php else: ?>
                <p class="mt-3 text-sm text-gray-500">No hay factura activa asociada a este alquiler.</p>
                <a href="<?= admin_url('facturas/crear.php?rental=' . $id) ?>" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"><?= icon('plus', 'w-4 h-4') ?> Generar factura</a>
            <?php endif; ?>
        </div>

        <!-- Cambiar estado -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h2 class="font-serif text-lg font-bold text-gray-900">Cambiar estado</h2>
            <form method="post" action="" class="mt-4 space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <select name="rental_status" class="lcn-input">
                    <?php
                    $stateLabels = [
                        'reserved' => 'Reservado', 'confirmed' => 'Confirmado', 'delivered' => 'Entregado',
                        'pending_return' => 'Pendiente devolución', 'returned' => 'Devuelto', 'cancelled' => 'Cancelado',
                    ];
                    foreach ($stateLabels as $val => $lab): ?>
                        <option value="<?= e($val) ?>" <?= $rental['rental_status'] === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($balance > 0): ?>
                    <p class="text-xs text-amber-600">Hay saldo pendiente: no podrá entregar sin pago total a menos que la entrega esté autorizada desde Editar.</p>
                <?php endif; ?>
                <button type="submit" data-confirm="¿Aplicar el nuevo estado al alquiler?" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Aplicar estado
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal: subir evidencia -->
<div id="modalEvidence" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-dark/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-card animate-scale-in">
        <div class="flex items-center justify-between">
            <h3 class="font-serif text-lg font-bold text-gray-900">Subir evidencia</h3>
            <button type="button" data-modal-close class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100"><?= icon('x', 'w-5 h-5') ?></button>
        </div>
        <form method="post" action="" enctype="multipart/form-data" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="evidence">
            <div>
                <label class="lcn-label" for="evidence_type">Tipo de evidencia</label>
                <select id="evidence_type" name="evidence_type" class="lcn-input">
                    <option value="delivery">Entrega</option>
                    <option value="return">Devolución</option>
                </select>
            </div>
            <div data-dropzone data-dropzone-preview="#evPrev" class="dropzone flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/60 px-6 py-8 text-center transition hover:border-brand-red/40 hover:bg-red-50/30">
                <span class="mb-2 text-gray-400"><?= icon('upload', 'w-8 h-8') ?></span>
                <p class="text-sm font-medium text-gray-700">Arrastra imágenes o haz clic para seleccionar</p>
                <p class="mt-1 text-xs text-gray-400">JPG, PNG o WEBP · máx. 5 MB c/u</p>
                <input type="file" name="evidence[]" multiple accept="image/*" class="hidden">
            </div>
            <div id="evPrev" class="flex flex-wrap gap-3"></div>
            <div>
                <label class="lcn-label" for="evidence_notes">Notas (opcional)</label>
                <input type="text" id="evidence_notes" name="evidence_notes" class="lcn-input" placeholder="Ej.: vestido entregado en perfecto estado">
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-modal-close class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700"><?= icon('upload', 'w-4 h-4') ?> Subir evidencia</button>
            </div>
        </form>
    </div>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
