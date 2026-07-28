<?php
declare(strict_types=1);
require_login();

if (is_post()) {
    verify_csrf();
    $a = $_POST['accion'] ?? '';
    if ($a === 'eliminar') {
        gp_delete((int)($_POST['id'] ?? 0));
        flash('Proveedor archivado. Lo podés recuperar abajo.', 'success');
    } elseif ($a === 'restaurar') {
        gp_restore((int)($_POST['id'] ?? 0));
        flash('Proveedor restaurado.', 'success');
    } elseif ($a === 'sembrar') {
        $n = gp_seed();
        flash($n > 0 ? "Se agregaron $n proveedores recurrentes." : 'Los proveedores ya estaban cargados.', 'success');
    }
    redirect('gproveedores');
}

render('gproveedores', [
    'rows'       => gp_all(),
    'eliminados' => gp_deleted(),
], 'Proveedores');
