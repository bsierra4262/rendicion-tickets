<?php
declare(strict_types=1);
require_login();

header('Content-Type: application/json; charset=utf-8');
if (!is_post()) { echo json_encode(['ok' => false]); exit; }
verify_csrf();

$accion  = mb_substr(trim((string)($_POST['accion'] ?? 'cliente')), 0, 120);
$detalle = mb_substr(trim((string)($_POST['detalle'] ?? '')), 0, 500);
$tipo    = in_array($_POST['tipo'] ?? 'accion', ['accion', 'error', 'sistema', 'nav'], true) ? (string)$_POST['tipo'] : 'accion';
if ($accion === '') { $accion = 'cliente'; }

if (function_exists('log_app')) {
    log_app($accion, ['tipo' => $tipo, 'entidad' => 'ocr', 'detalle' => $detalle]);
}
echo json_encode(['ok' => true]);
