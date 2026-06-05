<?php
/**
 * Productos / Inventario — Marcar una imagen como principal.
 * LONDRES Casa de Novias
 *
 * admin/productos/set-main-imagen.php  (N=2)  POST + CSRF
 * Permiso: products.manage
 *
 * Espera: image_id, product_id.
 * Desmarca la principal anterior, marca la nueva y actualiza products.main_image.
 * Responde JSON si es AJAX; si no, redirige de vuelta a la edición.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

$wantsJson = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
    || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

function respond(bool $ok, string $message, bool $wantsJson, ?int $productId = null): void
{
    if ($wantsJson) {
        json_response(['ok' => $ok, 'message' => $message], $ok ? 200 : 400);
    }
    flash($ok ? 'success' : 'error', $message);
    if ($productId) {
        redirect(admin_url('productos/editar.php?id=' . $productId));
    }
    back(admin_url('productos/index.php'));
}

if (!is_post()) {
    respond(false, 'Método no permitido.', $wantsJson);
}
require_csrf();

$imageId = (int) post('image_id', 0);
$image = $imageId ? db_one('SELECT * FROM product_images WHERE id = :id', ['id' => $imageId]) : null;

if (!$image) {
    respond(false, 'La imagen no existe.', $wantsJson, (int) post('product_id', 0) ?: null);
}

$pid = (int) $image['product_id'];

/* Desmarcar todas y marcar la elegida */
db_exec('UPDATE product_images SET is_main = 0 WHERE product_id = :id', ['id' => $pid]);
db_update('product_images', ['is_main' => 1], 'id = :id', ['id' => $imageId]);

/* Reflejar en el producto */
db_update('products', ['main_image' => $image['image_path']], 'id = :id', ['id' => $pid]);

log_activity('product.image_main', 'product', $pid, 'Imagen principal actualizada');

respond(true, 'Imagen principal actualizada.', $wantsJson, $pid);
