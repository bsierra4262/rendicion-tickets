<?php /** @var bool $editing @var int $id @var array $v @var array $errors */
$cats = gp_categorias(); $moms = gp_momentos();
$val = fn($k) => e((string)($v[$k] ?? ''));
$err = fn($k) => !empty($errors[$k]) ? '<em class="err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="page-head">
    <div><h1><?= $editing ? 'Editar proveedor' : 'Nuevo proveedor' ?></h1></div>
    <a href="<?= e(url('gproveedores')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<form method="post" action="<?= e(url($editing ? 'gproveedor-form?id=' . $id : 'gproveedor-form')) ?>" class="comp-form">
    <?= csrf_field() ?>
    <div class="form-card card">
        <h2 class="sec">Datos del proveedor</h2>
        <div class="grid-fields">
            <label class="fld">
                <span>Proveedor *</span>
                <input type="text" name="nombre" value="<?= $val('nombre') ?>" maxlength="150" required autofocus>
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
            <?php
            $cuitArr = array_map(
                fn($c) => strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10, 1) : $c,
                preg_split('/\s+/', trim((string)($v['cuit'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)
            );
            if (!$cuitArr) { $cuitArr = ['']; }
            ?>
            <div class="fld">
                <span>CUIT(s) del emisor (opcional)</span>
                <div class="multi-list" data-cuit-list>
                    <?php foreach ($cuitArr as $c): ?>
                        <div class="multi-row">
                            <input type="text" name="cuit[]" value="<?= e($c) ?>" maxlength="13" inputmode="numeric" placeholder="30-70739780-9" autocomplete="off">
                            <button type="button" class="multi-del" data-cuit-del title="Quitar" aria-label="Quitar este CUIT"><?= icon('trash', 16) ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost btn-sm multi-add" data-cuit-add><?= icon('plus', 14) ?>Agregar otro CUIT</button>
                <em class="muted small">Un proveedor puede tener <strong>varios</strong> (ej. EXPENSAS o BANCOS agrupan varias empresas). El match por CUIT del emisor usa cualquiera de ellos.</em>
                <?= $err('cuit') ?>
            </div>
            <label class="fld">
                <span>Carpeta en Drive</span>
                <input type="text" name="drive_folder" value="<?= $val('drive_folder') ?>" maxlength="150" placeholder="ej. 02.EXPENSAS">
                <em class="muted small">Nombre exacto de la carpeta del proveedor en Drive. Si se deja vacío, usa el nombre del proveedor.</em>
            </label>
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
        var list = document.querySelector('[data-cuit-list]'), add = document.querySelector('[data-cuit-add]');
        if (!list || !add) { return; }
        add.addEventListener('click', function () {
            var row = list.querySelector('.multi-row').cloneNode(true);
            var inp = row.querySelector('input'); inp.value = '';
            list.appendChild(row); inp.focus();
        });
        list.addEventListener('click', function (e) {
            var del = e.target.closest('[data-cuit-del]'); if (!del) { return; }
            var rows = list.querySelectorAll('.multi-row');
            if (rows.length > 1) { del.closest('.multi-row').remove(); }
            else { del.closest('.multi-row').querySelector('input').value = ''; }
        });
    })();
    </script>
    <div class="form-actions">
        <a href="<?= e(url('gproveedores')) ?>" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Crear proveedor' ?></button>
    </div>
</form>
