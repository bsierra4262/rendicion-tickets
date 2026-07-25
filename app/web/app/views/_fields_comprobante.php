<?php
/** Parcial de campos de comprobante. Requiere en scope: $v (valores), $errors, $conceptos */
$g = fn($k, $d = '') => $v[$k] ?? $d;
$tasas = [0, 10.5, 21, 27];
$curTasa = $g('tasa_iva', '');
$curMoneda = $g('moneda', 'ARS');
$hoy = date('Y-m-d');
?>
<div class="form-card card">
    <h2 class="sec">Comprobante</h2>
    <div class="grid-fields">
        <label class="fld"><span>Fecha de emisión *</span>
            <input type="date" name="fecha_emision" value="<?= e($g('fecha_emision', $hoy)) ?>" required>
            <?php if (isset($errors['fecha_emision'])): ?><em class="err"><?= e($errors['fecha_emision']) ?></em><?php endif; ?>
        </label>
        <label class="fld"><span>Tipo *</span>
            <select name="tipo_comprobante" required>
                <option value="">Elegí…</option>
                <?php foreach (tipos_comprobante() as $t): ?>
                    <option value="<?= e($t) ?>" <?= $g('tipo_comprobante') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['tipo_comprobante'])): ?><em class="err"><?= e($errors['tipo_comprobante']) ?></em><?php endif; ?>
        </label>
        <label class="fld"><span>Letra</span>
            <select name="letra">
                <option value="">—</option>
                <?php foreach (['A', 'B', 'C'] as $L): ?>
                    <option value="<?= $L ?>" <?= strtoupper((string)$g('letra')) === $L ? 'selected' : '' ?>><?= $L ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="fld"><span>Punto de venta</span>
            <input type="text" name="punto_venta" value="<?= e($g('punto_venta')) ?>" placeholder="0001">
        </label>
        <label class="fld"><span>Número</span>
            <input type="text" name="numero" value="<?= e($g('numero')) ?>" placeholder="00001234">
        </label>
    </div>
</div>

<div class="form-card card">
    <h2 class="sec">Proveedor y concepto</h2>
    <div class="grid-fields">
        <label class="fld"><span>CUIT del proveedor</span>
            <input type="text" name="cuit" id="f_cuit" value="<?= e($g('cuit')) ?>" placeholder="30-71420341-6" autocomplete="off">
            <em class="hint" id="cuit_hint"></em>
            <?php if (isset($errors['cuit'])): ?><em class="err"><?= e($errors['cuit']) ?></em><?php endif; ?>
        </label>
        <label class="fld wide"><span>Razón social</span>
            <input type="text" name="razon_social" id="f_razon" value="<?= e($g('razon_social')) ?>" placeholder="Nombre del proveedor">
        </label>
        <label class="fld"><span>Concepto *</span>
            <select name="concepto_id" required data-combobox data-combobox-create="<?= e(url('concepto-crear')) ?>">
                <option value="">Elegí…</option>
                <?php foreach ($conceptos as $co): ?>
                    <option value="<?= (int)$co['id'] ?>" <?= (string)$g('concepto_id') === (string)$co['id'] ? 'selected' : '' ?>><?= e($co['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['concepto_id'])): ?><em class="err"><?= e($errors['concepto_id']) ?></em><?php endif; ?>
            <em class="hint">¿No está? Escribí el nombre y tocá «Crear concepto nuevo».</em>
        </label>
    </div>
</div>

<div class="form-card card">
    <h2 class="sec">Importes</h2>
    <div class="grid-fields">
        <label class="fld"><span>Moneda</span>
            <select name="moneda" id="f_moneda">
                <option value="ARS" <?= $curMoneda === 'ARS' ? 'selected' : '' ?>>Pesos (ARS)</option>
                <option value="USD" <?= $curMoneda === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
            </select>
        </label>
        <label class="fld" id="f_tc_wrap" style="<?= $curMoneda === 'USD' ? '' : 'display:none' ?>"><span>Tipo de cambio</span>
            <input type="text" name="tipo_cambio" id="f_tc" value="<?= e($g('tipo_cambio')) ?>" placeholder="0,00">
        </label>
        <label class="fld"><span>Importe neto</span>
            <input type="text" name="importe_neto" id="f_neto" value="<?= e($g('importe_neto')) ?>" placeholder="0,00" inputmode="decimal">
        </label>
        <label class="fld"><span>Tasa IVA</span>
            <select name="tasa_iva" id="f_tasa">
                <option value="">—</option>
                <?php foreach ($tasas as $ta): ?>
                    <option value="<?= $ta ?>" <?= ($curTasa !== '' && abs((float)$curTasa - $ta) < 0.01) ? 'selected' : '' ?>><?= $ta ?>%</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="fld"><span>Importe IVA</span>
            <input type="text" name="importe_iva" id="f_iva" value="<?= e($g('importe_iva')) ?>" placeholder="0,00" inputmode="decimal">
        </label>
        <label class="fld"><span>Percepción</span>
            <input type="text" name="importe_percepcion" id="f_perc" value="<?= e($g('importe_percepcion')) ?>" placeholder="0,00" inputmode="decimal">
        </label>
        <label class="fld"><span>Importe total</span>
            <input type="text" name="importe_total" id="f_total" value="<?= e($g('importe_total')) ?>" placeholder="0,00" inputmode="decimal">
            <?php if (isset($errors['importe_total'])): ?><em class="err"><?= e($errors['importe_total']) ?></em><?php endif; ?>
            <?php if (isset($errors['importes'])): ?><em class="err"><?= e($errors['importes']) ?></em><?php endif; ?>
        </label>
    </div>
    <p class="muted small">El IVA y el total se calculan solos, pero podés ajustarlos a mano.</p>
</div>

<div class="form-card card">
    <label class="fld wide"><span>Observaciones</span>
        <textarea name="observaciones" rows="2" placeholder="Notas (opcional)"><?= e($g('observaciones')) ?></textarea>
    </label>
</div>
