<?php
declare(strict_types=1);
require_admin();

// Canal: 'app' (movimientos + errores de la aplicación) | 'api' (pedidos a la API)
$canal = ($_GET['canal'] ?? 'app') === 'api' ? 'api' : 'app';

$f = [
    'canal' => $canal,
    'tipo'  => $_GET['tipo'] ?? '',
    'q'     => trim($_GET['q'] ?? ''),
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
    'page'  => $_GET['page'] ?? 1,
];

render('cron', [
    'f'       => $f,
    'data'    => actividad_list($f),
    'resumen' => actividad_resumen($canal),
    'conteo'  => actividad_conteo_tipos($canal),
], 'CRON');
