<?php
/** @var array $conceptos @var array $eliminadas */
?>
<div class="page-head">
    <div>
        <h1>Conceptos</h1>
        <p class="muted">Los conceptos con los que clasificás cada comprobante</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-head"><h2>Agregar concepto</h2></div>
    <form method="post" action="<?= e(url('categoria-guardar')) ?>" class="urow"
          data-confirm="¿Confirmás crear este concepto?" data-confirm-ok="Crear">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" placeholder="Nombre del nuevo concepto — ej. Papelería, Fletes…" maxlength="60" required
               style="flex:1;min-width:240px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
        <button type="submit" class="btn btn-primary"><?= icon('plus') ?>Crear concepto</button>
    </form>
    <p class="muted small" style="margin:.7rem 0 0">También podés crear una al vuelo desde el campo <strong>Concepto</strong> al cargar o filtrar comprobantes.</p>
</div>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th class="num prio-2">Comprobantes</th>
                    <th>Estado</th>
                    <th class="acts">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$conceptos): ?>
                <tr><td colspan="4" class="empty">Todavía no hay conceptos.</td></tr>
            <?php else: foreach ($conceptos as $c): $fid = 'cat' . (int)$c['id']; $did = 'del' . (int)$c['id']; ?>
                <tr>
                    <td data-label="Concepto">
                        <form id="<?= $fid ?>" method="post" action="<?= e(url('categoria-guardar')) ?>" style="display:contents">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        </form>
                        <form id="<?= $did ?>" method="post" action="<?= e(url('categoria-guardar')) ?>" style="display:contents"
                              data-confirm="¿Eliminar el concepto «<?= e($c['nombre']) ?>»? Se archiva y la podés recuperar desde la papelera." data-confirm-danger data-confirm-ok="Eliminar">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        </form>
                        <input type="text" form="<?= $fid ?>" name="nombre" value="<?= e($c['nombre']) ?>" maxlength="60" required
                               style="width:100%;min-width:180px;padding:.5rem .6rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
                    </td>
                    <td class="num prio-2" data-label="Comprobantes"><?= number_format((int)$c['usos'], 0, ',', '.') ?></td>
                    <td data-label="Estado">
                        <label class="chk"><input type="checkbox" form="<?= $fid ?>" name="activo" value="1" <?= (int)$c['activo'] === 1 ? 'checked' : '' ?>> Activa</label>
                    </td>
                    <td class="acts" data-label="Acciones">
                        <button type="submit" form="<?= $fid ?>" class="btn btn-ghost btn-sm"><?= icon('save', 14) ?>Guardar</button>
                        <button type="submit" form="<?= $did ?>" class="act danger" title="Eliminar concepto"><?= icon('trash', 16) ?></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="muted small" style="margin-top:.8rem">Al <strong>desactivar</strong> un concepto deja de aparecer para nuevas cargas pero los comprobantes que ya la usaban la conservan. Al <strong>eliminar</strong> se archiva (recuperable): nada se borra de verdad.</p>

<?php if ($eliminadas): ?>
<div class="card table-card" style="margin-top:1.5rem">
    <div class="card-head" style="padding:1rem 1.2rem 0"><h2><?= icon('trash', 16) ?> Papelera de conceptos</h2></div>
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr><th>Concepto</th><th class="num prio-2">Comprobantes</th><th>Archivada</th><th class="acts">Recuperar</th></tr>
            </thead>
            <tbody>
            <?php foreach ($eliminadas as $c): ?>
                <tr>
                    <td data-label="Concepto"><?= e($c['nombre']) ?></td>
                    <td class="num prio-2" data-label="Comprobantes"><?= number_format((int)$c['usos'], 0, ',', '.') ?></td>
                    <td data-label="Archivada" class="muted small"><?= e(date('d/m/Y H:i', strtotime($c['eliminado_en']))) ?></td>
                    <td class="acts" data-label="Recuperar">
                        <form method="post" action="<?= e(url('categoria-guardar')) ?>" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="restaurar"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('back', 14) ?>Restaurar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
