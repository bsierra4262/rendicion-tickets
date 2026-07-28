<?php
declare(strict_types=1);
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q      = trim((string)($_GET['q'] ?? ''));
$digits = preg_replace('/\D/', '', $q) ?? '';
if (mb_strlen($q) < 3 && strlen($digits) < 3) {
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}

$rows  = padron_nac_buscar($q, 12);
$items = array_map(fn($r) => ['cuit' => (string)$r['cuit'], 'razon' => (string)$r['razon_social']], $rows);
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
