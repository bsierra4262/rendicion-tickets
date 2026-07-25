<?php
// ============================================================
//  PLANTILLA de configuración — copiá este archivo a config.php y completá
//  los valores reales. config.php NO se versiona (tiene secretos) → ver .gitignore.
//  En producción se despliega FUERA del web root:
//  /home/USUARIO/tickets_config/config.php  (no accesible desde el navegador)
// ============================================================
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'TU_BASE_DE_DATOS',
        'user'    => 'TU_USUARIO_DB',
        'pass'    => 'TU_PASSWORD_DB',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'Rendición de Tickets',
        'base_path' => '/tickets',
        'env'       => 'prod',                                   // 'prod' | 'dev'
        'timezone'  => 'America/Argentina/Buenos_Aires',
        'storage'   => '/home/USUARIO/tickets_storage',          // uploads, fuera del web root
        'secret'    => 'GENERAR_UN_SECRET_ALEATORIO_LARGO',
    ],
    'google' => [
        // Login con Google Workspace (OAuth 2.0, app Interna). Consola: Google Cloud → Auth Platform.
        'client_id'      => 'TU_CLIENT_ID.apps.googleusercontent.com',
        'client_secret'  => 'TU_CLIENT_SECRET',                  // GOCSPX-... (NUNCA subir a git)
        'allowed_domain' => 'tudominio.com',                     // hd: solo cuentas de este dominio
        'auto_provision' => true,                                // crear usuarios nuevos del dominio con rol 'carga'
    ],
];
