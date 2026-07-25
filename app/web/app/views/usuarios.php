<?php
/** @var array $usuarios @var int|null $me */
?>
<div class="page-head">
    <div>
        <h1>Usuarios</h1>
        <p class="muted">Gestión del equipo · roles y acceso</p>
    </div>
</div>

<div class="flash flash-info">
    Los usuarios se crean solos al ingresar por Google (rol <strong>Carga</strong>). Acá podés ascender a <strong>Admin</strong> o desactivar accesos.
</div>

<div class="cards" style="margin-bottom:1rem">
    <div class="card">
        <div class="card-head"><h2>Administrador</h2><span class="badge badge-ok">acceso total</span></div>
        <p class="muted small" style="margin:0">Cargar, editar, escanear, verificar y <strong>eliminar</strong> comprobantes · Reportes y PDF · Gestionar usuarios y tokens de la API.</p>
    </div>
    <div class="card">
        <div class="card-head"><h2>Carga</h2><span class="badge badge-pend">operativo</span></div>
        <p class="muted small" style="margin:0">Cargar, editar, escanear y <strong>verificar</strong> comprobantes · Ver reportes. <strong>No</strong> puede eliminar ni gestionar usuarios/API.</p>
    </div>
</div>

<div class="card table-card">
    <div class="table-scroll">
        <table class="grid">
            <thead>
                <tr><th>Usuario</th><th>Email</th><th>Gestión</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td data-label="Usuario">
                        <div class="uinfo">
                            <?php if (!empty($u['avatar_url'])): ?>
                                <img class="avatar" src="<?= e($u['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <span class="avatar-fallback"><?= e(mb_strtoupper(mb_substr((string)$u['nombre'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <span><?= e($u['nombre']) ?><?= $u['id'] == $me ? ' <span class="muted small">(vos)</span>' : '' ?></span>
                        </div>
                    </td>
                    <td data-label="Email" class="muted"><?= e($u['email']) ?></td>
                    <td data-label="Gestión">
                        <form method="post" action="<?= e(url('usuario-guardar')) ?>" class="urow">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <select name="rol">
                                <option value="carga" <?= $u['rol'] === 'carga' ? 'selected' : '' ?>>Carga</option>
                                <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <label class="chk"><input type="checkbox" name="activo" value="1" <?= (int)$u['activo'] === 1 ? 'checked' : '' ?>> Activo</label>
                            <span class="muted small"><?= $u['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : 'nunca' ?></span>
                            <button type="submit" class="btn btn-ghost btn-sm">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
