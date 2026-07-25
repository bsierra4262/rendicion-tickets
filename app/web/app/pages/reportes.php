<?php
declare(strict_types=1);

$periodo = $_GET['periodo'] ?? '';
$moneda  = ($_GET['moneda'] ?? 'ARS') === 'USD' ? 'USD' : 'ARS';

render('reportes', [
    'periodo'  => $periodo,
    'moneda'   => $moneda,
    'periodos' => periodos_disponibles(),
    'resumen'  => rep_resumen($periodo, $moneda),
    'porCat'   => rep_por_categoria($periodo, $moneda),
    'porMes'   => rep_por_mes($moneda),
    'topProv'  => rep_top_proveedores($periodo, $moneda, 10),
], 'Reportes');
