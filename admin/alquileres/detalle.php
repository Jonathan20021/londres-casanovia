<?php
/**
 * Detalle rápido de un alquiler (fragmento HTML para el modal del tablero).
 * LONDRES Casa de Novias
 *
 * Devuelve TODO lo del alquiler en una sola vista: cliente, fechas, piezas
 * (con su ficha y sus modificaciones), resumen económico, factura y pagos.
 *
 * admin/alquileres/detalle.php?id=ID  (N=2) · Permiso: rentals.manage
 * No imprime el layout: se inyecta dentro del modal con fetch().
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$id = (int) get_param('id', '0');

$rental = $id > 0 ? db_one(
    "SELECT r.*,
            c.id AS customer_id, c.full_name AS customer_name, c.document_number AS customer_document,
            c.phone AS customer_phone, c.whatsapp AS customer_whatsapp, c.email AS customer_email,
            c.address AS customer_address,
            uc.name AS created_by_name
       FROM rentals r
       JOIN customers c   ON c.id = r.customer_id
       LEFT JOIN users uc ON uc.id = r.created_by
      WHERE r.id = :id",
    ['id' => $id]
) : null;

if (!$rental) {
    http_response_code(404);
    echo '<div class="p-10 text-center">'
       . '<p class="font-serif text-lg text-gray-900">No encontramos este alquiler</p>'
       . '<p class="mt-1 text-sm text-gray-500">Puede que se haya eliminado. Actualice el tablero.</p>'
       . '</div>';
    exit;
}

$items    = rental_items_details($id);
$paid     = rental_paid_amount($id);
$balance  = round((float) $rental['total_amount'] - $paid, 2);
$invoice  = db_one("SELECT * FROM invoices WHERE rental_id = :id AND status <> 'void' ORDER BY id DESC LIMIT 1", ['id' => $id]);
$payments = db_all(
    "SELECT p.*, u.name AS received_by_name
       FROM payments p LEFT JOIN users u ON u.id = p.received_by
      WHERE p.rental_id = :id
      ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC",
    ['id' => $id]
);

$today     = date('Y-m-d');
$isOverdue = rental_applies_late_fee($rental['rental_status'])
          && $rental['rental_status'] !== 'returned'
          && $rental['return_date'] < $today;
$lateDays  = rental_late_days((string) $rental['return_date'], $rental['actual_return_date'] ?: null);
$latePend  = rental_late_penalty_for($rental['rental_status'], (string) $rental['return_date'], $rental['actual_return_date'] ?: null);

$pendAlter = array_values(array_filter($items, static fn(array $i): bool =>
    !empty($i['needs_alteration']) && $i['alteration_status'] === 'pending'));

$payMethods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card' => 'Tarjeta',
               'deposit' => 'Depósito', 'other' => 'Otro'];

$waDigits = preg_replace('/\D+/', '', (string) ($rental['customer_whatsapp'] ?: $rental['customer_phone']));
$waLink   = $waDigits
    ? 'https://wa.me/' . $waDigits . '?text=' . rawurlencode(
        'Hola ' . $rental['customer_name'] . ', le escribimos de ' . setting('business_name', APP_NAME)
        . ' sobre su alquiler ' . $rental['rental_number'] . '.')
    : '';

/** Fila de dato compacta. */
function lcn_dato(string $label, string $valor, string $extra = ''): string
{
    return '<div><dt class="text-[11px] font-medium uppercase tracking-wide text-gray-400">' . e($label) . '</dt>'
         . '<dd class="mt-0.5 text-sm font-medium text-gray-900 ' . $extra . '">' . $valor . '</dd></div>';
}
?>
<!-- ============ Encabezado ============ -->
<header class="flex flex-col gap-4 border-b border-gray-100 bg-gradient-to-br from-brand-cream via-white to-white px-6 py-5 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-serif text-2xl font-bold text-gray-900"><?= e($rental['rental_number']) ?></h2>
            <?= status_badge($rental['rental_status'], 'rental') ?>
            <?= status_badge($rental['payment_status'], 'payment') ?>
            <?php if ($isOverdue): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600">
                    <?= icon('clock', 'w-3.5 h-3.5') ?> Vencido
                </span>
            <?php endif; ?>
        </div>
        <p class="mt-1.5 text-sm text-gray-500">
            <?= count($items) ?> pieza<?= count($items) === 1 ? '' : 's' ?> ·
            Creado por <?= e($rental['created_by_name'] ?? 'Sistema') ?> · <?= e(format_datetime($rental['created_at'])) ?>
        </p>
    </div>
    <button type="button" data-modal-close aria-label="Cerrar"
            class="self-start rounded-xl p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
        <?= icon('x', 'w-5 h-5') ?>
    </button>
</header>

<!-- ============ Avisos ============ -->
<?php if ($isOverdue || $pendAlter): ?>
<div class="space-y-2 border-b border-gray-100 px-6 py-3">
    <?php if ($isOverdue): ?>
        <div class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800">
            <span class="mt-0.5 shrink-0 text-rose-500"><?= icon('clock', 'w-4 h-4') ?></span>
            <p>Devolución vencida desde <strong><?= e(format_date($rental['return_date'])) ?></strong>.
               Mora acumulada: <strong><?= e(money($latePend)) ?></strong>
               (<?= (int) $lateDays ?> día<?= $lateDays === 1 ? '' : 's' ?> laborable<?= $lateDays === 1 ? '' : 's' ?>
               × <?= e(money(late_fee_per_day())) ?>).</p>
        </div>
    <?php endif; ?>
    <?php if ($pendAlter): ?>
        <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm text-amber-900">
            <span class="mt-0.5 shrink-0 text-brand-gold"><?= icon('scissors', 'w-4 h-4') ?></span>
            <p><strong><?= count($pendAlter) ?> pieza<?= count($pendAlter) === 1 ? '' : 's' ?></strong>
               pendiente<?= count($pendAlter) === 1 ? '' : 's' ?> de modificar antes de la entrega.</p>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 px-6 py-5 lg:grid-cols-3">

    <!-- ============ Columna principal ============ -->
    <div class="space-y-5 lg:col-span-2">

        <!-- Fechas -->
        <section class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4">
            <h3 class="mb-3 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">Fechas</h3>
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <?= lcn_dato('Evento', e(format_date($rental['event_date']))) ?>
                <?= lcn_dato('Entrega',
                        e(format_date($rental['delivery_date']))
                        . (format_time($rental['delivery_time']) !== ''
                            ? '<span class="mt-0.5 block text-xs font-semibold text-brand-red">'
                              . e(format_time($rental['delivery_time'])) . '</span>'
                            : '')) ?>
                <?= lcn_dato('Devolución', e(format_date($rental['return_date'])),
                        $isOverdue ? 'text-rose-600' : '') ?>
                <?= lcn_dato('Duración',
                        e((string) max(0, days_between($rental['delivery_date'], $rental['return_date']))) . ' día(s)') ?>
            </dl>
            <?php if (!empty($rental['actual_return_date'])): ?>
                <p class="mt-3 text-xs text-gray-500">
                    Devuelto realmente el <strong class="text-gray-700"><?= e(format_date($rental['actual_return_date'])) ?></strong>.
                </p>
            <?php endif; ?>
        </section>

        <!-- Piezas -->
        <section>
            <h3 class="mb-3 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">
                Piezas del alquiler
                <span class="ml-1 rounded-full bg-brand-cream px-2 py-0.5 text-[11px] font-semibold text-brand-red"><?= count($items) ?></span>
            </h3>
            <div class="space-y-3">
                <?php foreach ($items as $item):
                    $needs = !empty($item['needs_alteration']);
                    $done  = $needs && $item['alteration_status'] === 'done'; ?>
                    <article class="flex gap-3.5 rounded-2xl border p-3 transition <?= $needs && !$done ? 'border-amber-200 bg-amber-50/40' : 'border-gray-100 bg-white' ?>">
                        <img src="<?= e(upload_url($item['main_image'] ?? null)) ?>" alt="<?= e($item['name']) ?>"
                             class="h-28 w-24 flex-none rounded-xl bg-gray-50 object-cover ring-1 ring-gray-100">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h4 class="truncate font-serif text-base font-semibold text-gray-900"><?= e($item['name']) ?></h4>
                                    <p class="text-xs font-medium text-brand-red"><?= e($item['category_name'] ?? 'General') ?></p>
                                </div>
                                <span class="shrink-0 text-sm font-bold text-gray-900"><?= e(money($item['unit_price'])) ?></span>
                            </div>

                            <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                                <span class="rounded-md bg-gray-50 px-1.5 py-0.5 font-mono tracking-wider ring-1 ring-gray-100">
                                    <?= e($item['barcode'] ?: ($item['sku'] ?: 'sin código')) ?>
                                </span>
                                <?php if (!empty($item['size'])): ?><span>Talla <strong class="text-gray-700"><?= e($item['size']) ?></strong></span><?php endif; ?>
                                <?php if (!empty($item['color'])): ?><span>Color <strong class="text-gray-700"><?= e($item['color']) ?></strong></span><?php endif; ?>
                                <?php if (!empty($item['material'])): ?><span><?= e($item['material']) ?></span><?php endif; ?>
                                <?php if (!empty($item['is_complement'])): ?>
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">Complemento</span>
                                <?php endif; ?>
                            </dl>

                            <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px]">
                                <span class="text-gray-400">Estado:</span> <?= status_badge($item['commercial_status'], 'commercial') ?>
                                <a href="<?= admin_url('productos/ver.php?id=' . (int) $item['id']) ?>" target="_blank"
                                   class="inline-flex items-center gap-1 font-medium text-brand-red hover:underline">
                                    <?= icon('eye', 'w-3.5 h-3.5') ?> Ficha completa
                                </a>
                            </div>

                            <?php if ($needs): ?>
                                <div class="mt-2.5 rounded-lg px-2.5 py-2 text-[11px] <?= $done ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-100/70 text-amber-900' ?>">
                                    <span class="inline-flex items-center gap-1 font-semibold">
                                        <?= icon('scissors', 'w-3 h-3') ?>
                                        <?= $done ? 'Modificación lista' : 'Pendiente de modificar' ?>
                                    </span>
                                    <?php if (!empty($item['alteration_notes'])): ?>
                                        <p class="mt-0.5 leading-snug"><?= e($item['alteration_notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Pagos -->
        <section>
            <h3 class="mb-3 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">Pagos recibidos</h3>
            <?php if (!$payments): ?>
                <p class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-gray-400">
                    Todavía no se ha registrado ningún pago.
                </p>
            <?php else: ?>
                <div class="overflow-hidden rounded-2xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3.5 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">Recibo</th>
                                <th class="px-3.5 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                <th class="px-3.5 py-2 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">Método</th>
                                <th class="px-3.5 py-2 text-right text-[10px] font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td class="px-3.5 py-2 font-medium text-gray-900">
                                        <a href="<?= admin_url('pagos/recibo.php?id=' . (int) $pay['id']) ?>" target="_blank" class="hover:text-brand-red hover:underline">
                                            <?= e($pay['payment_number']) ?>
                                        </a>
                                    </td>
                                    <td class="px-3.5 py-2 text-gray-600"><?= e(format_date($pay['paid_at'] ?: $pay['created_at'])) ?></td>
                                    <td class="px-3.5 py-2 text-gray-600"><?= e($payMethods[$pay['payment_method']] ?? $pay['payment_method']) ?></td>
                                    <td class="px-3.5 py-2 text-right font-semibold text-emerald-600"><?= e(money($pay['amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ============ Columna lateral ============ -->
    <aside class="space-y-5">

        <!-- Cliente -->
        <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
            <h3 class="mb-3 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">Cliente</h3>
            <div class="flex items-center gap-3">
                <?= avatar($rental['customer_name'], 'h-11 w-11 text-sm') ?>
                <div class="min-w-0">
                    <a href="<?= admin_url('clientes/ver.php?id=' . (int) $rental['customer_id']) ?>" target="_blank"
                       class="block truncate font-semibold text-gray-900 hover:text-brand-red">
                        <?= e($rental['customer_name']) ?>
                    </a>
                    <p class="text-xs text-gray-400">Cliente #<?= (int) $rental['customer_id'] ?></p>
                </div>
            </div>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex gap-1.5"><dt class="font-semibold text-gray-500">Cédula:</dt><dd class="text-gray-700"><?= e($rental['customer_document'] ?: '—') ?></dd></div>
                <div class="flex gap-1.5"><dt class="font-semibold text-gray-500">Teléfono:</dt><dd class="text-gray-700"><?= e($rental['customer_phone'] ?: '—') ?></dd></div>
                <div class="flex gap-1.5"><dt class="font-semibold text-gray-500">Dirección:</dt><dd class="text-gray-700"><?= e($rental['customer_address'] ?: '—') ?></dd></div>
                <?php if (!empty($rental['customer_email'])): ?>
                    <div class="flex gap-1.5"><dt class="font-semibold text-gray-500">Correo:</dt><dd class="truncate text-gray-700"><?= e($rental['customer_email']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($waLink): ?>
                <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
                   class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                    <?= icon('whatsapp', 'w-4 h-4') ?> Escribir por WhatsApp
                </a>
            <?php endif; ?>
        </section>

        <!-- Resumen económico -->
        <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <h3 class="border-b border-gray-100 bg-gray-50/70 px-4 py-2.5 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">Resumen económico</h3>
            <dl class="space-y-1.5 px-4 py-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal piezas</dt><dd class="font-medium text-gray-800"><?= e(money($rental['rental_price'])) ?></dd></div>
                <?php if ((float) $rental['discount'] > 0): ?>
                    <div class="flex justify-between"><dt class="text-gray-500">Descuento<?= (float) ($rental['discount_percent'] ?? 0) > 0 ? ' (' . e(rtrim(rtrim(number_format((float) $rental['discount_percent'], 2, '.', ''), '0'), '.')) . '%)' : '' ?></dt><dd class="font-medium text-rose-600">− <?= e(money($rental['discount'])) ?></dd></div>
                <?php endif; ?>
                <?php if ((float) $rental['late_penalty'] > 0): ?>
                    <div class="flex justify-between"><dt class="text-gray-500">Penalidad por mora</dt><dd class="font-medium text-rose-600">+ <?= e(money($rental['late_penalty'])) ?></dd></div>
                <?php endif; ?>
                <div class="flex justify-between border-t border-gray-100 pt-2"><dt class="font-semibold text-gray-900">Total</dt><dd class="text-lg font-bold text-gray-900"><?= e(money($rental['total_amount'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Abono requerido</dt><dd class="font-medium text-gray-700"><?= e(money($rental['initial_payment_required'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Pagado</dt><dd class="font-semibold text-emerald-600"><?= e(money($paid)) ?></dd></div>
                <div class="mt-1 flex justify-between rounded-xl bg-gray-50 px-3 py-2">
                    <dt class="font-semibold text-gray-900">Saldo</dt>
                    <dd class="font-bold <?= $balance > 0.009 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money(max(0, $balance))) ?></dd>
                </div>
            </dl>
        </section>

        <!-- Factura -->
        <section class="rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
            <h3 class="mb-3 font-serif text-sm font-bold uppercase tracking-wide text-gray-700">Factura</h3>
            <?php if ($invoice): ?>
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900"><?= e($invoice['invoice_number']) ?></p>
                        <p class="text-xs text-gray-400">
                            Emitida <?= e(format_date($invoice['issued_at'] ?: $invoice['created_at'])) ?> ·
                            saldo <?= e(money($invoice['balance'])) ?>
                        </p>
                    </div>
                    <?= status_badge($invoice['status'], 'invoice') ?>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="<?= admin_url('facturas/ver.php?id=' . (int) $invoice['id']) ?>" target="_blank"
                       class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('document', 'w-3.5 h-3.5') ?> Ver
                    </a>
                    <a href="<?= admin_url('facturas/imprimir.php?id=' . (int) $invoice['id']) ?>" target="_blank"
                       class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('printer', 'w-3.5 h-3.5') ?> Imprimir
                    </a>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No hay factura activa para este alquiler.</p>
                <a href="<?= admin_url('facturas/crear.php?rental=' . $id) ?>" target="_blank"
                   class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('plus', 'w-3.5 h-3.5') ?> Generar factura
                </a>
            <?php endif; ?>
        </section>

        <!-- Acciones -->
        <section class="space-y-2">
            <a href="<?= admin_url('alquileres/ver.php?id=' . $id) ?>"
               class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('eye', 'w-4 h-4') ?> Abrir detalle completo
            </a>
            <div class="grid grid-cols-1 gap-2 min-[380px]:grid-cols-3">
                <a href="<?= admin_url('alquileres/editar.php?id=' . $id) ?>"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-2 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('pencil', 'w-3.5 h-3.5') ?> Editar
                </a>
                <a href="<?= admin_url('alquileres/contrato.php?id=' . $id) ?>" target="_blank"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-2 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('document', 'w-3.5 h-3.5') ?> Contrato
                </a>
                <a href="<?= admin_url('pagos/crear.php?rental=' . $id) ?>"
                   class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-gray-200 px-2 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('banknotes', 'w-3.5 h-3.5') ?> Pago
                </a>
            </div>
        </section>
    </aside>
</div>
