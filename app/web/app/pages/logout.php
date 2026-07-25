<?php
declare(strict_types=1);
$motivo = preg_replace('/[^a-z]/', '', (string)($_GET['expiro'] ?? ''));
if (function_exists('log_app') && !empty($_SESSION['uid'])) {
    log_app('sesion.salida', ['tipo' => 'auth',
        'detalle' => $motivo !== '' ? ('Cierre por ' . ($motivo === 'expiro' ? 'sesión máxima' : 'inactividad')) : 'Cierre de sesión']);
}
auth_logout();
redirect($motivo !== '' ? ('login?expiro=' . $motivo) : 'login');
