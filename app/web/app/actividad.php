<?php
declare(strict_types=1);

/**
 * Actividad / auditoría — alimenta la sección "CRON".
 *
 * Dos canales:
 *   · app  → qué hace cada usuario (alta/edición/borrado/verificación,
 *            gestión de usuarios y tokens, login/logout) + errores.
 *   · api  → cada pedido a la API de solo lectura.
 *
 * `log_evento()` NUNCA debe romper una request: todo va dentro de try/catch.
 * Si la tabla `actividad` aún no existe, simplemente no registra (y sigue).
 */

/**
 * Inserta un evento.
 * @param string $canal  'app' | 'api'
 * @param string $accion identificador corto (ej. 'comprobante.crear')
 * @param array  $o      tipo|actor|entidad|entidad_id|detalle|usuario_id
 */
function log_evento(string $canal, string $accion, array $o = []): void
{
    try {
        $uid = $o['usuario_id'] ?? ($_SESSION['uid'] ?? null);
        $actor = $o['actor'] ?? null;
        if ($actor === null && !empty($_SESSION['uid']) && function_exists('auth_user')) {
            $u = auth_user();
            $actor = $u['nombre'] ?? null;
        }
        q('INSERT INTO actividad (canal, tipo, usuario_id, actor, accion, entidad, entidad_id, detalle, ip)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            in_array($canal, ['app', 'api'], true) ? $canal : 'app',
            $o['tipo'] ?? 'accion',
            $uid ? (int)$uid : null,
            $actor !== null ? mb_substr((string)$actor, 0, 160) : null,
            mb_substr($accion, 0, 120),
            $o['entidad'] ?? null,
            isset($o['entidad_id']) ? (int)$o['entidad_id'] : null,
            isset($o['detalle']) ? mb_substr((string)$o['detalle'], 0, 500) : null,
            function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);
    } catch (\Throwable $e) {
        // El log jamás debe tirar abajo la operación real.
    }
}

function log_app(string $accion, array $o = []): void { log_evento('app', $accion, $o); }
function log_api(string $accion, array $o = []): void { log_evento('api', $accion, $o); }

/**
 * Registra un error para auditoría con un CÓDIGO estable por ubicación
 * (mismo archivo:línea → mismo código), para poder buscar y arreglar la causa.
 */
function log_error(string $accion, string $mensaje, string $file, int $line, string $severidad = ''): void
{
    $loc  = basename($file) . ':' . $line;
    $code = 'ERR-' . strtoupper(substr(md5($file . ':' . $line), 0, 6));
    $det  = $code . ' · ' . ($severidad !== '' ? $severidad . ' · ' : '') . $mensaje . ' · ' . $loc;
    log_evento('app', $accion, ['tipo' => 'error', 'actor' => 'sistema', 'detalle' => $det]);
}

/** ¿Existe la tabla? (para degradar con gracia si aún no se migró). */
function actividad_disponible(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->query('SELECT 1 FROM actividad LIMIT 1');
        return $ok = true;
    } catch (\Throwable $e) {
        return $ok = false;
    }
}

/** Cantidad de meses que se muestran por defecto en pantalla. */
const ACTIVIDAD_VENTANA_MESES = 4;

/** Construye WHERE + params a partir de los filtros del CRON. */
function actividad_where(array $f): array
{
    $w = ['canal = ?'];
    $p = [in_array($f['canal'] ?? 'app', ['app', 'api'], true) ? $f['canal'] : 'app'];
    if (!empty($f['tipo']))  { $w[] = 'tipo = ?';        $p[] = $f['tipo']; }
    // Ventana por defecto: últimos N meses (rendimiento). El histórico NUNCA se
    // borra: si el usuario pide una fecha "Desde" anterior, se consulta completo.
    $floor = date('Y-m-d', strtotime('-' . ACTIVIDAD_VENTANA_MESES . ' months'));
    $desde = !empty($f['desde']) ? $f['desde'] : $floor;
    $w[] = 'creado_en >= ?'; $p[] = $desde . ' 00:00:00';
    if (!empty($f['hasta'])) { $w[] = 'creado_en <= ?';  $p[] = $f['hasta'] . ' 23:59:59'; }
    if (!empty($f['q'])) {
        $w[] = '(actor LIKE ? OR accion LIKE ? OR detalle LIKE ? OR ip LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    return ['WHERE ' . implode(' AND ', $w), $p];
}

/** Lista paginada de eventos. Devuelve [rows, total, page, per]. */
function actividad_list(array $f): array
{
    if (!actividad_disponible()) {
        return ['rows' => [], 'total' => 0, 'page' => 1, 'per' => 50, 'sin_tabla' => true];
    }
    [$where, $p] = actividad_where($f);
    $page = max(1, (int)($f['page'] ?? 1));
    $per = 15; // pocas filas en pantalla + paginación (como Comprobantes)
    $off = ($page - 1) * $per;
    $total = (int) q("SELECT COUNT(*) FROM actividad $where", $p)->fetchColumn();
    $rows = q("SELECT a.*, u.email AS usuario_email, u.avatar_url AS usuario_avatar
               FROM actividad a LEFT JOIN usuarios u ON u.id = a.usuario_id
               $where ORDER BY a.creado_en DESC, a.id DESC LIMIT $per OFFSET $off", $p)->fetchAll();
    return [
        'rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per,
        'sin_tabla' => false,
        'ventana_default' => empty($f['desde']),        // true = mostrando solo últimos N meses
        'ventana_meses'   => ACTIVIDAD_VENTANA_MESES,
    ];
}

/** Conteo por tipo dentro de la ventana por defecto (alimenta las "carpetas"). */
function actividad_conteo_tipos(string $canal): array
{
    $out = ['total' => 0, 'nav' => 0, 'accion' => 0, 'auth' => 0, 'error' => 0, 'sistema' => 0];
    if (!actividad_disponible()) return $out;
    $canal = in_array($canal, ['app', 'api'], true) ? $canal : 'app';
    $floor = date('Y-m-d', strtotime('-' . ACTIVIDAD_VENTANA_MESES . ' months')) . ' 00:00:00';
    $rows = q("SELECT tipo, COUNT(*) n FROM actividad WHERE canal = ? AND creado_en >= ? GROUP BY tipo", [$canal, $floor])->fetchAll();
    foreach ($rows as $r) {
        if (isset($out[$r['tipo']])) { $out[$r['tipo']] = (int)$r['n']; }
        $out['total'] += (int)$r['n'];
    }
    return $out;
}

/** Resumen para las tarjetas del CRON (total, hoy, errores) de un canal. */
function actividad_resumen(string $canal): array
{
    if (!actividad_disponible()) return ['total' => 0, 'hoy' => 0, 'errores' => 0, 'ultimo' => null];
    $canal = in_array($canal, ['app', 'api'], true) ? $canal : 'app';
    $r = q("SELECT
                COUNT(*) total,
                SUM(CASE WHEN DATE(creado_en) = CURDATE() THEN 1 ELSE 0 END) hoy,
                SUM(CASE WHEN tipo = 'error' THEN 1 ELSE 0 END) errores,
                MAX(creado_en) ultimo
            FROM actividad WHERE canal = ?", [$canal])->fetch();
    return [
        'total'   => (int)($r['total'] ?? 0),
        'hoy'     => (int)($r['hoy'] ?? 0),
        'errores' => (int)($r['errores'] ?? 0),
        'ultimo'  => $r['ultimo'] ?? null,
    ];
}
