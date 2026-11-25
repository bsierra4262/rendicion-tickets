<?php
declare(strict_types=1);

// Cierre de sesión. `?expiro=inactividad|expiro` lo manda idle.js en el
// cierre automático y pasa al login para que muestre el aviso. Si se entró
// con ITHub, la salida pedida por la persona sigue a /oauth/logout de ITHub
// (cierra la sesión única en todas las apps); el cierre automático no.
$motivo = preg_replace('/[^a-z]/', '', (string)($_GET['expiro'] ?? ''));
if (function_exists('log_app') && !empty($_SESSION['uid'])) {
    log_app('sesion.salida', ['tipo' => 'auth',
        'detalle' => $motivo !== '' ? ('Cierre por ' . ($motivo === 'expiro' ? 'sesión máxima' : 'inactividad')) : 'Cierre de sesión']);
}
$idToken = (string)($_SESSION['ithub_id_token'] ?? '');
$porIthub = ithub_habilitado() && !empty($_SESSION['ithub_sid']);
auth_logout();
if ($porIthub && $motivo === '') {
    header('Location: ' . ithub_url_salida($idToken !== '' ? $idToken : null));
    exit;
}
redirect($motivo !== '' ? ('login?expiro=' . $motivo) : 'login');
