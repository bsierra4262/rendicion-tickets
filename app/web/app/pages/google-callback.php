<?php
declare(strict_types=1);

// Vuelta de Google (google-callback). Sin ITHub hace el login completo. Con
// ITHub configurado, el login lo hace ITHub y esto queda sólo para conectar
// Google Drive de quien ya está adentro (paso 4'), con la misma cuenta.

$conIthub = ithub_habilitado();
if ($conIthub && !auth_user()) {
    redirect('login');
}
if (!$conIthub && auth_user()) {
    redirect('dashboard');
}
$volver = $conIthub ? 'dashboard' : 'login';

// 1) Validar state (anti-CSRF)
$state = $_GET['state'] ?? '';
$expected = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);
if ($state === '' || !hash_equals($expected, $state)) {
    flash('La validación de seguridad con Google falló. Reintentá.', 'error');
    redirect($volver);
}

if (isset($_GET['error'])) {
    flash($conIthub
        ? 'No se conectó Google Drive: los archivos que cargues quedan en el servidor y se suben solos cuando lo conectes.'
        : 'Se canceló el acceso con Google.', $conIthub ? 'info' : 'error');
    redirect($volver);
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    redirect($volver);
}

// 2) Intercambiar code por tokens
$tok = google_exchange_code($code);
if (empty($tok['id_token']) && empty($tok['access_token'])) {
    flash('No se pudo validar la cuenta de Google.', 'error');
    redirect($volver);
}

// 3) Obtener identidad (desde el id_token, recibido directo de Google por TLS)
$claims = !empty($tok['id_token']) ? google_decode_id_token($tok['id_token']) : [];
$email    = strtolower((string) ($claims['email'] ?? ''));
$verified = (bool) ($claims['email_verified'] ?? false);
$hd       = $claims['hd'] ?? null;
$nombre   = (string) ($claims['name'] ?? $email);
$picture  = (string) ($claims['picture'] ?? '');

if ($email === '' || !$verified) {
    flash('La cuenta de Google no tiene un email verificado.', 'error');
    redirect($volver);
}

// 4) (Opcional) restringir por dominio de la organización
$g = google_cfg();
if (!empty($g['allowed_domain']) && $hd !== $g['allowed_domain']) {
    flash('Tu cuenta no pertenece al dominio autorizado de la organización.', 'error');
    redirect($volver);
}

// 4') Con ITHub, esto no es un login: es «Conectar Google Drive» de alguien
//     que ya está adentro. Se exige que la cuenta de Google sea la misma de la
//     sesión (si no, el Drive de otra persona quedaría colgado de esta sesión)
//     y no se toca la sesión ni el usuario (nombre, foto y rol vienen de ITHub).
if ($conIthub) {
    $yo = auth_user();
    if (strtolower((string)$yo['email']) !== $email) {
        if (!empty($tok['refresh_token']) || !empty($tok['access_token'])) {
            // El permiso que dio la otra cuenta no se usa: se revoca enseguida.
            drive_http('POST', 'https://oauth2.googleapis.com/revoke',
                http_build_query(['token' => (string)($tok['refresh_token'] ?? $tok['access_token'])]),
                ['Content-Type: application/x-www-form-urlencoded'], 4);
        }
        flash('Elegiste otra cuenta de Google (' . $email . '). Conectá Drive con la tuya: ' . $yo['email'] . '.', 'error');
        redirect('dashboard');
    }
    if (empty($tok['refresh_token']) || strpos((string)($tok['scope'] ?? ''), DRIVE_SCOPE) === false) {
        flash('Google no dio permiso para Drive (se destildó el acceso). Los archivos quedan en el servidor '
            . 'hasta que vuelvas a conectar y dejes tildado el acceso a Drive.', 'error');
        redirect('dashboard');
    }
    drive_guardar_sesion($tok);
    log_app('drive.conectar', ['tipo' => 'auth', 'detalle' => 'Drive conectado en la sesión de ' . $email]);
    try { drive_sincronizar_pendientes(5); } catch (\Throwable $e) {}
    redirect('dashboard');
}

// 5) Buscar o crear el usuario
$user = find_user_by_email($email);
if (!$user) {
    if (!empty($g['auto_provision'])) {
        $id = provision_user($email, $nombre, 'carga');
        $user = ['id' => $id, 'activo' => 1];
    } else {
        flash('Tu usuario no está habilitado. Pedile al administrador que te dé de alta.', 'error');
        redirect('login');
    }
}
if ((int) $user['activo'] !== 1) {
    flash('Tu usuario está inactivo. Contactá al administrador.', 'error');
    redirect('login');
}

// Refrescar nombre y foto de perfil desde Google en cada login (tiempo real)
q('UPDATE usuarios SET nombre = ?, avatar_url = ? WHERE id = ?',
    [$nombre !== '' ? $nombre : $email, $picture !== '' ? $picture : null, (int) $user['id']]);

// Portal único + seguridad por sesión: si el login trajo acceso offline a Drive
// (refresh_token), lo guardamos en la SESIÓN. Vive solo mientras dure la sesión;
// al desloguear o al cierre automático se borra (ver auth_logout / bootstrap).
if (function_exists('drive_guardar_sesion') && !empty($tok['refresh_token'])) {
    drive_guardar_sesion($tok);
    if (function_exists('log_app')) {
        log_app('drive.conectar', ['tipo' => 'auth', 'detalle' => 'Drive conectado en la sesión de ' . $email]);
    }
}

auth_login_user((int) $user['id']);
// Si hay archivos que quedaron en el servidor porque Drive había fallado, se suben ahora.
if (function_exists('drive_sincronizar_pendientes')) {
    try { drive_sincronizar_pendientes(5); } catch (\Throwable $e) {}
}
redirect('dashboard');
