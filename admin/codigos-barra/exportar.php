<?php
/**
 * Códigos de barra — Exportación a PDF (individual y por lote).
 * LONDRES Casa de Novias
 *
 * admin/codigos-barra/exportar.php  (N=2)  ·  Permiso: products.view
 *
 * Parámetros (GET o POST):
 *   ids       int[] | CSV de IDs de producto
 *   unit_ids  int[] | CSV de IDs de unidad (product_units) — etiquetas sueltas
 *   all       1     → exporta todo lo que coincida con los filtros
 *   q, category_id, commercial_status, type   → filtros (sólo con all=1)
 *   modo      unidades (una etiqueta por pieza del stock) | producto (código maestro)
 *   tam       grande | mediana | pequena
 *   copias    1..50  (copias de CADA etiqueta)
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

/** Convierte ids[]/CSV en una lista de enteros positivos únicos. */
$intList = static function ($raw): array {
    if (is_string($raw)) $raw = explode(',', $raw);
    $out = [];
    foreach ((array) $raw as $v) {
        $v = (int) $v;
        if ($v > 0) $out[$v] = $v;
    }
    return array_values($out);
};

/* ---------------- Modo de etiqueta ---------------- */
$modo = (string) $param('modo', 'unidades');
if (!in_array($modo, ['unidades', 'producto'], true)) $modo = 'unidades';
if (!barcode_units_enabled()) $modo = 'producto';   // sin migración aplicada

/* ---------------- Selección ---------------- */
$all      = (string) $param('all') === '1';
$ids      = $intList($_POST['ids'] ?? $_GET['ids'] ?? []);
$unitIds  = $intList($_POST['unit_ids'] ?? $_GET['unit_ids'] ?? []);

if ($unitIds && barcode_units_enabled()) {
    $modo = 'unidades';   // etiquetas sueltas: manda la selección explícita
}

$where  = [];
$params = [];

if ($unitIds) {
    $in = [];
    foreach ($unitIds as $i => $v) {
        $in[] = ':u' . $i;
        $params['u' . $i] = $v;
    }
    $where[] = 'u.id IN (' . implode(',', $in) . ')';
} elseif ($all) {
    $q          = (string) $param('q');
    $categoryId = (int) $param('category_id', '0');
    $type       = (string) $param('type');
    $comStatus  = (string) $param('commercial_status');

    if ($q !== '') {
        $search = product_search_clause($q);
        if ($search['sql'] !== '') {
            $where[] = $search['sql'];
            $params += $search['params'];
        }
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
    foreach ($ids as $i => $v) {
        $in[] = ':id' . $i;
        $params['id' . $i] = $v;
    }
    $where[] = 'p.id IN (' . implode(',', $in) . ')';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ---------------- Opciones de la hoja ---------------- */
$layout     = barcode_layout((string) $param('tam', 'mediana'));
$copies     = max(1, min(50, (int) $param('copias', '1')));
$showHeader = (string) $param('encabezado', '1') === '1';
$business   = settings_all();

/**
 * Datos comunes de la etiqueta a partir de la fila del producto.
 * $code y $unit cambian según se imprima por unidad o el código maestro.
 */
$makeLabel = static function (array $p, string $code, string $unit) use ($layout): array {
    $price = '';
    if (!empty($layout['price'])) {
        $amount = $p['type'] === 'sale' ? (float) ($p['sale_price'] ?? 0) : (float) $p['rental_price'];
        if ($amount > 0) $price = money($amount);
    }
    return [
        'name'     => (string) $p['name'],
        'code'     => $code,
        'unit'     => $unit,
        'sku'      => (string) ($p['sku'] ?? ''),
        'size'     => (string) ($p['size'] ?? ''),
        'color'    => (string) ($p['color'] ?? ''),
        'category' => (string) ($p['category_name'] ?? ''),
        'price'    => $price,
    ];
};

/* ---------------- Construcción de etiquetas ---------------- */
$labels      = [];
$productIds  = [];

if ($modo === 'unidades') {
    /* Pone al día las unidades que no coincidan con su cantidad en stock. */
    barcode_units_backfill();

    /* La talla de la unidad manda sobre la del producto (que es un resumen) */
    $unitSizeSql = barcode_unit_sizes_enabled() ? 'u.size AS unit_size,' : "'' AS unit_size,";

    $rows = db_all(
        'SELECT u.id AS unit_id, u.unit_number, u.barcode AS unit_barcode, u.product_id,
                ' . $unitSizeSql . '
                p.id, p.name, p.sku, p.size, p.color, p.rental_price, p.sale_price, p.type,
                c.name AS category_name,
                (SELECT COUNT(*) FROM product_units x WHERE x.product_id = p.id) AS unit_total
         FROM product_units u
         JOIN products p ON p.id = u.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         ' . $whereSql . '
         ORDER BY p.name ASC, u.unit_number ASC',
        $params
    );

    foreach ($rows as $r) {
        $productIds[(int) $r['product_id']] = true;
        $total = (int) $r['unit_total'];
        $unit  = $total > 1
            ? 'Unidad ' . (int) $r['unit_number'] . ' de ' . $total
            : '';

        /*
         * Talla impresa: la de la pieza. Si esta unidad no la tiene y el
         * producto guarda un resumen ("S · M · L"), se deja en blanco antes
         * que mentir en la etiqueta.
         */
        $unitSize = trim((string) ($r['unit_size'] ?? ''));
        $r['size'] = $unitSize !== ''
            ? $unitSize
            : (str_contains((string) ($r['size'] ?? ''), '·') ? '' : (string) ($r['size'] ?? ''));

        $label = $makeLabel($r, (string) $r['unit_barcode'], $unit);
        for ($i = 0; $i < $copies; $i++) {
            $labels[] = $label;
        }
    }

    /*
     * Productos sin unidades (cantidad en stock 0): se imprime su código
     * maestro para que la selección del usuario nunca salga vacía.
     */
    if (!$unitIds) {
        $missing = db_all(
            'SELECT p.id, p.name, p.sku, p.barcode, p.size, p.color, p.rental_price, p.sale_price, p.type,
                    c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ' . $whereSql . '
             ' . ($whereSql ? 'AND' : 'WHERE') . ' NOT EXISTS (SELECT 1 FROM product_units u WHERE u.product_id = p.id)
             ORDER BY p.name ASC',
            $params
        );
        foreach ($missing as $p) {
            $productIds[(int) $p['id']] = true;
            $code = (string) ($p['barcode'] ?? '');
            if ($code === '') $code = barcode_assign((int) $p['id']);
            $label = $makeLabel($p, $code, '');
            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $label;
            }
        }
    }
} else {
    $products = db_all(
        'SELECT p.id, p.name, p.sku, p.barcode, p.size, p.color, p.rental_price, p.sale_price, p.type,
                c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         ' . $whereSql . '
         ORDER BY p.name ASC',
        $params
    );

    foreach ($products as $p) {
        $productIds[(int) $p['id']] = true;
        $code = (string) ($p['barcode'] ?? '');
        if ($code === '') {
            $code = barcode_assign((int) $p['id']); // por si el producto aún no tenía
        }
        $label = $makeLabel($p, $code, '');
        for ($i = 0; $i < $copies; $i++) {
            $labels[] = $label;
        }
    }
}

if (!$labels) {
    flash('error', 'No se encontraron etiquetas para exportar.');
    redirect(admin_url('codigos-barra/index.php'));
}

$productCount = count($productIds);

/*
 * Tope de seguridad: un PDF con miles de etiquetas agotaría la memoria o el
 * tiempo del servidor. Se avisa en lugar de recortar en silencio.
 */
$maxLabels = 1500;
if (count($labels) > $maxLabels) {
    flash('error', sprintf(
        'Son %s etiquetas (%d producto(s)%s × %d copia(s)) y el máximo por PDF es %s. Filtre los productos, reduzca las copias o imprima por producto.',
        number_format(count($labels)),
        $productCount,
        $modo === 'unidades' ? ' con sus unidades' : '',
        $copies,
        number_format($maxLabels)
    ));
    redirect(admin_url('codigos-barra/index.php'));
}

/* Los lotes grandes necesitan algo más de holgura que el valor por defecto */
if (count($labels) > 150) {
    @set_time_limit(180);
    @ini_set('memory_limit', '512M');
}

$firstId   = (int) array_key_first($productIds);
$firstName = (string) db_value('SELECT name FROM products WHERE id = :id', ['id' => $firstId]);

$docTitle = $productCount === 1
    ? 'Códigos de barra · ' . $firstName
    : 'Códigos de barra · ' . $productCount . ' productos';

log_activity(
    'barcode.export',
    'product',
    $productCount === 1 ? $firstId : null,
    'Exportó ' . count($labels) . ' etiqueta(s) de código de barras (' . $layout['key'] . ' · ' . $modo . ')'
);

$filename = $productCount === 1
    ? 'Codigos-' . preg_replace('/[^A-Za-z0-9]+/', '-', $firstName ?: (string) $firstId)
    : 'Codigos-de-barra-' . date('Y-m-d');

ob_start();
require LCN_ROOT . '/app/views/templates/pdf/barcode_labels.php';
render_pdf(ob_get_clean(), $filename);
