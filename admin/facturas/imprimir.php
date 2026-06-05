<?php
/**
 * Endpoint: Imprimir factura — LONDRES Casa de Novias
 *
 * Carga la factura (?id), su cliente, los pagos asociados y la operación
 * origen (alquiler/venta + producto) y renderiza la plantilla imprimible
 * app/views/templates/invoice.php para window.print().
 *
 * Permiso: invoices.manage · N = 2 (admin/facturas/imprimir.php)
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('invoices.manage');

$id = (int) get_param('id');

/* Factura + cliente + datos de la operación origen */
$invoice = db_one(
    "SELECT i.*,
            c.full_name AS customer_name, c.phone AS customer_phone, c.whatsapp AS customer_whatsapp,
            c.email AS customer_email, c.address AS customer_address, c.document_number AS customer_document,
            r.rental_number, r.delivery_date, r.return_date, r.event_date,
            s.sale_number,
            pr.name AS rental_product, ps.name AS sale_product,
            usr.name AS created_by_name
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     LEFT JOIN rentals  r   ON r.id  = i.rental_id
     LEFT JOIN sales    s   ON s.id  = i.sale_id
     LEFT JOIN products pr  ON pr.id = r.product_id
     LEFT JOIN products ps  ON ps.id = s.product_id
     LEFT JOIN users    usr ON usr.id = i.created_by
     WHERE i.id = :id",
    ['id' => $id]
);

if (!$invoice) {
    flash('error', 'La factura solicitada no existe.');
    redirect(admin_url('facturas/index.php'));
}

/* Cliente (objeto separado para el partial) */
$customer = db_one('SELECT * FROM customers WHERE id = :id', ['id' => (int) $invoice['customer_id']]) ?: [];

/* Pagos asociados (por invoice_id o por la operación origen) */
$paymentWhere  = ['p.invoice_id = :iid'];
$paymentParams = ['iid' => $id];
if (!empty($invoice['rental_id'])) {
    $paymentWhere[]       = 'p.rental_id = :rid';
    $paymentParams['rid'] = (int) $invoice['rental_id'];
}
if (!empty($invoice['sale_id'])) {
    $paymentWhere[]       = 'p.sale_id = :sid';
    $paymentParams['sid'] = (int) $invoice['sale_id'];
}
$payments = db_all(
    'SELECT p.*, u.name AS received_by_name
     FROM payments p
     LEFT JOIN users u ON u.id = p.received_by
     WHERE ' . implode(' OR ', $paymentWhere) . '
     ORDER BY COALESCE(p.paid_at, p.created_at) ASC',
    $paymentParams
);

/* Líneas de concepto */
$conceptText = $invoice['concept']
    ?: ($invoice['rental_product'] ?: ($invoice['sale_product'] ?: 'Servicio'));
$lines = [[
    'concept' => (string) $conceptText,
    'detail'  => (string) ($invoice['rental_number'] ?: ($invoice['sale_number'] ?: '')),
    'amount'  => (float) $invoice['subtotal'],
]];

/* Datos del negocio para el partial */
$business = settings_all();

/* Auditoría */
log_activity('invoice.print', 'invoice', (int) $invoice['id'], 'Impresión de factura ' . $invoice['invoice_number']);

/* Descarga PDF (Dompdf) si se solicita ?pdf=1 */
if (get_param('pdf') === '1') {
    ob_start();
    require LCN_ROOT . '/app/views/templates/pdf/invoice.php';
    render_pdf(ob_get_clean(), 'Factura-' . $invoice['invoice_number']);
}

/* Renderizar la plantilla imprimible en pantalla */
require LCN_ROOT . '/app/views/templates/invoice.php';
