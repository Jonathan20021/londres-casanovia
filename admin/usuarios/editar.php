<?php
/**
 * Usuarios · Editar
 * LONDRES Casa de Novias
 * Permiso: users.manage
 *
 * Reglas:
 *  - La contraseña sólo cambia si se llena el campo.
 *  - No se permite desactivar el propio usuario.
 *  - No se permite desactivar (ni cambiar de rol) al último Super Admin activo.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('users.manage');

$id = (int) get_param('id', '0');
$user = $id > 0
    ? db_one(
        'SELECT u.*, r.name AS role_name
         FROM users u LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id',
        ['id' => $id]
    )
    : null;

if (!$user) {
    flash('error', 'El usuario solicitado no existe.');
    redirect(admin_url('usuarios/index.php'));
}

$roles   = db_all('SELECT id, name FROM roles ORDER BY id ASC');
$current = current_user();
$isSelf  = (int) $current['id'] === (int) $user['id'];

// ¿Es este el último Super Admin activo del sistema?
$superAdminRoleId = (int) db_value("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
$isSuperAdmin     = (int) $user['role_id'] === $superAdminRoleId && $superAdminRoleId > 0;
$activeSuperAdmins = (int) db_value(
    "SELECT COUNT(*) FROM users WHERE role_id = :rid AND status = 'active'",
    ['rid' => $superAdminRoleId]
);
$isLastSuperAdmin = $isSuperAdmin && $user['status'] === 'active' && $activeSuperAdmins <= 1;

// Estado del formulario
$old = [
    'name'    => $user['name'],
    'email'   => $user['email'],
    'role_id' => (string) ($user['role_id'] ?? ''),
    'status'  => $user['status'],
];
$errors = [];

// --- Manejo POST ---
if (is_post()) {
    require_csrf();

    $old['name']    = trim((string) post('name', ''));
    $old['email']   = trim((string) post('email', ''));
    $old['role_id'] = (string) post('role_id', '');
    $old['status']  = post('status') === 'inactive' ? 'inactive' : 'active';
    $password       = (string) post('password', '');

    // Validaciones básicas
    if ($old['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingrese un correo electrónico válido.';
    } else {
        $exists = (int) db_value(
            'SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id',
            ['email' => $old['email'], 'id' => $id]
        );
        if ($exists > 0) {
            $errors['email'] = 'Ya existe otro usuario con este correo.';
        }
    }
    if ($old['role_id'] === '' || (int) db_value('SELECT COUNT(*) FROM roles WHERE id = :id', ['id' => (int) $old['role_id']]) === 0) {
        $errors['role_id'] = 'Seleccione un rol válido.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    // Determinar si la edición desactiva o degrada al usuario
    $willBeInactive   = $old['status'] === 'inactive';
    $willLoseSuper    = $isSuperAdmin && (int) $old['role_id'] !== $superAdminRoleId;

    // Regla: no desactivar el propio usuario
    if ($isSelf && $willBeInactive) {
        $errors['status'] = 'No puede desactivar su propia cuenta.';
    }
    // Regla: no desactivar ni degradar al último Super Admin activo
    if ($isLastSuperAdmin && ($willBeInactive || $willLoseSuper)) {
        $errors['status'] = 'No puede desactivar ni cambiar el rol del único Super Admin activo.';
    }

    if (!$errors) {
        $data = [
            'name'    => $old['name'],
            'email'   => $old['email'],
            'role_id' => (int) $old['role_id'],
            'status'  => $old['status'],
        ];
        // La contraseña sólo cambia si se llenó
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        db_update('users', $data, 'id = :id', ['id' => $id]);

        log_activity('update', 'user', $id, 'Actualizó el usuario ' . $old['name']);
        flash('success', 'Usuario actualizado correctamente.');
        redirect(admin_url('usuarios/index.php'));
    }

    flash('error', 'Revise los datos del formulario.');
}

$page_title    = 'Editar usuario';
$page_subtitle = e($user['name']);
$active        = 'usuarios';
$header_actions = '<a href="' . admin_url('usuarios/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<?php if ($isLastSuperAdmin): ?>
    <div class="mx-auto mb-5 max-w-2xl">
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-soft">
            <span class="mt-0.5 text-amber-500"><?= icon('warning', 'w-5 h-5') ?></span>
            <p>Este es el único <strong>Super Admin</strong> activo del sistema. Por seguridad no puede desactivarse ni cambiar su rol.</p>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= admin_url('usuarios/editar.php?id=' . $id) ?>" class="mx-auto max-w-2xl">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-6 flex items-center gap-4 border-b border-gray-100 pb-5">
            <?= avatar($user['name'], 'h-14 w-14 text-lg') ?>
            <div>
                <p class="font-serif text-lg font-semibold text-gray-900"><?= e($user['name']) ?></p>
                <p class="text-sm text-gray-500"><?= e($user['email']) ?></p>
                <p class="mt-1 text-xs text-gray-400">Último acceso: <?= e(format_datetime($user['last_login_at'])) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Nombre -->
            <div class="sm:col-span-2">
                <label for="name" class="lcn-label">Nombre completo</label>
                <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required class="lcn-input">
                <?php if (!empty($errors['name'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="lcn-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required class="lcn-input">
                <?php if (!empty($errors['email'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['email']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Rol -->
            <div>
                <label for="role_id" class="lcn-label">Rol</label>
                <select id="role_id" name="role_id" required class="lcn-input">
                    <option value="">Seleccione un rol…</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $old['role_id'] === (string) $r['id'] ? 'selected' : '' ?>>
                            <?= e($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['role_id'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['role_id']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Contraseña (opcional) -->
            <div>
                <label for="password" class="lcn-label">Nueva contraseña</label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="lcn-input" placeholder="Dejar en blanco para no cambiar">
                <?php if (!empty($errors['password'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password']) ?></p>
                <?php else: ?>
                    <p class="mt-1.5 text-xs text-gray-400">Sólo se cambia si escribe una nueva.</p>
                <?php endif; ?>
            </div>

            <!-- Estado -->
            <div>
                <label for="status" class="lcn-label">Estado</label>
                <select id="status" name="status" class="lcn-input"
                        <?= ($isSelf || $isLastSuperAdmin) ? 'disabled' : '' ?>>
                    <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="status" value="<?= e($old['status']) ?>">
                    <p class="mt-1.5 text-xs text-gray-400">No puede cambiar el estado de su propia cuenta.</p>
                <?php elseif ($isLastSuperAdmin): ?>
                    <input type="hidden" name="status" value="active">
                <?php endif; ?>
                <?php if (!empty($errors['status'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['status']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-7 flex items-center justify-end gap-2 border-t border-gray-100 pt-5">
            <a href="<?= admin_url('usuarios/index.php') ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Guardar cambios
            </button>
        </div>
    </div>
</form>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
