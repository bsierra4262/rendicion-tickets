<?php
/** @var int $total_comp @var int $pendientes @var int $proveedores */
/** @var float $total_mes @var string $mes_actual */
$u = auth_user();
?>
<div class="page-head">
    <div>
        <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?> 👋</h1>
        <p class="muted">Resumen de la rendición de tickets</p>
    </div>
    <a href="<?= e(url('escanear')) ?>" class="btn btn-primary"><?= icon('camera') ?>Escanear ticket</a>
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
    <div class="card stat">
        <span class="stat-label">Proveedores</span>
        <span class="stat-value"><?= number_format($proveedores, 0, ',', '.') ?></span>
    </div>
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
        <a class="quick-item" href="<?= e(url('reportes')) ?>">
            <span class="quick-ic"><?= icon('chart', 22) ?></span>
            <span class="quick-tx"><strong>Reportes y PDF</strong><small>Totales por mes y concepto</small></span>
        </a>
        <?php if (($u['rol'] ?? '') === 'admin'): ?>
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
