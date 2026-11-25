<?php
declare(strict_types=1);

// Pantalla de ingreso (pública). No hay usuario y contraseña: con ITHub
// configurado (ithub.php) se manda directo a la sesión única; si no, la vista
// ofrece «Ingresar con Google Workspace».

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

// Con ITHub, el login no tiene pantalla: se va directo a la sesión única y,
// si ya estaba abierta, se entra sin ver nada. La pantalla se muestra sólo
// cuando hay algo que explicar (un aviso, un cierre por inactividad o un
// error que volvió del callback con ?manual=1); ahí se entra con un botón.
if (ithub_habilitado() && $expiro === '' && !isset($_GET['manual']) && empty($_SESSION['_flash'])) {
    redirect('ithub-login');
}

// Acceso 100% Google Workspace o ITHub (sin login por contraseña).
render('login', [], 'Ingresar');
