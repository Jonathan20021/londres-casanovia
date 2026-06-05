<?php
/**
 * Solicitud de alquiler pública — LONDRES Casa de Novias.
 *
 *  - GET  ?product=ID : muestra resumen del producto + formulario completo
 *                        (por si el visitante llega directo a esta URL).
 *  - POST              : valida datos y fechas, RE-VALIDA disponibilidad en
 *                        servidor, inserta rental_request, notifica a los
 *                        administradores y redirige a confirmacion.php?req=ID.
 *
 * Nunca expone datos de clientes anteriores. Siempre usa prepared statements
 * (helpers db_*) y verifica el token CSRF en POST.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php'; // public/*.php => N=1

/** Mensaje exacto requerido cuando la fecha está ocupada. */
const SOLICITUD_NO_DISPONIBLE = 'Este vestido/traje ya está reservado para la fecha seleccionada. Por favor seleccione otra fecha.';

/**
 * Carga un producto público apto para alquiler. Devuelve null si no aplica.
 */
function cargar_producto_alquilable(int $pid): ?array
{
    if ($pid <= 0) return null;
    $p = db_one(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = :id AND p.status = 'active'
         LIMIT 1",
        ['id' => $pid]
    );
    return $p ?: null;
}

/* ================================================================== *
 *  MANEJO POST (antes de imprimir HTML)
 * ================================================================== */
if (is_post()) {
    require_csrf();

    $productId = (int) post('product_id', 0);
    $product   = cargar_producto_alquilable($productId);

    // Datos del solicitante
    $fullName = trim((string) post('full_name', ''));
    $phone    = trim((string) post('phone', ''));
    $email    = trim((string) post('email', ''));
    $message  = trim((string) post('message', ''));
    $eventDate    = trim((string) post('event_date', ''));
    $deliveryDate = trim((string) post('delivery_date', ''));
    $returnDate   = trim((string) post('return_date', ''));

    // Volver al detalle del producto con un flash de error.
    $backToProduct = function (string $msg) use ($product, $productId) {
        flash('error', $msg);
        if ($product && !empty($product['slug'])) {
            redirect(pub_url('producto.php?slug=' . urlencode((string) $product['slug'])));
        }
        redirect(pub_url('producto.php?id=' . $productId));
    };

    // --- Validaciones de existencia / tipo ---
    if (!$product) {
        flash('error', 'El producto solicitado no está disponible.');
        redirect(pub_url('inventario.php'));
    }
    $canRent = in_array($product['type'], ['rental', 'both'], true) && (float) $product['rental_price'] > 0;
    if (!$canRent) {
        $backToProduct('Esta pieza no está disponible para alquiler.');
    }

    // --- Validaciones de campos ---
    $errors = [];
    if ($fullName === '')                         $errors[] = 'Indica tu nombre completo.';
    if ($phone === '')                            $errors[] = 'Indica un teléfono de contacto.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El correo electrónico no es válido.';

    // --- Validaciones de fechas ---
    $tsDelivery = strtotime($deliveryDate);
    $tsReturn   = strtotime($returnDate);
    if ($deliveryDate === '' || $returnDate === '' || $tsDelivery === false || $tsReturn === false) {
        $errors[] = 'Selecciona las fechas de entrega y devolución.';
    } elseif ($tsReturn < $tsDelivery) {
        $errors[] = 'La fecha de devolución no puede ser anterior a la de entrega.';
    } elseif ($tsDelivery < strtotime(date('Y-m-d'))) {
        $errors[] = 'La fecha de entrega no puede estar en el pasado.';
    }
    // event_date es opcional; si viene, debe ser una fecha válida
    if ($eventDate !== '' && strtotime($eventDate) === false) {
        $errors[] = 'La fecha del evento no es válida.';
    }

    if (!empty($errors)) {
        $backToProduct(implode(' ', $errors));
    }

    // --- RE-VALIDACIÓN de disponibilidad en el servidor (regla de negocio) ---
    $avail = checkProductAvailability($productId, $deliveryDate, $returnDate);
    if (!empty($avail['error'])) {
        $backToProduct($avail['error']);
    }
    if (!$avail['available']) {
        // No exponer datos del cliente anterior: mensaje público estándar.
        $backToProduct(SOLICITUD_NO_DISPONIBLE);
    }

    // --- Insertar la solicitud ---
    $requestId = db_insert('rental_requests', [
        'customer_id'   => null,
        'product_id'    => $productId,
        'full_name'     => $fullName,
        'phone'         => $phone,
        'email'         => $email !== '' ? $email : null,
        'event_date'    => $eventDate !== '' ? date('Y-m-d', strtotime($eventDate)) : null,
        'delivery_date' => date('Y-m-d', $tsDelivery),
        'return_date'   => date('Y-m-d', $tsReturn),
        'message'       => $message !== '' ? $message : null,
        'status'        => 'pending',
        'source'        => 'public',
    ]);

    // Notificar a los administradores
    notify_admins(
        'Nueva solicitud de alquiler',
        $fullName . ' solicitó ' . $product['name']
    );
    log_activity('rental_request.create', 'rental_request', $requestId,
        'Solicitud pública de ' . $fullName . ' para ' . $product['name']);

    redirect(pub_url('confirmacion.php?req=' . $requestId));
}

/* ================================================================== *
 *  GET — mostrar resumen del producto + formulario
 * ================================================================== */
$productId = (int) get_param('product');
$product   = cargar_producto_alquilable($productId);

$page_title    = 'Solicitar alquiler';
$active_public = 'inventario';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">

    <?= render_flash() ?>

    <?php if (!$product): ?>
        <?= empty_state(
            'Producto no encontrado',
            'No pudimos encontrar la pieza que deseas solicitar. Explora nuestro inventario disponible.',
            'inbox',
            '<a href="' . e(pub_url('inventario.php')) . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('squares', 'w-4 h-4') . ' Ver inventario</a>'
        ) ?>
    <?php else:
        $rentalPrice = (float) $product['rental_price'];
        $initial50   = round($rentalPrice * 0.5, 2);
        $canRent     = in_array($product['type'], ['rental', 'both'], true) && $rentalPrice > 0;
        $rentBlocked = in_array($product['commercial_status'], ['rented', 'sold', 'unavailable', 'maintenance'], true);
        $defaultEvent = date('Y-m-d', strtotime('+14 days'));
    ?>

        <div class="text-center">
            <p class="font-script text-3xl text-brand-red">Casi listo</p>
            <h1 class="font-serif text-3xl text-brand-dark sm:text-4xl">Solicita tu alquiler</h1>
            <p class="mx-auto mt-2 max-w-xl text-gray-500">
                Verifica la disponibilidad para la fecha de tu evento y déjanos tus datos.
                Confirmaremos tu reserva a la brevedad.
            </p>
        </div>

        <!-- Resumen del producto -->
        <div class="mt-10 flex flex-col gap-5 rounded-3xl border border-gray-100 bg-white p-5 shadow-soft sm:flex-row sm:items-center">
            <a href="<?= e(pub_url('producto.php?slug=' . urlencode((string) $product['slug']))) ?>" class="block shrink-0">
                <img src="<?= e(upload_url($product['main_image'])) ?>"
                     alt="<?= e($product['name']) ?>"
                     class="h-40 w-full rounded-2xl object-cover sm:h-28 sm:w-28">
            </a>
            <div class="flex-1">
                <?php if (!empty($product['category_name'])): ?>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-red"><?= e($product['category_name']) ?></p>
                <?php endif; ?>
                <h2 class="font-serif text-xl text-brand-dark"><?= e($product['name']) ?></h2>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <?= status_badge($product['commercial_status'], 'commercial') ?>
                    <?php if ($canRent): ?>
                        <span class="text-sm text-gray-500">Alquiler <span class="font-semibold text-gray-900"><?= e(money($rentalPrice)) ?></span></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($canRent): ?>
            <div class="rounded-2xl bg-brand-cream/70 px-4 py-3 text-center sm:text-right">
                <p class="text-xs font-medium text-gray-500">Depósito inicial 50%</p>
                <p class="text-lg font-semibold text-brand-red"><?= e(money($initial50)) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$canRent || $rentBlocked): ?>
            <!-- No es posible solicitar el alquiler de esta pieza ahora mismo -->
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                <?php if (!$canRent): ?>
                    Esta pieza no está disponible para alquiler. Contáctanos para conocer otras opciones.
                <?php else: ?>
                    Esta pieza no está disponible para alquiler en este momento. Te invitamos a explorar otras piezas.
                <?php endif; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="<?= e(pub_url('inventario.php')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('squares', 'w-4 h-4') ?> Ver inventario disponible
                </a>
            </div>
        <?php else: ?>

            <!-- Verificar disponibilidad -->
            <div class="mt-8 rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
                <h3 class="flex items-center gap-2 font-serif text-lg text-brand-dark">
                    <?= icon('calendar', 'w-5 h-5') ?> Fechas del alquiler
                </h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="fEvento" class="lcn-label">Fecha de tu evento</label>
                        <input type="date" id="fEvento" min="<?= e(date('Y-m-d')) ?>" value="<?= e($defaultEvent) ?>" class="lcn-input">
                    </div>
                    <div>
                        <label for="fEntrega" class="lcn-label">Entrega</label>
                        <input type="date" id="fEntrega" min="<?= e(date('Y-m-d')) ?>" class="lcn-input">
                    </div>
                    <div>
                        <label for="fDevolucion" class="lcn-label">Devolución</label>
                        <input type="date" id="fDevolucion" min="<?= e(date('Y-m-d')) ?>" class="lcn-input">
                    </div>
                </div>

                <button type="button"
                        data-public-availability
                        data-product="<?= (int) $product['id'] ?>"
                        data-event="#fEvento"
                        data-delivery="#fEntrega"
                        data-return="#fDevolucion"
                        data-result="#boxDisp"
                        data-form="#formSolic"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('check', 'w-4 h-4') ?> Verificar disponibilidad
                </button>

                <div id="boxDisp" class="mt-4"></div>
            </div>

            <!-- Formulario de solicitud (oculto hasta verificar disponible) -->
            <form id="formSolic" method="post" action="<?= e(pub_url('solicitud-alquiler.php')) ?>" class="mt-6 hidden rounded-3xl border border-gray-100 bg-white p-6 shadow-soft">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="event_date"    id="hEvento"     value="">
                <input type="hidden" name="delivery_date" id="hEntrega"    value="">
                <input type="hidden" name="return_date"   id="hDevolucion" value="">

                <h3 class="font-serif text-lg text-brand-dark">Tus datos</h3>
                <p class="mt-1 text-sm text-gray-500">Te contactaremos para confirmar la reserva y el depósito inicial.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="fNombre" class="lcn-label">Nombre completo *</label>
                        <input type="text" id="fNombre" name="full_name" required maxlength="150" class="lcn-input" placeholder="Tu nombre y apellido">
                    </div>
                    <div>
                        <label for="fTelefono" class="lcn-label">Teléfono / WhatsApp *</label>
                        <input type="tel" id="fTelefono" name="phone" required maxlength="40" class="lcn-input" placeholder="+1 809 000 0000">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="fEmail" class="lcn-label">Correo electrónico</label>
                        <input type="email" id="fEmail" name="email" maxlength="150" class="lcn-input" placeholder="tucorreo@email.com">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="fMensaje" class="lcn-label">Mensaje (opcional)</label>
                        <textarea id="fMensaje" name="message" rows="3" class="lcn-input" placeholder="Cuéntanos sobre tu evento…"></textarea>
                    </div>
                </div>

                <button type="submit" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('heart', 'w-4 h-4') ?> Enviar solicitud de alquiler
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-gray-400">
                El formulario se habilitará al confirmar la disponibilidad de tus fechas.
            </p>

            <!-- JS inline: sincronizar fechas hacia los hidden del formulario -->
            <script>
            (function () {
              'use strict';
              function sync(srcId, dstId) {
                var src = document.getElementById(srcId);
                var dst = document.getElementById(dstId);
                if (!src || !dst) return;
                var copy = function () { dst.value = src.value; };
                src.addEventListener('change', copy);
                copy();
              }
              sync('fEvento', 'hEvento');
              sync('fEntrega', 'hEntrega');
              sync('fDevolucion', 'hDevolucion');

              var btnCheck = document.querySelector('[data-public-availability]');
              if (btnCheck) {
                btnCheck.addEventListener('click', function () {
                  setTimeout(function () {
                    var e = document.getElementById('fEvento');
                    var d = document.getElementById('fEntrega');
                    var r = document.getElementById('fDevolucion');
                    if (e) document.getElementById('hEvento').value = e.value;
                    if (d) document.getElementById('hEntrega').value = d.value;
                    if (r) document.getElementById('hDevolucion').value = r.value;
                  }, 50);
                });
              }
            })();
            </script>

        <?php endif; ?>

    <?php endif; ?>
</section>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
