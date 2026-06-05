<?php
/**
 * Cierre de sesión del panel administrativo.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Registrar la salida antes de destruir la sesión.
if ($u = current_user()) {
    log_activity('logout', 'user', (int) $u['id'], 'Cierre de sesión: ' . $u['email']);
}

logout_user();
redirect(admin_url('login.php'));
