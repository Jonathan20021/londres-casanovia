<?php
/**
 * Sidebar del panel administrativo — estilo Shoplytic (acordeón).
 * Variable esperada: $active (clave del módulo activo).
 * LONDRES Casa de Novias
 */
$active = $active ?? '';

/* Solicitudes pendientes (badge) */
$pendingRequests = (int) db_value("SELECT COUNT(*) FROM rental_requests WHERE status = 'pending'");

/*
 * Estructura del menú.
 * single: ['type'=>'link', key, label, icon, url, perm]
 * grupo : ['type'=>'group', label, icon, children=>[ [key,label,url,perm,badge?] ]]
 */
$menu = [
    ['type' => 'link', 'key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'url' => admin_url('dashboard.php'), 'perm' => null],
    ['type' => 'group', 'label' => 'Inventario', 'icon' => 'box', 'children' => [
        ['key' => 'productos',  'label' => 'Productos',  'url' => admin_url('productos/index.php'),  'perm' => 'products.view'],
        ['key' => 'categorias', 'label' => 'Categorías', 'url' => admin_url('categorias/index.php'), 'perm' => 'categories.manage'],
        ['key' => 'codigos-barra', 'label' => 'Códigos de barra', 'url' => admin_url('codigos-barra/index.php'), 'perm' => 'products.view'],
    ]],
    ['type' => 'group', 'label' => 'Alquileres', 'icon' => 'calendar', 'children' => [
        ['key' => 'alquileres',  'label' => 'Lista',       'url' => admin_url('alquileres/index.php'),       'perm' => 'rentals.manage'],
        ['key' => 'kanban',      'label' => 'Tablero',     'url' => admin_url('alquileres/kanban.php'),      'perm' => 'rentals.manage'],
        ['key' => 'calendario',  'label' => 'Calendario',  'url' => admin_url('alquileres/calendario.php'),  'perm' => 'calendar.view'],
        ['key' => 'solicitudes', 'label' => 'Solicitudes', 'url' => admin_url('solicitudes/index.php'),      'perm' => 'requests.manage', 'badge' => $pendingRequests],
    ]],
    ['type' => 'link', 'key' => 'clientes', 'label' => 'Clientes', 'icon' => 'users', 'url' => admin_url('clientes/index.php'), 'perm' => 'customers.manage'],
    ['type' => 'group', 'label' => 'Finanzas', 'icon' => 'banknotes', 'children' => [
        ['key' => 'facturas', 'label' => 'Facturas', 'url' => admin_url('facturas/index.php'), 'perm' => 'invoices.manage'],
        ['key' => 'pagos',    'label' => 'Pagos',    'url' => admin_url('pagos/index.php'),    'perm' => 'payments.manage'],
        ['key' => 'ventas',   'label' => 'Ventas',   'url' => admin_url('ventas/index.php'),   'perm' => 'sales.manage'],
    ]],
    ['type' => 'link', 'key' => 'reportes', 'label' => 'Reportes', 'icon' => 'chart', 'url' => admin_url('reportes/index.php'), 'perm' => 'reports.view'],
];

/* Sección "Sistema" (inferior) */
$systemMenu = [
    ['key' => 'usuarios',      'label' => 'Usuarios',      'icon' => 'user', 'url' => admin_url('usuarios/index.php'),      'perm' => 'users.manage'],
    ['key' => 'configuracion', 'label' => 'Configuración', 'icon' => 'cog',  'url' => admin_url('configuracion/index.php'), 'perm' => 'settings.manage'],
];

/* ¿Tiene el usuario acceso a algún hijo del grupo? */
$visibleChildren = function (array $children): array {
    return array_filter($children, fn($c) => $c['perm'] === null || user_can($c['perm']));
};

/* Clases reutilizables */
$leafClass = function (bool $isActive): string {
    return ($isActive
        ? 'bg-red-50 text-brand-red font-semibold'
        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 font-medium')
        . ' lcn-nav-item flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition';
};
?>
<div id="lcn-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-brand-dark/50 backdrop-blur-sm lg:hidden" data-sidebar-close></div>

<aside id="lcn-sidebar"
       class="lcn-sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-100 bg-white transition-transform duration-300 lg:translate-x-0">

    <!-- Logo + control de colapso -->
    <div class="flex h-20 items-center gap-2 px-6 lcn-sidebar-head">
        <a href="<?= admin_url('dashboard.php') ?>" class="flex min-w-0 items-center lcn-nav-label">
            <?= brand_lockup('dark', 'sm') ?>
        </a>
        <!-- Marca compacta: solo visible cuando el menú está plegado -->
        <a href="<?= admin_url('dashboard.php') ?>" class="lcn-mark-mini mx-auto hidden" title="LONDRES Casa de Novias">
            <img src="<?= asset('img/logo-cabina.svg') ?>" alt="LONDRES" class="h-8 w-auto">
        </a>

        <button type="button" data-sidebar-collapse
                class="lcn-collapse-btn ml-auto hidden h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 lg:flex"
                aria-label="Plegar o desplegar el menú" title="Plegar el menú" aria-expanded="true">
            <span class="lcn-collapse-icon transition-transform duration-300"><?= icon('chevron-left', 'w-5 h-5') ?></span>
        </button>
        <button type="button" data-sidebar-close class="ml-auto text-gray-400 hover:text-gray-600 lg:hidden"><?= icon('x', 'w-5 h-5') ?></button>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-3 no-scrollbar">
        <?php foreach ($menu as $item): ?>
            <?php if ($item['type'] === 'link'):
                if ($item['perm'] !== null && !user_can($item['perm'])) continue;
                $isActive = $active === $item['key']; ?>
                <a href="<?= e($item['url']) ?>" class="<?= $leafClass($isActive) ?>" title="<?= e($item['label']) ?>">
                    <span class="lcn-nav-icon shrink-0 <?= $isActive ? 'text-brand-red' : 'text-gray-400' ?>"><?= icon($item['icon'], 'w-5 h-5') ?></span>
                    <span class="lcn-nav-label flex-1"><?= e($item['label']) ?></span>
                </a>
            <?php else:
                $children = $visibleChildren($item['children']);
                if (!$children) continue;
                $childKeys = array_column($children, 'key');
                $open = in_array($active, $childKeys, true); ?>
                <?php $groupBadge = array_sum(array_map(fn($c) => (int) ($c['badge'] ?? 0), $children)); ?>
                <details class="lcn-nav-group select-none" <?= $open ? 'open' : '' ?>>
                    <summary class="lcn-nav-item flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium <?= $open ? 'text-gray-900' : 'text-gray-500' ?> hover:bg-gray-50"
                             title="<?= e($item['label']) ?>">
                        <span class="lcn-nav-icon relative shrink-0 <?= $open ? 'text-brand-red' : 'text-gray-400' ?>">
                            <?= icon($item['icon'], 'w-5 h-5') ?>
                            <?php if ($groupBadge > 0): ?>
                                <!-- Aviso visible también con el menú plegado -->
                                <span class="lcn-mini-badge absolute -right-1.5 -top-1.5 hidden h-2.5 w-2.5 rounded-full bg-brand-red ring-2 ring-white"></span>
                            <?php endif; ?>
                        </span>
                        <span class="lcn-nav-label flex-1"><?= e($item['label']) ?></span>
                        <span class="acc-chevron lcn-nav-label text-gray-400"><?= icon('chevron-down', 'w-4 h-4') ?></span>
                    </summary>
                    <div class="lcn-nav-children mt-1 space-y-1 pl-4">
                        <?php foreach ($children as $c):
                            $childActive = $active === $c['key']; ?>
                            <a href="<?= e($c['url']) ?>"
                               class="flex items-center gap-3 rounded-xl py-2 pl-7 pr-3 text-sm transition <?= $childActive ? 'font-semibold text-brand-red' : 'font-medium text-gray-500 hover:text-gray-900' ?>">
                                <span class="h-1.5 w-1.5 rounded-full <?= $childActive ? 'bg-brand-red' : 'bg-gray-300' ?>"></span>
                                <span class="flex-1"><?= e($c['label']) ?></span>
                                <?php if (!empty($c['badge'])): ?>
                                    <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-red px-1.5 text-xs font-semibold text-white"><?= (int) $c['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Sección Sistema -->
        <?php
        $sysVisible = array_filter($systemMenu, fn($s) => $s['perm'] === null || user_can($s['perm']));
        if ($sysVisible): ?>
            <p class="lcn-nav-section lcn-nav-label px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Sistema</p>
            <div class="lcn-nav-divider mt-4 hidden border-t border-gray-100"></div>
            <?php foreach ($sysVisible as $s): $isActive = $active === $s['key']; ?>
                <a href="<?= e($s['url']) ?>" class="<?= $leafClass($isActive) ?>" title="<?= e($s['label']) ?>">
                    <span class="lcn-nav-icon shrink-0 <?= $isActive ? 'text-brand-red' : 'text-gray-400' ?>"><?= icon($s['icon'], 'w-5 h-5') ?></span>
                    <span class="lcn-nav-label flex-1"><?= e($s['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- Pie: usuario + logout -->
    <div class="lcn-sidebar-foot border-t border-gray-100 p-3">
        <?php $u = current_user(); ?>
        <div class="lcn-user-pill flex items-center gap-3 rounded-2xl bg-gray-50 px-3 py-2.5"
             title="<?= e(($u['name'] ?? '') . ' · ' . ($u['role_name'] ?? '')) ?>">
            <?= avatar($u['name'] ?? '', 'h-9 w-9 text-sm shrink-0') ?>
            <div class="lcn-nav-label min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-900"><?= e($u['name'] ?? '') ?></p>
                <p class="truncate text-xs text-gray-400"><?= e($u['role_name'] ?? '') ?></p>
            </div>
            <a href="<?= admin_url('logout.php') ?>" data-confirm="¿Cerrar sesión?" class="lcn-nav-label text-gray-400 transition hover:text-brand-red" title="Cerrar sesión"><?= icon('logout', 'w-5 h-5') ?></a>
        </div>
        <!-- Salir: versión compacta cuando el menú está plegado -->
        <a href="<?= admin_url('logout.php') ?>" data-confirm="¿Cerrar sesión?"
           class="lcn-logout-mini mt-2 hidden items-center justify-center rounded-xl py-2 text-gray-400 transition hover:bg-red-50 hover:text-brand-red"
           title="Cerrar sesión"><?= icon('logout', 'w-5 h-5') ?></a>
    </div>
</aside>
