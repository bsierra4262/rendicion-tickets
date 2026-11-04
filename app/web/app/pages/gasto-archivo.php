<?php
declare(strict_types=1);
// Sirve la factura o el pago de un gasto (proxy desde Google Drive) para poder RE-LEERLOS
// al editar sin volver a subir el archivo. Si Drive había fallado, sirve la copia
// PENDIENTE guardada en el servidor (es la más nueva).
$id   = (int)($_GET['id'] ?? 0);
$tipo = (($_GET['tipo'] ?? 'factura') === 'pago') ? 'pago' : 'factura';
$g = $id ? gr_find($id) : null;
if (!$g) { http_response_code(404); exit('No encontrado'); }

$local = function_exists('drive_local_abs') ? drive_local_abs($g[$tipo . '_path'] ?? null) : null;
if ($local) {
    $mime = function_exists('mime_content_type') ? (mime_content_type($local) ?: 'application/octet-stream') : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($local));
    header('Cache-Control: private, max-age=300');
    readfile($local);
    exit;
}

$fileId = (string)($g['drive_' . $tipo . '_id'] ?? '');
if ($fileId !== '' && function_exists('drive_descargar')) {
    $f = drive_descargar($fileId);
    if ($f) {
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . strlen($f['body']));
        header('Cache-Control: private, max-age=3600');
        echo $f['body'];
        exit;
    }
}
http_response_code(404);
exit('No disponible');
