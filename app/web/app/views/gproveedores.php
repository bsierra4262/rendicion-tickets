<?php /** @var array $rows @var array $eliminados */
$cats = gp_categorias(); $moms = gp_momentos();
?>
<div class="page-head">
    <div>
        <h1>Proveedores</h1>
        <p class="muted">Maestro de proveedores de gastos recurrentes. Cada uno con su rubro, categoría y momento de facturación.</p>
    </div>
    <div class="head-actions">
        <a href="<?= e(url('gproveedor-form')) ?>" class="btn btn-primary"><?= icon('plus') ?>Nuevo proveedor</a>
    </div>
</div>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead><tr>
                <th>Proveedor</th><th class="prio-2">Rubro</th><th>Categoría</th>
                <th class="prio-3">Facturación</th><th class="prio-3">Tipo fact.</th>
                <th class="num prio-4">Gastos</th><th>Estado</th><th class="acts"></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="empty">
                    No hay proveedores todavía.
                    <form method="post" action="<?= e(url('gproveedores')) ?>" style="display:inline;margin-left:8px">
                        <?= csrf_field() ?><input type="hidden" name="accion" value="sembrar">
                        <button type="submit" class="btn btn-sm btn-ghost">Cargar los 19 proveedores reales</button>
                    </form>
                </td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td data-label="Proveedor"><strong><?= e($r['nombre']) ?></strong>
                        <?php if (!empty($r['nombre_gasto'])): ?><br><span class="muted small"><?= e($r['nombre_gasto']) ?></span><?php endif; ?>
                        <?php if (!empty($r['cuit'])): ?><br><span class="muted small"><?= e(gp_cuits_fmt($r['cuit'])) ?></span><?php endif; ?>
                    </td>
                    <td data-label="Rubro" class="prio-2"><?= e($r['rubro'] ?: '—') ?></td>
                    <td data-label="Categoría"><span class="badge <?= $r['categoria'] === 'recurrente' ? 'badge-ok' : 'badge-neutral' ?>"><?= e($cats[$r['categoria']] ?? $r['categoria']) ?></span></td>
                    <td data-label="Facturación" class="prio-3 muted small"><?= e($r['momento_facturacion'] ? ($moms[$r['momento_facturacion']] ?? '') : '—') ?></td>
                    <td data-label="Tipo fact." class="prio-3 muted small"><?= e($r['tipo_factura'] ?: '—') ?></td>
                    <td class="num prio-4" data-label="Gastos"><?= (int)$r['usos'] ?></td>
                    <td data-label="Estado"><span class="badge <?= (int)$r['activo'] === 1 ? 'badge-ok' : 'badge-rej' ?>"><?= (int)$r['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="acts" data-label="Acciones">
                        <a href="<?= e(url('gproveedor-form') . '?id=' . (int)$r['id']) ?>" class="act" title="Editar"><?= icon('edit', 16) ?></a>
                        <form method="post" action="<?= e(url('gproveedores')) ?>" style="display:inline" data-confirm="¿Archivar el proveedor «<?= e($r['nombre']) ?>»? Se puede recuperar." data-confirm-danger data-confirm-ok="Archivar">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="act danger" title="Archivar"><?= icon('trash', 16) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($eliminados): ?>
<div class="card table-card" style="margin-top:1.5rem">
    <div class="card-head" style="padding:1rem 1.2rem 0"><h2><?= icon('trash', 16) ?> Archivados</h2></div>
    <div class="table-scroll">
        <table class="grid">
            <thead><tr><th>Proveedor</th><th class="prio-2">Rubro</th><th>Archivado</th><th class="acts"></th></tr></thead>
            <tbody>
            <?php foreach ($eliminados as $r): ?>
                <tr>
                    <td data-label="Proveedor"><?= e($r['nombre']) ?></td>
                    <td data-label="Rubro" class="prio-2 muted small"><?= e($r['rubro'] ?: '—') ?></td>
                    <td data-label="Archivado" class="muted small"><?= e(date('d/m/Y H:i', strtotime($r['eliminado_en']))) ?></td>
                    <td class="acts">
                        <form method="post" action="<?= e(url('gproveedores')) ?>" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="restaurar"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-ghost"><?= icon('back', 15) ?>Restaurar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
