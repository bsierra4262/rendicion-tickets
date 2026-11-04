<?php
declare(strict_types=1);

// ============================================================
//  Google Drive — respaldo automático de imágenes de comprobantes
//
//  · OAuth "offline" (refresh token) para subir en nombre de la cuenta
//    conectada por el administrador. Reutiliza el mismo redirect que el login
//    (google-callback), así NO hay que registrar otra URI en Google Cloud.
//  · Facturas Compra (compras ÚNICAS = comprobantes):
//      <Facturas Compra> / AÑO / "AÑO MM - Mes" / archivo   (+ <Facturas Compra> / Archivados)
//  · Gastos Recurrentes:
//      <Gastos Recurrentes> / Proveedor / AÑO / "AÑO MM - Mes" / archivo   (+ / Archivados)
//    Una factura cargada desde Escanear/Nuevo comprobante cuyo CUIT es de un
//    proveedor de Gastos recurrentes se guarda como GASTO (ver gr_desde_factura).
//  · Drive es la fuente de verdad: la DB guarda el id/link y NO hay copia local.
//    EXCEPCIÓN: si Drive falla (sesión sin Drive, token vencido, red), el archivo
//    se guarda en el servidor (storage/uploads/pendientes-drive/…) para no
//    perderlo, y se sube solo la próxima vez que haya Drive en la sesión
//    (drive_sincronizar_pendientes). Al subirse, la copia local se borra.
//  · Reemplazo: si se sube un archivo nuevo para algo que ya tenía uno en Drive,
//    el anterior se mueve a «Archivados» (no quedan duplicados en la carpeta).
// ============================================================

// ---- Ajustes (clave/valor en app_settings) ----
function setting_get(string $k, ?string $def = null): ?string
{
    try {
        $r = q('SELECT valor FROM app_settings WHERE clave = ? LIMIT 1', [$k])->fetch();
        return $r ? (string)$r['valor'] : $def;
    } catch (\Throwable $e) {
        return $def;
    }
}

function setting_set(string $k, string $v): void
{
    try {
        q('INSERT INTO app_settings (clave, valor) VALUES (?, ?)
           ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$k, $v]);
    } catch (\Throwable $e) {}
}

function setting_del(string $k): void
{
    try { q('DELETE FROM app_settings WHERE clave = ?', [$k]); } catch (\Throwable $e) {}
}

// ---- Configuración ----
const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive';

/** Nombre (para logs/rutas legibles) de la carpeta de comprobantes de compra. */
const DRIVE_CARPETA_COMPRAS = 'Facturas Compra';

/**
 * ID de la carpeta «Facturas Compra» en Drive (configurable con el ajuste
 * drive_compras_root). Dentro va AÑO / "AÑO MM - Mes" y «Archivados».
 * Facturas Compra = compras ÚNICAS; lo recurrente va a drive_gastos_root().
 */
function drive_compras_root(): string
{
    return (string)setting_get('drive_compras_root', '1HGkmZ67VbqG3oPr5cCr-Q9ZMGq2pZ1q-');
}

/** Prefijo (relativo a STORAGE/uploads) de las copias locales pendientes de subir. */
const DRIVE_PENDIENTES_DIR = 'pendientes-drive';

/**
 * ¿Hay una conexión de Drive en la SESIÓN actual?
 * Por seguridad, los tokens de Drive viven SOLO en la sesión del usuario: se
 * borran al desloguear o en el cierre automático (no queda nada persistente).
 */
function drive_conectado(): bool
{
    return !empty($_SESSION['drive_refresh_token']);
}

/** Devuelve un access token válido para la sesión (lo refresca si hace falta). */
function drive_access_token(): ?string
{
    $tok = (string)($_SESSION['drive_access_token'] ?? '');
    $exp = (int)($_SESSION['drive_access_expira'] ?? 0);
    if ($tok !== '' && $exp > time() + 60) {
        return $tok;
    }
    $rt = (string)($_SESSION['drive_refresh_token'] ?? '');
    if ($rt === '') {
        return null;
    }
    $g = google_cfg();
    $body = http_build_query([
        'client_id'     => $g['client_id'] ?? '',
        'client_secret' => $g['client_secret'] ?? '',
        'refresh_token' => $rt,
        'grant_type'    => 'refresh_token',
    ]);
    $res = drive_http('POST', 'https://oauth2.googleapis.com/token', $body,
        ['Content-Type: application/x-www-form-urlencoded']);
    if (empty($res['access_token'])) {
        if (function_exists('log_error')) {
            log_error('drive.token_error', 'refresh falló: ' . json_encode($res), __FILE__, __LINE__);
        }
        return null;
    }
    $_SESSION['drive_access_token']  = (string)$res['access_token'];
    $_SESSION['drive_access_expira'] = time() + (int)($res['expires_in'] ?? 3600);
    return (string)$res['access_token'];
}

/** Guarda en la sesión la conexión de Drive obtenida en el login (acceso offline). */
function drive_guardar_sesion(array $tok): void
{
    if (empty($tok['refresh_token'])) {
        return;
    }
    $_SESSION['drive_refresh_token'] = (string)$tok['refresh_token'];
    if (!empty($tok['access_token'])) {
        $_SESSION['drive_access_token']  = (string)$tok['access_token'];
        $_SESSION['drive_access_expira'] = time() + (int)($tok['expires_in'] ?? 3600);
    }
}

/**
 * Olvida la conexión de Drive: revoca el token en Google (best-effort, con
 * timeout corto) y lo borra de la sesión. Se invoca en CADA cierre de sesión
 * —manual o automático— por seguridad.
 */
function drive_desconectar(): void
{
    $rt = (string)($_SESSION['drive_refresh_token'] ?? '');
    if ($rt !== '') {
        drive_http('POST', 'https://oauth2.googleapis.com/revoke', http_build_query(['token' => $rt]),
            ['Content-Type: application/x-www-form-urlencoded'], 4);
        if (function_exists('log_app')) {
            log_app('drive.desconectar', ['tipo' => 'auth', 'detalle' => 'Drive desconectado y token revocado (cierre de sesión)']);
        }
    }
    unset($_SESSION['drive_refresh_token'], $_SESSION['drive_access_token'], $_SESSION['drive_access_expira']);
}

// ---- HTTP helper (curl → array) ----
function drive_http(string $method, string $url, $body = null, array $headers = [], int $timeout = 40): array
{
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opt[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opt);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        return ['error' => 'curl', 'error_description' => $err];
    }
    $j = json_decode((string)$res, true);
    if (!is_array($j)) {
        return ['raw' => $res, 'http_code' => $code];
    }
    $j['http_code'] = $code;
    return $j;
}

// ============================================================
//  Nombres (funciones PURAS — testeables sin red)
// ============================================================

/** Nombre del mes en español (1..12). */
function drive_mes_es(int $m): string
{
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo',
        6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre',
        11 => 'Noviembre', 12 => 'Diciembre'];
    return $meses[$m] ?? '';
}

/** Carpeta mensual legible: "AÑO MM - Mes" (ej. "2026 07 - Julio"). */
function drive_carpeta_mes(string $fecha): string
{
    $y = substr($fecha, 0, 4);
    $m = (int)substr($fecha, 5, 2);
    return sprintf('%s %02d - %s', $y, $m, drive_mes_es($m));
}

/** Limpia un texto para usarlo como nombre de archivo/carpeta en Drive. */
function drive_sanitizar(string $s): string
{
    $s = str_replace(['/', '\\'], '-', $s);
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', '', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = function_exists('mb_substr') ? mb_substr($s, 0, 200) : substr($s, 0, 200);
    return trim($s);
}

/**
 * Nombre de archivo legible y ordenable por fecha:
 *   "2026-07-19 · Fact A · 00585-00002182 · FALABELLA SA.jpg"
 */
function drive_nombre_archivo(array $c, string $ext): string
{
    $fecha = (string)($c['fecha_emision'] ?? date('Y-m-d'));
    $tipo  = trim((string)($c['tipo_comprobante'] ?? '') . ' ' . (string)($c['letra'] ?? ''));
    $pv    = (string)($c['punto_venta'] ?? '');
    $nro   = (string)($c['numero'] ?? '');
    $comp  = '';
    if ($pv !== '' || $nro !== '') {
        $comp = ($pv !== '' ? str_pad($pv, 5, '0', STR_PAD_LEFT) : '00000')
              . '-' . ($nro !== '' ? str_pad($nro, 8, '0', STR_PAD_LEFT) : '00000000');
    }
    $razon = (string)($c['prov_razon'] ?? ($c['razon_social'] ?? ''));
    $partes = array_filter([$fecha, $tipo, $comp, $razon], static fn($x) => trim((string)$x) !== '');
    $nombre = drive_sanitizar(implode(' · ', $partes));
    if ($nombre === '') { $nombre = 'comprobante'; }
    return $nombre . '.' . $ext;
}

// ============================================================
//  Operaciones sobre Drive (requieren red)
// ============================================================

/** Busca (o crea) una carpeta por nombre bajo un padre. Devuelve su id o null.
 *  $creada se pone en true si la carpeta no existía y hubo que crearla. */
function drive_folder_id(string $nombre, string $parentId, string $token, bool &$creada = false): ?string
{
    $creada = false;
    // Cache por request: al sincronizar varios archivos no se re-busca la misma carpeta.
    static $cache = [];
    $ck = $parentId . '|' . $nombre;
    if (isset($cache[$ck])) {
        return $cache[$ck];
    }
    $nombreQ = str_replace(['\\', "'"], ['\\\\', "\\'"], $nombre);
    $q = sprintf("name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
        $nombreQ, $parentId);
    $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q'                         => $q,
        'fields'                    => 'files(id,name)',
        'pageSize'                  => 1,
        'supportsAllDrives'         => 'true',
        'includeItemsFromAllDrives' => 'true',
    ]);
    $res = drive_http('GET', $url, null, ['Authorization: Bearer ' . $token]);
    if (!empty($res['files'][0]['id'])) {
        return $cache[$ck] = (string)$res['files'][0]['id'];
    }
    $meta = json_encode([
        'name'     => $nombre,
        'mimeType' => 'application/vnd.google-apps.folder',
        'parents'  => [$parentId],
    ]);
    $res = drive_http('POST', 'https://www.googleapis.com/drive/v3/files?fields=id&supportsAllDrives=true',
        $meta, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
    if (!empty($res['id'])) {
        $creada = true;
        return $cache[$ck] = (string)$res['id'];
    }
    return null;
}

/** Raíz de cada ámbito: 'compras' (Facturas Compra) o 'gastos' (Gastos Recurrentes). */
function drive_root_ambito(string $ambito): ?string
{
    $r = $ambito === 'gastos' ? drive_gastos_root() : drive_compras_root();
    return $r !== '' ? $r : null;
}

// ============================================================
//  Copia local de respaldo (sólo cuando Drive falla)
// ============================================================

/**
 * Guarda el archivo subido en STORAGE/uploads/pendientes-drive/AÑO/MM/ para no
 * perderlo cuando Drive no está disponible. Devuelve la ruta relativa o null.
 */
function drive_guardar_local(string $tmp, string $ext): ?string
{
    if (!defined('STORAGE') || !is_file($tmp)) { return null; }
    $sub = DRIVE_PENDIENTES_DIR . '/' . date('Y/m');
    $dir = STORAGE . '/uploads/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) { return null; }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : @copy($tmp, $dest);
    return $ok ? ($sub . '/' . $name) : null;
}

/** ¿La ruta local es una copia pendiente de subir a Drive? (pura, testeable) */
function drive_es_pendiente(?string $rel): bool
{
    return $rel !== null && strpos($rel, DRIVE_PENDIENTES_DIR . '/') === 0;
}

/** Ruta absoluta y segura (dentro de STORAGE/uploads) de un archivo local, o null. */
function drive_local_abs(?string $rel): ?string
{
    if (!defined('STORAGE') || $rel === null || $rel === '') { return null; }
    $real = realpath(STORAGE . '/uploads/' . $rel);
    $base = realpath(STORAGE . '/uploads');
    if (!$real || !$base || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) { return null; }
    return $real;
}

/** Sube un archivo (multipart) a una carpeta. Devuelve la respuesta o null. */
function drive_subir(string $localAbs, string $nombre, string $parentId, string $mime, string $token): ?array
{
    if (!is_file($localAbs)) {
        return null;
    }
    $meta = json_encode(['name' => $nombre, 'parents' => [$parentId]]);
    $boundary = 'ith' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\n"
          . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
          . $meta . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: $mime\r\n\r\n"
          . (string)file_get_contents($localAbs) . "\r\n"
          . "--$boundary--";
    $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart'
         . '&fields=id,webViewLink&supportsAllDrives=true';
    $res = drive_http('POST', $url, $body,
        ['Authorization: Bearer ' . $token, 'Content-Type: multipart/related; boundary=' . $boundary]);
    return !empty($res['id']) ? $res : null;
}

/** Mapea extensión → MIME. */
function drive_mime_de(string $ext): string
{
    $m = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'pdf' => 'application/pdf'];
    return $m[strtolower($ext)] ?? 'application/octet-stream';
}

/**
 * Sube a Drive la imagen de un comprobante. Crea las carpetas AÑO/mes si no
 * existen y registra en el CRON la creación de carpetas.
 * @param array  $c       datos del comprobante (fecha_emision, tipo, etc.) para el nombre
 * @param string $absPath ruta ABSOLUTA del archivo a subir (puede ser el temporal del upload)
 * @param string $ext     extensión (jpg/png/webp/pdf)
 * @return array          ['id','link','ruta'] si OK, o ['error'=>motivo] si falló.
 */
function drive_subir_comprobante(array $c, string $absPath, string $ext): array
{
    if (!drive_conectado()) { return ['error' => 'Drive no conectado en la sesión']; }
    $token = drive_access_token();
    if (!$token) { return ['error' => 'no se pudo obtener el access token (refresh falló)']; }
    if (!is_file($absPath)) { return ['error' => 'no existe el archivo a subir']; }
    $root = drive_compras_root();
    if ($root === '') { return ['error' => 'no hay carpeta de ' . DRIVE_CARPETA_COMPRAS . ' configurada']; }

    $fecha = (string)($c['fecha_emision'] ?? date('Y-m-d'));
    $anio  = substr($fecha, 0, 4);
    $mesN  = drive_carpeta_mes($fecha);

    $creadaA = false; $creadaM = false;
    $fAnio = drive_folder_id($anio, $root, $token, $creadaA);
    if (!$fAnio) { return ['error' => 'no se pudo crear/acceder la carpeta ' . $anio]; }
    $fMes = drive_folder_id($mesN, $fAnio, $token, $creadaM);
    if (!$fMes) { return ['error' => 'no se pudo crear/acceder la carpeta ' . $mesN]; }
    if (($creadaA || $creadaM) && function_exists('log_app')) {
        log_app('drive.carpeta', ['tipo' => 'accion',
            'detalle' => 'Carpeta creada en Drive: ' . DRIVE_CARPETA_COMPRAS . ' / ' . $anio . ' / ' . $mesN]);
    }

    $nombre = drive_nombre_archivo($c, $ext);
    $res = drive_subir($absPath, $nombre, $fMes, drive_mime_de($ext), $token);
    if (!$res) { return ['error' => 'falló la subida del archivo ' . $nombre]; }
    $id = (string)$res['id'];
    return [
        'id'   => $id,
        'link' => (string)($res['webViewLink'] ?? ('https://drive.google.com/file/d/' . $id . '/view')),
        'ruta' => DRIVE_CARPETA_COMPRAS . '/' . $anio . '/' . $mesN . '/' . $nombre,
    ];
}

/**
 * Descarga los bytes de un archivo de Drive (para servir la imagen sin copia
 * local). Devuelve ['body'=>..., 'mime'=>...] o null.
 */
function drive_descargar(string $fileId): ?array
{
    if ($fileId === '' || !drive_conectado()) { return null; }
    $token = drive_access_token();
    if (!$token) { return null; }
    $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
        . '?alt=media&supportsAllDrives=true');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($body === false || $code !== 200) { return null; }
    return ['body' => $body, 'mime' => $mime ?: 'application/octet-stream'];
}

/**
 * Guarda en la DB el resultado de una subida OK de un comprobante: id/link de
 * Drive, limpia la copia local pendiente y archiva el archivo ANTERIOR si había
 * uno distinto (reemplazo → sin duplicados). Audita todo.
 * @param array $prev  fila previa ['drive_file_id','imagen_path'] (puede ser [])
 */
function drive_comprobante_registrar(int $comprobanteId, array $res, array $prev): void
{
    $prevPath = (string)($prev['imagen_path'] ?? '');
    // Las imágenes locales viejas (antes de Drive) se conservan; sólo se limpia la pendiente.
    $limpiaPath = $prevPath === '' || drive_es_pendiente($prevPath);
    q('UPDATE comprobantes SET drive_file_id = ?, drive_link = ?'
        . ($limpiaPath ? ', imagen_path = NULL' : '') . ' WHERE id = ?',
        [$res['id'], $res['link'], $comprobanteId]);
    if ($prevPath !== '' && drive_es_pendiente($prevPath) && ($abs = drive_local_abs($prevPath))) {
        @unlink($abs);
    }
    if (function_exists('log_app')) {
        log_app('drive.subida', ['tipo' => 'accion', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
            'detalle' => 'Imagen en Drive: ' . ($res['ruta'] ?? '') . ' · ' . $res['link']]);
    }
    $prevId = (string)($prev['drive_file_id'] ?? '');
    if ($prevId !== '' && $prevId !== (string)$res['id']) {
        $ok = drive_archivar($prevId);
        if (function_exists('log_app')) {
            log_app($ok ? 'drive.reemplazo' : 'drive.fallo', ['tipo' => $ok ? 'accion' : 'error',
                'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
                'detalle' => $ok ? 'Imagen anterior reemplazada: movida a «Archivados» en Drive'
                                 : 'Imagen reemplazada, pero no se pudo mover la anterior a «Archivados»']);
        }
    }
}

/**
 * Respalda la imagen de un comprobante recién guardado subiéndola a Drive.
 * Si Drive falla, la guarda en el servidor como PENDIENTE (no se pierde) y se
 * sube sola la próxima vez que haya Drive (drive_sincronizar_pendientes).
 * Si el comprobante ya tenía imagen en Drive, la anterior va a «Archivados».
 * Registra cada paso en la auditoría (CRON). Nunca lanza.
 * @param int    $comprobanteId  id del comprobante ya guardado
 * @param array  $c              datos del comprobante (para el nombre del archivo)
 * @param array  $file           $_FILES['imagen']
 */
function drive_procesar_imagen(int $comprobanteId, array $c, array $file): void
{
    if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) { return; }

    $mime = function_exists('mime_content_type') ? (mime_content_type($file['tmp_name']) ?: '') : ($file['type'] ?? '');
    $map  = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $ext  = $map[$mime] ?? null;
    if (!$ext || (int)($file['size'] ?? 0) > 15 * 1024 * 1024) {
        if (function_exists('log_app')) {
            log_app('drive.fallo', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
                'detalle' => 'Imagen rechazada (formato/tamaño): ' . $mime]);
        }
        return;
    }

    $prev = [];
    try {
        $prev = q('SELECT drive_file_id, imagen_path FROM comprobantes WHERE id = ? LIMIT 1', [$comprobanteId])->fetch() ?: [];
    } catch (\Throwable $e) {}

    $tmp = (string)$file['tmp_name'];
    try {
        $res = drive_subir_comprobante($c, $tmp, $ext);
    } catch (\Throwable $e) {
        if (function_exists('log_error')) { log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine()); }
        $res = ['error' => $e->getMessage()];
    }

    if (!empty($res['id'])) {
        try {
            drive_comprobante_registrar($comprobanteId, $res, $prev);
        } catch (\Throwable $e) {
            if (function_exists('log_error')) { log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine()); }
        }
        // Aprovecha que Drive anda para subir lo que haya quedado pendiente.
        drive_sincronizar_pendientes(2);
        return;
    }

    // Drive falló: guardar en el servidor para no perder la imagen.
    $motivo = (string)($res['error'] ?? 'motivo desconocido');
    $rel = drive_guardar_local($tmp, $ext);
    if ($rel !== null) {
        try {
            q('UPDATE comprobantes SET imagen_path = ? WHERE id = ?', [$rel, $comprobanteId]);
        } catch (\Throwable $e) {
            if (function_exists('log_error')) { log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine()); }
        }
        if (function_exists('log_app')) {
            log_app('drive.pendiente', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
                'detalle' => 'No se pudo subir a Drive (' . $motivo . '). La imagen quedó guardada en el servidor '
                           . 'y se subirá sola la próxima vez que haya conexión con Drive.']);
        }
        return;
    }
    if (function_exists('log_app')) {
        log_app('drive.fallo', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
            'detalle' => 'No se pudo subir a Drive (' . $motivo . ') ni guardar en el servidor. El comprobante quedó SIN imagen nueva.']);
    }
}

/** Mueve un archivo de Drive a una carpeta destino (quita los padres actuales). */
function drive_mover(string $fileId, string $destId, string $token): bool
{
    $info = drive_http('GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?fields=parents&supportsAllDrives=true',
        null, ['Authorization: Bearer ' . $token]);
    $remove = !empty($info['parents']) ? implode(',', $info['parents']) : '';
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
        . '?addParents=' . rawurlencode($destId)
        . ($remove !== '' ? '&removeParents=' . rawurlencode($remove) : '')
        . '&fields=id&supportsAllDrives=true';
    $res = drive_http('PATCH', $url, '{}',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
    return !empty($res['id']);
}

/**
 * Archiva en Drive un archivo: lo mueve a la carpeta «Archivados» de su ámbito.
 * Espeja el archivado (soft-delete) de la app.
 * @param string $ambito 'compras' → "Facturas Compra/Archivados" (comprobantes)
 *                       'gastos'  → "<raíz gastos>/Archivados"
 */
function drive_archivar(string $fileId, string $ambito = 'compras'): bool
{
    if ($fileId === '' || !drive_conectado()) { return false; }
    $token = drive_access_token();
    if (!$token) { return false; }
    $root = drive_root_ambito($ambito);
    if (!$root) { return false; }
    $arch = drive_folder_id('Archivados', $root, $token);
    if (!$arch) { return false; }
    return drive_mover($fileId, $arch, $token);
}

/**
 * Restaura en Drive el archivo de un comprobante: lo devuelve a
 * "Facturas Compra" / AÑO / "AÑO MM - Mes" (la recrea si hiciera falta).
 */
function drive_restaurar(string $fileId, string $fecha): bool
{
    if ($fileId === '' || !drive_conectado()) { return false; }
    $token = drive_access_token();
    if (!$token) { return false; }
    $parent = drive_compras_root();
    if ($parent === '') { return false; }
    $fAnio = drive_folder_id(substr($fecha, 0, 4), $parent, $token);
    if (!$fAnio) { return false; }
    $fMes = drive_folder_id(drive_carpeta_mes($fecha), $fAnio, $token);
    if (!$fMes) { return false; }
    return drive_mover($fileId, $fMes, $token);
}

// ============================================================
//  Gastos Recurrentes — estructura Proveedor / Año / Mes
// ============================================================

/** ID de la carpeta raíz de Gastos Recurrentes en Drive (configurable). */
function drive_gastos_root(): string
{
    return (string)setting_get('drive_gastos_root', '1wpfeuP0V5QAkSHEyxXqSqfKCCMddJHHG');
}

/**
 * Carpeta del mes de un gasto: <raíz gastos> / Proveedor / AÑO / "AÑO MM - Mes".
 * Crea las que falten (auditado). Devuelve ['id','prov','anio','mes'] o null.
 */
function drive_gasto_carpeta(array $g, string $token): ?array
{
    $root = drive_gastos_root();
    if ($root === '') { return null; }
    // Carpeta del proveedor: usa la carpeta configurada (espeja las del Drive real,
    // ej. "02.EXPENSAS"); si no hay, cae al nombre del proveedor.
    $prov    = drive_sanitizar((string)(!empty($g['prov_drive_folder']) ? $g['prov_drive_folder'] : ($g['prov_nombre'] ?? 'PROVEEDOR')));
    $periodo = (string)($g['periodo'] ?? date('Y-m'));
    $anio    = substr($periodo, 0, 4);
    $mesN    = drive_carpeta_mes($periodo . '-01');
    $c1 = false; $c2 = false; $c3 = false;
    $fProv = drive_folder_id($prov, $root, $token, $c1);
    if (!$fProv) { return null; }
    $fAnio = drive_folder_id($anio, $fProv, $token, $c2);
    if (!$fAnio) { return null; }
    $fMes = drive_folder_id($mesN, $fAnio, $token, $c3);
    if (!$fMes) { return null; }
    if (($c1 || $c2 || $c3) && function_exists('log_app')) {
        log_app('drive.carpeta', ['tipo' => 'accion', 'detalle' => 'Gastos: ' . $prov . ' / ' . $anio . ' / ' . $mesN]);
    }
    return ['id' => $fMes, 'prov' => $prov, 'anio' => $anio, 'mes' => $mesN];
}

/** Nombre del archivo de un gasto: "202607 - 02.EXPENSAS - Factura.pdf" (pura). */
function drive_gasto_nombre(array $g, string $tipo, string $ext): string
{
    $prov    = drive_sanitizar((string)(!empty($g['prov_drive_folder']) ? $g['prov_drive_folder'] : ($g['prov_nombre'] ?? 'PROVEEDOR')));
    $periodo = (string)($g['periodo'] ?? date('Y-m'));
    return drive_sanitizar(str_replace('-', '', $periodo) . ' - ' . $prov . ' - ' . ucfirst($tipo)) . '.' . $ext;
}

/**
 * Sube a Drive un archivo (ruta absoluta) de un gasto y actualiza la fila.
 * Si el gasto ya tenía un archivo de ese tipo en Drive, el anterior se mueve a
 * «Archivados» (reemplazo → sin duplicados en la carpeta del mes).
 * Si había una copia local pendiente de ese tipo, se limpia.
 * @param string $tipo 'factura' | 'pago'
 * @return array ['id','link','ruta'] si OK, o ['error'=>motivo]. Nunca lanza.
 */
function drive_gasto_subir_archivo(int $gastoId, array $g, string $tipo, string $absPath, string $ext): array
{
    $tipo = $tipo === 'pago' ? 'pago' : 'factura';
    if (!drive_conectado()) { return ['error' => 'Drive no conectado en la sesión']; }
    if (!is_file($absPath)) { return ['error' => 'no existe el archivo a subir']; }
    try {
        $token = drive_access_token();
        if (!$token) { return ['error' => 'no se pudo obtener el access token (refresh falló)']; }
        $dest = drive_gasto_carpeta($g, $token);
        if (!$dest) { return ['error' => 'no se pudo crear/acceder la carpeta Proveedor/Año/Mes']; }
        $nombre = drive_gasto_nombre($g, $tipo, $ext);
        $res = drive_subir($absPath, $nombre, $dest['id'], drive_mime_de($ext), $token);
        if (!$res) { return ['error' => 'falló la subida del archivo ' . $nombre]; }
        $id   = (string)$res['id'];
        $link = (string)($res['webViewLink'] ?? ('https://drive.google.com/file/d/' . $id . '/view'));
        $ruta = $dest['prov'] . '/' . $dest['anio'] . '/' . $dest['mes'];
        q("UPDATE gastos_recurrentes SET drive_{$tipo}_id = ?, drive_{$tipo}_link = ? WHERE id = ?", [$id, $link, $gastoId]);
        if (function_exists('log_app')) {
            log_app('gasto.drive_subida', ['tipo' => 'accion', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
                'detalle' => ucfirst($tipo) . ' en Drive: ' . $ruta . ' · ' . $link]);
        }
        // Copia local pendiente de este tipo: ya no hace falta (la columna existe sólo con la migración).
        $pend = (string)($g["{$tipo}_path"] ?? '');
        if ($pend !== '') {
            try { q("UPDATE gastos_recurrentes SET {$tipo}_path = NULL WHERE id = ?", [$gastoId]); } catch (\Throwable $e) {}
            if (drive_es_pendiente($pend) && ($abs = drive_local_abs($pend))) { @unlink($abs); }
        }
        // Reemplazo: el archivo anterior va a «Archivados» de Gastos.
        $prevId = (string)($g["drive_{$tipo}_id"] ?? '');
        if ($prevId !== '' && $prevId !== $id) {
            $ok = drive_archivar($prevId, 'gastos');
            if (function_exists('log_app')) {
                log_app($ok ? 'gasto.drive_reemplazo' : 'gasto.drive_fallo', ['tipo' => $ok ? 'accion' : 'error',
                    'entidad' => 'gasto', 'entidad_id' => $gastoId,
                    'detalle' => $ok ? ucfirst($tipo) . ' anterior reemplazada: movida a «Archivados» en Drive'
                                     : ucfirst($tipo) . ' reemplazada, pero no se pudo mover la anterior a «Archivados»']);
            }
        }
        return ['id' => $id, 'link' => $link, 'ruta' => $ruta . '/' . $nombre];
    } catch (\Throwable $e) {
        if (function_exists('log_error')) {
            log_error('gasto.drive_excepcion', $tipo . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
        }
        return ['error' => $e->getMessage()];
    }
}

/**
 * Sube a Drive el PDF (factura o pago) de un gasto en la estructura
 * <raíz gastos> / Proveedor / Año / "AÑO MM - Mes" / archivo. Crea las carpetas
 * si no existen, actualiza la fila y audita. Si Drive falla, el archivo queda
 * guardado en el servidor como PENDIENTE y se sube solo más adelante. Nunca lanza.
 * @param string $tipo 'factura' | 'pago'
 */
function drive_gasto_subir(int $gastoId, array $g, string $tipo, array $file): void
{
    if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) { return; }
    $tipo = $tipo === 'pago' ? 'pago' : 'factura';
    $mime = function_exists('mime_content_type') ? (mime_content_type($file['tmp_name']) ?: '') : ($file['type'] ?? '');
    $map  = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext  = $map[$mime] ?? null;
    if (!$ext || (int)($file['size'] ?? 0) > 20 * 1024 * 1024) {
        if (function_exists('log_app')) {
            log_app('gasto.drive_fallo', ['tipo' => 'error', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
                'detalle' => ucfirst($tipo) . ': formato/tamaño no permitido (' . $mime . ')']);
        }
        return;
    }

    $tmp = (string)$file['tmp_name'];
    $res = drive_gasto_subir_archivo($gastoId, $g, $tipo, $tmp, $ext);
    if (!empty($res['id'])) {
        drive_sincronizar_pendientes(2);
        return;
    }

    // Drive falló: guardar en el servidor para no perder el archivo.
    $motivo = (string)($res['error'] ?? 'motivo desconocido');
    $rel = drive_guardar_local($tmp, $ext);
    $guardado = false;
    if ($rel !== null) {
        try {
            q("UPDATE gastos_recurrentes SET {$tipo}_path = ? WHERE id = ?", [$rel, $gastoId]);
            $guardado = true;
        } catch (\Throwable $e) {
            // Falta la migración app/db/drive_pendientes.sql (columnas factura_path / pago_path).
            if (function_exists('log_error')) { log_error('gasto.drive_excepcion', $tipo . ': ' . $e->getMessage(), $e->getFile(), $e->getLine()); }
            if ($abs = drive_local_abs($rel)) { @unlink($abs); }
        }
    }
    if (function_exists('log_app')) {
        log_app($guardado ? 'gasto.drive_pendiente' : 'gasto.drive_fallo', ['tipo' => 'error', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
            'detalle' => $guardado
                ? ucfirst($tipo) . ': no se pudo subir a Drive (' . $motivo . '). Quedó guardada en el servidor y se subirá sola la próxima vez que haya conexión con Drive.'
                : ucfirst($tipo) . ': no se pudo subir a Drive (' . $motivo . ') ni guardar en el servidor.']);
    }
}

/**
 * Restaura en Drive los archivos de un gasto: los saca de «Archivados» y los
 * devuelve a <raíz gastos> / Proveedor / AÑO / Mes (recrea si hace falta).
 * Devuelve cuántos archivos se movieron; audita cada uno. Nunca lanza.
 */
function drive_gasto_restaurar(int $gastoId, array $g): int
{
    $n = 0;
    $tipos = array_filter(['factura', 'pago'], static fn($t) => !empty($g["drive_{$t}_id"]));
    if (!$tipos || !drive_conectado()) {
        if ($tipos && function_exists('log_app')) {
            log_app('gasto.drive_fallo', ['tipo' => 'error', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
                'detalle' => 'Restaurado, pero Drive no está conectado: los archivos siguen en «Archivados»']);
        }
        return 0;
    }
    try {
        $token = drive_access_token();
        $dest  = $token ? drive_gasto_carpeta($g, $token) : null;
        foreach ($tipos as $t) {
            $ok = $dest && drive_mover((string)$g["drive_{$t}_id"], $dest['id'], (string)$token);
            if ($ok) { $n++; }
            if (function_exists('log_app')) {
                log_app($ok ? 'gasto.drive_restaurar' : 'gasto.drive_fallo', ['tipo' => $ok ? 'accion' : 'error',
                    'entidad' => 'gasto', 'entidad_id' => $gastoId,
                    'detalle' => $ok ? ucfirst($t) . ' devuelta a ' . $dest['prov'] . '/' . $dest['anio'] . '/' . $dest['mes'] . ' en Drive'
                                     : 'No se pudo devolver la ' . $t . ' a su carpeta en Drive']);
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('log_error')) { log_error('gasto.drive_excepcion', 'restaurar: ' . $e->getMessage(), $e->getFile(), $e->getLine()); }
    }
    return $n;
}

/**
 * Sube a Drive lo que quedó guardado en el servidor como PENDIENTE (comprobantes
 * y gastos) porque Drive había fallado. Se llama al iniciar sesión con Drive y
 * después de cada subida exitosa. Corta en el primer fallo (Drive sigue caído).
 * Devuelve cuántos archivos se subieron. Nunca lanza.
 */
function drive_sincronizar_pendientes(int $max = 5): int
{
    static $corriendo = false;
    if ($corriendo || $max < 1 || !drive_conectado()) { return 0; }
    $corriendo = true;
    $n = 0;
    try {
        // ---- Comprobantes (imagen_path apunta a pendientes-drive/…) ----
        if (function_exists('comp_find')) {
            $rows = [];
            try {
                $rows = q('SELECT id FROM comprobantes WHERE imagen_path LIKE ? AND eliminado_en IS NULL ORDER BY id LIMIT ' . (int)$max,
                    [DRIVE_PENDIENTES_DIR . '/%'])->fetchAll();
            } catch (\Throwable $e) {}
            foreach ($rows as $r) {
                $c   = comp_find((int)$r['id']);
                if (!$c) { continue; }
                $abs = drive_local_abs((string)($c['imagen_path'] ?? ''));
                if (!$abs) {
                    // El archivo ya no está en el servidor: no se puede subir; se deja de reintentar.
                    q('UPDATE comprobantes SET imagen_path = NULL WHERE id = ?', [(int)$c['id']]);
                    if (function_exists('log_app')) {
                        log_app('drive.fallo', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => (int)$c['id'],
                            'detalle' => 'La imagen pendiente de subir a Drive ya no existe en el servidor']);
                    }
                    continue;
                }
                $res = drive_subir_comprobante($c, $abs, strtolower(pathinfo($abs, PATHINFO_EXTENSION)));
                if (empty($res['id'])) { return $n; }
                drive_comprobante_registrar((int)$c['id'], $res, $c);
                if (function_exists('log_app')) {
                    log_app('drive.sincronizado', ['tipo' => 'accion', 'entidad' => 'comprobante', 'entidad_id' => (int)$c['id'],
                        'detalle' => 'Imagen pendiente subida a Drive y borrada del servidor']);
                }
                $n++;
            }
        }
        // ---- Gastos (factura_path / pago_path; requiere la migración) ----
        if ($n < $max && function_exists('gr_find')) {
            $rows = [];
            try {
                $rows = q('SELECT id FROM gastos_recurrentes WHERE (factura_path IS NOT NULL OR pago_path IS NOT NULL)
                           AND eliminado_en IS NULL ORDER BY id LIMIT ' . (int)($max - $n))->fetchAll();
            } catch (\Throwable $e) {}
            foreach ($rows as $r) {
                foreach (['factura', 'pago'] as $t) {
                    $g = gr_find((int)$r['id']);
                    if (!$g || empty($g["{$t}_path"])) { continue; }
                    $abs = drive_local_abs((string)$g["{$t}_path"]);
                    if (!$abs) {
                        q("UPDATE gastos_recurrentes SET {$t}_path = NULL WHERE id = ?", [(int)$r['id']]);
                        if (function_exists('log_app')) {
                            log_app('gasto.drive_fallo', ['tipo' => 'error', 'entidad' => 'gasto', 'entidad_id' => (int)$r['id'],
                                'detalle' => ucfirst($t) . ' pendiente de subir a Drive ya no existe en el servidor']);
                        }
                        continue;
                    }
                    $res = drive_gasto_subir_archivo((int)$r['id'], $g, $t, $abs, strtolower(pathinfo($abs, PATHINFO_EXTENSION)));
                    if (empty($res['id'])) { return $n; }
                    $n++;
                }
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('log_error')) { log_error('drive.excepcion', 'sincronizar: ' . $e->getMessage(), $e->getFile(), $e->getLine()); }
    } finally {
        $corriendo = false;
    }
    return $n;
}

/** Prueba de conexión: accede/crea "Facturas Compra" y la raíz de Gastos. Devuelve [ok, mensaje]. */
function drive_probar(): array
{
    if (!drive_conectado()) {
        return [false, 'No hay ninguna cuenta de Drive conectada.'];
    }
    $token = drive_access_token();
    if (!$token) {
        return [false, 'No se pudo obtener el token de acceso (revisá la conexión).'];
    }
    if (!drive_folder_id('Archivados', drive_compras_root(), $token)) {
        return [false, 'Conectado, pero no pude acceder a la carpeta «' . DRIVE_CARPETA_COMPRAS . '». Verificá el ID y que la cuenta tenga permiso de edición.'];
    }
    if (!drive_folder_id('Archivados', drive_gastos_root(), $token)) {
        return [false, 'Conectado, pero no pude acceder a la carpeta de Gastos. Verificá el ID y el permiso de edición.'];
    }
    return [true, 'Conexión OK. «' . DRIVE_CARPETA_COMPRAS . '» y Gastos accesibles.'];
}
