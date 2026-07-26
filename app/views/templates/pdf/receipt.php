<?php
/**
 * Plantilla PDF — RECIBO DE PAGO (CSS en línea, Dompdf).
 * Variables: $payment, $customer, $remaining, $business
 */
$methods = ['cash'=>'Efectivo','transfer'=>'Transferencia','card'=>'Tarjeta','deposit'=>'Depósito','other'=>'Otro'];
$primary = $business['primary_color'] ?? '#C8102E';
$logo = pdf_logo_uri();
$refDoc = $payment['rental_number'] ?: ($payment['sale_number'] ?: ($payment['invoice_number'] ?: '—'));
$tipo = $payment['rental_number'] ? 'alquiler' : ($payment['sale_number'] ? 'venta' : 'factura');
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Recibo <?= e($payment['payment_number']) ?></title>
<style>
  @page { margin: 0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DejaVu Sans',sans-serif; color:#23232a; font-size:12px; line-height:1.55; }
  .band { background:#0B0B0C; padding:24px 40px; }
  .band .brand { font-family:'DejaVu Serif',serif; font-size:23px; letter-spacing:5px; color:#fff; line-height:1; }
  .band .sub { font-family:'DejaVu Serif',serif; font-style:italic; color:#C9A86A; font-size:12px; margin-top:3px; }
  .band h1 { font-family:'DejaVu Serif',serif; font-size:22px; letter-spacing:3px; color:#fff; }
  .band .meta { color:#C9A86A; font-size:10.5px; margin-top:6px; }
  .goldrule { height:4px; background:#C9A86A; }
  .body { padding:30px 44px 44px; }
  .amount-box { margin-top:8px; text-align:center; background:#FAF7F1; border:1px solid #EEE7DA; border-radius:14px; padding:26px; }
  .amount-box .label { font-size:10px; text-transform:uppercase; letter-spacing:2px; color:#A98E63; }
  .amount-box .value { font-family:'DejaVu Serif',serif; font-size:38px; color:<?= e($primary) ?>; margin-top:8px; }
  table.det { width:100%; border-collapse:collapse; margin-top:26px; }
  table.det td { padding:10px 12px; border-bottom:1px solid #eee; font-size:12px; }
  table.det td.k { color:#A98E63; width:38%; font-size:9.5px; text-transform:uppercase; letter-spacing:1px; font-weight:bold; }
  .thanks { font-family:'DejaVu Serif',serif; font-style:italic; color:<?= e($primary) ?>; margin-top:24px; font-size:14px; text-align:center; }
  .sign { width:100%; margin-top:54px; border-collapse:collapse; }
  .sign td { width:50%; padding:0 28px; }
  .sign .line { border-top:1px solid #555; padding-top:6px; text-align:center; font-size:10px; color:#666; }
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
        <h1>RECIBO</h1>
        <div class="meta">N.º <?= e($payment['payment_number']) ?> &nbsp;·&nbsp; <?= e(format_datetime($payment['paid_at'] ?: $payment['created_at'])) ?></div>
      </td>
    </tr></table>
  </div>
  <div class="goldrule"></div>

  <div class="body">
    <div class="amount-box">
      <div class="label">Recibimos de <?= e($customer['full_name'] ?? $payment['customer_name'] ?? 'el cliente') ?> la suma de</div>
      <div class="value"><?= e(money($payment['amount'])) ?></div>
    </div>

    <table class="det">
      <tr><td class="k">Concepto</td><td>Pago de <?= e($tipo) ?> <?= e($refDoc) ?><?= !empty($payment['product_name']) ? ' — ' . e($payment['product_name']) : '' ?></td></tr>
      <tr><td class="k">Método de pago</td><td><?= e($methods[$payment['payment_method']] ?? $payment['payment_method']) ?></td></tr>
      <?php if (!empty($payment['reference'])): ?><tr><td class="k">Referencia</td><td><?= e($payment['reference']) ?></td></tr><?php endif; ?>
      <?php if ($remaining !== null): ?><tr><td class="k">Saldo pendiente</td><td style="font-weight:bold; color:<?= $remaining > 0.009 ? e($primary) : '#1F8A5B' ?>"><?= e(money(max(0, $remaining))) ?></td></tr><?php endif; ?>
      <?php if (!empty($payment['received_by_name'])): ?><tr><td class="k">Recibido por</td><td><?= e($payment['received_by_name']) ?></td></tr><?php endif; ?>
      <?php if (!empty($payment['notes'])): ?><tr><td class="k">Nota</td><td><?= e($payment['notes']) ?></td></tr><?php endif; ?>
    </table>

    <p class="thanks">¡Gracias por su pago!</p>

    <table class="sign"><tr>
      <td><div class="line">Firma del cliente</div></td>
      <td><div class="line">Por <?= e($business['business_name'] ?? 'LONDRES Casa de Novias') ?></div></td>
    </tr></table>
  </div>
</body></html>
