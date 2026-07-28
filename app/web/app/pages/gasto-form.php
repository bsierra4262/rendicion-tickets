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
        $subeFactura = !empty($_FILES['factura']['name']);
        $subePago    = !empty($_FILES['pago']['name']);
        $files = $_FILES; // capturar antes de cerrar la request

        $msg = $editing ? 'Gasto actualizado.' : 'Gasto cargado.';
        if ($subeFactura || $subePago) { $msg .= ' La factura/pago se están respaldando en Drive.'; }
        flash($msg, 'success');

        // Respondemos el redirect YA y subimos a Drive en SEGUNDO PLANO: el usuario
        // no espera la subida (evita el "queda cargando" con max_execution_time=0).
        if ($subeFactura || $subePago) {
            header('Location: ' . url('gastos'), true, 302);
            finish_request();
            $g = gr_find($savedId);
            if ($g) {
                if ($subeFactura) { drive_gasto_subir($savedId, $g, 'factura', $files['factura']); }
                if ($subePago)    { drive_gasto_subir($savedId, $g, 'pago', $files['pago']); }
            }
            exit;
        }
        redirect('gastos');
    }
    $v = array_merge((array)$v, $_POST);
}

render('gasto-form', ['editing' => $editing, 'id' => $id, 'v' => $v, 'errors' => $errors, 'proveedores' => gp_list()],
    $editing ? 'Editar gasto' : 'Nuevo gasto');
