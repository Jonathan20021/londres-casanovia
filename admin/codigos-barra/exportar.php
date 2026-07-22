<?php
/**
 * Códigos de barra — Exportación a PDF (individual y por lote).
 * LONDRES Casa de Novias
 *
 * admin/codigos-barra/exportar.php  (N=2)  ·  Permiso: products.view
 *
 * Parámetros (GET o POST):
 *   ids       int[] | CSV de IDs de producto
 *   all       1     → exporta todo lo que coincida con los filtros
 *   q, category_id, commercial_status, type   → filtros (sólo con all=1)
 *   tam       grande | mediana | pequena
 *   copias    1..50  (etiquetas por producto)
 *   encabezado 1|0   (banda de marca en la primera página)
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('products.view');

if (is_post()) {
    require_csrf();
}

$param = static function (string $key, string $default = '') {
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_array($v) ? $v : trim((string) $v);
};

/* ---------------- Selección de productos ---------------- */
$all = (string) $param('all') === '1';

$ids = [];
$rawIds = $_POST['ids'] ?? $_GET['ids'] ?? [];
if (is_string($rawIds)) $rawIds = explode(',', $rawIds);
foreach ((array) $rawIds as $v) {
    $v = (int) $v;
    if ($v > 0) $ids[$v] = $v;
}

$where  = [];
$params = [];

if ($all) {
    $q          = (string) $param('q');
    $categoryId = (int) $param('category_id', '0');
    $type       = (string) $param('type');
    $comStatus  = (string) $param('commercial_status');

    if ($q !== '') {
        $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q OR p.color LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    if ($categoryId > 0)   { $where[] = 'p.category_id = :cat';      $params['cat']  = $categoryId; }
    if (in_array($type, ['rental', 'sale', 'both'], true)) { $where[] = 'p.type = :type'; $params['type'] = $type; }
    if (in_array($comStatus, ['available', 'reserved', 'rented', 'sold', 'unavailable', 'maintenance'], true)) {
        $where[] = 'p.commercial_status = :com';
        $params['com'] = $comStatus;
    }
} else {
    if (!$ids) {
        flash('error', 'Seleccione al menos un producto para exportar.');
        redirect(admin_url('codigos-barra/index.php'));
    }
    $in = [];
    foreach (array_values($ids) as $i => $v) {
        $in[] = ':id' . $i;
        $params['id' . $i] = $v;
    }
    $where[] = 'p.id IN (' . implode(',', $in) . ')';
}

$products = db_all(
    'SELECT p.id, p.name, p.sku, p.barcode, p.size, p.color, p.rental_price, p.sale_price, p.type,
            c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
     ORDER BY p.name ASC',
    $params
);

if (!$products) {
    flash('error', 'No se encontraron productos para exportar.');
    redirect(admin_url('codigos-barra/index.php'));
}

/* ---------------- Opciones de la hoja ---------------- */
$layout     = barcode_layout((string) $param('tam', 'mediana'));
$copies     = max(1, min(50, (int) $param('copias', '1')));
$showHeader = (string) $param('encabezado', '1') === '1';
$business   = settings_all();

/* ---------------- Construcción de etiquetas ---------------- */
$labels = [];
foreach ($products as $p) {
    $code = (string) ($p['barcode'] ?? '');
    if ($code === '') {
        $code = barcode_assign((int) $p['id']); // por si el producto aún no tenía
    }

    $price = '';
    if (!empty($layout['price'])) {
        $amount = $p['type'] === 'sale' ? (float) ($p['sale_price'] ?? 0) : (float) $p['rental_price'];
        if ($amount > 0) $price = money($amount);
    }

    $label = [
        'name'     => (string) $p['name'],
        'code'     => $code,
        'sku'      => (string) ($p['sku'] ?? ''),
        'size'     => (string) ($p['size'] ?? ''),
        'color'    => (string) ($p['color'] ?? ''),
        'category' => (string) ($p['category_name'] ?? ''),
        'price'    => $price,
    ];
    for ($i = 0; $i < $copies; $i++) {
        $labels[] = $label;
    }
}

$docTitle = count($products) === 1
    ? 'Código de barras · ' . $products[0]['name']
    : 'Códigos de barra · ' . count($products) . ' productos';

log_activity(
    'barcode.export',
    'product',
    count($products) === 1 ? (int) $products[0]['id'] : null,
    'Exportó ' . count($labels) . ' etiqueta(s) de código de barras (' . $layout['key'] . ')'
);

$filename = count($products) === 1
    ? 'Codigo-' . ($products[0]['barcode'] ?: $products[0]['id'])
    : 'Codigos-de-barra-' . date('Y-m-d');

ob_start();
require LCN_ROOT . '/app/views/templates/pdf/barcode_labels.php';
render_pdf(ob_get_clean(), $filename);
