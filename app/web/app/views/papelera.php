<?php /** @var array $rows */ ?>
<div class="page-head">
    <div>
        <h1><?= icon('trash', 22) ?> Papelera</h1>
        <p class="muted">Comprobantes archivados. Al restaurar, vuelven a la lista y su imagen regresa a la carpeta del mes en Drive. Solo administradores.</p>
    </div>
    <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost"><?= icon('back') ?>Volver a comprobantes</a>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>Comprobante</th>
                    <th class="prio-2">Proveedor</th>
                    <th class="num">Total</th>
                    <th class="prio-3">Foto</th>
                    <th class="prio-2">Archivado</th>
                    <th class="acts"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="empty">La papelera está vacía.</td></tr>
            <?php else: foreach ($rows as $r):
                $hasImg = !empty($r['imagen_path']) || !empty($r['drive_file_id']);
                $isPdf  = !empty($r['imagen_path']) && strtolower((string)pathinfo((string)$r['imagen_path'], PATHINFO_EXTENSION)) === 'pdf';
                $imgUrl = url('comprobante-imagen') . '?id=' . (int)$r['id'];
            ?>
                <tr>
                    <td data-label="Comprobante">
                        <strong><?= e($r['tipo_comprobante']) ?><?= $r['letra'] ? ' ' . e($r['letra']) : '' ?></strong>
                        <?php if ($r['punto_venta'] || $r['numero']): ?>
                            <span class="muted small"><?= e(str_pad((string)$r['punto_venta'], 4, '0', STR_PAD_LEFT)) ?>-<?= e($r['numero']) ?></span>
                        <?php endif; ?>
                        <br><span class="muted small"><?= e(date('d/m/Y', strtotime($r['fecha_emision']))) ?></span>
                    </td>
                    <td data-label="Proveedor" class="prio-2">
                        <?= e($r['prov_razon'] ?: '—') ?>
                        <?php if ($r['prov_cuit']): ?><br><span class="muted small"><?= e(formatear_cuit($r['prov_cuit'])) ?></span><?php endif; ?>
                    </td>
                    <td class="num strong" data-label="Total"><?= e(money($r['importe_total'], $r['moneda'])) ?></td>
                    <td data-label="Foto" class="prio-3">
                        <?php if ($hasImg): ?>
                            <a href="<?= e($imgUrl) ?>" class="comp-viewer" data-type="<?= $isPdf ? 'iframe' : 'image' ?>" data-title="Comprobante archivado">
                                <?php if ($isPdf): ?><span class="thumb-pdf"><?= icon('file', 20) ?></span>
                                <?php else: ?><img class="thumb" src="<?= e($imgUrl) ?>" alt="Comprobante" loading="lazy"><?php endif; ?>
                            </a>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td data-label="Archivado" class="prio-2 muted small">
                        <?= e(date('d/m/Y H:i', strtotime($r['eliminado_en']))) ?>
                        <?php if (!empty($r['elim_por'])): ?><br>por <?= e($r['elim_por']) ?><?php endif; ?>
                    </td>
                    <td class="acts" data-label="Acciones">
                        <form method="post" action="<?= e(url('papelera')) ?>" style="display:inline"
                              data-confirm="¿Restaurar este comprobante? Vuelve a la lista y su imagen regresa a la carpeta del mes en Drive." data-confirm-ok="Restaurar">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="restaurar">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary"><?= icon('back', 15) ?>Restaurar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
