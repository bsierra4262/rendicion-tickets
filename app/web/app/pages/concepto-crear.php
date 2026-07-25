<?php
declare(strict_types=1);

// Alta de categoría al vuelo desde el combobox (JSON). Requiere sesión + CSRF.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}
if (!isset($_POST['_csrf'], $_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf'])) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido. Recargá la página.']);
    exit;
}

$res = concepto_create((string)($_POST['nombre'] ?? ''));
echo json_encode($res, JSON_UNESCAPED_UNICODE);
