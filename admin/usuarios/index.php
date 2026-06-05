<?php
/**
 * Usuarios · Listado
 * LONDRES Casa de Novias
 * Permiso: users.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('users.manage');

// --- Consultas para mostrar ---
$users = db_all(
    'SELECT u.id, u.name, u.email, u.status, u.last_login_at, r.name AS role_name
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY u.name ASC'
);

$page_title    = 'Usuarios';
$page_subtitle = 'Gestione el equipo con acceso al panel administrativo.';
$active        = 'usuarios';
$header_actions = '<a href="' . admin_url('usuarios/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">'
    . icon('plus', 'w-4 h-4') . ' Nuevo usuario</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if (!$users): ?>
    <?= empty_state(
        'Aún no hay usuarios',
        'Cree el primer usuario para dar acceso al panel administrativo.',
        'users',
        '<a href="' . admin_url('usuarios/crear.php') . '" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">' . icon('plus', 'w-4 h-4') . ' Nuevo usuario</a>'
    ) ?>
<?php else: ?>
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Usuario</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Correo</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Rol</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Último acceso</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($users as $usr): ?>
                        <tr class="hover:bg-gray-50/60">
                            <td class="px-5 py-4 text-gray-700">
                                <div class="flex items-center gap-3">
                                    <?= avatar($usr['name']) ?>
                                    <span class="font-medium text-gray-900"><?= e($usr['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-gray-700"><?= e($usr['email']) ?></td>
                            <td class="px-5 py-4 text-gray-700"><?= e($usr['role_name'] ?? 'Sin rol') ?></td>
                            <td class="px-5 py-4 text-gray-700"><?= status_badge($usr['status'], 'user') ?></td>
                            <td class="px-5 py-4 text-gray-700"><?= e(format_datetime($usr['last_login_at'])) ?></td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= admin_url('usuarios/editar.php?id=' . (int) $usr['id']) ?>"
                                   class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                    <?= icon('pencil', 'w-4 h-4') ?> Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
