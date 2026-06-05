<?php
/**
 * Cabecera del sitio público.
 * Variables: $page_title (string), $active_public ('home'|'inventario'|'contacto')
 *
 * Uso:
 *   require LCN_ROOT.'/app/views/layouts/public_header.php';
 *   ... contenido ...
 *   require LCN_ROOT.'/app/views/layouts/public_footer.php';
 */
$page_title    = $page_title    ?? setting('business_name', APP_NAME);
$active_public = $active_public ?? 'home';
$wa = preg_replace('/\D/', '', (string) setting('whatsapp', ''));
$nav = [
    'home'       => ['Inicio',     pub_url('index.php')],
    'inventario' => ['Inventario', pub_url('inventario.php')],
    'contacto'   => ['Contacto',   pub_url('contacto.php')],
];
?>
<!doctype html>
<html lang="es" class="scroll-smooth">
<head>
    <title><?= e($page_title) ?> · LONDRES Casa de Novias</title>
    <meta name="description" content="LONDRES Casa de Novias — Alquiler y venta de vestidos de novia, vestidos de gala, trajes y accesorios para tu evento especial.">
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
</head>
<body class="bg-white font-sans text-gray-800 antialiased">

<header data-public-header class="sticky top-0 z-40 border-b border-gray-100 bg-white/90 backdrop-blur-md transition-shadow">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="<?= pub_url('index.php') ?>" class="flex items-center"><?= brand_lockup('dark', 'md') ?></a>

        <nav class="hidden items-center gap-8 md:flex">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>" class="text-sm font-medium transition <?= $active_public === $key ? 'text-brand-red' : 'text-gray-600 hover:text-brand-red' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-2">
            <?php if ($wa): ?>
            <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"
               class="hidden items-center gap-2 rounded-xl bg-brand-red px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 sm:inline-flex">
                <?= icon('whatsapp', 'w-4 h-4') ?> Reservar cita
            </a>
            <?php endif; ?>
            <button type="button" data-mobile-menu-toggle class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 md:hidden"><?= icon('menu', 'w-6 h-6') ?></button>
        </div>
    </div>

    <!-- Menú móvil -->
    <div id="mobile-menu" class="hidden border-t border-gray-100 bg-white md:hidden">
        <nav class="space-y-1 px-4 py-4">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>" class="block rounded-xl px-4 py-3 text-sm font-medium <?= $active_public === $key ? 'bg-red-50 text-brand-red' : 'text-gray-700 hover:bg-gray-50' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" class="mt-2 flex items-center justify-center gap-2 rounded-xl bg-brand-red px-4 py-3 text-sm font-semibold text-white"><?= icon('whatsapp','w-4 h-4') ?> Reservar cita por WhatsApp</a><?php endif; ?>
        </nav>
    </div>
</header>

<main>
