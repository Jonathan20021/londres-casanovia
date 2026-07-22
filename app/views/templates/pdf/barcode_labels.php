<?php
/**
 * Plantilla PDF — ETIQUETAS DE CÓDIGO DE BARRAS (CSS en línea, Dompdf).
 * LONDRES Casa de Novias
 *
 * Variables esperadas:
 *   $labels     array de ['name','code','sku','size','color','category','price']
 *   $layout     ['key','label','cols','rows','w','h','name_max','bar_h','font']
 *   $business   settings_all()
 *   $showHeader bool  — banda de marca en la primera página
 *   $docTitle   string
 */
$primary = $business['primary_color'] ?? '#C8102E';
$gold    = '#C9A86A';
$logo    = pdf_logo_uri();
$brand   = $business['business_name'] ?? 'LONDRES Casa de Novias';

/*
 * La banda de marca ocupa la altura de una fila, así que la primera página
 * lleva una fila menos: cada hoja queda exacta y nada se desborda.
 */
$cols     = max(1, (int) $layout['cols']);
$perPage  = max(1, $cols * (int) $layout['rows']);
$perFirst = !empty($showHeader) ? max($cols, $perPage - $cols) : $perPage;

$pages = [array_slice($labels, 0, $perFirst)];
$rest  = array_slice($labels, $perFirst);
if ($rest) {
    $pages = array_merge($pages, array_chunk($rest, $perPage));
}

/** Recorta respetando UTF-8 */
$cut = static function (?string $s, int $max): string {
    $s = trim((string) $s);
    if ($s === '') return '';
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
};
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title><?= e($docTitle ?? 'Códigos de barra') ?></title>
<style>
  @page { margin: 8mm 6mm 12mm 6mm; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DejaVu Sans',sans-serif; color:#1a1a1d; font-size:8pt; }
  .serif { font-family:'DejaVu Serif',serif; }

  /* Banda de marca (primera página) */
  .band { background:#0B0B0C; padding:10px 14px; margin-bottom:8px; }
  .band .brand { font-family:'DejaVu Serif',serif; font-size:15pt; letter-spacing:4px; color:#fff; line-height:1; }
  .band .sub   { font-family:'DejaVu Serif',serif; font-style:italic; color:<?= e($gold) ?>; font-size:7.5pt; margin-top:2px; }
  .band .meta  { color:<?= e($gold) ?>; font-size:7pt; text-align:right; }
  .band .meta strong { color:#fff; font-size:8.5pt; }
  .goldrule { height:3px; background:<?= e($gold) ?>; margin-bottom:10px; }

  /* Rejilla de etiquetas */
  /* Ancho fijo y centrado: así la hoja queda dentro del área imprimible de
     cualquier impresora, sin depender de cómo Dompdf aplique los márgenes. */
  table.sheet { width:<?= (float) $layout['w'] * (int) $layout['cols'] ?>mm; margin:0 auto; border-collapse:collapse; table-layout:fixed; }
  table.sheet td {
      width:<?= round(100 / (int) $layout['cols'], 4) ?>%;
      height:<?= (float) $layout['h'] ?>mm;
      vertical-align:top;
      padding:1.2mm;
  }
  .lbl {
      height:<?= max(8, (float) $layout['h'] - 2.4) ?>mm;
      border:0.6pt dashed #cfcfcf;
      border-radius:2mm;
      padding:1.8mm 2mm 1.4mm;
      text-align:center;
      background:#fff;
  }
  .lbl .top { font-family:'DejaVu Serif',serif; font-size:<?= (float) $layout['font'] - 1.4 ?>pt; letter-spacing:2px; color:#0B0B0C; line-height:1; }
  .lbl .rule { height:1pt; background:<?= e($gold) ?>; width:34%; margin:1mm auto 1.2mm; }
  .lbl .nm { font-size:<?= (float) $layout['font'] ?>pt; font-weight:bold; line-height:1.15; color:#1a1a1d; }
  .lbl .at { font-size:<?= (float) $layout['font'] - 1.2 ?>pt; color:#6b6b70; margin-top:0.6mm; }
  .lbl .pr { font-size:<?= (float) $layout['font'] + 0.6 ?>pt; font-weight:bold; color:<?= e($primary) ?>; margin-top:0.6mm; }
  .lbl .bc { margin-top:1.2mm; }
  .lbl .code { font-family:'DejaVu Sans Mono',monospace; font-size:<?= (float) $layout['font'] - 0.6 ?>pt; letter-spacing:1.4px; margin-top:0.4mm; color:#0B0B0C; }

  .foot { position:fixed; bottom:4mm; left:6mm; right:6mm; font-size:6.5pt; color:#9a9aa0; border-top:0.6pt solid #e6e6e6; padding-top:2mm; }
  .foot .r { float:right; }
</style></head>
<body>

<div class="foot">
    <span class="r"><?= e($layout['label']) ?> · <?= count($labels) ?> etiqueta<?= count($labels) === 1 ? '' : 's' ?></span>
    <?= e($brand) ?> · Códigos de barra Code 128 · Generado <?= e(date('d/m/Y h:i A')) ?>
</div>

<?php foreach ($pages as $pi => $chunk): ?>
    <?php if ($pi > 0): ?><div style="page-break-before:always;"></div><?php endif; ?>

    <?php if (!empty($showHeader) && $pi === 0): ?>
        <div class="band">
            <table style="width:100%"><tr>
                <td style="width:64%; vertical-align:middle">
                    <table><tr>
                        <?php if ($logo): ?><td style="width:34px; vertical-align:middle"><img src="<?= $logo ?>" style="width:28px"></td><?php endif; ?>
                        <td style="vertical-align:middle">
                            <div class="brand">LONDRES</div>
                            <div class="sub">Casa de Novias</div>
                        </td>
                    </tr></table>
                </td>
                <td style="vertical-align:middle" class="meta">
                    <strong>ETIQUETAS DE INVENTARIO</strong><br>
                    <?= e($layout['label']) ?> · <?= count($labels) ?> etiqueta<?= count($labels) === 1 ? '' : 's' ?><br>
                    <?= e(date('d/m/Y')) ?>
                </td>
            </tr></table>
        </div>
        <div class="goldrule"></div>
    <?php endif; ?>

    <table class="sheet">
        <?php foreach (array_chunk($chunk, (int) $layout['cols']) as $row): ?>
            <tr>
                <?php foreach ($row as $l): ?>
                    <td>
                        <div class="lbl">
                            <div class="top">LONDRES</div>
                            <div class="rule"></div>
                            <div class="nm"><?= e($cut($l['name'] ?? '', (int) $layout['name_max'])) ?></div>
                            <?php
                            $attrs = array_filter([
                                $l['size']  ? 'Talla ' . $l['size'] : '',
                                $l['color'] ?: '',
                                $l['sku']   ? 'SKU ' . $l['sku'] : '',
                            ]);
                            ?>
                            <?php if ($attrs): ?>
                                <div class="at"><?= e($cut(implode(' · ', $attrs), (int) $layout['name_max'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($l['price'])): ?>
                                <div class="pr"><?= e($l['price']) ?></div>
                            <?php endif; ?>
                            <div class="bc"><?= barcode_pdf_mark((string) $l['code'], ['pdf_height' => (float) $layout['bar_h'], 'css_width' => '96%']) ?></div>
                            <div class="code"><?= e($l['code']) ?></div>
                        </div>
                    </td>
                <?php endforeach; ?>
                <?php for ($i = count($row); $i < (int) $layout['cols']; $i++): ?>
                    <td></td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endforeach; ?>

</body></html>
