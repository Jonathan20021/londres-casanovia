<?php
/**
 * Plantilla imprimible de RECIBO DE PAGO — LONDRES Casa de Novias
 *
 * Documento HTML completo y autónomo para window.print().
 *
 * Variables esperadas (cargadas por admin/pagos/recibo.php):
 *   $payment   array   Fila de payments + alias (customer_name, received_by_name,
 *                      rental_number, sale_number, invoice_number, product_name…)
 *   $customer  array   Datos del cliente (full_name, phone, whatsapp, email, document_number)
 *   $business  array   settings_all()
 *   $remaining float|null  Saldo restante del alquiler/venta/factura tras este pago (opcional)
 *
 * Reglas: salida escapada con e(); montos con money(); fechas con format_date().
 */
declare(strict_types=1);

if (!defined('LCN_ROOT')) { exit('Acceso no permitido.'); }

$payment   = $payment   ?? [];
$customer  = $customer  ?? [];
$business  = $business  ?? settings_all();
$remaining = $remaining ?? null;

/* Datos del negocio */
$bizName    = (string) ($business['business_name'] ?? setting('business_name', APP_NAME));
$bizPhone   = (string) ($business['phone']    ?? '');
$bizWhats   = (string) ($business['whatsapp'] ?? '');
$bizEmail   = (string) ($business['email']    ?? '');
$bizAddress = (string) ($business['address']  ?? '');

$methodLabels = [
    'cash'     => 'Efectivo',
    'transfer' => 'Transferencia',
    'card'     => 'Tarjeta',
    'deposit'  => 'Depósito',
    'other'    => 'Otro',
];

/* Concepto del recibo: alquiler / venta / factura + número */
$concept = 'Pago recibido';
$docRef  = '';
if (!empty($payment['rental_number'])) {
    $concept = 'Pago de alquiler';
    $docRef  = (string) $payment['rental_number'];
} elseif (!empty($payment['sale_number'])) {
    $concept = 'Pago de venta';
    $docRef  = (string) $payment['sale_number'];
} elseif (!empty($payment['invoice_number'])) {
    $concept = 'Pago de factura';
    $docRef  = (string) $payment['invoice_number'];
}

$custName  = (string) ($customer['full_name'] ?? $payment['customer_name'] ?? 'Cliente');
$amount    = (float) ($payment['amount'] ?? 0);
$method    = (string) ($payment['payment_method'] ?? 'cash');
$docTitle  = 'Recibo ' . ($payment['payment_number'] ?? '') . ' · ' . $bizName;
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
    <title><?= e($docTitle) ?></title>
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
    <style>
        @page { size: A4; margin: 16mm; }
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
    <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-3">
        <p class="text-sm font-medium text-gray-500">Recibo <span class="font-semibold text-gray-900"><?= e($payment['payment_number'] ?? '') ?></span></p>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.close()" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('x', 'w-4 h-4') ?> Cerrar
            </button>
            <a href="?id=<?= (int) ($payment['id'] ?? 0) ?>&amp;pdf=1" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('download', 'w-4 h-4') ?> Descargar PDF
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('printer', 'w-4 h-4') ?> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- Documento -->
<main class="mx-auto my-6 max-w-2xl">
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
                <p class="font-serif text-2xl font-bold tracking-wide text-gray-900">RECIBO DE PAGO</p>
                <p class="mt-1 text-sm font-semibold text-brand-red"><?= e($payment['payment_number'] ?? '') ?></p>
                <p class="mt-2 text-xs text-gray-500">Fecha: <?= e(format_datetime($payment['paid_at'] ?? $payment['created_at'] ?? null)) ?></p>
            </div>
        </header>

        <div class="px-8 py-7">
            <!-- Recibimos de -->
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Recibimos de</p>
            <p class="mt-1.5 text-lg font-semibold text-gray-900"><?= e($custName) ?></p>
            <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                <?php $custDoc = $customer['document_number'] ?? ''; ?>
                <?php if ($custDoc): ?><p>Documento: <?= e($custDoc) ?></p><?php endif; ?>
                <?php $custPhone = $customer['phone'] ?? ''; ?>
                <?php if ($custPhone): ?><p><?= e($custPhone) ?></p><?php endif; ?>
            </div>

            <!-- Monto recibido destacado -->
            <div class="mt-6 flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">La suma de</p>
                    <p class="mt-1 text-3xl font-bold text-emerald-700"><?= e(money($amount)) ?></p>
                </div>
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-soft"><?= icon('banknotes', 'w-6 h-6') ?></span>
            </div>

            <!-- Detalle del pago -->
            <div class="mt-6 overflow-hidden rounded-xl border border-gray-200">
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-gray-500">Concepto</dt>
                        <dd class="font-medium text-gray-900 text-right"><?= e($concept) ?><?php if ($docRef): ?> · <span class="text-brand-red"><?= e($docRef) ?></span><?php endif; ?></dd>
                    </div>
                    <?php if (!empty($payment['product_name'])): ?>
                        <div class="flex items-center justify-between px-5 py-3">
                            <dt class="text-gray-500">Producto</dt>
                            <dd class="font-medium text-gray-900 text-right"><?= e($payment['product_name']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-gray-500">Método de pago</dt>
                        <dd class="font-medium text-gray-900"><?= e($methodLabels[$method] ?? ucfirst($method)) ?></dd>
                    </div>
                    <?php if (!empty($payment['reference'])): ?>
                        <div class="flex items-center justify-between px-5 py-3">
                            <dt class="text-gray-500">Referencia</dt>
                            <dd class="font-medium text-gray-900 text-right"><?= e($payment['reference']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-gray-500">Recibido por</dt>
                        <dd class="font-medium text-gray-900"><?= e($payment['received_by_name'] ?? '—') ?></dd>
                    </div>
                    <?php if ($remaining !== null): ?>
                        <div class="flex items-center justify-between bg-gray-50 px-5 py-3">
                            <dt class="font-semibold text-gray-700">Saldo restante</dt>
                            <dd class="font-bold <?= (float) $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money(max(0, (float) $remaining))) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if (!empty($payment['notes'])): ?>
                <div class="mt-5 rounded-xl border border-gray-100 bg-gray-50/60 px-5 py-3 text-sm text-gray-600">
                    <span class="font-medium text-gray-700">Notas:</span> <?= e($payment['notes']) ?>
                </div>
            <?php endif; ?>

            <!-- Firma -->
            <div class="mt-12 flex justify-end">
                <div class="w-56 text-center">
                    <div class="border-t border-gray-400 pt-2 text-xs text-gray-500">Firma y sello</div>
                    <p class="mt-1 text-sm font-medium text-gray-700"><?= e($bizName) ?></p>
                </div>
            </div>
        </div>

        <!-- Pie -->
        <footer class="border-t border-gray-100 bg-gray-50/60 px-8 py-5 text-center">
            <p class="font-script text-xl text-brand-red">Gracias por su preferencia</p>
            <p class="mt-1 text-[11px] text-gray-400">Documento generado el <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></p>
        </footer>
    </article>
</main>

</body>
</html>
