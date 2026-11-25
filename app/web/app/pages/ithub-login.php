<?php
declare(strict_types=1);

// ============================================================
//  Ruta: GET ithub-login (pública). Manda a ITHub a identificarse.
//
//  Si la persona ya tiene la sesión única abierta, ITHub devuelve el código
//  al instante y entra sin ver la pantalla de ingreso. La vuelta la atiende
//  ithub-callback.php (ver ithub.php).
// ============================================================

if (!ithub_habilitado()) {
    redirect('login');
}
if (auth_user()) {
    redirect('dashboard');
}
header('Location: ' . ithub_url_autorizacion());
exit;
