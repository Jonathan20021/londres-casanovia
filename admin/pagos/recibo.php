<?php
/**
 * Endpoint: Recibo de pago — LONDRES Casa de Novias
 *
 * Carga el pago (?id), su cliente, el documento asociado
 * (alquiler/venta/factura) y calcula el saldo restante, y renderiza la
 * plantilla imprimible app/views/templates/receipt.php para window.print().
 *
 * Permiso: payments.manage · N = 2 (admin/pagos/recibo.php)
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('payments.manage');

$id = (int) get_param('id');

/* Pago + cliente + referencias de la operación */
$payment = db_one(
    "SELECT p.*,
            c.full_name AS customer_name,
            u.name AS received_by_name,
            r.rental_number, r.total_amount AS rental_total,
            s.sale_number,  s.total_amount AS sale_total,
            i.invoice_number, i.total AS invoice_total, i.balance AS invoice_balance,
            COALESCE(pr.name, ps.name) AS product_name
     FROM payments p
     LEFT JOIN customers c ON c.id = p.customer_id
     LEFT JOIN users     u ON u.id = p.received_by
     LEFT JOIN rentals   r ON r.id = p.rental_id
     LEFT JOIN sales     s ON s.id = p.sale_id
     LEFT JOIN invoices  i ON i.id = p.invoice_id
     LEFT JOIN products pr ON pr.id = r.product_id
     LEFT JOIN products ps ON ps.id = s.product_id
     WHERE p.id = :id",
    ['id' => $id]
);

if (!$payment) {
    flash('error', 'El recibo solicitado no existe.');
    redirect(admin_url('pagos/index.php'));
}

/* Cliente (objeto separado para el partial) */
$customer = !empty($payment['customer_id'])
    ? (db_one('SELECT * FROM customers WHERE id = :id', ['id' => (int) $payment['customer_id']]) ?: [])
    : [];

/* Saldo restante del documento asociado (suma actual de pagos vs. total) */
$remaining = null;
if (!empty($payment['rental_id'])) {
    $remaining = round((float) $payment['rental_total'] - rental_paid_amount((int) $payment['rental_id']), 2);
} elseif (!empty($payment['sale_id'])) {
    $remaining = round((float) $payment['sale_total'] - sale_paid_amount((int) $payment['sale_id']), 2);
} elseif (!empty($payment['invoice_id'])) {
    $remaining = round((float) $payment['invoice_balance'], 2);
}

/* Datos del negocio para el partial */
$business = settings_all();

/* Auditoría */
log_activity('payment.print', 'payment', (int) $payment['id'], 'Impresión de recibo ' . $payment['payment_number']);

/* Descarga PDF (Dompdf) si se solicita ?pdf=1 */
if (get_param('pdf') === '1') {
    ob_start();
    require LCN_ROOT . '/app/views/templates/pdf/receipt.php';
    render_pdf(ob_get_clean(), 'Recibo-' . $payment['payment_number']);
}

/* Renderizar la plantilla imprimible en pantalla */
require LCN_ROOT . '/app/views/templates/receipt.php';
