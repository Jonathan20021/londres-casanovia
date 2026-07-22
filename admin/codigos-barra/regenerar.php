<?php
/**
 * Códigos de barra — Regenerar el código de un producto.
 * LONDRES Casa de Novias
 *
 * admin/codigos-barra/regenerar.php  (N=2)  ·  Permiso: products.manage
 * POST: id
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

if (!is_post()) {
    redirect(admin_url('codigos-barra/index.php'));
}
require_csrf();

$id = (int) post('id', 0);
$product = $id ? db_one('SELECT id, name FROM products WHERE id = :id', ['id' => $id]) : null;

if (!$product) {
    flash('error', 'El producto solicitado no existe.');
    back(admin_url('codigos-barra/index.php'));
}

$code = barcode_assign($id, true);
log_activity('barcode.regenerate', 'product', $id, 'Regeneró el código de barras: ' . $code);
flash('success', 'Código de barras regenerado: ' . $code);

back(admin_url('productos/ver.php?id=' . $id));
