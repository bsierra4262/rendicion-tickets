<?php
declare(strict_types=1);

if (!is_post()) {
    redirect('comprobantes');
}
verify_csrf();
if (!auth_is_admin()) {
    flash('Solo un administrador puede eliminar comprobantes.', 'error');
    redirect('comprobantes');
}
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    comp_delete($id);
    flash('Comprobante eliminado.', 'success');
}
redirect('comprobantes');
