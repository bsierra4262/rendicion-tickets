<?php
/** @var string $periodo @var string $moneda @var array $periodos */
/** @var array $resumen @var array $porCat @var array $porMes @var array $topProv */
$sym = $moneda === 'USD' ? 'US$' : '$';

// Datos para los gráficos
$catLabels = array_map(fn($c) => $c['categoria'], $porCat);
$catData   = array_map(fn($c) => round((float)$c['total'], 2), $porCat);
$provLabels = array_map(fn($p) => $p['prov'], $topProv);
$provData   = array_map(fn($p) => round((float)$p['total'], 2), $topProv);
$mes = array_reverse($porMes); // cronológico ascendente
$mesLabels = array_map(fn($m) => $m['periodo'], $mes);
$mesData   = array_map(fn($m) => round((float)$m['total'], 2), $mes);

$hCat  = max(260, count($catLabels) * 34 + 40);
$hProv = max(260, count($provLabels) * 34 + 40);
?>
<div class="page-head">
    <div>
        <h1>Reportes</h1>
        <p class="muted">Resúmenes por concepto, mes y proveedor</p>
    </div>
    <a href="<?= e(url('reporte-pdf') . '?periodo=' . rawurlencode($periodo) . '&moneda=' . rawurlencode($moneda) . '&_=' . time()) ?>" class="btn btn-ghost no-print" target="_blank"><?= icon('printer') ?>Descargar PDF</a>
</div>

<form method="get" action="<?= e(url('reportes')) ?>" class="filters card no-print">
    <div class="filter-grid">
        <label><span>Período</span>
            <input type="month" name="periodo" value="<?= e($periodo) ?>" max="<?= e(date('Y-m')) ?>" data-autosubmit placeholder="Todos los meses">
        </label>
        <label><span>Moneda</span>
            <select name="moneda" onchange="this.form.submit()">
                <option value="ARS" <?= $moneda === 'ARS' ? 'selected' : '' ?>>Pesos (ARS)</option>
                <option value="USD" <?= $moneda === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
            </select>
        </label>
    </div>
    <?php if ($periodo !== ''): ?>
        <div class="filter-actions">
            <a href="<?= e(url('reportes') . '?moneda=' . rawurlencode($moneda)) ?>" class="btn btn-ghost btn-sm"><?= icon('x', 14) ?>Ver todos los meses</a>
        </div>
    <?php endif; ?>
</form>

<div class="cards">
    <div class="card stat"><span class="stat-label">Comprobantes</span><span class="stat-value"><?= number_format((int)$resumen['n'], 0, ',', '.') ?></span></div>
    <div class="card stat"><span class="stat-label">Neto</span><span class="stat-value"><?= e(money($resumen['neto'], $moneda)) ?></span></div>
    <div class="card stat"><span class="stat-label">IVA</span><span class="stat-value"><?= e(money($resumen['iva'], $moneda)) ?></span></div>
    <div class="card stat"><span class="stat-label">Total</span><span class="stat-value accent"><?= e(money($resumen['total'], $moneda)) ?></span></div>
</div>

<div class="card">
    <div class="card-head"><h2>Evolución mensual</h2></div>
    <div id="chartMes" class="chart-box" style="min-height:300px"></div>
</div>

<div class="rep-grid">
    <div class="card">
        <div class="card-head"><h2>Por concepto</h2></div>
        <div id="chartCat"></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Top proveedores</h2></div>
        <div id="chartProv"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@4.5.0/dist/apexcharts.min.js"></script>
<script>
(function () {
    if (!window.ApexCharts) return;
    var SYM = <?= json_encode($sym) ?>;
    var nf = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var money = function (v) { return SYM + ' ' + nf.format(+v || 0); };
    var short = function (v) { v = +v || 0; if (v >= 1e6) return SYM + (v / 1e6).toFixed(1) + 'M'; if (v >= 1e3) return SYM + Math.round(v / 1e3) + 'k'; return SYM + v; };
    var INK = '#161922', MUT = '#6f6a7d', GRID = '#f0edf6';

    function barChart(el, labels, data, color) {
        return new ApexCharts(el, {
            chart: { type: 'bar', height: el.dataset.h, fontFamily: 'Saira, sans-serif', toolbar: { show: false }, parentHeightOffset: 0 },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, borderRadiusApplication: 'end', barHeight: '58%' } },
            series: [{ name: 'Total', data: data }],
            xaxis: { categories: labels, labels: { formatter: short, style: { colors: MUT, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: INK, fontSize: '12px' }, maxWidth: 160 } },
            colors: [color],
            dataLabels: { enabled: true, formatter: short, textAnchor: 'start', offsetX: 4, style: { colors: [INK], fontSize: '11px', fontWeight: 600 }, background: { enabled: false } },
            grid: { borderColor: GRID, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } }, padding: { right: 24 } },
            tooltip: { y: { formatter: money } }
        });
    }

    var elCat = document.getElementById('chartCat'); elCat.dataset.h = <?= $hCat ?>;
    barChart(elCat, <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($catData) ?>, '#663399').render();

    var elProv = document.getElementById('chartProv'); elProv.dataset.h = <?= $hProv ?>;
    barChart(elProv, <?= json_encode($provLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($provData) ?>, '#5f8a12').render();

    new ApexCharts(document.getElementById('chartMes'), {
        chart: { type: 'area', height: 300, fontFamily: 'Saira, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0 },
        series: [{ name: 'Total', data: <?= json_encode($mesData) ?> }],
        xaxis: { categories: <?= json_encode($mesLabels) ?>, labels: { style: { colors: MUT, fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { formatter: short, style: { colors: MUT, fontSize: '11px' } } },
        colors: ['#663399'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .02, stops: [0, 100] } },
        stroke: { curve: 'smooth', width: 2.5 },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        grid: { borderColor: GRID, strokeDashArray: 4 },
        tooltip: { y: { formatter: money } }
    }).render();
})();
</script>
