<?php
declare(strict_types=1);

// Aislamiento cross-origin → habilita WASM multi-hilo en onnxruntime-web (OCR más rápido,
// se puede usar alta resolución). 'credentialless' NO rompe recursos cross-origin públicos
// (avatar de Google, fuentes, CDNs) porque se cargan sin credenciales.
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Embedder-Policy: credentialless');

$errors = [];
$v = [];
if (is_post()) {
    verify_csrf();
    [$errors, $clean] = valida_comprobante($_POST);
    // Borrador OCR (pendiente de revisión): el desajuste de total NO bloquea — es común en
    // tickets de súper con varias tasas de IVA y se ajusta en la revisión.
    unset($errors['importe_total']);
    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if (!$errors) {
        $opts = ['estado' => 'pendiente_revision', 'origen' => 'ocr'];
        if (!empty($_POST['ocr_text'])) {
            $opts['ocr_text'] = mb_substr((string)$_POST['ocr_text'], 0, 20000);
        }
        $savedId = comp_save($clean, null, current_user_id() ?? 0, $opts);
        // Imagen: SOLO a Google Drive (sin copia local). Todo queda auditado en el CRON.
        if (!empty($_FILES['imagen']['name'])) {
            drive_procesar_imagen($savedId, $clean, $_FILES['imagen']);
        }
        flash('Ticket escaneado y guardado como pendiente de revisión.', 'success');
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => url('comprobantes?estado=pendiente_revision')]); exit; }
        redirect('comprobantes?estado=pendiente_revision');
    }
    // Con errores: por AJAX devolvemos el detalle SIN recargar (así NO se pierde la imagen).
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'errors' => $errors]); exit; }
    $v = $_POST;
}

render('escanear', [
    'v'         => $v,
    'errors'    => $errors,
    'conceptos' => conceptos_list(),
], 'Escanear ticket');
