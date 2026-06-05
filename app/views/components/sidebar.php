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
    return $isActive
        ? 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold bg-red-50 text-brand-red'
        : 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900 transition';
};
?>
<div id="lcn-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-brand-dark/50 backdrop-blur-sm lg:hidden" data-sidebar-close></div>

<aside id="lcn-sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-100 bg-white transition-transform duration-300 lg:translate-x-0">

    <!-- Logo -->
    <div class="flex h-20 items-center gap-2 px-6">
        <a href="<?= admin_url('dashboard.php') ?>" class="flex items-center"><?= brand_lockup('dark', 'sm') ?></a>
        <button type="button" data-sidebar-close class="ml-auto text-gray-400 hover:text-gray-600 lg:hidden"><?= icon('x', 'w-5 h-5') ?></button>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-3 no-scrollbar">
        <?php foreach ($menu as $item): ?>
            <?php if ($item['type'] === 'link'):
                if ($item['perm'] !== null && !user_can($item['perm'])) continue;
                $isActive = $active === $item['key']; ?>
                <a href="<?= e($item['url']) ?>" class="<?= $leafClass($isActive) ?>">
                    <span class="<?= $isActive ? 'text-brand-red' : 'text-gray-400' ?>"><?= icon($item['icon'], 'w-5 h-5') ?></span>
                    <span class="flex-1"><?= e($item['label']) ?></span>
                </a>
            <?php else:
                $children = $visibleChildren($item['children']);
                if (!$children) continue;
                $childKeys = array_column($children, 'key');
                $open = in_array($active, $childKeys, true); ?>
                <details class="select-none" <?= $open ? 'open' : '' ?>>
                    <summary class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium <?= $open ? 'text-gray-900' : 'text-gray-500' ?> hover:bg-gray-50">
                        <span class="<?= $open ? 'text-brand-red' : 'text-gray-400' ?>"><?= icon($item['icon'], 'w-5 h-5') ?></span>
                        <span class="flex-1"><?= e($item['label']) ?></span>
                        <span class="acc-chevron text-gray-400"><?= icon('chevron-down', 'w-4 h-4') ?></span>
                    </summary>
                    <div class="mt-1 space-y-1 pl-4">
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
            <p class="px-3 pb-1 pt-5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Sistema</p>
            <?php foreach ($sysVisible as $s): $isActive = $active === $s['key']; ?>
                <a href="<?= e($s['url']) ?>" class="<?= $leafClass($isActive) ?>">
                    <span class="<?= $isActive ? 'text-brand-red' : 'text-gray-400' ?>"><?= icon($s['icon'], 'w-5 h-5') ?></span>
                    <span class="flex-1"><?= e($s['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- Pie: usuario + logout -->
    <div class="border-t border-gray-100 p-3">
        <?php $u = current_user(); ?>
        <div class="flex items-center gap-3 rounded-2xl bg-gray-50 px-3 py-2.5">
            <?= avatar($u['name'] ?? '', 'h-9 w-9 text-sm') ?>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-900"><?= e($u['name'] ?? '') ?></p>
                <p class="truncate text-xs text-gray-400"><?= e($u['role_name'] ?? '') ?></p>
            </div>
            <a href="<?= admin_url('logout.php') ?>" data-confirm="¿Cerrar sesión?" class="text-gray-400 transition hover:text-brand-red" title="Cerrar sesión"><?= icon('logout', 'w-5 h-5') ?></a>
        </div>
    </div>
</aside>
