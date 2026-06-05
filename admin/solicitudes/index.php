<?php
/**
 * Solicitudes de alquiler (bandeja de entrada).
 * LONDRES Casa de Novias — Panel administrativo.
 *
 * Lista las rental_requests recibidas (públicas o internas), permite filtrar
 * por estado, ver el detalle en un modal con verificación de disponibilidad y
 * gestionar el flujo: marcar revisada, rechazar o cancelar. La conversión en
 * alquiler enlaza al formulario de creación pasando el id de la solicitud.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('requests.manage');

/* ------------------------------------------------------------------ *
 *  Manejo de POST (acciones de estado) — SIEMPRE antes del HTML
 * ------------------------------------------------------------------ */
if (is_post()) {
    require_csrf();

    $action    = (string) post('action', '');
    $requestId = (int) post('id', 0);

    // Mapa de acción -> [nuevo estado, etiqueta para log/flash]
    $transitions = [
        'review' => ['reviewed',  'revisada'],
        'reject' => ['rejected',  'rechazada'],
        'cancel' => ['cancelled', 'cancelada'],
    ];

    $request = $requestId > 0
        ? db_one('SELECT * FROM rental_requests WHERE id = :id', ['id' => $requestId])
        : null;

    if (!$request) {
        flash('error', 'La solicitud indicada no existe.');
        redirect(admin_url('solicitudes/index.php'));
    }

    if (!isset($transitions[$action])) {
        flash('error', 'Acción no válida.');
        redirect(admin_url('solicitudes/index.php'));
    }

    // No permitir mover una solicitud ya convertida (tiene alquiler asociado).
    if ($request['status'] === 'converted') {
        flash('warning', 'Esta solicitud ya fue convertida en alquiler y no puede modificarse.');
        redirect(admin_url('solicitudes/index.php'));
    }

    [$newStatus, $label] = $transitions[$action];

    db_update('rental_requests', ['status' => $newStatus], 'id = :id', ['id' => $requestId]);

    $solicitante = $request['full_name'] ?: 'Solicitud #' . $requestId;
    log_activity(
        'request.' . $action,
        'rental_request',
        $requestId,
        'Solicitud de ' . $solicitante . ' marcada como ' . $label
    );

    flash('success', 'Solicitud ' . $label . ' correctamente.');
    redirect(admin_url('solicitudes/index.php'));
}

/* ------------------------------------------------------------------ *
 *  Filtros y consultas para mostrar
 * ------------------------------------------------------------------ */
$statusFilter = get_param('status');
$validStatuses = ['pending', 'reviewed', 'converted', 'rejected', 'cancelled'];

$where  = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $where = ' WHERE rr.status = :status';
    $params['status'] = $statusFilter;
} else {
    $statusFilter = ''; // normaliza valores inválidos a "todas"
}

$requests = db_all(
    'SELECT rr.*,
            p.name AS product_name, p.slug AS product_slug, p.main_image AS product_image,
            p.rental_price AS product_rental_price, p.commercial_status AS product_commercial_status,
            c.full_name AS customer_full_name, c.phone AS customer_phone, c.whatsapp AS customer_whatsapp
     FROM rental_requests rr
     LEFT JOIN products  p ON p.id = rr.product_id
     LEFT JOIN customers c ON c.id = rr.customer_id'
    . $where .
    ' ORDER BY FIELD(rr.status, "pending", "reviewed", "converted", "rejected", "cancelled"), rr.created_at DESC',
    $params
);

/* Conteos por estado para las pestañas de filtro */
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS total FROM rental_requests GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['total'];
}
$totalAll = array_sum($counts);

/* Pestañas de filtro: clave => etiqueta */
$tabs = [
    ''          => 'Todas',
    'pending'   => 'Pendientes',
    'reviewed'  => 'Revisadas',
    'converted' => 'Convertidas',
    'rejected'  => 'Rechazadas',
    'cancelled' => 'Canceladas',
];

/* Solo dígitos del WhatsApp del negocio (fallback para mensajes salientes) */
$bizWhatsapp = preg_replace('/\D+/', '', (string) setting('whatsapp', ''));

/* Clases reutilizables (paleta del proyecto) */
$BTN_PRIMARY   = 'inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700';
$BTN_SECONDARY = 'inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50';
$BTN_DANGER    = 'inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700';

$page_title    = 'Solicitudes de alquiler';
$page_subtitle = 'Gestiona las solicitudes recibidas desde el sitio público y conviértelas en alquileres.';
$active        = 'solicitudes';
require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Pestañas de filtro por estado -->
<div class="mb-6 flex flex-wrap items-center gap-2">
    <?php foreach ($tabs as $key => $label):
        $count    = $key === '' ? $totalAll : ($counts[$key] ?? 0);
        $isActive = $statusFilter === $key; ?>
        <a href="<?= e(query_url(['status' => $key, 'page' => null])) ?>"
           class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition
                  <?= $isActive
                        ? 'bg-brand-red text-white shadow-sm'
                        : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' ?>">
            <?= e($label) ?>
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-semibold
                         <?= $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' ?>">
                <?= (int) $count ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$requests): ?>

    <?= empty_state(
        $statusFilter === '' ? 'Sin solicitudes' : 'Sin solicitudes en este estado',
        $statusFilter === ''
            ? 'Cuando un cliente solicite un vestido o traje desde el sitio público, aparecerá aquí para su gestión.'
            : 'Prueba con otro filtro para ver más solicitudes.',
        'inbox',
        '<a href="' . e(admin_url('solicitudes/index.php')) . '" class="' . e($BTN_SECONDARY) . '">' . icon('filter', 'w-4 h-4') . ' Ver todas</a>'
    ) ?>

<?php else: ?>

    <!-- Tabla de solicitudes -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recibida</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Solicitante</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Producto</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Evento</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Entrega / Devolución</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($requests as $r):
                        // Cliente: usa los datos del customer enlazado o, si es null, los del propio request.
                        $name  = $r['customer_full_name'] ?: $r['full_name'];
                        $name  = $name !== null && $name !== '' ? $name : 'Sin nombre';
                        $phone = $r['customer_phone'] ?: $r['phone'];
                        $email = $r['email']; // el correo del solicitante público vive en el request
                        $modalId = 'modal-req-' . (int) $r['id']; ?>
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-4 text-gray-700">
                                <div class="font-medium text-gray-900"><?= e(format_date($r['created_at'])) ?></div>
                                <div class="text-xs text-gray-400"><?= e(format_date($r['created_at'], 'h:i A')) ?></div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <div class="flex items-center gap-3">
                                    <?= avatar($name, 'h-9 w-9 text-xs') ?>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-gray-900"><?= e($name) ?></div>
                                        <?php if (!empty($phone)): ?>
                                            <div class="truncate text-xs text-gray-400"><?= e($phone) ?></div>
                                        <?php elseif (!empty($email)): ?>
                                            <div class="truncate text-xs text-gray-400"><?= e($email) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <?php if (!empty($r['product_name'])): ?>
                                    <div class="flex items-center gap-3">
                                        <img src="<?= e(upload_url($r['product_image'])) ?>" alt="<?= e($r['product_name']) ?>"
                                             class="h-11 w-9 flex-shrink-0 rounded-lg object-cover ring-1 ring-gray-100">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-gray-900"><?= e($r['product_name']) ?></div>
                                            <div class="text-xs text-gray-400"><?= e(money($r['product_rental_price'])) ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs italic text-gray-400">Producto no disponible</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-gray-700"><?= e(format_date($r['event_date'])) ?></td>
                            <td class="px-5 py-4 text-gray-700">
                                <div class="flex items-center gap-1.5 text-gray-700">
                                    <span class="text-gray-400"><?= icon('truck', 'w-4 h-4') ?></span>
                                    <?= e(format_date($r['delivery_date'])) ?>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-gray-500">
                                    <span class="text-gray-400"><?= icon('return', 'w-4 h-4') ?></span>
                                    <?= e(format_date($r['return_date'])) ?>
                                </div>
                            </td>
                            <td class="px-5 py-4"><?= status_badge($r['status'], 'request') ?></td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" data-modal-open="<?= e($modalId) ?>" class="<?= e($BTN_SECONDARY) ?> px-3 py-2">
                                        <?= icon('eye', 'w-4 h-4') ?> Ver
                                    </button>
                                    <?php if (!in_array($r['status'], ['converted', 'rejected', 'cancelled'], true) && !empty($r['product_id'])): ?>
                                        <a href="<?= e(admin_url('alquileres/crear.php?request=' . (int) $r['id'])) ?>" class="<?= e($BTN_PRIMARY) ?> px-3 py-2">
                                            <?= icon('check', 'w-4 h-4') ?> Convertir
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modales de detalle (uno por solicitud) -->
    <?php foreach ($requests as $r):
        $name    = $r['customer_full_name'] ?: $r['full_name'];
        $name    = $name !== null && $name !== '' ? $name : 'Sin nombre';
        $phone   = $r['customer_phone'] ?: $r['phone'];
        $waPhone = preg_replace('/\D+/', '', (string) ($r['customer_whatsapp'] ?: $phone));
        $email   = $r['email'];
        $modalId = 'modal-req-' . (int) $r['id'];
        $checkId = 'check-req-' . (int) $r['id'];
        $isOpen  = !in_array($r['status'], ['converted', 'rejected', 'cancelled'], true); // todavía gestionable
        ?>
        <div id="<?= e($modalId) ?>" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-brand-dark/50 p-4 backdrop-blur-sm">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-card animate-scale-in">

                <!-- Cabecera del modal -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-red">Solicitud de alquiler</p>
                        <h2 class="mt-0.5 font-serif text-2xl font-bold text-gray-900">Solicitud #<?= (int) $r['id'] ?></h2>
                        <p class="mt-1 text-sm text-gray-500">Recibida el <?= e(format_datetime($r['created_at'])) ?> · <?= status_badge($r['status'], 'request') ?></p>
                    </div>
                    <button type="button" data-modal-close class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                        <?= icon('x', 'w-5 h-5') ?>
                    </button>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <!-- Datos del solicitante -->
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="text-brand-red"><?= icon('user', 'w-4 h-4') ?></span> Datos del solicitante
                        </h3>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex items-center gap-2 text-gray-700">
                                <span class="text-gray-400"><?= icon('user', 'w-4 h-4') ?></span>
                                <span class="font-medium"><?= e($name) ?></span>
                            </div>
                            <?php if (!empty($phone)): ?>
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="text-gray-400"><?= icon('phone', 'w-4 h-4') ?></span>
                                    <span><?= e($phone) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($email)): ?>
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="text-gray-400"><?= icon('mail', 'w-4 h-4') ?></span>
                                    <a href="mailto:<?= e($email) ?>" class="truncate hover:text-brand-red"><?= e($email) ?></a>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center gap-2 text-gray-500">
                                <span class="text-gray-400"><?= icon('inbox', 'w-4 h-4') ?></span>
                                <span class="text-xs uppercase tracking-wide">Origen: <?= $r['source'] === 'admin' ? 'Interna' : 'Sitio público' ?></span>
                            </div>
                        </dl>
                        <?php if (!empty($waPhone)): ?>
                            <a href="https://wa.me/<?= e($waPhone) ?>?text=<?= e(rawurlencode('Hola ' . $name . ', le contactamos de LONDRES Casa de Novias sobre su solicitud de alquiler.')) ?>"
                               target="_blank" rel="noopener"
                               class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                                <?= icon('whatsapp', 'w-4 h-4') ?> Escribir por WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Producto solicitado -->
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="text-brand-red"><?= icon('tag', 'w-4 h-4') ?></span> Producto solicitado
                        </h3>
                        <?php if (!empty($r['product_name'])): ?>
                            <div class="flex gap-3">
                                <img src="<?= e(upload_url($r['product_image'])) ?>" alt="<?= e($r['product_name']) ?>"
                                     class="h-24 w-20 flex-shrink-0 rounded-xl object-cover ring-1 ring-gray-100">
                                <div class="min-w-0">
                                    <p class="font-serif text-base font-semibold leading-tight text-gray-900"><?= e($r['product_name']) ?></p>
                                    <p class="mt-1 text-sm text-gray-700"><?= e(money($r['product_rental_price'])) ?> <span class="text-xs text-gray-400">alquiler</span></p>
                                    <div class="mt-2"><?= status_badge($r['product_commercial_status'], 'commercial') ?></div>
                                    <?php if (!empty($r['product_slug'])): ?>
                                        <a href="<?= e(pub_url('producto.php?slug=' . $r['product_slug'])) ?>" target="_blank" rel="noopener"
                                           class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-red hover:underline">
                                            <?= icon('eye', 'w-3.5 h-3.5') ?> Ver en catálogo
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm italic text-gray-400">El producto asociado ya no está disponible en el catálogo.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Fechas solicitadas -->
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-gray-100 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Fecha del evento</p>
                        <p class="mt-1 flex items-center gap-2 font-medium text-gray-900"><span class="text-brand-red"><?= icon('sparkles', 'w-4 h-4') ?></span><?= e(format_date($r['event_date'])) ?></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Entrega</p>
                        <p class="mt-1 flex items-center gap-2 font-medium text-gray-900"><span class="text-brand-red"><?= icon('truck', 'w-4 h-4') ?></span><?= e(format_date($r['delivery_date'])) ?></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Devolución</p>
                        <p class="mt-1 flex items-center gap-2 font-medium text-gray-900"><span class="text-brand-red"><?= icon('return', 'w-4 h-4') ?></span><?= e(format_date($r['return_date'])) ?></p>
                    </div>
                </div>

                <!-- Mensaje del solicitante -->
                <?php if (!empty($r['message'])): ?>
                    <div class="mt-5 rounded-2xl border border-gray-100 bg-gray-50/60 p-4">
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <span class="text-brand-red"><?= icon('document', 'w-4 h-4') ?></span> Mensaje
                        </h3>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600"><?= e($r['message']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Verificación de disponibilidad (producto fijo) -->
                <?php if (!empty($r['product_id']) && !empty($r['delivery_date']) && !empty($r['return_date'])): ?>
                    <div class="mt-5 rounded-2xl border border-gray-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <span class="text-brand-red"><?= icon('calendar', 'w-4 h-4') ?></span> Disponibilidad
                            </h3>
                            <button type="button"
                                    data-check-availability
                                    data-product="#prod-<?= (int) $r['id'] ?>"
                                    data-delivery="#del-<?= (int) $r['id'] ?>"
                                    data-return="#ret-<?= (int) $r['id'] ?>"
                                    data-exclude=""
                                    data-result="#<?= e($checkId) ?>"
                                    class="<?= e($BTN_SECONDARY) ?> px-3 py-2">
                                <?= icon('calendar', 'w-4 h-4') ?> Verificar disponibilidad
                            </button>
                        </div>
                        <!-- Valores fijos que consume el verificador (data-check-availability) -->
                        <input type="hidden" id="prod-<?= (int) $r['id'] ?>" value="<?= (int) $r['product_id'] ?>">
                        <input type="hidden" id="del-<?= (int) $r['id'] ?>"  value="<?= e($r['delivery_date']) ?>">
                        <input type="hidden" id="ret-<?= (int) $r['id'] ?>"  value="<?= e($r['return_date']) ?>">
                        <div id="<?= e($checkId) ?>" class="mt-3 text-sm text-gray-400">Pulse «Verificar disponibilidad» para comprobar las fechas solicitadas.</div>
                    </div>
                <?php endif; ?>

                <!-- Acciones del modal -->
                <div class="mt-6 flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" data-modal-close class="<?= e($BTN_SECONDARY) ?>">
                        Cerrar
                    </button>

                    <?php if ($isOpen): ?>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <!-- Marcar revisada (solo si aún está pendiente) -->
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" action="<?= e(admin_url('solicitudes/index.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="<?= e($BTN_SECONDARY) ?> w-full justify-center sm:w-auto">
                                        <?= icon('check', 'w-4 h-4') ?> Marcar revisada
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Rechazar (con confirmación) -->
                            <form method="post" action="<?= e(admin_url('solicitudes/index.php')) ?>"
                                  data-confirm="¿Rechazar esta solicitud? El solicitante no podrá convertirse en alquiler.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="<?= e($BTN_DANGER) ?> w-full justify-center sm:w-auto">
                                    <?= icon('x', 'w-4 h-4') ?> Rechazar
                                </button>
                            </form>

                            <!-- Convertir en alquiler -->
                            <?php if (!empty($r['product_id'])): ?>
                                <a href="<?= e(admin_url('alquileres/crear.php?request=' . (int) $r['id'])) ?>" class="<?= e($BTN_PRIMARY) ?> w-full justify-center sm:w-auto">
                                    <?= icon('check', 'w-4 h-4') ?> Convertir en alquiler
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else:
                        $closedLabels = ['converted' => 'convertida en alquiler', 'rejected' => 'rechazada', 'cancelled' => 'cancelada']; ?>
                        <p class="text-sm text-gray-400">Esta solicitud está <?= e($closedLabels[$r['status']] ?? 'cerrada') ?> y no admite más acciones.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
