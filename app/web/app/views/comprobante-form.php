<?php
/** @var bool $editing @var int $id @var array $v @var array $errors @var array $conceptos @var string $estado */
$action = url('comprobante-form') . ($editing ? '?id=' . $id : '');
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Editar comprobante' : 'Nuevo comprobante' ?></h1>
        <p class="muted"><?= $editing && $estado === 'pendiente_revision' ? 'Revisá los datos escaneados y verificá' : 'Cargá los datos del comprobante de gasto' ?></p>
        <?php if ($editing):
            $bmap = ['pendiente_revision' => ['badge-pend', 'Pendiente'], 'verificado' => ['badge-ok', 'Verificado'], 'rechazado' => ['badge-rej', 'Rechazado']];
            [$bc, $bl] = $bmap[$estado] ?? ['badge-neutral', $estado];
        ?>
            <span class="badge <?= e($bc) ?>" style="margin-top:.45rem">Estado: <?= e($bl) ?></span>
        <?php endif; ?>
    </div>
    <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error">Revisá los campos marcados: <?= e(implode(' · ', $errors)) ?></div>
<?php endif; ?>

<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>

<form method="post" action="<?= e($action) ?>" class="comp-form" id="compForm" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if ($editing && !empty($v['imagen_path'])): ?>
        <div class="form-card card">
            <h2 class="sec">Imagen del comprobante</h2>
            <a href="<?= e(url('comprobante-imagen') . '?id=' . $id) ?>" target="_blank" rel="noopener">
                <img src="<?= e(url('comprobante-imagen') . '?id=' . $id) ?>" alt="Comprobante" class="comp-img">
            </a>
        </div>
    <?php endif; ?>

    <?php include APP . '/views/_fields_comprobante.php'; ?>

    <div class="form-card card">
        <h2 class="sec"><?= !empty($v['imagen_path']) ? 'Reemplazar imagen' : 'Adjuntar imagen/foto (opcional)' ?></h2>
        <div class="filefield" data-filefield>
            <input class="filefield__input" type="file" name="imagen" id="adjImagen" accept="image/*,application/pdf">
            <label class="filefield__drop" for="adjImagen">
                <?= icon('image', 26) ?>
                <span class="filefield__cta">Elegí una imagen o PDF</span>
                <span class="filefield__hint">Tocá acá o arrastrá el archivo</span>
            </label>
            <p class="filefield__name" data-empty="Sin archivo seleccionado">Sin archivo seleccionado</p>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Cargar comprobante' ?></button>
        <?php if ($editing): ?>
            <?php if ($estado !== 'verificado'): ?>
                <button type="submit" name="estado_action" value="verificar" class="btn btn-accent"><?= icon('check') ?><?= $estado === 'rechazado' ? 'Aprobar (verificar)' : 'Verificar' ?></button>
            <?php endif; ?>
            <?php if ($estado !== 'rechazado'): ?>
                <button type="submit" name="estado_action" value="rechazar" class="btn btn-ghost" data-confirm="¿Marcar este comprobante como rechazado?" data-confirm-danger data-confirm-ok="Rechazar"><?= icon('x') ?>Rechazar</button>
            <?php endif; ?>
            <?php if ($estado !== 'pendiente_revision'): ?>
                <button type="submit" name="estado_action" value="pendiente" class="btn btn-ghost"><?= icon('back') ?>Volver a pendiente</button>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost">Cancelar</a>
    </div>
</form>

<script src="<?= e(url('assets/js/comprobante-form.js')) ?>?v=3"></script>
