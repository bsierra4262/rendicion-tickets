<?php
declare(strict_types=1);
api_require();

$data = comp_list(['estado' => 'pendiente_revision', 'page' => $_GET['page'] ?? 1]);
api_json([
    'ok'         => true,
    'total'      => $data['total'],
    'pendientes' => array_map('api_row', $data['rows']),
]);
