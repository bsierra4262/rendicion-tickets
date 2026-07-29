<?php
/** @var array $f  Filtros (incluye canal) */
/** @var array $data  [rows,total,page,per,sin_tabla,ventana_default,ventana_meses] */
/** @var array $resumen  [total,hoy,errores,ultimo] */
/** @var array $conteo  Conteo por tipo (carpetas) */
$canal = $f['canal'];
$esApp = $canal === 'app';

$tipoBadge = static function (string $t): array {
    $m = [
        'accion'  => ['badge-ok', 'acción'],
        'nav'     => ['badge-neutral', 'navegación'],
        'auth'    => ['badge-info', 'sesión'],
        'error'   => ['badge-rej', 'error'],
        'sistema' => ['badge-pend', 'sistema'],
    ];
    return $m[$t] ?? ['badge-neutral', $t];
};

// Acción en texto legible ("quién hizo qué"), con el código técnico debajo.
$accionLabel = static function (string $a): string {
    static $m = [
        'comprobante.crear' => 'Creó un comprobante', 'comprobante.editar' => 'Editó un comprobante',
        'comprobante.verificar' => 'Verificó un comprobante', 'comprobante.rechazar' => 'Rechazó un comprobante',
        'comprobante.estado' => 'Cambió el estado de un comprobante', 'comprobante.eliminar' => 'Archivó un comprobante',
        'comprobante.restaurar' => 'Restauró un comprobante',
        'categoria.crear' => 'Creó un concepto', 'categoria.editar' => 'Editó un concepto',
        'categoria.eliminar' => 'Archivó un concepto', 'categoria.restaurar' => 'Restauró un concepto',
        'usuario.actualizar' => 'Actualizó un usuario',
        'token.crear' => 'Creó un token de API', 'token.estado' => 'Cambió un token de API', 'token.eliminar' => 'Eliminó un token de API',
        'sesion.ingreso' => 'Inició sesión', 'sesion.salida' => 'Cerró sesión',
        'error.php' => 'Error de PHP', 'error.excepcion' => 'Excepción no controlada', 'error.fatal' => 'Error fatal',
        'sistema.deploy' => 'Despliegue del sistema', 'comprobantes.exportar' => 'Exportó comprobantes',
    ];
    if (isset($m[$a])) return $m[$a];
    if (strncmp($a, 'ver.', 4) === 0) return 'Navegó a «' . substr($a, 4) . '»';
    if (strncmp($a, 'api', 3) === 0) return 'Consultó la API';
    return $a;
};

$totalPages = (int)ceil(($data['total'] ?? 0) / max(1, $data['per'] ?? 50));
$qs = array_filter([
    'canal' => $canal, 'tipo' => $f['tipo'], 'q' => $f['q'],
    'desde' => $f['desde'], 'hasta' => $f['hasta'],
], static fn($v) => $v !== '' && $v !== null);
?>
<div class="page-head">
    <div>
        <h1>CRON — Registro de actividad</h1>
        <p class="muted">Bitácora de todo lo que pasa en el sistema. Solo lectura, visible para administradores.</p>
    </div>
    <div class="head-actions">
        <label class="live-toggle" title="Actualiza la bitácora en tiempo real"><input type="checkbox" id="cronLive" checked><span class="live-dot"></span>En vivo</label>
        <a href="<?= e(url('cron') . '?' . http_build_query($qs)) ?>" class="btn btn-ghost" id="cronRefresh"><?= icon('refresh') ?>Actualizar</a>
    </div>
</div>

<div class="seg" style="margin-bottom:1rem" role="tablist">
    <a href="<?= e(url('cron') . '?canal=app') ?>" class="seg-item <?= $esApp ? 'active' : '' ?>"><?= icon('server', 16) ?>Aplicación</a>
    <a href="<?= e(url('cron') . '?canal=api') ?>" class="seg-item <?= !$esApp ? 'active' : '' ?>"><?= icon('key', 16) ?>API</a>
</div>

<div class="flash flash-info">
    <?php if ($esApp): ?>
        <strong>CRON de la aplicación.</strong> Todo lo que hace cada usuario: navegación, altas, ediciones, verificaciones, borrados, gestión de usuarios/tokens, ingresos y salidas, y <strong>errores</strong> del sistema.
    <?php else: ?>
        <strong>CRON de la API.</strong> Cada pedido que recibe la API de solo lectura (por ejemplo, ITHOS u otro servicio autorizado): qué token consultó, cuándo y desde qué IP. Incluye los rechazos por token inválido.
    <?php endif; ?>
</div>

<?php if (!empty($data['sin_tabla'])): ?>
    <div class="flash flash-error">El registro de actividad todavía no está disponible. Se habilitará automáticamente cuando el sistema termine de configurarse.</div>
<?php endif; ?>

<?php
// Carpetas por tipo (más fácil buscar algo específico que con el dropdown)
$folders = [
    ''        => ['Todos',          (int)($conteo['total'] ?? 0)],
    'nav'     => ['Navegación',     (int)($conteo['nav'] ?? 0)],
    'accion'  => ['Modificaciones', (int)($conteo['accion'] ?? 0)],
    'auth'    => ['Sesiones',       (int)($conteo['auth'] ?? 0)],
    'error'   => ['Errores',        (int)($conteo['error'] ?? 0)],
    'sistema' => ['Sistema',        (int)($conteo['sistema'] ?? 0)],
];
$baseFold = array_filter(
    ['canal' => $canal, 'q' => $f['q'], 'desde' => $f['desde'], 'hasta' => $f['hasta']],
    static fn($v) => $v !== '' && $v !== null
);
?>
<div class="folders">
    <?php foreach ($folders as $k => [$lbl, $n]):
        // Ocultar carpetas vacías, PERO "Errores" y "Sistema" siempre visibles (auditoría)
        if ($k !== '' && $n === 0 && $f['tipo'] !== $k && $k !== 'error' && $k !== 'sistema') continue;
        $active = ($f['tipo'] === $k && ($f['q'] ?? '') !== 'alerta.');
        $href = url('cron') . '?' . http_build_query($baseFold + ($k !== '' ? ['tipo' => $k] : []));
    ?>
        <a href="<?= e($href) ?>" class="folder <?= $active ? 'active' : '' ?>"><?= e($lbl) ?><span class="folder-n"><?= number_format($n, 0, ',', '.') ?></span></a>
        <?php if ($k === 'auth' && $esApp): // carpeta dedicada de Alertas, al lado de Sesiones
            $alN = (int)($alertasN ?? 0); $alActive = ($f['q'] ?? '') === 'alerta.';
            $alHref = url('cron') . '?' . http_build_query(['canal' => $canal, 'q' => 'alerta.'] + array_filter(['desde' => $f['desde'], 'hasta' => $f['hasta']]));
        ?>
            <a href="<?= e($alHref) ?>" class="folder <?= $alActive ? 'active' : '' ?>"><?= icon('alert', 14) ?> Alertas<span class="folder-n"><?= number_format($alN, 0, ',', '.') ?></span></a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="cards" id="cronStats">
    <div class="card stat"><span class="stat-label">Eventos (últimos <?= (int)($data['ventana_meses'] ?? 4) ?> meses)</span><span class="stat-value"><?= number_format($resumen['total'], 0, ',', '.') ?></span></div>
    <div class="card stat"><span class="stat-label">Hoy</span><span class="stat-value"><?= number_format($resumen['hoy'], 0, ',', '.') ?></span></div>
    <div class="card stat"><span class="stat-label">Errores</span><span class="stat-value <?= $resumen['errores'] > 0 ? 'warn' : '' ?>"><?= number_format($resumen['errores'], 0, ',', '.') ?></span></div>
    <div class="card stat"><span class="stat-label">Último movimiento</span><span class="stat-value" style="font-size:16px"><?= $resumen['ultimo'] ? e(date('d/m/Y H:i', strtotime($resumen['ultimo']))) : '—' ?></span></div>
</div>

<form method="get" action="<?= e(url('cron')) ?>" class="filters card">
    <input type="hidden" name="canal" value="<?= e($canal) ?>">
    <input type="hidden" name="tipo" value="<?= e($f['tipo']) ?>">
    <div class="filter-grid">
        <label><span>Desde <span class="muted" style="font-weight:500;text-transform:none">(histórico)</span></span>
            <input type="date" name="desde" value="<?= e($f['desde']) ?>" max="<?= e(date('Y-m-d')) ?>">
        </label>
        <label><span>Hasta</span>
            <input type="date" name="hasta" value="<?= e($f['hasta']) ?>" max="<?= e(date('Y-m-d')) ?>">
        </label>
        <label class="filter-search"><span>Buscar</span>
            <input type="text" name="q" value="<?= e($f['q']) ?>" placeholder="Usuario, acción, detalle o IP…">
        </label>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn btn-primary"><?= icon('search') ?>Filtrar</button>
        <a href="<?= e(url('cron') . '?' . http_build_query(array_filter(['canal' => $canal, 'tipo' => $f['tipo']]))) ?>" class="btn btn-ghost">Limpiar</a>
    </div>
</form>

<?php if (!empty($data['ventana_default'])): ?>
    <p class="cron-note"><?= icon('clock', 14) ?> En pantalla se muestran los <strong>últimos <?= (int)$data['ventana_meses'] ?> meses</strong> (por rendimiento). El histórico completo nunca se borra: para consultarlo, elegí una fecha <strong>«Desde»</strong> anterior.</p>
<?php endif; ?>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Tipo</th>
                    <th class="prio-2"><?= $esApp ? 'Usuario' : 'Token' ?></th>
                    <th>Acción</th>
                    <th class="prio-3">Detalle</th>
                    <th class="prio-4">IP</th>
                </tr>
            </thead>
            <tbody id="cronBody">
            <?php if (empty($data['rows'])): ?>
                <tr><td colspan="6" class="empty">Sin movimientos registrados<?= count($qs) > 1 ? ' con estos filtros' : ' todavía' ?>.</td></tr>
            <?php else: foreach ($data['rows'] as $r):
                [$bc, $bl] = $tipoBadge((string)$r['tipo']);
            ?>
                <tr>
                    <td data-label="Fecha y hora">
                        <strong><?= e(date('d/m/Y', strtotime($r['creado_en']))) ?></strong>
                        <span class="muted small"><?= e(date('H:i:s', strtotime($r['creado_en']))) ?></span>
                    </td>
                    <td data-label="Tipo"><span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></td>
                    <td data-label="<?= $esApp ? 'Usuario' : 'Token' ?>" class="prio-2">
                        <div class="uinfo">
                            <?php if ($esApp && !empty($r['usuario_avatar'])): ?>
                                <img class="avatar avatar-sm" src="<?= e($r['usuario_avatar']) ?>" alt="" referrerpolicy="no-referrer">
                            <?php elseif ($esApp && !empty($r['usuario_id'])): ?>
                                <span class="avatar-fallback avatar-sm"><?= e(mb_strtoupper(mb_substr((string)($r['actor'] ?: '?'), 0, 1))) ?></span>
                            <?php endif; ?>
                            <span>
                                <?= e($r['actor'] ?: '—') ?>
                                <?php if (!empty($r['usuario_email'])): ?><br><span class="muted small"><?= e($r['usuario_email']) ?></span><?php endif; ?>
                            </span>
                        </div>
                    </td>
                    <td data-label="Acción">
                        <strong style="font-weight:600"><?= e($accionLabel((string)$r['accion'])) ?></strong>
                        <br><code class="mono" style="font-size:11px;color:var(--muted)"><?= e($r['accion']) ?></code>
                    </td>
                    <td data-label="Detalle" class="prio-3"><?= e($r['detalle'] ?: '—') ?></td>
                    <td data-label="IP" class="prio-4"><span class="mono-cell"><?= e($r['ip'] ?: '—') ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pager">
    <?php if ($data['page'] > 1): ?>
        <a class="btn btn-ghost" href="?<?= e(http_build_query($qs + ['page' => $data['page'] - 1])) ?>">← Anterior</a>
    <?php endif; ?>
    <span class="muted">Página <?= $data['page'] ?> de <?= $totalPages ?> · <?= number_format($data['total'], 0, ',', '.') ?> eventos</span>
    <?php if ($data['page'] < $totalPages): ?>
        <a class="btn btn-ghost" href="?<?= e(http_build_query($qs + ['page' => $data['page'] + 1])) ?>">Siguiente →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script src="<?= e(url('assets/js/cron-live.js')) ?>?v=1"></script>
