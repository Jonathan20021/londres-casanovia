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

/**
 * Busca un producto a partir de lo que envió el lector.
 * Acepta el código completo, el SKU, sólo los dígitos o el ID.
 */
function barcode_lookup(string $raw): ?array
{
    $code = barcode_normalize($raw);
    if ($code === '') return null;

    $hasBarcode = barcode_column_exists();

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

/** Data-URI PNG lista para <img src="…"> dentro de un PDF (Dompdf). */
function barcode_png_uri(string $code, array $o = []): string
{
    $png = barcode_png($code, $o);
    return $png !== null ? 'data:image/png;base64,' . base64_encode($png) : '';
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
