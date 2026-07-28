<?php
// ============================================================
//  Batería de tests de regresión — INTELLIHELP Tickets
//  Uso local:    php test/run_tests.php
//  Uso servidor: TICKETS_SRC=/ruta/al/app php run_tests.php
//  REQUERIMIENTO: cada bug encontrado agrega un caso acá para
//  que no vuelva a aparecer.
// ============================================================
error_reporting(E_ALL);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/comprobantes');
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'intellihelp.tech';
$GLOBALS['CONFIG'] = [
    'app'    => ['name' => 'Test', 'base_path' => '/comprobantes'],
    'google' => [
        'client_id'      => 'CID.apps.googleusercontent.com',
        'client_secret'  => 'SECRET',
        'allowed_domain' => 'intellihelp.tech',
        'auto_provision' => true,
    ],
];

$SRC = getenv('TICKETS_SRC') ?: (__DIR__ . '/../app/web/app');
require $SRC . '/helpers.php';
require $SRC . '/google.php';
require $SRC . '/google_drive.php';
require $SRC . '/comprobantes.php';
require $SRC . '/gastos.php';
require $SRC . '/actividad.php';

$T = 0; $F = 0; $fails = [];
function ok(string $name, bool $cond): void {
    global $T, $F, $fails;
    $T++;
    if ($cond) { echo "  [OK] $name\n"; }
    else { $F++; $fails[] = $name; echo "  [XX] $name\n"; }
}
function eq(string $name, $got, $exp): void {
    ok($name . "  [exp=" . var_export($exp, true) . " got=" . var_export($got, true) . "]", $got === $exp);
}
function feq(string $name, $got, $exp): void {
    ok($name . "  [exp=$exp got=$got]", abs(((float)$got) - ((float)$exp)) < 0.001);
}
function has(string $name, string $hay, string $needle): void {
    ok($name, strpos($hay, $needle) !== false);
}

echo "== CUIT (modulo 11) ==\n";
eq('valido 30-12345678-1',                 validar_cuit('30123456781'), true);
eq('valido real 30714203416',              validar_cuit('30714203416'), true);
eq('invalido dv 30123456782',              validar_cuit('30123456782'), false);
eq('invalido 10 digitos',                  validar_cuit('3012345678'), false);
eq('acepta con guiones',                   validar_cuit('30-12345678-1'), true);
eq('normaliza',                            normalizar_cuit('30-12345678-1'), '30123456781');
eq('formatea',                             formatear_cuit('30123456781'), '30-12345678-1');

echo "== parse_importe (AR / US) ==\n";
feq('AR 1.234,56',                         parse_importe('1.234,56'), 1234.56);
feq('US 9,669.42  (regresion BUG #2)',     parse_importe('9,669.42'), 9669.42);
feq('AR con simbolo  $ 11.700,00',         parse_importe(' $ 11.700,00 '), 11700.0);
feq('AR 51.000,00',                        parse_importe('$ 51.000,00'), 51000.0);
feq('plano 1500',                          parse_importe('1500'), 1500.0);
feq('vacio -> 0',                          parse_importe(''), 0.0);
feq('float directo',                       parse_importe(1234.5), 1234.5);
feq('solo coma 10,5',                      parse_importe('10,5'), 10.5);
feq('US 1,234.56',                         parse_importe('1,234.56'), 1234.56);

echo "== money ==\n";
eq('ARS 1234.5',                           money(1234.5), '$ 1.234,50');
eq('USD 1000',                             money(1000, 'USD'), 'US$ 1.000,00');
eq('cero',                                 money(0), '$ 0,00');

echo "== periodo ==\n";
eq('periodo_de',                           periodo_de('2026-06-15'), '2026-06');

echo "== Google OAuth ==\n";
$ru = google_redirect_uri();
ok('redirect_uri es HTTPS  (regresion BUG #1)', strpos($ru, 'https://') === 0);
eq('redirect_uri exacto',                  $ru, 'https://intellihelp.tech/comprobantes/google-callback');
$au = google_auth_url('estado123');
has('auth_url response_type=code',         $au, 'response_type=code');
has('auth_url scope openid',               $au, 'scope=openid');
has('auth_url restringe dominio (hd)',     $au, 'hd=intellihelp.tech');
has('auth_url lleva el state',             $au, 'estado123');

echo "== valida_comprobante ==\n";
[$e1, $c1] = valida_comprobante(['fecha_emision' => '2026-06-10', 'tipo_comprobante' => 'Fact A', 'letra' => 'A', 'concepto_id' => '1', 'importe_neto' => '1000', 'tasa_iva' => '21', 'importe_iva' => '210', 'importe_percepcion' => '0', 'importe_total' => '1210']);
ok('comprobante valido sin errores', empty($e1));
feq('total ok = 1210', $c1['importe_total'] ?? 0, 1210);
[$e2] = valida_comprobante(['fecha_emision' => '2026-06-10', 'tipo_comprobante' => 'Fact A', 'concepto_id' => '1', 'cuit' => '30123456789', 'importe_total' => '100']);
ok('CUIT invalido detectado', isset($e2['cuit']));
[$e3] = valida_comprobante(['fecha_emision' => 'no', 'tipo_comprobante' => 'Fact A', 'concepto_id' => '1', 'importe_total' => '100']);
ok('fecha invalida detectada', isset($e3['fecha_emision']));
[$e4] = valida_comprobante(['fecha_emision' => '2026-06-10', 'tipo_comprobante' => 'Fact A', 'concepto_id' => '0', 'importe_total' => '100']);
ok('categoria faltante detectada', isset($e4['concepto_id']));
[$e5, $c5] = valida_comprobante(['fecha_emision' => '2026-06-10', 'tipo_comprobante' => 'TK', 'concepto_id' => '1', 'importe_neto' => '100', 'importe_iva' => '21', 'importe_percepcion' => '0', 'importe_total' => '']);
feq('total autocalculado = 121', $c5['importe_total'] ?? 0, 121);
[$e6] = valida_comprobante(['fecha_emision' => '2026-06-10', 'tipo_comprobante' => 'Fact A', 'concepto_id' => '1', 'importe_neto' => '100', 'importe_iva' => '21', 'importe_total' => '999']);
ok('total inconsistente detectado', isset($e6['importe_total']));

echo "== UI / assets (regresión v2 — polish UX) ==\n";
$WEB    = dirname($SRC);            // .../app/web
$VIEWS  = $SRC . '/views';
$ASSETS = $WEB . '/assets';
$readf  = fn(string $p): string => is_file($p) ? (string)file_get_contents($p) : '';

$layout = $readf("$VIEWS/layout.php");
ok('layout sin Tom Select (removido)',         strpos($layout, 'tom-select') === false);
ok('layout carga filefield.js',                strpos($layout, 'assets/js/filefield.js') !== false);
ok('layout versiona CSS (anti-cache ?v=N)',    (bool)preg_match('/app\.css[^\n]*\?v=\d+/', $layout));

ok('filefield.js existe',                       is_file("$ASSETS/js/filefield.js"));

$comp = $readf("$VIEWS/comprobantes.php");
ok('tabla usa columnas de prioridad',          strpos($comp, 'prio-4') !== false);
ok('tabla ya no tiene columna Fecha suelta',   strpos($comp, '<th>Fecha</th>') === false);
ok('empty-state colspan a 9 (no 10)',          strpos($comp, 'colspan="9"') !== false && strpos($comp, 'colspan="10"') === false);
ok('acciones fijas: th.acts rotulado',         strpos($comp, '<th class="acts">Acciones</th>') !== false);

$esc = $readf("$VIEWS/escanear.php");
$frm = $readf("$VIEWS/comprobante-form.php");
ok('escanear usa filefield',                   strpos($esc, 'data-filefield') !== false);
ok('escanear conserva id=scanFile (OCR)',      strpos($esc, 'id="scanFile"') !== false);
ok('form adjuntar usa filefield',              strpos($frm, 'data-filefield') !== false);

$fields = $readf("$VIEWS/_fields_comprobante.php");
ok('_fields sin class ts (Tom Select fuera)',  strpos($fields, 'class="ts"') === false);

$css = $readf("$ASSETS/css/app.css");
ok('css: contenedor full-width 1500',          strpos($css, 'max-width:1500px') !== false);
ok('css: filefield estilizado',                strpos($css, '.filefield__drop') !== false);
ok('css: prioridad de columnas (acciones visibles)', strpos($css, '.prio-4') !== false);
ok('css: select nativo con caret',             strpos($css, 'appearance:none') !== false);

echo "== actividad / CRON (v2.2) ==\n";
[$w1, $p1] = actividad_where(['canal' => 'app']);
has('where filtra por canal', $w1, 'canal = ?');
eq('canal default = app', $p1[0], 'app');
ok('canal inválido cae a app', actividad_where(['canal' => 'xx'])[1][0] === 'app');
[$w2, $p2] = actividad_where(['canal' => 'api', 'tipo' => 'error', 'q' => 'foo', 'desde' => '2026-07-01', 'hasta' => '2026-07-31']);
eq('canal api respetado', $p2[0], 'api');
has('where incluye tipo', $w2, 'tipo = ?');
has('where incluye rango desde', $w2, 'creado_en >= ?');
has('where incluye rango hasta', $w2, 'creado_en <= ?');
has('where incluye búsqueda', $w2, 'actor LIKE ?');
ok('comp_etiqueta arma etiqueta legible', strpos(comp_etiqueta(['tipo_comprobante' => 'Fact', 'letra' => 'A', 'importe_total' => 1000, 'moneda' => 'ARS']), 'Fact A') !== false);

echo "== UI / assets (regresión v2.2 — CRON, calendario, tabla) ==\n";
$layout2 = $readf("$VIEWS/layout.php");
ok('nav incluye CRON (admin)',                 strpos($layout2, "'cron'") !== false);
$idx = $readf("$WEB/index.php");
ok('ruta cron es admin',                       strpos($idx, "'cron'") !== false && strpos($idx, "'admin' => true") !== false);
ok('módulo actividad.php existe',              is_file("$SRC/actividad.php"));
ok('pages/cron.php existe',                    is_file("$SRC/pages/cron.php"));
ok('views/cron.php existe',                    is_file("$VIEWS/cron.php"));
$boot = $readf("$SRC/bootstrap.php");
ok('bootstrap requiere actividad',             strpos($boot, "actividad.php") !== false);
ok('bootstrap captura errores (handler)',      strpos($boot, 'set_exception_handler') !== false && strpos($boot, 'register_shutdown_function') !== false);
$icons = $readf("$SRC/icons.php");
ok('icono activity agregado',                  strpos($icons, "'activity'") !== false);

$comp2 = $readf("$VIEWS/comprobantes.php");
ok('período comprobantes = calendario',        strpos($comp2, 'type="month"') !== false);
ok('período comprobantes sin <select>',        strpos($comp2, 'name="periodo"') !== false && strpos($comp2, '<select name="periodo"') === false);
ok('totales solo con moneda filtrada',         strpos($comp2, "!empty(\$f['moneda'])") !== false);
$rep2 = $readf("$VIEWS/reportes.php");
ok('período reportes = calendario',            strpos($rep2, 'type="month"') !== false);

$css2 = $readf("$ASSETS/css/app.css");
ok('bug acciones: sin columna sticky',         strpos($css2, 'td.acts{position:sticky') === false);
ok('sin scroll interno (max-height fuera)',    strpos($css2, 'max-height:calc(100vh') === false);
ok('css: control segmentado (pestañas)',       strpos($css2, '.seg-item') !== false);
ok('css: input month estilizado',              strpos($css2, 'input[type="month"]') !== false);

echo "== IP real + navegación + categorías (v2.3) ==\n";
$_SERVER['HTTP_X_FORWARDED_FOR'] = '190.2.3.4, 10.0.0.1';
eq('client_ip toma la 1ª de X-Forwarded-For', client_ip(), '190.2.3.4');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
$_SERVER['REMOTE_ADDR'] = '200.5.5.5';
eq('client_ip cae a REMOTE_ADDR', client_ip(), '200.5.5.5');
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'basura-no-ip';
eq('client_ip ignora IP inválida', client_ip(), '200.5.5.5');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
[$wv] = actividad_where(['canal' => 'app']); // sin desde → piso de 4 meses
has('ventana 4 meses aplica piso de fecha', $wv, 'creado_en >= ?');
[$wh] = actividad_where(['canal' => 'app', 'desde' => '2020-01-01']); // histórico
has('con Desde antiguo consulta histórico', $wh, 'creado_en >= ?');
ok('concepto_create rechaza vacío',  concepto_create('')['ok'] === false);
ok('concepto_create rechaza corto',  concepto_create('a')['ok'] === false);
ok('concepto_create rechaza largo',  concepto_create(str_repeat('x', 61))['ok'] === false);

echo "== UI / assets (regresión v2.3 — CRON live, combobox, IP) ==\n";
ok('combobox.js existe',                       is_file("$ASSETS/js/combobox.js"));
ok('cron-live.js existe',                      is_file("$ASSETS/js/cron-live.js"));
ok('pages/concepto-crear.php existe',          is_file("$SRC/pages/concepto-crear.php"));
$css3 = $readf("$ASSETS/css/app.css");
ok('css: combobox (panel acotado)',            strpos($css3, '.cbx-panel') !== false);
ok('css: toggle En vivo',                      strpos($css3, '.live-toggle') !== false);
$idx3 = $readf("$WEB/index.php");
ok('ruta concepto-crear',                      strpos($idx3, "'concepto-crear'") !== false);
ok('index registra navegación (nav)',          strpos($idx3, "'nav'") !== false && strpos($idx3, 'navRoutes') !== false);
$lay3 = $readf("$VIEWS/layout.php");
ok('layout carga combobox.js',                 strpos($lay3, 'combobox.js') !== false);
ok('layout inyecta meta csrf',                 strpos($lay3, 'name="csrf"') !== false);
$cron3 = $readf("$VIEWS/cron.php");
ok('cron: auto-refresh (cronBody/cronStats)',  strpos($cron3, 'id="cronBody"') !== false && strpos($cron3, 'id="cronStats"') !== false);
ok('cron: toggle en vivo',                     strpos($cron3, 'cronLive') !== false);
ok('cron: avatar del usuario',                 strpos($cron3, 'usuario_avatar') !== false);
ok('cron: badge/filtro navegación',            strpos($cron3, "'nav'") !== false);
ok('cron: nota de ventana 4 meses',            strpos($cron3, 'ventana_default') !== false);
$fields3 = $readf("$VIEWS/_fields_comprobante.php");
ok('form categoría: combobox + crear',         strpos($fields3, 'data-combobox-create') !== false);
$comp3 = $readf("$VIEWS/comprobantes.php");
ok('filtro categoría: combobox',               strpos($comp3, 'data-combobox') !== false);
$act3 = $readf("$SRC/actividad.php");
ok('actividad usa client_ip',                  strpos($act3, 'client_ip') !== false);
ok('actividad: ventana de meses',              strpos($act3, 'ACTIVIDAD_VENTANA_MESES') !== false);
$hlp3 = $readf("$SRC/helpers.php");
ok('helpers define client_ip',                 strpos($hlp3, 'function client_ip') !== false);

echo "== v2.4 — combobox global, datepicker, carpetas, panel ==\n";
$ct = actividad_conteo_tipos('app'); // sin DB local → degrada a ceros sin crashear
ok('conteo_tipos degrada sin tabla',           is_array($ct) && ($ct['total'] ?? null) === 0 && array_key_exists('nav', $ct));

ok('datepicker.js existe',                      is_file("$ASSETS/js/datepicker.js"));
$cbx = $readf("$ASSETS/js/combobox.js");
ok('combobox aplica a TODOS los selects',       strpos($cbx, "querySelectorAll('select')") !== false);
ok('combobox: umbral de búsqueda',              strpos($cbx, 'SEARCH_MIN') !== false);
ok('combobox: crear categoría descubrible',     strpos($cbx, 'Crear concepto nuevo') !== false);
$dp = $readf("$ASSETS/js/datepicker.js");
ok('datepicker: mes y fecha',                   strpos($dp, "type === 'month'") !== false && strpos($dp, 'renderDate') !== false);
ok('datepicker: auto-submit opcional',          strpos($dp, 'data-autosubmit') !== false || strpos($dp, 'autosubmit') !== false);

$css4 = $readf("$ASSETS/css/app.css");
ok('css: datepicker (panel)',                   strpos($css4, '.dp-panel') !== false);
ok('css: carpetas CRON',                        strpos($css4, '.folder') !== false);
ok('css: accesos rápidos (panel)',              strpos($css4, '.quick-item') !== false);
ok('css: combobox sin buscador (chico)',        strpos($css4, '.cbx--nosearch') !== false);

$lay4 = $readf("$VIEWS/layout.php");
ok('layout carga datepicker.js',                strpos($lay4, 'datepicker.js') !== false);
$rep4 = $readf("$VIEWS/reportes.php");
ok('reportes período con auto-submit',          strpos($rep4, 'data-autosubmit') !== false);
$cron4 = $readf("$VIEWS/cron.php");
ok('cron: carpetas por tipo',                   strpos($cron4, "class=\"folders\"") !== false && strpos($cron4, "conteo") !== false);
ok('cron: sin dropdown de Tipo (lo reemplaza carpetas)', strpos($cron4, '<select name="tipo">') === false);
$actv4 = $readf("$SRC/actividad.php");
ok('actividad: conteo por tipo',                strpos($actv4, 'function actividad_conteo_tipos') !== false);
$dash4 = $readf("$VIEWS/dashboard.php");
ok('panel: accesos rápidos activos',            strpos($dash4, 'quick-item') !== false);
ok('panel: sin «Próximos pasos» inactivo',      strpos($dash4, 'Próximos pasos') === false);
$fld4 = $readf("$VIEWS/_fields_comprobante.php");
ok('form: pista para crear categoría',          strpos($fld4, 'Crear concepto nuevo') !== false);

echo "== v2.5 — soft delete, categorías, re-aprobar, errores, dropdowns fijos ==\n";
[$wsd] = comp_where([]);
has('comp_where excluye eliminados (soft delete)', $wsd, 'eliminado_en IS NULL');
ok('concepto_update rechaza vacío',            concepto_update(1, '', true)['ok'] === false);
ok('concepto_update rechaza corto',            concepto_update(1, 'a', true)['ok'] === false);

$cmodel5 = $readf("$SRC/comprobantes.php");
ok('comp_delete es soft (UPDATE, no DELETE físico)', strpos($cmodel5, 'UPDATE comprobantes SET eliminado_en = NOW()') !== false && strpos($cmodel5, 'DELETE FROM comprobantes') === false);
ok('modelo: conceptos_all + concepto_update',  strpos($cmodel5, 'function conceptos_all') !== false && strpos($cmodel5, 'function concepto_update') !== false);

$cbx5 = $readf("$ASSETS/js/combobox.js");
ok('combobox: panel position:fixed (fix overflow)', strpos($cbx5, "position = 'fixed'") !== false);
$dp5 = $readf("$ASSETS/js/datepicker.js");
ok('datepicker: panel position:fixed',         strpos($dp5, "position = 'fixed'") !== false);

ok('pages/categorias.php existe',              is_file("$SRC/pages/categorias.php"));
ok('pages/categoria-guardar.php existe',       is_file("$SRC/pages/categoria-guardar.php"));
ok('views/categorias.php existe',              is_file("$VIEWS/categorias.php"));
$idx5 = $readf("$WEB/index.php");
ok('rutas categorias',                         strpos($idx5, "'categorias'") !== false && strpos($idx5, "'categoria-guardar'") !== false);
$lay5 = $readf("$VIEWS/layout.php");
ok('nav Categorías',                           strpos($lay5, "'categorias'") !== false);
$cf5 = $readf("$VIEWS/comprobante-form.php");
ok('re-aprobar rechazados',                    strpos($cf5, 'value="pendiente"') !== false && strpos($cf5, 'Aprobar') !== false);
$boot5 = $readf("$SRC/bootstrap.php");
ok('bootstrap: captura warnings',              strpos($boot5, 'set_error_handler') !== false);
ok('bootstrap: errores con log_error',         strpos($boot5, 'log_error') !== false);
$act5 = $readf("$SRC/actividad.php");
ok('actividad: log_error con código ERR-',     strpos($act5, 'function log_error') !== false && strpos($act5, "'ERR-'") !== false);
ok('cron: 15 filas por página',                strpos($act5, '$per = 15') !== false);
$cronv5 = $readf("$VIEWS/cron.php");
ok('cron: carpeta Errores siempre visible',    strpos($cronv5, "\$k !== 'error'") !== false);

echo "== v2.6 — eliminar categorías (papelera) + CRON legible ==\n";
$cbx6 = $readf("$ASSETS/js/combobox.js");
ok('combobox: confirma antes de crear',        strpos($cbx6, '¿Crear el concepto') !== false);
$comp6 = $readf("$VIEWS/comprobantes.php");
// El filtro de Comprobantes NO debe ofrecer "crear categoría" (sólo el form de edición);
// pedido del cliente: la creación se hace en la pestaña Categorías / al editar.
ok('filtro Comprobantes SIN crear categoría',   strpos($comp6, 'data-combobox-create') === false);
ok('form edición SÍ permite crear categoría',   strpos($readf("$VIEWS/_fields_comprobante.php"), 'data-combobox-create') !== false);
$model6 = $readf("$SRC/comprobantes.php");
ok('modelo: concepto_delete/restore/deleted',  strpos($model6, 'function concepto_delete') !== false && strpos($model6, 'function concepto_restore') !== false && strpos($model6, 'function conceptos_deleted') !== false);
ok('conceptos_list excluye eliminadas',        strpos($model6, 'activo = 1 AND eliminado_en IS NULL') !== false);
$cg6 = $readf("$SRC/pages/categoria-guardar.php");
ok('categoria-guardar: eliminar + restaurar',  strpos($cg6, "'eliminar'") !== false && strpos($cg6, "'restaurar'") !== false);
$catv6 = $readf("$VIEWS/categorias.php");
ok('vista categorías: eliminar con confirmación', strpos($catv6, 'value="eliminar"') !== false && strpos($catv6, 'data-confirm') !== false);
ok('vista categorías: papelera + restaurar',   strpos($catv6, 'Papelera') !== false && strpos($catv6, 'value="restaurar"') !== false);
$cronv6 = $readf("$VIEWS/cron.php");
ok('cron: acciones en texto legible',          strpos($cronv6, 'accionLabel') !== false && strpos($cronv6, 'Creó un concepto') !== false);
$exp6 = $readf("$SRC/pages/comprobantes-export.php");
ok('exportar queda logueado',                  strpos($exp6, 'comprobantes.exportar') !== false);

echo "== v2.7 — modal de confirmación profesional (sin confirm() nativo) ==\n";
ok('confirm.js existe',                        is_file("$ASSETS/js/confirm.js"));
$conf7 = $readf("$ASSETS/js/confirm.js");
ok('confirm.js expone appConfirm + intercepta data-confirm', strpos($conf7, 'window.appConfirm') !== false && strpos($conf7, 'data-confirm') !== false);
$lay7 = $readf("$VIEWS/layout.php");
ok('layout carga confirm.js',                  strpos($lay7, 'confirm.js') !== false);
$css7 = $readf("$ASSETS/css/app.css");
ok('css: modal-overlay branded',               strpos($css7, '.modal-overlay') !== false);
$cbx7 = $readf("$ASSETS/js/combobox.js");
ok('combobox usa appConfirm (no confirm nativo)', strpos($cbx7, 'window.appConfirm') !== false);
foreach (['comprobantes.php', 'comprobante-form.php', 'categorias.php', 'api-keys.php'] as $vw) {
    $s = $readf("$VIEWS/$vw");
    ok("$vw sin confirm() nativo",             strpos($s, 'return confirm(') === false);
    ok("$vw usa data-confirm",                 strpos($s, 'data-confirm') !== false);
}

echo "== v2.8 — OCR: fixes de sincronización, NaN, QR y parser ==\n";
$esc8 = $readf("$ASSETS/js/escanear.js");
ok('OCR: setVal dispara change (sincroniza combobox/datepicker)', strpos($esc8, "dispatchEvent(new Event('change'") !== false);
ok('OCR: fix NaN (labeled agrupa la alternancia)', strpos($esc8, "'(?:' + label + ')") !== false);
ok('OCR: QR a varias escalas (full-res primero)', strpos($esc8, 'var scales = [1,') !== false);
ok('OCR: total no confunde SUBTOTAL/OTROS', strpos($esc8, 'OTROS)') !== false);
ok('OCR: IVA suma alícuotas', strpos($esc8, 'AL[IÍ]CUOTA') !== false);
ok('OCR: tipos con Tique/Código', strpos($esc8, 'TIQUE') !== false && strpos($esc8, "C[OÓ]D") !== false);
ok('OCR: auto-rotación de la imagen', strpos($esc8, 'ocrWithRotation') !== false && strpos($esc8, 'function rotate') !== false);
ok('OCR: worker + modo recibo (PSM)', strpos($esc8, 'tessedit_pageseg_mode') !== false && strpos($esc8, 'createWorker') !== false);
ok('OCR: preprocesado con upscale', strpos($esc8, '1800 / big') !== false);
$dp8 = $readf("$ASSETS/js/datepicker.js");
ok('datepicker: refleja cambios externos (OCR)', strpos($dp8, "hidden.addEventListener('change'") !== false);
$escv8 = $readf("$VIEWS/escanear.php");
ok('escanear versiona escanear.js', (bool)preg_match('/escanear\.js[^\n]*\?v=\d+/', $escv8));

echo "== v2.10 — OCR: montos, feedback de campos, etapas, reset de caché ==\n";
$esc10 = $readf("$ASSETS/js/escanear.js");
ok('OCR: monto soporta miles sin separador',   strpos($esc10, "var MONEY =") !== false && strpos($esc10, '\\\\d+[.,]\\\\d{2}') !== false);
ok('OCR: parseAndFill devuelve campos cargados', strpos($esc10, 'filled.push') !== false && strpos($esc10, 'return { filled: filled') !== false);
ok('OCR: resumen de detectado/faltante',        strpos($esc10, 'function refreshSummary') !== false && strpos($esc10, 'Completá a mano') !== false);
ok('OCR: progreso por etapas + orientación',     strpos($esc10, "'Buscando orientación '") !== false);
ok('OCR: chequea las 4 orientaciones (sin corte)', strpos($esc10, "queue = [0, 180, 90, 270]") !== false);
ok('OCR: reset de caché (pageshow + resetScan)', strpos($esc10, 'function resetScan') !== false && strpos($esc10, "'pageshow'") !== false);
$cbx10 = $readf("$ASSETS/js/combobox.js");
ok('combobox: + como SVG (centrado)',            strpos($cbx10, 'var PLUS =') !== false && strpos($cbx10, "'<span class=\"cbx-plus\">' + PLUS") !== false);

echo "== v2.11 — OCR: QR robusto, formato ARS, razón social ==\n";
$esc11 = $readf("$ASSETS/js/escanear.js");
ok('QR: recortes + rotaciones (más robusto)',   strpos($esc11, 'function scanQr') !== false && strpos($esc11, 'crops = [') !== false);
ok('QR: detecta formato tique sin datos',        strpos($esc11, '?qr=') !== false || strpos($esc11, 'no traen los datos') !== false);
ok('OCR: importes en formato ARS (fmtAR)',       strpos($esc11, 'function fmtAR') !== false && strpos($esc11, "toLocaleString('es-AR'") !== false);
ok('OCR: aplica fmtAR a total/neto/iva',         strpos($esc11, "setVal('importe_total', fmtAR") !== false);
ok('OCR: extrae razón social (primera línea)',   strpos($esc11, "'Razón social'") !== false);
$cf11 = $readf("$ASSETS/js/comprobante-form.js");
ok('form: autocálculo en formato ARS',           strpos($cf11, "toLocaleString('es-AR'") !== false);

echo "== v2.12 — OCR afinado contra texto real (RapidOCR) ==\n";
$esc12 = $readf("$ASSETS/js/escanear.js");
ok('OCR: total en renglón siguiente (hueco conectores)', strpos($esc12, '[\\\\s$:.\\\\-#*]{0,6}') !== false);
ok('OCR: descarta totales basura (CAP)',         strpos($esc12, 'var CAP = 1e8') !== false);
ok('OCR: PV/Número aunque venga pegado',         strpos($esc12, '(?:\D|^)(\d{4,5})') !== false);
ok('OCR: letra pegada a FACTURA',                strpos($esc12, "FACT\w{1,3}") !== false);
ok('OCR: detección rápida (baja resolución)',    strpos($esc12, 'recognize(src, 640)') !== false && strpos($esc12, 'function finalRead') !== false);

echo "== v2.13 — PaddleOCR (motor mejor) + fallback Tesseract ==\n";
ok('paddleocr.js existe',                       is_file("$ASSETS/js/paddleocr.js"));
$pad = $readf("$ASSETS/js/paddleocr.js");
ok('paddleocr: expone window.PaddleOCR + recognize', strpos($pad, 'window.PaddleOCR') !== false && strpos($pad, 'svc.recognize') !== false);
ok('paddleocr: carga ppu-paddle-ocr por ESM',   strpos($pad, "import('https://esm.sh/ppu-paddle-ocr") !== false);
ok('paddleocr: red de seguridad (timeout→fallback)', strpos($pad, 'setTimeout') !== false && strpos($pad, "finish(false") !== false);
$esc13 = $readf("$ASSETS/js/escanear.js");
ok('escanear: motor PaddleOCR (Tesseract off)',  strpos($esc13, "var engine = 'paddle'") !== false && strpos($esc13, 'function ensureEngine') !== false);
ok('escanear: sin fallback a Tesseract (deshabilitado)', strpos($esc13, "ensureWorker(function () { engine = 'tess'") === false);
ok('escanear: recognize abstrae el motor',      strpos($esc13, 'function recognize(src, maxSide)') !== false);
$escv13 = $readf("$VIEWS/escanear.php");
ok('escanear.php carga paddleocr.js',           strpos($escv13, 'paddleocr.js') !== false);

echo "== v2.14 — Excel-training, importes coherentes, Proveedores, auto-aprendizaje ==\n";
// Importes reconciliados (Neto+IVA+Percep=Total, como la fórmula del Excel)
$esc14 = $readf("$ASSETS/js/escanear.js");
ok('OCR: reconcilia importes (Neto+IVA+Percep=Total)', strpos($esc14, 'total - neto - p') !== false && strpos($esc14, 'total - ivaVal - p') !== false);
ok('OCR: deriva Neto desde Total+Tasa',          strpos($esc14, '(total - p) / (1 + tn / 100)') !== false);
ok('OCR: extrae Percepción',                     strpos($esc14, "labeled(U, 'PERCEP") !== false);
ok('OCR: razón social por etiqueta explícita',   strpos($esc14, 'RAZ[OÓ]N') !== false);
ok('OCR: categorizador por puntaje (CATS)',      strpos($esc14, 'var CATS = [') !== false && strpos($esc14, 'bestScore') !== false);
ok('OCR: barra de progreso',                     strpos($esc14, 'function setProgress') !== false);
// PaddleOCR medium + WebGPU
$pad14 = $readf("$ASSETS/js/paddleocr.js");
ok('PaddleOCR: modelo configurable (small por velocidad)', strpos($pad14, 'var MODEL_TIER =') !== false && strpos($pad14, 'V6_MEDIUM_MODEL') !== false);
ok('PaddleOCR: fija wasmPaths (habilita WebGPU real)',      strpos($pad14, 'wasm.wasmPaths') !== false);
ok('PaddleOCR: usa WebGPU si está disponible',   strpos($pad14, 'isWebGpuAvailable') !== false);
// Auto-entrenamiento de categoría del proveedor
ok('modelo: prov_entrenar_categoria existe',     function_exists('prov_entrenar_categoria'));
$compsrc = $readf("$SRC/comprobantes.php");
ok('modelo: entrena tras guardar (comp_save)',   substr_count($compsrc, 'prov_entrenar_categoria(') >= 3);
ok('modelo: categoría más usada del proveedor',  strpos($compsrc, 'GROUP BY concepto_id') !== false && strpos($compsrc, 'categoria_sugerida_id') !== false);
// Sección Proveedores (ABM)
ok('proveedores: función de listado',            function_exists('proveedores_list'));
ok('proveedores: función de alta/edición',       function_exists('prov_admin_save'));
ok('proveedores: página existe',                 is_file("$SRC/pages/proveedores.php") && is_file("$SRC/pages/proveedor-guardar.php"));
ok('proveedores: vista existe',                  is_file("$VIEWS/proveedores.php"));
ok('proveedores: ruta registrada',               strpos($readf("$WEB/index.php"), "'proveedores'") !== false && strpos($readf("$WEB/index.php"), "'proveedor-guardar'") !== false);
ok('proveedores: link en el nav',                strpos($readf("$VIEWS/layout.php"), "'proveedores'") !== false);
ok('schema: columna categoria_sugerida_id',      strpos($readf(dirname($WEB) . '/db/schema.sql'), 'categoria_sugerida_id') !== false);

echo "== v2.16 — worker (no congela), visor de imagen, QR binarizado ==\n";
$pad16 = $readf("$ASSETS/js/paddleocr.js");
$esc16 = $readf("$ASSETS/js/escanear.js");
ok('PaddleOCR: carga estable (hilo principal + medium)', strpos($pad16, 'V6_MEDIUM_MODEL') !== false && strpos($pad16, 'svc.recognize') !== false);
ok('escanear: cede el hilo entre pasadas (no congela)', strpos($esc16, 'setTimeout(detect, 20)') !== false);
$comp16 = $readf("$VIEWS/comprobantes.php");
ok('visor: imagen forzada a data-type image',   strpos($comp16, "'iframe' : 'image'") !== false);
ok('QR: binarización Otsu para decodificar más', strpos($esc16, 'function otsuBinarize') !== false && strpos($esc16, 'scanQr(base, 1.4, null, true)') !== false);
ok('QR: liviano (copia acotada, no congela)',    strpos($esc16, '1400 / big') !== false);
ok('escanear: cede el hilo antes del trabajo pesado (no congela)', strpos($esc16, 'setTimeout(runScan') !== false);

echo "== v2.18 — código de barras, visor pro, percepción, requerimientos ==\n";
$esc18 = $readf("$ASSETS/js/escanear.js");
$escv18 = $readf("$VIEWS/escanear.php");
ok('barcode: lector ZXing (ITF/AFIP) integrado',  strpos($esc18, 'function tryBarcode') !== false && strpos($esc18, 'function parseAfipBarcode') !== false);
ok('barcode: ZXing cargado en la vista',          strpos($escv18, '@zxing/library') !== false);
ok('barcode: pisa CUIT/tipo/PtoVta y guarda CAE', strpos($esc18, 'barcode.cuit') !== false && strpos($esc18, "'CAE '") !== false);
ok('OCR: percepción también "Perc. IIBB/Ing"',    strpos($esc18, "PERC\\\\.?\\\\s*(?:IIBB|ING") !== false);
ok('OCR: lectura final acotada (velocidad)',      strpos($esc18, 'recognize(canvas, 1200)') !== false);
$comp18 = $readf("$VIEWS/comprobantes.php");
ok('visor: caption con datos del comprobante',    strpos($comp18, 'data-title=') !== false && strpos($comp18, 'data-description=') !== false);
$css18 = $readf("$ASSETS/css/app.css");
ok('visor: caption informativo (título + descripción)', strpos($comp18, 'data-title=') !== false);
ok('requerimientos: documento existe',            is_file(dirname($WEB) . '/../md/REQUERIMIENTOS.md') || is_file("$WEB/../../md/REQUERIMIENTOS.md"));

echo "== v2.19 — parser afinado end-to-end (tiques con etiquetas mal leídas) ==\n";
$esc19 = $readf("$ASSETS/js/escanear.js");
ok('OCR: tique por "1IQUE/TIDUE"/"Cód.083" (OCR malo)', strpos($esc19, 'T[I1][QD]UE') !== false && strpos($esc19, "C[OÓ]D(?:IGO)?\\.?\\s*0?8[0-3]") !== false);
ok('OCR: PV aunque venga "P.U.NO"/"N°0005" (OCR malo)', strpos($esc19, 'P[.\s]*[VU][^\d]{0,6}') !== false);
ok('OCR: número tras "T"/"NO T."/"T0001605"',      strpos($esc19, "(?:N[O0]\\s*)?\\bT[.\\s]*(\\d{6,8})") !== false);

echo "== v2.20 — visor custom, QR/barra nativo, lazy pdf, reset de imágenes ==\n";
$lay20 = $readf("$VIEWS/layout.php");
ok('visor: propio (viewer.js), sin GLightbox',   is_file("$ASSETS/js/viewer.js") && strpos($lay20, 'glightbox') === false && strpos($lay20, 'viewer.js') !== false);
$comp20 = $readf("$VIEWS/comprobantes.php");
ok('visor: usa clase comp-viewer',               strpos($comp20, 'class="comp-viewer"') !== false && strpos($comp20, 'glightbox') === false);
$css20 = $readf("$ASSETS/css/app.css");
ok('visor: estilo branded (.cv-overlay)',        strpos($css20, '.cv-overlay') !== false && strpos($css20, '.gslide-description') === false);
$esc20 = $readf("$ASSETS/js/escanear.js");
ok('QR/barra: BarcodeDetector nativo + fallback', strpos($esc20, 'function scanCodes') !== false && strpos($esc20, 'window.BarcodeDetector') !== false);
ok('perf: pdf.js bajo demanda (lazy)',           strpos($esc20, 'function ensurePdfLib') !== false && strpos($readf("$VIEWS/escanear.php"), 'pdf.min.js') === false);
$mreset = $readf(dirname($WEB) . '/comprobantes.php');
ok('reset: script borra imágenes de uploads',    strpos($readf("$WEB/../../md/REQUERIMIENTOS.md"), 'Imágenes subidas borradas') !== false);

echo "== v2.21 — worker anti-freeze, tiles tickets largos, zoom en visor ==\n";
$pad21 = $readf("$ASSETS/js/paddleocr.js");
$esc21 = $readf("$ASSETS/js/escanear.js");
$vw21  = $readf("$ASSETS/js/viewer.js");
ok('OCR: hilo principal + WebGPU (worker descartado)', strpos($pad21, 'api.mode = ') !== false && strpos($pad21, 'isWebGpuAvailable') !== false);
ok('OCR: registra motor (GPU/WASM) en el CRON',        strpos($pad21, "'ocr.motor'") !== false);
$wk21 = $readf("$ASSETS/js/paddle-worker.js");
ok('worker: corre PaddleOCR en OffscreenCanvas',        strpos($wk21, 'OffscreenCanvas') !== false && strpos($wk21, 'svc.recognize') !== false);
ok('OCR: carga PP-OCRv6 medium por ESM',               strpos($pad21, 'V6_MEDIUM_MODEL') !== false);
ok('tickets largos: lectura por franjas (tiles)',       strpos($esc21, 'function readTiled') !== false && strpos($esc21, 'canvas.height / canvas.width > 1.4') !== false);
ok('visor: zoom (scroll) + pan (arrastrar)',            strpos($vw21, "addEventListener('wheel'") !== false && strpos($vw21, 'zoomAt') !== false);
ok('visor: pellizco táctil (pinch)',                    strpos($vw21, 'touchmove') !== false && strpos($vw21, 'pinchD') !== false);

echo "== v2.22 — auditoría al CRON de eventos del cliente (OCR, motor, errores) ==\n";
ok('CRON: endpoint api-log existe',              is_file("$SRC/pages/api-log.php"));
ok('CRON: ruta api-log registrada',              strpos($readf("$WEB/index.php"), "'api-log'") !== false);
$lay22 = $readf("$VIEWS/layout.php");
ok('CRON: logger global window.appLog',          strpos($lay22, 'window.appLog') !== false && strpos($lay22, 'api-log') !== false);
ok('CRON: captura errores JS no controlados',    strpos($lay22, "addEventListener('error'") !== false && strpos($lay22, 'unhandledrejection') !== false);
$pad22 = $readf("$ASSETS/js/paddleocr.js");
ok('CRON: registra motor OCR (y no-disponible)', strpos($pad22, "'ocr.motor'") !== false && strpos($pad22, "'ocr.no_disponible'") !== false);
$esc22 = $readf("$ASSETS/js/escanear.js");
ok('CRON: registra cada escaneo (QR/OCR)',       strpos($esc22, "'ocr.escaneo'") !== false && strpos($esc22, "'ocr.qr'") !== false);

echo "== v2.24 — orden por columnas + guardado rápido + timeline en CRON ==\n";
$mcomp24 = $readf("$SRC/comprobantes.php");
ok('orden: comp_order con whitelist (default id DESC)', strpos($mcomp24, 'function comp_order') !== false && strpos($mcomp24, "'reciente'  => 'c.id'") !== false);
$vcomp24 = $readf("$VIEWS/comprobantes.php");
ok('orden: encabezados clicables con flechas',   strpos($vcomp24, '$sortTh(') !== false && strpos($vcomp24, 'th-arrow') !== false);
$pad24 = $readf("$ASSETS/js/paddleocr.js");
ok('guardado: libera OCR (destroy) para navegar rápido', strpos($pad24, 'api.destroy') !== false && strpos($pad24, 'svc.destroy') !== false);
$esc24 = $readf("$ASSETS/js/escanear.js");
ok('guardado: submit libera OCR + loguea guardando', strpos($esc24, "'comprobante.guardando'") !== false && strpos($esc24, 'PaddleOCR.destroy') !== false);
ok('CRON: timeline (inicio + tiempo en ms del OCR)', strpos($esc24, "'ocr.inicio'") !== false && strpos($esc24, "(Date.now() - scanT0) + 'ms'") !== false);

echo "== v2.25 — guardado AJAX (no pierde imagen) + franjas + aislamiento ==\n";
$esc25  = $readf("$ASSETS/js/escanear.js");
$pesc25 = $readf("$SRC/pages/escanear.php");
$pad25  = $readf("$ASSETS/js/paddleocr.js");
ok('guardado: AJAX sin recargar (conserva imagen)', strpos($esc25, "'X-Requested-With': 'fetch'") !== false && strpos($esc25, 'function showSaveErrors') !== false);
ok('guardado: servidor responde JSON en AJAX',      strpos($pesc25, 'HTTP_X_REQUESTED_WITH') !== false && strpos($pesc25, "json_encode(['ok'") !== false);
ok('borrador: total no bloquea (se revisa)',        strpos($pesc25, "unset(\$errors['importe_total'])") !== false);
ok('largos: más franjas a máxima calidad',          strpos($esc25, 'Math.ceil(ratio * 6)') !== false && strpos($esc25, '1800 / src.width') !== false);
ok('OCR: intenta multi-hilo si está aislado',       strpos($pad25, 'crossOriginIsolated') !== false && strpos($pad25, 'numThreads') !== false);
ok('escanear: aislamiento cross-origin (COOP/COEP)', strpos($pesc25, 'Cross-Origin-Embedder-Policy') !== false);

echo "== v2.27 — parser afinado contra los 9 tickets (OCR mal escrito) ==\n";
$esc27 = $readf("$ASSETS/js/escanear.js");
ok('tipo: tolera OCR "TIDUE FACTIRA" (tique+letra)', strpos($esc27, 'T[I1][QD]UE') !== false && strpos($esc27, 'FACT\\w{1,3}') !== false);
ok('PV: tolera "P.V. N°0005" (símbolos)',           strpos($esc27, 'P[.\s]*[VU][^\d]{0,6}') !== false);
ok('IVA: tolera OCR "Iua contenido"',               strpos($esc27, 'I[VU]A\\\\s*CONTENIDO') !== false);
ok('CAE: se extrae a Observaciones',                strpos($esc27, "'CAE '") !== false && strpos($esc27, '(\\d{14})') !== false);

echo "== v2.28 — CUIT emisor vs comprador + lookup bidireccional (razón↔CUIT) ==\n";
$esc28 = $readf("$ASSETS/js/escanear.js");
$apiprov = $readf("$SRC/pages/api-proveedor.php");
$escv28 = $readf("$VIEWS/escanear.php");
// CUIT: preferir el emisor; NO tomar el del comprador (INTELLIHELP/consumidor)
ok('CUIT: prefiere emisor y descarta el del comprador', strpos($esc28, 'soloComprador') !== false && strpos($esc28, 'buyerIdx') !== false && strpos($esc28, 'INTELLI') !== false);
// Lookup bidireccional local: CUIT→razón y razón→CUIT
ok('lookup: enrichProveedor bidireccional (cuit + razon)', strpos($esc28, 'function enrichProveedor') !== false && strpos($esc28, "razon=' + encodeURIComponent") !== false);
ok('lookup: api-proveedor acepta razón y resuelve CUIT',   strpos($apiprov, "\$_GET['razon']") !== false && strpos($apiprov, 'resolver_proveedor(') !== false);
ok('modelo: prov_by_razon usado por el motor de resolución', strpos($readf("$SRC/comprobantes.php"), 'prov_by_razon($razon)') !== false);
ok('modelo: prov_by_razon + prov_norm_razon existen',      function_exists('prov_by_razon') && function_exists('prov_norm_razon'));
ok('modelo: prov_by_razon usa levenshtein (tolera OCR)',   strpos($readf("$SRC/comprobantes.php"), 'levenshtein(') !== false);
ok('norm razón: sin acentos/puntuación, mayúsculas',       prov_norm_razon('Falabella S.A.  #18') === 'FALABELLA S A 18' && prov_norm_razon('  hola   mundo ') === 'HOLA MUNDO');
// Razón social: no confunde dirección / etiqueta:valor / fechas
ok('razón: descarta dirección (Piso/Av/Calle) y label:valor', strpos($esc28, 'PIS[OT]') !== false && strpos($esc28, "ln.indexOf(':')") !== false);
// Franjas: mínimo 8 a 1800px (fragmentación agresiva pedida por el cliente)
ok('largos: mínimo 10 franjas a 1800px + solape 22%',      strpos($esc28, 'Math.max(10, Math.min(16') !== false && strpos($esc28, '1800 / src.width') !== false && strpos($esc28, 'over = 0.22') !== false);
// Versión del asset actualizada
ok('escanear.php versiona escanear.js v36',                strpos($escv28, 'escanear.js') !== false && (bool)preg_match('/escanear\.js[^\n]*\?v=36/', $escv28));

echo "== v2.29 — padrón nacional offline (empresas) sin certificado ==\n";
$sch29 = str_replace('`', '', $readf(dirname($WEB) . '/db/schema.sql')); // tolera backticks del dump
$mc29  = $readf("$SRC/comprobantes.php");
$api29 = $readf("$SRC/pages/api-proveedor.php");
$esc29 = $readf("$ASSETS/js/escanear.js");
ok('schema: tabla padron_nacional (cuit PK + FULLTEXT razón)', strpos($sch29, 'CREATE TABLE IF NOT EXISTS padron_nacional') !== false && strpos($sch29, 'FULLTEXT KEY ft_padron_razon') !== false);
ok('modelo: padron_nac_by_cuit + padron_nac_by_razon',        function_exists('padron_nac_by_cuit') && function_exists('padron_nac_by_razon'));
ok('modelo: razón→CUIT nacional por FULLTEXT BOOLEAN',        strpos($mc29, 'MATCH(razon_social) AGAINST') !== false && strpos($mc29, 'IN BOOLEAN MODE') !== false);
ok('cliente: avisa/loguea proveedor del padrón nacional',     strpos($esc29, "d.fuente === 'nacional'") !== false && strpos($esc29, "'ocr.padron_nacional'") !== false);

echo "== v2.30 — motor de resolución con VALIDACIÓN CRUZADA (inequívoco) ==\n";
$mc30  = $readf("$SRC/comprobantes.php");
$api30 = $readf("$SRC/pages/api-proveedor.php");
$esc30 = $readf("$ASSETS/js/escanear.js");
ok('motor: resolver_proveedor + helpers existen',            function_exists('resolver_proveedor') && function_exists('padron_nac_candidates') && function_exists('padron_nac_fuzzy_cuit') && function_exists('prov_razon_core'));
ok('motor: desempata homónimos por cercanía de CUIT (levenshtein ≤2)', strpos($mc30, 'function _prov_pick_por_cuit') !== false && strpos($mc30, 'levenshtein($c') !== false && strpos($mc30, '$bestD <= 2') !== false);
ok('motor: coincidencia inequívoca CUIT+razón = confianza alta', strpos($mc30, "=== \$qcore") !== false && strpos($mc30, "'alta'") !== false && strpos($mc30, "'media'") !== false);
ok('motor: recuperación difusa de CUIT (variantes a 1 edición)', strpos($mc30, 'function padron_nac_fuzzy_cuit') !== false && strpos($mc30, 'substr($c, 0, $i)') !== false);
ok('núcleo: prov_razon_core quita sufijos societarios',      prov_razon_core('FALABELLA S.A.') === 'FALABELLA' && prov_razon_core('YPF SOCIEDAD ANONIMA') === 'YPF' && prov_razon_core('COTO S R L') === 'COTO');
ok('api: usa resolver_proveedor + cuit_ocr + confianza',     strpos($api30, 'resolver_proveedor(') !== false && strpos($api30, "\$_GET['cuit_ocr']") !== false && strpos($api30, "'confianza'") !== false);
ok('cliente: manda cuit_ocr (hint difuso) + corrige CUIT en alta', strpos($esc30, 'cuit_ocr=') !== false && strpos($esc30, "d.confianza === 'alta'") !== false && strpos($esc30, 'cuitOcr:') !== false);

echo "== v2.31 — honestidad del motor + anti-basura (capturas del cliente) ==\n";
$pad31 = $readf("$ASSETS/js/paddleocr.js");
$esc31 = $readf("$ASSETS/js/escanear.js");
$escv31 = $readf("$VIEWS/escanear.php");
// Backend REAL (no hardcodeado): se lee de ort.env.webgpu.device; si no, CPU/WASM
ok('motor: backend real (GPU sólo si ort.env.webgpu.device)', strpos($pad31, 'env.webgpu.device') !== false && strpos($pad31, "'GPU (WebGPU)'") !== false && strpos($pad31, "'CPU (WASM") !== false);
ok('motor: la etiqueta usa el backend real (no hardcodea GPU)', strpos($pad31, 'api.backend = usedGpu') !== false && strpos($esc31, 'P.backend') !== false);
// Limpieza de campos entre escaneos
ok('escaneo: limpia campos previos (no deja valores viejos)', strpos($esc31, 'function clearFields') !== false && strpos($esc31, 'clearFields();') !== false);
// Razón social: rechaza basura (dígitos, símbolos iniciales, palabras de ítems)
ok('razón: rechaza basura (dígitos/símbolos/ítems)',        strpos($esc31, '/^[A-Za-zÁÉÍÓÚÑ]/.test(ln)') !== false && strpos($esc31, 'IMPORTE|SUBTOTAL|NETO|TOTAL') !== false);
// Importes: no completar si Neto+IVA+Percep no da el Total
ok('importes: no completa si son incoherentes',            strpos($esc31, '(neto + ivaVal + p) - total') !== false && strpos($esc31, 'neto = null; ivaVal = null; tn = null') !== false);
ok('escanear.php versiona paddleocr.js v18',               (bool)preg_match('/paddleocr\.js[^\n]*\?v=18/', $escv31));

echo "== v2.32 — orientación por clasificador ONNX (doc_ori 4 clases) ==\n";
$pad32 = $readf("$ASSETS/js/paddleocr.js");
$esc32 = $readf("$ASSETS/js/escanear.js");
$escv32 = $readf("$VIEWS/escanear.php");
ok('orientación: modelo doc_ori presente en el servidor',   is_file("$ASSETS/models/doc_ori.onnx") && filesize("$ASSETS/models/doc_ori.onnx") > 1000000);
ok('orientación: paddleocr carga doc_ori + expone api.orientation', strpos($pad32, "'assets/models/doc_ori.onnx'") !== false && strpos($pad32, 'function detectOrientation') !== false && strpos($pad32, 'api.orientation = detectOrientation') !== false);
ok('orientación: preprocesa 224 + normalización ImageNet',  strpos($pad32, '[0.485, 0.456, 0.406]') !== false && strpos($pad32, '(360 - pred) % 360') !== false);
ok('orientación: escanear usa el modelo con fallback bruteforce', strpos($esc32, 'P.orientation(workCanvas)') !== false && strpos($esc32, 'function bruteForce') !== false && strpos($esc32, "'ocr.orientacion'") !== false);
ok('escanear.php versiona escanear.js v36 + paddleocr v18', (bool)preg_match('/escanear\.js[^\n]*\?v=36/', $escv32) && (bool)preg_match('/paddleocr\.js[^\n]*\?v=18/', $escv32));

echo "== v2.33 — fix resumen (estado real de campos, no lista previa) ==\n";
$esc33b = $readf("$ASSETS/js/escanear.js");
ok('resumen: se calcula del estado REAL de los campos',     strpos($esc33b, 'function refreshSummary') !== false && strpos($esc33b, "q(f[1])") !== false);
ok('resumen: Categoría se evalúa por el valor del combo',   strpos($esc33b, "['Categoría', 'concepto_id']") !== false);
ok('resumen: se refresca tras categoría y padrón (async)',  substr_count($esc33b, 'refreshSummary()') >= 4);
ok('resumen: notas via summaryNotes (no innerHTML +=)',     strpos($esc33b, 'summaryNotes.push') !== false && strpos($esc33b, "statusEl.innerHTML +=") === false);

echo "== v2.35 — dewarp+CRON, multi-foto, auto-orientar preview, rotar en visor ==\n";
$pad35 = $readf("$ASSETS/js/paddleocr.js");
$esc35 = $readf("$ASSETS/js/escanear.js");
$vw35  = $readf("$ASSETS/js/viewer.js");
$escv35 = $readf("$VIEWS/escanear.php");
$lay35 = $readf("$VIEWS/layout.php");
ok('dewarp: re-implementado con logging (import/run/error)', strpos($pad35, 'ocr.dewarp.cargando') !== false && strpos($pad35, 'ocr.dewarp.import_ok') !== false && strpos($pad35, 'ocr.dewarp.run_error') !== false && strpos($pad35, 'function withTimeout') !== false);
ok('dewarp: modelo UVDoc en el servidor',                  is_file("$ASSETS/models/UVDoc.onnx") && filesize("$ASSETS/models/UVDoc.onnx") > 10000000);
ok('dewarp: ADAPTATIVO en escanear (intento logueado)',    strpos($esc35, 'ocr.dewarp.intento') !== false && strpos($esc35, 'typeof P.unwarp') !== false);
ok('CRON: errores de orientación y lectura logueados',     strpos($pad35, 'ocr.orient.error') !== false && strpos($pad35, 'ocr.orient.run_error') !== false && strpos($esc35, 'ocr.lectura_error') !== false);
ok('multi-foto: une varias fotos en un ticket (multiple)', strpos($esc35, 'function loadMultiple') !== false && strpos($escv35, 'multiple') !== false && strpos($esc35, 'ocr.multifoto') !== false);
ok('orientar: endereza el preview y guarda imagen procesada', strpos($esc35, 'workCanvas = rotate(workCanvas, corr)') !== false && strpos($esc35, "fd.set('imagen', blob") !== false);
ok('visor: rotar (⟲ ⟳) además de zoom/pan',                strpos($vw35, 'cv-rot') !== false && strpos($vw35, "rotate(' + rot + 'deg)") !== false && (bool)preg_match('/viewer\.js[^\n]*\?v=5/', $lay35));
ok('visor: rotación LIBRE por esquinas (PC) + imán a 90°',  strpos($vw35, 'cv-handle') !== false && strpos($vw35, 'function startRotate') !== false && strpos($vw35, 'function snap') !== false);
ok('visor: rotación por DOS DEDOS (tablet/celular)',        strpos($vw35, 'function angle') !== false && strpos($vw35, 'rot += (a - pinchA)') !== false);
$cfv = $readf("$VIEWS/comprobante-form.php");
ok('detalle: la imagen abre el visor (comp-viewer) y contempla Drive', strpos($cfv, 'comp-viewer') !== false && strpos($cfv, "drive_file_id") !== false);
$clv = $readf("$VIEWS/comprobantes.php");
ok('lista: muestra miniatura también para imágenes en Drive', strpos($clv, '$hasImg') !== false && strpos($clv, "drive_file_id") !== false);

echo "== v2.36 — auto-cierre de sesión (inactividad 10 min + máx. 8 h) ==\n";
$boot36  = $readf("$SRC/bootstrap.php");
$auth36  = $readf("$SRC/auth.php");
$idx36   = $readf("$WEB/index.php");
$logout36= $readf("$SRC/pages/logout.php");
$loginp36= $readf("$SRC/pages/login.php");
$apises36= $readf("$SRC/pages/api-sesion.php");
$idle36  = $readf("$ASSETS/js/idle.js");
$lay36   = $readf("$VIEWS/layout.php");
ok('sesión: enforcement REAL en el servidor (idle + absoluto)', strpos($boot36, "idle_ttl") !== false && strpos($boot36, "abs_ttl") !== false && strpos($boot36, "last_activity") !== false);
ok('sesión: vencida responde 440 a AJAX y redirige la navegación', strpos($boot36, 'http_response_code(440)') !== false && strpos($boot36, "sesion_expirada") !== false && strpos($boot36, "redirect('login?expiro=") !== false);
ok('sesión: allowlist JSON (api-keys HTML se redirige, no 440)', strpos($boot36, '$jsonRoutes') !== false && strpos($boot36, "'api-proveedor'") !== false && strpos($boot36, "'api-keys'") === false);
ok('sesión: el cierre automático queda en el CRON',        strpos($boot36, "sesion.cierre_auto") !== false);
ok('sesión: login sella login_time + last_activity',       substr_count($auth36, "\$_SESSION['login_time']") >= 2 && substr_count($auth36, "\$_SESSION['last_activity']") >= 2);
ok('sesión: ruta api-sesion registrada (pública, chequea adentro)', strpos($idx36, "'api-sesion'") !== false);
ok('sesión: endpoint keepalive existe y valida auth (440)', $apises36 !== '' && strpos($apises36, 'http_response_code(440)') !== false && strpos($apises36, 'abs_rem') !== false);
ok('sesión: logout propaga el motivo (expiro)',            strpos($logout36, "expiro") !== false && strpos($logout36, "login?expiro=") !== false);
ok('sesión: login avisa por inactividad / máximo',         strpos($loginp36, "\$_GET['expiro']") !== false && strpos($loginp36, 'inactividad') !== false);
ok('sesión: idle.js con keepalive, aviso y cuenta regresiva', $idle36 !== '' && strpos($idle36, 'api-sesion') !== false && strpos($idle36, 'Seguir conectado') !== false && strpos($idle36, 'idleCd') !== false);
ok('sesión: idle.js corta por inactividad y por absoluto', strpos($idle36, "go('inactividad')") !== false && strpos($idle36, "go('expiro')") !== false);
ok('layout: inyecta window.__sesion y carga idle.js v1',   strpos($lay36, 'window.__sesion') !== false && (bool)preg_match('/idle\.js[^\n]*\?v=1/', $lay36));

echo "== v2.37 — respaldo en Google Drive (OAuth offline + subida + fallback) ==\n";
// Funciones PURAS (sin red)
eq('drive: mes en español (7)',            drive_mes_es(7), 'Julio');
eq('drive: mes en español (12)',           drive_mes_es(12), 'Diciembre');
eq('drive: carpeta mensual "AÑO MM - Mes"', drive_carpeta_mes('2026-07-19'), '2026 07 - Julio');
eq('drive: carpeta mensual enero',         drive_carpeta_mes('2026-01-03'), '2026 01 - Enero');
eq('drive: sanitiza barras y control',     drive_sanitizar("FA/LA\\BELLA"), 'FA-LA-BELLA');
eq('drive: mime pdf',                      drive_mime_de('pdf'), 'application/pdf');
eq('drive: mime jpg',                      drive_mime_de('JPG'), 'image/jpeg');
$nom = drive_nombre_archivo([
    'fecha_emision' => '2026-07-19', 'tipo_comprobante' => 'Fact', 'letra' => 'A',
    'punto_venta' => '585', 'numero' => '2182', 'prov_razon' => 'FALABELLA SA',
], 'jpg');
ok('drive: nombre legible ordenable por fecha', strpos($nom, '2026-07-19') === 0 && strpos($nom, 'Fact A') !== false && strpos($nom, '00585-00002182') !== false && strpos($nom, 'FALABELLA SA') !== false && substr($nom, -4) === '.jpg');
ok('drive: nombre sin barras (seguro para Drive)', strpos($nom, '/') === false && strpos($nom, '\\') === false);
$nomVacio = drive_nombre_archivo([], 'pdf');
ok('drive: nombre válido aun con datos faltantes', $nomVacio !== '.pdf' && substr($nomVacio, -4) === '.pdf' && strpos($nomVacio, '/') === false);
// Wiring / integración
$gd37    = $readf("$SRC/google_drive.php");
$boot37  = $readf("$SRC/bootstrap.php");
$idx37   = $readf("$WEB/index.php");
$cb37    = $readf("$SRC/pages/google-callback.php");
$cf37    = $readf("$SRC/pages/comprobante-form.php");
ok('drive: refresca access token con refresh_token',      strpos($gd37, "'grant_type'    => 'refresh_token'") !== false && strpos($gd37, 'function drive_access_token') !== false);
ok('drive: sube por multipart y crea carpetas año/mes',   strpos($gd37, 'multipart/related') !== false && strpos($gd37, 'application/vnd.google-apps.folder') !== false);
ok('drive: bootstrap carga el módulo',                    strpos($boot37, "require APP . '/google_drive.php'") !== false);
ok('drive: comprobante-form sube imagen SOLO a Drive (drive_procesar_imagen)', strpos($cf37, 'drive_procesar_imagen($savedId') !== false && strpos($cf37, 'guardar_imagen(') === false);
$esc37 = $readf("$SRC/pages/escanear.php");
ok('drive: BUGFIX escaneo también sube a Drive (no solo local)', strpos($esc37, 'drive_procesar_imagen($savedId') !== false && strpos($esc37, 'guardar_imagen(') === false);
ok('drive: NADA local — procesar_imagen setea imagen_path=NULL + audita', strpos($gd37, 'function drive_procesar_imagen') !== false && strpos($gd37, 'imagen_path = NULL') !== false && strpos($gd37, "log_app('drive.subida'") !== false && strpos($gd37, "log_app('drive.fallo'") !== false);
ok('drive: audita creación de carpetas (drive.carpeta)', strpos($gd37, "log_app('drive.carpeta'") !== false && strpos($gd37, 'bool &$creada') !== false);
ok('drive: sirve la imagen desde Drive (drive_descargar) + fallback local viejo', strpos($gd37, 'function drive_descargar') !== false && strpos($readf("$SRC/pages/comprobante-imagen.php"), 'drive_descargar') !== false);
ok('drive: desconectar queda auditado en el CRON',        strpos($gd37, "log_app('drive.desconectar'") !== false);
// El portal /drive se eliminó: no debe haber rutas, nav, ni archivos del portal.
ok('drive: portal ELIMINADO (sin rutas ni nav)',          strpos($idx37, "'drive-connect'") === false && strpos($idx37, "'drive'                =>") === false && strpos($readf("$VIEWS/layout.php"), "\$nav['drive']") === false);
ok('drive: archivos del portal borrados',                 !is_file("$SRC/pages/drive.php") && !is_file("$SRC/pages/drive-connect.php") && !is_file("$VIEWS/drive.php"));

echo "== v2.38 — portabilidad: config por entorno, esquema, Docker, white-label ==\n";
$ROOT = dirname($WEB, 2);
// config por entorno
putenv('DB_HOST=testhost'); putenv('DB_NAME=testdb'); putenv('APP_NAME=Marca X');
putenv('APP_ORG=ACME'); putenv('APP_BRAND_PRIMARY=#123456'); putenv('GOOGLE_AUTO_PROVISION=false');
$cenv = require "$SRC/config_env.php";
eq('config_env: db.host desde entorno',      $cenv['db']['host'], 'testhost');
eq('config_env: app.name desde entorno',     $cenv['app']['name'], 'Marca X');
eq('config_env: app.org desde entorno',      $cenv['app']['org'], 'ACME');
eq('config_env: brand.primary desde entorno', $cenv['app']['brand']['primary'], '#123456');
eq('config_env: bool GOOGLE_AUTO_PROVISION=false', $cenv['google']['auto_provision'], false);
eq('config_env: default idle_ttl sin env',   $cenv['app']['idle_ttl'], 600);
foreach (['DB_HOST','DB_NAME','APP_NAME','APP_ORG','APP_BRAND_PRIMARY','GOOGLE_AUTO_PROVISION'] as $k) { putenv($k); }
// bootstrap: fallback a entorno
$boot38 = $readf("$SRC/bootstrap.php");
ok('bootstrap: fallback a config_env por entorno', strpos($boot38, "config_env.php") !== false && strpos($boot38, "getenv('DB_HOST')") !== false && strpos($boot38, 'server-config/config.php') !== false);
// esquema
$sch = $readf("$ROOT/app/db/schema.sql");
ok('schema.sql: existe con las 10 tablas',   substr_count($sch, 'CREATE TABLE') === 10);
ok('schema.sql: incluye las tablas de gastos', strpos($sch, 'CREATE TABLE `gastos_proveedores`') !== false && strpos($sch, 'CREATE TABLE `gastos_recurrentes`') !== false);
ok('schema.sql: incluye columnas de Drive + FULLTEXT del padrón', strpos($sch, 'drive_link') !== false && strpos($sch, 'drive_file_id') !== false && strpos($sch, 'FULLTEXT') !== false);
ok('schema.sql: sin AUTO_INCREMENT= (contadores reseteados)', strpos($sch, 'AUTO_INCREMENT=') === false);
// Docker
ok('docker: Dockerfile (php8.2-apache + pdo_mysql/mbstring + rewrite)', strpos($readf("$ROOT/Dockerfile"), 'php:8.2-apache') !== false && strpos($readf("$ROOT/Dockerfile"), 'docker-php-ext-install pdo_mysql mbstring') !== false);
ok('docker: compose con app + db (mariadb) y volúmenes', strpos($readf("$ROOT/docker-compose.yml"), 'mariadb') !== false && strpos($readf("$ROOT/docker-compose.yml"), 'dbdata') !== false);
ok('docker: entrypoint espera DB + importa esquema', strpos($readf("$ROOT/docker/entrypoint.sh"), 'dbinit.php') !== false && strpos($readf("$ROOT/docker/entrypoint.sh"), 'apache2-foreground') !== false);
ok('docker: vhost DocumentRoot app/web + protege app/', strpos($readf("$ROOT/docker/000-default.conf"), 'DocumentRoot /var/www/html/app/web') !== false && strpos($readf("$ROOT/docker/000-default.conf"), 'Require all denied') !== false);
ok('docker: dbinit importa schema.sql por PDO si falta', strpos($readf("$ROOT/docker/dbinit.php"), 'schema.sql') !== false && strpos($readf("$ROOT/docker/dbinit.php"), "SHOW TABLES LIKE 'usuarios'") !== false);
ok('.env.example con DB + Google + marca',   strpos($readf("$ROOT/.env.example"), 'GOOGLE_CLIENT_ID') !== false && strpos($readf("$ROOT/.env.example"), 'APP_BRAND_PRIMARY') !== false);
// instalador + docs
ok('bin/setup.php diagnostica e importa esquema', strpos($readf("$ROOT/bin/setup.php"), 'extension_loaded') !== false && strpos($readf("$ROOT/bin/setup.php"), 'schema.sql') !== false);
ok('INSTALL.md + BRANDING.md presentes',     $readf("$ROOT/INSTALL.md") !== '' && $readf("$ROOT/BRANDING.md") !== '');
// white-label en el layout
$lay38 = $readf("$VIEWS/layout.php");
ok('layout: inyecta color de marca + logo/organización configurables', strpos($lay38, '--primary:') !== false && strpos($lay38, '$brandLogo') !== false && strpos($lay38, '$brandOrg') !== false);
// gitignore versiona lo nuevo, nunca secretos
$gi = $readf("$ROOT/.gitignore");
ok('.gitignore: versiona Docker/bin/.env.example, excluye config.php y .env', strpos($gi, '!/Dockerfile') !== false && strpos($gi, '!/bin/') !== false && strpos($gi, '!/.env.example') !== false && strpos($gi, 'app/server-config/config.php') !== false && strpos($gi, '/.env') !== false);
// README profesional (sin lenguaje casual)
$rm = $readf("$ROOT/README.md");
ok('README: sin lenguaje casual (tu Excel / sacás / pegá / creá)', stripos($rm, 'tu Excel') === false && stripos($rm, 'sacás') === false && strpos($rm, 'Pegá') === false && strpos($rm, 'Creá') === false);

echo "== v2.39 — portal único: el login conecta Drive (sin portal aparte) ==\n";
$au39 = google_auth_url('st39'); // en tests drive_conectado()=false → debe pedir Drive
ok('login: pide scope Drive + offline cuando no está conectado', strpos($au39, 'access_type=offline') !== false && strpos($au39, urlencode('https://www.googleapis.com/auth/drive')) !== false && strpos($au39, 'prompt=consent') !== false);
$gp39 = $readf("$SRC/google.php");
ok('login: el scope Drive es condicional a !drive_conectado()', strpos($gp39, 'function_exists(\'drive_conectado\') && !drive_conectado()') !== false);
$cb39 = $readf("$SRC/pages/google-callback.php");
ok('login: guarda la conexión de Drive en la sesión', strpos($cb39, 'drive_guardar_sesion($tok)') !== false && strpos($cb39, "strncmp") === false);

echo "== v2.40 — Drive atado a la sesión + portal eliminado (seguridad) ==\n";
$gd40 = $readf("$SRC/google_drive.php");
$au40 = $readf("$SRC/auth.php");
ok('drive: tokens viven en la SESIÓN (no persistentes)', strpos($gd40, "\$_SESSION['drive_refresh_token']") !== false && strpos($gd40, 'function drive_guardar_sesion') !== false && strpos($gd40, "setting_get('drive_refresh_token'") === false);
ok('drive: drive_conectado lee la sesión',             strpos($gd40, "return !empty(\$_SESSION['drive_refresh_token'])") !== false);
ok('drive: desconectar revoca en Google + limpia sesión', strpos($gd40, 'oauth2.googleapis.com/revoke') !== false && strpos($gd40, "unset(\$_SESSION['drive_refresh_token']") !== false);
ok('seguridad: auth_logout desconecta Drive (todo cierre)', strpos($au40, 'drive_desconectar()') !== false && strpos($au40, 'function auth_logout') !== false);
ok('drive: se quitaron las funciones del portal (auth_url/exchange)', strpos($gd40, 'function drive_auth_url') === false && strpos($gd40, 'function drive_exchange_code') === false);

echo "== v2.42 — Drive: archivar/restaurar espejo del soft-delete + visor limpio ==\n";
$gd42 = $readf("$SRC/google_drive.php");
$mc42 = $readf("$SRC/comprobantes.php");
$vw42 = $readf("$ASSETS/js/viewer.js");
ok('drive: archivar mueve a «Archivados»',          strpos($gd42, "function drive_archivar") !== false && strpos($gd42, "drive_folder_id('Archivados'") !== false && strpos($gd42, 'function drive_mover') !== false);
ok('drive: restaurar devuelve a AÑO/mes',           strpos($gd42, 'function drive_restaurar') !== false && strpos($gd42, 'drive_carpeta_mes($fecha)') !== false);
ok('drive: mover usa addParents/removeParents (PATCH)', strpos($gd42, 'addParents=') !== false && strpos($gd42, 'removeParents=') !== false && strpos($gd42, "'PATCH'") !== false);
ok('modelo: eliminar archiva en Drive + audita',    strpos($mc42, 'drive_archivar((string)$c[\'drive_file_id\'])') !== false && strpos($mc42, "'drive.archivar'") !== false);
ok('modelo: restaurar recupera en Drive + audita',  strpos($mc42, 'drive_restaurar((string)$c[\'drive_file_id\']') !== false && strpos($mc42, "'drive.restaurar'") !== false);
ok('visor: esquinas INVISIBLES (sin puntos, background transparent)', strpos($vw42, 'background:transparent;cursor:grab') !== false && strpos($vw42, "border:2px solid var(--primary") === false);
ok('visor: ayuda ADAPTATIVA por dispositivo (no hardcodeada)', strpos($vw42, "matchMedia('(pointer: coarse)')") !== false && strpos($vw42, 'con dos dedos') !== false && strpos($vw42, 'desde las esquinas') !== false);

echo "== v2.43 — Papelera de administrador (recuperar lo archivado) ==\n";
$pap43  = $readf("$SRC/pages/papelera.php");
$papv43 = $readf("$VIEWS/papelera.php");
$idx43  = $readf("$WEB/index.php");
$lay43  = $readf("$VIEWS/layout.php");
$mc43   = $readf("$SRC/comprobantes.php");
ok('papelera: ruta admin registrada',            strpos($idx43, "'papelera'             => ['public' => false, 'admin' => true]") !== false);
ok('papelera: página solo admin + acción restaurar', strpos($pap43, 'require_admin()') !== false && strpos($pap43, "=== 'restaurar'") !== false && strpos($pap43, 'comp_restore(') !== false);
ok('papelera: consulta comp_eliminados (eliminado_en NOT NULL)', strpos($mc43, 'function comp_eliminados') !== false && strpos($mc43, 'c.eliminado_en IS NOT NULL') !== false);
ok('papelera: vista lista archivados + botón Restaurar + quién/cuándo', strpos($papv43, 'restaurar') !== false && strpos($papv43, 'eliminado_en') !== false && strpos($papv43, 'elim_por') !== false);
ok('papelera: en el nav de administrador',        strpos($lay43, "\$nav['papelera']") !== false);

echo "== v2.44 — módulo Gastos Recurrentes (base: maestro + gastos + Drive) ==\n";
// Opciones
eq('gastos: categorías', array_keys(gp_categorias()), ['recurrente', 'unica_vez']);
eq('gastos: momentos de facturación', array_keys(gp_momentos()), ['adelantado', 'vencido']);
// Validación del maestro (pura)
[$e1, $c1] = gp_validar(['nombre' => 'EDENOR', 'rubro' => 'ELECTRICIDAD', 'categoria' => 'recurrente', 'momento_facturacion' => 'vencido']);
ok('gastos: proveedor válido sin errores', empty($e1) && $c1['nombre'] === 'EDENOR' && $c1['categoria'] === 'recurrente' && $c1['momento_facturacion'] === 'vencido');
[$e2] = gp_validar(['nombre' => 'X']);
ok('gastos: nombre corto da error', isset($e2['nombre']));
[$e3] = gp_validar(['nombre' => 'Proveedor', 'cuit' => '30123456782']);
ok('gastos: CUIT inválido da error', isset($e3['cuit']));
[$e4, $c4] = gp_validar(['nombre' => 'Proveedor', 'categoria' => 'basura']);
ok('gastos: categoría inválida cae a recurrente', empty($e4) && $c4['categoria'] === 'recurrente');
// Validación de gastos (proveedor_id=0 no toca DB)
[$e5] = gr_validar(['proveedor_id' => 0, 'periodo' => '2026-07']);
ok('gastos: sin proveedor da error', isset($e5['proveedor_id']));
[$e6] = gr_validar(['proveedor_id' => 0, 'periodo' => 'mal']);
ok('gastos: período inválido da error', isset($e6['periodo']));
// Seed real
$gsrc = $readf("$SRC/gastos.php");
ok('gastos: seed de los 19 proveedores reales', substr_count($gsrc, "'recurrente',") >= 19 && strpos($gsrc, "'EDENOR'") !== false && strpos($gsrc, "'GCBA - ABL'") !== false && strpos($gsrc, "'TELECOM EMPRESAS'") !== false);
// Drive: estructura proveedor/año/mes
$gd44 = $readf("$SRC/google_drive.php");
ok('gastos: Drive sube a Proveedor/Año/Mes (raíz nueva) + audita', strpos($gd44, 'function drive_gasto_subir') !== false && strpos($gd44, "function drive_gastos_root") !== false && strpos($gd44, "'gasto.drive_subida'") !== false);
// Wiring: bootstrap, rutas, nav, páginas
$boot44 = $readf("$SRC/bootstrap.php");
$idx44  = $readf("$WEB/index.php");
$lay44  = $readf("$VIEWS/layout.php");
ok('gastos: bootstrap carga el módulo', strpos($boot44, "require APP . '/gastos.php'") !== false);
ok('gastos: rutas registradas', strpos($idx44, "'gproveedores'") !== false && strpos($idx44, "'gasto-form'") !== false);
ok('gastos: nav con Gastos recurrentes + Proveedores', strpos($lay44, "'gastos'       => ['Gastos recurrentes'") !== false && strpos($lay44, "'gproveedores' => ['Proveedores'") !== false);
ok('gastos: páginas y vistas existen', is_file("$SRC/pages/gproveedores.php") && is_file("$SRC/pages/gasto-form.php") && is_file("$VIEWS/gastos.php") && is_file("$VIEWS/gproveedor-form.php"));
ok('gastos: form sube factura + pago a Drive', strpos($readf("$SRC/pages/gasto-form.php"), "drive_gasto_subir(\$savedId, \$g, 'factura'") !== false && strpos($readf("$SRC/pages/gasto-form.php"), "'pago'") !== false);
// Fase 2: lectura automática de la factura (parser + popup)
$gjs = $readf("$ASSETS/js/gastos.js");
ok('gastos F2: parser de factura (proveedor/valor/fechas) + expuesto', strpos($gjs, 'function parseFactura') !== false && strpos($gjs, 'window.parseFacturaGasto') !== false && strpos($gjs, 'function detectProv') !== false);
ok('gastos F2: extrae texto del PDF (pdf.js getTextContent)', strpos($gjs, 'getTextContent') !== false && strpos($gjs, 'pdfjsLib') !== false);
ok('gastos F2: popup de confirmación que completa el form', strpos($gjs, 'function showPopup') !== false && strpos($gjs, 'function fillForm') !== false && strpos($gjs, 'Usar estos datos') !== false);
ok('gastos F2: form wire (botón leer + __gprov + gastos.js)', strpos($readf("$VIEWS/gasto-form.php"), 'id="leerFactura"') !== false && strpos($readf("$VIEWS/gasto-form.php"), 'window.__gprov') !== false && strpos($readf("$VIEWS/gasto-form.php"), 'assets/js/gastos.js') !== false);

echo "== v2.46 — buscador del padrón al agregar proveedor (Prov. comprob.) ==\n";
$mc46 = $readf("$SRC/comprobantes.php");
$idx46 = $readf("$WEB/index.php");
$pv46 = $readf("$VIEWS/proveedores.php");
$pjs46 = $readf("$ASSETS/js/padron.js");
ok('padrón: función de búsqueda amplia (CUIT o razón)', strpos($mc46, 'function padron_nac_buscar') !== false && strpos($mc46, 'cuit LIKE ?') !== false && strpos($mc46, 'IN BOOLEAN MODE') !== false);
ok('padrón: endpoint api-padron (JSON, login) existe', is_file("$SRC/pages/api-padron.php") && strpos($readf("$SRC/pages/api-padron.php"), 'padron_nac_buscar') !== false);
ok('padrón: ruta registrada + allowlist JSON del auto-logout', strpos($idx46, "'api-padron'") !== false && strpos($readf("$SRC/bootstrap.php"), "'api-padron'") !== false);
ok('padrón: autocompletado en Prov. comprob. (NO en el maestro de gastos)', strpos($pv46, 'data-padron-form') !== false && strpos($pv46, 'assets/js/padron.js') !== false);
ok('padrón: padron.js completa CUIT + razón sin duplicar', $pjs46 !== '' && strpos($pjs46, "api-padron?q=") !== false && strpos($pjs46, 'function pick') !== false && strpos($pjs46, 'razonEl.value') !== false && strpos($pjs46, 'cuitEl.value') !== false);

echo "== v2.47 — menos solapas: Nuevo comprobante→Escáner + Nuevo gasto coherente ==\n";
$lay47 = $readf("$VIEWS/layout.php");
$cmp47 = $readf("$VIEWS/comprobantes.php");
$gf47  = $readf("$VIEWS/gasto-form.php");
ok('nav: se quitó la solapa Escanear', strpos($lay47, "'escanear'     => ['Escanear'") === false);
ok('comprobantes: «Nuevo comprobante» va al escáner', (bool)preg_match('/url\(\x27escanear\x27\)[^>]*>[^<]*<\?= icon\(\x27plus\x27\).*Nuevo comprobante/s', $cmp47) || (strpos($cmp47, "url('escanear')") !== false && strpos($cmp47, 'Nuevo comprobante') !== false && strpos($cmp47, "url('comprobante-form')) ?>\" class=\"btn btn-primary\"") === false));
ok('gasto-form: diseño coherente con el escáner (scan-panel + filefield + secciones)', strpos($gf47, 'scan-panel') !== false && strpos($gf47, 'data-filefield') !== false && strpos($gf47, '1 · Factura') !== false && strpos($gf47, '2 · Revisá y corregí') !== false);
ok('gasto-form: botón Leer factura + wire de gastos.js intactos', strpos($gf47, 'id="leerFactura"') !== false && strpos($gf47, 'id="gastoForm"') !== false && strpos($gf47, 'assets/js/gastos.js') !== false);

echo "== v2.48 — fix cuelgue (subida a Drive en 2° plano) + Leer pago + parser por rubro ==\n";
$hlp48 = $readf("$SRC/helpers.php");
$gd48  = $readf("$SRC/google_drive.php");
$gfp48 = $readf("$SRC/pages/gasto-form.php");
$gfv48 = $readf("$VIEWS/gasto-form.php");
$gjs48 = $readf("$ASSETS/js/gastos.js");
ok('gasto: helper finish_request (responde y sigue en 2° plano)', strpos($hlp48, 'function finish_request') !== false && strpos($hlp48, 'finish_request()') !== false);
ok('gasto: guardado NO bloquea — Drive en segundo plano', strpos($gfp48, 'finish_request();') !== false && strpos($gfp48, 'drive_gasto_subir($savedId') !== false && stripos($gfp48, 'segundo plano') !== false);
ok('drive: connect timeout (no cuelga si no llega a Google)', strpos($gd48, 'CURLOPT_CONNECTTIMEOUT') !== false);
ok('gasto: parser matchea también por RUBRO (ej. EXPENSAS)', strpos($gjs48, "p.nombre + ' ' + (p.rubro") !== false);
ok('gasto: Leer PAGO (fecha + marca pagado)', strpos($gjs48, 'function parsePago') !== false && strpos($gjs48, "setVal('estado', 'pagado')") !== false && strpos($gfv48, 'id="leerPago"') !== false);
ok('gasto: __gprov con rubro + gastos.js v2', strpos($gfv48, "'rubro' => \$p['rubro']") !== false && (bool)preg_match('/gastos\.js[^\n]*\?v=2/', $gfv48));

echo "\n==================================================\n";
printf("RESULTADO: %d passed, %d failed (de %d)\n", $T - $F, $F, $T);
if ($F) { echo "FALLARON:\n - " . implode("\n - ", $fails) . "\n"; }
exit($F > 0 ? 1 : 0);
