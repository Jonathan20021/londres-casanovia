<?php
/**
 * Inicio de sesión del panel administrativo.
 * Página a pantalla completa SIN layout admin.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Si ya hay una sesión activa, ir directo al panel.
if (is_logged_in()) {
    redirect(admin_url('dashboard.php'));
}

// --- Manejo POST: autenticación ---
if (is_post()) {
    require_csrf();

    $email    = trim((string) post('email', ''));
    $password = (string) post('password', '');

    if ($email === '' || $password === '') {
        flash('error', 'Ingrese su correo y contraseña.');
        redirect(admin_url('login.php'));
    }

    // Buscar usuario activo por correo.
    $user = db_one(
        'SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1',
        ['email' => $email]
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user);
        log_activity('login', 'user', (int) $user['id'], 'Inicio de sesión: ' . $user['email']);
        flash('success', '¡Bienvenida de nuevo, ' . $user['name'] . '!');
        redirect(admin_url('dashboard.php'));
    }

    flash('error', 'Correo o contraseña incorrectos. Intente nuevamente.');
    redirect(admin_url('login.php'));
}

$page_title = 'Iniciar sesión';
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
    <title><?= e($page_title) ?> · LONDRES Casa de Novias</title>
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
</head>
<body class="h-full bg-brand-dark font-sans text-gray-800 antialiased">

<div class="relative flex min-h-full items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
    <!-- Fondo decorativo elegante -->
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-dark via-[#15151a] to-[#1f1216]"></div>
    <div class="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-brand-red/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-brand-gold/15 blur-3xl"></div>
    <div class="pointer-events-none absolute inset-0 opacity-[0.04]"
         style="background-image:radial-gradient(circle at 1px 1px,#fff 1px,transparent 0);background-size:28px 28px;"></div>

    <!-- Tarjeta -->
    <div class="relative w-full max-w-md">
        <!-- Marca sobre la tarjeta -->
        <div class="mb-7 flex flex-col items-center text-center">
            <?= brand_lockup('dark', 'lg') ?>
            <p class="mt-4 font-script text-2xl text-brand-gold">Tu gran día comienza aquí</p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white p-7 shadow-card sm:p-9">
            <div class="mb-6 text-center">
                <h1 class="font-serif text-2xl font-bold text-gray-900">Panel administrativo</h1>
                <p class="mt-1 text-sm text-gray-500">Acceda con sus credenciales para continuar</p>
            </div>

            <?= render_flash() ?>

            <form method="post" action="<?= admin_url('login.php') ?>" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="lcn-label">Correo electrónico</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><?= icon('mail', 'w-5 h-5') ?></span>
                        <input type="email" id="email" name="email" required autofocus
                               value="<?= e(post('email', '')) ?>"
                               placeholder="usuario@londresnovias.com"
                               class="lcn-input pl-11" autocomplete="username">
                    </div>
                </div>

                <div>
                    <label for="password" class="lcn-label">Contraseña</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400"><?= icon('lock', 'w-5 h-5') ?></span>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••"
                               class="lcn-input pl-11 pr-11" autocomplete="current-password">
                        <button type="button" data-toggle-password="#password"
                                class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 transition hover:text-gray-600"
                                title="Mostrar/ocultar contraseña"><?= icon('eye', 'w-5 h-5') ?></button>
                    </div>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                    <?= icon('logout', 'w-5 h-5') ?> Iniciar sesión
                </button>
            </form>

            <!-- Credenciales demo -->
            <div class="mt-7 rounded-2xl border border-dashed border-gray-200 bg-gray-50/70 px-4 py-3 text-center">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Acceso de demostración</p>
                <p class="mt-1 text-sm text-gray-600">
                    <span class="font-medium text-gray-900">admin@londresnovias.com</span>
                    <span class="mx-1.5 text-gray-300">·</span>
                    <span class="font-medium text-gray-900">Admin12345</span>
                </p>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-white/40">
            &copy; <?= date('Y') ?> <?= e(setting('business_name', APP_NAME)) ?> · Todos los derechos reservados
        </p>
    </div>
</div>

<!-- Mostrar/ocultar contraseña (sin dependencias) -->
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.querySelector(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
    });
});
</script>
</body>
</html>
