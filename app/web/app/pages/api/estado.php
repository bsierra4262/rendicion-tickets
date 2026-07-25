<?php
declare(strict_types=1);
api_require();

$pdo = db();
$mesActual = date('Y-m');
api_json([
    'ok'       => true,
    'generado' => date('c'),
    'resumen'  => [
        'comprobantes'          => (int)$pdo->query("SELECT COUNT(*) FROM comprobantes WHERE eliminado_en IS NULL")->fetchColumn(),
        'pendientes_revision'   => (int)$pdo->query("SELECT COUNT(*) FROM comprobantes WHERE estado='pendiente_revision' AND eliminado_en IS NULL")->fetchColumn(),
        'verificados'           => (int)$pdo->query("SELECT COUNT(*) FROM comprobantes WHERE estado='verificado' AND eliminado_en IS NULL")->fetchColumn(),
        'rechazados'            => (int)$pdo->query("SELECT COUNT(*) FROM comprobantes WHERE estado='rechazado' AND eliminado_en IS NULL")->fetchColumn(),
        'proveedores'           => (int)$pdo->query("SELECT COUNT(*) FROM proveedores")->fetchColumn(),
        'mes_actual'            => $mesActual,
        'total_mes_actual_ars'  => (float) q("SELECT COALESCE(SUM(importe_total),0) FROM comprobantes WHERE periodo=? AND moneda='ARS' AND eliminado_en IS NULL", [$mesActual])->fetchColumn(),
        'por_moneda'            => $pdo->query("SELECT moneda, COUNT(*) n, ROUND(SUM(importe_total),2) total FROM comprobantes WHERE eliminado_en IS NULL GROUP BY moneda")->fetchAll(),
        'por_categoria'         => $pdo->query("SELECT COALESCE(co.nombre,'(sin categoría)') categoria, COUNT(*) n, ROUND(SUM(c.importe_total),2) total FROM comprobantes c LEFT JOIN conceptos co ON co.id=c.concepto_id WHERE c.eliminado_en IS NULL GROUP BY c.concepto_id ORDER BY total DESC")->fetchAll(),
    ],
]);
