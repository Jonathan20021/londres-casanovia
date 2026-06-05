<?php
/**
 * Instalador web (un solo uso) — crea la base de datos e importa schema + seed.
 * Útil en hosting/cPanel donde no hay acceso a la línea de comandos.
 *
 * SEGURIDAD: ELIMINA este archivo después de instalar.
 * En XAMPP también puedes importar manualmente database/schema.sql y seed.sql.
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/app/config/database.php'; // DB_HOST, DB_USER, DB_PASS, DB_NAME

$done = false;
$errors = [];
$log = [];

function run_sql_file(mysqli $conn, string $path, array &$log, array &$errors): void
{
    if (!is_file($path)) { $errors[] = "No se encontró: $path"; return; }
    $sql = file_get_contents($path);
    if ($conn->multi_query($sql)) {
        do {
            if ($res = $conn->store_result()) { $res->free(); }
        } while ($conn->more_results() && $conn->next_result());
    }
    if ($conn->errno) {
        $errors[] = basename($path) . ': ' . $conn->error;
    } else {
        $log[] = basename($path) . ' importado correctamente.';
    }
    // Drenar cualquier resultado restante
    while ($conn->more_results() && $conn->next_result()) { if ($r = $conn->store_result()) $r->free(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', (int) DB_PORT);
    if ($conn->connect_errno) {
        $errors[] = 'No se pudo conectar a MySQL: ' . $conn->connect_error;
    } else {
        $conn->set_charset('utf8mb4'); // evita corrupción de acentos al importar
        run_sql_file($conn, __DIR__ . '/database/schema.sql', $log, $errors);
        $conn->select_db(DB_NAME);
        run_sql_file($conn, __DIR__ . '/database/seed.sql', $log, $errors);
        $conn->close();
        $done = empty($errors);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalador · LONDRES Casa de Novias</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>body{font-family:Inter,system-ui,sans-serif} h1,h2{font-family:'Playfair Display',serif}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#0B0B0C] to-[#2a0a0e] flex items-center justify-center p-4">
<div class="w-full max-w-xl rounded-3xl bg-white p-8 shadow-2xl">
    <div class="mb-6 flex items-center gap-3">
        <img src="public/assets/img/logo-mark.svg" class="h-12 w-auto" alt="">
        <div>
            <h1 class="text-2xl font-bold tracking-[0.18em] text-[#0B0B0C]">LONDRES</h1>
            <p class="-mt-1 text-sm text-[#C8102E]">Casa de Novias · Instalador</p>
        </div>
    </div>

    <?php if ($done): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800">
            <h2 class="text-lg font-semibold">✓ Instalación completada</h2>
            <ul class="mt-2 list-disc pl-5 text-sm"><?php foreach ($log as $l) echo '<li>' . htmlspecialchars($l) . '</li>'; ?></ul>
        </div>
        <div class="mt-5 rounded-2xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
            <strong>Importante:</strong> elimine <code>install.php</code> ahora por seguridad.
        </div>
        <div class="mt-6 flex gap-3">
            <a href="public/index.php" class="flex-1 rounded-xl border border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">Ver sitio público</a>
            <a href="admin/login.php" class="flex-1 rounded-xl bg-[#C8102E] px-4 py-3 text-center text-sm font-semibold text-white hover:bg-red-700">Ir al panel</a>
        </div>
        <p class="mt-4 text-center text-xs text-gray-400">Acceso demo: admin@londresnovias.com / Admin12345</p>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <strong>Se produjeron errores:</strong>
                <ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul>
            </div>
        <?php endif; ?>
        <p class="text-sm text-gray-600">Este asistente creará la base de datos <code class="rounded bg-gray-100 px-1"><?= htmlspecialchars(DB_NAME) ?></code> e importará el esquema y los datos de prueba.</p>
        <dl class="mt-4 grid grid-cols-2 gap-2 rounded-2xl bg-gray-50 p-4 text-sm">
            <dt class="text-gray-400">Host</dt><dd class="text-right font-medium"><?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></dd>
            <dt class="text-gray-400">Usuario</dt><dd class="text-right font-medium"><?= htmlspecialchars(DB_USER) ?></dd>
            <dt class="text-gray-400">Base de datos</dt><dd class="text-right font-medium"><?= htmlspecialchars(DB_NAME) ?></dd>
        </dl>
        <p class="mt-3 text-xs text-gray-400">Edita las credenciales en <code>app/config/database.php</code> si difieren.</p>
        <form method="post" class="mt-6">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="w-full rounded-xl bg-[#C8102E] px-4 py-3 text-sm font-semibold text-white hover:bg-red-700">Instalar ahora</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
