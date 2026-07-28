<?php
/** @var array $proveedores @var array $conceptos @var int $total @var string $term */
$catOptions = function ($sel) use ($conceptos) {
    $out = '<option value="">— Sin concepto —</option>';
    foreach ($conceptos as $co) {
        $s = ((int)$sel === (int)$co['id']) ? ' selected' : '';
        $out .= '<option value="' . (int)$co['id'] . '"' . $s . '>' . e($co['nombre']) . '</option>';
    }
    return $out;
};
?>
<div class="page-head">
    <div>
        <h1>Proveedores</h1>
        <p class="muted">Catálogo de proveedores (CUIT · razón social · concepto). El OCR usa estos datos: al leer el CUIT, autocompleta razón social y concepto.</p>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-head"><h2>Agregar proveedor</h2></div>
    <form method="post" action="<?= e(url('proveedor-guardar')) ?>" class="urow" style="flex-wrap:wrap;gap:.6rem"
          data-confirm="¿Confirmás agregar este proveedor?" data-confirm-ok="Agregar" data-padron-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="0">
        <input type="text" name="cuit" placeholder="CUIT (opcional)" maxlength="13" inputmode="numeric"
               style="width:170px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
        <input type="text" name="razon_social" placeholder="Razón social… (buscá en el padrón)" maxlength="200" required
               style="flex:1;min-width:220px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
        <select name="categoria_sugerida_id" style="min-width:190px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
            <?= $catOptions(0) ?>
        </select>
        <button type="submit" class="btn btn-primary"><?= icon('plus') ?>Agregar</button>
    </form>
    <p class="muted small" style="margin:.7rem 0 0"><?= icon('search', 13) ?> Mientras escribís la razón social (o el CUIT), te sugiere coincidencias del <strong>padrón nacional (1,13&nbsp;M de empresas)</strong>: elegí una y se completan CUIT + razón, sin duplicar. La <strong>concepto sugerido</strong> se aprende sola con el uso.</p>
</div>

<form method="get" action="<?= e(url('proveedores')) ?>" class="urow" style="margin-bottom:1rem">
    <input type="search" name="q" value="<?= e($term) ?>" placeholder="Buscar por razón social o CUIT…"
           style="flex:1;min-width:220px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
    <button type="submit" class="btn btn-ghost"><?= icon('search') ?>Buscar</button>
    <?php if ($term !== ''): ?><a href="<?= e(url('proveedores')) ?>" class="btn btn-ghost">Ver todos</a><?php endif; ?>
</form>

<div class="card table-card">
    <div class="card-head" style="padding:1rem 1.2rem 0"><h2><?= (int)$total ?> proveedores<?= $term !== '' ? ' · filtrando «' . e($term) . '»' : '' ?></h2></div>
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>CUIT</th>
                    <th>Razón social</th>
                    <th>Concepto sugerido</th>
                    <th class="num prio-2">Comprob.</th>
                    <th class="acts">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$proveedores): ?>
                <tr><td colspan="5" class="empty">No hay proveedores<?= $term !== '' ? ' que coincidan con la búsqueda' : '' ?>.</td></tr>
            <?php else: foreach ($proveedores as $p): $fid = 'prov' . (int)$p['id']; ?>
                <tr>
                    <td data-label="CUIT">
                        <form id="<?= $fid ?>" method="post" action="<?= e(url('proveedor-guardar')) ?>" style="display:contents">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        </form>
                        <input type="text" form="<?= $fid ?>" name="cuit" value="<?= e($p['cuit'] ?? '') ?>" maxlength="13" inputmode="numeric"
                               style="width:130px;padding:.5rem .6rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
                    </td>
                    <td data-label="Razón social">
                        <input type="text" form="<?= $fid ?>" name="razon_social" value="<?= e($p['razon_social']) ?>" maxlength="200" required
                               style="width:100%;min-width:180px;padding:.5rem .6rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
                    </td>
                    <td data-label="Concepto sugerido">
                        <select form="<?= $fid ?>" name="categoria_sugerida_id" style="min-width:170px;padding:.5rem .6rem;border:1px solid var(--line);border-radius:var(--radius-xs)">
                            <?= $catOptions($p['categoria_sugerida_id']) ?>
                        </select>
                    </td>
                    <td class="num prio-2" data-label="Comprob."><?= number_format((int)$p['usos'], 0, ',', '.') ?></td>
                    <td class="acts" data-label="Acciones">
                        <button type="submit" form="<?= $fid ?>" class="btn btn-ghost btn-sm"><?= icon('save', 14) ?>Guardar</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="muted small" style="margin-top:.8rem">Se muestran hasta 300 proveedores. Usá el buscador para encontrar uno puntual. El catálogo crece solo con cada carga y se entrena con el uso.</p>
<script defer src="<?= e(url('assets/js/padron.js')) ?>?v=1"></script>
