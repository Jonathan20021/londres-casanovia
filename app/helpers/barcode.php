<?php
/**
 * Códigos de barras (Code 128-B) — generación, render y búsqueda.
 * LONDRES Casa de Novias
 *
 * Se usa Code 128 subconjunto B porque:
 *   - Es el estándar industrial para códigos internos (no retail/EAN).
 *   - Lo leen TODOS los lectores del mercado (láser 1D, CCD, imager 2D
 *     como el 2CONNET 2C-SC1100W-2D, lectores de teléfono, etc.).
 *   - Permite letras + números, así que el código lleva el prefijo de la marca.
 *
 * El valor generado usa SOLO A-Z y 0-9 (sin guiones ni símbolos) para evitar
 * que un lector configurado con otra distribución de teclado envíe un carácter
 * distinto al PC. Ej.: LCN000042
 */
declare(strict_types=1);

/* ================================================================== *
 *  TABLA DE PATRONES CODE 128 (índices 0..106)
 *  Cada patrón son anchos alternados: barra, espacio, barra, espacio…
 *  Todos suman 11 módulos (el de parada, 106, suma 13).
 * ================================================================== */
function barcode_c128_patterns(): array
{
    static $p = null;
    if ($p !== null) return $p;

    $p = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
    return $p;
}

/**
 * Codifica una cadena en Code 128-B y devuelve la secuencia binaria de módulos
 * ('1' = barra, '0' = espacio), incluyendo start, checksum y stop.
 *
 * @throws InvalidArgumentException si hay caracteres fuera del rango imprimible
 */
function barcode_c128_bits(string $data): string
{
    $data = (string) $data;
    if ($data === '') {
        throw new InvalidArgumentException('Código vacío.');
    }

    $patterns = barcode_c128_patterns();
    $values   = [104]; // Start B

    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($data[$i]);
        if ($ord < 32 || $ord > 126) {
            throw new InvalidArgumentException('Carácter no soportado en Code 128-B.');
        }
        $values[] = $ord - 32; // En el set B: valor = ASCII - 32
    }

    /* Checksum: (start + Σ posición × valor) mod 103 */
    $sum = 104;
    for ($i = 1, $n = count($values); $i < $n; $i++) {
        $sum += $i * $values[$i];
    }
    $values[] = $sum % 103;
    $values[] = 106; // Stop (incluye la barra final de terminación)

    $bits = '';
    foreach ($values as $v) {
        $pattern = $patterns[$v];
        $isBar   = true;
        for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
            $bits .= str_repeat($isBar ? '1' : '0', (int) $pattern[$i]);
            $isBar = !$isBar;
        }
    }
    return $bits;
}

/* ================================================================== *
 *  VALOR DEL CÓDIGO (asignación y persistencia)
 * ================================================================== */

/** Prefijo de la marca (configurable en Configuración del negocio). */
function barcode_prefix(): string
{
    $p = strtoupper(trim((string) setting('barcode_prefix', 'LCN')));
    $p = preg_replace('/[^A-Z0-9]/', '', $p) ?? '';
    return $p !== '' ? substr($p, 0, 6) : 'LCN';
}

/** Normaliza lo que llega de un lector: quita CR/LF/TAB, espacios y mayúsculas. */
function barcode_normalize(string $code): string
{
    $code = preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '';
    $code = trim($code);
    return strtoupper($code);
}

/** Valor canónico del código para un producto: PREFIJO + id a 6 dígitos. */
function barcode_for_id(int $productId): string
{
    return barcode_prefix() . str_pad((string) $productId, 6, '0', STR_PAD_LEFT);
}

/** ¿La tabla products ya tiene la columna barcode? (evita fallos pre-migración) */
function barcode_column_exists(): bool
{
    static $exists = null;
    if ($exists !== null) return $exists;
    try {
        $exists = db_one("SHOW COLUMNS FROM products LIKE 'barcode'") !== null;
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Asigna (y guarda) el código de barras de un producto.
 * Si ya tiene uno y $force = false, devuelve el existente.
 */
function barcode_assign(int $productId, bool $force = false): string
{
    if (!barcode_column_exists()) {
        return barcode_for_id($productId);
    }

    $current = (string) (db_value('SELECT barcode FROM products WHERE id = :id', ['id' => $productId]) ?? '');
    if ($current !== '' && !$force) {
        return $current;
    }

    $base = barcode_for_id($productId);
    $code = $base;
    $n    = 1;
    /* Colisión improbable (sólo si alguien editó códigos a mano): añade sufijo. */
    while ((int) db_value(
        'SELECT COUNT(*) FROM products WHERE barcode = :b AND id <> :id',
        ['b' => $code, 'id' => $productId]
    ) > 0) {
        $code = $base . 'X' . $n++;
    }

    db_update('products', ['barcode' => $code], 'id = :id', ['id' => $productId]);
    return $code;
}

/** Asigna código a todos los productos que aún no lo tienen. Devuelve cuántos. */
function barcode_backfill(): int
{
    if (!barcode_column_exists()) return 0;
    $rows = db_all("SELECT id FROM products WHERE barcode IS NULL OR barcode = ''");
    foreach ($rows as $r) {
        barcode_assign((int) $r['id']);
    }
    return count($rows);
}

/** Código que se asignará al próximo producto (previsualización en el alta). */
function barcode_next_preview(): string
{
    $maxId = (int) db_value('SELECT COALESCE(MAX(id), 0) FROM products');
    return barcode_for_id($maxId + 1);
}

/* ================================================================== *
 *  UNIDADES FÍSICAS (una etiqueta por pieza del stock)
 *
 *  "Cantidad en stock = 10" significa 10 trajes reales, así que se crean
 *  10 unidades con 10 códigos distintos: PREFIJO + id + U + nº de unidad.
 *  Ej.: producto 42 → LCN000042U01 … LCN000042U10.
 *  El código del PRODUCTO (LCN000042) se conserva como código maestro.
 * ================================================================== */

/** Tope de unidades por producto: evita que un tecleo (9999) genere miles de filas. */
function barcode_units_max(): int
{
    return 300;
}

/** ¿Existe la tabla product_units? (evita fallos pre-migración) */
function barcode_units_enabled(): bool
{
    static $exists = null;
    if ($exists !== null) return $exists;
    try {
        $exists = db_one("SHOW TABLES LIKE 'product_units'") !== null;
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

/** Código canónico de una unidad: PREFIJO + id a 6 dígitos + U + nº a 2 dígitos. */
function barcode_unit_code(int $productId, int $unitNumber): string
{
    $n = max(1, $unitNumber);
    return barcode_for_id($productId) . 'U' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

/** Unidades de un producto, ordenadas. */
function barcode_units(int $productId): array
{
    if (!barcode_units_enabled()) return [];
    return db_all(
        'SELECT * FROM product_units WHERE product_id = :id ORDER BY unit_number ASC',
        ['id' => $productId]
    );
}

function barcode_units_count(int $productId): int
{
    if (!barcode_units_enabled()) return 0;
    return (int) db_value('SELECT COUNT(*) FROM product_units WHERE product_id = :id', ['id' => $productId]);
}

/** ¿El código ya está en uso por otra unidad u otro producto? */
function barcode_code_taken(string $code, int $exceptUnitId = 0): bool
{
    if (barcode_units_enabled()) {
        $n = (int) db_value(
            'SELECT COUNT(*) FROM product_units WHERE barcode = :b AND id <> :id',
            ['b' => $code, 'id' => $exceptUnitId]
        );
        if ($n > 0) return true;
    }
    if (barcode_column_exists()) {
        if ((int) db_value('SELECT COUNT(*) FROM products WHERE barcode = :b', ['b' => $code]) > 0) {
            return true;
        }
    }
    return false;
}

/**
 * Ajusta las unidades de un producto a su cantidad en stock.
 *   - Crea las que falten (con su código propio).
 *   - Elimina las sobrantes (siempre las de numeración más alta).
 * Devuelve ['created','removed','total','requested','capped'].
 *
 * @param int|null $quantity  Si es null se lee de products.quantity.
 * @param bool     $force     Regenera además el código de las unidades existentes
 *                            (útil si cambió el prefijo de la marca).
 */
function barcode_units_sync(int $productId, ?int $quantity = null, bool $force = false): array
{
    $out = ['created' => 0, 'removed' => 0, 'total' => 0, 'requested' => 0, 'capped' => false];
    if (!barcode_units_enabled() || $productId <= 0) return $out;

    if ($quantity === null) {
        $quantity = (int) db_value('SELECT quantity FROM products WHERE id = :id', ['id' => $productId]);
    }
    $requested = max(0, (int) $quantity);
    $target    = min($requested, barcode_units_max());

    $out['requested'] = $requested;
    $out['capped']    = $requested > $target;

    $existing = [];
    foreach (barcode_units($productId) as $u) {
        $existing[(int) $u['unit_number']] = $u;
    }

    /* Sobrantes: se borran las unidades por encima de la cantidad actual. */
    foreach ($existing as $num => $u) {
        if ($num > $target) {
            db_delete('product_units', 'id = :id', ['id' => (int) $u['id']]);
            unset($existing[$num]);
            $out['removed']++;
        }
    }

    /* Faltantes (y, con $force, refresco del código de las existentes). */
    for ($n = 1; $n <= $target; $n++) {
        $code = barcode_unit_code($productId, $n);
        $has  = $existing[$n] ?? null;

        if ($has === null) {
            $unique = $code;
            $i = 1;
            while (barcode_code_taken($unique)) {
                $unique = $code . 'X' . $i++;
            }
            db_insert('product_units', [
                'product_id'  => $productId,
                'unit_number' => $n,
                'barcode'     => $unique,
            ]);
            $out['created']++;
        } elseif ($force && (string) $has['barcode'] !== $code && !barcode_code_taken($code, (int) $has['id'])) {
            db_update('product_units', ['barcode' => $code], 'id = :id', ['id' => (int) $has['id']]);
        }
    }

    $out['total'] = $target;

    /* Al añadir o quitar piezas cambian las tallas disponibles del producto. */
    if ($out['created'] > 0 || $out['removed'] > 0) {
        product_size_summary_refresh($productId);
    }

    return $out;
}

/** Crea las unidades que falten en TODO el inventario. Devuelve cuántas creó. */
function barcode_units_backfill(): int
{
    if (!barcode_units_enabled()) return 0;

    /* Sólo productos cuyo nº de unidades no coincide con su stock. */
    $rows = db_all(
        'SELECT p.id, p.quantity, COUNT(u.id) AS units
         FROM products p
         LEFT JOIN product_units u ON u.product_id = p.id
         GROUP BY p.id, p.quantity
         HAVING units <> LEAST(GREATEST(p.quantity, 0), ' . barcode_units_max() . ')'
    );

    $created = 0;
    foreach ($rows as $r) {
        $res = barcode_units_sync((int) $r['id'], (int) $r['quantity']);
        $created += $res['created'];
    }
    return $created;
}

/* ------------------------------------------------------------------ *
 *  TALLAS POR UNIDAD
 *
 *  Del mismo traje (mismo color, mismo diseño) puede haber varias tallas.
 *  Cada unidad guarda la suya y `products.size` queda como resumen legible
 *  ("S · M · L"), que es lo que ya muestran el catálogo y los listados.
 * ------------------------------------------------------------------ */

/** ¿La tabla product_units ya tiene la columna size? (evita fallos pre-migración) */
function barcode_unit_sizes_enabled(): bool
{
    static $exists = null;
    if ($exists !== null) return $exists;
    if (!barcode_units_enabled()) return $exists = false;
    try {
        $exists = db_one("SHOW COLUMNS FROM product_units LIKE 'size'") !== null;
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

/** Tallas de un producto indexadas por número de unidad: [1 => 'S', 2 => 'M', …] */
function product_unit_sizes(int $productId): array
{
    if (!barcode_unit_sizes_enabled()) return [];
    $out = [];
    foreach (barcode_units($productId) as $u) {
        $out[(int) $u['unit_number']] = (string) ($u['size'] ?? '');
    }
    return $out;
}

/**
 * Lista de tallas distintas de un producto, en el orden en que aparecen.
 * Es lo que se enseña al cliente ("Tallas disponibles: S, M, L").
 */
function product_sizes_list(int $productId): array
{
    if (!barcode_unit_sizes_enabled()) return [];
    $rows = db_all(
        'SELECT size FROM product_units
         WHERE product_id = :id AND size IS NOT NULL AND size <> ""
         ORDER BY unit_number ASC',
        ['id' => $productId]
    );
    $out = [];
    foreach ($rows as $r) {
        $s = trim((string) $r['size']);
        if ($s !== '' && !in_array($s, $out, true)) $out[] = $s;
    }
    return $out;
}

/**
 * Recalcula products.size a partir de las tallas de las unidades.
 * Si ninguna unidad tiene talla, respeta lo que el usuario escribió a mano —
 * salvo que ahí hubiera quedado un resumen viejo, que sí se limpia.
 */
function product_size_summary_refresh(int $productId): ?string
{
    $sizes = product_sizes_list($productId);

    if (!$sizes) {
        $current = (string) (db_value('SELECT size FROM products WHERE id = :id', ['id' => $productId]) ?? '');
        if (str_contains($current, '·')) {
            db_update('products', ['size' => null], 'id = :id', ['id' => $productId]);
        }
        return null;
    }

    $summary = implode(' · ', $sizes);
    if (mb_strlen($summary) > 120) {                    // products.size = VARCHAR(120)
        $summary = mb_substr($summary, 0, 117) . '…';
    }
    db_update('products', ['size' => $summary], 'id = :id', ['id' => $productId]);
    return $summary;
}

/**
 * Guarda las tallas enviadas por el formulario ([nº de unidad => talla]) y
 * actualiza el resumen del producto. Devuelve cuántas unidades cambiaron.
 */
function product_units_apply_sizes(int $productId, array $sizes): int
{
    if (!barcode_unit_sizes_enabled()) return 0;

    $changed = 0;
    foreach (barcode_units($productId) as $u) {
        $n = (int) $u['unit_number'];
        if (!array_key_exists($n, $sizes)) continue;

        $new = mb_substr(trim((string) $sizes[$n]), 0, 40);
        if ($new === (string) ($u['size'] ?? '')) continue;

        db_update('product_units', ['size' => $new !== '' ? $new : null], 'id = :id', ['id' => (int) $u['id']]);
        $changed++;
    }

    if ($changed > 0) product_size_summary_refresh($productId);
    return $changed;
}

/** Tallas ya usadas en el inventario — alimenta el <datalist> de los formularios. */
function product_sizes_catalog(): array
{
    $out = [];
    if (barcode_unit_sizes_enabled()) {
        foreach (db_all('SELECT DISTINCT size FROM product_units WHERE size IS NOT NULL AND size <> "" ORDER BY size ASC') as $r) {
            $out[] = (string) $r['size'];
        }
    }
    foreach (db_all('SELECT DISTINCT size FROM products WHERE size IS NOT NULL AND size <> "" ORDER BY size ASC') as $r) {
        $s = (string) $r['size'];
        if (!str_contains($s, '·') && !in_array($s, $out, true)) $out[] = $s;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/** Busca la unidad física correspondiente a un código leído. */
function barcode_unit_lookup(string $raw): ?array
{
    if (!barcode_units_enabled()) return null;

    $code = barcode_normalize($raw);
    if ($code === '') return null;

    return db_one('SELECT * FROM product_units WHERE UPPER(barcode) = :c LIMIT 1', ['c' => $code]);
}

/**
 * Busca un producto a partir de lo que envió el lector.
 * Acepta el código de unidad, el código del producto, el SKU, sólo los dígitos o el ID.
 *
 * Si el código leído es de una unidad, la fila devuelta añade las claves
 * unit_id / unit_number / unit_barcode / unit_status / unit_total.
 */
function barcode_lookup(string $raw): ?array
{
    $code = barcode_normalize($raw);
    if ($code === '') return null;

    $hasBarcode = barcode_column_exists();

    /* 1) Unidad física — es lo que llevan las etiquetas nuevas. */
    $unit = barcode_unit_lookup($code);
    if ($unit) {
        $row = db_one(
            'SELECT p.*, c.name AS category_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id LIMIT 1',
            ['id' => (int) $unit['product_id']]
        );
        if ($row) {
            $row['unit_id']      = (int) $unit['id'];
            $row['unit_number']  = (int) $unit['unit_number'];
            $row['unit_barcode'] = (string) $unit['barcode'];
            $row['unit_status']  = (string) $unit['status'];
            $row['unit_size']    = (string) ($unit['size'] ?? '');
            $row['unit_total']   = barcode_units_count((int) $unit['product_id']);
            return $row;
        }
    }

    if ($hasBarcode) {
        $row = db_one(
            'SELECT p.*, c.name AS category_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE UPPER(p.barcode) = :c LIMIT 1',
            ['c' => $code]
        );
        if ($row) return $row;
    }

    /* SKU */
    $row = db_one(
        'SELECT p.*, c.name AS category_name
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE UPPER(p.sku) = :c LIMIT 1',
        ['c' => $code]
    );
    if ($row) return $row;

    /*
     * Etiqueta de unidad cuya fila ya no existe (stock reducido, código
     * regenerado…): se resuelve por el código maestro que lleva delante.
     * Sin esto, el bloque de dígitos de abajo leería "00004203" y devolvería
     * el producto 4203, que no tiene nada que ver.
     */
    if (preg_match('/^(.+?)U(\d+)(?:X\d+)?$/', $code, $m)) {
        $base = $m[1];
        if ($hasBarcode) {
            $row = db_one(
                'SELECT p.*, c.name AS category_name
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 WHERE UPPER(p.barcode) = :c LIMIT 1',
                ['c' => $base]
            );
            if ($row) return $row;
        }
        $baseId = (int) ltrim(preg_replace('/\D+/', '', $base) ?? '', '0');
        if ($baseId > 0) {
            $row = db_one(
                'SELECT p.*, c.name AS category_name
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :id LIMIT 1',
                ['id' => $baseId]
            );
            if ($row) return $row;
        }
        return null;
    }

    /* Sólo dígitos: el lector pudo omitir el prefijo o ser un ID directo. */
    $digits = preg_replace('/\D+/', '', $code) ?? '';
    if ($digits !== '') {
        $id  = (int) ltrim($digits, '0');
        if ($id > 0) {
            $row = db_one(
                'SELECT p.*, c.name AS category_name
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = :id LIMIT 1',
                ['id' => $id]
            );
            if ($row) return $row;
        }
        if ($hasBarcode) {
            $row = db_one(
                'SELECT p.*, c.name AS category_name
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.barcode LIKE :b LIMIT 1',
                ['b' => '%' . $digits]
            );
            if ($row) return $row;
        }
    }

    return null;
}

/* ================================================================== *
 *  FORMATOS DE ETIQUETA (hoja A4)
 * ================================================================== */
function barcode_layouts(): array
{
    return [
        'grande' => [
            'key' => 'grande', 'label' => 'Grande (2 × 5 · 10 por hoja)',
            'hint' => 'Ideal para vestidos y trajes (colgante)',
            'cols' => 2, 'rows' => 5, 'w' => 96, 'h' => 50,
            'bar_h' => 52, 'font' => 9.5, 'name_max' => 44, 'price' => true,
        ],
        'mediana' => [
            'key' => 'mediana', 'label' => 'Mediana (3 × 7 · 21 por hoja)',
            'hint' => 'Uso general de inventario',
            'cols' => 3, 'rows' => 7, 'w' => 64, 'h' => 36,
            'bar_h' => 36, 'font' => 7.5, 'name_max' => 34, 'price' => false,
        ],
        'pequena' => [
            'key' => 'pequena', 'label' => 'Pequeña (4 × 10 · 40 por hoja)',
            'hint' => 'Accesorios, velos, coronas',
            'cols' => 4, 'rows' => 10, 'w' => 48, 'h' => 25,
            'bar_h' => 24, 'font' => 6.2, 'name_max' => 24, 'price' => false,
        ],
    ];
}

function barcode_layout(string $key): array
{
    $all = barcode_layouts();
    return $all[$key] ?? $all['mediana'];
}

/* ================================================================== *
 *  RENDER
 * ================================================================== */

/**
 * SVG en línea (para pantalla).
 *
 * @param array $o  module (px), height (px), quiet (módulos), text (bool),
 *                  color, background, class
 */
function barcode_svg(string $code, array $o = []): string
{
    $code = barcode_normalize($code);
    try {
        $bits = barcode_c128_bits($code);
    } catch (Throwable $e) {
        return '<span class="text-xs text-gray-400">Código inválido</span>';
    }

    $module = (float) ($o['module'] ?? 2);
    $height = (float) ($o['height'] ?? 60);
    $quiet  = (int)   ($o['quiet']  ?? 10);
    $text   = $o['text'] ?? true;
    $color  = (string) ($o['color'] ?? '#0B0B0C');
    $bg     = (string) ($o['background'] ?? '#FFFFFF');
    $class  = (string) ($o['class'] ?? '');

    $n        = strlen($bits);
    $width    = ($n + $quiet * 2) * $module;
    $fontSize = max(9.0, $module * 5.0);
    $textGap  = $text ? $fontSize + 6 : 0;
    $total    = $height + $textGap;

    $rects = '';
    $i = 0;
    while ($i < $n) {
        if ($bits[$i] === '1') {
            $run = 1;
            while ($i + $run < $n && $bits[$i + $run] === '1') $run++;
            $x = ($quiet + $i) * $module;
            $rects .= '<rect x="' . round($x, 3) . '" y="0" width="' . round($run * $module, 3)
                . '" height="' . round($height, 3) . '" fill="' . e($color) . '"/>';
            $i += $run;
        } else {
            $i++;
        }
    }

    $label = '';
    if ($text) {
        $label = '<text x="' . round($width / 2, 3) . '" y="' . round($height + $fontSize + 2, 3)
            . '" text-anchor="middle" font-family="Menlo,Consolas,monospace" letter-spacing="'
            . round($module * 0.8, 2) . '" font-size="' . round($fontSize, 2) . '" fill="' . e($color) . '">'
            . e($code) . '</text>';
    }

    return '<svg class="' . e($class) . '" role="img" aria-label="Código de barras ' . e($code) . '"'
        . ' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . round($width, 3) . ' ' . round($total, 3) . '"'
        . ' width="100%" preserveAspectRatio="xMidYMid meet">'
        . '<rect width="100%" height="100%" fill="' . e($bg) . '"/>'
        . $rects . $label . '</svg>';
}

/**
 * PNG binario del código (para Dompdf, que renderiza PNG de forma fiable).
 * Se genera a alta resolución y luego se escala en el PDF → impresión nítida.
 */
function barcode_png(string $code, array $o = []): ?string
{
    if (!function_exists('imagecreatetruecolor')) return null;

    $code = barcode_normalize($code);
    try {
        $bits = barcode_c128_bits($code);
    } catch (Throwable $e) {
        return null;
    }

    $module = max(1, (int) ($o['module'] ?? 3));   // px por módulo
    $height = max(10, (int) ($o['height'] ?? 110));
    $quiet  = (int) ($o['quiet'] ?? 10);

    $n     = strlen($bits);
    $width = ($n + $quiet * 2) * $module;

    $img   = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, $width, $height, $white);

    for ($i = 0; $i < $n; $i++) {
        if ($bits[$i] !== '1') continue;
        $x1 = ($quiet + $i) * $module;
        imagefilledrectangle($img, $x1, 0, $x1 + $module - 1, $height - 1, $black);
    }

    ob_start();
    imagepng($img, null, 9);
    $png = (string) ob_get_clean();
    imagedestroy($img);

    return $png;
}

/**
 * Data-URI PNG lista para <img src="…"> dentro de un PDF (Dompdf).
 * Se memoriza: en un lote con copias repetidas evita regenerar la misma imagen.
 */
function barcode_png_uri(string $code, array $o = []): string
{
    static $cache = [];
    $key = $code . '|' . ($o['module'] ?? 3) . '|' . ($o['height'] ?? 110) . '|' . ($o['quiet'] ?? 10);
    if (isset($cache[$key])) return $cache[$key];

    $png = barcode_png($code, $o);
    return $cache[$key] = ($png !== null ? 'data:image/png;base64,' . base64_encode($png) : '');
}

/**
 * Marca de barras para PDF. Usa PNG (GD); si GD no existiera, cae a barras
 * dibujadas con <div> — Dompdf también las imprime correctamente.
 */
function barcode_pdf_mark(string $code, array $o = []): string
{
    $widthCss = (string) ($o['css_width'] ?? '100%');
    $heightPx = (float) ($o['pdf_height'] ?? 34);

    $uri = barcode_png_uri($code, ['module' => 3, 'height' => 140, 'quiet' => 12]);
    if ($uri !== '') {
        return '<img src="' . $uri . '" style="width:' . e($widthCss) . '; height:' . $heightPx . 'px;">';
    }

    /* Fallback sin GD: barras como divs de ancho fijo. */
    try {
        $bits = barcode_c128_bits(barcode_normalize($code));
    } catch (Throwable $e) {
        return '';
    }
    $module = (float) ($o['fallback_module'] ?? 0.9);
    $out    = '<div style="height:' . $heightPx . 'px; font-size:0; line-height:0; white-space:nowrap;">';
    $i = 0; $n = strlen($bits);
    while ($i < $n) {
        $ch  = $bits[$i];
        $run = 1;
        while ($i + $run < $n && $bits[$i + $run] === $ch) $run++;
        $out .= '<div style="display:inline-block; vertical-align:top; width:' . round($run * $module, 3)
            . 'px; height:' . $heightPx . 'px; background:' . ($ch === '1' ? '#000' : '#fff') . ';"></div>';
        $i += $run;
    }
    return $out . '</div>';
}
