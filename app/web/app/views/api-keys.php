<?php
/** @var array $tokens @var array|null $nueva */
$host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'intellihelp.tech');
$b = $host . url('api');
?>
<div class="page-head">
    <div>
        <h1>API y tokens</h1>
        <p class="muted">Permití que ITHOS u otro servicio autorizado consulte los datos en tiempo real, con permisos por token.</p>
    </div>
</div>

<div class="flash flash-info">
    <strong>Dos tipos de token (permisos):</strong>
    <strong>Lectura</strong> — solo consultar (estado, comprobantes, pendientes y alertas); ideal para ITHOS. ·
    <strong>Root</strong> — acceso total, sin límites (para integrar la app en un sistema propio).
    Se autentica con el header <code class="mono">X-API-Key</code> (o <code class="mono">Authorization: Bearer</code>).
</div>

<?php if (!empty($ithosKey)): ?>
<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>Token de ITHOS (por defecto)</h2><span class="badge badge-info">Lectura</span></div>
    <p class="muted small" style="margin:.2rem 0 .7rem">Ya viene generado. Sirve para <strong>enviar alertas y hacer consultas</strong> a nuestra app desde ITHOS (un solo token, solo lectura). Copialo y pegalo en ITHOS.</p>
    <div class="token-box"><code class="mono" id="ithosKey"><?= e($ithosKey) ?></code>
        <button type="button" class="btn btn-sm btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById('ithosKey').textContent).then(()=>this.textContent='Copiado')">Copiar</button>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><h2>Endpoints</h2></div>
    <div class="table-scroll">
        <table class="grid">
            <thead><tr><th>Endpoint (GET)</th><th>Devuelve</th></tr></thead>
            <tbody>
                <tr><td data-label="Endpoint"><code class="mono"><?= e($b) ?>/estado</code></td><td data-label="Devuelve">Resumen: totales, pendientes, por moneda y por concepto</td></tr>
                <tr><td data-label="Endpoint"><code class="mono"><?= e($b) ?>/pendientes</code></td><td data-label="Devuelve">Comprobantes «pendientes de revisión»</td></tr>
                <tr><td data-label="Endpoint"><code class="mono"><?= e($b) ?>/comprobantes</code></td><td data-label="Devuelve">Lista con filtros: <code class="mono">?periodo=&amp;estado=&amp;concepto_id=&amp;moneda=&amp;q=&amp;page=</code></td></tr>
                <tr><td data-label="Endpoint"><code class="mono"><?= e($b) ?>/alertas</code></td><td data-label="Devuelve">Alertas abiertas (para ITHOS): <code class="mono">?desde=&lt;id&gt;</code></td></tr>
            </tbody>
        </table>
    </div>
    <p class="muted small" style="margin-top:10px">Ejemplo: <code class="mono">curl -H "X-API-Key: TU_TOKEN" <?= e($b) ?>/estado</code></p>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>Probar la API</h2></div>
    <div class="urow">
        <input type="text" id="tkTest" placeholder="Pegá un token (el que creás abajo)" autocomplete="off" style="flex:1;min-width:220px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:9px">
        <button type="button" class="btn btn-ghost" onclick="probarApi('estado')">Probar /estado</button>
        <button type="button" class="btn btn-ghost" onclick="probarApi('pendientes')">/pendientes</button>
    </div>
    <pre id="apiOut" class="apiout" style="display:none"></pre>
</div>

<?php if ($nueva): ?>
    <div class="flash flash-success" style="margin-top:16px">
        <strong>Token «<?= e($nueva['nombre']) ?>» creado</strong> (<?= ($nueva['scope'] ?? 'lectura') === 'root' ? 'ROOT · acceso total' : 'lectura' ?>). Copialo ahora — por seguridad no se vuelve a mostrar.
        <div class="token-box"><code class="mono" id="tk"><?= e($nueva['token']) ?></code>
            <button type="button" class="btn btn-sm btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById('tk').textContent).then(()=>this.textContent='Copiado')">Copiar</button>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>Tokens</h2></div>
    <form method="post" action="<?= e(url('api-keys')) ?>" class="urow" style="margin-bottom:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" placeholder="Nombre del token (para identificar quién lo usa)" maxlength="80" style="flex:1;min-width:220px">
        <select name="scope" title="Permiso del token" style="min-width:200px">
            <option value="lectura">Lectura (consultas)</option>
            <option value="root">Root (acceso total)</option>
        </select>
        <button type="submit" class="btn btn-primary"><?= icon('plus') ?>Crear token</button>
    </form>
    <div class="table-scroll">
        <table class="grid">
            <thead><tr><th>Nombre</th><th>Permiso</th><th class="prio-3">Prefijo</th><th class="num">Usos</th><th class="prio-2">Último uso</th><th>Estado</th><th class="acts"></th></tr></thead>
            <tbody>
            <?php if (!$tokens): ?>
                <tr><td colspan="7" class="empty">Todavía no hay tokens. Creá uno para ITHOS o para tu integración.</td></tr>
            <?php else: foreach ($tokens as $t): $root = ($t['scope'] ?? 'lectura') === 'root'; ?>
                <tr>
                    <td data-label="Nombre"><strong><?= e($t['nombre']) ?></strong></td>
                    <td data-label="Permiso"><span class="badge <?= $root ? 'badge-rej' : 'badge-info' ?>"><?= $root ? 'Root · total' : 'Lectura' ?></span></td>
                    <td data-label="Prefijo" class="prio-3"><code class="mono">ith_<?= e($t['prefijo']) ?>_…</code></td>
                    <td class="num" data-label="Usos"><?= number_format((int)$t['usos'], 0, ',', '.') ?></td>
                    <td data-label="Último uso" class="muted small prio-2"><?= $t['ultimo_uso'] ? e(date('d/m/Y H:i', strtotime($t['ultimo_uso']))) : 'nunca' ?></td>
                    <td data-label="Estado"><span class="badge <?= (int)$t['activo'] === 1 ? 'badge-ok' : 'badge-rej' ?>"><?= (int)$t['activo'] === 1 ? 'Activo' : 'Revocado' ?></span></td>
                    <td class="acts" data-label="Acciones">
                        <form method="post" action="<?= e(url('api-keys')) ?>" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="activo" value="<?= (int)$t['activo'] === 1 ? 0 : 1 ?>">
                            <button type="submit" class="btn btn-sm btn-ghost"><?= (int)$t['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('api-keys')) ?>" style="display:inline" data-confirm="¿Eliminar el token «<?= e($t['nombre']) ?>»? Quien lo use pierde el acceso." data-confirm-danger data-confirm-ok="Eliminar">
                            <?= csrf_field() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="act danger" title="Eliminar"><?= icon('trash', 16) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function probarApi(ep){
    var t=document.getElementById('tkTest').value.trim(), o=document.getElementById('apiOut');
    o.style.display='block';
    if(!t){o.textContent='Pegá un token primero (creá uno abajo y copialo).';return;}
    o.textContent='Consultando '+ep+'…';
    fetch(<?= json_encode($b) ?>+'/'+ep,{headers:{'X-API-Key':t,'Accept':'application/json'}})
      .then(function(r){return r.text();})
      .then(function(x){try{o.textContent=JSON.stringify(JSON.parse(x),null,2);}catch(e){o.textContent=x;}})
      .catch(function(e){o.textContent='Error: '+e;});
}
</script>
