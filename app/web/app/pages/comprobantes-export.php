<?php
declare(strict_types=1);

$f = [
    'periodo'     => $_GET['periodo'] ?? '',
    'concepto_id' => $_GET['concepto_id'] ?? '',
    'estado'      => $_GET['estado'] ?? '',
    'moneda'      => $_GET['moneda'] ?? '',
    'q'           => trim($_GET['q'] ?? ''),
];
$rows = comp_all($f);

if (function_exists('log_app')) {
    log_app('comprobantes.exportar', ['entidad' => 'comprobante', 'detalle' => 'Exportó ' . count($rows) . ' comprobante(s) a CSV']);
}

$fn = 'comprobantes_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fn . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete UTF-8
$sep = ';';
fputcsv($out, ['Fecha', 'Tipo', 'Letra', 'Punto de venta', 'Numero', 'Proveedor', 'CUIT',
    'Categoria', 'Moneda', 'Neto', 'Tasa IVA', 'IVA', 'Percepcion', 'Total', 'Estado'], $sep);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['fecha_emision'],
        $r['tipo_comprobante'],
        $r['letra'],
        $r['punto_venta'],
        $r['numero'],
        $r['prov_razon'],
        $r['prov_cuit'],
        $r['concepto'],
        $r['moneda'],
        number_format((float)$r['importe_neto'], 2, ',', ''),
        $r['tasa_iva'],
        number_format((float)$r['importe_iva'], 2, ',', ''),
        number_format((float)$r['importe_percepcion'], 2, ',', ''),
        number_format((float)$r['importe_total'], 2, ',', ''),
        $r['estado'],
    ], $sep);
}
fclose($out);
exit;
