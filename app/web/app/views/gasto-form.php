<?php /** @var bool $editing @var int $id @var array $v @var array $errors @var array $proveedores */
$val = fn($k) => e((string)($v[$k] ?? ''));
$err = fn($k) => !empty($errors[$k]) ? '<em class="err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Editar gasto' : 'Nuevo gasto recurrente' ?></h1>
        <p class="muted">Subí la factura y leela para autocompletar; revisá y guardá. La factura y el pago se respaldan en Drive.</p>
    </div>
    <a href="<?= e(url('gastos')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<form method="post" action="<?= e(url($editing ? 'gasto-form?id=' . $id : 'gasto-form')) ?>" class="comp-form" enctype="multipart/form-data" id="gastoForm">
    <?= csrf_field() ?>

    <div class="form-card card scan-panel">
        <h2 class="sec">1 · Factura</h2>
        <div class="filefield" data-filefield>
            <input class="filefield__input" type="file" name="factura" id="facturaFile" accept="application/pdf,image/*">
            <label class="filefield__drop" for="facturaFile">
                <?= icon('file', 26) ?>
                <span class="filefield__cta">Elegí el PDF de la factura (o arrastralo)</span>
                <span class="filefield__hint">PDF preferentemente. Después tocá <strong>«Leer factura»</strong> y se autocompletan proveedor, valor y fechas.</span>
            </label>
            <p class="filefield__name" data-empty="Sin archivo seleccionado">Sin archivo seleccionado</p>
        </div>
        <button type="button" id="leerFactura" class="btn btn-primary" style="margin-top:.85rem"><?= icon('scan') ?>Leer factura (autocompletar)</button>
        <?php if ($editing && (!empty($v['drive_factura_link']) || !empty($v['factura_path']))): ?>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:.85rem;margin-left:.4rem" data-releer="factura" data-src="<?= e(url('gasto-archivo') . '?id=' . $id . '&tipo=factura') ?>"><?= icon('scan', 15) ?>Re-leer la factura cargada</button>
        <?php endif; ?>
        <div id="leerEstado" class="muted small" style="margin-top:8px"></div>
        <?php if (!empty($v['factura_path'])): ?>
            <p class="muted small" style="margin-top:8px"><?= icon('file', 15) ?> Factura guardada en el servidor · <strong>pendiente de subir a Drive</strong> (se sube sola cuando haya conexión)</p>
        <?php elseif (!empty($v['drive_factura_link'])): ?>
            <p class="muted small" style="margin-top:8px"><?= icon('cloud', 15) ?> Factura ya cargada · <a href="<?= e($v['drive_factura_link']) ?>" target="_blank" rel="noopener">ver en Drive</a> · no hace falta volver a subirla para re-leerla</p>
        <?php endif; ?>
    </div>

    <h2 class="sec" style="margin:4px 0 -6px">2 · Revisá y corregí los datos</h2>
    <div class="form-card card">
        <div class="grid-fields">
            <label class="fld">
                <span>Proveedor *</span>
                <select name="proveedor_id" required>
                    <option value="">Elegí…</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)($v['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?><?= !empty($p['rubro']) ? ' · ' . e($p['rubro']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $err('proveedor_id') ?>
            </label>
            <label class="fld">
                <span>Período (mes de la factura) *</span>
                <input type="month" name="periodo" value="<?= $val('periodo') ?>" required>
                <?= $err('periodo') ?>
            </label>
            <label class="fld">
                <span>Valor mensual</span>
                <input type="text" name="valor_mensual" value="<?= $val('valor_mensual') ?>" inputmode="decimal" placeholder="0,00">
            </label>
            <label class="fld">
                <span>Moneda</span>
                <select name="moneda">
                    <option value="ARS" <?= ($v['moneda'] ?? 'ARS') === 'ARS' ? 'selected' : '' ?>>Pesos (ARS)</option>
                    <option value="USD" <?= ($v['moneda'] ?? '') === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                </select>
            </label>
            <label class="fld">
                <span>Fecha de factura</span>
                <input type="date" name="fecha_factura" value="<?= $val('fecha_factura') ?>">
            </label>
            <label class="fld">
                <span>1er vencimiento</span>
                <input type="date" name="fecha_vencimiento" value="<?= $val('fecha_vencimiento') ?>">
            </label>
            <label class="fld">
                <span>Fecha de pago</span>
                <input type="date" name="fecha_pago" value="<?= $val('fecha_pago') ?>">
            </label>
            <label class="fld">
                <span>Estado</span>
                <select name="estado">
                    <option value="pendiente" <?= ($v['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="pagado" <?= ($v['estado'] ?? '') === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                </select>
            </label>
            <label class="fld span-all">
                <span>Observaciones</span>
                <input type="text" name="observaciones" value="<?= $val('observaciones') ?>" maxlength="500" placeholder="Notas (opcional)">
            </label>
        </div>
    </div>

    <div class="form-card card">
        <h2 class="sec">3 · Comprobante de pago (opcional)</h2>
        <div class="filefield" data-filefield>
            <input class="filefield__input" type="file" name="pago" id="pagoFile" accept="application/pdf,image/*">
            <label class="filefield__drop" for="pagoFile">
                <?= icon('file', 26) ?>
                <span class="filefield__cta">Elegí el PDF del pago (o arrastralo)</span>
                <span class="filefield__hint">Se guarda junto a la factura en Drive (Proveedor / Año / Mes).</span>
            </label>
            <p class="filefield__name" data-empty="Sin archivo seleccionado">Sin archivo seleccionado</p>
        </div>
        <button type="button" id="leerPago" class="btn btn-ghost btn-sm" style="margin-top:.85rem"><?= icon('scan', 15) ?>Leer pago (fecha + marcar pagado)</button>
        <?php if ($editing && (!empty($v['drive_pago_link']) || !empty($v['pago_path']))): ?>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:.85rem;margin-left:.4rem" data-releer="pago" data-src="<?= e(url('gasto-archivo') . '?id=' . $id . '&tipo=pago') ?>"><?= icon('scan', 15) ?>Re-leer el pago cargado</button>
        <?php endif; ?>
        <div id="leerPagoEstado" class="muted small" style="margin-top:8px"></div>
        <?php if (!empty($v['pago_path'])): ?>
            <p class="muted small" style="margin-top:8px"><?= icon('file', 15) ?> Pago guardado en el servidor · <strong>pendiente de subir a Drive</strong> (se sube solo cuando haya conexión)</p>
        <?php elseif (!empty($v['drive_pago_link'])): ?>
            <p class="muted small" style="margin-top:8px"><?= icon('cloud', 15) ?> Pago ya cargado · <a href="<?= e($v['drive_pago_link']) ?>" target="_blank" rel="noopener">ver en Drive</a></p>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-accent"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Guardar gasto' ?></button>
        <a href="<?= e(url('gastos')) ?>" class="btn btn-ghost">Cancelar</a>
    </div>
</form>

<!-- Import map para el OCR de imagen (PaddleOCR se carga bajo demanda sólo si la factura
     no tiene texto ni QR). Debe ir antes de cualquier import de módulos. -->
<script type="importmap">
{ "imports": { "onnxruntime-web": "https://esm.sh/onnxruntime-web@1.23.2?target=es2022" } }
</script>
<script>
window.APP_BASE = <?= json_encode(url('')) ?>;
window.__gprov = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'rubro' => $p['rubro'] ?? '', 'cuit' => $p['cuit'] ?? ''], $proveedores), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script defer src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script defer src="<?= e(url('assets/js/gastos.js')) ?>?v=10"></script>
