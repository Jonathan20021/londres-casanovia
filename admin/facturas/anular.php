<?php
/**
 * Facturas — Anular (POST). Establece status='void'.
 * Una factura ya 'paid' sólo puede anularla un Super Admin / Administrador.
 * LONDRES Casa de Novias
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('invoices.manage');

/* Sólo se procesa por POST con CSRF válido */
if (!is_post()) {
    redirect(admin_url('facturas/index.php'));
}
require_csrf();

$id = (int) post('id');

$invoice = db_one('SELECT id, invoice_number, status FROM invoices WHERE id = :id', ['id' => $id]);

if (!$invoice) {
    flash('error', 'La factura solicitada no existe.');
    redirect(admin_url('facturas/index.php'));
}

/* Ya anulada: nada que hacer */
if ($invoice['status'] === 'void') {
    flash('warning', 'La factura ' . $invoice['invoice_number'] . ' ya estaba anulada.');
    redirect(admin_url('facturas/ver.php?id=' . $id));
}

/* No permitir anular una factura pagada salvo con rol autorizado */
if ($invoice['status'] === 'paid' && !has_role('Super Admin', 'Administrador')) {
    flash('error', 'No tiene permisos para anular una factura ya pagada.');
    redirect(admin_url('facturas/ver.php?id=' . $id));
}

db_update('invoices', ['status' => 'void'], 'id = :id', ['id' => $id]);

log_activity('void', 'invoice', $id, 'Factura anulada: ' . $invoice['invoice_number']);
flash('success', 'Factura ' . $invoice['invoice_number'] . ' anulada correctamente.');
redirect(admin_url('facturas/ver.php?id=' . $id));
