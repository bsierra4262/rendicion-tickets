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
        $savedId = comp_save($clean, $editing ? $id : null, current_user_id() ?? 0, $opts);

        // Respaldo automático en Google Drive (si está conectado). Envuelto en
        // try/catch: la copia local ya está guardada, así que esto NUNCA rompe
        // el guardado del comprobante.
        if (!empty($opts['imagen_path']) && function_exists('drive_conectado') && drive_conectado()) {
            try {
                $cInfo = comp_find($savedId);
                if ($cInfo) {
                    $up = drive_subir_comprobante($cInfo, (string)$opts['imagen_path']);
                    if ($up) {
                        q('UPDATE comprobantes SET drive_link = ?, drive_file_id = ? WHERE id = ?',
                            [$up['link'], $up['id'], $savedId]);
                        if (function_exists('log_app')) {
                            log_app('drive.subida', ['entidad' => 'comprobante', 'entidad_id' => $savedId,
                                'detalle' => 'Imagen respaldada en Google Drive']);
                        }
                    } elseif (function_exists('log_app')) {
                        log_app('drive.fallo', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => $savedId,
                            'detalle' => 'No se pudo respaldar en Drive; queda la copia local']);
                    }
                }
            } catch (\Throwable $e) {
                if (function_exists('log_error')) {
                    log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine());
                }
            }
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
