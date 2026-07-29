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

<?php $tieneImg = $editing && (!empty($v['imagen_path']) || !empty($v['drive_file_id'])); ?>
<!-- Import map para el OCR (mismo motor que el escáner). Debe ir antes de cualquier módulo. -->
<script type="importmap">
{ "imports": { "onnxruntime-web": "https://esm.sh/onnxruntime-web@1.23.2?target=es2022" } }
</script>
<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>

<form method="post" action="<?= e($action) ?>" class="comp-form" id="compForm" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if ($editing && (!empty($v['imagen_path']) || !empty($v['drive_file_id']))):
        $imgSrc = url('comprobante-imagen') . '?id=' . $id;
        $isPdf  = !empty($v['imagen_path']) && strtolower((string)pathinfo((string)$v['imagen_path'], PATHINFO_EXTENSION)) === 'pdf';
    ?>
        <div class="form-card card">
            <h2 class="sec">Imagen del comprobante</h2>
            <a href="<?= e($imgSrc) ?>" class="comp-viewer" data-type="<?= $isPdf ? 'iframe' : 'image' ?>" data-title="Comprobante">
                <?php if ($isPdf): ?>
                    <span class="btn btn-ghost"><?= icon('file') ?> Ver PDF</span>
                <?php else: ?>
                    <img src="<?= e($imgSrc) ?>" alt="Comprobante" class="comp-img">
                <?php endif; ?>
            </a>
            <?php if (!empty($v['drive_link'])): ?>
                <p class="muted small" style="margin-top:8px">
                    <?= icon('cloud', 15) ?> Respaldado en Drive ·
                    <a href="<?= e($v['drive_link']) ?>" target="_blank" rel="noopener">Ver en Google Drive</a>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php include APP . '/views/_fields_comprobante.php'; ?>

    <div class="form-card card scan-panel">
        <h2 class="sec"><?= $tieneImg ? 'Re-escanear o reemplazar la imagen' : 'Adjuntar imagen/foto (opcional)' ?></h2>
        <?php if ($tieneImg): ?>
            <button type="button" id="rescanBtn" class="btn btn-primary" data-rescan-src="<?= e(url('comprobante-imagen') . '?id=' . $id) ?>"><?= icon('scan') ?>Re-escanear el archivo cargado</button>
            <p class="muted small" style="margin:.5rem 0 .9rem">Vuelve a leer con OCR la imagen que ya está cargada (no hace falta subirla de nuevo, no se duplica). O reemplazala por otra abajo.</p>
        <?php endif; ?>
        <div class="filefield" data-filefield>
            <input class="filefield__input" type="file" name="imagen" id="scanFile" accept="image/*,application/pdf">
            <label class="filefield__drop" for="scanFile">
                <?= icon('image', 26) ?>
                <span class="filefield__cta"><?= $tieneImg ? 'Reemplazar por otra imagen o PDF' : 'Elegí una imagen o PDF' ?></span>
                <span class="filefield__hint">Tocá acá o arrastrá el archivo</span>
            </label>
            <p class="filefield__name" data-empty="Sin archivo seleccionado">Sin archivo seleccionado</p>
        </div>
        <button type="button" id="scanBtn" class="btn btn-ghost btn-sm" style="margin-top:.75rem"><?= icon('scan', 15) ?>Leer con OCR la imagen elegida</button>
        <div id="scanStatus" class="muted small" style="margin-top:8px"></div>
        <img id="scanPreview" class="scan-preview" style="display:none" alt="Vista previa">
        <input type="hidden" name="ocr_text" id="ocrText">
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
<!-- OCR (mismo motor que el escáner): permite leer una imagen adjuntada o RE-ESCANEAR la ya cargada. -->
<script defer src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>
<script src="<?= e(url('assets/js/paddleocr.js')) ?>?v=18"></script>
<script src="<?= e(url('assets/js/escanear.js')) ?>?v=37"></script>
<script>
// Re-escanear el archivo YA cargado: lo trae del server (proxy de Drive) y lo pasa al OCR
// sin usar el input → al guardar NO se re-sube (evita duplicar la imagen).
(function () {
    var rb = document.getElementById('rescanBtn');
    if (!rb) { return; }
    rb.addEventListener('click', function () {
        var st = document.getElementById('scanStatus');
        if (st) { st.textContent = 'Trayendo la imagen cargada…'; }
        rb.disabled = true;
        fetch(rb.getAttribute('data-rescan-src'), { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { if (!r.ok) { throw new Error('no se pudo obtener la imagen'); } return r.blob(); })
            .then(function (blob) {
                var ext = /pdf/.test(blob.type) ? 'pdf' : 'jpg';
                var file = new File([blob], 'comprobante.' + ext, { type: blob.type || 'image/jpeg' });
                if (window.ithScanFile) { window.ithScanFile(file); }
                else if (st) { st.textContent = 'La lectura automática no está disponible; recargá la página.'; }
            })
            .catch(function (e) { if (st) { st.textContent = 'No se pudo traer la imagen: ' + (e && e.message || e); } })
            .then(function () { rb.disabled = false; });
    });
})();
</script>
