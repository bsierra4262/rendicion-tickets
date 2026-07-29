<?php
declare(strict_types=1);
require_admin();

$nueva = null;
if (is_post()) {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            flash('Poné un nombre para identificar el token.', 'error');
            redirect('api-keys');
        }
        $scope = ($_POST['scope'] ?? 'lectura') === 'root' ? 'root' : 'lectura';
        $nueva = api_token_create($nombre, current_user_id() ?? 0, $scope);
        // no redirect: mostramos el token una sola vez
    } elseif ($accion === 'toggle') {
        api_token_set_active((int)($_POST['id'] ?? 0), (int)($_POST['activo'] ?? 0) === 1);
        redirect('api-keys');
    } elseif ($accion === 'borrar') {
        api_token_delete((int)($_POST['id'] ?? 0));
        flash('Token eliminado.', 'success');
        redirect('api-keys');
    }
}

render('api-keys', ['tokens' => api_tokens_all(), 'nueva' => $nueva], 'API');
