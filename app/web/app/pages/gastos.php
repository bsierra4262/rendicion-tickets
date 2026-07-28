<?php
declare(strict_types=1);
require_login();

$f = [
    'periodo'      => trim((string)($_GET['periodo'] ?? '')),
    'proveedor_id' => (int)($_GET['proveedor_id'] ?? 0),
    'estado'       => trim((string)($_GET['estado'] ?? '')),
];

if (is_post()) {
    verify_csrf();
    $a = $_POST['accion'] ?? '';
    if ($a === 'eliminar')  { gr_delete((int)($_POST['id'] ?? 0));  flash('Gasto archivado.', 'success'); }
    elseif ($a === 'restaurar') { gr_restore((int)($_POST['id'] ?? 0)); flash('Gasto restaurado.', 'success'); }
    redirect('gastos');
}

render('gastos', [
    'rows'        => gr_list($f),
    'f'           => $f,
    'proveedores' => gp_list(),
], 'Gastos recurrentes');
