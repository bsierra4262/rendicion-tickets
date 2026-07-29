<?php
declare(strict_types=1);
// ============================================================
//  Evaluación de alertas 24/7 (para el cron de Hostinger).
//  Ejecutar por CLI cada pocos minutos:
//    */5 * * * * /usr/bin/php <ruta>/app/cron-alertas.php >/dev/null 2>&1
//  Detecta condiciones (pagos vencidos, etc.) y despacha por los canales
//  habilitados (in-sistema + Email/ITHOS según config). Idempotente.
// ============================================================

if (PHP_SAPI !== 'cli') {
    // Fallback web (por si se lo llama por HTTP con un cron-URL). Protegido por clave.
    require __DIR__ . '/bootstrap.php';
    $key = (string)setting_get('alertas_cron_key', '');
    if ($key === '' || (($_GET['key'] ?? '') !== $key)) { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain');
    try { echo 'alertas: ' . alertas_evaluar() . "\n"; } catch (\Throwable $e) { echo 'ERR: ' . $e->getMessage() . "\n"; }
    exit;
}

require __DIR__ . '/bootstrap.php';
try {
    $n = alertas_evaluar();
    fwrite(STDOUT, date('c') . " · alertas evaluadas: {$n}\n");
} catch (\Throwable $e) {
    fwrite(STDERR, date('c') . ' · ERR: ' . $e->getMessage() . "\n");
    exit(1);
}
