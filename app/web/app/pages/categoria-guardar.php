<?php
declare(strict_types=1);
require_login();

if (!is_post()) { redirect('categorias'); }
verify_csrf();

$accion = $_POST['accion'] ?? '';
if ($accion === 'crear') {
    $r = concepto_create((string)($_POST['nombre'] ?? ''));
    if ($r['ok']) {
        flash('Categoría «' . $r['nombre'] . '» ' . (!empty($r['existia']) ? 'ya existía (reactivada).' : 'creada.'), 'success');
    } else {
        flash($r['error'], 'error');
    }
} elseif ($accion === 'editar') {
    $r = concepto_update((int)($_POST['id'] ?? 0), (string)($_POST['nombre'] ?? ''), !empty($_POST['activo']));
    flash($r['ok'] ? 'Categoría actualizada.' : $r['error'], $r['ok'] ? 'success' : 'error');
} elseif ($accion === 'eliminar') {
    concepto_delete((int)($_POST['id'] ?? 0));
    flash('Categoría archivada. La podés recuperar desde la papelera.', 'success');
} elseif ($accion === 'restaurar') {
    concepto_restore((int)($_POST['id'] ?? 0));
    flash('Categoría restaurada.', 'success');
}
redirect('categorias');
