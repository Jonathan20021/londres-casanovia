<?php
/**
 * Plantilla imprimible de CONTRATO DE ALQUILER — LONDRES Casa de Novias
 *
 * Documento HTML completo y autónomo para window.print().
 *
 * Variables esperadas (cargadas por admin/alquileres/contrato.php):
 *   $rental   array  Fila de rentals (+ payment_status, rental_status, montos, fechas, condiciones)
 *   $customer array  Datos del cliente (full_name, phone, whatsapp, email, document_number, address)
 *   $product  array  Datos del producto (name, sku, size, color, material, condition_status, category_name)
 *   $business array  settings_all()
 *
 * El contrato incluye: datos del negocio y del cliente, producto, fechas de
 * evento/entrega/devolución, montos (total, inicial, saldo), políticas de
 * alquiler/devolución, penalidad por retraso, estado del producto al entregar
 * y líneas de firma del cliente y del negocio.
 *
 * Reglas: salida escapada con e(); montos con money(); fechas con format_date().
 */
declare(strict_types=1);

if (!defined('LCN_ROOT')) { exit('Acceso no permitido.'); }

$rental   = $rental   ?? [];
$customer = $customer ?? [];
$product  = $product  ?? [];
$business = $business ?? settings_all();

/* Datos del negocio */
$bizName    = (string) ($business['business_name'] ?? setting('business_name', APP_NAME));
$bizPhone   = (string) ($business['phone']    ?? '');
$bizWhats   = (string) ($business['whatsapp'] ?? '');
$bizEmail   = (string) ($business['email']    ?? '');
$bizAddress = (string) ($business['address']  ?? '');
$rentalPolicy = (string) ($business['rental_policy'] ?? '');
$returnPolicy = (string) ($business['return_policy'] ?? '');

/* Montos del alquiler */
$rentalPrice = (float) ($rental['rental_price']             ?? 0);
$discount    = (float) ($rental['discount']                 ?? 0);
$latePenalty = (float) ($rental['late_penalty']             ?? 0);
$total       = (float) ($rental['total_amount']             ?? 0);
$initialReq  = (float) ($rental['initial_payment_required'] ?? 0);
$initialPaid = (float) ($rental['initial_payment_paid']     ?? 0);
$balance     = (float) ($rental['remaining_balance']        ?? 0);

$custName  = (string) ($customer['full_name'] ?? 'Cliente');
$docTitle  = 'Contrato ' . ($rental['rental_number'] ?? '') . ' · ' . $bizName;
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
    <title><?= e($docTitle) ?></title>
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
    <style>
        @page { size: A4; margin: 14mm; }
        body  { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @media print {
            .no-print { display: none !important; }
            .print-area { box-shadow: none !important; border: 0 !important; margin: 0 !important; max-width: 100% !important; }
            .break-avoid { break-inside: avoid; page-break-inside: avoid; }
            html, body { background: #fff !important; }
        }
    </style>
</head>
<body class="min-h-full bg-gray-100 font-sans text-gray-800 antialiased">

<!-- Barra de acciones (no se imprime) -->
<div class="no-print sticky top-0 z-10 border-b border-gray-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
        <p class="text-sm font-medium text-gray-500">Contrato <span class="font-semibold text-gray-900"><?= e($rental['rental_number'] ?? '') ?></span></p>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.close()" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('x', 'w-4 h-4') ?> Cerrar
            </button>
            <a href="?id=<?= (int) ($rental['id'] ?? 0) ?>&amp;pdf=1" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
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
                <p class="font-serif text-2xl font-bold tracking-wide text-gray-900">CONTRATO DE ALQUILER</p>
                <p class="mt-1 text-sm font-semibold text-brand-red"><?= e($rental['rental_number'] ?? '') ?></p>
                <div class="mt-2 flex sm:justify-end"><?= status_badge($rental['rental_status'] ?? 'pending', 'rental') ?></div>
                <p class="mt-2 text-xs text-gray-500">Fecha: <?= e(format_date($rental['created_at'] ?? date('Y-m-d'))) ?></p>
            </div>
        </header>

        <div class="px-8 py-7">
            <!-- Partes del contrato -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div class="break-avoid rounded-xl border border-gray-100 bg-gray-50/50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">El arrendador</p>
                    <p class="mt-1.5 font-semibold text-gray-900"><?= e($bizName) ?></p>
                    <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                        <?php if ($bizAddress): ?><p><?= e($bizAddress) ?></p><?php endif; ?>
                        <?php if ($bizPhone): ?><p>Tel.: <?= e($bizPhone) ?></p><?php endif; ?>
                        <?php if ($bizEmail): ?><p><?= e($bizEmail) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="break-avoid rounded-xl border border-gray-100 bg-gray-50/50 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">El cliente</p>
                    <p class="mt-1.5 font-semibold text-gray-900"><?= e($custName) ?></p>
                    <div class="mt-1 space-y-0.5 text-sm text-gray-500">
                        <?php if (!empty($customer['document_number'])): ?><p>Documento: <?= e($customer['document_number']) ?></p><?php endif; ?>
                        <?php if (!empty($customer['phone'])): ?><p>Tel.: <?= e($customer['phone']) ?></p><?php endif; ?>
                        <?php if (!empty($customer['email'])): ?><p><?= e($customer['email']) ?></p><?php endif; ?>
                        <?php if (!empty($customer['address'])): ?><p><?= e($customer['address']) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Productos alquilados -->
            <div class="mt-7 break-avoid">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= count($products ?? []) === 1 ? 'Pieza en alquiler' : 'Piezas en alquiler' ?></p>
                <div class="mt-2 overflow-hidden rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Características</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach (($products ?? [$product]) as $contractProduct): ?>
                                <tr>
                                    <td class="px-5 py-3 text-gray-900">
                                        <p class="font-medium"><?= e($contractProduct['name'] ?? '—') ?></p>
                                        <p class="text-xs text-gray-400"><?= e($contractProduct['barcode'] ?? $contractProduct['sku'] ?? '') ?></p>
                                    </td>
                                    <td class="px-5 py-3 text-gray-600"><?= e(implode(' · ', array_filter([
                                        $contractProduct['category_name'] ?? '',
                                        !empty($contractProduct['size']) ? 'Talla ' . $contractProduct['size'] : '',
                                        $contractProduct['color'] ?? '',
                                        $contractProduct['material'] ?? '',
                                    ]))) ?></td>
                                    <td class="px-5 py-3 text-right font-medium text-gray-900"><?= e(money($contractProduct['unit_price'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Fechas -->
            <div class="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-3 break-avoid">
                <div class="rounded-xl border border-gray-200 px-5 py-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Evento</p>
                    <p class="mt-1.5 text-base font-semibold text-gray-900"><?= e(format_date($rental['event_date'] ?? null)) ?></p>
                </div>
                <div class="rounded-xl border border-gray-200 px-5 py-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Entrega</p>
                    <p class="mt-1.5 text-base font-semibold text-gray-900"><?= e(format_date($rental['delivery_date'] ?? null)) ?></p>
                </div>
                <div class="rounded-xl border border-gray-200 px-5 py-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Devolución</p>
                    <p class="mt-1.5 text-base font-semibold text-gray-900"><?= e(format_date($rental['return_date'] ?? null)) ?></p>
                </div>
            </div>

            <!-- Resumen económico -->
            <div class="mt-7 flex justify-end break-avoid">
                <div class="w-full max-w-sm space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Precio de alquiler</span><span class="font-medium text-gray-800"><?= e(money($rentalPrice)) ?></span></div>
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span class="font-medium text-rose-600">- <?= e(money($discount)) ?></span></div>
                    <?php endif; ?>
                    <?php if ($latePenalty > 0): ?>
                        <div class="flex justify-between"><span class="text-gray-500">Penalidad por retraso</span><span class="font-medium text-gray-800"><?= e(money($latePenalty)) ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between border-t border-gray-200 pt-2"><span class="font-semibold text-gray-900">Total</span><span class="text-lg font-bold text-gray-900"><?= e(money($total)) ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Pago inicial<?php if ($initialReq > 0): ?> (requerido <?= e(money($initialReq)) ?>)<?php endif; ?></span><span class="font-medium text-emerald-600"><?= e(money($initialPaid)) ?></span></div>
                    <div class="flex justify-between rounded-xl bg-gray-50 px-3 py-2"><span class="font-semibold text-gray-900">Saldo pendiente</span><span class="font-bold <?= $balance > 0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= e(money($balance)) ?></span></div>
                    <div class="flex justify-between pt-1"><span class="text-gray-500">Estado de pago</span><span><?= status_badge($rental['payment_status'] ?? 'pending', 'payment') ?></span></div>
                </div>
            </div>

            <!-- Estado del producto al entregar -->
            <div class="mt-7 break-avoid rounded-xl border border-gray-100 bg-gray-50/50 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Estado del producto al entregar</p>
                <p class="mt-1.5 text-sm text-gray-700">
                    <?php
                    $deliveryCond = trim((string) ($rental['delivery_condition'] ?? ''));
                    if ($deliveryCond !== '') {
                        echo e($deliveryCond);
                    } elseif (!empty($product['condition_status'])) {
                        echo 'Condición física registrada de las piezas: ' . status_badge($product['condition_status'], 'condition');
                    } else {
                        echo 'La pieza se entrega en óptimas condiciones, lista para su uso.';
                    }
                    ?>
                </p>
                <?php if (!empty($rental['delivery_notes'])): ?>
                    <p class="mt-2 text-sm text-gray-500"><span class="font-medium text-gray-600">Observaciones de entrega:</span> <?= e($rental['delivery_notes']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Condiciones / políticas -->
            <div class="mt-7 break-avoid">
                <h2 class="font-serif text-lg font-semibold text-gray-900">Condiciones del alquiler</h2>
                <div class="mt-3 space-y-4 text-sm leading-relaxed text-gray-600">
                    <?php if ($rentalPolicy !== ''): ?>
                        <div>
                            <p class="font-medium text-gray-800">Política de reserva y pago</p>
                            <p class="mt-1 whitespace-pre-line"><?= e($rentalPolicy) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($returnPolicy !== ''): ?>
                        <div>
                            <p class="font-medium text-gray-800">Política de devolución</p>
                            <p class="mt-1 whitespace-pre-line"><?= e($returnPolicy) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($latePenalty > 0): ?>
                        <div>
                            <p class="font-medium text-gray-800">Penalidad por retraso</p>
                            <p class="mt-1">El retraso en la devolución de la pieza genera una penalidad de <span class="font-semibold text-gray-900"><?= e(money($latePenalty)) ?></span>, según las condiciones acordadas.</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($rentalPolicy === '' && $returnPolicy === ''): ?>
                        <p>El cliente declara haber recibido la pieza en las condiciones descritas y se compromete a devolverla en la fecha pactada y en el mismo estado. Cualquier daño será evaluado y cobrado según corresponda.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Firmas -->
            <div class="mt-12 grid grid-cols-1 gap-10 sm:grid-cols-2 break-avoid">
                <div class="text-center">
                    <div class="border-t border-gray-400 pt-2 text-xs text-gray-500">Firma del cliente</div>
                    <p class="mt-1 text-sm font-medium text-gray-700"><?= e($custName) ?></p>
                    <?php if (!empty($customer['document_number'])): ?>
                        <p class="text-xs text-gray-400">Doc.: <?= e($customer['document_number']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="text-center">
                    <div class="border-t border-gray-400 pt-2 text-xs text-gray-500">Firma y sello del negocio</div>
                    <p class="mt-1 text-sm font-medium text-gray-700"><?= e($bizName) ?></p>
                </div>
            </div>
        </div>

        <!-- Pie -->
        <footer class="border-t border-gray-100 bg-gray-50/60 px-8 py-5 text-center">
            <p class="font-script text-xl text-brand-red">Gracias por confiar en nosotros</p>
            <p class="mt-1 text-[11px] text-gray-400">Documento generado el <?= e(format_datetime(date('Y-m-d H:i:s'))) ?></p>
        </footer>
    </article>
</main>

</body>
</html>
