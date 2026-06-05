<?php
/**
 * Sesiones seguras + protección CSRF
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  Sesión segura
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('LCN_SESSION');
    session_start();

    // Regenerar el ID periódicamente para mitigar fixation
    if (empty($_SESSION['_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_initiated'] = true;
    }
}

/* ------------------------------------------------------------------ *
 *  CSRF
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Campo oculto para formularios. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** Comprueba el token enviado por POST o cabecera AJAX. */
function verify_csrf(): bool
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && $token !== '' && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

/** Aborta la petición si un POST llega sin token válido. */
function require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !verify_csrf()) {
        http_response_code(403);
        die('Token de seguridad inválido o expirado. Recargue la página e intente de nuevo.');
    }
}
