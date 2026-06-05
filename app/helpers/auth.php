<?php
/**
 * Autenticación, sesión de usuario y control de permisos por rol.
 * LONDRES Casa de Novias
 *
 * Roles: Super Admin, Administrador, Cajera, Vendedora, Inventario.
 * Permisos = claves de módulo (ej. 'products.manage', 'rentals.manage',
 * 'invoices.manage', 'payments.manage', 'sales.manage', 'reports.view',
 * 'users.manage', 'settings.manage', 'customers.manage',
 * 'products.view'). Super Admin tiene comodín total.
 */
declare(strict_types=1);

function current_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user ?: null;
    }
    if (empty($_SESSION['user_id'])) {
        $user = false;
        return null;
    }
    $user = db_one(
        'SELECT u.*, r.name AS role_name
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.status = "active"',
        ['id' => $_SESSION['user_id']]
    ) ?: false;

    return $user ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    db_update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Protege una página de administración. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Debe iniciar sesión para continuar.');
        redirect(admin_url('login.php'));
    }
}

function user_role(): ?string
{
    return current_user()['role_name'] ?? null;
}

function has_role(string ...$roles): bool
{
    return in_array(user_role(), $roles, true);
}

/**
 * Comprueba si el usuario actual tiene un permiso.
 * Super Admin siempre true. El resto se valida contra role_permissions.
 */
function user_can(string $permission): bool
{
    $u = current_user();
    if (!$u) return false;
    if (($u['role_name'] ?? '') === 'Super Admin') return true;

    static $cache = [];
    $roleId = (int) $u['role_id'];
    if (!isset($cache[$roleId])) {
        $rows = db_all(
            'SELECT p.name FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :rid',
            ['rid' => $roleId]
        );
        $cache[$roleId] = array_column($rows, 'name');
    }
    return in_array('*', $cache[$roleId], true) || in_array($permission, $cache[$roleId], true);
}

/** Exige login + permiso; corta con 403 si no lo tiene. */
function require_permission(string $permission): void
{
    require_login();
    if (!user_can($permission)) {
        http_response_code(403);
        $title = 'Acceso restringido';
        echo '<!doctype html><meta charset="utf-8"><title>403</title>'
           . '<div style="font-family:system-ui;max-width:480px;margin:15vh auto;text-align:center">'
           . '<h1 style="font-size:3rem;color:#C8102E;margin:0">403</h1>'
           . '<p style="color:#475467">No tiene permisos para acceder a esta sección.</p>'
           . '<a href="' . admin_url('dashboard.php') . '" style="color:#C8102E">Volver al panel</a></div>';
        exit;
    }
}

/** Iniciales para avatar. */
function user_initials(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') return 'U';
    $parts = preg_split('/\s+/', $name);
    $a = mb_substr($parts[0], 0, 1);
    $b = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
    return mb_strtoupper($a . $b);
}
