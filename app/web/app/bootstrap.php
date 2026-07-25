<?php
declare(strict_types=1);

// ---- Localizar la configuración segura (fuera del web root) ----
$configFile = $_SERVER['TICKETS_CONFIG'] ?? (getenv('TICKETS_CONFIG') ?: '');
if (!$configFile || !is_file($configFile)) {
    foreach ([
        '/home/u566451014/tickets_config/config.php',
        dirname(__DIR__) . '/config.local.php',
    ] as $cand) {
        if (is_file($cand)) { $configFile = $cand; break; }
    }
}
if (!$configFile || !is_file($configFile)) {
    http_response_code(500);
    exit('Error: no se encontró la configuración de la aplicación.');
}
$CONFIG = require $configFile;
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
require APP . '/comprobantes.php';
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
}
