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
    define('BASE_PATH', '/tickets');
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'intellihelp.tech';
$GLOBALS['CONFIG'] = [
    'app'    => ['name' => 'Test', 'base_path' => '/tickets'],
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
require $SRC . '/comprobantes.php';
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
eq('redirect_uri exacto',                  $ru, 'https://intellihelp.tech/tickets/google-callback');
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
$sch29 = $readf(dirname($WEB) . '/db/schema.sql');
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
ok('visor: rotar (⟲ ⟳) además de zoom/pan',                strpos($vw35, 'cv-rot') !== false && strpos($vw35, "rotate(' + rot + 'deg)") !== false && (bool)preg_match('/viewer\.js[^\n]*\?v=3/', $lay35));

echo "\n==================================================\n";
printf("RESULTADO: %d passed, %d failed (de %d)\n", $T - $F, $F, $T);
if ($F) { echo "FALLARON:\n - " . implode("\n - ", $fails) . "\n"; }
exit($F > 0 ? 1 : 0);
