<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$cuit    = $_GET['cuit'] ?? '';
$razon   = trim($_GET['razon'] ?? '');
$cuitOcr = $_GET['cuit_ocr'] ?? '';          // CUIT crudo (aunque falle el DV) → recuperación difusa
$term    = trim($_GET['q'] ?? '');

if ($cuit !== '' || $razon !== '' || $cuitOcr !== '') {
    // Motor de resolución con VALIDACIÓN CRUZADA (100% local, sin APIs ni certificados):
    // prioriza que CUIT y razón apunten al MISMO registro (inequívoco); el CUIT desempata
    // homónimos y la razón detecta el CUIT del comprador. Ver resolver_proveedor().
    $r = resolver_proveedor($cuit, $razon, $cuitOcr);
    $c = $r['cuit'] ?? ($cuit !== '' ? normalizar_cuit($cuit) : '');
    $catNombre = '';
    if ($r && !empty($r['categoria_sugerida_id'])) {
        $catNombre = (string) q('SELECT nombre FROM conceptos WHERE id = ?', [$r['categoria_sugerida_id']])->fetchColumn();
    }
    echo json_encode([
        'cuit'         => $c,
        'valido'       => $c !== '' && validar_cuit($c),
        'found'        => (bool) $r,
        'fuente'       => $r['fuente'] ?? '',
        'confianza'    => $r['confianza'] ?? '',
        'razon_social' => $r['razon_social'] ?? '',
        'concepto_id'  => $r['categoria_sugerida_id'] ?? null,
        'concepto'     => $catNombre,
        // ¿Es de un proveedor de Gastos recurrentes? → al guardar va a Gastos, no a Facturas Compra.
        'recurrente'   => ($c !== '' && function_exists('gp_find_by_cuit') && ($gp = gp_find_by_cuit($c)))
                            ? ['id' => (int)$gp['id'], 'nombre' => (string)$gp['nombre']] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($term !== '') {
    echo json_encode(['results' => prov_buscar($term)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'sin parametros']);
