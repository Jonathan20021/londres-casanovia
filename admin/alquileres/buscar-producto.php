<?php
/**
 * Alquileres · Buscar producto por código (JSON)
 * LONDRES Casa de Novias
 *
 * admin/alquileres/buscar-producto.php  (N=2)  ·  Permiso: rentals.manage
 *
 * Lo usa el lector de la pantalla de crear alquiler cuando el código escaneado
 * no está en el catálogo cargado con la página: resuelve la etiqueta con
 * barcode_lookup() (unidad física, código maestro, SKU, dígitos o id) y
 * devuelve la pieza lista para agregarse, o el motivo por el que no se puede.
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$code = barcode_normalize((string) get_param('code'));
if ($code === '') {
    json_response(['ok' => false, 'message' => 'No se recibió ningún código.'], 400);
}

$hit = barcode_lookup($code);
if (!$hit) {
    json_response(['ok' => false, 'message' => 'Ningún producto tiene el código ' . $code . '.']);
}

$name = (string) $hit['name'];

if (($hit['status'] ?? 'active') !== 'active') {
    json_response(['ok' => false, 'message' => '«' . $name . '» está inactivo en el inventario.']);
}
if (!in_array($hit['type'], ['rental', 'both'], true)) {
    json_response(['ok' => false, 'message' => '«' . $name . '» es un producto de venta: no está disponible para alquiler.']);
}

// Mismas claves que el catálogo del formulario, para poder indexarlo en el acto.
$product = [
    'id'                => (int) $hit['id'],
    'name'              => $name,
    'sku'               => (string) ($hit['sku'] ?? ''),
    'barcode'           => (string) ($hit['barcode'] ?? ''),
    'rental_price'      => (string) $hit['rental_price'],
    'deposit_amount'    => (string) ($hit['deposit_amount'] ?? '0'),
    'commercial_status' => (string) $hit['commercial_status'],
    'type'              => (string) $hit['type'],
    'is_complement'     => (string) (int) ($hit['is_complement'] ?? 0),
    'size'              => (string) ($hit['size'] ?? ''),
    'color'             => (string) ($hit['color'] ?? ''),
    'material'          => (string) ($hit['material'] ?? ''),
    'main_image'        => (string) ($hit['main_image'] ?? ''),
    'category_name'     => (string) ($hit['category_name'] ?? ''),
    'image_url'         => upload_url($hit['main_image'] ?? null),
    'units'             => barcode_units_by_product([(int) $hit['id']])[(int) $hit['id']] ?? [],
];

json_response([
    'ok'          => true,
    'product'     => $product,
    'unit_number' => (int) ($hit['unit_number'] ?? 0),
]);
