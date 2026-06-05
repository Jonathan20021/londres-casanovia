<?php
/**
 * Confirmación de solicitud de alquiler — LONDRES Casa de Novias.
 *
 * Lee ?req=ID. Muestra una página elegante de agradecimiento con el número
 * de solicitud, el producto, el monto inicial 50% a abonar y accesos rápidos.
 * No expone datos de otros clientes ni notas internas.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php'; // public/*.php => N=1

$reqId = (int) get_param('req');

/* Cargar la solicitud junto al producto (solo lo necesario para mostrar). */
$request = null;
if ($reqId > 0) {
    $request = db_one(
        "SELECT rr.id, rr.full_name, rr.phone, rr.email,
                rr.event_date, rr.delivery_date, rr.return_date, rr.status,
                p.name AS product_name, p.slug AS product_slug,
                p.rental_price, p.main_image
         FROM rental_requests rr
         LEFT JOIN products p ON p.id = rr.product_id
         WHERE rr.id = :id AND rr.source = 'public'
         LIMIT 1",
        ['id' => $reqId]
    );
}

$page_title    = 'Solicitud recibida';
$active_public = 'inventario';

// Datos de contacto del negocio para el botón de WhatsApp
$waBiz = preg_replace('/\D/', '', (string) setting('whatsapp', ''));

require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<section class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8">

    <?php if (!$request): ?>
        <!-- Solicitud no localizada -->
        <div class="text-center">
            <span class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-amber-50 text-amber-500">
                <?= icon('warning', 'w-10 h-10') ?>
            </span>
            <h1 class="font-serif text-3xl text-brand-dark">No encontramos esta solicitud</h1>
            <p class="mx-auto mt-3 max-w-md text-gray-500">
                Es posible que el enlace haya expirado. Si ya enviaste tu solicitud, nuestro equipo
                la revisará y te contactará pronto.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="<?= e(pub_url('inventario.php')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('squares', 'w-4 h-4') ?> Ver inventario
                </a>
                <a href="<?= e(pub_url('index.php')) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('home', 'w-4 h-4') ?> Volver al inicio
                </a>
            </div>
        </div>

    <?php else:
        $rentalPrice = (float) ($request['rental_price'] ?? 0);
        $initial50   = round($rentalPrice * 0.5, 2);
        $reqNumber   = sprintf('SOL-%05d', (int) $request['id']);

        // Mensaje pre-rellenado para WhatsApp (sin datos de terceros)
        $waText = 'Hola, soy ' . ($request['full_name'] ?? '')
                . '. Acabo de enviar la solicitud ' . $reqNumber
                . ' para la pieza "' . ($request['product_name'] ?? '') . '". Quisiera confirmar mi reserva.';
        $waHref = $waBiz ? 'https://wa.me/' . $waBiz . '?text=' . rawurlencode($waText) : '';
    ?>

        <div class="text-center">
            <!-- Ícono check grande con halo -->
            <span class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 ring-8 ring-emerald-50/60 animate-scale-in">
                <?= icon('check', 'w-12 h-12') ?>
            </span>

            <p class="font-script text-3xl text-brand-red">¡Gracias!</p>
            <h1 class="mt-1 font-serif text-3xl text-brand-dark sm:text-4xl">¡Solicitud recibida!</h1>
            <p class="mx-auto mt-3 max-w-md text-gray-500">
                Hemos recibido tu solicitud correctamente. Nuestro equipo confirmará la disponibilidad
                y se pondrá en contacto contigo muy pronto para reservar tu pieza.
            </p>

            <!-- Número de solicitud -->
            <div class="mx-auto mt-6 inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm shadow-soft">
                <span class="text-gray-400">N.º de solicitud</span>
                <span class="font-semibold tracking-wide text-brand-dark">#<?= e($reqNumber) ?></span>
                <?= status_badge($request['status'], 'request') ?>
            </div>
        </div>

        <!-- Detalle de la solicitud -->
        <div class="mt-10 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft">
            <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                <img src="<?= e(upload_url($request['main_image'])) ?>"
                     alt="<?= e($request['product_name'] ?? 'Producto') ?>"
                     class="h-44 w-full rounded-2xl object-cover sm:h-24 sm:w-24">
                <div class="flex-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pieza solicitada</p>
                    <h2 class="font-serif text-xl text-brand-dark"><?= e($request['product_name'] ?? 'Producto') ?></h2>
                    <?php if (!empty($request['event_date'])): ?>
                        <p class="mt-1 text-sm text-gray-500">
                            <?= icon('calendar', 'w-4 h-4 inline-block -mt-0.5 text-brand-red') ?>
                            Evento: <span class="font-medium text-gray-700"><?= e(format_date($request['event_date'])) ?></span>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($request['delivery_date']) && !empty($request['return_date'])): ?>
                        <p class="mt-0.5 text-sm text-gray-500">
                            Entrega <span class="font-medium text-gray-700"><?= e(format_date($request['delivery_date'])) ?></span>
                            · Devolución <span class="font-medium text-gray-700"><?= e(format_date($request['return_date'])) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($rentalPrice > 0): ?>
            <!-- Monto inicial 50% a abonar -->
            <div class="border-t border-gray-100 bg-brand-cream/60 p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Para confirmar tu reserva</p>
                        <p class="mt-1 text-sm text-gray-600">Depósito inicial del 50% del valor del alquiler</p>
                    </div>
                    <div class="text-right">
                        <p class="font-serif text-3xl text-brand-red"><?= e(money($initial50)) ?></p>
                        <p class="mt-0.5 text-xs text-gray-400">Alquiler total: <?= e(money($rentalPrice)) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Próximos pasos -->
        <div class="mt-8 rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
            <h3 class="flex items-center gap-2 font-serif text-lg text-brand-dark"><?= icon('sparkles', 'w-5 h-5 text-brand-gold') ?> ¿Qué sigue?</h3>
            <ol class="mt-4 space-y-3 text-sm text-gray-600">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-semibold text-brand-red">1</span>
                    Revisaremos la disponibilidad de tu pieza para las fechas indicadas.
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-semibold text-brand-red">2</span>
                    Te contactaremos por teléfono o WhatsApp para confirmar los detalles.
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-semibold text-brand-red">3</span>
                    Con el depósito inicial del 50% tu reserva quedará confirmada.
                </li>
            </ol>
        </div>

        <!-- Acciones -->
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-center">
            <a href="<?= e(pub_url('index.php')) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('home', 'w-4 h-4') ?> Volver al inicio
            </a>
            <a href="<?= e(pub_url('inventario.php')) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                <?= icon('squares', 'w-4 h-4') ?> Ver más inventario
            </a>
            <?php if ($waHref): ?>
            <a href="<?= e($waHref) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('whatsapp', 'w-4 h-4') ?> Confirmar por WhatsApp
            </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</section>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
