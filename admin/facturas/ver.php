<?php
/**
 * Facturas — Vista en pantalla de una factura.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('invoices.manage');

$id = (int) get_param('id');

$invoice = db_one(
    "SELECT i.*,
            c.full_name AS customer_name, c.phone AS customer_phone, c.whatsapp AS customer_whatsapp,
            c.email AS customer_email, c.address AS customer_address, c.document_number AS customer_document,
            r.rental_number, r.delivery_date, r.return_date, r.event_date,
            s.sale_number,
            pr.name AS rental_product, ps.name AS sale_product,
            usr.name AS created_by_name
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     LEFT JOIN rentals r  ON r.id = i.rental_id
     LEFT JOIN sales   s  ON s.id = i.sale_id
     LEFT JOIN products pr ON pr.id = r.product_id
     LEFT JOIN products ps ON ps.id = s.product_id
     LEFT JOIN users    usr ON usr.id = i.created_by
     WHERE i.id = :id",
    ['id' => $id]
);

if (!$invoice) {
    flash('error', 'La factura solicitada no existe.');
    redirect(admin_url('facturas/index.php'));
}

/* Pagos asociados a esta factura (por invoice_id o por la operación origen) */
$paymentWhere  = ['invoice_id = :iid'];
$paymentParams = ['iid' => $id];
if (!empty($invoice['rental_id'])) {
    $paymentWhere[]            = 'rental_id = :rid';
    $paymentParams['rid']      = (int) $invoice['rental_id'];
}
if (!empty($invoice['sale_id'])) {
    $paymentWhere[]            = 'sale_id = :sid';
    $paymentParams['sid']      = (int) $invoice['sale_id'];
}
$payments = db_all(
    'SELECT p.*, u.name AS received_by_name
     FROM payments p
     LEFT JOIN users u ON u.id = p.received_by
     WHERE ' . implode(' OR ', $paymentWhere) . '
     ORDER BY COALESCE(p.paid_at, p.created_at) ASC',
    $paymentParams
);
$paymentsTotal = 0.0;
foreach ($payments as $p) {
    $paymentsTotal += (float) $p['amount'];
}

/* Datos del negocio */
$bizName    = (string) setting('business_name', APP_NAME);
$bizPhone   = (string) setting('phone', '');
$bizEmail   = (string) setting('email', '');
$bizAddress = (string) setting('address', '');
$bizWhats   = (string) setting('whatsapp', '');

$methodLabels = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

/* Enlace de WhatsApp con resumen de la factura */
$waDigits = preg_replace('/\D+/', '', (string) ($invoice['customer_whatsapp'] ?: $invoice['customer_phone'] ?: $bizWhats));
$waMessage = "Hola {$invoice['customer_name']}, le compartimos su factura {$invoice['invoice_number']} de {$bizName}.\n"
    . 'Total: ' . money($invoice['total']) . "\n"
    . 'Pagado: ' . money($invoice['paid_amount']) . "\n"
    . 'Saldo pendiente: ' . money($invoice['balance']) . "\n"
    . 'Gracias por su preferencia.';
$waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waMessage);

/* Permiso especial para anular facturas ya pagadas */
$canVoidPaid = has_role('Super Admin', 'Administrador');
$isVoid      = $invoice['status'] === 'void';

$page_title    = 'Factura ' . $invoice['invoice_number'];
$page_subtitle = ($invoice['invoice_type'] === 'sale' ? 'Venta' : 'Alquiler') . ' · Emitida el ' . format_date($invoice['issued_at']);
$active        = 'facturas';
$header_actions = '<a href="' . e(admin_url('facturas/index.php')) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Factura -->
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft">
            <!-- Encabezado -->
            <div class="flex flex-col gap-4 border-b border-gray-100 bg-gradient-to-br from-brand-cream to-white px-6 py-6 sm:flex-row sm:items-start sm:justify-between sm:px-8">
                <div>
                    <?= brand_lockup('dark', 'md') ?>
                    <div class="mt-3 space-y-0.5 text-xs text-gray-500">
                        <?php if ($bizAddress): ?><p class="flex items-center gap-1.5"><?= icon('pin', 'w-3.5 h-3.5') ?> <?= e($bizAddress) ?></p><?php endif; ?>
                        <?php if ($bizPhone): ?><p class="flex items-center gap-1.5"><?= icon('phone', 'w-3.5 h-3.5') ?> <?= e($bizPhone) ?></p><?php endif; ?>
                        <?php if ($bizEmail): ?><p class="flex items-center gap-1.5"><?= icon('mail', 'w-3.5 h-3.5') ?> <?= e($bizEmail) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <p class="font-serif text-2xl font-bold text-gray-900">FACTURA</p>
                    <p class="mt-1 text-sm font-semibold text-brand-red"><?= e($invoice['invoice_number']) ?></p>
                    <div class="mt-2"><?= status_badge($invoice['status'], 'invoice') ?></div>
                    <p class="mt-2 text-xs text-gray-500">Emitida: <?= format_date($invoice['issued_at']) ?></p>
                </div>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <!-- Cliente y operación -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Facturar a</p>
                        <p class="mt-1.5 font-semibold text-gray-900"><?= e($invoice['customer_name']) ?></p>
                        <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                            <?php if ($invoice['customer_document']): ?><p>Doc.: <?= e($invoice['customer_document']) ?></p><?php endif; ?>
                            <?php if ($invoice['customer_phone']): ?><p><?= e($invoice['customer_phone']) ?></p><?php endif; ?>
                            <?php if ($invoice['customer_email']): ?><p><?= e($invoice['customer_email']) ?></p><?php endif; ?>
                            <?php if ($invoice['customer_address']): ?><p><?= e($invoice['customer_address']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Detalles</p>
                        <div class="mt-1.5 space-y-0.5 text-sm text-gray-600">
                            <p>Tipo: <span class="font-medium text-gray-900"><?= $invoice['invoice_type'] === 'sale' ? 'Venta' : 'Alquiler' ?></span></p>
                            <?php if ($invoice['rental_number']): ?>
                                <p>Alquiler:
                                    <a href="<?= e(admin_url('alquileres/ver.php?id=' . (int) $invoice['rental_id'])) ?>" class="font-medium text-brand-red hover:underline"><?= e($invoice['rental_number']) ?></a>
                                </p>
                                <?php if ($invoice['delivery_date']): ?><p>Entrega: <?= format_date($invoice['delivery_date']) ?> · Devolución: <?= format_date($invoice['return_date']) ?></p><?php endif; ?>
                            <?php endif; ?>
                            <?php if ($invoice['sale_number']): ?>
                                <p>Venta:
                                    <a href="<?= e(admin_url('ventas/ver.php?id=' . (int) $invoice['sale_id'])) ?>" class="font-medium text-brand-red hover:underline"><?= e($invoice['sale_number']) ?></a>
                                </p>
                            <?php endif; ?>
                            <?php if ($invoice['created_by_name']): ?><p>Generada por: <?= e($invoice['created_by_name']) ?></p><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Concepto / producto -->
                <div class="mt-6 overflow-hidden rounded-2xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Concepto</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr>
                                <td class="px-5 py-4 text-gray-700">
                                    <p class="font-medium text-gray-900"><?= e($invoice['concept'] ?: ($invoice['rental_product'] ?: ($invoice['sale_product'] ?: 'Servicio'))) ?></p>
                                    <?php $prod = $invoice['rental_product'] ?: $invoice['sale_product']; ?>
                                    <?php if ($prod && $invoice['concept']): ?>
                                        <p class="mt-0.5 text-xs text-gray-400"><?= e($prod) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($invoice['subtotal'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class="mt-5 flex justify-end">
                    <div class="w-full max-w-xs space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-medium text-gray-800"><?= e(money($invoice['subtotal'])) ?></span></div>
                        <?php if ((float) $invoice['discount'] > 0): ?>
                            <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span class="font-medium text-rose-600">- <?= e(money($invoice['discount'])) ?></span></div>
                        <?php endif; ?>
                        <?php if ((float) $invoice['tax'] > 0): ?>
                            <div class="flex justify-between"><span class="text-gray-500">Impuesto</span><span class="font-medium text-gray-800"><?= e(money($invoice['tax'])) ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between border-t border-gray-200 pt-2"><span class="font-semibold text-gray-900">Total</span><span class="text-lg font-bold text-gray-900"><?= e(money($invoice['total'])) ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Pagado</span><span class="font-medium text-emerald-600"><?= e(money($invoice['paid_amount'])) ?></span></div>
                        <div class="flex justify-between rounded-xl bg-gray-50 px-3 py-2"><span class="font-semibold text-gray-900">Saldo</span><span class="font-bold <?= (float) $invoice['balance'] > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money($invoice['balance'])) ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagos asociados -->
        <div class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="font-serif text-lg font-semibold text-gray-900">Pagos registrados</h2>
                <span class="text-sm text-gray-500"><?= count($payments) ?> pago(s) · <?= e(money($paymentsTotal)) ?></span>
            </div>
            <?php if (!$payments): ?>
                <div class="px-6 py-8">
                    <?= empty_state('Sin pagos', 'Aún no se han registrado pagos para esta factura.', 'banknotes') ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibo</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Método</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibido por</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 font-medium text-gray-900"><?= e($p['payment_number']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= format_date($p['paid_at'] ?: $p['created_at']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($methodLabels[$p['payment_method']] ?? ucfirst((string) $p['payment_method'])) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($p['received_by_name'] ?: '—') ?></td>
                                    <td class="px-5 py-4 text-right font-medium text-emerald-600"><?= e(money($p['amount'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= e(admin_url('pagos/recibo.php?id=' . (int) $p['id'])) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red" title="Imprimir recibo"><?= icon('printer', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Acciones -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft lg:sticky lg:top-24">
            <h2 class="font-serif text-lg font-semibold text-gray-900">Acciones</h2>
            <div class="mt-4 space-y-2.5">
                <a href="<?= e(admin_url('facturas/imprimir.php?id=' . (int) $invoice['id'])) ?>" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('printer', 'w-4 h-4') ?> Imprimir factura
                </a>

                <?php if (!$isVoid && (float) $invoice['balance'] > 0): ?>
                    <?php
                    $payHref = admin_url('pagos/crear.php?invoice=' . (int) $invoice['id']);
                    if (!empty($invoice['rental_id'])) $payHref .= '&rental=' . (int) $invoice['rental_id'];
                    if (!empty($invoice['sale_id']))   $payHref .= '&sale=' . (int) $invoice['sale_id'];
                    ?>
                    <a href="<?= e($payHref) ?>" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <?= icon('banknotes', 'w-4 h-4') ?> Registrar pago
                    </a>
                <?php endif; ?>

                <?php if ($waDigits !== ''): ?>
                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">
                        <?= icon('whatsapp', 'w-4 h-4') ?> Enviar por WhatsApp
                    </a>
                <?php endif; ?>

                <?php if (!$isVoid && ($invoice['status'] !== 'paid' || $canVoidPaid)): ?>
                    <form method="post" action="<?= e(admin_url('facturas/anular.php')) ?>" data-confirm="¿Anular esta factura? Esta acción no se puede deshacer.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">
                            <?= icon('x', 'w-4 h-4') ?> Anular factura
                        </button>
                    </form>
                <?php elseif ($isVoid): ?>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-center text-sm text-rose-700">
                        Esta factura está anulada.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen lateral -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-gray-900">Resumen</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-medium text-gray-900"><?= e(money($invoice['total'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Pagado</dt><dd class="font-medium text-emerald-600"><?= e(money($invoice['paid_amount'])) ?></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Saldo</dt><dd class="font-semibold <?= (float) $invoice['balance'] > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money($invoice['balance'])) ?></dd></div>
            </dl>
        </div>
    </div>
</div>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
