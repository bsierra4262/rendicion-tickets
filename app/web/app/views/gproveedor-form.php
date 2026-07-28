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
            <label class="fld">
                <span>CUIT (opcional)</span>
                <input type="text" name="cuit" value="<?= $val('cuit') ?>" maxlength="13" placeholder="30-12345678-9">
                <?= $err('cuit') ?>
            </label>
            <?php if ($editing): ?>
            <label class="fld">
                <span>Estado</span>
                <span style="display:flex;align-items:center;gap:.5rem;padding:.4rem 0">
                    <input type="checkbox" name="activo" value="1" <?= (int)($v['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Activo
                </span>
            </label>
            <?php endif; ?>
        </div>
    </div>
    <div class="form-actions">
        <a href="<?= e(url('gproveedores')) ?>" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Crear proveedor' ?></button>
    </div>
</form>
