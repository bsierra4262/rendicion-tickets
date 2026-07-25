<?php
declare(strict_types=1);

// Keepalive de sesión. El cliente (idle.js) lo llama ante actividad real
// (con throttle) para mantener viva la sesión mientras el usuario trabaja sin
// recargar la página. bootstrap.php ya actualizó last_activity en esta misma
// request y, si la sesión estaba vencida, ya respondió 440 antes de llegar acá.
header('Content-Type: application/json; charset=utf-8');

$u = auth_user();
if (!$u) {
    http_response_code(440);
    echo json_encode(['ok' => false, 'error' => 'sesion_expirada']);
    exit;
}

$now     = time();
$idleTtl = (int)($GLOBALS['CONFIG']['app']['idle_ttl'] ?? 600);
$absTtl  = (int)($GLOBALS['CONFIG']['app']['abs_ttl']  ?? 28800);
$absRem  = max(0, $absTtl - ($now - (int)($_SESSION['login_time'] ?? $now)));

echo json_encode(['ok' => true, 'idle' => $idleTtl, 'abs_rem' => $absRem]);
