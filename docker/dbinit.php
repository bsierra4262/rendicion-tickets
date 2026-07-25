<?php
declare(strict_types=1);
// Importa app/db/schema.sql si la base todavía no tiene las tablas.
// No requiere el cliente mysql: usa PDO.

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'tickets';
$user = getenv('DB_USER') ?: 'tickets';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "  Base no disponible aún: " . $e->getMessage() . "\n");
    exit(0); // no bloquear el arranque
}

$existe = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
if ($existe) {
    echo "  Esquema ya presente; no se toca.\n";
    exit(0);
}

$sqlFile = '/var/www/html/app/db/schema.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "  No se encontró schema.sql\n");
    exit(0);
}

echo "  Importando esquema inicial ...\n";
$pdo->exec((string)file_get_contents($sqlFile));
echo "  Esquema importado correctamente.\n";
