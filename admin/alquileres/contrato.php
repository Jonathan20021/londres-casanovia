<?php
/**
 * Endpoint: Contrato de alquiler — LONDRES Casa de Novias
 *
 * Carga el alquiler (?id), su cliente y el producto, y renderiza la
 * plantilla imprimible app/views/templates/contract.php para window.print().
 *
 * Permiso: rentals.manage · N = 2 (admin/alquileres/contrato.php)
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('rentals.manage');

$id = (int) get_param('id');

/* Alquiler */
$rental = db_one('SELECT * FROM rentals WHERE id = :id', ['id' => $id]);

if (!$rental) {
    flash('error', 'El alquiler solicitado no existe.');
    redirect(admin_url('alquileres/index.php'));
}

/* Cliente y productos del alquiler */
$customer = db_one('SELECT * FROM customers WHERE id = :id', ['id' => (int) $rental['customer_id']]) ?: [];
$products = rental_items_details($id);
$product = $products[0] ?? [];

/* Datos del negocio para el partial */
$business = settings_all();

/* Auditoría */
log_activity('rental.contract', 'rental', (int) $rental['id'], 'Impresión de contrato ' . $rental['rental_number']);

/* Descarga PDF (Dompdf) si se solicita ?pdf=1 */
if (get_param('pdf') === '1') {
    ob_start();
    require LCN_ROOT . '/app/views/templates/pdf/contract.php';
    render_pdf(ob_get_clean(), 'Contrato-' . $rental['rental_number']);
}

/* Renderizar la plantilla imprimible en pantalla */
require LCN_ROOT . '/app/views/templates/contract.php';
