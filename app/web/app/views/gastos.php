<?php /** @var array $rows @var array $f @var array $proveedores */ ?>
<div class="page-head">
    <div>
        <h1>Gastos recurrentes</h1>
        <p class="muted">Facturas y pagos de gastos recurrentes, por período. La factura y el pago se respaldan en Drive (Proveedor / Año / Mes).</p>
    </div>
    <div class="head-actions">
        <a href="<?= e(url('gasto-form')) ?>" class="btn btn-primary"><?= icon('plus') ?>Nuevo gasto</a>
    </div>
</div>

<form method="get" action="<?= e(url('gastos')) ?>" class="filters card no-print">
    <div class="filter-grid">
        <label>
            <span>Período</span>
            <input type="month" name="periodo" value="<?= e($f['periodo']) ?>">
        </label>
        <label>
            <span>Proveedor</span>
            <select name="proveedor_id">
                <option value="">Todos los proveedores</option>
                <?php foreach ($proveedores as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$f['proveedor_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Estado</span>
            <select name="estado">
                <option value="">Todos</option>
                <option value="pendiente" <?= $f['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="pagado" <?= $f['estado'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
            </select>
        </label>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="<?= e(url('gastos')) ?>" class="btn btn-ghost">Limpiar</a>
    </div>
</form>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead><tr>
                <th>Período</th><th>Proveedor</th><th class="num">Valor mensual</th>
                <th class="prio-3">Factura</th><th class="prio-2">Vencimiento</th>
                <th>Estado</th><th class="prio-3">Archivos</th><th class="acts"></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="empty">No hay gastos con estos filtros.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td data-label="Período"><strong><?= e($r['periodo']) ?></strong></td>
                    <td data-label="Proveedor"><?= e($r['prov_nombre'] ?: '—') ?>
                        <?php if (!empty($r['prov_rubro'])): ?><br><span class="muted small"><?= e($r['prov_rubro']) ?></span><?php endif; ?>
                    </td>
                    <td class="num strong" data-label="Valor"><?= $r['valor_mensual'] !== null ? e(money($r['valor_mensual'], $r['moneda'])) : '—' ?></td>
                    <td data-label="Factura" class="prio-3 muted small"><?= $r['fecha_factura'] ? e(date('d/m/Y', strtotime($r['fecha_factura']))) : '—' ?></td>
                    <td data-label="Vencimiento" class="prio-2 muted small"><?= $r['fecha_vencimiento'] ? e(date('d/m/Y', strtotime($r['fecha_vencimiento']))) : '—' ?></td>
                    <td data-label="Estado"><span class="badge <?= $r['estado'] === 'pagado' ? 'badge-ok' : 'badge-pend' ?>"><?= e(ucfirst($r['estado'])) ?></span></td>
                    <td data-label="Archivos" class="prio-3">
                        <?php if (!empty($r['drive_factura_link'])): ?><a href="<?= e($r['drive_factura_link']) ?>" target="_blank" rel="noopener" class="badge badge-info badge-link" title="Factura en Drive"><?= icon('cloud', 13) ?>Factura</a> <?php endif; ?>
                        <?php if (!empty($r['drive_pago_link'])): ?><a href="<?= e($r['drive_pago_link']) ?>" target="_blank" rel="noopener" class="badge badge-info badge-link" title="Pago en Drive"><?= icon('cloud', 13) ?>Pago</a><?php endif; ?>
                        <?php foreach (['factura', 'pago'] as $t): if (!empty($r[$t . '_path'])): ?><a href="<?= e(url('gasto-archivo') . '?id=' . (int)$r['id'] . '&tipo=' . $t) ?>" target="_blank" rel="noopener" class="badge badge-pend badge-link" title="Guardada en el servidor: se sube a Drive automáticamente cuando haya conexión"><?= icon('file', 13) ?><?= ucfirst($t) ?> · pendiente Drive</a> <?php endif; endforeach; ?>
                        <?php if (empty($r['drive_factura_link']) && empty($r['drive_pago_link']) && empty($r['factura_path']) && empty($r['pago_path'])): ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td class="acts" data-label="Acciones">
                        <a href="<?= e(url('gasto-form') . '?id=' . (int)$r['id']) ?>" class="act" title="Editar"><?= icon('edit', 16) ?></a>
                        <form method="post" action="<?= e(url('gastos')) ?>" style="display:inline" data-confirm="¿Archivar este gasto? Se puede recuperar." data-confirm-danger data-confirm-ok="Archivar">
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
