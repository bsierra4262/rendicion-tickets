<?php
declare(strict_types=1);

if (auth_user()) {
    redirect('dashboard');
}
if (!google_enabled()) {
    flash('El acceso con Google todavía no está configurado.', 'error');
    redirect('login');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
header('Location: ' . google_auth_url($state));
exit;
