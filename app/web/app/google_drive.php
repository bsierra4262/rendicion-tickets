<?php
declare(strict_types=1);

// ============================================================
//  Google Drive — respaldo automático de imágenes de comprobantes
//
//  · OAuth "offline" (refresh token) para subir en nombre de la cuenta
//    conectada por el administrador. Reutiliza el mismo redirect que el login
//    (google-callback), así NO hay que registrar otra URI en Google Cloud.
//  · Estructura de carpetas:  <carpeta raíz> / AÑO / "AÑO MM - Mes" / archivo.
//  · La DB guarda el link de Drive; la copia LOCAL siempre se conserva como
//    respaldo, de modo que el guardado del comprobante NUNCA se rompe.
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

/** ID de la carpeta raíz en Drive (configurable; por defecto la del cliente). */
function drive_parent_id(): string
{
    return (string)setting_get('drive_parent_id', '12y0-4kU2A4YlG1eZl2Dy-SxylR-h9mgY');
}

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
    $nombreQ = str_replace("'", "\\'", $nombre);
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
        return (string)$res['files'][0]['id'];
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
        return (string)$res['id'];
    }
    return null;
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
    $parent = drive_parent_id();
    if ($parent === '') { return ['error' => 'no hay carpeta raíz configurada']; }

    $fecha = (string)($c['fecha_emision'] ?? date('Y-m-d'));
    $anio  = substr($fecha, 0, 4);
    $mesN  = drive_carpeta_mes($fecha);

    $creadaA = false; $creadaM = false;
    $fAnio = drive_folder_id($anio, $parent, $token, $creadaA);
    if (!$fAnio) { return ['error' => 'no se pudo crear/acceder la carpeta ' . $anio]; }
    $fMes = drive_folder_id($mesN, $fAnio, $token, $creadaM);
    if (!$fMes) { return ['error' => 'no se pudo crear/acceder la carpeta ' . $mesN]; }
    if (($creadaA || $creadaM) && function_exists('log_app')) {
        log_app('drive.carpeta', ['tipo' => 'accion',
            'detalle' => 'Carpeta creada en Drive: ' . $anio . ' / ' . $mesN]);
    }

    $nombre = drive_nombre_archivo($c, $ext);
    $res = drive_subir($absPath, $nombre, $fMes, drive_mime_de($ext), $token);
    if (!$res) { return ['error' => 'falló la subida del archivo ' . $nombre]; }
    $id = (string)$res['id'];
    return [
        'id'   => $id,
        'link' => (string)($res['webViewLink'] ?? ('https://drive.google.com/file/d/' . $id . '/view')),
        'ruta' => $anio . '/' . $mesN . '/' . $nombre,
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
 * Respalda la imagen de un comprobante recién guardado subiéndola a Drive
 * (SIN copia local). Registra cada paso en la auditoría (CRON). Nunca lanza:
 * si Drive falla, deja el comprobante sin imagen y un error claro en el CRON.
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

    try {
        $res = drive_subir_comprobante($c, (string)$file['tmp_name'], $ext);
        if (!empty($res['id'])) {
            q('UPDATE comprobantes SET drive_file_id = ?, drive_link = ?, imagen_path = NULL WHERE id = ?',
                [$res['id'], $res['link'], $comprobanteId]);
            if (function_exists('log_app')) {
                log_app('drive.subida', ['tipo' => 'accion', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
                    'detalle' => 'Imagen en Drive: ' . ($res['ruta'] ?? '') . ' · ' . $res['link']]);
            }
        } else {
            if (function_exists('log_app')) {
                log_app('drive.fallo', ['tipo' => 'error', 'entidad' => 'comprobante', 'entidad_id' => $comprobanteId,
                    'detalle' => 'No se pudo subir a Drive (' . ($res['error'] ?? 'motivo desconocido') . '). El comprobante quedó SIN imagen.']);
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('log_error')) {
            log_error('drive.excepcion', $e->getMessage(), $e->getFile(), $e->getLine());
        }
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
 * Archiva en Drive el archivo de un comprobante: lo mueve a la carpeta
 * "Archivados" (bajo la raíz). Espeja el archivado (soft-delete) de la app.
 */
function drive_archivar(string $fileId): bool
{
    if ($fileId === '' || !drive_conectado()) { return false; }
    $token = drive_access_token();
    if (!$token) { return false; }
    $arch = drive_folder_id('Archivados', drive_parent_id(), $token);
    if (!$arch) { return false; }
    return drive_mover($fileId, $arch, $token);
}

/**
 * Restaura en Drive el archivo: lo devuelve a su carpeta AÑO / "AÑO MM - Mes"
 * (la recrea si hiciera falta), sacándolo de "Archivados".
 */
function drive_restaurar(string $fileId, string $fecha): bool
{
    if ($fileId === '' || !drive_conectado()) { return false; }
    $token = drive_access_token();
    if (!$token) { return false; }
    $parent = drive_parent_id();
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
 * Sube a Drive el PDF (factura o pago) de un gasto en la estructura
 * <raíz gastos> / Proveedor / Año / "AÑO MM - Mes" / archivo. Crea las carpetas
 * si no existen, actualiza la fila y audita. Nunca lanza.
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
    if (!drive_conectado()) {
        if (function_exists('log_app')) {
            log_app('gasto.drive_fallo', ['tipo' => 'error', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
                'detalle' => 'Drive no conectado en la sesión; ' . $tipo . ' no subida']);
        }
        return;
    }
    try {
        $token = drive_access_token();
        if (!$token) { throw new \RuntimeException('sin access token'); }
        $root    = drive_gastos_root();
        $prov    = drive_sanitizar((string)($g['prov_nombre'] ?? 'PROVEEDOR'));
        $periodo = (string)($g['periodo'] ?? date('Y-m'));
        $anio    = substr($periodo, 0, 4);
        $mesN    = drive_carpeta_mes($periodo . '-01');
        $c1 = false; $c2 = false; $c3 = false;
        $fProv = drive_folder_id($prov, $root, $token, $c1);
        if (!$fProv) { throw new \RuntimeException('carpeta proveedor'); }
        $fAnio = drive_folder_id($anio, $fProv, $token, $c2);
        if (!$fAnio) { throw new \RuntimeException('carpeta año'); }
        $fMes = drive_folder_id($mesN, $fAnio, $token, $c3);
        if (!$fMes) { throw new \RuntimeException('carpeta mes'); }
        if (($c1 || $c2 || $c3) && function_exists('log_app')) {
            log_app('drive.carpeta', ['tipo' => 'accion', 'detalle' => 'Gastos: ' . $prov . ' / ' . $anio . ' / ' . $mesN]);
        }
        $nombre = drive_sanitizar(str_replace('-', '', $periodo) . ' - ' . $prov . ' - ' . ucfirst($tipo)) . '.' . $ext;
        $res = drive_subir((string)$file['tmp_name'], $nombre, $fMes, drive_mime_de($ext), $token);
        if (!$res) { throw new \RuntimeException('subida'); }
        $id   = (string)$res['id'];
        $link = (string)($res['webViewLink'] ?? ('https://drive.google.com/file/d/' . $id . '/view'));
        q("UPDATE gastos_recurrentes SET drive_{$tipo}_id = ?, drive_{$tipo}_link = ? WHERE id = ?", [$id, $link, $gastoId]);
        if (function_exists('log_app')) {
            log_app('gasto.drive_subida', ['tipo' => 'accion', 'entidad' => 'gasto', 'entidad_id' => $gastoId,
                'detalle' => ucfirst($tipo) . ' en Drive: ' . $prov . '/' . $anio . '/' . $mesN . ' · ' . $link]);
        }
    } catch (\Throwable $e) {
        if (function_exists('log_error')) {
            log_error('gasto.drive_excepcion', $tipo . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
}

/** Prueba de conexión: lista/crea la carpeta raíz. Devuelve [ok, mensaje]. */
function drive_probar(): array
{
    if (!drive_conectado()) {
        return [false, 'No hay ninguna cuenta de Drive conectada.'];
    }
    $token = drive_access_token();
    if (!$token) {
        return [false, 'No se pudo obtener el token de acceso (revisá la conexión).'];
    }
    $parent = drive_parent_id();
    $anio = date('Y');
    $f = drive_folder_id($anio, $parent, $token);
    if (!$f) {
        return [false, 'Conectado, pero no pude acceder/crear la carpeta raíz. Verificá el ID y que la cuenta tenga permiso de edición.'];
    }
    return [true, 'Conexión OK. Carpeta del año «' . $anio . '» accesible.'];
}
