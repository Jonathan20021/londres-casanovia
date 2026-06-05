<?php
/**
 * Punto de entrada raíz: redirige al sitio público.
 * LONDRES Casa de Novias
 */
require_once __DIR__ . '/app/bootstrap.php';
redirect(pub_url('index.php'));
