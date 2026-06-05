<?php
/**
 * Punto de entrada del panel: redirige al dashboard (o al login si no hay sesión).
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

require_login();
redirect(admin_url('dashboard.php'));
