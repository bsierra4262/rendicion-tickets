<?php
declare(strict_types=1);

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
redirect('dashboard');
