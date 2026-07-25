<?php
declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$c = $id ? comp_find($id) : null;
if (!$c || empty($c['imagen_path'])) {
    http_response_code(404);
    exit('No encontrada');
}
$real = realpath(STORAGE . '/uploads/' . $c['imagen_path']);
$base = realpath(STORAGE . '/uploads');
if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('No encontrada');
}
$mime = function_exists('mime_content_type') ? (mime_content_type($real) ?: 'application/octet-stream') : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=3600');
readfile($real);
exit;
