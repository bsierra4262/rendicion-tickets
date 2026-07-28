<?php
declare(strict_types=1);

// ---- Localizar la configuración ----
// Orden de resolución:
//   1) archivo indicado por la variable TICKETS_CONFIG (fuera del web root, prod)
//   2) rutas conocidas (Hostinger, repo, local)
//   3) variables de entorno (Docker / 12-factor) → app/config_env.php
$configFile = $_SERVER['TICKETS_CONFIG'] ?? (getenv('TICKETS_CONFIG') ?: '');
if (!$configFile || !is_file($configFile)) {
    foreach ([
        '/home/u566451014/tickets_config/config.php',       // Hostinger (producción)
        dirname(__DIR__, 2) . '/server-config/config.php',  // repo: app/server-config/config.php
        dirname(__DIR__) . '/config.local.php',             // local
    ] as $cand) {
        if (is_file($cand)) { $configFile = $cand; break; }
    }
}
if ($configFile && is_file($configFile)) {
    $CONFIG = require $configFile;
} elseif (getenv('DB_HOST') !== false || getenv('DB_NAME') !== false) {
    $CONFIG = require __DIR__ . '/config_env.php';           // Docker / entorno
} else {
    http_response_code(500);
    exit('Error: no se encontró la configuración (ni archivo config.php ni variables de entorno).');
}
$GLOBALS['CONFIG'] = $CONFIG;

// ---- Constantes ----
define('APP', __DIR__);
define('WEBROOT', dirname(__DIR__));
define('BASE_PATH', rtrim($CONFIG['app']['base_path'] ?? '', '/'));
define('STORAGE', $_SERVER['TICKETS_STORAGE'] ?? ($CONFIG['app']['storage'] ?? (WEBROOT . '/storage')));
define('APP_ENV', $CONFIG['app']['env'] ?? 'prod');

date_default_timezone_set($CONFIG['app']['timezone'] ?? 'UTC');

// ---- Manejo de errores ----
error_reporting(E_ALL);
if (APP_ENV === 'prod') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}

// ---- Núcleo ----
require APP . '/db.php';
require APP . '/helpers.php';
require APP . '/auth.php';
require APP . '/actividad.php';
require APP . '/google.php';
require APP . '/google_drive.php';
require APP . '/comprobantes.php';
require APP . '/gastos.php';
require APP . '/icons.php';
require APP . '/apiauth.php';

// ---- Registro de errores en la actividad (canal app / tipo error) ----
// Cada error queda con un CÓDIGO estable (ERR-XXXXXX) por archivo:línea, para
// identificar qué falló y dónde, y poder buscarlo/arreglarlo. No cambia el
// comportamiento visible salvo dejar traza.
$GLOBALS['SEV_MAP'] = [
    E_WARNING => 'E_WARNING', E_USER_WARNING => 'E_USER_WARNING', E_USER_ERROR => 'E_USER_ERROR',
    E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
    E_ERROR => 'E_ERROR', E_PARSE => 'E_PARSE', E_CORE_ERROR => 'E_CORE_ERROR', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
];

set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    // Ignorar ruido (notices / deprecations / strict); registrar warnings y peores.
    if ($no & (E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_STRICT)) { return false; }
    static $inside = false;
    if (!$inside && function_exists('log_error')) {
        $inside = true;
        try { log_error('error.php', $str, $file, $line, $GLOBALS['SEV_MAP'][$no] ?? ('E#' . $no)); } catch (\Throwable $e) {}
        $inside = false;
    }
    return false; // deja también el manejo estándar de PHP
});

set_exception_handler(static function (\Throwable $e): void {
    if (function_exists('log_error')) {
        log_error('error.excepcion', get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
    }
    error_log('[tickets] ' . $e);
    if (!headers_sent()) { http_response_code(500); }
    echo 'Ocurrió un error inesperado. Ya quedó registrado; reintentá en unos segundos.';
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && function_exists('log_error')) {
        log_error('error.fatal', $e['message'], $e['file'], $e['line'], $GLOBALS['SEV_MAP'][$e['type']] ?? 'FATAL');
    }
});

// ---- Sesión (solo web) ----
if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_PATH ?: '/') . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('ith_tickets');
    session_start();

    // ---- Auto-cierre de sesión (enforcement REAL en el servidor) ----
    // Inactividad (10 min) + sesión máxima (8 h). El cliente (idle.js) solo
    // muestra el aviso; la seguridad la impone el servidor: la próxima request
    // tras el vencimiento destruye la sesión aunque el navegador no colabore.
    if (!empty($_SESSION['uid'])) {
        $now     = time();
        $idleTtl = (int)($CONFIG['app']['idle_ttl'] ?? 600);    // 10 min
        $absTtl  = (int)($CONFIG['app']['abs_ttl']  ?? 28800);  // 8 h
        $idle    = $now - (int)($_SESSION['last_activity'] ?? $now);
        $abs     = $now - (int)($_SESSION['login_time']    ?? $now);
        $venIdle = $idleTtl > 0 && $idle > $idleTtl;
        $venAbs  = $absTtl  > 0 && $abs  > $absTtl;
        if ($venIdle || $venAbs) {
            $motivo = $venIdle ? 'inactividad' : 'expiro';
            $uid    = (int)$_SESSION['uid'];
            if (function_exists('log_app')) {
                try {
                    log_app('sesion.cierre_auto', ['tipo' => 'auth', 'usuario_id' => $uid,
                        'detalle' => 'Cierre automático por ' . ($venIdle ? 'inactividad (10 min)' : 'sesión máxima (8 h)')]);
                } catch (\Throwable $e) {}
            }
            auth_logout();
            // Endpoints que responden JSON (fetch en segundo plano): devolvemos 440
            // para que el cliente reaccione. La navegación normal y los formularios
            // de página completa se redirigen al login con el aviso.
            $rt = function_exists('current_route') ? current_route() : '';
            $jsonRoutes = ['api-sesion', 'api-log', 'api-proveedor', 'api-padron', 'concepto-crear',
                'api/estado', 'api/comprobantes', 'api/pendientes'];
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
                   || in_array($rt, $jsonRoutes, true);
            if ($isAjax) {
                http_response_code(440);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'sesion_expirada', 'motivo' => $motivo]);
                exit;
            }
            redirect('login?expiro=' . $motivo);
        }
        $_SESSION['last_activity'] = $now;
    }
}
