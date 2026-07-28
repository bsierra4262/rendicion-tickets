<?php
declare(strict_types=1);
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$v = $editing ? gr_find($id) : ['periodo' => date('Y-m'), 'moneda' => 'ARS', 'estado' => 'pendiente'];
if ($editing && !$v) { http_response_code(404); require APP . '/pages/404.php'; exit; }

$errors = [];
if (is_post()) {
    verify_csrf();
    [$errors, $c] = gr_validar($_POST);
    if (!$errors) {
        $savedId = gr_save($c, $editing ? $id : null, current_user_id() ?? 0);
        // Subida a Drive (factura + pago) — estructura Proveedor/Año/Mes. Auditado.
        $g = gr_find($savedId);
        if ($g) {
            if (!empty($_FILES['factura']['name'])) { drive_gasto_subir($savedId, $g, 'factura', $_FILES['factura']); }
            if (!empty($_FILES['pago']['name']))    { drive_gasto_subir($savedId, $g, 'pago', $_FILES['pago']); }
        }
        flash($editing ? 'Gasto actualizado.' : 'Gasto cargado.', 'success');
        redirect('gastos');
    }
    $v = array_merge((array)$v, $_POST);
}

render('gasto-form', ['editing' => $editing, 'id' => $id, 'v' => $v, 'errors' => $errors, 'proveedores' => gp_list()],
    $editing ? 'Editar gasto' : 'Nuevo gasto');
