<?php
/** @var array $v @var array $errors @var array $conceptos */
?>
<!-- Import map: fuerza que ppu-paddle-ocr (cargado con ?external=onnxruntime-web) use ESTE
     onnxruntime-web, el mismo que paddleocr.js configura con multi-hilo. Debe ir antes de
     cualquier import de módulos. -->
<script type="importmap">
{ "imports": { "onnxruntime-web": "https://esm.sh/onnxruntime-web@1.23.2?target=es2022" } }
</script>
<div class="page-head">
    <div>
        <h1>Nuevo comprobante</h1>
        <p class="muted">Sacá o subí una foto (o PDF); el OCR lee los datos y los revisás antes de guardar</p>
    </div>
    <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error">Revisá los campos marcados: <?= e(implode(' · ', $errors)) ?></div>
<?php endif; ?>

<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>

<form method="post" action="<?= e(url('escanear')) ?>" class="comp-form" id="compForm" enctype="multipart/form-data" data-ocr-async>
    <?= csrf_field() ?>

    <div class="form-card card scan-panel">
        <h2 class="sec">1 · Foto del ticket</h2>
        <div class="filefield" data-filefield>
            <input class="filefield__input" type="file" name="imagen" id="scanFile" accept="image/*,application/pdf" capture="environment" multiple>
            <label class="filefield__drop" for="scanFile">
                <?= icon('camera', 26) ?>
                <span class="filefield__cta">Sacá o elegí una foto (o PDF) del ticket</span>
                <span class="filefield__hint">Tocá acá para abrir la cámara o arrastrá el archivo · para tickets muy largos podés elegir <strong>varias fotos</strong> y se unen en uno solo</span>
            </label>
            <p class="filefield__name" data-empty="Sin archivo seleccionado">Sin archivo seleccionado</p>
        </div>
        <button type="button" id="scanBtn" class="btn btn-primary" style="margin-top:.85rem"><?= icon('scan') ?>Escanear con OCR</button>
        <div id="scanStatus" class="muted small" style="margin-top:8px"></div>
        <img id="scanPreview" class="scan-preview" style="display:none" alt="Vista previa">
        <input type="hidden" name="ocr_text" id="ocrText">
    </div>

    <h2 class="sec" style="margin:4px 0 -6px">2 · Revisá y corregí los datos</h2>
    <?php include APP . '/views/_fields_comprobante.php'; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-accent"><?= icon('save') ?>Guardar como pendiente de revisión</button>
        <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost">Cancelar</a>
    </div>
</form>

<!-- pdf.js se carga bajo demanda desde escanear.js (solo si suben un PDF) -->
<script defer src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>
<script src="<?= e(url('assets/js/comprobante-form.js')) ?>?v=3"></script>
<script src="<?= e(url('assets/js/paddleocr.js')) ?>?v=18"></script>
<script src="<?= e(url('assets/js/escanear.js')) ?>?v=37"></script>
