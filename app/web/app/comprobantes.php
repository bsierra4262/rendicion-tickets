<?php
declare(strict_types=1);

// ============================================================
//  Modelo: proveedores, conceptos y comprobantes
// ============================================================

// ---- Proveedores ----
function prov_by_cuit(string $cuit): ?array
{
    $c = normalizar_cuit($cuit);
    if ($c === '') {
        return null;
    }
    return q('SELECT * FROM proveedores WHERE cuit = ? LIMIT 1', [$c])->fetch() ?: null;
}

function prov_buscar(string $term, int $limit = 8): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }
    $like = '%' . $term . '%';
    return q('SELECT id, cuit, razon_social FROM proveedores
              WHERE razon_social LIKE ? OR cuit LIKE ?
              ORDER BY razon_social LIMIT ' . (int)$limit, [$like, $like])->fetchAll();
}

/** Busca el proveedor por CUIT (o razón social si no hay CUIT) y lo crea si no existe. */
function prov_find_or_create(string $cuit, string $razon): ?int
{
    $c = normalizar_cuit($cuit);
    $razon = trim($razon);
    if ($c !== '') {
        $p = prov_by_cuit($c);
        if ($p) {
            if ($razon !== '' && in_array((string)$p['razon_social'], ['', '(sin nombre)'], true)) {
                q('UPDATE proveedores SET razon_social = ? WHERE id = ?', [$razon, $p['id']]);
            }
            return (int)$p['id'];
        }
        q('INSERT INTO proveedores (cuit, razon_social, cuit_valido) VALUES (?, ?, ?)',
            [$c, $razon !== '' ? $razon : '(sin nombre)', validar_cuit($c) ? 1 : 0]);
        return (int)db()->lastInsertId();
    }
    if ($razon !== '') {
        $p = q('SELECT id FROM proveedores WHERE cuit IS NULL AND razon_social = ? LIMIT 1', [$razon])->fetch();
        if ($p) {
            return (int)$p['id'];
        }
        q('INSERT INTO proveedores (cuit, razon_social, cuit_valido) VALUES (NULL, ?, 0)', [$razon]);
        return (int)db()->lastInsertId();
    }
    return null;
}

/** Normaliza una razón social para comparar: sin acentos, mayúsculas, sólo A-Z0-9 y espacios. */
function prov_norm_razon(string $s): string
{
    $s = strtr($s, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U',
    ]);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9 ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Busca un proveedor por razón social APROXIMADA (tolera errores de OCR).
 * Estrategia: 1) contención del token más largo (≥4)  2) levenshtein ≤1 sobre palabras
 * significativas.  Sirve para recuperar el CUIT del emisor a partir de su nombre.
 */
function prov_by_razon(string $razon): ?array
{
    $norm = prov_norm_razon($razon);
    if (strlen($norm) < 4) {
        return null;
    }
    $tokens = array_values(array_filter(explode(' ', $norm), fn($w) => strlen($w) >= 4));
    if (!$tokens) {
        return null;
    }
    usort($tokens, fn($a, $b) => strlen($b) - strlen($a));
    $main = $tokens[0];   // token MÁS DISTINTIVO (el más largo) — exigirlo evita falsos positivos

    // 1) contención del token principal (el más distintivo, no cualquiera)
    $rows = q('SELECT * FROM proveedores WHERE razon_social LIKE ? ORDER BY razon_social LIMIT 12',
        ['%' . $main . '%'])->fetchAll();
    foreach ($rows as $row) {
        if (strpos(prov_norm_razon((string) $row['razon_social']), $main) !== false) {
            return $row;
        }
    }

    // 2) aproximado: levenshtein ≤1 sobre el token principal (recupera "FFLABELLA"→"FALABELLA")
    if (strlen($main) >= 5) {
        $rows = q("SELECT * FROM proveedores WHERE razon_social <> '' AND razon_social <> '(sin nombre)' LIMIT 800")->fetchAll();
        foreach ($rows as $row) {
            foreach (explode(' ', prov_norm_razon((string)$row['razon_social'])) as $w) {
                if (strlen($w) >= 5 && levenshtein($main, $w) <= 1) {
                    return $row;
                }
            }
        }
    }
    return null;
}

// ---- Padrón nacional (empresas, offline) — sólo lectura, enriquece el OCR ----
/** CUIT → razón social en el padrón nacional (empresas precargadas). */
function padron_nac_by_cuit(string $cuit): ?array
{
    $c = normalizar_cuit($cuit);
    if (strlen($c) !== 11) {
        return null;
    }
    return q('SELECT cuit, razon_social FROM padron_nacional WHERE cuit = ? LIMIT 1', [$c])->fetch() ?: null;
}

/** Núcleo de una razón social: sin acentos/puntuación ni sufijos societarios (SA/SRL/…). */
function prov_razon_core(string $s): string
{
    $n = prov_norm_razon($s);
    $n = preg_replace('/\b(SOCIEDAD|ANONIMA|RESPONSABILIDAD|LIMITADA|UNIPERSONAL|COLECTIVA)\b/', ' ', $n);
    $n = preg_replace('/\b(SACIF|SAICF|SACIFIA|SAIC|SACI|SAS|SRL|SCA|SCS|SC|SH|SA|CIA|LTDA)\b/', ' ', $n);
    // Sufijos societarios ESCRITOS CON PUNTOS quedan como letras sueltas ("S.R.L."→"S R L",
    // "S.A."→"S A"): las quitamos para que "SRL" (OCR) y "S R L" (dataset) normalicen IGUAL.
    $n = preg_replace('/\b[A-Z]\b/', ' ', $n);
    return trim(preg_replace('/\s+/', ' ', $n));
}

/**
 * Razón social → CUIT en el padrón nacional (FULLTEXT + verificación CONSERVADORA).
 * La razón social NO es única (hay homónimos, franquicias, etc.), así que sólo devuelve
 * un CUIT cuando el núcleo del nombre coincide EXACTO y es ÚNICO → nunca adivina entre
 * homónimos (mejor no completar que completar un CUIT equivocado).
 */
function padron_nac_by_razon(string $razon): ?array
{
    $qcore = prov_razon_core($razon);
    if (strlen($qcore) < 3) {
        return null;
    }
    $tokens = array_values(array_filter(explode(' ', prov_norm_razon($razon)), fn($w) => strlen($w) >= 3));
    if (!$tokens) {
        return null;
    }
    usort($tokens, fn($a, $b) => strlen($b) - strlen($a));
    $expr = implode(' ', array_map(fn($t) => '+' . $t . '*', array_slice($tokens, 0, 3)));
    try {
        $rows = q('SELECT cuit, razon_social FROM padron_nacional
                   WHERE MATCH(razon_social) AGAINST (? IN BOOLEAN MODE)
                   ORDER BY CHAR_LENGTH(razon_social) ASC LIMIT 25', [$expr])->fetchAll();
    } catch (\Throwable $e) {
        return null;
    }
    $hits = [];
    foreach ($rows as $r) {
        if (prov_razon_core((string) $r['razon_social']) === $qcore) {
            $hits[$r['cuit']] = $r;
        }
    }
    return count($hits) === 1 ? reset($hits) : null;   // único homónimo exacto, o nada
}

/** Todos los homónimos del padrón nacional cuyo NÚCLEO de razón coincide con la consulta. */
function padron_nac_candidates(string $razon, int $limit = 30): array
{
    $qcore = prov_razon_core($razon);
    if (strlen($qcore) < 3) {
        return [];
    }
    $tokens = array_values(array_filter(explode(' ', prov_norm_razon($razon)), fn($w) => strlen($w) >= 3));
    if (!$tokens) {
        return [];
    }
    usort($tokens, fn($a, $b) => strlen($b) - strlen($a));
    $expr = implode(' ', array_map(fn($t) => '+' . $t . '*', array_slice($tokens, 0, 3)));
    try {
        $rows = q('SELECT cuit, razon_social FROM padron_nacional
                   WHERE MATCH(razon_social) AGAINST (? IN BOOLEAN MODE)
                   ORDER BY CHAR_LENGTH(razon_social) ASC LIMIT ' . (int)$limit, [$expr])->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        if (prov_razon_core((string) $r['razon_social']) === $qcore) {
            $out[] = $r;
        }
    }
    return $out;
}

/** Variantes de un CUIT mal leído (1 edición) que EXISTAN en el padrón (recupera OCR con 1 error). */
function padron_nac_fuzzy_cuit(string $cuit): array
{
    $c = preg_replace('/\D/', '', $cuit);
    $L = strlen($c);
    if ($L < 10 || $L > 12) {
        return [];
    }
    $vars = [];
    if ($L === 11) {                                   // sustitución de 1 dígito
        for ($i = 0; $i < 11; $i++) {
            for ($d = 0; $d < 10; $d++) {
                $v = substr($c, 0, $i) . $d . substr($c, $i + 1);
                if ($v !== $c) $vars[$v] = 1;
            }
        }
    } elseif ($L === 10) {                              // OCR comió 1 dígito → insertar
        for ($i = 0; $i <= 10; $i++) {
            for ($d = 0; $d < 10; $d++) {
                $vars[substr($c, 0, $i) . $d . substr($c, $i)] = 1;
            }
        }
    } else {                                            // L === 12: OCR metió 1 de más → borrar
        for ($i = 0; $i < 12; $i++) {
            $vars[substr($c, 0, $i) . substr($c, $i + 1)] = 1;
        }
    }
    $keys = array_slice(array_keys($vars), 0, 220);
    if (!$keys) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($keys), '?'));
    try {
        return q("SELECT cuit, razon_social FROM padron_nacional WHERE cuit IN ($ph)", $keys)->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
}

/** Entre homónimos, elige el de CUIT MÁS CERCANO al leído por OCR (ganador claro y ≤2 ediciones). */
function _prov_pick_por_cuit(array $cands, string $c): ?array
{
    $c = preg_replace('/\D/', '', $c);
    if (strlen($c) !== 11 || !$cands) {
        return null;
    }
    $best = null; $bestD = 99; $tie = false;
    foreach ($cands as $r) {
        $d = levenshtein($c, (string) $r['cuit']);
        if ($d < $bestD) { $bestD = $d; $best = $r; $tie = false; }
        elseif ($d === $bestD) { $tie = true; }
    }
    return (!$tie && $bestD <= 2) ? $best : null;
}

function _prov_ret_nac(array $r, string $conf): array
{
    return ['cuit' => $r['cuit'], 'razon_social' => $r['razon_social'], 'categoria_sugerida_id' => null, 'fuente' => 'nacional', 'confianza' => $conf];
}

/**
 * MOTOR de resolución de proveedor: cruza lo que leyó el OCR (CUIT y/o razón) contra el
 * padrón y prioriza coincidencias INEQUÍVOCAS (CUIT y razón apuntan al MISMO registro).
 * El CUIT desempata homónimos; la razón detecta cuando el CUIT es el del comprador.
 * $cuitOcr = dígitos crudos del CUIT (aunque falle el DV) para recuperación difusa.
 * Devuelve ['cuit','razon_social','categoria_sugerida_id','fuente','confianza'] o null.
 */
function resolver_proveedor(string $cuit, string $razon, string $cuitOcr = ''): ?array
{
    $c = preg_replace('/\D/', '', $cuit);
    $c = strlen($c) === 11 ? $c : '';
    $qcore = prov_razon_core($razon);
    $hasR = strlen($qcore) >= 3;

    $cands = $hasR ? padron_nac_candidates($razon) : [];   // homónimos nacionales por razón

    // ---- 1) POR CUIT (exacto, en ambas tablas) — tiene prioridad sobre la razón ----
    if ($c !== '') {
        // 1a) tabla viva `proveedores` (clientes propios; trae categoría aprendida)
        $p = prov_by_cuit($c);
        if ($p) {
            return ['cuit' => $p['cuit'], 'razon_social' => $p['razon_social'], 'categoria_sugerida_id' => $p['categoria_sugerida_id'] ?? null, 'fuente' => 'padron', 'confianza' => 'alta'];
        }
        // 1b) padrón nacional por CUIT + VALIDACIÓN CRUZADA con la razón
        $byCuit = padron_nac_by_cuit($c);
        if ($byCuit) {
            if (!$hasR || prov_razon_core($byCuit['razon_social']) === $qcore) {
                return _prov_ret_nac($byCuit, 'alta');            // CUIT+razón de acuerdo (o sin razón) = INEQUÍVOCO
            }
            if ($cands) {                                          // razón apunta a otra empresa → CUIT sospechoso
                $pick = _prov_pick_por_cuit($cands, $c);
                return $pick ? _prov_ret_nac($pick, 'alta') : null;   // sin desempate → no adivinar
            }
            return _prov_ret_nac($byCuit, 'media');               // razón es ruido → confiar en el CUIT
        }
    }

    // ---- 2) POR RAZÓN (cuando el CUIT no resolvió) ----
    if ($hasR) {
        // 2a) tabla viva `proveedores` (exige el token más distintivo → sin falsos positivos)
        $p = prov_by_razon($razon);
        if ($p) {
            return ['cuit' => $p['cuit'] ?? $c, 'razon_social' => $p['razon_social'], 'categoria_sugerida_id' => $p['categoria_sugerida_id'] ?? null, 'fuente' => 'padron', 'confianza' => 'alta'];
        }
        // 2b) padrón nacional por razón, desempatando homónimos con el CUIT leído
        if ($cands) {
            $pick = _prov_pick_por_cuit($cands, $c !== '' ? $c : $cuitOcr);
            if ($pick) return _prov_ret_nac($pick, 'alta');       // razón↔CUIT cruzados = INEQUÍVOCO
            $uniq = [];
            foreach ($cands as $r) $uniq[$r['cuit']] = $r;
            if (count($uniq) === 1) return _prov_ret_nac(reset($uniq), 'media');   // homónimo único
        }
    }

    // ---- 3) RECUPERACIÓN DIFUSA del CUIT mal leído (variantes a 1 edición en el padrón) ----
    $raw = preg_replace('/\D/', '', $cuitOcr !== '' ? $cuitOcr : $cuit);
    if (strlen($raw) >= 10 && strlen($raw) <= 12) {
        $fuzzy = padron_nac_fuzzy_cuit($raw);
        if ($fuzzy) {
            if ($hasR) {                                          // cruzar con la razón para elegir seguro
                foreach ($fuzzy as $r) {
                    if (prov_razon_core($r['razon_social']) === $qcore) return _prov_ret_nac($r, 'alta');
                }
            }
            if (count($fuzzy) === 1) return _prov_ret_nac($fuzzy[0], 'media');  // única variante existente
        }
    }
    return null;
}

// ---- Proveedores (ABM / catálogo) ----
/** Lista de proveedores con su categoría sugerida y cantidad de comprobantes. */
function proveedores_list(string $term = '', int $limit = 300): array
{
    $term = trim($term);
    $where = '';
    $params = [];
    if ($term !== '') {
        $where = 'WHERE p.razon_social LIKE ? OR p.cuit LIKE ?';
        $params = ['%' . $term . '%', '%' . $term . '%'];
    }
    return q("SELECT p.id, p.cuit, p.razon_social, p.categoria_sugerida_id,
                     co.nombre AS categoria,
                     (SELECT COUNT(*) FROM comprobantes c WHERE c.proveedor_id = p.id AND c.eliminado_en IS NULL) AS usos
                FROM proveedores p
                LEFT JOIN conceptos co ON co.id = p.categoria_sugerida_id
                $where
                ORDER BY p.razon_social LIMIT " . (int)$limit, $params)->fetchAll();
}

function proveedores_total(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM proveedores')->fetchColumn();
}

/** Alta/edición manual de un proveedor (CUIT, razón social, categoría sugerida). */
function prov_admin_save(int $id, string $cuit, string $razon, ?int $catId): array
{
    $razon = trim($razon);
    $c = normalizar_cuit($cuit);
    if ($razon === '') {
        return ['ok' => false, 'error' => 'La razón social es obligatoria.'];
    }
    if ($c !== '' && !validar_cuit($c)) {
        return ['ok' => false, 'error' => 'El CUIT no es válido (dígito verificador).'];
    }
    $catId = $catId ?: null;
    $cuitVal = $c !== '' ? $c : null;
    $valido = ($c !== '' && validar_cuit($c)) ? 1 : 0;
    if ($id) {
        q('UPDATE proveedores SET cuit = ?, razon_social = ?, cuit_valido = ?, categoria_sugerida_id = ? WHERE id = ?',
            [$cuitVal, $razon, $valido, $catId, $id]);
        if (function_exists('log_app')) { log_app('proveedor.editar', ['entidad' => 'proveedor', 'entidad_id' => $id, 'detalle' => $razon]); }
        return ['ok' => true];
    }
    if ($c !== '') {
        $p = prov_by_cuit($c);
        if ($p) {
            q('UPDATE proveedores SET razon_social = ?, categoria_sugerida_id = ? WHERE id = ?', [$razon, $catId, $p['id']]);
            return ['ok' => true, 'existia' => true];
        }
    }
    q('INSERT INTO proveedores (cuit, razon_social, cuit_valido, categoria_sugerida_id) VALUES (?, ?, ?, ?)',
        [$cuitVal, $razon, $valido, $catId]);
    $nid = (int) db()->lastInsertId();
    if (function_exists('log_app')) { log_app('proveedor.crear', ['entidad' => 'proveedor', 'entidad_id' => $nid, 'detalle' => $razon]); }
    return ['ok' => true];
}

// ---- Conceptos (categorías) ----
function conceptos_list(): array
{
    return q('SELECT id, nombre FROM conceptos WHERE activo = 1 AND eliminado_en IS NULL ORDER BY orden, nombre')->fetchAll();
}

/** Categorías vivas (no eliminadas), con su cantidad de comprobantes. */
function conceptos_all(): array
{
    return q("SELECT co.id, co.nombre, co.activo, co.orden,
                     (SELECT COUNT(*) FROM comprobantes c WHERE c.concepto_id = co.id AND c.eliminado_en IS NULL) AS usos
              FROM conceptos co
              WHERE co.eliminado_en IS NULL
              ORDER BY co.activo DESC, co.orden, co.nombre")->fetchAll();
}

/** Categorías archivadas (papelera). */
function conceptos_deleted(): array
{
    return q("SELECT co.id, co.nombre, co.eliminado_en,
                     (SELECT COUNT(*) FROM comprobantes c WHERE c.concepto_id = co.id AND c.eliminado_en IS NULL) AS usos
              FROM conceptos co
              WHERE co.eliminado_en IS NOT NULL
              ORDER BY co.eliminado_en DESC")->fetchAll();
}

/** Archiva una categoría (soft delete, recuperable). Los comprobantes que la usan conservan el nombre. */
function concepto_delete(int $id): void
{
    $c = q('SELECT nombre FROM conceptos WHERE id = ? AND eliminado_en IS NULL', [$id])->fetch();
    if (!$c) { return; }
    q('UPDATE conceptos SET eliminado_en = NOW(), activo = 0 WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('categoria.eliminar', ['entidad' => 'concepto', 'entidad_id' => $id,
            'detalle' => 'Categoría «' . $c['nombre'] . '» archivada (recuperable, no se borró de la base)']);
    }
}

/** Restaura una categoría desde la papelera. */
function concepto_restore(int $id): void
{
    $c = q('SELECT nombre FROM conceptos WHERE id = ?', [$id])->fetch();
    if (!$c) { return; }
    q('UPDATE conceptos SET eliminado_en = NULL, activo = 1 WHERE id = ?', [$id]);
    if (function_exists('log_app')) {
        log_app('categoria.restaurar', ['entidad' => 'concepto', 'entidad_id' => $id,
            'detalle' => 'Categoría «' . $c['nombre'] . '» restaurada desde la papelera']);
    }
}

/** Renombra / activa / desactiva una categoría. */
function concepto_update(int $id, string $nombre, bool $activo): array
{
    $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
    $len = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);
    if ($nombre === '' || $len < 2) { return ['ok' => false, 'error' => 'El nombre es muy corto.']; }
    if ($len > 60) { return ['ok' => false, 'error' => 'El nombre es demasiado largo (máx. 60).']; }
    $dup = q('SELECT id FROM conceptos WHERE LOWER(nombre) = LOWER(?) AND id <> ? LIMIT 1', [$nombre, $id])->fetch();
    if ($dup) { return ['ok' => false, 'error' => 'Ya existe otra categoría con ese nombre.']; }
    q('UPDATE conceptos SET nombre = ?, activo = ? WHERE id = ?', [$nombre, $activo ? 1 : 0, $id]);
    if (function_exists('log_app')) {
        log_app('categoria.editar', ['entidad' => 'concepto', 'entidad_id' => $id,
            'detalle' => '«' . $nombre . '» · ' . ($activo ? 'activa' : 'inactiva')]);
    }
    return ['ok' => true];
}

/**
 * Crea una categoría nueva (o devuelve la existente si ya está, sin duplicar).
 * Devuelve ['ok'=>bool, 'id'=>int, 'nombre'=>string, 'existia'=>bool] | ['ok'=>false,'error'=>...].
 */
function concepto_create(string $nombre): array
{
    $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
    $len = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);
    if ($nombre === '' || $len < 2) {
        return ['ok' => false, 'error' => 'El nombre es muy corto.'];
    }
    if ($len > 60) {
        return ['ok' => false, 'error' => 'El nombre es demasiado largo (máx. 60).'];
    }
    // Reutilizar si ya existe (case-insensitive), reactivando/desarchivando si hacía falta.
    $ex = q('SELECT id, nombre, activo, eliminado_en FROM conceptos WHERE LOWER(nombre) = LOWER(?) LIMIT 1', [$nombre])->fetch();
    if ($ex) {
        if ((int)$ex['activo'] !== 1 || $ex['eliminado_en'] !== null) {
            q('UPDATE conceptos SET activo = 1, eliminado_en = NULL WHERE id = ?', [$ex['id']]);
        }
        return ['ok' => true, 'id' => (int)$ex['id'], 'nombre' => $ex['nombre'], 'existia' => true];
    }
    $orden = (int) db()->query('SELECT COALESCE(MAX(orden), 0) + 10 FROM conceptos')->fetchColumn();
    q('INSERT INTO conceptos (nombre, activo, orden) VALUES (?, 1, ?)', [$nombre, $orden]);
    $id = (int) db()->lastInsertId();
    if (function_exists('log_app')) {
        log_app('categoria.crear', ['entidad' => 'concepto', 'entidad_id' => $id, 'detalle' => 'Categoría «' . $nombre . '»']);
    }
    return ['ok' => true, 'id' => $id, 'nombre' => $nombre, 'existia' => false];
}

// ---- Comprobantes ----
function comp_find(int $id): ?array
{
    return q('SELECT c.*, p.cuit AS prov_cuit, p.razon_social AS prov_razon
              FROM comprobantes c
              LEFT JOIN proveedores p ON p.id = c.proveedor_id
              WHERE c.id = ? AND c.eliminado_en IS NULL LIMIT 1', [$id])->fetch() ?: null;
}

/** Validación pura (sin DB). Devuelve [errores, limpio]. */
function valida_comprobante(array $in): array
{
    $err = [];
    $c = [];

    $fecha = trim($in['fecha_emision'] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        $err['fecha_emision'] = 'Fecha inválida.';
    } else {
        $c['fecha_emision'] = $fecha;
        $c['periodo'] = substr($fecha, 0, 7);
    }

    $tipo = trim($in['tipo_comprobante'] ?? '');
    if (!in_array($tipo, tipos_comprobante(), true)) {
        $err['tipo_comprobante'] = 'Elegí un tipo de comprobante.';
    } else {
        $c['tipo_comprobante'] = $tipo;
    }

    $letra = strtoupper(trim($in['letra'] ?? ''));
    $c['letra'] = in_array($letra, ['A', 'B', 'C'], true) ? $letra : null;

    $c['punto_venta'] = trim($in['punto_venta'] ?? '') ?: null;
    $c['numero'] = trim($in['numero'] ?? '') ?: null;

    $cuit = normalizar_cuit($in['cuit'] ?? '');
    if ($cuit !== '' && !validar_cuit($cuit)) {
        $err['cuit'] = 'El CUIT no es válido (dígito verificador).';
    }
    $c['cuit'] = $cuit;
    $c['razon_social'] = trim($in['razon_social'] ?? '');

    $concepto_id = (int)($in['concepto_id'] ?? 0);
    if ($concepto_id <= 0) {
        $err['concepto_id'] = 'Elegí una categoría.';
    } else {
        $c['concepto_id'] = $concepto_id;
    }

    $moneda = ($in['moneda'] ?? 'ARS') === 'USD' ? 'USD' : 'ARS';
    $c['moneda'] = $moneda;
    $tc = parse_importe($in['tipo_cambio'] ?? '');
    $c['tipo_cambio'] = ($moneda === 'USD' && $tc > 0) ? $tc : null;

    $neto  = parse_importe($in['importe_neto'] ?? 0);
    $iva   = parse_importe($in['importe_iva'] ?? 0);
    $perc  = parse_importe($in['importe_percepcion'] ?? 0);
    $total = parse_importe($in['importe_total'] ?? 0);
    $tasaRaw = $in['tasa_iva'] ?? '';
    $tasa  = ($tasaRaw === '' || $tasaRaw === null) ? null : (float)$tasaRaw;

    if ($neto < 0 || $iva < 0 || $perc < 0 || $total < 0) {
        $err['importes'] = 'Los importes no pueden ser negativos.';
    }
    $c['importe_neto'] = $neto;
    $c['importe_iva'] = $iva;
    $c['importe_percepcion'] = $perc;
    $c['tasa_iva'] = $tasa;

    if ($total <= 0) {
        $total = round($neto + $iva + $perc, 2);
    } else {
        $calc = round($neto + $iva + $perc, 2);
        if (abs($calc - $total) > 0.05) {
            $err['importe_total'] = 'El total (' . money($total) . ') no coincide con Neto+IVA+Percepción (' . money($calc) . ').';
        }
    }
    $c['importe_total'] = $total;

    if ($total <= 0 && !isset($err['importes'])) {
        $err['importe_total'] = 'Cargá al menos el importe total.';
    }

    $c['observaciones'] = trim($in['observaciones'] ?? '') ?: null;

    return [$err, $c];
}

/**
 * Inserta o actualiza un comprobante. Devuelve el id.
 * $opts admite: estado, origen, imagen_path, ocr_text.
 */
function comp_save(array $c, ?int $id, int $uid, array $opts = []): int
{
    $prov_id = prov_find_or_create($c['cuit'] ?? '', $c['razon_social'] ?? '');
    $fields = [
        'periodo'            => $c['periodo'],
        'fecha_emision'      => $c['fecha_emision'],
        'tipo_comprobante'   => $c['tipo_comprobante'],
        'letra'              => $c['letra'],
        'punto_venta'        => $c['punto_venta'],
        'numero'             => $c['numero'],
        'proveedor_id'       => $prov_id,
        'concepto_id'        => $c['concepto_id'],
        'moneda'             => $c['moneda'],
        'tipo_cambio'        => $c['tipo_cambio'],
        'importe_neto'       => $c['importe_neto'],
        'tasa_iva'           => $c['tasa_iva'],
        'importe_iva'        => $c['importe_iva'],
        'importe_percepcion' => $c['importe_percepcion'],
        'importe_total'      => $c['importe_total'],
        'observaciones'      => $c['observaciones'],
    ];
    if (array_key_exists('imagen_path', $opts)) { $fields['imagen_path'] = $opts['imagen_path']; }
    if (array_key_exists('ocr_text', $opts))    { $fields['ocr_text']    = $opts['ocr_text']; }

    if ($id) {
        if (!empty($opts['estado'])) {
            $fields['estado'] = $opts['estado'];
            if ($opts['estado'] === 'verificado') {
                $fields['verificado_por'] = $uid;
                $fields['verificado_en']  = date('Y-m-d H:i:s');
            }
        }
        $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($fields)));
        $vals = array_values($fields);
        $vals[] = $id;
        q("UPDATE comprobantes SET $set WHERE id = ?", $vals);
        prov_entrenar_categoria($prov_id);
        if (function_exists('log_app')) {
            $accion = 'comprobante.editar';
            if (($opts['estado'] ?? '') === 'verificado')      { $accion = 'comprobante.verificar'; }
            elseif (($opts['estado'] ?? '') === 'rechazado')   { $accion = 'comprobante.rechazar'; }
            log_app($accion, ['entidad' => 'comprobante', 'entidad_id' => $id, 'detalle' => comp_etiqueta($c)]);
        }
        return $id;
    }

    $fields['estado']     = $opts['estado'] ?? 'verificado';
    $fields['origen']     = $opts['origen'] ?? 'manual';
    $fields['creado_por'] = $uid;
    if ($fields['estado'] === 'verificado') {
        $fields['verificado_por'] = $uid;
        $fields['verificado_en']  = date('Y-m-d H:i:s');
    }
    $cols = implode(', ', array_keys($fields));
    $ph = implode(', ', array_fill(0, count($fields), '?'));
    q("INSERT INTO comprobantes ($cols) VALUES ($ph)", array_values($fields));
    $nid = (int)db()->lastInsertId();
    prov_entrenar_categoria($prov_id);
    if (function_exists('log_app')) {
        log_app('comprobante.crear', ['entidad' => 'comprobante', 'entidad_id' => $nid,
            'detalle' => comp_etiqueta($c) . ' · ' . ($fields['estado'] ?? '')]);
    }
    return $nid;
}

/**
 * Auto-entrenamiento de la categoría sugerida del proveedor (sin costo, sin IA externa).
 * Fija como sugerida la categoría MÁS usada por ese proveedor en sus comprobantes vivos.
 * Efecto: un proveedor nuevo aprende de su 1ª carga, y a medida que los usuarios cargan,
 * la sugerencia converge a la categoría real y se corrige sola (nunca queda "clavada").
 * Así el OCR, al detectar el CUIT, ya trae la categoría correcta (api-proveedor).
 */
function prov_entrenar_categoria(?int $prov_id): void
{
    if (!$prov_id) { return; }
    try {
        $row = q(
            "SELECT concepto_id, COUNT(*) AS n
               FROM comprobantes
              WHERE proveedor_id = ? AND concepto_id IS NOT NULL AND eliminado_en IS NULL
              GROUP BY concepto_id
              ORDER BY n DESC, MAX(id) DESC
              LIMIT 1",
            [$prov_id]
        )->fetch();
        if ($row && !empty($row['concepto_id'])) {
            q('UPDATE proveedores SET categoria_sugerida_id = ? WHERE id = ?', [(int)$row['concepto_id'], $prov_id]);
        }
    } catch (\Throwable $e) { /* si falta la columna, no rompe el guardado */ }
}

/** Etiqueta legible de un comprobante para los logs (tipo/letra + total). */
function comp_etiqueta(array $c): string
{
    $t = trim(($c['tipo_comprobante'] ?? '') . ' ' . ($c['letra'] ?? ''));
    $m = money($c['importe_total'] ?? 0, $c['moneda'] ?? 'ARS');
    return trim($t . ' · ' . $m, ' ·');
}

/** Cambia el estado de un comprobante (verificado / rechazado / pendiente_revision). */
function comp_set_estado(int $id, string $estado, int $uid): void
{
    $estado = in_array($estado, ['verificado', 'rechazado', 'pendiente_revision'], true) ? $estado : 'pendiente_revision';
    if ($estado === 'verificado') {
        q('UPDATE comprobantes SET estado = ?, verificado_por = ?, verificado_en = NOW() WHERE id = ?', [$estado, $uid, $id]);
    } else {
        q('UPDATE comprobantes SET estado = ? WHERE id = ?', [$estado, $id]);
    }
    if (function_exists('log_app')) {
        log_app('comprobante.estado', ['entidad' => 'comprobante', 'entidad_id' => $id, 'detalle' => '→ ' . $estado]);
    }
}

/**
 * "Borra" un comprobante — REGLA DE ORO: no se borra físicamente. Se archiva
 * (soft delete) con eliminado_en/eliminado_por: deja de verse para los usuarios
 * pero sigue en la base para auditoría, y el borrado queda en el CRON.
 * Se puede restaurar con comp_restore().
 */
function comp_delete(int $id): void
{
    $c = comp_find($id); // sólo encuentra los no-eliminados
    if (!$c) { return; }
    q('UPDATE comprobantes SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ? AND eliminado_en IS NULL',
        [current_user_id() ?: null, $id]);
    // Espejo en Drive: mover el archivo a "Archivados" (queda auditado en el CRON).
    if (!empty($c['drive_file_id']) && function_exists('drive_archivar')) {
        try {
            $ok = drive_archivar((string)$c['drive_file_id']);
            if (function_exists('log_app')) {
                log_app($ok ? 'drive.archivar' : 'drive.fallo', ['tipo' => $ok ? 'accion' : 'error',
                    'entidad' => 'comprobante', 'entidad_id' => $id,
                    'detalle' => $ok ? 'Imagen movida a «Archivados» en Drive' : 'No se pudo mover la imagen a «Archivados» en Drive']);
            }
        } catch (\Throwable $e) {
            if (function_exists('log_error')) { log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine()); }
        }
    }
    if (function_exists('log_app')) {
        log_app('comprobante.eliminar', ['entidad' => 'comprobante', 'entidad_id' => $id,
            'detalle' => comp_etiqueta($c) . ' · archivado (recuperable, no se borró de la base)']);
    }
}

/** Restaura un comprobante archivado (admin). */
function comp_restore(int $id): void
{
    $c = q('SELECT id, drive_file_id, fecha_emision FROM comprobantes WHERE id = ? LIMIT 1', [$id])->fetch();
    q('UPDATE comprobantes SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ?', [$id]);
    // Espejo en Drive: devolver el archivo a su carpeta AÑO/mes (queda auditado).
    if ($c && !empty($c['drive_file_id']) && function_exists('drive_restaurar')) {
        try {
            $ok = drive_restaurar((string)$c['drive_file_id'], (string)$c['fecha_emision']);
            if (function_exists('log_app')) {
                log_app($ok ? 'drive.restaurar' : 'drive.fallo', ['tipo' => $ok ? 'accion' : 'error',
                    'entidad' => 'comprobante', 'entidad_id' => $id,
                    'detalle' => $ok ? 'Imagen devuelta a su carpeta AÑO/mes en Drive' : 'No se pudo devolver la imagen a su carpeta en Drive']);
            }
        } catch (\Throwable $e) {
            if (function_exists('log_error')) { log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine()); }
        }
    }
    if (function_exists('log_app')) {
        log_app('comprobante.restaurar', ['entidad' => 'comprobante', 'entidad_id' => $id, 'detalle' => 'Restaurado desde la papelera']);
    }
}

/** Guarda una imagen/PDF subido y devuelve la ruta relativa (año/mes/archivo) o null. */
function guardar_imagen(array $file): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return null;
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($file['tmp_name']) ?: '') : ($file['type'] ?? '');
    $map = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $ext = $map[$mime] ?? null;
    if (!$ext || ($file['size'] ?? 0) > 15 * 1024 * 1024) {
        return null;
    }
    $sub = date('Y/m');
    $dir = STORAGE . '/uploads/' . $sub;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    $ok = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $dest)
        : @rename($file['tmp_name'], $dest);
    return $ok ? ($sub . '/' . $name) : null;
}

/** Construye el WHERE + params a partir de los filtros (reutilizable). */
function comp_where(array $f): array
{
    $w = ['c.eliminado_en IS NULL']; // soft delete: nunca mostrar archivados
    $p = [];
    if (!empty($f['periodo']))     { $w[] = 'c.periodo = ?';     $p[] = $f['periodo']; }
    if (!empty($f['concepto_id'])) { $w[] = 'c.concepto_id = ?'; $p[] = (int)$f['concepto_id']; }
    if (!empty($f['estado']))      { $w[] = 'c.estado = ?';      $p[] = $f['estado']; }
    if (!empty($f['moneda']))      { $w[] = 'c.moneda = ?';      $p[] = $f['moneda']; }
    if (!empty($f['q'])) {
        $w[] = '(p.razon_social LIKE ? OR p.cuit LIKE ? OR c.numero LIKE ?)';
        $like = '%' . $f['q'] . '%';
        $p[] = $like; $p[] = $like; $p[] = $like;
    }
    return [$w ? ('WHERE ' . implode(' AND ', $w)) : '', $p];
}

const COMP_SELECT = "SELECT c.*, p.cuit AS prov_cuit, p.razon_social AS prov_razon, co.nombre AS concepto
                     FROM comprobantes c
                     LEFT JOIN proveedores p ON p.id = c.proveedor_id
                     LEFT JOIN conceptos co ON co.id = c.concepto_id";

/** ORDER BY seguro (whitelist). Default: lo cargado recientemente arriba (c.id DESC). */
function comp_order(array $f): string
{
    $map = [
        'reciente'  => 'c.id',
        'fecha'     => 'c.fecha_emision',
        'proveedor' => 'p.razon_social',
        'concepto'  => 'co.nombre',
        'neto'      => 'c.importe_neto',
        'iva'       => 'c.importe_iva',
        'total'     => 'c.importe_total',
        'estado'    => 'c.estado',
    ];
    $sort = $f['sort'] ?? 'reciente';
    if (!isset($map[$sort])) { $sort = 'reciente'; }
    $dir = (strtolower((string)($f['dir'] ?? 'desc')) === 'asc') ? 'ASC' : 'DESC';
    return "ORDER BY {$map[$sort]} $dir, c.id DESC";
}

/** Lista con filtros + paginación. Devuelve [rows, total, page, per, totales]. */
function comp_list(array $f): array
{
    [$where, $p] = comp_where($f);
    $page = max(1, (int)($f['page'] ?? 1));
    $per = 50;
    $off = ($page - 1) * $per;

    $total = (int) q("SELECT COUNT(*) FROM comprobantes c LEFT JOIN proveedores p ON p.id = c.proveedor_id $where", $p)->fetchColumn();
    $rows = q(COMP_SELECT . " $where " . comp_order($f) . " LIMIT $per OFFSET $off", $p)->fetchAll();
    $totales = q("SELECT c.moneda, SUM(c.importe_neto) neto, SUM(c.importe_iva) iva,
                         SUM(c.importe_percepcion) perc, SUM(c.importe_total) total
                  FROM comprobantes c LEFT JOIN proveedores p ON p.id = c.proveedor_id
                  $where GROUP BY c.moneda", $p)->fetchAll();
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'totales' => $totales];
}

/** Todos los comprobantes que matchean los filtros (sin paginar), para exportar. */
function comp_all(array $f): array
{
    [$where, $p] = comp_where($f);
    return q(COMP_SELECT . " $where " . comp_order($f), $p)->fetchAll();
}

function periodos_disponibles(): array
{
    return q("SELECT DISTINCT periodo FROM comprobantes WHERE eliminado_en IS NULL ORDER BY periodo DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// ============================================================
//  Reportes / tableros
// ============================================================
function rep_resumen(string $periodo, string $moneda): array
{
    $w = ['eliminado_en IS NULL', 'moneda = ?'];
    $p = [$moneda];
    if ($periodo !== '') { $w[] = 'periodo = ?'; $p[] = $periodo; }
    $where = 'WHERE ' . implode(' AND ', $w);
    return q("SELECT COUNT(*) n, COALESCE(SUM(importe_neto),0) neto, COALESCE(SUM(importe_iva),0) iva,
                     COALESCE(SUM(importe_percepcion),0) perc, COALESCE(SUM(importe_total),0) total
              FROM comprobantes $where", $p)->fetch();
}

function rep_por_categoria(string $periodo, string $moneda): array
{
    $w = ['c.eliminado_en IS NULL', 'c.moneda = ?'];
    $p = [$moneda];
    if ($periodo !== '') { $w[] = 'c.periodo = ?'; $p[] = $periodo; }
    $where = 'WHERE ' . implode(' AND ', $w);
    return q("SELECT COALESCE(co.nombre,'(sin categoría)') categoria, COUNT(*) n, SUM(c.importe_total) total
              FROM comprobantes c LEFT JOIN conceptos co ON co.id = c.concepto_id
              $where GROUP BY c.concepto_id ORDER BY total DESC", $p)->fetchAll();
}

function rep_por_mes(string $moneda): array
{
    return q("SELECT periodo, COUNT(*) n, SUM(importe_total) total
              FROM comprobantes WHERE eliminado_en IS NULL AND moneda = ?
              GROUP BY periodo ORDER BY periodo DESC LIMIT 12", [$moneda])->fetchAll();
}

function rep_top_proveedores(string $periodo, string $moneda, int $limit = 10): array
{
    $w = ['c.eliminado_en IS NULL', 'c.moneda = ?'];
    $p = [$moneda];
    if ($periodo !== '') { $w[] = 'c.periodo = ?'; $p[] = $periodo; }
    $where = 'WHERE ' . implode(' AND ', $w);
    return q("SELECT COALESCE(pr.razon_social,'(sin proveedor)') prov, COUNT(*) n, SUM(c.importe_total) total
              FROM comprobantes c LEFT JOIN proveedores pr ON pr.id = c.proveedor_id
              $where GROUP BY c.proveedor_id ORDER BY total DESC LIMIT " . (int)$limit, $p)->fetchAll();
}
