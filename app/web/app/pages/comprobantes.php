<?php
declare(strict_types=1);

$f = [
    'periodo'     => $_GET['periodo'] ?? '',
    'concepto_id' => $_GET['concepto_id'] ?? '',
    'estado'      => $_GET['estado'] ?? '',
    'moneda'      => $_GET['moneda'] ?? '',
    'q'           => trim($_GET['q'] ?? ''),
    'page'        => $_GET['page'] ?? 1,
    'sort'        => $_GET['sort'] ?? 'reciente',
    'dir'         => $_GET['dir'] ?? 'desc',
];

$data = comp_list($f);

render('comprobantes', [
    'f'         => $f,
    'data'      => $data,
    'conceptos' => conceptos_list(),
    'periodos'  => periodos_disponibles(),
], 'Comprobantes');
