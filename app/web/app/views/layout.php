<?php
/** Layout principal. Requiere en scope: $content, $title. */
/** @var string $content @var string $title */
$u = auth_user();
$route = current_route();
$appName = $GLOBALS['CONFIG']['app']['name'] ?? 'Rendición de Tickets';
$nav = [
    'dashboard'    => ['Panel', 'dashboard'],
    'comprobantes' => ['Comprobantes', 'receipt'],
    'gastos'       => ['Gastos', 'repeat'],
    'reportes'     => ['Reportes', 'chart'],
    'gproveedores' => ['Proveedores', 'users'],
    'proveedores'  => ['Prov. comprob.', 'users'],
    'categorias'   => ['Conceptos', 'tag'],
];
if ($u && ($u['rol'] ?? '') === 'admin') {
    $nav['usuarios'] = ['Usuarios', 'users'];
    $nav['papelera'] = ['Papelera', 'trash'];
    $nav['api-keys'] = ['API', 'key'];
    $nav['cron']     = ['CRON', 'activity'];
}
// Campana + popup: solo admin. (La evaluación/envío de alertas corre DESPUÉS de responder,
// en index.php, para cualquier usuario y sin bloquear la página.)
$alertasNoLeidas = 0; $alertaToast = null;
if ($u && ($u['rol'] ?? '') === 'admin' && function_exists('alertas_no_leidas')) {
    $alertasNoLeidas = alertas_no_leidas();
    if ($alertasNoLeidas && function_exists('alerta_ultima_no_leida')) { $alertaToast = alerta_ultima_no_leida(); }
}
$asset = fn($p) => e(url($p));
$extraHead = $extraHead ?? '';
$extraFoot = $extraFoot ?? '';
// Marca (white-label): color y logo configurables por config/entorno.
$brand = $GLOBALS['CONFIG']['app']['brand'] ?? [];
$brandLogo = !empty($brand['logo']) ? $brand['logo'] : 'assets/img/ITH-logo-139x75.png';
$brandOrg  = $GLOBALS['CONFIG']['app']['org'] ?? 'INTELLIHELP';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#663399">
    <?php if ($u): ?><meta name="csrf" content="<?= e(csrf_token()) ?>"><?php endif; ?>
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="icon" href="<?= $asset('assets/img/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= $asset('assets/img/ITH-logo-80x80.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Saira:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $asset('assets/css/app.css') ?>?v=20">
    <?php if (!empty($brand['primary']) || !empty($brand['accent'])): ?>
    <style>:root{<?php if (!empty($brand['primary'])): ?>--primary:<?= e($brand['primary']) ?>;<?php endif; ?><?php if (!empty($brand['accent'])): ?>--accent:<?= e($brand['accent']) ?>;<?php endif; ?>}</style>
    <?php endif; ?>
    <?php if ($u): ?>
    <script>
    // Logger de auditoría al CRON: todo evento del cliente (OCR, motor, errores) se registra.
    window.CSRF = <?= json_encode(csrf_token()) ?>;
    window.appLog = function (a, d, t) {
        try {
            fetch(<?= json_encode(url('api-log')) ?>, {
                method: 'POST', keepalive: true,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'accion=' + encodeURIComponent(a || '') + '&detalle=' + encodeURIComponent(d || '') +
                      '&tipo=' + encodeURIComponent(t || 'accion') + '&_csrf=' + encodeURIComponent(window.CSRF || '')
            }).catch(function () {});
        } catch (e) {}
    };
    // Cualquier error JS no capturado también va al CRON (auditoría completa).
    window.addEventListener('error', function (e) {
        if (window.appLog) window.appLog('js.error', (e.message || 'error') + ' @ ' + ((e.filename || '').split('/').pop()) + ':' + (e.lineno || ''), 'error');
    });
    window.addEventListener('unhandledrejection', function (e) {
        if (window.appLog) window.appLog('js.error', 'promesa: ' + ((e.reason && (e.reason.message || e.reason)) || 'rechazo'), 'error');
    });
    // Config del auto-cierre de sesión (lo consume idle.js).
    window.__sesion = {
        idle: <?= (int)($GLOBALS['CONFIG']['app']['idle_ttl'] ?? 600) ?>,
        abs: <?= (int)($GLOBALS['CONFIG']['app']['abs_ttl'] ?? 28800) ?>,
        loginAt: <?= (int)($_SESSION['login_time'] ?? time()) ?>,
        base: <?= json_encode(url('')) ?>
    };
</script>
    <?php endif; ?>
    <?= $extraHead ?>
</head>
<body class="<?= $u ? 'app' : 'auth' ?>">
<?php if ($u):
    $ini = mb_strtoupper(mb_substr((string)($u['nombre'] ?? '?'), 0, 1));
?>
    <header class="topbar">
        <div class="brand">
            <a href="<?= $asset('dashboard') ?>" aria-label="Inicio">
                <img src="<?= $asset($brandLogo) ?>" alt="<?= e($brandOrg) ?>" class="brand-logo">
            </a>
        </div>
        <button class="nav-toggle" aria-label="Menú" onclick="document.body.classList.toggle('nav-open')"><?= icon('menu', 20) ?></button>
        <nav class="topnav">
            <?php foreach ($nav as $r => [$label, $ic]): ?>
                <a href="<?= $asset($r) ?>" class="<?= $route === $r ? 'active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($u && ($u['rol'] ?? '') === 'admin'): ?>
            <a href="<?= $asset('alertas') ?>" class="bell <?= $route === 'alertas' ? 'active' : '' ?>" title="Alertas" aria-label="Alertas">
                <?= icon('alert', 20) ?>
                <?php if ($alertasNoLeidas): ?><span class="bell-badge"><?= $alertasNoLeidas > 99 ? '99+' : (int)$alertasNoLeidas ?></span><?php endif; ?>
            </a>
        <?php endif; ?>
        <div class="user-box">
            <div class="user-meta">
                <span class="user-name"><?= e($u['nombre']) ?></span>
                <span class="user-rol"><?= e($u['rol'] === 'admin' ? 'Administrador' : 'Carga') ?></span>
            </div>
            <?php if (!empty($u['avatar_url'])): ?>
                <img class="avatar" src="<?= e($u['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
            <?php else: ?>
                <span class="avatar-fallback"><?= e($ini) ?></span>
            <?php endif; ?>
            <a class="btn-logout" href="<?= $asset('logout') ?>"><?= icon('logout', 16) ?>Salir</a>
        </div>
    </header>
    <main class="content">
        <?php foreach (take_flash() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
    <footer class="foot"><?= e($appName) ?> · <?= e($brandOrg) ?> — <?= date('Y') ?></footer>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<?php if ($alertaToast): $sevc = ['danger' => 'toast-danger', 'warn' => 'toast-warn', 'info' => 'toast-info'][$alertaToast['severidad']] ?? 'toast-info'; ?>
    <div id="alertToast" class="alert-toast <?= $sevc ?>" data-alert-id="<?= (int)$alertaToast['id'] ?>" data-count="<?= (int)$alertasNoLeidas ?>" role="status" hidden>
        <div class="alert-toast__ic"><?= icon('alert', 18) ?></div>
        <div class="alert-toast__body">
            <strong><?= e($alertaToast['titulo']) ?></strong>
            <p><?= e(mb_strimwidth((string)$alertaToast['mensaje'], 0, 120, '…')) ?></p>
            <a href="<?= $asset('alertas') ?>" class="alert-toast__link">Ver <?= (int)$alertasNoLeidas > 1 ? 'las ' . (int)$alertasNoLeidas . ' alertas' : 'la alerta' ?> →</a>
        </div>
        <button type="button" class="alert-toast__x" aria-label="Cerrar" onclick="this.closest('.alert-toast').remove()"><?= icon('x', 16) ?></button>
    </div>
    <script>
    (function () {
        var t = document.getElementById('alertToast'); if (!t) { return; }
        var id = t.getAttribute('data-alert-id');
        try { if (localStorage.getItem('ith_alert_seen') === id && !location.pathname.match(/alertas$/)) { return; } } catch (e) {}
        t.hidden = false;
        requestAnimationFrame(function () { t.classList.add('show'); });
        try { localStorage.setItem('ith_alert_seen', id); } catch (e) {}
        setTimeout(function () { t.classList.remove('show'); setTimeout(function () { if (t.parentNode) t.remove(); }, 300); }, 9000);
    })();
    </script>
<?php endif; ?>
<?php if ($u && ($u['rol'] ?? '') === 'admin'): ?>
<script>
// Heartbeat de alertas: con la app abierta, evalúa y avisa cada 60s aunque estés idle en
// cualquier sección (no depende de navegar ni de estar parado en «Alertas»).
(function () {
    var CHECK = <?= json_encode(url('alertas-check')) ?>, ALERTAS = <?= json_encode(url('alertas')) ?>;
    var SEV = { danger: 'toast-danger', warn: 'toast-warn', info: 'toast-info' };
    function setBadge(n) {
        var bell = document.querySelector('.bell'); if (!bell) { return; }
        var b = bell.querySelector('.bell-badge');
        if (n > 0) { if (!b) { b = document.createElement('span'); b.className = 'bell-badge'; bell.appendChild(b); } b.textContent = n > 99 ? '99+' : n; }
        else if (b) { b.remove(); }
    }
    function toast(a) {
        var old = document.getElementById('alertToast'); if (old) { old.remove(); }
        var t = document.createElement('div');
        t.id = 'alertToast'; t.className = 'alert-toast ' + (SEV[a.severidad] || 'toast-info');
        t.innerHTML = '<div class="alert-toast__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>'
            + '<div class="alert-toast__body"><strong></strong><p></p><a class="alert-toast__link" href="' + ALERTAS + '">Ver la alerta →</a></div>'
            + '<button type="button" class="alert-toast__x" aria-label="Cerrar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        t.querySelector('strong').textContent = a.titulo || 'Alerta';
        t.querySelector('p').textContent = a.mensaje || '';
        t.querySelector('.alert-toast__x').addEventListener('click', function () { t.remove(); });
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('show'); });
        setTimeout(function () { t.classList.remove('show'); setTimeout(function () { if (t.parentNode) { t.remove(); } }, 300); }, 9000);
    }
    function tick() {
        fetch(CHECK, { headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.ok) { return; }
            setBadge(d.n || 0);
            if (d.last) {
                var seen = null; try { seen = localStorage.getItem('ith_alert_seen'); } catch (e) {}
                if (String(d.last.id) !== seen) { toast(d.last); try { localStorage.setItem('ith_alert_seen', String(d.last.id)); } catch (e) {} }
            }
        }).catch(function () {});
    }
    setInterval(tick, 60000);
    setTimeout(tick, 8000);   // primer chequeo a los 8s de abrir
})();
</script>
<?php endif; ?>
<script defer src="<?= $asset('assets/js/viewer.js') ?>?v=5"></script>
<script defer src="<?= $asset('assets/js/confirm.js') ?>?v=1"></script>
<script defer src="<?= $asset('assets/js/filefield.js') ?>?v=1"></script>
<script defer src="<?= $asset('assets/js/combobox.js') ?>?v=7"></script>
<script defer src="<?= $asset('assets/js/datepicker.js') ?>?v=4"></script>
<?php if ($u): ?><script defer src="<?= $asset('assets/js/idle.js') ?>?v=1"></script><?php endif; ?>
<?= $extraFoot ?>
</body>
</html>
