<?php
/**
 * Cabecera del layout administrativo — estilo Shoplytic.
 * Variables (opcionales salvo $page_title):
 *   $page_title, $page_subtitle, $active, $header_actions (HTML), $use_charts
 */
require_login();
$page_title     = $page_title     ?? 'Panel';
$page_subtitle  = $page_subtitle  ?? '';
$active         = $active          ?? '';
$header_actions = $header_actions  ?? '';
$u = current_user();

$notifs = db_all('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 8', ['uid' => $u['id']]);
$unread = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0', ['uid' => $u['id']]);

$iconBtn = 'flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-700';
?>
<!doctype html>
<html lang="es" class="h-full">
<head>
    <title><?= e($page_title) ?> · LONDRES Casa de Novias</title>
    <?php require LCN_ROOT . '/app/views/layouts/_head_assets.php'; ?>
</head>
<body class="h-full bg-[#F6F6F4] font-sans text-gray-800 antialiased dark:bg-[#0f1115] dark:text-gray-200">

<?php require LCN_ROOT . '/app/views/components/sidebar.php'; ?>

<div class="lg:pl-72">
    <!-- Topbar -->
    <header class="sticky top-0 z-20 border-b border-gray-100 bg-[#F6F6F4]/85 backdrop-blur-md dark:border-gray-800 dark:bg-[#0f1115]/85">
        <div class="flex h-20 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" data-sidebar-open class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden"><?= icon('menu', 'w-6 h-6') ?></button>

            <!-- Título -->
            <div class="min-w-0">
                <h1 class="truncate font-serif text-xl font-semibold text-gray-900 dark:text-gray-100 sm:text-[1.6rem]"><?= e($page_title) ?></h1>
                <?php if ($page_subtitle): ?><p class="truncate text-xs text-gray-400 sm:text-sm"><?= e($page_subtitle) ?></p><?php endif; ?>
            </div>

            <!-- Buscador global -->
            <form action="<?= admin_url('productos/index.php') ?>" method="get" class="ml-auto hidden flex-1 md:block lg:max-w-md">
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400"><?= icon('search', 'w-5 h-5') ?></span>
                    <input type="search" name="q" placeholder="Buscar productos, SKU…"
                           class="w-full rounded-full border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm text-gray-700 shadow-soft transition focus:border-brand-red focus:outline-none focus:ring-2 focus:ring-brand-red/15 dark:border-gray-700 dark:bg-[#13151b]">
                </label>
            </form>

            <!-- Acciones -->
            <div class="ml-auto flex items-center gap-2 md:ml-3">
                <a href="<?= pub_url('index.php') ?>" target="_blank" rel="noopener" class="<?= $iconBtn ?>" title="Ver sitio público"><?= icon('eye', 'w-5 h-5') ?></a>
                <button type="button" data-theme-toggle class="<?= $iconBtn ?>" title="Modo claro/oscuro">
                    <span class="dark:hidden"><?= icon('moon', 'w-5 h-5') ?></span>
                    <span class="hidden dark:inline"><?= icon('sun', 'w-5 h-5') ?></span>
                </button>

                <!-- Notificaciones -->
                <div class="relative">
                    <button type="button" data-dropdown-toggle="notif-menu" class="<?= $iconBtn ?> relative">
                        <?= icon('bell', 'w-5 h-5') ?>
                        <?php if ($unread > 0): ?><span class="absolute right-2 top-2 flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-red opacity-60"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-red"></span></span><?php endif; ?>
                    </button>
                    <div id="notif-menu" data-dropdown class="absolute right-0 mt-2 hidden w-80 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-card animate-scale-in">
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900">Notificaciones</p>
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-brand-red"><?= $unread ?> nuevas</span>
                        </div>
                        <div class="max-h-80 divide-y divide-gray-50 overflow-y-auto">
                            <?php if (!$notifs): ?>
                                <p class="px-4 py-8 text-center text-sm text-gray-400">Sin notificaciones</p>
                            <?php else: foreach ($notifs as $n): ?>
                                <div class="flex gap-3 px-4 py-3 <?= $n['is_read'] ? '' : 'bg-red-50/40' ?>">
                                    <span class="mt-0.5 text-brand-red"><?= icon('bell', 'w-4 h-4') ?></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900"><?= e($n['title']) ?></p>
                                        <p class="truncate text-xs text-gray-500"><?= e($n['message']) ?></p>
                                        <p class="mt-0.5 text-[11px] text-gray-400"><?= format_datetime($n['created_at']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Usuario (pill) -->
                <div class="relative">
                    <button type="button" data-dropdown-toggle="user-menu" class="flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1 pl-1 pr-2 transition hover:bg-gray-50">
                        <?= avatar($u['name'] ?? '', 'h-9 w-9 text-sm') ?>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-semibold leading-tight text-gray-900 dark:text-gray-100"><?= e(explode(' ', (string) $u['name'])[0]) ?></span>
                            <span class="block text-xs text-gray-400"><?= e($u['role_name']) ?></span>
                        </span>
                        <span class="hidden pr-1 text-gray-400 sm:block"><?= icon('chevron-down', 'w-4 h-4') ?></span>
                    </button>
                    <div id="user-menu" data-dropdown class="absolute right-0 mt-2 hidden w-56 overflow-hidden rounded-2xl border border-gray-100 bg-white py-1.5 shadow-card animate-scale-in">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="text-sm font-semibold text-gray-900"><?= e($u['name']) ?></p>
                            <p class="truncate text-xs text-gray-400"><?= e($u['email']) ?></p>
                        </div>
                        <?php if (user_can('settings.manage')): ?>
                        <a href="<?= admin_url('configuracion/index.php') ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50"><?= icon('cog', 'w-4 h-4') ?> Configuración</a>
                        <?php endif; ?>
                        <a href="<?= admin_url('logout.php') ?>" data-confirm="¿Cerrar sesión?" class="flex items-center gap-2 px-4 py-2.5 text-sm text-brand-red hover:bg-red-50"><?= icon('logout', 'w-4 h-4') ?> Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido -->
    <main class="px-4 py-7 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <?php if ($header_actions): ?>
                <div class="mb-6 flex flex-wrap items-center justify-end gap-2"><?= $header_actions ?></div>
            <?php endif; ?>
            <?= render_flash() ?>
