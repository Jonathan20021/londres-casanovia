<?php
/**
 * Plantilla imprimible de FACTURA — LONDRES Casa de Novias
 *
 * Documento HTML completo y autónomo, pensado para window.print() (sin
 * dependencias externas más allá de Tailwind, ya cargado vía _head_assets).
 *
 * Variables esperadas (cargadas por el endpoint admin/facturas/imprimir.php):
 *   $invoice   array   Fila de invoices + datos del cliente/operación (alias customer_*, rental_*, sale_*)
 *   $customer  array   Datos del cliente (full_name, phone, whatsapp, email, address, document_number)
 *   $lines     array   Líneas de concepto: cada una ['concept','detail','amount']  (también acepta $items)
 *   $payments  array   Pagos asociados (payment_number, paid_at, payment_method, amount, received_by_name)
 *   $business  array   settings_all()  (datos del negocio)
 *
 * Reglas: toda salida escapada con e(); montos con money(); fechas con format_date().
 */
declare(strict_types=1);

if (!defined('LCN_ROOT')) { exit('Acceso no permitido.'); }

/* ------------------------------------------------------------------ *
 *  Normalización de variables (defensivo: el partial nunca debe romper)
 * ------------------------------------------------------------------ */
$invoice  = $invoice  ?? [];
$customer = $customer ?? [];
$payments = $payments ?? [];
$business = $business ?? settings_all();
// Permite recibir las líneas como $lines o como $items
$lines    = $lines ?? ($items ?? []);

/* Datos del negocio */
$bizName    = (string) ($business['business_name'] ?? setting('business_name', APP_NAME));
$bizPhone   = (string) ($business['phone']    ?? '');
$bizWhats   = (string) ($business['whatsapp'] ?? '');
$bizEmail   = (string) ($business['email']    ?? '');
$bizAddress = (string) ($business['address']  ?? '');
$bizRnc     = (string) ($business['rnc']      ?? ''); // por si existe en el futuro

/* Etiquetas de método de pago */
$methodLabels = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

/* Si no se entregaron líneas explícitas, construir una desde el concepto */
if (empty($lines)) {
    $conceptText = $invoice['concept']
        ?? ($invoice['rental_product'] ?? ($invoice['sale_product'] ?? 'Servicio'));
    $lines = [[
        'concept' => (string) $conceptText,
        'detail'  => (string) ($invoice['rental_number'] ?? $invoice['sale_number'] ?? ''),
        'amount'  => (float) ($invoice['subtotal'] ?? 0),
    ]];
}

/* Totales del documento */
$subtotal = (float) ($invoice['subtotal']    ?? 0);
$discount = (float) ($invoice['discount']    ?? 0);
$tax      = (float) ($invoice['tax']         ?? 0);
$total    = (float) ($invoice['total']       ?? 0);
$paid     = (float) ($invoice['paid_amount'] ?? 0);
$balance  = (float) ($invoice['balance']     ?? 0);

/* Tipo de operación */
$isSale = ($invoice['invoice_type'] ?? 'rental') === 'sale';

/* Enlace WhatsApp (dígitos del cliente o del negocio) */
$custWhats = (string) ($customer['whatsapp'] ?? $invoice['customer_whatsapp'] ?? '');
$custPhone = (string) ($customer['phone']    ?? $invoice['customer_phone']    ?? '');
$custName  = (string) ($customer['full_name'] ?? $invoice['customer_name']    ?? 'Cliente');
$waDigits  = preg_replace('/\D+/', '', $custWhats ?: ($custPhone ?: $bizWhats));
$waMessage = "Hola {$custName}, le compartimos su factura " . ($invoice['invoice_number'] ?? '') . " de {$bizName}.\n"
    . 'Total: ' . money($total) . "\n"
    . 'Pagado: ' . money($paid) . "\n"
    . 'Saldo pendiente: ' . money($balance) . "\n"
    . 'Gracias por su preferencia.';
$waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waMessage);

$docTitle = 'Factura ' . ($invoice['invoice_number'] ?? '') . ' · ' . $bizName;
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
    <title><?= e($docTitle) ?></title>
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
    <style>
        /* Estilos específicos de impresión: papel A4, sin elementos de pantalla */
        @page { size: A4; margin: 14mm; }
        body  { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media print {
            .no-print { display: none !important; }
            .print-area { box-shadow: none !important; border: 0 !important; margin: 0 !important; max-width: 100% !important; }
            html, body { background: #fff !important; }
        }
    </style>
</head>
<body class="min-h-full bg-gray-100 font-sans text-gray-800 antialiased">

<!-- Barra de acciones (no se imprime) -->
<div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
        <p class="text-sm font-medium text-gray-500">Factura <span class="font-semibold text-gray-900"><?= e($invoice['invoice_number'] ?? '') ?></span></p>
        <div class="flex items-center gap-2">
            <?php if ($waDigits !== ''): ?>
                <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">
                    <?= icon('whatsapp', 'w-4 h-4') ?> WhatsApp
                </a>
            <?php endif; ?>
            <button type="button" onclick="window.close()" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('x', 'w-4 h-4') ?> Cerrar
            </button>
            <a href="?id=<?= (int) ($invoice['id'] ?? 0) ?>&amp;pdf=1" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('download', 'w-4 h-4') ?> Descargar PDF
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('printer', 'w-4 h-4') ?> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- Documento -->
<main class="mx-auto my-6 max-w-3xl">
    <article class="print-area overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-card">

        <!-- Encabezado de marca -->
        <header class="flex flex-col gap-4 border-b border-gray-100 bg-gradient-to-br from-brand-cream to-white px-8 py-7 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <?= brand_lockup('dark', 'md') ?>
                <div class="mt-3 space-y-0.5 text-xs text-gray-500">
                    <?php if ($bizAddress): ?><p class="flex items-center gap-1.5"><?= icon('pin', 'w-3.5 h-3.5') ?> <?= e($bizAddress) ?></p><?php endif; ?>
                    <?php if ($bizPhone): ?><p class="flex items-center gap-1.5"><?= icon('phone', 'w-3.5 h-3.5') ?> <?= e($bizPhone) ?></p><?php endif; ?>
                    <?php if ($bizEmail): ?><p class="flex items-center gap-1.5"><?= icon('mail', 'w-3.5 h-3.5') ?> <?= e($bizEmail) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="text-left sm:text-right">
                <p class="font-serif text-3xl font-bold tracking-wide text-gray-900">FACTURA</p>
                <p class="mt-1 text-sm font-semibold text-brand-red"><?= e($invoice['invoice_number'] ?? '') ?></p>
                <div class="mt-2 flex sm:justify-end"><?= status_badge($invoice['status'] ?? 'pending', 'invoice') ?></div>
                <p class="mt-2 text-xs text-gray-500">Emitida: <?= e(format_date($invoice['issued_at'] ?? $invoice['created_at'] ?? null)) ?></p>
            </div>
        </header>

        <div class="px-8 py-7">
            <!-- Cliente y detalles -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Facturar a</p>
                    <p class="mt-1.5 font-semibold text-gray-900"><?= e($custName) ?></p>
                    <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                        <?php $custDoc = $customer['document_number'] ?? $invoice['customer_document'] ?? ''; ?>
                        <?php if ($custDoc): ?><p>Documento: <?= e($custDoc) ?></p><?php endif; ?>
                        <?php if ($custPhone): ?><p><?= e($custPhone) ?></p><?php endif; ?>
                        <?php $custEmail = $customer['email'] ?? $invoice['customer_email'] ?? ''; ?>
                        <?php if ($custEmail): ?><p><?= e($custEmail) ?></p><?php endif; ?>
                        <?php $custAddr = $customer['address'] ?? $invoice['customer_address'] ?? ''; ?>
                        <?php if ($custAddr): ?><p><?= e($custAddr) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Detalles</p>
                    <div class="mt-1.5 space-y-0.5 text-sm text-gray-600">
                        <p>Tipo: <span class="font-medium text-gray-900"><?= $isSale ? 'Venta' : 'Alquiler' ?></span></p>
                        <?php if (!empty($invoice['rental_number'])): ?>
                            <p>Alquiler: <span class="font-medium text-gray-900"><?= e($invoice['rental_number']) ?></span></p>
                            <?php if (!empty($invoice['delivery_date'])): ?>
                                <p>Entrega: <?= e(format_date($invoice['delivery_date'])) ?></p>
                                <p>Devolución: <?= e(format_date($invoice['return_date'] ?? null)) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($invoice['sale_number'])): ?>
                            <p>Venta: <span class="font-medium text-gray-900"><?= e($invoice['sale_number']) ?></span></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['created_by_name'])): ?>
                            <p>Atendido por: <?= e($invoice['created_by_name']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabla de conceptos -->
            <div class="mt-7 overflow-hidden rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Concepto</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($lines as $ln): ?>
                            <tr>
                                <td class="px-5 py-4 text-gray-700">
                                    <p class="font-medium text-gray-900"><?= e($ln['concept'] ?? 'Servicio') ?></p>
                                    <?php if (!empty($ln['detail'])): ?>
                                        <p class="mt-0.5 text-xs text-gray-400"><?= e($ln['detail']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-right font-medium text-gray-900"><?= e(money($ln['amount'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totales -->
            <div class="mt-6 flex justify-end">
                <div class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span class="font-medium text-gray-800"><?= e(money($subtotal)) ?></span></div>
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span class="font-medium text-rose-600">- <?= e(money($discount)) ?></span></div>
                    <?php endif; ?>
                    <?php if ($tax > 0): ?>
                        <div class="flex justify-between"><span class="text-gray-500">Impuesto</span><span class="font-medium text-gray-800"><?= e(money($tax)) ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between border-t border-gray-200 pt-2"><span class="font-semibold text-gray-900">Total</span><span class="text-lg font-bold text-gray-900"><?= e(money($total)) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Pagado</span><span class="font-medium text-emerald-600"><?= e(money($paid)) ?></span></div>
                    <div class="flex justify-between rounded-xl bg-gray-50 px-3 py-2"><span class="font-semibold text-gray-900">Saldo</span><span class="font-bold <?= $balance > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money($balance)) ?></span></div>
                </div>
            </div>

            <!-- Historial de pagos -->
            <?php if (!empty($payments)): ?>
                <div class="mt-8">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Pagos recibidos</p>
                    <div class="mt-2 overflow-hidden rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibo</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Método</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900"><?= e($p['payment_number'] ?? '—') ?></td>
                                        <td class="px-4 py-3 text-gray-700"><?= e(format_date($p['paid_at'] ?? $p['created_at'] ?? null)) ?></td>
                                        <td class="px-4 py-3 text-gray-700"><?= e($methodLabels[$p['payment_method'] ?? ''] ?? ucfirst((string) ($p['payment_method'] ?? '—'))) ?></td>
                                        <td class="px-4 py-3 text-right font-medium text-emerald-600"><?= e(money($p['amount'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pie de página -->
        <footer class="border-t border-gray-100 bg-gray-50/60 px-8 py-6 text-center">
            <p class="font-script text-2xl text-brand-red">Gracias por su preferencia</p>
            <p class="mt-1 text-xs text-gray-400">
                <?= e($bizName) ?><?php if ($bizPhone): ?> · <?= e($bizPhone) ?><?php endif; ?><?php if ($bizWhats): ?> · WhatsApp <?= e($bizWhats) ?><?php endif; ?>
            </p>
            <p class="mt-1 text-[11px] text-gray-400">Documento generado el <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></p>
        </footer>
    </article>
</main>

</body>
</html>
