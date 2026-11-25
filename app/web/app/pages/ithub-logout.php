<?php
declare(strict_types=1);

// ============================================================
//  Aviso de cierre de ITHub (OpenID Connect back-channel logout).
//
//  Ruta: POST ithub-logout (pública: llama el servidor de ITHub, no un
//  navegador, así que no hay cookie ni CSRF; lo que se valida es la firma del
//  logout_token). Anota el `sid` como cerrado y auth_user() corta en su
//  pedido siguiente toda sesión de la app abierta con él.
//
//  200 si se procesó, 400 si el token no es válido (lo pide el estándar).
// ============================================================

header('Cache-Control: no-store');
if (!ithub_habilitado() || !is_post()) {
    http_response_code(400);
    exit;
}

try {
    $c = ithub_validar_jwt((string)($_POST['logout_token'] ?? ''), null, true);
} catch (Throwable $e) {
    log_app('ithub.logout_invalido', ['tipo' => 'error', 'actor' => 'sistema',
        'detalle' => 'logout_token rechazado: ' . $e->getMessage()]);
    http_response_code(400);
    exit;
}

$sid = (string)($c['sid'] ?? '');
if ($sid !== '') {
    ithub_cerrar_sid($sid);
}
// El logout_token no trae el email (sólo `sub`, el id de ITHub): la salida de
// la persona queda anotada cuando su sesión cae en auth_user().
log_app('ithub.aviso_cierre', ['tipo' => 'auth', 'actor' => 'ITHub',
    'detalle' => 'ITHub avisó el cierre de una sesión única']);
http_response_code(200);
exit;
