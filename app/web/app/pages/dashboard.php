<?php
declare(strict_types=1);

// Métricas rápidas del tablero.
$total_comp   = (int) db()->query('SELECT COUNT(*) FROM comprobantes WHERE eliminado_en IS NULL')->fetchColumn();
$pendientes   = (int) db()->query("SELECT COUNT(*) FROM comprobantes WHERE estado='pendiente_revision' AND eliminado_en IS NULL")->fetchColumn();
$proveedores  = (int) db()->query('SELECT COUNT(*) FROM proveedores')->fetchColumn();
$mes_actual   = date('Y-m');
$total_mes    = (float) q('SELECT COALESCE(SUM(importe_total),0) FROM comprobantes WHERE periodo = ? AND moneda = ? AND eliminado_en IS NULL',
                          [$mes_actual, 'ARS'])->fetchColumn();
// Gastos recurrentes pendientes de pago (módulo nuevo).
$gastos_pend  = 0;
try { $gastos_pend = (int) db()->query("SELECT COUNT(*) FROM gastos_recurrentes WHERE estado <> 'pagado' AND eliminado_en IS NULL")->fetchColumn(); } catch (\Throwable $e) {}
// Alertas activas (solo relevante para admin).
$alertas_act  = (function_exists('alertas_no_leidas') && (auth_user()['rol'] ?? '') === 'admin') ? alertas_no_leidas() : 0;

render('dashboard', [
    'total_comp'  => $total_comp,
    'pendientes'  => $pendientes,
    'proveedores' => $proveedores,
    'total_mes'   => $total_mes,
    'mes_actual'  => $mes_actual,
    'gastos_pend' => $gastos_pend,
    'alertas_act' => $alertas_act,
], 'Panel');
