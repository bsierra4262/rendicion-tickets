<?php
declare(strict_types=1);
if (function_exists('log_app') && !empty($_SESSION['uid'])) {
    log_app('sesion.salida', ['tipo' => 'auth', 'detalle' => 'Cierre de sesión']);
}
auth_logout();
redirect('login');
