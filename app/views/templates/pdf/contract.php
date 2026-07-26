<?php
/**
 * Plantilla PDF — CONTRATO DE ALQUILER (CSS en línea, Dompdf).
 * Variables: $rental, $customer, $product, $business
 */
$primary = $business['primary_color'] ?? '#C8102E';
$logo = pdf_logo_uri();
$conditionLabels = ['new'=>'Nuevo','excellent'=>'Excelente','good'=>'Bueno','repair'=>'En reparación','out_of_service'=>'Fuera de servicio'];
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Contrato <?= e($rental['rental_number']) ?></title>
<style>
  @page { margin: 0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DejaVu Sans',sans-serif; color:#23232a; font-size:11.5px; line-height:1.55; }
  .band { background:#0B0B0C; padding:22px 40px; }
  .band .brand { font-family:'DejaVu Serif',serif; font-size:22px; letter-spacing:5px; color:#fff; line-height:1; }
  .band .sub { font-family:'DejaVu Serif',serif; font-style:italic; color:#C9A86A; font-size:11px; margin-top:3px; }
  .band h1 { font-family:'DejaVu Serif',serif; font-size:20px; letter-spacing:2px; color:#fff; }
  .band .meta { color:#C9A86A; font-size:10px; margin-top:5px; }
  .goldrule { height:4px; background:#C9A86A; }
  .body { padding:24px 40px 40px; }
  h2 { font-family:'DejaVu Serif',serif; font-size:13px; color:#0B0B0C; margin:18px 0 7px; padding-bottom:5px; border-bottom:1px solid #EEE7DA; }
  h2 .n { color:<?= e($primary) ?>; }
  .grid2 { width:100%; border-collapse:separate; border-spacing:0; }
  .grid2 td { width:50%; vertical-align:top; padding:12px 14px; background:#FAF7F1; border:1px solid #EEE7DA; }
  .grid2 td.first { border-right:none; }
  .label { font-size:9px; text-transform:uppercase; letter-spacing:1.5px; color:#A98E63; font-weight:bold; }
  .name { font-weight:bold; font-size:12px; margin-top:2px; }
  table.kv { width:100%; border-collapse:collapse; }
  table.kv td { padding:6px 12px; font-size:11px; vertical-align:top; border-bottom:1px solid #f3f0ea; }
  table.kv td.k { color:#888; width:24%; }
  .totbox { width:100%; border-collapse:collapse; margin-top:6px; }
  .totbox td { padding:8px 14px; border-bottom:1px solid #eee; }
  .totbox td.r { text-align:right; font-weight:bold; }
  .totbox tr.grand td { background:<?= e($primary) ?>; color:#fff; }
  .policy { font-size:10px; color:#666; margin-top:7px; text-align:justify; }
  .sign { width:100%; margin-top:52px; border-collapse:collapse; }
  .sign td { width:50%; padding:0 26px; }
  .sign .line { border-top:1px solid #555; padding-top:6px; text-align:center; font-size:10px; color:#666; }
</style></head>
<body>
  <div class="band">
    <table style="width:100%"><tr>
      <td style="width:62%; vertical-align:middle">
        <table><tr>
          <?php if ($logo): ?><td style="width:50px; vertical-align:middle"><img src="<?= $logo ?>" style="width:42px"></td><?php endif; ?>
          <td style="vertical-align:middle"><div class="brand">LONDRES</div><div class="sub">Casa de Novias</div></td>
        </tr></table>
      </td>
      <td style="text-align:right; vertical-align:middle">
        <h1>CONTRATO DE ALQUILER</h1>
        <div class="meta">N.º <?= e($rental['rental_number']) ?> &nbsp;·&nbsp; <?= e(format_date($rental['created_at'] ?? null)) ?></div>
      </td>
    </tr></table>
  </div>
  <div class="goldrule"></div>

  <div class="body">
    <table class="grid2"><tr>
      <td class="first">
        <div class="label">Arrendatario (Cliente)</div>
        <div class="name"><?= e($customer['full_name'] ?? '') ?></div>
        <?php if (!empty($customer['document_number'])): ?>Doc: <?= e($customer['document_number']) ?><br><?php endif; ?>
        <?php if (!empty($customer['phone'])): ?>Tel: <?= e($customer['phone']) ?><br><?php endif; ?>
        <?php if (!empty($customer['email'])): ?><?= e($customer['email']) ?><br><?php endif; ?>
        <?php if (!empty($customer['address'])): ?><?= e($customer['address']) ?><?php endif; ?>
      </td>
      <td>
        <div class="label">Arrendador</div>
        <div class="name"><?= e($business['business_name'] ?? 'LONDRES Casa de Novias') ?></div>
        <?php if (!empty($business['address'])): ?><?= e($business['address']) ?><br><?php endif; ?>
        <?php if (!empty($business['phone'])): ?>Tel: <?= e($business['phone']) ?><?php endif; ?>
      </td>
    </tr></table>

    <h2><span class="n">01.</span> <?= count($products ?? []) === 1 ? 'Pieza alquilada' : 'Piezas alquiladas' ?></h2>
    <table class="kv">
      <?php foreach (($products ?? [$product]) as $position => $contractProduct): ?>
        <?php $conditionLabel = $conditionLabels[$contractProduct['condition_status'] ?? ''] ?? '—'; ?>
        <tr>
          <td class="k">Pieza <?= (int) $position + 1 ?></td>
          <td><strong><?= e($contractProduct['name'] ?? '') ?></strong>
            <?= !empty($contractProduct['barcode']) ? '(' . e($contractProduct['barcode']) . ')' : (!empty($contractProduct['sku']) ? '(' . e($contractProduct['sku']) . ')' : '') ?>
            — <?= e($contractProduct['category_name'] ?? 'General') ?>
            — Talla/Color: <?= e($contractProduct['size'] ?? '—') ?> · <?= e($contractProduct['color'] ?? '—') ?>
            — <?= e($conditionLabel) ?>
            <?php if (!empty($contractProduct['needs_alteration'])): ?>
              <br><span style="color:#A9761A; font-weight:bold">Por modificar<?= !empty($contractProduct['alteration_notes']) ? ': ' . e($contractProduct['alteration_notes']) : '' ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!empty($rental['delivery_condition'])): ?><tr><td class="k">Estado al entregar</td><td><?= e($rental['delivery_condition']) ?></td></tr><?php endif; ?>
    </table>

    <h2><span class="n">02.</span> Fechas</h2>
    <table class="kv">
      <tr><td class="k">Evento</td><td><?= e(format_date($rental['event_date'])) ?></td></tr>
      <tr><td class="k">Entrega</td><td><?= e(format_date_time($rental['delivery_date'], $rental['delivery_time'] ?? null)) ?></td></tr>
      <tr><td class="k">Devolución</td><td><?= e(format_date($rental['return_date'])) ?></td></tr>
    </table>

    <h2><span class="n">03.</span> Condiciones económicas</h2>
    <table class="totbox">
      <tr><td>Precio de alquiler</td><td class="r"><?= e(money($rental['rental_price'])) ?></td></tr>
      <?php if ((float)$rental['discount'] > 0): ?><tr><td>Descuento</td><td class="r">− <?= e(money($rental['discount'])) ?></td></tr><?php endif; ?>
      <tr class="grand"><td>Total</td><td class="r"><?= e(money($rental['total_amount'])) ?></td></tr>
      <tr><td>Pago inicial (50%)</td><td class="r"><?= e(money($rental['initial_payment_paid'])) ?></td></tr>
      <tr><td>Saldo pendiente</td><td class="r"><?= e(money($rental['remaining_balance'])) ?></td></tr>
      <tr><td>Penalidad por retraso</td><td class="r"><?= (float)$rental['late_penalty'] > 0 ? e(money($rental['late_penalty'])) : 'Según política' ?></td></tr>
    </table>

    <h2><span class="n">04.</span> Términos y condiciones</h2>
    <?php if (!empty($business['rental_policy'])): ?><p class="policy"><strong>Alquiler:</strong> <?= e($business['rental_policy']) ?></p><?php endif; ?>
    <?php if (!empty($business['return_policy'])): ?><p class="policy"><strong>Devolución:</strong> <?= e($business['return_policy']) ?></p><?php endif; ?>
    <p class="policy">El saldo pendiente debe liquidarse al momento de retirar la pieza. La pieza se entrega en el estado indicado y debe devolverse en las mismas condiciones en la fecha pactada. El cliente acepta estos términos al firmar el presente contrato.</p>

    <table class="sign"><tr>
      <td><div class="line">Firma del cliente<br><?= e($customer['full_name'] ?? '') ?></div></td>
      <td><div class="line">Por <?= e($business['business_name'] ?? 'LONDRES Casa de Novias') ?></div></td>
    </tr></table>
  </div>
</body></html>
