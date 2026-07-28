<?php /** @var bool $editing @var int $id @var array $v @var array $errors @var array $proveedores */
$val = fn($k) => e((string)($v[$k] ?? ''));
$err = fn($k) => !empty($errors[$k]) ? '<em class="err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="page-head">
    <div><h1><?= $editing ? 'Editar gasto' : 'Nuevo gasto recurrente' ?></h1></div>
    <a href="<?= e(url('gastos')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver</a>
</div>

<form method="post" action="<?= e(url($editing ? 'gasto-form?id=' . $id : 'gasto-form')) ?>" class="comp-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-card card">
        <h2 class="sec">Datos del gasto</h2>
        <div class="grid-fields">
            <label class="fld">
                <span>Proveedor *</span>
                <select name="proveedor_id" required>
                    <option value="">Elegí…</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-rubro="<?= e($p['rubro'] ?? '') ?>" <?= (int)($v['proveedor_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
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
                    <option value="ARS" <?= ($v['moneda'] ?? 'ARS') === 'ARS' ? 'selected' : '' ?>>ARS ($)</option>
                    <option value="USD" <?= ($v['moneda'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (US$)</option>
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
            <label class="fld" style="grid-column:1/-1">
                <span>Observaciones</span>
                <input type="text" name="observaciones" value="<?= $val('observaciones') ?>" maxlength="500">
            </label>
        </div>
    </div>

    <div class="form-card card">
        <h2 class="sec">Archivos (se suben a Drive)</h2>
        <div class="grid-fields">
            <label class="fld">
                <span>PDF de la factura</span>
                <input type="file" name="factura" accept="application/pdf,image/*">
                <?php if (!empty($v['drive_factura_link'])): ?><em class="muted small"><?= icon('cloud', 13) ?> Ya cargada · <a href="<?= e($v['drive_factura_link']) ?>" target="_blank" rel="noopener">ver</a></em><?php endif; ?>
            </label>
            <label class="fld">
                <span>PDF del pago</span>
                <input type="file" name="pago" accept="application/pdf,image/*">
                <?php if (!empty($v['drive_pago_link'])): ?><em class="muted small"><?= icon('cloud', 13) ?> Ya cargado · <a href="<?= e($v['drive_pago_link']) ?>" target="_blank" rel="noopener">ver</a></em><?php endif; ?>
            </label>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= e(url('gastos')) ?>" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?><?= $editing ? 'Guardar cambios' : 'Cargar gasto' ?></button>
    </div>
</form>
