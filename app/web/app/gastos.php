<?php
declare(strict_types=1);

// ============================================================
//  Módulo Gastos Recurrentes
//   · Maestro de proveedores (gastos_proveedores) — estilo Conceptos.
//   · Entradas de gastos (gastos_recurrentes) — factura + pago por período.
//  Respaldo en Drive: Proveedor / Año / Mes / (factura + pago). Todo auditado.
// ============================================================

// ---- Opciones (para selects) ----
function gp_categorias(): array   { return ['recurrente' => 'Gasto recurrente', 'unica_vez' => 'Única vez']; }
function gp_momentos(): array     { return ['adelantado' => 'Mes adelantado', 'vencido' => 'Mes vencido']; }

/** Formatea los CUIT guardados (dígitos separados por espacio) para mostrarlos: "30-70739780-9, …". */
function gp_cuits_fmt(?string $cuits): string
{
    $out = [];
    foreach (preg_split('/\s+/', trim((string)$cuits), -1, PREG_SPLIT_NO_EMPTY) as $c) {
        $out[] = strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10, 1) : $c;
    }
    return implode(', ', $out);
}

// ============================================================
//  Maestro de proveedores
// ============================================================

/** Proveedores activos (para selects). */
function gp_list(): array
{
    return q("SELECT id, nombre, rubro, categoria, momento_facturacion, tipo_factura, cuit, drive_folder_id
                FROM gastos_proveedores
               WHERE activo = 1 AND eliminado_en IS NULL
               ORDER BY orden, nombre")->fetchAll();
}

/** Todos los proveedores vivos, con su cantidad de gastos cargados. */
function gp_all(): array
{
    return q("SELECT p.*, (SELECT COUNT(*) FROM gastos_recurrentes g
                            WHERE g.proveedor_id = p.id AND g.eliminado_en IS NULL) AS usos
                FROM gastos_proveedores p
               WHERE p.eliminado_en IS NULL
               ORDER BY p.activo DESC, p.orden, p.nombre")->fetchAll();
}

/** Proveedores archivados (papelera). */
function gp_deleted(): array
{
    return q("SELECT id, nombre, rubro, eliminado_en FROM gastos_proveedores
               WHERE eliminado_en IS NOT NULL ORDER BY eliminado_en DESC")->fetchAll();
}

function gp_find(int $id): ?array
{
    return q('SELECT * FROM gastos_proveedores WHERE id = ? LIMIT 1', [$id])->fetch() ?: null;
}

/** ¿El CUIT (sólo dígitos) es uno de los del proveedor? (lista plana `cuit`). Pura. */
function gp_cuit_coincide(array $p, string $cuit): bool
{
    $dg = preg_replace('/\D/', '', $cuit) ?? '';
    if (strlen($dg) !== 11) { return false; }
    $lista = preg_split('/[^\d]+/', (string)($p['cuit'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return in_array($dg, $lista, true);
}

/**
 * Proveedor ACTIVO de Gastos recurrentes que tiene este CUIT (incluye las
 * entidades de un multi-entidad), o null. Es la regla que distingue un
 * GASTO RECURRENTE de una FACTURA COMPRA única.
 */
function gp_find_by_cuit(string $cuit): ?array
{
    $dg = preg_replace('/\D/', '', $cuit) ?? '';
    if (strlen($dg) !== 11) { return null; }
    $rows = q('SELECT * FROM gastos_proveedores
                WHERE activo = 1 AND eliminado_en IS NULL AND cuit LIKE ?
                ORDER BY orden, id', ['%' . $dg . '%'])->fetchAll();
    foreach ($rows as $p) {
        if (gp_cuit_coincide($p, $dg)) { return $p; }
    }
    return null;
}

/**
 * Una factura cargada desde Escanear / Nuevo comprobante resultó ser de un
 * proveedor de Gastos recurrentes: en vez de crear un comprobante, crea o
 * completa el GASTO de ese proveedor y período y le adjunta el archivo
 * (Drive → Gastos Recurrentes / Proveedor / Año / Mes).
 *
 *  · Si ya existe el gasto del proveedor+período SIN factura (y el proveedor no
 *    es multi-entidad), se completa: valor/fecha sólo donde estaban vacíos.
 *  · Si no existe, o ya tiene factura, o es multi-entidad (ej. varios bancos
 *    en el mismo mes), se crea un gasto nuevo.
 *
 * @param array $in    $_POST del formulario de comprobante (cuit, fecha_emision, importe_total, moneda…)
 * @param array $prov  proveedor detectado (gp_find_by_cuit)
 * @param array $file  $_FILES['imagen'] (puede venir vacío)
 * @return array [errores, gastoId, 'creado'|'completado']
 */
function gr_desde_factura(array $in, array $prov, int $uid, array $file = []): array
{
    $fecha = trim((string)($in['fecha_emision'] ?? ''));
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        return [['fecha_emision' => 'Fecha inválida: se necesita para el período del gasto recurrente.'], 0, ''];
    }
    $total = trim((string)($in['importe_total'] ?? ''));
    $tipoC = trim((string)($in['tipo_comprobante'] ?? ''));   // ej. "Fact B" (ya trae la letra)
    $letra = trim((string)($in['letra'] ?? ''));
    if ($letra !== '' && preg_match('/\s' . preg_quote($letra, '/') . '$/i', $tipoC)) { $letra = ''; }
    $comp  = trim(implode(' ', array_filter([
        $tipoC, $letra,
        trim((string)($in['punto_venta'] ?? '') . '-' . (string)($in['numero'] ?? ''), '-'),
    ])));
    [$err, $c] = gr_validar([
        'proveedor_id'  => (int)$prov['id'],
        'periodo'       => substr($fecha, 0, 7),
        'valor_mensual' => $total,
        'moneda'        => (string)($in['moneda'] ?? 'ARS'),
        'fecha_factura' => $fecha,
        'estado'        => 'pendiente',
        'observaciones' => ($comp !== '' ? $comp : 'Factura') . ' cargada como comprobante: detectada como gasto recurrente por CUIT.',
    ]);
    if ($err) { return [$err, 0, '']; }

    $existente = null;
    if (empty($prov['multi_entidad'])) {
        $existente = q('SELECT * FROM gastos_recurrentes
                         WHERE proveedor_id = ? AND periodo = ? AND eliminado_en IS NULL
                           AND (drive_factura_id IS NULL OR drive_factura_id = \'\')
                         ORDER BY id DESC LIMIT 1', [(int)$prov['id'], $c['periodo']])->fetch() ?: null;
    }

    if ($existente) {
        $gid = (int)$existente['id'];
        $set = []; $vals = [];
        foreach (['valor_mensual', 'fecha_factura'] as $k) {
            if (($existente[$k] ?? null) === null && $c[$k] !== null) { $set[] = "$k = ?"; $vals[] = $c[$k]; }
        }
        if ($existente['valor_mensual'] === null && $c['valor_mensual'] !== null) { $set[] = 'moneda = ?'; $vals[] = $c['moneda']; }
        if ($set) { $vals[] = $gid; q('UPDATE gastos_recurrentes SET ' . implode(', ', $set) . ' WHERE id = ?', $vals); }
        $accion = 'completado';
    } else {
        $gid = gr_save($c, null, $uid);
        $accion = 'creado';
    }
    if (function_exists('log_app')) {
        log_app('gasto.desde_factura', ['tipo' => 'accion', 'entidad' => 'gasto', 'entidad_id' => $gid,
            'detalle' => 'Factura de ' . ($prov['nombre'] ?? '?') . ' (CUIT ' . preg_replace('/\D/', '', (string)($in['cuit'] ?? '')) . ') '
                       . 'detectada como gasto recurrente → gasto ' . $c['periodo'] . ' ' . $accion]);
    }
    if (!empty($file['name']) && function_exists('drive_gasto_subir')) {
        $g = gr_find($gid);
        if ($g) { drive_gasto_subir($gid, $g, 'factura', $file); }
    }
    return [[], $gid, $accion];
}

/** Normaliza y valida los campos del maestro. Devuelve [errores, limpio]. */
function gp_validar(array $in): array
{
    $err = [];
    $c = [];
    $nombre = trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? '')) ?? '');
    $len = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);
    if ($nombre === '' || $len < 2) { $err['nombre'] = 'El nombre es muy corto.'; }
    elseif ($len > 150)             { $err['nombre'] = 'El nombre es demasiado largo.'; }
    $c['nombre'] = $nombre;

    $c['rubro']        = trim((string)($in['rubro'] ?? '')) ?: null;
    $c['categoria']    = array_key_exists((string)($in['categoria'] ?? ''), gp_categorias()) ? $in['categoria'] : 'recurrente';
    $mom               = (string)($in['momento_facturacion'] ?? '');
    $c['momento_facturacion'] = array_key_exists($mom, gp_momentos()) ? $mom : null;
    $c['tipo_factura'] = trim((string)($in['tipo_factura'] ?? '')) ?: null;

    $vig = trim((string)($in['vigencia_descuento'] ?? ''));
    if ($vig !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $vig);
        $c['vigencia_descuento'] = ($d && $d->format('Y-m-d') === $vig) ? $vig : null;
    } else {
        $c['vigencia_descuento'] = null;
    }
    $c['nombre_gasto'] = trim((string)($in['nombre_gasto'] ?? '')) ?: null;

    // Un proveedor puede agrupar VARIAS ENTIDADES (ej. BANCOS: cada banco con su nombre +
    // CUIT). Si NO es multi-entidad (ej. EDENOR: existe uno solo), se limita a 1 CUIT y sin
    // nombres extra — la UI lo bloquea y el server lo refuerza.
    $multi = !empty($in['multi_entidad']);
    $c['multi_entidad'] = $multi ? 1 : 0;
    $cuits = [];
    $entidades = [];
    if ($multi) {
        $noms = (array)($in['ent_nombre'] ?? []);
        $cus  = (array)($in['ent_cuit'] ?? []);
        foreach ($cus as $i => $rawC) {
            $dg  = preg_replace('/\D/', '', (string)$rawC);
            $nom = trim((string)($noms[$i] ?? ''));
            if ($dg === '' && $nom === '') { continue; }
            if (strlen($dg) !== 11 || !validar_cuit($dg)) { $err['cuit'] = 'CUIT inválido (dígito verificador): ' . ($rawC ?: '(vacío)'); continue; }
            if (in_array($dg, $cuits, true)) { continue; }
            $cuits[] = $dg;
            $entidades[] = ['nombre' => $nom, 'cuit' => $dg];
        }
        $c['entidades'] = $entidades ? json_encode($entidades, JSON_UNESCAPED_UNICODE) : null;
    } else {
        // Single: un solo CUIT (acepta separadores por compatibilidad, pero guarda solo el 1º válido).
        $rawCuit = $in['cuit'] ?? '';
        if (is_array($rawCuit)) { $rawCuit = implode(' ', $rawCuit); }
        foreach (preg_split('/[,;\s]+/', trim((string)$rawCuit), -1, PREG_SPLIT_NO_EMPTY) as $tk) {
            $dg = preg_replace('/\D/', '', $tk);
            if ($dg === '') { continue; }
            if (strlen($dg) !== 11 || !validar_cuit($dg)) { $err['cuit'] = 'CUIT inválido (dígito verificador): ' . $tk; continue; }
            if (!in_array($dg, $cuits, true)) { $cuits[] = $dg; }
        }
        $cuits = array_slice($cuits, 0, 1);   // uno solo cuando no es multi-entidad
        $c['entidades'] = null;
    }
    // `cuit` = lista plana (para el match por CUIT del emisor, que usa cualquiera).
    $c['cuit'] = $cuits ? implode(' ', $cuits) : null;

    $c['drive_folder'] = trim((string)($in['drive_folder'] ?? '')) ?: null;

    return [$err, $c];
}

/** Crea o edita un proveedor del maestro. $id null = crear. Devuelve [ok, id, error]. */
function gp_save(array $c, ?int $id, bool $activo = true): array
{
    // Anti-duplicado por nombre.
    $dup = q('SELECT id FROM gastos_proveedores WHERE LOWER(nombre) = LOWER(?) AND id <> ? AND eliminado_en IS NULL LIMIT 1',
        [$c['nombre'], $id ?? 0])->fetch();
    if ($dup) { return ['ok' => false, 'error' => 'Ya existe un proveedor con ese nombre.']; }

    if ($id) {
        q("UPDATE gastos_proveedores SET nombre=?, rubro=?, categoria=?, momento_facturacion=?, tipo_factura=?,
                  vigencia_descuento=?, nombre_gasto=?, cuit=?, multi_entidad=?, entidades=?, drive_folder=?, activo=? WHERE id=?",
            [$c['nombre'], $c['rubro'], $c['categoria'], $c['momento_facturacion'], $c['tipo_factura'],
             $c['vigencia_descuento'], $c['nombre_gasto'], $c['cuit'], $c['multi_entidad'] ?? 0, $c['entidades'] ?? null, $c['drive_folder'], $activo ? 1 : 0, $id]);
        if (function_exists('log_app')) {
            log_app('gproveedor.editar', ['entidad' => 'gasto_proveedor', 'entidad_id' => $id,
                'detalle' => '«' . $c['nombre'] . '» · ' . ($c['rubro'] ?? '') . ' · ' . $c['categoria']]);
        }
        return ['ok' => true, 'id' => $id];
    }
    $orden = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM gastos_proveedores')->fetchColumn();
    q("INSERT INTO gastos_proveedores (nombre, rubro, categoria, momento_facturacion, tipo_factura,
              vigencia_descuento, nombre_gasto, cuit, multi_entidad, entidades, drive_folder, orden, activo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)",
        [$c['nombre'], $c['rubro'], $c['categoria'], $c['momento_facturacion'], $c['tipo_factura'],
         $c['vigencia_descuento'], $c['nombre_gasto'], $c['cuit'], $c['multi_entidad'] ?? 0, $c['entidades'] ?? null, $c['drive_folder'], $orden]);
    $nid = (int) db()->lastInsertId();
    if (function_exists('log_app')) {
        log_app('gproveedor.crear', ['entidad' => 'gasto_proveedor', 'entidad_id' => $nid,
            'detalle' => '«' . $c['nombre'] . '» · ' . ($c['rubro'] ?? '') . ' · ' . $c['categoria']]);
    }
    return ['ok' => true, 'id' => $nid];
}

function gp_delete(int $id): void
{
    $p = gp_find($id);
    if (!$p) { return; }
    q('UPDATE gastos_proveedores SET eliminado_en = NOW(), activo = 0 WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('gproveedor.eliminar', ['entidad' => 'gasto_proveedor', 'entidad_id' => $id,
            'detalle' => 'Proveedor «' . $p['nombre'] . '» archivado (recuperable)']);
    }
}

function gp_restore(int $id): void
{
    $p = q('SELECT nombre FROM gastos_proveedores WHERE id = ?', [$id])->fetch();
    if (!$p) { return; }
    q('UPDATE gastos_proveedores SET eliminado_en = NULL, activo = 1 WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('gproveedor.restaurar', ['entidad' => 'gasto_proveedor', 'entidad_id' => $id,
            'detalle' => 'Proveedor «' . $p['nombre'] . '» restaurado']);
    }
}

/** Siembra los proveedores recurrentes reales (idempotente, no duplica). */
function gp_seed(): int
{
    // [nombre, rubro, categoria, momento, carpeta_drive, cuit] — carpeta y CUIT reales
    // (el CUIT del emisor hace el match exacto; vacío si el PDF no lo expone / es compartido).
    $data = [
        ['EDENOR', 'ELECTRICIDAD', 'recurrente', 'vencido', '01.EDENOR', '30655116202'],
        ['ADMINISTRACION SILVANA LORENA GUAYCOCHEA', 'EXPENSAS', 'recurrente', 'adelantado', '02.EXPENSAS', '27238075535'],
        ['MAURICIO SZTERN', 'ALQUILER OFICINA', 'recurrente', 'adelantado', '03.MAURICIO STERN', '30702102045'],
        ['ESTUDIO COLLINET', 'ESTUDIO CONTABLE', 'recurrente', 'vencido', '04.ESTUDIO COLLINET', '20169624373'],
        ['NET2PHONE', 'SERVICIO TELEFONIA', 'recurrente', 'vencido', '05.NET2PHONE', '30707397809'],
        ['GUPAZ', 'SERVICIO AGUA', 'recurrente', 'vencido', '06.GUPAZ - EVERFULL', ''],
        ['CLARO EMPRESAS', 'SERVICIO CELULARES', 'recurrente', 'vencido', '07.CLARO EMPRESAS', '30663288497'],
        ['TELECOM EMPRESAS', 'SERVICIO INTERNET', 'recurrente', 'vencido', '08.TELECOM EMPRESAS', '30639453738'],
        ['SANCOR SALUD', 'MEDICINA PREPAGA', 'recurrente', 'adelantado', '09.SANCOR SALUD', '30590354798'],
        ['JOI SEGUROS', 'SEGUROS', 'recurrente', 'vencido', '10.SEGUROS', '30500049770'],
        ['GCBA - ABL', 'ABL', 'recurrente', 'vencido', '11.ABL OFICINA', ''],
        ['GCBA - PATENTES', 'PATENTES', 'recurrente', 'vencido', '12.PATENTES VEHICULOS', ''],
        ['ARCA', 'IMPUESTOS', 'recurrente', 'vencido', '13.ARCA', ''],
        ['BANCOS', 'BANCOS', 'recurrente', 'vencido', '14.BANCOS', '', 1],   // multi-entidad: varios bancos
        ['GOOGLE SUITE', 'GOOGLE SUITE', 'recurrente', 'vencido', '15.GOOGLE SUITE', ''],
        ['HOSPITAL ALEMAN', 'MEDICINA PREPAGA', 'recurrente', 'adelantado', '16.HOSPITAL ALEMAN', '30545843036'],
        ['OSDE', 'MEDICINA PREPAGA', 'recurrente', 'adelantado', '17.OSDE', '30546741253'],
        ['CHATGPT', 'INTELIGENCIA ARTIFICIAL', 'recurrente', 'vencido', '18.CHATGPT NICOLAS', ''],
        ['PERSONAL FLOW', 'SERVICIO CELULARES', 'recurrente', 'vencido', '19.PERSONAL NICOLAS', ''],
    ];
    $n = 0; $orden = 10;
    foreach ($data as $row) {
        [$nombre, $rubro, $cat, $mom, $folder, $cuit] = $row;
        $multi = $row[6] ?? 0;
        $ex = q('SELECT id FROM gastos_proveedores WHERE LOWER(nombre) = LOWER(?) LIMIT 1', [$nombre])->fetch();
        if ($ex) { continue; }
        q("INSERT INTO gastos_proveedores (nombre, rubro, categoria, momento_facturacion, drive_folder, cuit, multi_entidad, orden, activo)
           VALUES (?,?,?,?,?,?,?,?,1)", [$nombre, $rubro, $cat, $mom, $folder, ($cuit ?: null), $multi, $orden]);
        $orden += 10; $n++;
    }
    return $n;
}

// ============================================================
//  Entradas de gastos recurrentes
// ============================================================

const GR_SELECT = "SELECT g.*, p.nombre AS prov_nombre, p.rubro AS prov_rubro, p.categoria AS prov_categoria,
                          p.drive_folder AS prov_drive_folder
                     FROM gastos_recurrentes g
                     LEFT JOIN gastos_proveedores p ON p.id = g.proveedor_id";

/** Lista de gastos (no eliminados), opcionalmente filtrada por período/proveedor. */
function gr_list(array $f = []): array
{
    $w = ['g.eliminado_en IS NULL']; $p = [];
    if (!empty($f['periodo']))      { $w[] = 'g.periodo = ?';      $p[] = $f['periodo']; }
    if (!empty($f['proveedor_id'])) { $w[] = 'g.proveedor_id = ?'; $p[] = (int)$f['proveedor_id']; }
    if (!empty($f['estado']))       { $w[] = 'g.estado = ?';       $p[] = $f['estado']; }
    $where = 'WHERE ' . implode(' AND ', $w);
    return q(GR_SELECT . " $where ORDER BY g.periodo DESC, p.nombre", $p)->fetchAll();
}

function gr_find(int $id): ?array
{
    return q(GR_SELECT . ' WHERE g.id = ? AND g.eliminado_en IS NULL LIMIT 1', [$id])->fetch() ?: null;
}

/** Valida los campos de un gasto. Devuelve [errores, limpio]. */
function gr_validar(array $in): array
{
    $err = [];
    $c = [];
    $c['proveedor_id'] = (int)($in['proveedor_id'] ?? 0);
    if ($c['proveedor_id'] <= 0 || !gp_find($c['proveedor_id'])) { $err['proveedor_id'] = 'Elegí un proveedor.'; }

    $periodo = trim((string)($in['periodo'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) { $err['periodo'] = 'Período inválido (AAAA-MM).'; }
    $c['periodo'] = $periodo;

    $c['valor_mensual'] = ($in['valor_mensual'] ?? '') !== '' ? parse_importe((string)$in['valor_mensual']) : null;
    $c['moneda'] = in_array($in['moneda'] ?? 'ARS', ['ARS', 'USD'], true) ? $in['moneda'] : 'ARS';

    foreach (['fecha_factura', 'fecha_vencimiento', 'fecha_pago'] as $k) {
        $v = trim((string)($in[$k] ?? ''));
        if ($v !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v);
            $c[$k] = ($d && $d->format('Y-m-d') === $v) ? $v : null;
        } else { $c[$k] = null; }
    }
    $c['estado'] = in_array($in['estado'] ?? 'pendiente', ['pendiente', 'pagado'], true) ? $in['estado'] : 'pendiente';
    $c['observaciones'] = trim((string)($in['observaciones'] ?? '')) ?: null;
    return [$err, $c];
}

/** Crea o edita un gasto. Devuelve el id. */
function gr_save(array $c, ?int $id, int $uid): int
{
    $fields = [
        'proveedor_id'      => $c['proveedor_id'],
        'periodo'           => $c['periodo'],
        'valor_mensual'     => $c['valor_mensual'],
        'moneda'            => $c['moneda'],
        'fecha_factura'     => $c['fecha_factura'],
        'fecha_vencimiento' => $c['fecha_vencimiento'],
        'fecha_pago'        => $c['fecha_pago'],
        'estado'            => $c['estado'],
        'observaciones'     => $c['observaciones'],
    ];
    if ($id) {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $vals = array_values($fields); $vals[] = $id;
        q("UPDATE gastos_recurrentes SET $set WHERE id = ?", $vals);
        if (function_exists('log_app')) {
            log_app('gasto.editar', ['entidad' => 'gasto', 'entidad_id' => $id, 'detalle' => gr_etiqueta($c)]);
        }
        return $id;
    }
    $fields['creado_por'] = $uid;
    $cols = implode(', ', array_keys($fields));
    $ph = implode(', ', array_fill(0, count($fields), '?'));
    q("INSERT INTO gastos_recurrentes ($cols) VALUES ($ph)", array_values($fields));
    $nid = (int) db()->lastInsertId();
    if (function_exists('log_app')) {
        log_app('gasto.crear', ['entidad' => 'gasto', 'entidad_id' => $nid, 'detalle' => gr_etiqueta($c)]);
    }
    return $nid;
}

function gr_etiqueta(array $c): string
{
    $prov = '';
    if (!empty($c['proveedor_id'])) {
        $p = gp_find((int)$c['proveedor_id']);
        $prov = $p['nombre'] ?? ('#' . $c['proveedor_id']);
    }
    $val = isset($c['valor_mensual']) && $c['valor_mensual'] !== null ? money($c['valor_mensual'], $c['moneda'] ?? 'ARS') : 's/valor';
    return trim(($c['periodo'] ?? '') . ' · ' . $prov . ' · ' . $val, ' ·');
}

function gr_delete(int $id): void
{
    $g = gr_find($id);
    if (!$g) { return; }
    q('UPDATE gastos_recurrentes SET eliminado_en = NOW() WHERE id = ? AND eliminado_en IS NULL', [$id]);
    // Espejo en Drive: archivar los PDFs en «<raíz gastos>/Archivados» (no en la de comprobantes).
    foreach (['factura', 'pago'] as $t) {
        if (!empty($g["drive_{$t}_id"]) && function_exists('drive_archivar')) {
            $ok = false;
            try { $ok = drive_archivar((string)$g["drive_{$t}_id"], 'gastos'); } catch (\Throwable $e) {}
            if (function_exists('log_app')) {
                log_app($ok ? 'gasto.drive_archivar' : 'gasto.drive_fallo', ['tipo' => $ok ? 'accion' : 'error',
                    'entidad' => 'gasto', 'entidad_id' => $id,
                    'detalle' => $ok ? ucfirst($t) . ' movida a «Archivados» en Drive' : 'No se pudo mover la ' . $t . ' a «Archivados» en Drive']);
            }
        }
    }
    if (function_exists('log_app')) {
        log_app('gasto.eliminar', ['entidad' => 'gasto', 'entidad_id' => $id,
            'detalle' => gr_etiqueta($g) . ' · archivado (recuperable)']);
    }
}

function gr_restore(int $id): void
{
    q('UPDATE gastos_recurrentes SET eliminado_en = NULL WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('gasto.restaurar', ['entidad' => 'gasto', 'entidad_id' => $id, 'detalle' => 'Restaurado desde la papelera']);
    }
    // Espejo en Drive: devolver factura/pago de «Archivados» a Proveedor/Año/Mes.
    $g = gr_find($id);
    if ($g && function_exists('drive_gasto_restaurar')) {
        drive_gasto_restaurar($id, $g);
    }
}
