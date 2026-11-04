<?php
declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$c = $id ? comp_find($id) : null;
if (!$c) {
    http_response_code(404);
    exit('No encontrada');
}

// Copia PENDIENTE de subir a Drive (Drive había fallado): es la imagen más nueva.
$pendiente = function_exists('drive_es_pendiente') && drive_es_pendiente($c['imagen_path'] ?? null);

// Preferido: servir desde Google Drive (los comprobantes nuevos no tienen copia local).
if (!$pendiente && !empty($c['drive_file_id']) && function_exists('drive_descargar')) {
    $f = drive_descargar((string)$c['drive_file_id']);
    if ($f) {
        header('Content-Type: ' . $f['mime']);
        header('Content-Length: ' . strlen($f['body']));
        header('Cache-Control: private, max-age=3600');
        echo $f['body'];
        exit;
    }
    // Si Drive no responde, se intenta la copia local (si existiera).
}

// Copia local: pendiente de subir a Drive, o comprobantes viejos con imagen local.
if (!empty($c['imagen_path'])) {
    $real = realpath(STORAGE . '/uploads/' . $c['imagen_path']);
    $base = realpath(STORAGE . '/uploads');
    if ($real && $base && strpos($real, $base) === 0 && is_file($real)) {
        $mime = function_exists('mime_content_type') ? (mime_content_type($real) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Cache-Control: private, max-age=3600');
        readfile($real);
        exit;
    }
}

http_response_code(404);
exit('No encontrada');
