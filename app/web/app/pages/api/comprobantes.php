<?php
declare(strict_types=1);
api_require();

$f = [
    'periodo'     => $_GET['periodo'] ?? '',
    'concepto_id' => $_GET['concepto_id'] ?? '',
    'estado'      => $_GET['estado'] ?? '',
    'moneda'      => $_GET['moneda'] ?? '',
    'q'           => trim($_GET['q'] ?? ''),
    'page'        => $_GET['page'] ?? 1,
];
$data = comp_list($f);
api_json([
    'ok'          => true,
    'total'       => $data['total'],
    'pagina'      => $data['page'],
    'por_pagina'  => $data['per'],
    'comprobantes' => array_map('api_row', $data['rows']),
]);
