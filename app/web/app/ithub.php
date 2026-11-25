<?php
declare(strict_types=1);

// ============================================================
//  Ingreso con ITHub (sesión única de Intellihelp, OpenID Connect).
//
//  Con `ithub` configurado (config.php → 'ithub' con client_id y
//  client_secret), la app deja de tener login propio: /login manda a ITHub,
//  ITHub ya validó la cuenta de Google y el dominio, decide si la persona
//  tiene acceso a Comprobantes y con qué rol (claim `rol`: admin | carga), y
//  vuelve a /ithub-callback con un código. Si ya había sesión en ITHub, la
//  persona entra sin ver la pantalla de ingreso.
//
//  Google sigue usándose para lo que no es identidad: el permiso de Google
//  Drive (respaldo de comprobantes y gastos). Como los tokens de Drive viven
//  sólo en la sesión (se revocan al salir), después de entrar con ITHub se
//  pide «Conectar Google Drive» con la misma cuenta de la sesión; si la
//  persona lo cancela, los archivos quedan pendientes en el servidor y el
//  aviso del layout ofrece conectarlo después.
//
//  Salida: logout.php cierra la sesión local y manda a /oauth/logout de
//  ITHub, que cierra la sesión única en todas las apps. Al revés, cuando la
//  sesión se cierra en ITHub (o le quitan la app a la persona), ITHub avisa
//  por detrás a /ithub-logout (back-channel logout): se anota el `sid` en
//  `ithub_sesiones_cerradas` y auth_user() corta cualquier sesión abierta con
//  ese sid en su pedido siguiente.
//
//  Contrato completo: md/INTEGRAR-APLICACION.md del repo ithub-portal.
//  Sin dependencias: el PEM de la clave pública se arma desde el JWK (n, e).
// ============================================================

const ITHUB_EVENTO_LOGOUT = 'http://schemas.openid.net/event/backchannel-logout';
const ITHUB_JWKS_TTL = 3600;
const ITHUB_ROLES = ['admin', 'carga'];

/** Configuración de ITHub ('ithub' en config.php), con el issuer por defecto. */
function ithub_cfg(): array
{
    $c = (array)($GLOBALS['CONFIG']['ithub'] ?? []);
    $c['issuer'] = rtrim((string)($c['issuer'] ?? 'https://ithub.intellihelp.tech'), '/');
    $c['client_id'] = (string)($c['client_id'] ?? '');
    $c['client_secret'] = (string)($c['client_secret'] ?? '');
    return $c;
}

/** ¿El ingreso es por ITHub? Hace falta client_id y client_secret. */
function ithub_habilitado(): bool
{
    $c = ithub_cfg();
    return $c['client_id'] !== '' && $c['client_secret'] !== '';
}

/**
 * URL absoluta dentro de la app. Igual que google_redirect_uri(): siempre
 * HTTPS (detrás del CDN el origen puede reportar http).
 */
function ithub_url_abs(string $ruta): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'intellihelp.tech';
    return 'https://' . $host . url($ruta);
}

/** Dirección de vuelta registrada en ITHub. */
function ithub_redirect_uri(): string
{
    return ithub_url_abs('ithub-callback');
}

/** Base64 URL sin relleno. */
function ithub_b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function ithub_b64url_dec(string $txt): string
{
    return (string)base64_decode(strtr($txt, '-_', '+/') . str_repeat('=', (4 - strlen($txt) % 4) % 4), true);
}

/**
 * Arma la dirección de /oauth/authorize y deja en la sesión el state, el
 * nonce y el verificador de PKCE (S256), que valida ithub-callback.
 */
function ithub_url_autorizacion(): string
{
    $c = ithub_cfg();
    $_SESSION['ithub_state'] = bin2hex(random_bytes(16));
    $_SESSION['ithub_nonce'] = bin2hex(random_bytes(16));
    $_SESSION['ithub_ver'] = ithub_b64url(random_bytes(48));
    return $c['issuer'] . '/oauth/authorize?' . http_build_query([
        'client_id'             => $c['client_id'],
        'redirect_uri'          => ithub_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $_SESSION['ithub_state'],
        'nonce'                 => $_SESSION['ithub_nonce'],
        'code_challenge'        => ithub_b64url(hash('sha256', $_SESSION['ithub_ver'], true)),
        'code_challenge_method' => 'S256',
    ]);
}

/**
 * Pedido HTTP a ITHub. Devuelve [código, cuerpo decodificado]. Sin conexión,
 * [0, []]. Nunca deja el secreto en un log.
 */
function ithub_http(string $url, ?array $post = null, int $timeout = 10): array
{
    $c = ithub_cfg();
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        $opts[CURLOPT_USERPWD] = rawurlencode($c['client_id']) . ':' . rawurlencode($c['client_secret']);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$codigo, is_string($res) ? (json_decode($res, true) ?: []) : []];
}

/** Canjea el código de autorización por los tokens (con el verificador de PKCE). */
function ithub_canjear_codigo(string $code, string $verificador): array
{
    [, $r] = ithub_http(ithub_cfg()['issuer'] . '/oauth/token', [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => ithub_redirect_uri(),
        'code_verifier' => $verificador,
    ]);
    return $r;
}

// ---- Claves públicas ----

/** Largo DER (forma corta o larga). */
function ithub_der_largo(int $n): string
{
    if ($n < 0x80) {
        return chr($n);
    }
    $b = ltrim(pack('N', $n), "\0");
    return chr(0x80 | strlen($b)) . $b;
}

function ithub_der(int $tag, string $valor): string
{
    return chr($tag) . ithub_der_largo(strlen($valor)) . $valor;
}

/** Entero DER positivo (se antepone un 0 si el primer bit está prendido). */
function ithub_der_entero(string $bin): string
{
    $bin = ltrim($bin, "\0");
    if ($bin === '' || ord($bin[0]) > 0x7f) {
        $bin = "\0" . $bin;
    }
    return ithub_der(0x02, $bin);
}

/** PEM de una clave pública RSA a partir del JWK (n, e en base64url). */
function ithub_jwk_a_pem(array $jwk): string
{
    $rsa = ithub_der(0x30, ithub_der_entero(ithub_b64url_dec((string)($jwk['n'] ?? '')))
                         . ithub_der_entero(ithub_b64url_dec((string)($jwk['e'] ?? ''))));
    $alg = ithub_der(0x30, ithub_der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00");
    $spki = ithub_der(0x30, $alg . ithub_der(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Archivo de caché del JWKS (fuera del webroot, en STORAGE). */
function ithub_jwks_archivo(): ?string
{
    if (!defined('STORAGE')) {
        return null;
    }
    $dir = STORAGE . '/cache-ithub';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return is_dir($dir) ? $dir . '/jwks.json' : null;
}

/**
 * Claves de ITHub por kid. Se guardan una hora; si el kid pedido no está
 * (ITHub rotó la clave), se vuelven a bajar una vez.
 *
 * @return array<string,array> kid → JWK
 */
function ithub_jwks(string $kid = ''): array
{
    $archivo = ithub_jwks_archivo();
    if ($archivo !== null && is_file($archivo) && filemtime($archivo) > time() - ITHUB_JWKS_TTL) {
        $claves = json_decode((string)file_get_contents($archivo), true) ?: [];
        if ($claves !== [] && ($kid === '' || isset($claves[$kid]))) {
            return $claves;
        }
    }
    [$codigo, $r] = ithub_http(ithub_cfg()['issuer'] . '/oauth/jwks');
    $claves = [];
    foreach ((array)($codigo === 200 ? ($r['keys'] ?? []) : []) as $k) {
        if (is_array($k) && ($k['kty'] ?? '') === 'RSA' && isset($k['kid'], $k['n'], $k['e'])) {
            $claves[(string)$k['kid']] = $k;
        }
    }
    if ($claves !== [] && $archivo !== null) {
        @file_put_contents($archivo, json_encode($claves), LOCK_EX);
    }
    return $claves;
}

/**
 * Valida un JWT de ITHub (id_token o logout_token) y devuelve sus claims.
 *
 * Comprueba: RS256, firma con la clave del kid, iss, aud = nuestro
 * client_id, vencimiento (60 s de tolerancia) y, si se pasa, el nonce.
 * Un logout_token además tiene que traer el evento de back-channel logout
 * y no puede traer nonce.
 *
 * @param array<string,array>|null $claves para los tests; null = las de ITHub
 * @throws RuntimeException con un motivo corto (va al log, nunca a pantalla)
 */
function ithub_validar_jwt(string $jwt, ?string $nonce = null, bool $esLogout = false, ?array $claves = null): array
{
    $partes = explode('.', $jwt);
    if (count($partes) !== 3) {
        throw new RuntimeException('formato');
    }
    [$h, $p, $s] = $partes;
    $cab = json_decode(ithub_b64url_dec($h), true);
    $c = json_decode(ithub_b64url_dec($p), true);
    if (!is_array($cab) || !is_array($c)) {
        throw new RuntimeException('formato');
    }
    if (($cab['alg'] ?? '') !== 'RS256') {
        throw new RuntimeException('alg');
    }
    $kid = (string)($cab['kid'] ?? '');
    $claves ??= ithub_jwks($kid);
    if (!isset($claves[$kid])) {
        throw new RuntimeException('kid');
    }
    if (openssl_verify("$h.$p", ithub_b64url_dec($s), ithub_jwk_a_pem($claves[$kid]), OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('firma');
    }
    $cfg = ithub_cfg();
    $aud = $c['aud'] ?? '';
    $audOk = is_array($aud) ? in_array($cfg['client_id'], $aud, true) : $aud === $cfg['client_id'];
    if (($c['iss'] ?? '') !== $cfg['issuer'] || !$audOk) {
        throw new RuntimeException('iss/aud');
    }
    if ((int)($c['exp'] ?? 0) < time() - 60) {
        throw new RuntimeException('vencido');
    }
    if ($esLogout) {
        if (!isset($c['events'][ITHUB_EVENTO_LOGOUT]) || isset($c['nonce']) || (empty($c['sid']) && empty($c['sub']))) {
            throw new RuntimeException('logout_token');
        }
    } elseif ($nonce !== null && !hash_equals($nonce, (string)($c['nonce'] ?? ''))) {
        throw new RuntimeException('nonce');
    }
    return $c;
}

// ---- Usuario local ----

/**
 * Busca (o crea) el usuario de la app para la persona que mandó ITHub y le
 * aplica el rol que trae el token: ITHub es quien decide los permisos.
 *
 * @return array|null el usuario, o null si el rol no es uno de la app
 */
function ithub_usuario_local(array $claims): ?array
{
    $email = strtolower(trim((string)($claims['email'] ?? '')));
    $rol = (string)($claims['rol'] ?? '');
    if ($email === '' || !in_array($rol, ITHUB_ROLES, true)) {
        return null;
    }
    $nombre = trim((string)($claims['name'] ?? '')) ?: $email;
    $foto = (string)($claims['picture'] ?? '');
    $u = find_user_by_email($email);
    if ($u === null) {
        $id = provision_user($email, $nombre, $rol);
    } else {
        $id = (int)$u['id'];
        if ($u['rol'] !== $rol || (int)$u['activo'] !== 1) {
            log_app('usuario.rol_ithub', ['tipo' => 'auth', 'usuario_id' => $id, 'entidad' => 'usuario', 'entidad_id' => $id,
                'detalle' => "Rol y estado tomados de ITHub: {$u['rol']} → $rol"]);
        }
    }
    q('UPDATE usuarios SET nombre = ?, avatar_url = ?, rol = ?, activo = 1 WHERE id = ?',
      [$nombre, $foto !== '' ? $foto : null, $rol, $id]);
    return find_user_by_email($email);
}

// ---- Cierre por detrás (back-channel logout) ----

/** Anota que la sesión de ITHub `sid` se cerró: las de la app con ese sid caen. */
function ithub_cerrar_sid(string $sid): void
{
    q('INSERT INTO ithub_sesiones_cerradas (sid, cerrada_en) VALUES (?, NOW())
       ON DUPLICATE KEY UPDATE cerrada_en = NOW()', [$sid]);
    // Limpieza: con la sesión máxima de la app (8 h) nada más viejo que un día sirve.
    q('DELETE FROM ithub_sesiones_cerradas WHERE cerrada_en < NOW() - INTERVAL 2 DAY');
}

/** ¿La sesión de ITHub `sid` ya se cerró? */
function ithub_sid_cerrado(string $sid): bool
{
    return (bool)q('SELECT sid FROM ithub_sesiones_cerradas WHERE sid = ? LIMIT 1', [$sid])->fetch();
}

/**
 * Dirección de salida de ITHub: cierra la sesión única y vuelve al login de
 * la app. Con el id_token como pista, ITHub no pregunta.
 */
function ithub_url_salida(?string $idToken): string
{
    $q = ['post_logout_redirect_uri' => ithub_url_abs('login'), 'client_id' => ithub_cfg()['client_id']];
    if ($idToken) {
        $q['id_token_hint'] = $idToken;
    }
    return ithub_cfg()['issuer'] . '/oauth/logout?' . http_build_query($q);
}
