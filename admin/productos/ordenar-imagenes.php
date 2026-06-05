<?php
/**
 * Productos / Inventario — Reordenar imágenes de la galería (drag & drop).
 * LONDRES Casa de Novias
 *
 * admin/productos/ordenar-imagenes.php  (N=2)  POST + CSRF (AJAX)
 * Permiso: products.manage
 *
 * Espera: product_id, order[] = lista de IDs de product_images en el nuevo orden.
 * Actualiza sort_order según la posición. Responde JSON.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

if (!is_post() || !verify_csrf()) {
    json_response(['ok' => false, 'error' => 'Solicitud inválida.'], 419);
}

$productId = (int) post('product_id', 0);
$order     = post('order', []);

if ($productId <= 0 || !is_array($order) || !$order) {
    json_response(['ok' => false, 'error' => 'Datos inválidos.'], 422);
}

$pos = 0;
foreach ($order as $imageId) {
    $imageId = (int) $imageId;
    if ($imageId <= 0) continue;
    db_update('product_images', ['sort_order' => $pos], 'id = :id AND product_id = :pid', ['id' => $imageId, 'pid' => $productId]);
    $pos++;
}

log_activity('product.reorder_images', 'product', $productId, 'Reordenó las imágenes de la galería');

json_response(['ok' => true, 'count' => $pos]);
