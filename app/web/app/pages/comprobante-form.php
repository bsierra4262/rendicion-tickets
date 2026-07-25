<?php
declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$v = [];
$estadoActual = '';
$c = null;

if ($editing) {
    $c = comp_find($id);
    if (!$c) {
        http_response_code(404);
        require APP . '/pages/404.php';
        exit;
    }
    $estadoActual = (string)$c['estado'];
    $c['cuit'] = $c['prov_cuit'] ?? '';
    $c['razon_social'] = $c['prov_razon'] ?? '';
    $v = $c;
}

$errors = [];
if (is_post()) {
    verify_csrf();
    [$errors, $clean] = valida_comprobante($_POST);
    if (!$errors) {
        $opts = [];
        if (!empty($_FILES['imagen']['name'])) {
            $ruta = guardar_imagen($_FILES['imagen']);
            if ($ruta) { $opts['imagen_path'] = $ruta; }
        }
        $act = $_POST['estado_action'] ?? '';
        if ($act === 'verificar')      { $opts['estado'] = 'verificado'; }
        elseif ($act === 'rechazar')   { $opts['estado'] = 'rechazado'; }
        elseif ($act === 'pendiente')  { $opts['estado'] = 'pendiente_revision'; }
        comp_save($clean, $editing ? $id : null, current_user_id() ?? 0, $opts);
        flash($editing ? 'Comprobante actualizado.' : 'Comprobante cargado correctamente.', 'success');
        redirect('comprobantes');
    }
    $v = $_POST;
    $v['imagen_path'] = $c['imagen_path'] ?? '';
}

render('comprobante-form', [
    'editing'   => $editing,
    'id'        => $id,
    'v'         => $v,
    'errors'    => $errors,
    'conceptos' => conceptos_list(),
    'estado'    => $estadoActual,
], $editing ? 'Editar comprobante' : 'Nuevo comprobante');
