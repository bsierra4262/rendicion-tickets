<?php
declare(strict_types=1);
// Heartbeat de alertas: lo llama el front cada ~60s mientras la app está abierta. Evalúa las
// condiciones (crea/despacha alertas Email/ITHOS) aunque el usuario esté idle en cualquier
// sección, y devuelve el conteo + la última no leída para actualizar la campana y el popup.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!auth_user()) { echo json_encode(['ok' => false]); exit; }

// Sacar el envío del camino de la respuesta para no demorar el ping.
if (function_exists('finish_request')) {
    // Igual devolvemos el estado ACTUAL antes de evaluar; la evaluación corre después.
}
$admin = (auth_user()['rol'] ?? '') === 'admin';
$out = ['ok' => true, 'n' => 0, 'last' => null];
if ($admin && function_exists('alertas_no_leidas')) {
    $out['n'] = alertas_no_leidas();
    $ult = function_exists('alerta_ultima_no_leida') ? alerta_ultima_no_leida() : null;
    if ($ult) {
        $out['last'] = [
            'id'        => (int)$ult['id'],
            'titulo'    => (string)$ult['titulo'],
            'mensaje'   => mb_strimwidth((string)$ult['mensaje'], 0, 130, '…'),
            'severidad' => (string)$ult['severidad'],
            'url'       => (string)$ult['url'],
        ];
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);

// Evaluar/despachar DESPUÉS de responder (no demora el ping).
if (function_exists('finish_request')) { finish_request(); }
if (function_exists('alertas_autoeval')) { try { alertas_autoeval(60); } catch (\Throwable $e) {} }
