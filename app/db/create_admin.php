<?php
// Crea o actualiza el usuario administrador. Uso (CLI):
//   TICKETS_CONFIG=/ruta/config.php php create_admin.php <email> [password] [nombre]
declare(strict_types=1);
require '/home/u566451014/domains/intellihelp.tech/public_html/comprobantes/app/bootstrap.php';

$email  = strtolower(trim($argv[1] ?? 'bsierra4262@gmail.com'));
$pass   = $argv[2] ?? substr(bin2hex(random_bytes(8)), 0, 12);
$nombre = $argv[3] ?? 'Administrador';
$hash   = password_hash($pass, PASSWORD_DEFAULT);

q('INSERT INTO usuarios (nombre, email, password_hash, rol, activo)
   VALUES (?, ?, ?, "admin", 1)
   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), rol = "admin", activo = 1, nombre = VALUES(nombre)',
  [$nombre, $email, $hash]);

echo "ADMIN_EMAIL=$email\n";
echo "ADMIN_PASS=$pass\n";
echo "OK\n";
