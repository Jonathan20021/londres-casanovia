<?php
/**
 * Listado de clientes — LONDRES Casa de Novias
 * Tabla con búsqueda, conteo de alquileres, deuda pendiente y paginación.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('customers.manage');

// --- Filtro de búsqueda (nombre / teléfono / whatsapp / email) ---
$q = get_param('q');

// Condición WHERE dinámica
$where  = '';
$params = [];
if ($q !== '') {
    $where = "WHERE (c.full_name LIKE :q OR c.phone LIKE :q OR c.whatsapp LIKE :q OR c.email LIKE :q OR c.document_number LIKE :q)";
    $params['q'] = '%' . $q . '%';
}

// Total para paginación
$total = (int) db_value("SELECT COUNT(*) FROM customers c $where", $params);
$pag   = paginate($total, 12);

/*
 * Listado con:
 *  - rentals_count: total de alquileres del cliente
 *  - debt: suma de remaining_balance de alquileres ACTIVOS (que bloquean) sin pagar del todo
 * La deuda se calcula sobre alquileres no cancelados ni devueltos completamente liquidados.
 */
$sql = "SELECT c.*,
               (SELECT COUNT(*) FROM rentals r WHERE r.customer_id = c.id) AS rentals_count,
               (SELECT COALESCE(SUM(r.remaining_balance),0)
                  FROM rentals r
                 WHERE r.customer_id = c.id
                   AND r.rental_status NOT IN ('cancelled')
                   AND r.payment_status <> 'paid') AS debt
        FROM customers c
        $where
        ORDER BY c.full_name ASC
        LIMIT {$pag['perPage']} OFFSET {$pag['offset']}";
$customers = db_all($sql, $params);

$page_title    = 'Clientes';
$page_subtitle = 'Directorio de clientes, historial y saldos pendientes.';
$active        = 'clientes';
$header_actions = '<a href="' . admin_url('clientes/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nuevo cliente</a>';
require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<!-- Barra de búsqueda -->
<div class="mb-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-soft">
    <form method="get" class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative block flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, teléfono, WhatsApp, correo o documento…"
                   class="lcn-input pl-10">
        </label>
        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('filter', 'w-4 h-4') ?> Buscar
            </button>
            <?php if ($q !== ''): ?>
                <a href="<?= admin_url('clientes/index.php') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                    <?= icon('x', 'w-4 h-4') ?> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($q !== ''): ?>
        <p class="mt-3 text-sm text-gray-500"><?= $total ?> resultado<?= $total === 1 ? '' : 's' ?> para “<span class="font-medium text-gray-700"><?= e($q) ?></span>”.</p>
    <?php endif; ?>
</div>

<?php if (!$customers): ?>
    <?= empty_state(
        $q !== '' ? 'Sin coincidencias' : 'Aún no hay clientes',
        $q !== '' ? 'No encontramos clientes que coincidan con tu búsqueda.' : 'Registra tu primer cliente para empezar a gestionar alquileres y ventas.',
        'users',
        '<a href="' . admin_url('clientes/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo cliente</a>'
    ) ?>
<?php else: ?>
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Teléfono / WhatsApp</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Correo</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Alquileres</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Deuda pendiente</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($customers as $c):
                        $verUrl = admin_url('clientes/ver.php?id=' . (int) $c['id']);
                        $debt   = (float) $c['debt'];
                    ?>
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-4 text-gray-700">
                                <a href="<?= e($verUrl) ?>" class="flex items-center gap-3">
                                    <?= avatar($c['full_name']) ?>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-gray-900 hover:text-brand-red"><?= e($c['full_name']) ?></span>
                                        <?php if (!empty($c['document_number'])): ?>
                                            <span class="block text-xs text-gray-400">Doc. <?= e($c['document_number']) ?></span>
                                        <?php elseif (!empty($c['instagram'])): ?>
                                            <span class="block text-xs text-gray-400">@<?= e(ltrim($c['instagram'], '@')) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <?php if (!empty($c['phone'])): ?>
                                    <span class="flex items-center gap-1.5 text-gray-700"><span class="text-gray-400"><?= icon('phone', 'w-4 h-4') ?></span><?= e($c['phone']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($c['whatsapp'])): ?>
                                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-emerald-600"><span class="text-emerald-500"><?= icon('whatsapp', 'w-4 h-4') ?></span><?= e($c['whatsapp']) ?></span>
                                <?php endif; ?>
                                <?php if (empty($c['phone']) && empty($c['whatsapp'])): ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <?php if (!empty($c['email'])): ?>
                                    <a href="mailto:<?= e($c['email']) ?>" class="text-gray-600 hover:text-brand-red"><?= e($c['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center text-gray-700">
                                <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-gray-100 px-2 text-xs font-semibold text-gray-700"><?= (int) $c['rentals_count'] ?></span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <?php if ($debt > 0.009): ?>
                                    <span class="font-semibold text-brand-red"><?= e(money($debt)) ?></span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-emerald-600"><?= icon('check', 'w-4 h-4') ?> Al día</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="<?= e($verUrl) ?>" title="Ver ficha" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red"><?= icon('eye', 'w-4 h-4') ?></a>
                                    <a href="<?= admin_url('clientes/editar.php?id=' . (int) $c['id']) ?>" title="Editar" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-brand-red"><?= icon('pencil', 'w-4 h-4') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pag['pages'] > 1): ?>
        <div class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p class="text-sm text-gray-500">
                Mostrando <span class="font-medium text-gray-700"><?= count($customers) ?></span> de <span class="font-medium text-gray-700"><?= $total ?></span> clientes
            </p>
            <?= render_pagination($pag['page'], $pag['pages']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
