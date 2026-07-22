<?php
/**
 * Ficha de cliente — LONDRES Casa de Novias
 * Resumen, historial de alquileres, ventas, pagos y facturas.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('customers.manage');

$id = (int) get_param('id');
$customer = $id ? db_one('SELECT * FROM customers WHERE id = :id', ['id' => $id]) : null;

if (!$customer) {
    flash('error', 'El cliente solicitado no existe.');
    redirect(admin_url('clientes/index.php'));
}

/* ----------------------------------------------------------------- *
 *  Métricas resumen
 * ----------------------------------------------------------------- */
// Total alquilado (suma de total_amount de alquileres no cancelados)
$totalRented = (float) db_value(
    "SELECT COALESCE(SUM(total_amount),0) FROM rentals WHERE customer_id = :id AND rental_status <> 'cancelled'",
    ['id' => $id]
);
// Deuda pendiente (saldos de alquileres activos sin liquidar)
$totalDebt = (float) db_value(
    "SELECT COALESCE(SUM(remaining_balance),0) FROM rentals
      WHERE customer_id = :id AND rental_status <> 'cancelled' AND payment_status <> 'paid'",
    ['id' => $id]
);
$rentalsCount = (int) db_value('SELECT COUNT(*) FROM rentals WHERE customer_id = :id', ['id' => $id]);
$salesCount   = (int) db_value('SELECT COUNT(*) FROM sales   WHERE customer_id = :id', ['id' => $id]);

/* ----------------------------------------------------------------- *
 *  Historiales
 * ----------------------------------------------------------------- */
$rentals = db_all(
    "SELECT r.*, p.name AS product_name,
            (SELECT COUNT(*) FROM rental_items ric WHERE ric.rental_id = r.id) AS product_count
       FROM rentals r
       JOIN products p ON p.id = r.product_id
      WHERE r.customer_id = :id
      ORDER BY r.created_at DESC",
    ['id' => $id]
);

$sales = db_all(
    "SELECT s.*, p.name AS product_name
       FROM sales s
       JOIN products p ON p.id = s.product_id
      WHERE s.customer_id = :id
      ORDER BY s.created_at DESC",
    ['id' => $id]
);

$payments = db_all(
    "SELECT pa.*, r.rental_number, s.sale_number
       FROM payments pa
       LEFT JOIN rentals r ON r.id = pa.rental_id
       LEFT JOIN sales   s ON s.id = pa.sale_id
      WHERE pa.customer_id = :id
      ORDER BY COALESCE(pa.paid_at, pa.created_at) DESC",
    ['id' => $id]
);

$invoices = db_all(
    "SELECT * FROM invoices WHERE customer_id = :id ORDER BY created_at DESC",
    ['id' => $id]
);

/* ----------------------------------------------------------------- *
 *  Enlace de WhatsApp (sólo dígitos)
 * ----------------------------------------------------------------- */
$waNumber = preg_replace('/\D+/', '', (string) ($customer['whatsapp'] ?: $customer['phone'] ?: ''));
$waMsg    = 'Hola ' . $customer['full_name'] . ', le saludamos de ' . setting('business_name', APP_NAME) . '.';
$waUrl    = $waNumber !== '' ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waMsg) : '';

$page_title    = $customer['full_name'];
$page_subtitle = 'Ficha del cliente · historial completo';
$active        = 'clientes';

$actions  = '<a href="' . admin_url('clientes/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">' . icon('chevron-left', 'w-4 h-4') . ' Listado</a>';
if ($waUrl !== '') {
    $actions .= '<a href="' . e($waUrl) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">' . icon('whatsapp', 'w-4 h-4') . ' WhatsApp</a>';
}
$actions .= '<a href="' . admin_url('clientes/editar.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('pencil', 'w-4 h-4') . ' Editar</a>';
$header_actions = $actions;

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Datos de contacto + métricas -->
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Tarjeta de identidad -->
    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft lg:col-span-1">
        <div class="flex items-center gap-4">
            <?= avatar($customer['full_name'], 'h-16 w-16 text-xl') ?>
            <div class="min-w-0">
                <h2 class="font-serif text-xl font-bold text-gray-900"><?= e($customer['full_name']) ?></h2>
                <?php if (!empty($customer['document_number'])): ?>
                    <p class="text-sm text-gray-500">Doc. <?= e($customer['document_number']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <dl class="mt-6 space-y-3 text-sm">
            <?php if (!empty($customer['phone'])): ?>
                <div class="flex items-center gap-3 text-gray-700">
                    <span class="text-gray-400"><?= icon('phone', 'w-5 h-5') ?></span>
                    <a href="tel:<?= e($customer['phone']) ?>" class="hover:text-brand-red"><?= e($customer['phone']) ?></a>
                </div>
            <?php endif; ?>
            <?php if (!empty($customer['whatsapp'])): ?>
                <div class="flex items-center gap-3 text-gray-700">
                    <span class="text-emerald-500"><?= icon('whatsapp', 'w-5 h-5') ?></span>
                    <span><?= e($customer['whatsapp']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <div class="flex items-center gap-3 text-gray-700">
                    <span class="text-gray-400"><?= icon('mail', 'w-5 h-5') ?></span>
                    <a href="mailto:<?= e($customer['email']) ?>" class="break-all hover:text-brand-red"><?= e($customer['email']) ?></a>
                </div>
            <?php endif; ?>
            <?php if (!empty($customer['instagram'])): ?>
                <div class="flex items-center gap-3 text-gray-700">
                    <span class="text-gray-400"><?= icon('instagram', 'w-5 h-5') ?></span>
                    <a href="https://instagram.com/<?= e(ltrim($customer['instagram'], '@')) ?>" target="_blank" rel="noopener" class="hover:text-brand-red">@<?= e(ltrim($customer['instagram'], '@')) ?></a>
                </div>
            <?php endif; ?>
            <?php if (!empty($customer['address'])): ?>
                <div class="flex items-start gap-3 text-gray-700">
                    <span class="mt-0.5 text-gray-400"><?= icon('pin', 'w-5 h-5') ?></span>
                    <span><?= e($customer['address']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($customer['birthdate'])): ?>
                <div class="flex items-center gap-3 text-gray-700">
                    <span class="text-gray-400"><?= icon('calendar', 'w-5 h-5') ?></span>
                    <span>Nació el <?= e(format_date($customer['birthdate'])) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex items-center gap-3 text-gray-400">
                <span><?= icon('clock', 'w-5 h-5') ?></span>
                <span>Registrado el <?= e(format_date($customer['created_at'])) ?></span>
            </div>
        </dl>

        <?php if (!empty($customer['notes'])): ?>
            <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-inset ring-amber-100">
                <p class="mb-1 flex items-center gap-1.5 font-semibold"><?= icon('document', 'w-4 h-4') ?> Notas internas</p>
                <p class="whitespace-pre-line text-amber-700"><?= e($customer['notes']) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Métricas -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-2">
        <?= metric_card('Total alquilado', money($totalRented), 'banknotes', 'gold') ?>
        <?= metric_card('Deuda pendiente', money($totalDebt), 'warning', $totalDebt > 0.009 ? 'red' : 'emerald', $totalDebt > 0.009 ? 'Saldo por cobrar' : 'Al día') ?>
        <?= metric_card('Alquileres', (string) $rentalsCount, 'box', 'sky') ?>
        <?= metric_card('Compras', (string) $salesCount, 'bag', 'violet') ?>
    </div>
</div>

<!-- Pestañas -->
<div class="mt-8" data-tabs>
    <div class="flex flex-wrap gap-1 border-b border-gray-100">
        <button type="button" data-tab="alquileres" class="lcn-tab border-b-2 border-brand-red px-4 py-2.5 text-sm font-semibold text-brand-red">
            Alquileres <span class="ml-1 text-xs text-gray-400"><?= count($rentals) ?></span>
        </button>
        <button type="button" data-tab="ventas" class="lcn-tab border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700">
            Ventas <span class="ml-1 text-xs text-gray-400"><?= count($sales) ?></span>
        </button>
        <button type="button" data-tab="pagos" class="lcn-tab border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700">
            Pagos <span class="ml-1 text-xs text-gray-400"><?= count($payments) ?></span>
        </button>
        <button type="button" data-tab="facturas" class="lcn-tab border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700">
            Facturas <span class="ml-1 text-xs text-gray-400"><?= count($invoices) ?></span>
        </button>
    </div>

    <!-- Panel: Alquileres -->
    <div data-tab-panel="alquileres" class="pt-6">
        <?php if (!$rentals): ?>
            <?= empty_state('Sin alquileres', 'Este cliente todavía no tiene alquileres registrados.', 'box') ?>
        <?php else: ?>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"># Alquiler</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fechas</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($rentals as $r): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 font-medium text-gray-900"><?= e($r['rental_number']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($r['product_name']) ?><?= (int) $r['product_count'] > 1 ? ' +' . ((int) $r['product_count'] - 1) : '' ?></td>
                                    <td class="px-5 py-4 text-gray-700">
                                        <span class="block text-xs text-gray-400">Entrega</span>
                                        <span><?= e(format_date($r['delivery_date'])) ?></span>
                                        <span class="block text-xs text-gray-400">→ Devuelve <?= e(format_date($r['return_date'])) ?></span>
                                    </td>
                                    <td class="px-5 py-4"><?= status_badge($r['rental_status'], 'rental') ?></td>
                                    <td class="px-5 py-4"><?= status_badge($r['payment_status'], 'payment') ?></td>
                                    <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($r['total_amount'])) ?></td>
                                    <td class="px-5 py-4 text-right <?= (float) $r['remaining_balance'] > 0.009 ? 'font-semibold text-brand-red' : 'text-gray-400' ?>"><?= e(money($r['remaining_balance'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= admin_url('alquileres/ver.php?id=' . (int) $r['id']) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red" title="Ver alquiler"><?= icon('eye', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Panel: Ventas -->
    <div data-tab-panel="ventas" class="hidden pt-6">
        <?php if (!$sales): ?>
            <?= empty_state('Sin ventas', 'Este cliente todavía no ha realizado compras.', 'bag') ?>
        <?php else: ?>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"># Venta</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Pago</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($sales as $s): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 font-medium text-gray-900"><?= e($s['sale_number']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($s['product_name']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($s['created_at'])) ?></td>
                                    <td class="px-5 py-4"><?= status_badge($s['status'], 'sale') ?></td>
                                    <td class="px-5 py-4"><?= status_badge($s['payment_status'], 'payment') ?></td>
                                    <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($s['total_amount'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= admin_url('ventas/ver.php?id=' . (int) $s['id']) ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red" title="Ver venta"><?= icon('eye', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Panel: Pagos -->
    <div data-tab-panel="pagos" class="hidden pt-6">
        <?php if (!$payments): ?>
            <?= empty_state('Sin pagos', 'Este cliente todavía no tiene pagos registrados.', 'banknotes') ?>
        <?php else:
            $methodLabels = [
                'cash'     => 'Efectivo',
                'transfer' => 'Transferencia',
                'card'     => 'Tarjeta',
                'deposit'  => 'Depósito',
                'other'    => 'Otro',
            ];
        ?>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"># Recibo</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Concepto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Método</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($payments as $p): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 font-medium text-gray-900"><?= e($p['payment_number']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($p['paid_at'] ?: $p['created_at'])) ?></td>
                                    <td class="px-5 py-4 text-gray-700">
                                        <?php if (!empty($p['rental_number'])): ?>
                                            Alquiler <span class="font-medium text-gray-900"><?= e($p['rental_number']) ?></span>
                                        <?php elseif (!empty($p['sale_number'])): ?>
                                            Venta <span class="font-medium text-gray-900"><?= e($p['sale_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">General</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700"><?= e($methodLabels[$p['payment_method']] ?? ucfirst((string) $p['payment_method'])) ?></td>
                                    <td class="px-5 py-4 text-right font-semibold text-emerald-600"><?= e(money($p['amount'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= admin_url('pagos/recibo.php?id=' . (int) $p['id']) ?>" target="_blank" rel="noopener" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red" title="Recibo"><?= icon('printer', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Panel: Facturas -->
    <div data-tab-panel="facturas" class="hidden pt-6">
        <?php if (!$invoices): ?>
            <?= empty_state('Sin facturas', 'Este cliente todavía no tiene facturas emitidas.', 'document') ?>
        <?php else: ?>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"># Factura</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Emitida</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($invoices as $inv): ?>
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-5 py-4 font-medium text-gray-900"><?= e($inv['invoice_number']) ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= $inv['invoice_type'] === 'sale' ? 'Venta' : 'Alquiler' ?></td>
                                    <td class="px-5 py-4 text-gray-700"><?= e(format_date($inv['issued_at'] ?: $inv['created_at'])) ?></td>
                                    <td class="px-5 py-4"><?= status_badge($inv['status'], 'invoice') ?></td>
                                    <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($inv['total'])) ?></td>
                                    <td class="px-5 py-4 text-right <?= (float) $inv['balance'] > 0.009 ? 'font-semibold text-brand-red' : 'text-gray-400' ?>"><?= e(money($inv['balance'])) ?></td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="<?= admin_url('facturas/imprimir.php?id=' . (int) $inv['id']) ?>" target="_blank" rel="noopener" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red" title="Imprimir factura"><?= icon('printer', 'w-4 h-4') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pestañas: alternancia ligera (sin dependencias externas) -->
<script>
(function () {
    var root = document.querySelector('[data-tabs]');
    if (!root) return;
    var tabs   = root.querySelectorAll('[data-tab]');
    var panels = root.querySelectorAll('[data-tab-panel]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-tab');
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('border-brand-red', on);
                t.classList.toggle('text-brand-red', on);
                t.classList.toggle('border-transparent', !on);
                t.classList.toggle('text-gray-500', !on);
            });
            panels.forEach(function (p) {
                p.classList.toggle('hidden', p.getAttribute('data-tab-panel') !== key);
            });
        });
    });
})();
</script>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
