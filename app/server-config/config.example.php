<?php
// ============================================================
//  PLANTILLA de configuración — copiá este archivo a config.php y completá
//  los valores reales. config.php NO se versiona (tiene secretos) → ver .gitignore.
//
//  Dos formas de configurar la app:
//   · Hosting web: este archivo, desplegado FUERA del web root, p. ej.
//     /home/USUARIO/tickets_config/config.php  (no accesible por navegador).
//   · Docker / 12-factor: NO se usa este archivo; la config se toma de
//     variables de entorno (ver .env.example y app/web/app/config_env.php).
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
        'org'       => 'TU_ORGANIZACION',                        // marca del pie / alt del logo
        'base_path' => '/comprobantes',                               // '' si se sirve en la raíz del dominio
        'env'       => 'prod',                                   // 'prod' | 'dev'
        'timezone'  => 'America/Argentina/Buenos_Aires',
        'storage'   => '/home/USUARIO/tickets_storage',          // uploads, fuera del web root
        'secret'    => 'GENERAR_UN_SECRET_ALEATORIO_LARGO',
        'idle_ttl'  => 600,                                      // cierre por inactividad (segundos)
        'abs_ttl'   => 28800,                                    // sesion maxima absoluta (segundos)
        'brand'     => [                                         // white-label (opcional)
            'primary' => '',                                     // color primario, p. ej. '#663399'
            'accent'  => '',                                     // color de acento, p. ej. '#9CC930'
            'logo'    => '',                                     // ruta al logo, p. ej. 'assets/img/mi-logo.png'
        ],
    ],
    'google' => [
        // Login con Google Workspace (OAuth 2.0, app Interna). Consola: Google Cloud → Auth Platform.
        'client_id'      => 'TU_CLIENT_ID.apps.googleusercontent.com',
        'client_secret'  => 'TU_CLIENT_SECRET',                  // GOCSPX-... (NUNCA subir a git)
        'allowed_domain' => 'tudominio.com',                     // hd: solo cuentas de este dominio
        'auto_provision' => true,                                // crear usuarios nuevos del dominio con rol 'carga'
    ],
];
