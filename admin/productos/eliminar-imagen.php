<?php
/**
 * Productos / Inventario — Eliminar una imagen de la galería.
 * LONDRES Casa de Novias
 *
 * admin/productos/eliminar-imagen.php  (N=2)  POST + CSRF
 * Permiso: products.manage
 *
 * Espera: image_id, product_id.
 * Si la imagen era la principal, asigna otra como principal automáticamente.
 * Responde JSON si es petición AJAX; si no, redirige de vuelta (back()).
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

/* ¿La petición espera JSON? (cabecera AJAX) */
$wantsJson = !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
    || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

/** Responder según el tipo de petición. */
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

$imageId   = (int) post('image_id', 0);
$productId  = (int) post('product_id', 0);

$image = $imageId ? db_one('SELECT * FROM product_images WHERE id = :id', ['id' => $imageId]) : null;
if (!$image) {
    respond(false, 'La imagen no existe o ya fue eliminada.', $wantsJson, $productId ?: null);
}

$pid     = (int) $image['product_id'];
$wasMain = (int) $image['is_main'] === 1;
$path    = $image['image_path'];

/* Eliminar el registro y el archivo físico */
db_delete('product_images', 'id = :id', ['id' => $imageId]);
delete_upload($path);

/* Si era la principal, promover otra imagen (la de menor sort_order) */
$promoted = null;
$mainCleared = false;
if ($wasMain) {
    $next = db_one('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC LIMIT 1', ['id' => $pid]);
    if ($next) {
        db_update('product_images', ['is_main' => 1], 'id = :id', ['id' => $next['id']]);
        db_update('products', ['main_image' => $next['image_path']], 'id = :id', ['id' => $pid]);
        $promoted = ['id' => (int) $next['id'], 'src' => upload_url($next['image_path'])];
    } else {
        /* No quedan imágenes */
        db_update('products', ['main_image' => null], 'id = :id', ['id' => $pid]);
        $mainCleared = true;
    }
}

log_activity('product.image_delete', 'product', $pid, 'Imagen eliminada del producto');

if ($wantsJson) {
    json_response(['ok' => true, 'message' => 'Imagen eliminada correctamente.', 'promoted' => $promoted, 'main_cleared' => $mainCleared]);
}
respond(true, 'Imagen eliminada correctamente.', $wantsJson, $pid);
