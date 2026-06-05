<?php
/**
 * Configuración del negocio
 * LONDRES Casa de Novias
 * Permiso: settings.manage
 *
 * Edita la fila única business_settings (id = 1).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('settings.manage');

// Fila única de configuración (id = 1)
$settings = db_one('SELECT * FROM business_settings WHERE id = 1') ?: [];
$errors = [];

// --- Manejo POST ---
if (is_post()) {
    require_csrf();

    // Recoger y normalizar campos de texto
    $data = [
        'business_name'  => trim((string) post('business_name', '')),
        'phone'          => trim((string) post('phone', '')),
        'whatsapp'       => trim((string) post('whatsapp', '')),
        'email'          => trim((string) post('email', '')),
        'address'        => trim((string) post('address', '')),
        'instagram'      => trim((string) post('instagram', '')),
        'opening_hours'  => trim((string) post('opening_hours', '')),
        'rental_policy'  => trim((string) post('rental_policy', '')),
        'return_policy'  => trim((string) post('return_policy', '')),
        'currency'       => trim((string) post('currency', 'RD$')),
        'invoice_prefix' => trim((string) post('invoice_prefix', 'FAC')),
        'primary_color'  => trim((string) post('primary_color', '#C8102E')),
    ];

    // Numéricos (porcentajes acotados a 0–100)
    $initialPct = (float) post('initial_payment_percentage', 50);
    $taxPct     = (float) post('tax_percentage', 0);
    $data['initial_payment_percentage'] = max(0, min(100, $initialPct));
    $data['tax_percentage']             = max(0, min(100, $taxPct));

    // Validaciones mínimas
    if ($data['business_name'] === '') {
        $errors['business_name'] = 'El nombre del negocio es obligatorio.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ingrese un correo electrónico válido.';
    }
    if ($data['currency'] === '') {
        $data['currency'] = 'RD$';
    }
    if ($data['invoice_prefix'] === '') {
        $data['invoice_prefix'] = 'FAC';
    }

    // Logo (opcional)
    $logoError = null;
    if (!empty($_FILES['logo']['name'])) {
        $up = upload_image($_FILES['logo'], 'business');
        if ($up['ok']) {
            // Eliminar el logo anterior si existía
            if (!empty($settings['logo'])) {
                delete_upload($settings['logo']);
            }
            $data['logo'] = $up['path'];
        } else {
            $logoError = $up['error'];
            $errors['logo'] = $up['error'];
        }
    }

    if (!$errors) {
        db_update('business_settings', $data, 'id = :id', ['id' => 1]);
        log_activity('update', 'settings', 1, 'Actualizó la configuración del negocio');
        flash('success', 'Configuración guardada correctamente.');
        redirect(admin_url('configuracion/index.php'));
    }

    // Si hubo error, repintar con los valores enviados
    $settings = array_merge($settings, $data);
    flash('error', 'Revise los datos del formulario.');
}

// Helper local para obtener valor actual o por defecto
$val = function (string $key, $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};

$page_title    = 'Configuración';
$page_subtitle = 'Datos del negocio, contacto, políticas y parámetros de facturación.';
$active        = 'configuracion';

require LCN_ROOT . '/app/views/layouts/admin_header.php';
?>

<form method="post" action="<?= admin_url('configuracion/index.php') ?>" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-6">
    <?= csrf_field() ?>

    <!-- ============ Datos del negocio ============ -->
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-5 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('home', 'w-5 h-5') ?></span>
            <div>
                <h2 class="font-serif text-lg font-semibold text-gray-900">Datos del negocio</h2>
                <p class="text-sm text-gray-500">Nombre y logotipo que verán los clientes.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="business_name" class="lcn-label">Nombre del negocio</label>
                <input type="text" id="business_name" name="business_name" required
                       value="<?= e($val('business_name', 'LONDRES Casa de Novias')) ?>" class="lcn-input">
                <?php if (!empty($errors['business_name'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['business_name']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Logo -->
            <div class="sm:col-span-2">
                <label class="lcn-label">Logotipo</label>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-24 w-40 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                        <?php if (!empty($val('logo'))): ?>
                            <img id="logoPrev" src="<?= e(upload_url($val('logo'))) ?>" alt="Logo actual" class="max-h-full max-w-full object-contain">
                        <?php else: ?>
                            <span class="text-gray-300"><?= icon('photo', 'w-8 h-8') ?></span>
                            <img id="logoPrev" class="hidden max-h-full max-w-full object-contain" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            <?= icon('upload', 'w-4 h-4') ?> Cambiar logotipo
                            <input type="file" name="logo" accept="image/*" data-image-preview="#logoPrev" class="hidden">
                        </label>
                        <p class="mt-2 text-xs text-gray-400">JPG, PNG o WEBP. Máximo 5 MB.</p>
                        <?php if (!empty($errors['logo'])): ?>
                            <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['logo']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Contacto y redes ============ -->
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-5 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><?= icon('phone', 'w-5 h-5') ?></span>
            <div>
                <h2 class="font-serif text-lg font-semibold text-gray-900">Contacto y redes</h2>
                <p class="text-sm text-gray-500">Datos de contacto y presencia en línea.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="phone" class="lcn-label">Teléfono</label>
                <input type="text" id="phone" name="phone" value="<?= e($val('phone')) ?>" class="lcn-input" placeholder="(809) 000-0000">
            </div>
            <div>
                <label for="whatsapp" class="lcn-label">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" value="<?= e($val('whatsapp')) ?>" class="lcn-input" placeholder="18090000000">
            </div>
            <div>
                <label for="email" class="lcn-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($val('email')) ?>" class="lcn-input" placeholder="contacto@londresnovias.com">
                <?php if (!empty($errors['email'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['email']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label for="instagram" class="lcn-label">Instagram</label>
                <input type="text" id="instagram" name="instagram" value="<?= e($val('instagram')) ?>" class="lcn-input" placeholder="@londresnovias">
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="lcn-label">Dirección</label>
                <input type="text" id="address" name="address" value="<?= e($val('address')) ?>" class="lcn-input" placeholder="Calle, número, ciudad">
            </div>
            <div class="sm:col-span-2">
                <label for="opening_hours" class="lcn-label">Horario de atención</label>
                <input type="text" id="opening_hours" name="opening_hours" value="<?= e($val('opening_hours')) ?>" class="lcn-input" placeholder="Lun a Sáb · 9:00 AM – 7:00 PM">
            </div>
        </div>
    </section>

    <!-- ============ Políticas ============ -->
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-5 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><?= icon('document', 'w-5 h-5') ?></span>
            <div>
                <h2 class="font-serif text-lg font-semibold text-gray-900">Políticas</h2>
                <p class="text-sm text-gray-500">Textos que aparecen en contratos y en el sitio público.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5">
            <div>
                <label for="rental_policy" class="lcn-label">Política de alquiler</label>
                <textarea id="rental_policy" name="rental_policy" rows="4" class="lcn-input" placeholder="Condiciones de alquiler, depósitos, plazos…"><?= e($val('rental_policy')) ?></textarea>
            </div>
            <div>
                <label for="return_policy" class="lcn-label">Política de devolución</label>
                <textarea id="return_policy" name="return_policy" rows="4" class="lcn-input" placeholder="Condiciones de devolución, penalidades por retraso…"><?= e($val('return_policy')) ?></textarea>
            </div>
        </div>
    </section>

    <!-- ============ Facturación y marca ============ -->
    <section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
        <div class="mb-5 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-brand-gold"><?= icon('banknotes', 'w-5 h-5') ?></span>
            <div>
                <h2 class="font-serif text-lg font-semibold text-gray-900">Facturación y marca</h2>
                <p class="text-sm text-gray-500">Parámetros de cobros, impuestos y color de marca.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label for="initial_payment_percentage" class="lcn-label">Pago inicial por defecto (%)</label>
                <input type="number" id="initial_payment_percentage" name="initial_payment_percentage"
                       value="<?= e((string) $val('initial_payment_percentage', '50')) ?>"
                       min="0" max="100" step="0.01" class="lcn-input">
                <p class="mt-1.5 text-xs text-gray-400">Porcentaje sugerido al crear alquileres (típicamente 50%).</p>
            </div>
            <div>
                <label for="tax_percentage" class="lcn-label">Impuesto (%)</label>
                <input type="number" id="tax_percentage" name="tax_percentage"
                       value="<?= e((string) $val('tax_percentage', '0')) ?>"
                       min="0" max="100" step="0.01" class="lcn-input">
            </div>
            <div>
                <label for="currency" class="lcn-label">Moneda</label>
                <input type="text" id="currency" name="currency" value="<?= e($val('currency', 'RD$')) ?>" class="lcn-input" placeholder="RD$">
            </div>
            <div>
                <label for="invoice_prefix" class="lcn-label">Prefijo de factura</label>
                <input type="text" id="invoice_prefix" name="invoice_prefix" value="<?= e($val('invoice_prefix', 'FAC')) ?>" class="lcn-input" placeholder="FAC">
            </div>
            <div class="sm:col-span-2">
                <label for="primary_color" class="lcn-label">Color principal de marca</label>
                <div class="flex items-center gap-3">
                    <input type="color" id="primary_color" name="primary_color"
                           value="<?= e($val('primary_color', '#C8102E')) ?>"
                           class="h-11 w-16 cursor-pointer rounded-xl border border-gray-200 bg-white p-1">
                    <span class="text-sm text-gray-500"><?= e($val('primary_color', '#C8102E')) ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Acciones -->
    <div class="flex items-center justify-end gap-2">
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            <?= icon('check', 'w-4 h-4') ?> Guardar configuración
        </button>
    </div>
</form>

<?php require LCN_ROOT . '/app/views/layouts/admin_footer.php'; ?>
