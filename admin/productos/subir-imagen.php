<?php
/**
 * Productos / Inventario — Subir una imagen a un producto (AJAX).
 * LONDRES Casa de Novias
 *
 * admin/productos/subir-imagen.php  (N=2)  POST + CSRF
 * Permiso: products.manage
 *
 * Espera: product_id (POST) y un archivo en $_FILES['image'].
 * Responde JSON: { ok, path?, id?, is_main?, error? }
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

if (!is_post()) {
    json_response(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
require_csrf();

$productId = (int) post('product_id', 0);
$product = $productId ? db_one('SELECT id, main_image FROM products WHERE id = :id', ['id' => $productId]) : null;

if (!$product) {
    json_response(['ok' => false, 'error' => 'Producto no encontrado.'], 404);
}

if (empty($_FILES['image']['name'])) {
    json_response(['ok' => false, 'error' => 'No se recibió ninguna imagen.'], 422);
}

/* Subir el archivo de forma segura (valida ext/mime/tamaño) */
$up = upload_image($_FILES['image'], 'products');
if (!$up['ok']) {
    json_response(['ok' => false, 'error' => $up['error']], 422);
}

/* ¿El producto ya tiene una imagen principal? */
$hasMain = (int) db_value('SELECT COUNT(*) FROM product_images WHERE product_id = :id AND is_main = 1', ['id' => $productId]) > 0;
$isMain  = $hasMain ? 0 : 1;

$sort = (int) db_value('SELECT COALESCE(MAX(sort_order),-1)+1 FROM product_images WHERE product_id = :id', ['id' => $productId]);

$imageId = db_insert('product_images', [
    'product_id' => $productId,
    'image_path' => $up['path'],
    'is_main'    => $isMain,
    'sort_order' => $sort,
]);

/* Si pasa a ser principal, reflejarlo en products.main_image */
if ($isMain) {
    db_update('products', ['main_image' => $up['path']], 'id = :id', ['id' => $productId]);
}

log_activity('product.image_add', 'product', $productId, 'Imagen añadida al producto');

json_response([
    'ok'      => true,
    'id'      => $imageId,
    'path'    => upload_url($up['path']),
    'is_main' => (bool) $isMain,
]);
