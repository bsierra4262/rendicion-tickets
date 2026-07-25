<?php
/** @var bool $conectado @var string $parent_id @var string $por @var string $cuando
 *  @var int $respaldados @var int $con_imagen */
$folderUrl = 'https://drive.google.com/drive/folders/' . rawurlencode($parent_id);
?>
<div class="page-head">
    <div>
        <h1><?= icon('cloud', 22) ?> Respaldo en Google Drive</h1>
        <p class="muted">Cada imagen de comprobante se guarda también en Drive, ordenada por año y mes. La copia local siempre se conserva como respaldo.</p>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Estado de la conexión</h2></div>
    <?php if ($conectado): ?>
        <p>
            <span class="badge badge-ok">Conectado</span>
            <?php if ($por !== ''): ?><span class="muted small">por <strong><?= e($por) ?></strong><?= $cuando !== '' ? ' · ' . e(date('d/m/Y H:i', strtotime($cuando))) : '' ?></span><?php endif; ?>
        </p>
        <p class="muted small" style="margin-top:6px">
            Comprobantes respaldados en Drive: <strong><?= number_format($respaldados, 0, ',', '.') ?></strong>
            de <strong><?= number_format($con_imagen, 0, ',', '.') ?></strong> con imagen.
        </p>
        <div class="urow" style="margin-top:14px">
            <form method="post" action="<?= e(url('drive')) ?>" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="accion" value="probar">
                <button type="submit" class="btn btn-ghost"><?= icon('check') ?>Probar conexión</button>
            </form>
            <form method="post" action="<?= e(url('drive')) ?>" style="display:inline"
                  data-confirm="¿Desconectar Google Drive? Los comprobantes nuevos dejarán de respaldarse en Drive (se seguirán guardando localmente)." data-confirm-danger data-confirm-ok="Desconectar">
                <?= csrf_field() ?><input type="hidden" name="accion" value="desconectar">
                <button type="submit" class="btn btn-ghost">Desconectar</button>
            </form>
            <a class="btn btn-ghost" href="<?= e($folderUrl) ?>" target="_blank" rel="noopener">Abrir carpeta en Drive</a>
        </div>
    <?php else: ?>
        <p><span class="badge badge-rej">No conectado</span></p>
        <p class="muted small" style="margin-top:6px">
            Mientras no esté conectado, las imágenes se guardan solo en el servidor (no se pierde nada; se pueden respaldar después).
        </p>
        <div style="margin-top:14px">
            <a class="btn btn-primary" href="<?= e(url('drive-connect')) ?>"><?= icon('cloud') ?>Conectar Google Drive</a>
        </div>
        <p class="muted small" style="margin-top:10px">
            Se abre el consentimiento de Google. Iniciá sesión con la cuenta <strong>dueña de la carpeta</strong> (la de los 24&nbsp;TB) y aceptá el permiso de Drive.
        </p>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>Carpeta raíz</h2></div>
    <p class="muted small">Todos los comprobantes se guardan dentro de esta carpeta, en subcarpetas <code class="mono">AÑO / AÑO MM - Mes</code>.</p>
    <form method="post" action="<?= e(url('drive')) ?>" class="urow" style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="parent">
        <input type="text" name="drive_parent_id" value="<?= e($parent_id) ?>" placeholder="ID o URL de la carpeta de Drive"
               style="flex:1;min-width:260px;padding:.6rem .75rem;border:1px solid var(--line);border-radius:9px">
        <button type="submit" class="btn btn-primary"><?= icon('save') ?>Guardar</button>
    </form>
    <p class="muted small" style="margin-top:8px">Podés pegar la URL completa (<code class="mono">.../folders/XXXX</code>) o solo el ID.</p>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head"><h2>¿Cómo se organizan los archivos?</h2></div>
    <ul class="muted small" style="margin:0;padding-left:18px;line-height:1.7">
        <li>Estructura: <code class="mono">Carpeta raíz / 2026 / 2026 07 - Julio / archivo</code></li>
        <li>Nombre del archivo: <code class="mono">2026-07-19 · Fact A · 00585-00002182 · FALABELLA SA.jpg</code></li>
        <li>La base de datos guarda el <strong>link</strong> a Drive; la imagen local queda como respaldo.</li>
        <li>Si Drive falla o no está conectado, el guardado del comprobante <strong>nunca se rompe</strong>.</li>
    </ul>
</div>
