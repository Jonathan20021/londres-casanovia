<?php
/**
 * Usuarios · Crear
 * LONDRES Casa de Novias
 * Permiso: users.manage
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('users.manage');

$roles = db_all('SELECT id, name FROM roles ORDER BY id ASC');

// Valores precargados para repintar el formulario tras un error de validación
$old = [
    'name'    => '',
    'email'   => '',
    'role_id' => '',
    'status'  => 'active',
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

    // Validaciones
    if ($old['name'] === '') {
        $errors['name'] = 'El nombre es obligatorio.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingrese un correo electrónico válido.';
    } else {
        $exists = (int) db_value('SELECT COUNT(*) FROM users WHERE email = :email', ['email' => $old['email']]);
        if ($exists > 0) {
            $errors['email'] = 'Ya existe un usuario con este correo.';
        }
    }
    if ($old['role_id'] === '' || (int) db_value('SELECT COUNT(*) FROM roles WHERE id = :id', ['id' => (int) $old['role_id']]) === 0) {
        $errors['role_id'] = 'Seleccione un rol válido.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if (!$errors) {
        $newId = db_insert('users', [
            'name'          => $old['name'],
            'email'         => $old['email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id'       => (int) $old['role_id'],
            'status'        => $old['status'],
        ]);

        log_activity('create', 'user', $newId, 'Creó el usuario ' . $old['name']);
        flash('success', 'Usuario creado correctamente.');
        redirect(admin_url('usuarios/index.php'));
    }

    flash('error', 'Revise los datos del formulario.');
}

$page_title    = 'Nuevo usuario';
$page_subtitle = 'Registre un nuevo integrante del equipo.';
$active        = 'usuarios';
$header_actions = '<a href="' . admin_url('usuarios/index.php') . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver</a>';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<form method="post" action="<?= admin_url('usuarios/crear.php') ?>" class="mx-auto max-w-2xl">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Nombre -->
            <div class="sm:col-span-2">
                <label for="name" class="lcn-label">Nombre completo</label>
                <input type="text" id="name" name="name" value="<?= e($old['name']) ?>" required
                       class="lcn-input" placeholder="Ej. María Gerente">
                <?php if (!empty($errors['name'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="lcn-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required
                       class="lcn-input" placeholder="usuario@londresnovias.com">
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

            <!-- Contraseña -->
            <div>
                <label for="password" class="lcn-label">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="new-password"
                       class="lcn-input" placeholder="Mínimo 6 caracteres">
                <?php if (!empty($errors['password'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['password']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Estado -->
            <div>
                <label for="status" class="lcn-label">Estado</label>
                <select id="status" name="status" class="lcn-input">
                    <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="mt-7 flex items-center justify-end gap-2 border-t border-gray-100 pt-5">
            <a href="<?= admin_url('usuarios/index.php') ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                <?= icon('check', 'w-4 h-4') ?> Guardar usuario
            </button>
        </div>
    </div>
</form>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
