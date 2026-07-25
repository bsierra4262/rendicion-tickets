<?php
declare(strict_types=1);
require_admin();

if (!is_post()) {
    redirect('usuarios');
}
verify_csrf();

$id     = (int)($_POST['id'] ?? 0);
$rol    = ($_POST['rol'] ?? 'carga') === 'admin' ? 'admin' : 'carga';
$activo = !empty($_POST['activo']);

if ($id > 0) {
    // No dejar el sistema sin administradores activos
    $u = q('SELECT rol, activo FROM usuarios WHERE id = ? LIMIT 1', [$id])->fetch();
    if ($u && $u['rol'] === 'admin' && (int)$u['activo'] === 1
        && ($rol !== 'admin' || !$activo) && admins_activos() <= 1) {
        flash('No podés dejar el sistema sin administradores activos.', 'error');
        redirect('usuarios');
    }
    user_update($id, $rol, $activo);
    flash('Usuario actualizado.', 'success');
}
redirect('usuarios');
