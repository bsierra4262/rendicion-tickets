<?php
declare(strict_types=1);

if (auth_user()) {
    redirect('dashboard');
}

// Aviso si la sesión se cerró sola (inactividad / sesión máxima).
$expiro = preg_replace('/[^a-z]/', '', (string)($_GET['expiro'] ?? ''));
if ($expiro === 'inactividad') {
    flash('Cerramos tu sesión por inactividad. Volvé a ingresar.', 'info');
} elseif ($expiro === 'expiro') {
    flash('Tu sesión alcanzó el tiempo máximo permitido. Volvé a ingresar.', 'info');
}

// Acceso 100% Google Workspace (sin login por contraseña).
render('login', [], 'Ingresar');
