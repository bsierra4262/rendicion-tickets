<?php
declare(strict_types=1);

// ---- Conexión de Google Drive (el admin ya está logueado) ----
// Se distingue del login por el prefijo del state ("drive:"). Va ANTES del
// redirect de sesión iniciada porque en este flujo el usuario SÍ está logueado.
if (strncmp((string)($_GET['state'] ?? ''), 'drive:', 6) === 0) {
    require_admin();
    $state    = (string)$_GET['state'];
    $expected = (string)($_SESSION['drive_state'] ?? '');
    unset($_SESSION['drive_state']);
    if ($expected === '' || !hash_equals($expected, $state)) {
        flash('La validación de seguridad con Google falló. Reintentá la conexión.', 'error');
        redirect('drive');
    }
    if (isset($_GET['error'])) {
        flash('Se canceló la conexión con Google Drive.', 'error');
        redirect('drive');
    }
    $code = (string)($_GET['code'] ?? '');
    if ($code === '') {
        redirect('drive');
    }
    $tok = drive_exchange_code($code);
    if (empty($tok['refresh_token'])) {
        if (function_exists('log_error')) {
            log_error('drive.sin_refresh', 'sin refresh_token: ' . json_encode($tok), __FILE__, __LINE__);
        }
        flash('Google no devolvió un token permanente. Entrá a tu cuenta → Seguridad → "Acceso de terceros", quitá esta app y reintentá.', 'error');
        redirect('drive');
    }
    setting_set('drive_refresh_token', (string)$tok['refresh_token']);
    if (!empty($tok['access_token'])) {
        setting_set('drive_access_token', (string)$tok['access_token']);
        setting_set('drive_access_expira', (string)(time() + (int)($tok['expires_in'] ?? 3600)));
    }
    $u = auth_user();
    setting_set('drive_conectado_por', (string)($u['email'] ?? ''));
    setting_set('drive_conectado_en', date('Y-m-d H:i:s'));
    if (function_exists('log_app')) {
        log_app('drive.conectar', ['tipo' => 'auth', 'detalle' => 'Google Drive conectado por ' . ($u['email'] ?? '')]);
    }
    flash('¡Google Drive conectado! Las imágenes nuevas se respaldan automáticamente.', 'success');
    redirect('drive');
}

if (auth_user()) {
    redirect('dashboard');
}

// 1) Validar state (anti-CSRF)
$state = $_GET['state'] ?? '';
$expected = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state']);
if ($state === '' || !hash_equals($expected, $state)) {
    flash('La validación de seguridad con Google falló. Reintentá.', 'error');
    redirect('login');
}

if (isset($_GET['error'])) {
    flash('Se canceló el acceso con Google.', 'error');
    redirect('login');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    redirect('login');
}

// 2) Intercambiar code por tokens
$tok = google_exchange_code($code);
if (empty($tok['id_token']) && empty($tok['access_token'])) {
    flash('No se pudo validar la cuenta de Google.', 'error');
    redirect('login');
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
    redirect('login');
}

// 4) (Opcional) restringir por dominio de la organización
$g = google_cfg();
if (!empty($g['allowed_domain']) && $hd !== $g['allowed_domain']) {
    flash('Tu cuenta no pertenece al dominio autorizado de la organización.', 'error');
    redirect('login');
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

// Portal único: si el login trajo acceso offline a Drive (refresh_token) y Drive
// todavía no está conectado, lo dejamos conectado automáticamente en este mismo paso.
if (function_exists('drive_conectado') && !drive_conectado() && !empty($tok['refresh_token'])) {
    setting_set('drive_refresh_token', (string) $tok['refresh_token']);
    if (!empty($tok['access_token'])) {
        setting_set('drive_access_token', (string) $tok['access_token']);
        setting_set('drive_access_expira', (string) (time() + (int) ($tok['expires_in'] ?? 3600)));
    }
    setting_set('drive_conectado_por', $email);
    setting_set('drive_conectado_en', date('Y-m-d H:i:s'));
    if (function_exists('log_app')) {
        log_app('drive.conectar', ['tipo' => 'auth', 'detalle' => 'Google Drive conectado desde el login por ' . $email]);
    }
}

auth_login_user((int) $user['id']);
redirect('dashboard');
