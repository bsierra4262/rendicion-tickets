<?php
declare(strict_types=1);

// Ruta: GET google-login (pública). Manda a Google.
//  · Sin ITHub es el login (y de paso conecta Drive, ver google_auth_url()).
//  · Con ITHub, Google ya no es el login: es «Conectar Google Drive» de quien
//    ya está adentro (lo dispara ithub-callback y el aviso del layout).

if (ithub_habilitado()) {
    if (!auth_user()) {
        redirect('login');
    }
    if (drive_conectado()) {
        redirect('dashboard');
    }
} elseif (auth_user()) {
    redirect('dashboard');
}
if (!google_enabled()) {
    flash('El acceso con Google todavía no está configurado.', 'error');
    redirect(auth_user() ? 'dashboard' : 'login');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
header('Location: ' . google_auth_url($state));
exit;
