<?php
/** @var array $f  Filtros */
/** @var array $data */
/** @var array $conceptos */
/** @var array $periodos */
$rows = $data['rows'];

// Encabezado ordenable: click alterna asc/desc y preserva los filtros actuales.
$curSort = $f['sort'] ?? 'reciente';
$curDir  = strtolower($f['dir'] ?? 'desc');
$sortTh = function (string $key, string $label, string $thClass = '') use ($f, $curSort, $curDir) {
    $newDir = ($curSort === $key && $curDir === 'asc') ? 'desc' : 'asc';
    $qs = array_filter([
        'periodo' => $f['periodo'] ?? '', 'concepto_id' => $f['concepto_id'] ?? '',
        'estado' => $f['estado'] ?? '', 'moneda' => $f['moneda'] ?? '', 'q' => $f['q'] ?? '',
        'sort' => $key, 'dir' => $newDir,
    ], fn($v) => $v !== '' && $v !== null);
    $arrow = ($curSort === $key)
        ? '<span class="th-arrow on">' . ($curDir === 'asc' ? '▲' : '▼') . '</span>'
        : '<span class="th-arrow">⇅</span>';
    $cls = trim('th-sort ' . $thClass);
    return '<th class="' . e($thClass) . '"><a class="' . $cls . '" href="' . e(url('comprobantes') . '?' . http_build_query($qs)) . '">' . e($label) . ' ' . $arrow . '</a></th>';
};
$badges = [
    'pendiente_revision' => ['badge-pend', 'Pendiente'],
    'verificado'         => ['badge-ok', 'Verificado'],
    'rechazado'          => ['badge-rej', 'Rechazado'],
];
$totalPages = (int)ceil($data['total'] / $data['per']);
$qsBase = array_filter([
    'periodo' => $f['periodo'], 'concepto_id' => $f['concepto_id'],
    'estado' => $f['estado'], 'moneda' => $f['moneda'], 'q' => $f['q'],
    // preservar el orden elegido al paginar/exportar (si no es el default)
    'sort' => ($curSort !== 'reciente') ? $curSort : '', 'dir' => ($curSort !== 'reciente' || $curDir !== 'desc') ? $curDir : '',
]);
?>
<div class="page-head">
    <div>
        <h1>Comprobantes</h1>
        <p class="muted"><?= number_format($data['total'], 0, ',', '.') ?> comprobante(s)</p>
    </div>
    <div class="head-actions">
        <a href="<?= e(url('comprobantes-export') . ($qsBase ? '?' . http_build_query($qsBase) : '')) ?>" class="btn btn-ghost"><?= icon('download') ?>Exportar</a>
        <a href="<?= e(url('escanear')) ?>" class="btn btn-primary"><?= icon('plus') ?>Nuevo comprobante</a>
    </div>
</div>

<form method="get" action="<?= e(url('comprobantes')) ?>" class="filters card no-print">
    <div class="filter-grid">
        <label><span>Período</span>
            <input type="month" name="periodo" value="<?= e($f['periodo']) ?>" max="<?= e(date('Y-m')) ?>" placeholder="Todos">
        </label>
        <label><span>Concepto</span>
            <select name="concepto_id" data-combobox>
                <option value="">Todas</option>
                <?php foreach ($conceptos as $co): ?>
                    <option value="<?= (int)$co['id'] ?>" <?= (string)$f['concepto_id'] === (string)$co['id'] ? 'selected' : '' ?>><?= e($co['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Estado</span>
            <select name="estado">
                <option value="">Todos</option>
                <?php foreach (['pendiente_revision' => 'Pendiente', 'verificado' => 'Verificado', 'rechazado' => 'Rechazado'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Moneda</span>
            <select name="moneda">
                <option value="">Todas</option>
                <option value="ARS" <?= $f['moneda'] === 'ARS' ? 'selected' : '' ?>>Pesos</option>
                <option value="USD" <?= $f['moneda'] === 'USD' ? 'selected' : '' ?>>Dólares</option>
            </select>
        </label>
        <label class="filter-search"><span>Buscar</span>
            <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="Proveedor, CUIT o número…">
        </label>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="<?= e(url('comprobantes')) ?>" class="btn btn-ghost">Limpiar</a>
    </div>
</form>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <?= $sortTh('fecha', 'Comprobante') ?>
                    <?= $sortTh('proveedor', 'Proveedor', 'prio-2') ?>
                    <?= $sortTh('concepto', 'Concepto', 'prio-3') ?>
                    <?= $sortTh('neto', 'Neto', 'num prio-4') ?>
                    <?= $sortTh('iva', 'IVA', 'num prio-4') ?>
                    <?= $sortTh('total', 'Total', 'num') ?>
                    <th class="prio-3">Foto</th>
                    <?= $sortTh('estado', 'Estado', 'prio-2') ?>
                    <th class="acts">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="empty">No hay comprobantes con estos filtros.</td></tr>
            <?php else: foreach ($rows as $r):
                [$bc, $bl] = $badges[$r['estado']] ?? ['', $r['estado']];
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
                    <td data-label="Concepto" class="prio-3"><?= e($r['concepto'] ?: '—') ?></td>
                    <td class="num prio-4" data-label="Neto"><?= e(money($r['importe_neto'], $r['moneda'])) ?></td>
                    <td class="num prio-4" data-label="IVA"><?= e(money($r['importe_iva'], $r['moneda'])) ?></td>
                    <td class="num strong" data-label="Total"><?= e(money($r['importe_total'], $r['moneda'])) ?></td>
                    <td data-label="Foto" class="prio-3">
                        <?php if ($hasImg):
                            $capT = trim($r['tipo_comprobante'] . ' ' . ($r['letra'] ?? '')) . ' · ' . str_pad((string)$r['punto_venta'], 4, '0', STR_PAD_LEFT) . '-' . $r['numero'];
                            $capD = ($r['prov_razon'] ?: 'Sin proveedor') . '  ·  ' . money($r['importe_total'], $r['moneda']) . '  ·  ' . date('d/m/Y', strtotime($r['fecha_emision']));
                        ?>
                            <a href="<?= e($imgUrl) ?>" class="comp-viewer" data-type="<?= $isPdf ? 'iframe' : 'image' ?>"
                               data-title="<?= e($capT) ?>" data-description="<?= e($capD) ?>">
                                <?php if ($isPdf): ?><span class="thumb-pdf"><?= icon('file', 20) ?></span>
                                <?php else: ?><img class="thumb" src="<?= e($imgUrl) ?>" alt="Comprobante" loading="lazy"><?php endif; ?>
                            </a>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td data-label="Estado" class="prio-2"><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
                    <td class="acts" data-label="Acciones">
                        <a href="<?= e(url('comprobante-form') . '?id=' . (int)$r['id']) ?>" class="act" title="Editar"><?= icon('edit', 16) ?></a>
                        <?php if (auth_is_admin()): ?>
                        <form method="post" action="<?= e(url('comprobante-eliminar')) ?>" data-confirm="¿Eliminar este comprobante? Se archiva (recuperable, no se borra de la base)." data-confirm-danger data-confirm-ok="Eliminar" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="act danger" title="Eliminar"><?= icon('trash', 16) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if ($data['totales'] && !empty($f['moneda'])): ?>
            <tfoot>
                <?php foreach ($data['totales'] as $t): ?>
                <tr class="totals">
                    <td>Total <?= e($t['moneda']) ?></td>
                    <td class="prio-2"></td>
                    <td class="prio-3"></td>
                    <td class="num prio-4" data-label="Neto"><?= e(money($t['neto'], $t['moneda'])) ?></td>
                    <td class="num prio-4" data-label="IVA"><?= e(money($t['iva'], $t['moneda'])) ?></td>
                    <td class="num strong" data-label="Total"><?= e(money($t['total'], $t['moneda'])) ?></td>
                    <td class="prio-3"></td>
                    <td class="prio-2"></td>
                    <td class="acts"></td>
                </tr>
                <?php endforeach; ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pager">
    <?php if ($data['page'] > 1): ?>
        <a class="btn btn-ghost" href="?<?= e(http_build_query($qsBase + ['page' => $data['page'] - 1])) ?>">← Anterior</a>
    <?php endif; ?>
    <span class="muted">Página <?= $data['page'] ?> de <?= $totalPages ?></span>
    <?php if ($data['page'] < $totalPages): ?>
        <a class="btn btn-ghost" href="?<?= e(http_build_query($qsBase + ['page' => $data['page'] + 1])) ?>">Siguiente →</a>
    <?php endif; ?>
</div>
<?php endif; ?>
