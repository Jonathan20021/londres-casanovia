<?php
/**
 * Página pública de contacto.
 * - Muestra los datos del negocio (business_settings) en tarjetas premium.
 * - Botón grande de WhatsApp + mapa de Google Maps con la dirección.
 * - Formulario de contacto que, al enviar, notifica a los administradores
 *   (notify_admins) y registra una notificación interna. No requiere tablas nuevas.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';  // public/*.php => N=1

/* ------------------------------------------------------------------ *
 *  Datos del negocio (settings)
 * ------------------------------------------------------------------ */
$business = setting('business_name', APP_NAME);
$phone    = (string) setting('phone', '');
$whatsapp = (string) setting('whatsapp', '');
$email    = (string) setting('email', '');
$address  = (string) setting('address', '');
$instagram = (string) setting('instagram', '');
$hours    = (string) setting('opening_hours', '');

// Sólo dígitos para enlaces de WhatsApp / teléfono.
$waDigits    = preg_replace('/\D/', '', $whatsapp);
$phoneDigits = preg_replace('/\D/', '', $phone);
$igHandle    = ltrim($instagram, '@');
$igUrl       = $igHandle !== '' ? 'https://instagram.com/' . $igHandle : '';

// Mensaje predefinido para el botón grande de WhatsApp.
$waMessage = 'Hola ' . $business . ', me gustaría más información sobre alquiler/venta de vestidos.';
$waLink    = $waDigits !== '' ? 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waMessage) : '';

// URL del mapa (embed de Google Maps) usando la dirección urlencoded.
$mapUrl = $address !== ''
    ? 'https://www.google.com/maps?q=' . rawurlencode($address) . '&output=embed'
    : '';

/* ------------------------------------------------------------------ *
 *  Manejo del POST del formulario de contacto
 * ------------------------------------------------------------------ */
$errors = [];
$old    = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];

if (is_post()) {
    require_csrf();

    $old['name']    = trim((string) post('name', ''));
    $old['email']   = trim((string) post('email', ''));
    $old['phone']   = trim((string) post('phone', ''));
    $old['message'] = trim((string) post('message', ''));

    // Validación
    if ($old['name'] === '')    $errors['name']    = 'Por favor ingrese su nombre.';
    if ($old['email'] === '' && $old['phone'] === '') {
        $errors['email'] = 'Indique un correo o un teléfono de contacto.';
    } elseif ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El correo electrónico no es válido.';
    }
    if ($old['message'] === '') $errors['message'] = 'Por favor escriba su mensaje.';

    if (!$errors) {
        // Construye el resumen para la notificación interna (acotado a 255 chars).
        $contactInfo = $old['email'] !== '' ? $old['email'] : '';
        if ($old['phone'] !== '') {
            $contactInfo .= ($contactInfo !== '' ? ' · ' : '') . 'Tel: ' . $old['phone'];
        }
        $resumen = $old['name'] . ($contactInfo !== '' ? ' (' . $contactInfo . ')' : '') . ': ' . $old['message'];
        $resumen = mb_substr($resumen, 0, 250);

        // Notifica a todos los administradores activos.
        notify_admins(
            'Nuevo mensaje de contacto',
            $resumen,
            'info'
        );

        // Registro de actividad (no rompe el flujo si falla).
        log_activity('contact.submitted', 'contact', null, $resumen);

        flash('success', '¡Gracias por escribirnos! Hemos recibido tu mensaje y te contactaremos muy pronto.');
        redirect(pub_url('contacto.php'));
    }
}

$page_title    = 'Contacto';
$active_public = 'contacto';
require LCN_ROOT . '/app/views/layouts/public_header.php';
?>

<!-- ====================== HERO ====================== -->
<section class="relative overflow-hidden bg-brand-dark">
    <div class="pointer-events-none absolute inset-0 opacity-30"
         style="background:radial-gradient(60% 60% at 20% 0%, rgba(201,168,106,.25), transparent), radial-gradient(50% 50% at 90% 100%, rgba(200,16,46,.25), transparent)"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-20 text-center sm:px-6 lg:px-8 lg:py-28">
        <span class="font-script text-3xl text-brand-gold">Estamos para ti</span>
        <h1 class="mt-2 font-serif text-4xl font-semibold text-white sm:text-5xl">Hablemos de tu evento</h1>
        <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-gray-300">
            Cuéntanos qué buscas y te ayudaremos a encontrar la pieza perfecta. Agenda tu cita,
            resuelve tus dudas o solicita información sobre alquiler y venta.
        </p>
        <?php if ($waLink): ?>
        <div class="mt-8 flex justify-center">
            <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-3 rounded-2xl bg-brand-red px-7 py-4 text-base font-semibold text-white shadow-card transition hover:bg-red-700">
                <?= icon('whatsapp', 'w-6 h-6') ?> Escríbenos por WhatsApp
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ====================== CONTENIDO ====================== -->
<section class="bg-brand-cream">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">

        <!-- Tarjetas de datos del negocio -->
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <?php if ($address !== ''): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('pin', 'w-6 h-6') ?></span>
                <h3 class="mt-4 font-serif text-lg text-gray-900">Visítanos</h3>
                <p class="mt-1 text-sm leading-relaxed text-gray-600"><?= e($address) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($phone !== ''): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('phone', 'w-6 h-6') ?></span>
                <h3 class="mt-4 font-serif text-lg text-gray-900">Llámanos</h3>
                <a href="tel:<?= e($phoneDigits) ?>" class="mt-1 block text-sm font-medium text-gray-600 transition hover:text-brand-red"><?= e($phone) ?></a>
                <?php if ($waDigits): ?>
                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-emerald-600 transition hover:text-emerald-700"><?= icon('whatsapp', 'w-4 h-4') ?> WhatsApp</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($email !== ''): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('mail', 'w-6 h-6') ?></span>
                <h3 class="mt-4 font-serif text-lg text-gray-900">Escríbenos</h3>
                <a href="mailto:<?= e($email) ?>" class="mt-1 block break-words text-sm font-medium text-gray-600 transition hover:text-brand-red"><?= e($email) ?></a>
                <?php if ($igUrl): ?>
                    <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-gray-600 transition hover:text-brand-red"><?= icon('instagram', 'w-4 h-4') ?> @<?= e($igHandle) ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($hours !== ''): ?>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-soft">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-brand-red"><?= icon('clock', 'w-6 h-6') ?></span>
                <h3 class="mt-4 font-serif text-lg text-gray-900">Horario</h3>
                <p class="mt-1 text-sm leading-relaxed text-gray-600"><?= e($hours) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Formulario + mapa -->
        <div class="mt-12 grid gap-8 lg:grid-cols-5">

            <!-- Formulario de contacto -->
            <div class="lg:col-span-3">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-soft sm:p-8">
                    <h2 class="font-serif text-2xl text-gray-900">Envíanos un mensaje</h2>
                    <p class="mt-1 text-sm text-gray-500">Completa el formulario y te responderemos a la brevedad.</p>

                    <?= render_flash() ?>

                    <form method="post" action="<?= pub_url('contacto.php') ?>" class="mt-6 space-y-5" novalidate>
                        <?= csrf_field() ?>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="lcn-label" for="name">Nombre completo <span class="text-brand-red">*</span></label>
                                <input type="text" id="name" name="name" class="lcn-input" placeholder="Tu nombre"
                                       value="<?= e($old['name']) ?>" required>
                                <?php if (!empty($errors['name'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['name']) ?></p><?php endif; ?>
                            </div>
                            <div>
                                <label class="lcn-label" for="phone">Teléfono / WhatsApp</label>
                                <input type="tel" id="phone" name="phone" class="lcn-input" placeholder="+1 809 000 0000"
                                       value="<?= e($old['phone']) ?>">
                            </div>
                        </div>

                        <div>
                            <label class="lcn-label" for="email">Correo electrónico</label>
                            <input type="email" id="email" name="email" class="lcn-input" placeholder="tucorreo@email.com"
                                   value="<?= e($old['email']) ?>">
                            <?php if (!empty($errors['email'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['email']) ?></p><?php endif; ?>
                        </div>

                        <div>
                            <label class="lcn-label" for="message">Mensaje <span class="text-brand-red">*</span></label>
                            <textarea id="message" name="message" rows="5" class="lcn-input" placeholder="Cuéntanos en qué podemos ayudarte…" required><?= e($old['message']) ?></textarea>
                            <?php if (!empty($errors['message'])): ?><p class="mt-1 text-xs text-rose-600"><?= e($errors['message']) ?></p><?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                                <?= icon('mail', 'w-4 h-4') ?> Enviar mensaje
                            </button>
                            <?php if ($waLink): ?>
                            <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                                <?= icon('whatsapp', 'w-4 h-4') ?> Prefiero WhatsApp
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mapa + bloque lateral -->
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-soft">
                    <?php if ($mapUrl): ?>
                        <iframe
                            src="<?= e($mapUrl) ?>"
                            class="h-64 w-full border-0 sm:h-72"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Ubicación de <?= e($business) ?>"
                            allowfullscreen></iframe>
                    <?php else: ?>
                        <div class="flex h-64 items-center justify-center bg-gray-100 text-gray-400">
                            <?= icon('pin', 'w-10 h-10') ?>
                        </div>
                    <?php endif; ?>

                    <div class="p-6">
                        <h3 class="font-serif text-lg text-gray-900"><?= e($business) ?></h3>
                        <?php if ($address !== ''): ?>
                            <p class="mt-1 flex items-start gap-2 text-sm text-gray-600">
                                <span class="mt-0.5 text-brand-red"><?= icon('pin', 'w-4 h-4') ?></span><?= e($address) ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                            <a href="https://www.google.com/maps?q=<?= rawurlencode($address) ?>" target="_blank" rel="noopener"
                               class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-red transition hover:text-red-700">
                                Cómo llegar <?= icon('chevron-right', 'w-4 h-4') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tarjeta dorada de cita -->
                <div class="mt-6 rounded-3xl bg-brand-dark p-6 text-center text-gray-200 shadow-card">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 text-brand-gold"><?= icon('heart', 'w-6 h-6') ?></span>
                    <h3 class="mt-4 font-serif text-xl text-white">¿Lista para tu prueba?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-400">Agenda una cita personalizada y vive la experiencia LONDRES.</p>
                    <?php if ($waLink): ?>
                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener"
                       class="mt-5 inline-flex items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                        <?= icon('whatsapp', 'w-4 h-4') ?> Agendar por WhatsApp
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require LCN_ROOT . '/app/views/layouts/public_footer.php'; ?>
