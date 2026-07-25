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

/** ¿Hay una cuenta de Drive conectada (tenemos refresh token)? */
function drive_conectado(): bool
{
    return (string)setting_get('drive_refresh_token', '') !== '';
}

// ---- OAuth offline (consentimiento para Drive) ----
function drive_auth_url(string $state): string
{
    $g = google_cfg();
    $params = [
        'client_id'              => $g['client_id'] ?? '',
        'redirect_uri'           => google_redirect_uri(), // mismo que el login
        'response_type'          => 'code',
        'scope'                  => 'openid email ' . DRIVE_SCOPE,
        'state'                  => $state,
        'access_type'            => 'offline',
        'prompt'                 => 'consent',             // fuerza refresh_token
        'include_granted_scopes' => 'true',
    ];
    if (!empty($g['allowed_domain'])) {
        $params['hd'] = $g['allowed_domain'];
    }
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/** Intercambia el "code" por tokens (incluye refresh_token). */
function drive_exchange_code(string $code): array
{
    $g = google_cfg();
    $body = http_build_query([
        'code'          => $code,
        'client_id'     => $g['client_id'] ?? '',
        'client_secret' => $g['client_secret'] ?? '',
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    return drive_http('POST', 'https://oauth2.googleapis.com/token', $body,
        ['Content-Type: application/x-www-form-urlencoded']);
}

/** Devuelve un access token válido (lo refresca y cachea si hace falta). */
function drive_access_token(): ?string
{
    $tok = (string)setting_get('drive_access_token', '');
    $exp = (int)setting_get('drive_access_expira', '0');
    if ($tok !== '' && $exp > time() + 60) {
        return $tok;
    }
    $rt = (string)setting_get('drive_refresh_token', '');
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
    setting_set('drive_access_token', (string)$res['access_token']);
    setting_set('drive_access_expira', (string)(time() + (int)($res['expires_in'] ?? 3600)));
    return (string)$res['access_token'];
}

/** Revoca y olvida la conexión de Drive. */
function drive_desconectar(): void
{
    $rt = (string)setting_get('drive_refresh_token', '');
    if ($rt !== '') {
        drive_http('POST', 'https://oauth2.googleapis.com/revoke', http_build_query(['token' => $rt]),
            ['Content-Type: application/x-www-form-urlencoded']);
    }
    foreach (['drive_refresh_token', 'drive_access_token', 'drive_access_expira'] as $k) {
        setting_del($k);
    }
}

// ---- HTTP helper (curl → array) ----
function drive_http(string $method, string $url, $body = null, array $headers = []): array
{
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
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

/** Busca (o crea) una carpeta por nombre bajo un padre. Devuelve su id o null. */
function drive_folder_id(string $nombre, string $parentId, string $token): ?string
{
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
    return !empty($res['id']) ? (string)$res['id'] : null;
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
 * Orquesta la subida de la imagen de un comprobante a Drive.
 * @param array  $c        fila del comprobante (con fecha_emision, tipo, etc.)
 * @param string $localRel ruta relativa dentro de uploads/ (ej. "2026/07/xx.jpg")
 * @return array|null      ['id'=>..., 'link'=>...] o null si no se pudo.
 */
function drive_subir_comprobante(array $c, string $localRel): ?array
{
    if (!drive_conectado()) {
        return null;
    }
    $token = drive_access_token();
    if (!$token) {
        return null;
    }
    $abs = STORAGE . '/uploads/' . $localRel;
    if (!is_file($abs)) {
        return null;
    }
    $parent = drive_parent_id();
    if ($parent === '') {
        return null;
    }
    $fecha = (string)($c['fecha_emision'] ?? date('Y-m-d'));
    $anio  = substr($fecha, 0, 4);
    $fAnio = drive_folder_id($anio, $parent, $token);
    if (!$fAnio) { return null; }
    $fMes = drive_folder_id(drive_carpeta_mes($fecha), $fAnio, $token);
    if (!$fMes) { return null; }
    $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) ?: 'jpg';
    $nombre = drive_nombre_archivo($c, $ext);
    $res = drive_subir($abs, $nombre, $fMes, drive_mime_de($ext), $token);
    if (!$res) { return null; }
    $id = (string)$res['id'];
    return ['id' => $id, 'link' => (string)($res['webViewLink'] ?? ('https://drive.google.com/file/d/' . $id . '/view'))];
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
