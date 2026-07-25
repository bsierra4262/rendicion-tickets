<?php
/** Plantilla PDF del reporte (Dompdf). Requiere: $resumen,$porCat,$porMes,$topProv,$periodo,$moneda,$logo */
$periodoTxt = $periodo !== '' ? ('Período ' . $periodo) : 'Todos los períodos';
$monTxt = $moneda === 'USD' ? 'Dólares (USD)' : 'Pesos (ARS)';
$maxCat = 0;
foreach ($porCat as $c) { $maxCat = max($maxCat, (float)$c['total']); }
$pct = fn($v) => $maxCat > 0 ? round((float)$v / $maxCat * 100) : 0;
?>
<!doctype html>
<html><head><meta charset="utf-8"><style>
  @page { margin: 0; }
  body { font-family:'DejaVu Sans',sans-serif; color:#1c1e28; font-size:10.5px; margin:0; }
  .header { background:#663399; color:#fff; padding:30px 44px 26px; }
  .hlogo { height:30px; margin-bottom:16px; }
  .eyebrow { font-size:8.5px; letter-spacing:3px; color:#d4c2ee; margin:0 0 6px; font-weight:bold; }
  .title { font-size:27px; font-weight:bold; margin:0 0 7px; letter-spacing:-.4px; }
  .subtitle { font-size:11px; color:#e6dbf5; margin:0; }
  .body { padding:22px 44px 46px; }
  .kpis { width:100%; border-collapse:separate; border-spacing:8px; margin:0 -8px 4px; }
  .kpi { border:1px solid #e7e4ef; border-radius:12px; padding:12px 14px; width:25%; background:#fff; }
  .kpi .l { font-size:8px; color:#8a8598; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; }
  .kpi .v { font-size:14px; font-weight:bold; margin-top:6px; white-space:nowrap; color:#1c1e28; }
  .kpi .v.accent { color:#5f8a12; }
  .callout { background:#f6f2fb; border-left:3px solid #663399; border-radius:8px; padding:11px 14px; font-size:10px; color:#42335e; margin:14px 0 6px; line-height:1.5; }
  h2.sec { font-size:12.5px; color:#663399; margin:22px 0 9px; padding-bottom:6px; border-bottom:2px solid #efeaf6; font-weight:bold; }
  table.data { width:100%; border-collapse:collapse; font-size:10px; }
  table.data th { text-align:left; color:#8a8598; font-size:8px; text-transform:uppercase; letter-spacing:.5px; padding:7px 9px; border-bottom:1.5px solid #e2ddec; font-weight:bold; }
  table.data td { padding:7px 9px; border-bottom:1px solid #f1eef7; }
  table.data tr.alt td { background:#faf9fc; }
  table.data td.num, table.data th.num { text-align:right; }
  table.data td.strong { font-weight:bold; }
  .bar { height:8px; background:#efeaf6; border-radius:5px; width:100%; }
  .bar > div { height:8px; background:#663399; border-radius:5px; }
  .totrow td { border-top:2px solid #d9d2e8; font-weight:bold; background:#f2ecf9; color:#3d2d5c; padding-top:8px; padding-bottom:8px; }
</style></head><body>
  <div class="header">
    <img class="hlogo" src="data:image/png;base64,<?= $logo ?>">
    <div class="eyebrow">RENDICIÓN DE GASTOS</div>
    <div class="title">Reporte de comprobantes</div>
    <div class="subtitle"><?= e($periodoTxt) ?> &nbsp;·&nbsp; <?= e($monTxt) ?> &nbsp;·&nbsp; Generado el <?= date('d/m/Y H:i') ?></div>
  </div>
  <div class="body">
    <table class="kpis"><tr>
      <td class="kpi"><div class="l">Comprobantes</div><div class="v"><?= number_format((int)$resumen['n'], 0, ',', '.') ?></div></td>
      <td class="kpi"><div class="l">Neto</div><div class="v"><?= e(money($resumen['neto'], $moneda)) ?></div></td>
      <td class="kpi"><div class="l">IVA</div><div class="v"><?= e(money($resumen['iva'], $moneda)) ?></div></td>
      <td class="kpi"><div class="l">Total</div><div class="v accent"><?= e(money($resumen['total'], $moneda)) ?></div></td>
    </tr></table>

    <div class="callout">
      Resumen de <b><?= number_format((int)$resumen['n'], 0, ',', '.') ?></b> comprobante(s) en <?= e($monTxt) ?>,
      por un total de <b><?= e(money($resumen['total'], $moneda)) ?></b>
      (neto <?= e(money($resumen['neto'], $moneda)) ?> + IVA <?= e(money($resumen['iva'], $moneda)) ?>).
    </div>

    <h2 class="sec">Gastos por concepto</h2>
    <table class="data">
      <thead><tr><th width="23%">Concepto</th><th width="10%" class="num">Cant.</th><th width="42%">Participación</th><th width="25%" class="num">Total</th></tr></thead>
      <tbody>
      <?php $i = 0; foreach ($porCat as $c): ?>
        <tr<?= $i++ % 2 ? ' class="alt"' : '' ?>>
          <td><?= e($c['categoria']) ?></td>
          <td class="num"><?= (int)$c['n'] ?></td>
          <td><div class="bar"><div style="width:<?= $pct($c['total']) ?>%"></div></div></td>
          <td class="num strong"><?= e(money($c['total'], $moneda)) ?></td>
        </tr>
      <?php endforeach; ?>
        <tr class="totrow"><td>Total general</td><td class="num"><?= (int)$resumen['n'] ?></td><td></td><td class="num"><?= e(money($resumen['total'], $moneda)) ?></td></tr>
      </tbody>
    </table>

    <h2 class="sec">Evolución mensual</h2>
    <table class="data">
      <thead><tr><th width="34%">Período</th><th width="26%" class="num">Comprobantes</th><th width="40%" class="num">Total</th></tr></thead>
      <tbody>
      <?php $i = 0; foreach ($porMes as $m): ?>
        <tr<?= $i++ % 2 ? ' class="alt"' : '' ?>><td><?= e($m['periodo']) ?></td><td class="num"><?= (int)$m['n'] ?></td><td class="num strong"><?= e(money($m['total'], $moneda)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h2 class="sec">Principales proveedores</h2>
    <table class="data">
      <thead><tr><th width="58%">Proveedor</th><th width="14%" class="num">Cant.</th><th width="28%" class="num">Total</th></tr></thead>
      <tbody>
      <?php $i = 0; foreach ($topProv as $p): ?>
        <tr<?= $i++ % 2 ? ' class="alt"' : '' ?>><td><?= e($p['prov']) ?></td><td class="num"><?= (int)$p['n'] ?></td><td class="num strong"><?= e(money($p['total'], $moneda)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body></html>
