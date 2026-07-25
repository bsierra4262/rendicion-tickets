<?php
declare(strict_types=1);

if (auth_user()) {
    redirect('dashboard');
}

// Acceso 100% Google Workspace (sin login por contraseña).
render('login', [], 'Ingresar');
