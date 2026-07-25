<?php
/** Layout principal. Requiere en scope: $content, $title. */
/** @var string $content @var string $title */
$u = auth_user();
$route = current_route();
$appName = $GLOBALS['CONFIG']['app']['name'] ?? 'Rendición de Tickets';
$nav = [
    'dashboard'    => ['Panel', 'dashboard'],
    'comprobantes' => ['Comprobantes', 'receipt'],
    'escanear'     => ['Escanear', 'scan'],
    'reportes'     => ['Reportes', 'chart'],
    'proveedores'  => ['Proveedores', 'users'],
    'categorias'   => ['Conceptos', 'tag'],
];
if ($u && ($u['rol'] ?? '') === 'admin') {
    $nav['usuarios'] = ['Usuarios', 'users'];
    $nav['api-keys'] = ['API', 'key'];
    $nav['cron']     = ['CRON', 'activity'];
}
$asset = fn($p) => e(url($p));
$extraHead = $extraHead ?? '';
$extraFoot = $extraFoot ?? '';
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
    <link rel="stylesheet" href="<?= $asset('assets/css/app.css') ?>?v=14">
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
                <img src="<?= $asset('assets/img/ITH-logo-139x75.png') ?>" alt="INTELLIHELP" class="brand-logo">
            </a>
        </div>
        <button class="nav-toggle" aria-label="Menú" onclick="document.body.classList.toggle('nav-open')"><?= icon('menu', 20) ?></button>
        <nav class="topnav">
            <?php foreach ($nav as $r => [$label, $ic]): ?>
                <a href="<?= $asset($r) ?>" class="<?= $route === $r ? 'active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
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
    <footer class="foot"><?= e($appName) ?> · INTELLIHELP — <?= date('Y') ?></footer>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
<script defer src="<?= $asset('assets/js/viewer.js') ?>?v=3"></script>
<script defer src="<?= $asset('assets/js/confirm.js') ?>?v=1"></script>
<script defer src="<?= $asset('assets/js/filefield.js') ?>?v=1"></script>
<script defer src="<?= $asset('assets/js/combobox.js') ?>?v=7"></script>
<script defer src="<?= $asset('assets/js/datepicker.js') ?>?v=3"></script>
<?= $extraFoot ?>
</body>
</html>
