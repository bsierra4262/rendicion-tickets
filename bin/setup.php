<?php
declare(strict_types=1);
// ============================================================
//  Instalación / diagnóstico — funciona en hosting web o Docker.
//  Uso:  php bin/setup.php
//  Detecta PHP + extensiones, localiza la configuración, verifica la base,
//  importa el esquema si falta y prepara el almacenamiento.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de comandos.');
}

$root   = dirname(__DIR__);
$webapp = $root . '/app/web/app';
$fail   = 0;

function chk(bool $ok, string $msg): bool
{
    global $fail;
    echo ($ok ? "[OK] " : "[!!] ") . $msg . "\n";
    if (!$ok) { $fail++; }
    return $ok;
}

echo "== Rendición de Tickets · setup / diagnóstico ==\n\n";

// 1) PHP + extensiones
chk(version_compare(PHP_VERSION, '8.1', '>='), 'PHP ' . PHP_VERSION . ' (>= 8.1)');
foreach (['pdo_mysql', 'mbstring', 'curl', 'fileinfo'] as $ext) {
    chk(extension_loaded($ext), "extensión $ext");
}

// 2) Configuración (mismo orden que bootstrap)
$cfg = null; $fuente = '';
$candidatos = array_filter([
    getenv('TICKETS_CONFIG') ?: null,
    '/home/u566451014/tickets_config/config.php',
    $root . '/app/server-config/config.php',
    $webapp . '/config.local.php',
]);
foreach ($candidatos as $f) {
    if (is_file($f)) { $cfg = require $f; $fuente = $f; break; }
}
if (!$cfg && (getenv('DB_HOST') !== false || getenv('DB_NAME') !== false)) {
    $cfg = require $webapp . '/config_env.php';
    $fuente = 'variables de entorno';
}
if (!chk((bool)$cfg, 'configuración localizada')) {
    echo "\n→ Copiá app/server-config/config.example.php a config.php y completala,\n";
    echo "  o definí variables de entorno (ver .env.example).\n";
    exit(1);
}
echo "     fuente: $fuente\n";

// 3) Base de datos
$db = $cfg['db'];
try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    chk(true, "conexión a la base «{$db['name']}» en {$db['host']}");
} catch (Throwable $e) {
    chk(false, 'conexión a la base: ' . $e->getMessage());
    echo "\n→ Revisá host/usuario/clave/nombre de la base.\n";
    exit(1);
}

// 4) Esquema
$tieneTablas = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
if ($tieneTablas) {
    chk(true, 'esquema ya presente (no se toca)');
} else {
    $sql = $root . '/app/db/schema.sql';
    if (is_file($sql)) {
        $pdo->exec((string)file_get_contents($sql));
        chk(true, 'esquema importado desde app/db/schema.sql');
    } else {
        chk(false, 'no se encontró app/db/schema.sql');
    }
}

// 5) Almacenamiento
$storage = $cfg['app']['storage'] ?? ($root . '/storage');
if (!is_dir($storage . '/uploads')) { @mkdir($storage . '/uploads', 0755, true); }
chk(is_dir($storage . '/uploads') && is_writable($storage . '/uploads'),
    "almacenamiento escribible: $storage/uploads");

// 6) Secreto de app
chk(!empty($cfg['app']['secret']) && $cfg['app']['secret'] !== 'GENERAR_UN_SECRET_ALEATORIO_LARGO',
    'app.secret configurado');

echo "\n" . ($fail
    ? "Terminó con $fail observación(es). Revisá las líneas [!!] de arriba.\n"
    : "Todo en orden. La aplicación debería funcionar.\n");
exit($fail ? 1 : 0);
