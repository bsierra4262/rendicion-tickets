<?php
declare(strict_types=1);

require_admin();

if (is_post()) {
    verify_csrf();
    if (($_POST['accion'] ?? '') === 'restaurar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            comp_restore($id);
            flash('Comprobante restaurado. La imagen volvió a su carpeta en Drive.', 'success');
        }
    }
    redirect('papelera');
}

render('papelera', ['rows' => comp_eliminados()], 'Papelera');
