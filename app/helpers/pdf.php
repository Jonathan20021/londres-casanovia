<?php
/**
 * Generación de PDF (Dompdf) con degradación elegante.
 * LONDRES Casa de Novias
 *
 * Si Dompdf está instalado (composer require dompdf/dompdf) genera un PDF
 * descargable en el servidor. Si no, devuelve el mismo HTML autocontenido
 * (CSS en línea) con auto-impresión para "Guardar como PDF" desde el navegador.
 */
declare(strict_types=1);

/** ¿Está disponible Dompdf? */
function pdf_available(): bool
{
    return class_exists('Dompdf\\Dompdf');
}

/** Data-URI del logo (cabina) para incrustarlo en los PDF/plantillas. */
function pdf_logo_uri(): string
{
    static $uri = null;
    if ($uri !== null) return $uri;
    $path = LCN_ROOT . '/public/assets/img/logo-mark.svg';
    $uri = is_file($path)
        ? 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($path))
        : '';
    return $uri;
}

/**
 * Genera y descarga un PDF a partir de HTML autocontenido (CSS en línea).
 * Si Dompdf no está disponible, sirve el HTML con auto-impresión.
 * Esta función finaliza la petición (exit).
 *
 * @param string $html      Documento HTML completo con estilos en línea
 * @param string $filename  Nombre del archivo sin extensión
 */
function render_pdf(string $html, string $filename): void
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'documento';

    if (pdf_available()) {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // soporta acentos (UTF-8)

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
        exit;
    }

    // Fallback sin Dompdf: el mismo HTML, con impresión automática del navegador.
    echo str_replace(
        '</body>',
        "<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},350);});</script></body>",
        $html
    );
    exit;
}
