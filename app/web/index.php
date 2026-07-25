<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$route = current_route();
if ($route === '' || $route === '/') {
    $route = 'home';
}

// Rutas registradas: nombre => ['public' => bool, 'admin' => bool]
$routes = [
    'home'            => ['public' => false],
    'login'           => ['public' => true],
    'google-login'    => ['public' => true],
    'google-callback' => ['public' => true],
    'logout'          => ['public' => false],
    'api-sesion'      => ['public' => true],
    'dashboard'       => ['public' => false],
    'comprobantes'         => ['public' => false],
    'comprobantes-export'  => ['public' => false],
    'comprobante-form'     => ['public' => false],
    'comprobante-eliminar' => ['public' => false],
    'api-proveedor'        => ['public' => false],
    'api-log'              => ['public' => false],
    'concepto-crear'       => ['public' => false],
    'categorias'           => ['public' => false],
    'categoria-guardar'    => ['public' => false],
    'proveedores'          => ['public' => false],
    'proveedor-guardar'    => ['public' => false],
    'escanear'             => ['public' => false],
    'comprobante-imagen'   => ['public' => false],
    'reportes'             => ['public' => false],
    'reporte-pdf'          => ['public' => false],
    'api/estado'           => ['public' => true],
    'api/comprobantes'     => ['public' => true],
    'api/pendientes'       => ['public' => true],
    'api-keys'             => ['public' => false, 'admin' => true],
    'papelera'             => ['public' => false, 'admin' => true],
    'cron'                 => ['public' => false, 'admin' => true],
    'usuarios'             => ['public' => false, 'admin' => true],
    'usuario-guardar'      => ['public' => false, 'admin' => true],
];

if (!isset($routes[$route])) {
    http_response_code(404);
    require APP . '/pages/404.php';
    exit;
}

$def = $routes[$route];
if (empty($def['public']) && !auth_user()) {
    redirect('login');
}
if (!empty($def['admin']) && !auth_is_admin()) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// Registro de navegación para el CRON: cada visita GET a una página real.
// (Las mutaciones se registran en el modelo; la API en api_require; el propio
//  CRON y los endpoints técnicos se excluyen para no ensuciar la bitácora.)
$navRoutes = ['dashboard', 'comprobantes', 'comprobante-form', 'escanear', 'reportes', 'proveedores', 'usuarios', 'api-keys', 'papelera'];
if (function_exists('log_app') && method() === 'GET' && in_array($route, $navRoutes, true) && auth_user()) {
    log_app('ver.' . $route, ['tipo' => 'nav', 'detalle' => 'Ingresó a la página «' . $route . '»']);
}

require APP . '/pages/' . $route . '.php';
