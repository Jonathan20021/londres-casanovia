<?php
/**
 * Plantilla PDF — FACTURA (CSS en línea, compatible con Dompdf).
 * Variables: $invoice, $customer, $payments, $lines, $business
 */
$methods = ['cash'=>'Efectivo','transfer'=>'Transferencia','card'=>'Tarjeta','deposit'=>'Depósito','other'=>'Otro'];
$statusLabel = ['pending'=>'Pendiente','partial'=>'Parcialmente pagada','paid'=>'Pagada','void'=>'Anulada'][$invoice['status']] ?? $invoice['status'];
$statusColor = ['pending'=>'#9AA0AA','partial'=>'#C8102E','paid'=>'#1F8A5B','void'=>'#9AA0AA'][$invoice['status']] ?? '#9AA0AA';
$primary = $business['primary_color'] ?? '#C8102E';
$logo = pdf_logo_uri();
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Factura <?= e($invoice['invoice_number']) ?></title>
<style>
  @page { margin: 0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DejaVu Sans',sans-serif; color:#23232a; font-size:11px; line-height:1.4; }
  .serif { font-family:'DejaVu Serif',serif; }
  .band { background:#0B0B0C; padding:16px 32px; }
  .band .brand { font-family:'DejaVu Serif',serif; font-size:20px; letter-spacing:5px; color:#fff; line-height:1; }
  .band .sub { font-family:'DejaVu Serif',serif; font-style:italic; color:#C9A86A; font-size:11px; margin-top:2px; }
  .band h1 { font-family:'DejaVu Serif',serif; font-size:21px; letter-spacing:3px; color:#fff; }
  .band .meta { color:#C9A86A; font-size:10px; margin-top:4px; }
  .goldrule { height:3px; background:#C9A86A; }
  .body { padding:16px 32px 22px; }
  .biz { font-size:10px; color:#777; }
  .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:10px; color:#fff; }
  .label { font-size:9px; text-transform:uppercase; letter-spacing:1.5px; color:#A98E63; font-weight:bold; }
  .parties { width:100%; border-collapse:separate; border-spacing:0; margin-top:12px; }
  .parties td { width:50%; vertical-align:top; padding:9px 12px; background:#FAF7F1; border:1px solid #EEE7DA; }
  .parties td.first { border-right:none; }
  .name { font-weight:bold; font-size:12px; margin-top:2px; color:#1a1a1d; }
  table.items { width:100%; border-collapse:collapse; margin-top:18px; }
  table.items th { background:#0B0B0C; color:#fff; font-size:9px; text-transform:uppercase; letter-spacing:1.2px; text-align:left; padding:6px 10px; }
  table.items th.r, table.items td.r { text-align:right; }
  /* Filas compactas: caben más de 10 artículos en una sola hoja */
  table.items td { padding:5px 10px; border-bottom:1px solid #eee; font-size:11px; }
  table.items td.num { color:#aaa; font-size:10px; width:20px; }
  table.items tr:nth-child(even) td { background:#FBFAF8; }
  .parties .row { margin-top:2px; }
  .parties .k { color:#7a7a7a; font-weight:bold; }
  .totwrap { width:100%; margin-top:10px; }
  .totals { width:46%; float:right; border-collapse:collapse; }
  .totals td { padding:4px 12px; font-size:11px; }
  .totals td.r { text-align:right; }
  .totals tr.sep td { border-top:1px solid #e8e8e8; }
  .totals tr.grand td { background:<?= e($primary) ?>; color:#fff; font-weight:bold; font-size:14px; }
  .totals tr.bal td { font-weight:bold; color:<?= e($primary) ?>; }
  .clear { clear:both; }
  .pays { width:100%; border-collapse:collapse; margin-top:16px; }
  .pays caption { text-align:left; }
  .pays th { text-align:left; font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#999; border-bottom:1.5px solid #e6e6e6; padding:4px 8px; }
  .pays td { padding:4px 8px; border-bottom:1px solid #f2f2f2; font-size:10px; }
  .foot { margin-top:18px; border-top:2px solid #C9A86A; padding-top:10px; }
  .thanks { font-family:'DejaVu Serif',serif; font-style:italic; color:<?= e($primary) ?>; font-size:13px; }
  .policy { font-size:9px; color:#888; margin-top:4px; }
</style></head>
<body>
  <div class="band">
    <table style="width:100%"><tr>
      <td style="width:62%; vertical-align:middle">
        <?php if ($logo): ?>
          <img src="<?= $logo ?>" style="width:172px" alt="LONDRES Casa de Novias">
        <?php else: ?>
          <div class="brand">LONDRES</div><div class="sub">Casa de Novias</div>
        <?php endif; ?>
      </td>
      <td style="text-align:right; vertical-align:middle">
        <h1>FACTURA</h1>
        <div class="meta">N.º <?= e($invoice['invoice_number']) ?> &nbsp;·&nbsp; <?= e(format_date($invoice['issued_at'] ?: $invoice['created_at'])) ?></div>
      </td>
    </tr></table>
  </div>
  <div class="goldrule"></div>

  <div class="body">
    <table style="width:100%"><tr>
      <td class="biz" style="vertical-align:top">
        <strong style="color:#1a1a1d; font-size:11px"><?= e($business['business_name'] ?? 'LONDRES Casa de Novias') ?></strong><br>
        <?php if (!empty($business['address'])): ?><?= e($business['address']) ?><br><?php endif; ?>
        <?php if (!empty($business['phone'])): ?>Tel: <?= e($business['phone']) ?><?php endif; ?><?php if (!empty($business['email'])): ?> · <?= e($business['email']) ?><?php endif; ?>
      </td>
      <td style="text-align:right; vertical-align:top">
        <span class="badge" style="background:<?= e($statusColor) ?>"><?= e($statusLabel) ?></span><br>
        <span style="font-size:10px; color:#999; margin-top:4px; display:inline-block">Tipo: <?= $invoice['invoice_type'] === 'sale' ? 'Venta' : 'Alquiler' ?></span>
      </td>
    </tr></table>

    <table class="parties"><tr>
      <td class="first">
        <div class="label">Facturar a</div>
        <div class="row"><span class="k">Cliente:</span> <span class="name" style="display:inline; margin:0"><?= e($customer['full_name'] ?? $invoice['customer_name'] ?? '—') ?></span></div>
        <div class="row"><span class="k">Cédula:</span> <?= e(!empty($customer['document_number']) ? $customer['document_number'] : '—') ?></div>
        <div class="row"><span class="k">Teléfono:</span> <?= e(!empty($customer['phone']) ? $customer['phone'] : '—') ?></div>
        <div class="row"><span class="k">Dirección:</span> <?= e(!empty($customer['address']) ? $customer['address'] : '—') ?></div>
        <?php if (!empty($customer['email'])): ?><div class="row"><span class="k">Correo:</span> <?= e($customer['email']) ?></div><?php endif; ?>
      </td>
      <td>
        <div class="label">Referencia</div>
        <div class="name"><?= e($invoice['rental_number'] ?: ($invoice['sale_number'] ?: '—')) ?></div>
        <?php if (!empty($invoice['event_date'])): ?>Evento: <?= e(format_date($invoice['event_date'])) ?><br><?php endif; ?>
        <?php if (!empty($invoice['delivery_date'])): ?>Entrega: <?= e(format_date_time($invoice['delivery_date'], $invoice['delivery_time'] ?? null)) ?><br><?php endif; ?>
        <?php if (!empty($invoice['return_date'])): ?>Devolución: <?= e(format_date($invoice['return_date'])) ?><?php endif; ?>
      </td>
    </tr></table>

    <table class="items">
      <thead><tr><th style="width:20px">#</th><th>Concepto</th><th>Detalle</th><th class="r">Importe</th></tr></thead>
      <tbody>
        <?php foreach ($lines as $i => $ln): ?>
        <tr>
          <td class="num"><?= (int) $i + 1 ?></td>
          <td><?= e($ln['concept']) ?></td>
          <td style="color:#777"><?= e($ln['detail']) ?></td>
          <td class="r"><?= e(money($ln['amount'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totwrap">
      <table class="totals">
        <tr><td>Subtotal</td><td class="r"><?= e(money($invoice['subtotal'])) ?></td></tr>
        <?php if ((float)$invoice['discount'] > 0): ?><tr><td>Descuento</td><td class="r">− <?= e(money($invoice['discount'])) ?></td></tr><?php endif; ?>
        <?php if ((float)$invoice['tax'] > 0): ?><tr><td>Impuestos</td><td class="r"><?= e(money($invoice['tax'])) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Total</td><td class="r"><?= e(money($invoice['total'])) ?></td></tr>
        <tr class="sep"><td>Pagado</td><td class="r"><?= e(money($invoice['paid_amount'])) ?></td></tr>
        <tr class="bal"><td>Saldo pendiente</td><td class="r"><?= e(money($invoice['balance'])) ?></td></tr>
      </table>
      <div class="clear"></div>
    </div>

    <?php if (!empty($payments)): ?>
    <table class="pays">
      <thead><tr><th>Recibo</th><th>Fecha</th><th>Método</th><th>Referencia</th><th style="text-align:right">Monto</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= e($p['payment_number']) ?></td>
          <td><?= e(format_date($p['paid_at'] ?: $p['created_at'])) ?></td>
          <td><?= e($methods[$p['payment_method']] ?? $p['payment_method']) ?></td>
          <td><?= e($p['reference'] ?: '—') ?></td>
          <td style="text-align:right"><?= e(money($p['amount'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <div class="foot">
      <p class="thanks">Gracias por confiar en LONDRES Casa de Novias.</p>
      <?php if (!empty($business['rental_policy'])): ?><p class="policy"><?= e($business['rental_policy']) ?></p><?php endif; ?>
    </div>
  </div>
</body></html>
