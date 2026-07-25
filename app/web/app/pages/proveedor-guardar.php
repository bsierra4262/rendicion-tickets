<?php
declare(strict_types=1);
require_login();

if (!is_post()) { redirect('proveedores'); }
verify_csrf();

$id  = (int)($_POST['id'] ?? 0);
$cat = (int)($_POST['categoria_sugerida_id'] ?? 0);
$r = prov_admin_save($id, (string)($_POST['cuit'] ?? ''), (string)($_POST['razon_social'] ?? ''), $cat ?: null);

if ($r['ok']) {
    $msg = $id ? 'Proveedor actualizado.' : ('Proveedor ' . (!empty($r['existia']) ? 'ya existía (actualizado).' : 'creado.'));
    flash($msg, 'success');
} else {
    flash($r['error'], 'error');
}
redirect('proveedores');
