<?php
/** @var array $alertas @var bool $verTodas @var array $tipos @var array $cfg @var array $canales
 *  @var string $emailTo @var string $entorno */
$sevBadge = ['danger' => ['badge-rej', 'Urgente'], 'warn' => ['badge-pend', 'Atención'], 'info' => ['badge-info', 'Info']];
$noLeidas = 0; foreach ($alertas as $a) { if (empty($a['leida_en'])) { $noLeidas++; } }
$enTest = ($entorno ?? 'testing') === 'testing';
?>
<?php if ($enTest): ?>
    <div class="flash flash-info" style="display:flex;align-items:center;gap:.5rem"><?= icon('alert', 16) ?> <strong>Modo TEST activo</strong> — las alertas que se envían por Email/ITHOS/WhatsApp llevan el prefijo <code>[TEST]</code>. Cambialo a Producción abajo (o avisá) cuando esté listo.</div>
<?php endif; ?>
<div class="page-head">
    <div>
        <h1>Alertas</h1>
        <p class="muted">Avisos del sistema centralizados. Por default se ven acá; el admin puede enviarlas también por Email o ITHOS.</p>
    </div>
    <div class="head-actions">
        <form method="post" action="<?= e(url('alertas')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="accion" value="evaluar">
            <button type="submit" class="btn btn-ghost"><?= icon('activity') ?>Evaluar ahora</button>
        </form>
        <?php if ($noLeidas): ?>
        <form method="post" action="<?= e(url('alertas')) ?>" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="accion" value="leer_todas">
            <button type="submit" class="btn btn-primary"><?= icon('check') ?>Marcar todas leídas</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card table-card">
    <div class="card-head" style="padding:1rem 1.2rem 0;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <h2>Bandeja <?= $noLeidas ? '<span class="badge badge-rej" style="margin-left:.4rem">' . $noLeidas . ' sin leer</span>' : '' ?></h2>
        <a href="<?= e(url('alertas') . ($verTodas ? '' : '?ver=todas')) ?>" class="btn btn-ghost btn-sm"><?= $verTodas ? 'Ver solo activas' : 'Ver también resueltas' ?></a>
    </div>
    <div class="table-scroll">
        <table class="grid">
            <thead><tr>
                <th>Alerta</th><th class="prio-3">Detalle</th><th class="prio-2">Cuándo</th><th>Estado</th><th class="acts"></th>
            </tr></thead>
            <tbody>
            <?php if (!$alertas): ?>
                <tr><td colspan="5" class="empty"><?= icon('check', 26) ?><br>No hay alertas <?= $verTodas ? '' : 'activas' ?>. Todo al día 👌</td></tr>
            <?php else: foreach ($alertas as $a):
                [$bc, $bl] = $sevBadge[$a['severidad']] ?? ['badge-neutral', $a['severidad']];
                $leida = !empty($a['leida_en']); $resuelta = !empty($a['resuelta_en']);
            ?>
                <tr style="<?= $leida ? '' : 'font-weight:600' ?>">
                    <td data-label="Alerta">
                        <span class="badge <?= e($bc) ?>"><?= e($bl) ?></span>
                        <?php if ($a['url'] && !$resuelta): ?>
                            <a href="<?= e($a['url']) ?>" style="margin-left:.4rem"><?= e($a['titulo']) ?></a>
                        <?php else: ?>
                            <span style="margin-left:.4rem"><?= e($a['titulo']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Detalle" class="prio-3 muted small"><?= e($a['mensaje']) ?></td>
                    <td data-label="Cuándo" class="prio-2 muted small"><?= e(date('d/m/Y H:i', strtotime((string)$a['created_at']))) ?></td>
                    <td data-label="Estado">
                        <?php if ($resuelta): ?><span class="badge badge-ok">Resuelta</span>
                        <?php elseif ($leida): ?><span class="badge badge-neutral">Leída</span>
                        <?php else: ?><span class="badge badge-pend">Nueva</span><?php endif; ?>
                    </td>
                    <td class="acts" data-label="Acciones">
                        <?php if (!$leida && !$resuelta): ?>
                        <form method="post" action="<?= e(url('alertas')) ?>" class="inline-form">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="leer"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="act" title="Marcar leída"><?= icon('check', 16) ?></button>
                        </form>
                        <?php endif; ?>
                        <?php if (!$resuelta): ?>
                        <form method="post" action="<?= e(url('alertas')) ?>" class="inline-form" data-confirm="¿Marcar esta alerta como resuelta?" data-confirm-ok="Resolver">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="resolver"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="act danger" title="Resolver"><?= icon('x', 16) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<h2 class="sec" style="margin:1.5rem 0 .5rem">Configuración de canales (por tipo de alerta)</h2>
<form method="post" action="<?= e(url('alertas')) ?>" class="form-card card">
    <?= csrf_field() ?><input type="hidden" name="accion" value="config">
    <p class="muted small" style="margin-bottom:1rem">Todas las alertas viven <strong>dentro del sistema</strong>. Activá Email o ITHOS por tipo (WhatsApp se envía a través de ITHOS).</p>
    <div class="table-scroll">
        <table class="grid">
            <thead><tr><th>Tipo de alerta</th><th>Activa</th><th>Email</th><th>ITHOS</th></tr></thead>
            <tbody>
            <?php foreach ($tipos as $k => $t):
                $emailOff = empty($canales['email']['ok']); $ithosOff = empty($canales['ithos']['ok']);
            ?>
                <tr>
                    <td data-label="Tipo"><strong><?= e($t['label']) ?></strong><br><span class="muted small"><?= e($t['desc']) ?></span></td>
                    <td data-label="Activa">
                        <label class="switch"><input type="checkbox" name="activo[<?= e($k) ?>]" value="1" <?= !empty($cfg[$k]['activo']) ? 'checked' : '' ?>><span class="track"><span class="knob"></span></span></label>
                    </td>
                    <td data-label="Email">
                        <label class="switch" title="<?= $emailOff ? 'Configurá el SMTP para habilitarlo' : '' ?>"><input type="checkbox" name="email[<?= e($k) ?>]" value="1" <?= !empty($cfg[$k]['email']) ? 'checked' : '' ?> <?= $emailOff ? 'disabled' : '' ?>><span class="track"><span class="knob"></span></span></label>
                    </td>
                    <td data-label="ITHOS">
                        <label class="switch" title="<?= $ithosOff ? 'Configurá el endpoint de ITHOS para habilitarlo' : '' ?>"><input type="checkbox" name="ithos[<?= e($k) ?>]" value="1" <?= !empty($cfg[$k]['ithos']) ? 'checked' : '' ?> <?= $ithosOff ? 'disabled' : '' ?>><span class="track"><span class="knob"></span></span></label>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions" style="margin-top:1rem">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?>Guardar configuración</button>
    </div>
</form>

<h2 class="sec" style="margin:1.5rem 0 .5rem">Canales externos</h2>
<div class="cards" style="margin-bottom:1rem">
    <?php foreach (['sistema' => 'Dentro del sistema', 'email' => 'Email', 'ithos' => 'ITHOS (Jarvis)'] as $ck => $cl): ?>
        <div class="card">
            <div class="card-head"><h2 style="font-size:14px"><?= e($cl) ?></h2>
                <span class="badge <?= !empty($canales[$ck]['ok']) ? 'badge-ok' : 'badge-neutral' ?>"><?= !empty($canales[$ck]['ok']) ? 'Listo' : 'Falta configurar' ?></span>
            </div>
            <p class="muted small"><?= e($canales[$ck]['detalle']) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<form method="post" action="<?= e(url('alertas')) ?>" class="form-card card">
    <?= csrf_field() ?><input type="hidden" name="accion" value="ithos">
    <div class="grid-fields">
        <label class="fld">
            <span>Entorno</span>
            <select name="entorno">
                <option value="testing" <?= ($entorno ?? 'testing') === 'testing' ? 'selected' : '' ?>>Testing (envía con prefijo [TEST])</option>
                <option value="produccion" <?= ($entorno ?? '') === 'produccion' ? 'selected' : '' ?>>Producción (sin prefijo)</option>
            </select>
            <em class="muted small">En testing, todo lo externo dice [TEST].</em>
        </label>
        <label class="fld span-all">
            <span>Destinatarios de Email (opcional)</span>
            <input type="text" name="email_to" value="<?= e($emailTo) ?>" placeholder="Por default, todos los administradores">
            <em class="muted small">Separá con coma (uno o muchos). Si se deja vacío, se avisa a los administradores.</em>
        </label>
    </div>
    <div class="form-actions" style="margin-top:1rem">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?>Guardar</button>
    </div>
</form>
