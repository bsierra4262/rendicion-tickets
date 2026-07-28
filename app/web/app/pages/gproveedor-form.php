<?php
declare(strict_types=1);
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$v = $editing ? gp_find($id) : ['categoria' => 'recurrente'];
if ($editing && !$v) { http_response_code(404); require APP . '/pages/404.php'; exit; }

$errors = [];
if (is_post()) {
    verify_csrf();
    [$errors, $c] = gp_validar($_POST);
    if (!$errors) {
        $activo = $editing ? !empty($_POST['activo']) : true;
        $r = gp_save($c, $editing ? $id : null, $activo);
        if ($r['ok']) {
            flash($editing ? 'Proveedor actualizado.' : 'Proveedor creado.', 'success');
            redirect('gproveedores');
        }
        $errors['nombre'] = $r['error'];
    }
    $v = array_merge((array)$v, $_POST);
}

render('gproveedor-form', ['editing' => $editing, 'id' => $id, 'v' => $v, 'errors' => $errors],
    $editing ? 'Editar proveedor' : 'Nuevo proveedor');
