<?php
/**
 * Subida segura de imágenes.
 * LONDRES Casa de Novias
 */
declare(strict_types=1);

const UPLOAD_ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp'];
const UPLOAD_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
const UPLOAD_MAX_BYTES    = 5242880; // 5 MB

/**
 * Procesa un único archivo de $_FILES y lo guarda.
 *
 * @param array  $file   Elemento de $_FILES (con keys name,tmp_name,error,size)
 * @param string $subdir Subcarpeta dentro de /public/assets/uploads (ej. products)
 * @return array{ok:bool, path:?string, error:?string}
 *         path se guarda relativo a /assets, ej. "uploads/products/abc.jpg"
 */
function upload_image(array $file, string $subdir = 'products'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Parámetro de archivo inválido.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => null, 'error' => 'No se seleccionó ningún archivo.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Error al subir el archivo (código ' . $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'path' => null, 'error' => 'La imagen supera el tamaño máximo (5 MB).'];
    }

    // Validar extensión
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        return ['ok' => false, 'path' => null, 'error' => 'Formato no permitido. Use JPG, PNG o WEBP.'];
    }

    // Validar MIME real
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
        return ['ok' => false, 'path' => null, 'error' => 'El contenido del archivo no es una imagen válida.'];
    }

    // Confirmar que es imagen real
    if (@getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'path' => null, 'error' => 'El archivo no es una imagen válida.'];
    }

    // Crear carpeta destino
    $targetDir = UPLOADS_PATH . '/' . trim($subdir, '/');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'path' => null, 'error' => 'No se pudo crear la carpeta de destino.'];
    }

    // Nombre seguro y aleatorio
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest    = $targetDir . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        // Fallback para CLI/tests
        if (!@rename($file['tmp_name'], $dest)) {
            return ['ok' => false, 'path' => null, 'error' => 'No se pudo guardar la imagen.'];
        }
    }
    @chmod($dest, 0644);

    return ['ok' => true, 'path' => 'uploads/' . trim($subdir, '/') . '/' . $newName, 'error' => null];
}

/**
 * Procesa múltiples archivos (input name="images[]").
 * @return array lista de rutas exitosas
 */
function upload_images(array $files, string $subdir = 'products'): array
{
    $paths = [];
    if (empty($files['name']) || !is_array($files['name'])) {
        return $paths;
    }
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $single = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $res = upload_image($single, $subdir);
        if ($res['ok']) {
            $paths[] = $res['path'];
        }
    }
    return $paths;
}

/** Elimina físicamente una imagen subida (ruta relativa a /assets). */
function delete_upload(?string $relativePath): void
{
    if (empty($relativePath)) return;
    $full = LCN_ROOT . '/public/assets/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}
