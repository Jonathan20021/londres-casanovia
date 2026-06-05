<?php
/**
 * Productos / Inventario — Eliminar producto (con borrado suave inteligente).
 * LONDRES Casa de Novias
 *
 * admin/productos/eliminar.php  (N=2)  POST + CSRF
 * Permiso: products.manage
 *
 * Regla:
 *  - Si el producto NO tiene alquileres/ventas asociados -> borrado físico
 *    (incluye eliminar archivos de imágenes con delete_upload).
 *  - Si tiene historial -> borrado suave: status='inactive', commercial_status='unavailable'.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.manage');

/* Solo aceptamos POST con CSRF válido */
if (!is_post()) {
    redirect(admin_url('productos/index.php'));
}
require_csrf();

$id = (int) post('id', 0);
$product = $id ? db_one('SELECT * FROM products WHERE id = :id', ['id' => $id]) : null;

if (!$product) {
    flash('error', 'El producto no existe o ya fue eliminado.');
    redirect(admin_url('productos/index.php'));
}

/* ¿Tiene movimientos asociados? */
$hasRentals = (int) db_value('SELECT COUNT(*) FROM rentals WHERE product_id = :id', ['id' => $id]) > 0;
$hasSales   = (int) db_value('SELECT COUNT(*) FROM sales WHERE product_id = :id', ['id' => $id]) > 0;

if ($hasRentals || $hasSales) {
    /* Borrado suave: conservar el registro por integridad del historial */
    db_update('products', [
        'status'            => 'inactive',
        'commercial_status' => 'unavailable',
    ], 'id = :id', ['id' => $id]);

    log_activity('product.soft_delete', 'product', $id, 'Producto desactivado (tiene historial): ' . $product['name']);
    flash('warning', 'El producto tiene alquileres o ventas asociados, por lo que se desactivó en lugar de eliminarse.');
    redirect(admin_url('productos/index.php'));
}

/* Borrado físico: recoger imágenes para borrar archivos */
$images = db_all('SELECT image_path FROM product_images WHERE product_id = :id', ['id' => $id]);

/* Eliminar el producto (product_images cae por ON DELETE CASCADE) */
db_delete('products', 'id = :id', ['id' => $id]);

/* Borrar archivos físicos */
foreach ($images as $img) {
    delete_upload($img['image_path']);
}
if (!empty($product['main_image'])) {
    delete_upload($product['main_image']);
}

log_activity('product.delete', 'product', $id, 'Producto eliminado: ' . $product['name']);
flash('success', 'Producto eliminado correctamente.');
redirect(admin_url('productos/index.php'));
