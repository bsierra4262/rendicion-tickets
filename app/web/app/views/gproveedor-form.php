<?php /** @var bool $editing @var int $id @var array $v @var array $errors */
$cats = gp_categorias(); $moms = gp_momentos();
$val = fn($k) => e((string)($v[$k] ?? ''));
$err = fn($k) => !empty($errors[$k]) ? '<em class="err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="page-head">
    <div>
        <h1><?= $editing ? 'Editar proveedor' : 'Nuevo proveedor' ?></h1>
        <p class="muted">Datos del proveedor de gastos recurrentes. El CUIT del emisor permite reconocerlo automáticamente al leer la factura.</p>
    </div>
    <a href="<?= e(url('gproveedores')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<form method="post" action="<?= e(url($editing ? 'gproveedor-form?id=' . $id : 'gproveedor-form')) ?>" class="comp-form">
    <?= csrf_field() ?>
    <div class="form-card card">
        <h2 class="sec">Datos del proveedor</h2>
        <div class="grid-fields">
            <label class="fld">
                <span>Proveedor *</span>
                <input type="text" name="nombre" value="<?= $val('nombre') ?>" maxlength="150" required <?= $editing ? '' : 'autofocus' ?>>
                <?= $err('nombre') ?>
            </label>
            <label class="fld">
                <span>Rubro / Tipo de gasto</span>
                <input type="text" name="rubro" value="<?= $val('rubro') ?>" maxlength="120" placeholder="ej. SERVICIO INTERNET">
            </label>
            <label class="fld">
                <span>Categoría *</span>
                <select name="categoria">
                    <?php foreach ($cats as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= ($v['categoria'] ?? 'recurrente') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="fld">
                <span>Momento de facturación</span>
                <select name="momento_facturacion">
                    <option value="">—</option>
                    <?php foreach ($moms as $k => $lbl): ?>
                        <option value="<?= e($k) ?>" <?= ($v['momento_facturacion'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="fld">
                <span>Tipo de factura</span>
                <input type="text" name="tipo_factura" value="<?= $val('tipo_factura') ?>" maxlength="60" placeholder="ej. Factura A / C / Recibo">
            </label>
            <label class="fld">
                <span>Vigencia de descuento</span>
                <input type="date" name="vigencia_descuento" value="<?= $val('vigencia_descuento') ?>">
            </label>
            <label class="fld">
                <span>Nombre del gasto (opcional)</span>
                <input type="text" name="nombre_gasto" value="<?= $val('nombre_gasto') ?>" maxlength="150">
            </label>
            <label class="fld">
                <span>Carpeta en Drive</span>
                <input type="text" name="drive_folder" value="<?= $val('drive_folder') ?>" maxlength="150" placeholder="ej. 02.EXPENSAS">
                <em class="muted small">Nombre exacto de la carpeta del proveedor en Drive. Si se deja vacío, usa el nombre del proveedor.</em>
            </label>
            <?php
            $multiEnt = !empty($v['multi_entidad']);
            $fmtCuit = fn($c) => strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10, 1) : $c;
            // CUIT único (modo single).
            $singleCuit = $fmtCuit(preg_split('/\s+/', trim((string)($v['cuit'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
            // Entidades (modo multi): [{nombre, cuit}].
            $entArr = [];
            if (!empty($v['entidades'])) { $entArr = json_decode((string)$v['entidades'], true) ?: []; }
            if (!$entArr) { $entArr = [['nombre' => '', 'cuit' => '']]; }
            ?>
            <div class="fld span-all">
                <label class="switch" style="margin-bottom:.55rem">
                    <input type="checkbox" name="multi_entidad" value="1" id="multiEnt" <?= $multiEnt ? 'checked' : '' ?>>
                    <span class="track"><span class="knob"></span></span>
                    <span class="switch-txt">Varias entidades (ej. BANCOS: varios bancos, cada uno con su nombre y CUIT)</span>
                </label>

                <!-- SINGLE: un solo CUIT (proveedores que existen uno solo, ej. EDENOR) -->
                <div data-cuit-single<?= $multiEnt ? ' hidden' : '' ?>>
                    <span class="fld-lbl">CUIT del emisor (opcional)</span>
                    <input type="text" name="cuit" value="<?= e($singleCuit) ?>" maxlength="13" inputmode="numeric" placeholder="30-70739780-9" autocomplete="off" style="max-width:320px"<?= $multiEnt ? ' disabled' : '' ?>>
                    <em class="muted small">Permite reconocer al proveedor por el CUIT del emisor al leer la factura.</em>
                </div>

                <!-- MULTI: varias entidades (nombre + CUIT) -->
                <div data-cuit-multi<?= $multiEnt ? '' : ' hidden' ?>>
                    <span class="fld-lbl">Entidades (nombre + CUIT)</span>
                    <div class="multi-list" data-ent-list>
                        <?php foreach ($entArr as $ent): ?>
                            <div class="multi-row ent-row">
                                <input type="text" name="ent_nombre[]" value="<?= e($ent['nombre'] ?? '') ?>" maxlength="150" placeholder="Nombre / razón social de la entidad" class="ent-nombre"<?= $multiEnt ? '' : ' disabled' ?>>
                                <input type="text" name="ent_cuit[]" value="<?= e($fmtCuit($ent['cuit'] ?? '')) ?>" maxlength="13" inputmode="numeric" placeholder="30-11111111-1" class="ent-cuit"<?= $multiEnt ? '' : ' disabled' ?>>
                                <button type="button" class="multi-del" data-ent-del title="Quitar" aria-label="Quitar esta entidad"><?= icon('trash', 16) ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm multi-add" data-ent-add><?= icon('plus', 14) ?>Agregar entidad</button>
                    <em class="muted small">Cada entidad se identifica por su nombre y se reconoce por su CUIT. El nombre se usa para la carpeta en Drive.</em>
                </div>
                <?= $err('cuit') ?>
            </div>
            <?php if ($editing): ?>
            <div class="fld">
                <span>Estado</span>
                <label class="switch">
                    <input type="checkbox" name="activo" value="1" <?= (int)($v['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span class="track"><span class="knob"></span></span>
                    <span class="switch-txt">Activo</span>
                </label>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    (function () {
        var toggle = document.getElementById('multiEnt');
        var single = document.querySelector('[data-cuit-single]');
        var multi  = document.querySelector('[data-cuit-multi]');
        var entList = document.querySelector('[data-ent-list]');
        var entAdd  = document.querySelector('[data-ent-add]');
        if (!toggle || !single || !multi) { return; }
        function setEnabled(box, on) { box.querySelectorAll('input').forEach(function (i) { i.disabled = !on; }); }
        function sync() {
            var on = toggle.checked;
            multi.hidden = !on; single.hidden = on;         // solo se envía el bloque activo…
            setEnabled(multi, on); setEnabled(single, !on); // …y sus inputs quedan deshabilitados si no aplica
        }
        toggle.addEventListener('change', sync); sync();
        if (entAdd && entList) {
            entAdd.addEventListener('click', function () {
                var row = entList.querySelector('.ent-row').cloneNode(true);
                row.querySelectorAll('input').forEach(function (i) { i.value = ''; i.disabled = false; });
                entList.appendChild(row); row.querySelector('input').focus();
            });
            entList.addEventListener('click', function (e) {
                var del = e.target.closest('[data-ent-del]'); if (!del) { return; }
                var rows = entList.querySelectorAll('.ent-row');
                if (rows.length > 1) { del.closest('.ent-row').remove(); }
                else { del.closest('.ent-row').querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
            });
        }
    })();
    </script>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Crear proveedor' ?></button>
        <a href="<?= e(url('gproveedores')) ?>" class="btn btn-ghost">Cancelar</a>
    </div>
</form>
