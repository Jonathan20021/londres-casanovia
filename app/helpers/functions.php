<?php
/**
 * Funciones de utilidad generales.
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

/* ------------------------------------------------------------------ *
 *  Escape / salida segura
 * ------------------------------------------------------------------ */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------------------------------------------ *
 *  Generación de URLs
 * ------------------------------------------------------------------ */
function url(string $path = ''): string  { return APP_URL . '/' . ltrim($path, '/'); }

/**
 * URL de un recurso estático.
 *
 * A las hojas de estilo y a los scripts se les añade ?v=<fecha del archivo>.
 * Sin eso el navegador sigue sirviendo la copia guardada en caché y los
 * cambios de CSS/JS no llegan al usuario hasta que vacía la caché a mano.
 */
function asset(string $path = ''): string
{
    $path = ltrim($path, '/');
    $url  = APP_URL . '/public/assets/' . $path;

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== 'css' && $ext !== 'js') {
        return $url;
    }

    static $versiones = [];
    if (!array_key_exists($path, $versiones)) {
        $file = LCN_ROOT . '/public/assets/' . $path;
        $versiones[$path] = is_file($file) ? (string) filemtime($file) : '';
    }

    return $versiones[$path] !== '' ? $url . '?v=' . $versiones[$path] : $url;
}

/**
 * Normaliza una ruta a "URL limpia": separa la query, quita ".php" y
 * convierte "index"/".../index" en la carpeta. Devuelve [ruta, query].
 */
function clean_route(string $path): array
{
    $path = ltrim($path, '/');
    $qs = '';
    if (($pos = strpos($path, '?')) !== false) {
        $qs   = substr($path, $pos);
        $path = substr($path, 0, $pos);
    }
    $isIndex = false;
    $path = preg_replace('/\.php$/i', '', $path);
    if ($path === 'index') {
        $path = '';
        $isIndex = true;
    } elseif (substr($path, -6) === '/index') {
        $path = substr($path, 0, -6);
        $isIndex = true;
    }
    return [trim($path, '/'), $qs, $isIndex];
}

/** URL del panel sin ".php": admin_url('productos/crear.php') -> /admin/productos/crear */
function admin_url(string $path = ''): string
{
    [$p, $qs, $isIndex] = clean_route($path);
    $base = APP_URL . '/admin' . ($p !== '' ? '/' . $p : '');
    if ($isIndex) $base .= '/';   // carpeta -> con barra final (evita el redirect 301)
    return $base . $qs;
}

/** URL pública sin ".php" ni "/public/": pub_url('inventario.php') -> /inventario */
function pub_url(string $path = ''): string
{
    // Producto con slug bonito: producto.php?slug=X -> /producto/X
    if (preg_match('#^/?producto\.php\?slug=(.+)$#i', $path, $m)) {
        return APP_URL . '/producto/' . $m[1];
    }
    [$p, $qs, $isIndex] = clean_route($path);
    if ($p === '') return APP_URL . '/' . $qs;       // raíz pública
    return APP_URL . '/' . $p . ($isIndex ? '/' : '') . $qs;
}

/** URL de una imagen subida; si está vacía devuelve un placeholder. */
function upload_url(?string $path): string
{
    if (empty($path)) {
        return asset('img/placeholder.svg');
    }
    // Rutas guardadas como "uploads/products/x.jpg" (relativas a /assets)
    if (str_starts_with($path, 'http')) return $path;
    return asset(ltrim($path, '/'));
}

/* ------------------------------------------------------------------ *
 *  Redirecciones
 * ------------------------------------------------------------------ */
function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function back(string $fallback = ''): void
{
    redirect($_SERVER['HTTP_REFERER'] ?? ($fallback ?: admin_url('dashboard.php')));
}

/* ------------------------------------------------------------------ *
 *  Entrada
 * ------------------------------------------------------------------ */
/** GET sanitizado a string. */
function get_param(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

/** POST sin sanitizar (se escapa al imprimir con e()). */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/* ------------------------------------------------------------------ *
 *  Slugs y números de documento
 * ------------------------------------------------------------------ */
function slugify(string $text): string
{
    $text = trim($text);
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n','Ü'=>'u'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', $text);
    $text = strtolower(trim($text, '-'));
    return $text ?: 'item';
}

/** Garantiza un slug único en una tabla/columna. */
function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $i = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = :slug" . ($ignoreId ? " AND id <> :id" : '');
        $params = ['slug' => $slug];
        if ($ignoreId) $params['id'] = $ignoreId;
        if ((int) db_value($sql, $params) === 0) return $slug;
        $slug = slugify($base) . '-' . (++$i);
    }
}

/**
 * Genera un número correlativo tipo PREFIX-000123 basado en el conteo de la tabla.
 */
function generate_number(string $table, string $column, string $prefix, int $pad = 5): string
{
    $count = (int) db_value("SELECT COUNT(*) FROM `$table`") + 1;
    do {
        $candidate = sprintf('%s-%0' . $pad . 'd', $prefix, $count);
        $exists = (int) db_value("SELECT COUNT(*) FROM `$table` WHERE `$column` = :n", ['n' => $candidate]);
        $count++;
    } while ($exists > 0);
    return $candidate;
}

/* ------------------------------------------------------------------ *
 *  Configuración del negocio (business_settings, fila id=1)
 * ------------------------------------------------------------------ */
function settings_all(): array
{
    static $row = null;
    if ($row === null) {
        $row = db_one('SELECT * FROM business_settings ORDER BY id ASC LIMIT 1') ?: [];
    }
    return $row;
}

function setting(string $key, $default = null)
{
    $row = settings_all();
    if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
        return $row[$key];
    }
    return $default;
}

/* ------------------------------------------------------------------ *
 *  Formato
 * ------------------------------------------------------------------ */
function money($amount): string
{
    $currency = setting('currency', 'RD$');
    return $currency . ' ' . number_format((float) $amount, 2, '.', ',');
}

function format_date($date, string $fmt = 'd/m/Y'): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '—';
    try { return (new DateTime((string) $date))->format($fmt); }
    catch (Exception $e) { return (string) $date; }
}

function format_datetime($date): string { return format_date($date, 'd/m/Y h:i A'); }

/** Hora suelta ("14:30" / "14:30:00") en formato 12 h. Vacía => ''. */
function format_time($time, string $fmt = 'h:i A'): string
{
    if (empty($time) || $time === '00:00:00') return '';
    try { return (new DateTime('2000-01-01 ' . $time))->format($fmt); }
    catch (Exception $e) { return (string) $time; }
}

/** "29/07/2026 · 10:30 AM" (la hora solo si existe). */
function format_date_time($date, $time): string
{
    $out = format_date($date);
    $hh  = format_time($time);
    return $hh !== '' ? $out . ' · ' . $hh : $out;
}

/** Días entre dos fechas (puede ser negativo). */
function days_between($from, $to): int
{
    try {
        $a = new DateTime((string) $from);
        $b = new DateTime((string) $to);
        return (int) $a->diff($b)->format('%r%a');
    } catch (Exception $e) { return 0; }
}

/* ------------------------------------------------------------------ *
 *  Mensajes flash
 * ------------------------------------------------------------------ */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type] = $message;
}

function flash_get(string $type): ?string
{
    if (!empty($_SESSION['_flash'][$type])) {
        $m = $_SESSION['_flash'][$type];
        unset($_SESSION['_flash'][$type]);
        return $m;
    }
    return null;
}

function flash_all(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ------------------------------------------------------------------ *
 *  Respuestas JSON (APIs)
 * ------------------------------------------------------------------ */
function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ------------------------------------------------------------------ *
 *  Registro de actividad y notificaciones
 * ------------------------------------------------------------------ */
function log_activity(string $action, ?string $entityType = null, ?int $entityId = null, string $description = ''): void
{
    try {
        db_insert('activity_logs', [
            'user_id'     => $_SESSION['user_id'] ?? null,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* nunca romper el flujo por un log */ }
}

function notify(?int $userId, string $title, string $message, string $type = 'info'): void
{
    try {
        db_insert('notifications', [
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'type'    => $type,
        ]);
    } catch (Throwable $e) { /* silencioso */ }
}

/** Notifica a todos los usuarios administradores activos. */
function notify_admins(string $title, string $message, string $type = 'info'): void
{
    $admins = db_all("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                      WHERE u.status = 'active' AND r.name IN ('Super Admin','Administrador')");
    foreach ($admins as $a) {
        notify((int) $a['id'], $title, $message, $type);
    }
}

/* ------------------------------------------------------------------ *
 *  Paginación: calcula offset/limit y metadatos
 * ------------------------------------------------------------------ */
function paginate(int $total, int $perPage = 12, string $pageParam = 'page'): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($pages, (int) ($_GET[$pageParam] ?? 1)));
    return [
        'page'    => $page,
        'pages'   => $pages,
        'perPage' => $perPage,
        'offset'  => ($page - 1) * $perPage,
        'total'   => $total,
    ];
}

/** Construye una URL conservando los parámetros GET actuales + overrides. */
function query_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($params);
    return strtok($_SERVER['REQUEST_URI'], '?') . ($qs ? '?' . $qs : '');
}
