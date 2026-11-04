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
    // ¿Gasto recurrente o Factura Compra única? (sólo al CARGAR uno nuevo) Si el CUIT es de un
    // proveedor de Gastos recurrentes, se guarda como GASTO y no como comprobante.
    $gp = !$editing ? gp_find_by_cuit((string)($_POST['cuit'] ?? '')) : null;
    if ($gp) {
        [$errors, $gid, $accion] = gr_desde_factura($_POST, $gp, current_user_id() ?? 0, $_FILES['imagen'] ?? []);
        if (!$errors) {
            flash('Es un gasto recurrente de ' . $gp['nombre'] . ': se ' . ($accion === 'completado' ? 'completó el gasto' : 'creó el gasto')
                . ' del período en Gastos. Revisá el vencimiento y guardá.', 'success');
            redirect('gasto-form?id=' . $gid);
        }
        $clean = [];
    } else {
        [$errors, $clean] = valida_comprobante($_POST);
    }
    if (!$errors) {
        $opts = [];
        $act = $_POST['estado_action'] ?? '';
        if ($act === 'verificar')      { $opts['estado'] = 'verificado'; }
        elseif ($act === 'rechazar')   { $opts['estado'] = 'rechazado'; }
        elseif ($act === 'pendiente')  { $opts['estado'] = 'pendiente_revision'; }
        $savedId = comp_save($clean, $editing ? $id : null, current_user_id() ?? 0, $opts);

        // Imagen a Google Drive. Si Drive falla, queda PENDIENTE en el servidor y se sube sola después. Auditado en el CRON.
        if (!empty($_FILES['imagen']['name'])) {
            drive_procesar_imagen($savedId, $clean, $_FILES['imagen']);
        }

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
