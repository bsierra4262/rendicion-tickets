<?php
declare(strict_types=1);
require_admin();

render('usuarios', [
    'usuarios' => users_all(),
    'me'       => current_user_id(),
], 'Usuarios');
