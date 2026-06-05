<?php
/**
 * Editar cliente — LONDRES Casa de Novias
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('customers.manage');

$id = (int) get_param('id');
$customer = $id ? db_one('SELECT * FROM customers WHERE id = :id', ['id' => $id]) : null;

if (!$customer) {
    flash('error', 'El cliente solicitado no existe.');
    redirect(admin_url('clientes/index.php'));
}

$errors = [];
// Precargar con los datos actuales del cliente
$form = [
    'full_name'       => (string) $customer['full_name'],
    'phone'           => (string) ($customer['phone'] ?? ''),
    'whatsapp'        => (string) ($customer['whatsapp'] ?? ''),
    'email'           => (string) ($customer['email'] ?? ''),
    'document_number' => (string) ($customer['document_number'] ?? ''),
    'address'         => (string) ($customer['address'] ?? ''),
    'birthdate'       => (string) ($customer['birthdate'] ?? ''),
    'instagram'       => (string) ($customer['instagram'] ?? ''),
    'notes'           => (string) ($customer['notes'] ?? ''),
];

if (is_post()) {
    require_csrf();

    foreach ($form as $k => $_) {
        $form[$k] = trim((string) post($k, ''));
    }

    // Validación
    if ($form['full_name'] === '') {
        $errors['full_name'] = 'El nombre completo es obligatorio.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingresa un correo electrónico válido.';
    }
    if ($form['birthdate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['birthdate'])) {
        $errors['birthdate'] = 'Fecha de nacimiento inválida.';
    }

    if (!$errors) {
        $data = [
            'full_name'       => $form['full_name'],
            'phone'           => $form['phone'] !== '' ? $form['phone'] : null,
            'whatsapp'        => $form['whatsapp'] !== '' ? $form['whatsapp'] : null,
            'email'           => $form['email'] !== '' ? $form['email'] : null,
            'document_number' => $form['document_number'] !== '' ? $form['document_number'] : null,
            'address'         => $form['address'] !== '' ? $form['address'] : null,
            'birthdate'       => $form['birthdate'] !== '' ? $form['birthdate'] : null,
            'instagram'       => $form['instagram'] !== '' ? ltrim($form['instagram'], '@') : null,
            'notes'           => $form['notes'] !== '' ? $form['notes'] : null,
        ];

        db_update('customers', $data, 'id = :id', ['id' => $id]);
        log_activity('update', 'customer', $id, 'Cliente actualizado: ' . $form['full_name']);
        flash('success', 'Cliente actualizado correctamente.');
        redirect(admin_url('clientes/ver.php?id=' . $id));
    } else {
        flash('error', 'Revisa los campos marcados e intenta de nuevo.');
    }
}

$page_title    = 'Editar cliente';
$page_subtitle = e($customer['full_name']);
$active        = 'clientes';
$header_actions = '<a href="' . admin_url('clientes/ver.php?id=' . $id) . '" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">'
    . icon('chevron-left', 'w-4 h-4') . ' Volver a la ficha</a>';
require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<form method="post" class="mx-auto max-w-3xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <h2 class="mb-1 font-serif text-lg font-semibold text-gray-900">Datos del cliente</h2>
        <p class="mb-6 text-sm text-gray-500">Los campos marcados con <span class="text-brand-red">*</span> son obligatorios.</p>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="lcn-label" for="full_name">Nombre completo <span class="text-brand-red">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="150"
                       class="lcn-input <?= isset($errors['full_name']) ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-400/20' : '' ?>">
                <?php if (isset($errors['full_name'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['full_name']) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="lcn-label" for="phone">Teléfono</label>
                <input type="text" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="40" class="lcn-input">
            </div>

            <div>
                <label class="lcn-label" for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" value="<?= e($form['whatsapp']) ?>" maxlength="40" placeholder="Ej. 8095551234" class="lcn-input">
            </div>

            <div>
                <label class="lcn-label" for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="150"
                       class="lcn-input <?= isset($errors['email']) ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-400/20' : '' ?>">
                <?php if (isset($errors['email'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="lcn-label" for="document_number">Documento / Cédula</label>
                <input type="text" id="document_number" name="document_number" value="<?= e($form['document_number']) ?>" maxlength="40" class="lcn-input">
            </div>

            <div>
                <label class="lcn-label" for="birthdate">Fecha de nacimiento</label>
                <input type="date" id="birthdate" name="birthdate" value="<?= e(format_date($form['birthdate'], 'Y-m-d') !== '—' ? format_date($form['birthdate'], 'Y-m-d') : '') ?>"
                       class="lcn-input <?= isset($errors['birthdate']) ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-400/20' : '' ?>">
                <?php if (isset($errors['birthdate'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['birthdate']) ?></p><?php endif; ?>
            </div>

            <div>
                <label class="lcn-label" for="instagram">Instagram</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">@</span>
                    <input type="text" id="instagram" name="instagram" value="<?= e(ltrim($form['instagram'], '@')) ?>" maxlength="80" class="lcn-input pl-7">
                </div>
            </div>

            <div class="sm:col-span-2">
                <label class="lcn-label" for="address">Dirección</label>
                <input type="text" id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" class="lcn-input">
            </div>

            <div class="sm:col-span-2">
                <label class="lcn-label" for="notes">Notas internas</label>
                <textarea id="notes" name="notes" rows="3" class="lcn-input" placeholder="Preferencias, observaciones, etc."><?= e($form['notes']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
        <a href="<?= admin_url('clientes/ver.php?id=' . $id) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancelar</a>
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            <?= icon('check', 'w-4 h-4') ?> Guardar cambios
        </button>
    </div>
</form>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
