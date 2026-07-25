<?php
declare(strict_types=1);

// ============================================================
//  Configuración desde VARIABLES DE ENTORNO (Docker / 12-factor).
//  Se usa automáticamente cuando no existe un archivo config.php.
//  No contiene secretos: los toma del entorno en tiempo de ejecución.
// ============================================================

$env = static function (string $k, string $def = ''): string {
    $v = getenv($k);
    return ($v !== false && $v !== '') ? $v : $def;
};
$bool = static function (string $k, bool $def): bool {
    $v = strtolower((string)getenv($k));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true))  { return true; }
    if (in_array($v, ['0', 'false', 'no', 'off'], true)) { return false; }
    return $def;
};

return [
    'db' => [
        'host'    => $env('DB_HOST', 'db'),
        'name'    => $env('DB_NAME', 'tickets'),
        'user'    => $env('DB_USER', 'tickets'),
        'pass'    => $env('DB_PASS', ''),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
    ],
    'app' => [
        'name'      => $env('APP_NAME', 'Rendición de Tickets'),
        'org'       => $env('APP_ORG', 'INTELLIHELP'),
        'base_path' => $env('APP_BASE_PATH', ''),
        'env'       => $env('APP_ENV', 'prod'),
        'timezone'  => $env('APP_TIMEZONE', 'America/Argentina/Buenos_Aires'),
        'storage'   => $env('APP_STORAGE', '/var/www/storage'),
        'secret'    => $env('APP_SECRET', ''),
        'idle_ttl'  => (int)$env('APP_IDLE_TTL', '600'),
        'abs_ttl'   => (int)$env('APP_ABS_TTL', '28800'),
        'brand'     => [
            'primary' => $env('APP_BRAND_PRIMARY', ''),
            'accent'  => $env('APP_BRAND_ACCENT', ''),
            'logo'    => $env('APP_BRAND_LOGO', ''),
        ],
    ],
    'google' => [
        'client_id'      => $env('GOOGLE_CLIENT_ID', ''),
        'client_secret'  => $env('GOOGLE_CLIENT_SECRET', ''),
        'allowed_domain' => $env('GOOGLE_ALLOWED_DOMAIN', ''),
        'auto_provision' => $bool('GOOGLE_AUTO_PROVISION', true),
    ],
];
