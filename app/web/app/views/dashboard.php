<?php
/** @var int $total_comp @var int $pendientes @var int $proveedores @var int $gastos_pend @var int $alertas_act */
/** @var float $total_mes @var string $mes_actual */
$u = auth_user();
$esAdmin = ($u['rol'] ?? '') === 'admin';
?>
<div class="page-head">
    <div>
        <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
        <p class="muted">Resumen de la actividad</p>
    </div>
    <a href="<?= e(url('escanear')) ?>" class="btn btn-primary"><?= icon('camera') ?>Escanear comprobante</a>
</div>

<div class="cards">
    <a class="card stat" href="<?= e(url('comprobantes')) ?>">
        <span class="stat-label">Comprobantes cargados</span>
        <span class="stat-value"><?= number_format($total_comp, 0, ',', '.') ?></span>
    </a>
    <a class="card stat" href="<?= e(url('comprobantes') . '?estado=pendiente_revision') ?>">
        <span class="stat-label">Pendientes de revisión</span>
        <span class="stat-value <?= $pendientes > 0 ? 'warn' : '' ?>"><?= number_format($pendientes, 0, ',', '.') ?></span>
    </a>
    <a class="card stat" href="<?= e(url('gastos') . '?estado=pendiente') ?>">
        <span class="stat-label">Gastos por pagar</span>
        <span class="stat-value <?= $gastos_pend > 0 ? 'warn' : '' ?>"><?= number_format($gastos_pend, 0, ',', '.') ?></span>
    </a>
    <?php if ($esAdmin): ?>
    <a class="card stat" href="<?= e(url('alertas')) ?>">
        <span class="stat-label">Alertas activas</span>
        <span class="stat-value <?= $alertas_act > 0 ? 'warn' : '' ?>"><?= number_format($alertas_act, 0, ',', '.') ?></span>
    </a>
    <?php endif; ?>
    <a class="card stat" href="<?= e(url('reportes')) ?>">
        <span class="stat-label">Total <?= e($mes_actual) ?> (ARS)</span>
        <span class="stat-value accent"><?= e(money($total_mes)) ?></span>
    </a>
</div>

<div class="card">
    <div class="card-head"><h2>Accesos rápidos</h2></div>
    <div class="quick">
        <a class="quick-item" href="<?= e(url('escanear')) ?>">
            <span class="quick-ic"><?= icon('scan', 22) ?></span>
            <span class="quick-tx"><strong>Escanear ticket</strong><small>Sacá una foto y el OCR lo lee</small></span>
        </a>
        <a class="quick-item" href="<?= e(url('comprobante-form')) ?>">
            <span class="quick-ic"><?= icon('plus', 22) ?></span>
            <span class="quick-tx"><strong>Nuevo comprobante</strong><small>Cargar a mano</small></span>
        </a>
        <a class="quick-item" href="<?= e(url('comprobantes')) ?>">
            <span class="quick-ic"><?= icon('receipt', 22) ?></span>
            <span class="quick-tx"><strong>Ver comprobantes</strong><small>Buscar, filtrar y editar</small></span>
        </a>
        <a class="quick-item" href="<?= e(url('gastos')) ?>">
            <span class="quick-ic"><?= icon('repeat', 22) ?></span>
            <span class="quick-tx"><strong>Gastos recurrentes</strong><small>Facturas y pagos fijos</small></span>
        </a>
        <a class="quick-item" href="<?= e(url('gasto-form')) ?>">
            <span class="quick-ic"><?= icon('plus', 22) ?></span>
            <span class="quick-tx"><strong>Nuevo gasto</strong><small>Cargar una factura</small></span>
        </a>
        <a class="quick-item" href="<?= e(url('reportes')) ?>">
            <span class="quick-ic"><?= icon('chart', 22) ?></span>
            <span class="quick-tx"><strong>Reportes y PDF</strong><small>Totales por mes y concepto</small></span>
        </a>
        <?php if ($esAdmin): ?>
            <a class="quick-item" href="<?= e(url('alertas')) ?>">
                <span class="quick-ic"><?= icon('alert', 22) ?></span>
                <span class="quick-tx"><strong>Alertas</strong><small>Avisos y vencimientos</small></span>
            </a>
            <a class="quick-item" href="<?= e(url('usuarios')) ?>">
                <span class="quick-ic"><?= icon('users', 22) ?></span>
                <span class="quick-tx"><strong>Usuarios</strong><small>Roles y accesos del equipo</small></span>
            </a>
            <a class="quick-item" href="<?= e(url('cron')) ?>">
                <span class="quick-ic"><?= icon('activity', 22) ?></span>
                <span class="quick-tx"><strong>CRON</strong><small>Bitácora de actividad</small></span>
            </a>
        <?php endif; ?>
    </div>
</div>
