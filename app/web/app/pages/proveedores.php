<?php
declare(strict_types=1);
require_login();

$term = trim($_GET['q'] ?? '');
render('proveedores', [
    'proveedores' => proveedores_list($term),
    'conceptos'   => conceptos_list(),
    'total'       => proveedores_total(),
    'term'        => $term,
], 'Proveedores');
