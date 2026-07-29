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

// Conteo de eventos de ALERTAS (accion alerta.*) para la carpeta dedicada.
$alertasN = 0;
try { $alertasN = (int) q("SELECT COUNT(*) FROM actividad WHERE canal='app' AND accion LIKE 'alerta.%' AND creado_en >= DATE_SUB(NOW(), INTERVAL 4 MONTH)")->fetchColumn(); } catch (\Throwable $e) {}

render('cron', [
    'f'        => $f,
    'data'     => actividad_list($f),
    'resumen'  => actividad_resumen($canal),
    'conteo'   => actividad_conteo_tipos($canal),
    'alertasN' => $alertasN,
], 'CRON');
